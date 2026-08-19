<?php

namespace App\Console\Commands;

use App\Dtos\HealthSnapshot;
use App\Enums\HealthStatus;
use App\Mail\HealthAlertMail;
use App\Services\Health\HealthFormat;
use App\Services\Health\HealthMarkers;
use App\Services\Health\HostFacts;
use App\Services\Health\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails an operator when system health changes for the worse, and again when
 * it recovers.
 *
 * Four rules do all the work:
 *
 *  1. NOTHING ALERTS ON A SINGLE OBSERVATION. A deploy restarting PHP-FPM or the
 *     queue worker resolves well inside one interval; requiring two consecutive
 *     non-OK observations turns that into silence, while a real outage still
 *     reaches an inbox within roughly ten minutes.
 *  2. FAIL TOWARD ALERTING. If the suppression state itself cannot be read or
 *     written, suppression is skipped and the alert goes out. A cache outage is
 *     exactly the correlated failure this command exists to report, so its own
 *     broken state must never be the reason it stays quiet.
 *  3. SILENCE MUST BE EARNED. While a problem persists, the same alert repeats
 *     every realert_hours; a new failing check re-alerts immediately; and a
 *     return to green always sends a recovery notice, because a silent recovery
 *     leaves you unsure whether it healed or the alerter died.
 *  4. MAIL FAILURES NEVER THROW and never record a notification, so the next
 *     tick retries rather than swallowing the alert.
 *
 * Honest limit: if the droplet, the network or SMTP is down, none of this
 * fires. That is what the external uptime monitor is for.
 */
class SendHealthAlert extends Command
{
    protected $signature = 'health:alert {--force : Send the current status regardless of state}';

    protected $description = 'Email operators when system health changes for the worse, or recovers.';

    public function handle(SystemHealthService $health, HostFacts $facts): int
    {
        // A reboot wipes the tmpfs facts file, so for the first minute or two
        // every host-derived check reports no data and the system is
        // indistinguishable from an outage. Suppression by consecutive
        // observations does not help: an escalation inside an existing episode
        // alerts immediately, which is exactly what happened in production.
        // Hold until the host has had a chance to describe itself.
        if (! $this->option('force') && $facts->withinBootGrace()) {
            $this->comment('Host booted '.HealthFormat::age((int) $facts->bootedSecondsAgo()).' ago — holding alerts until the first facts write.');

            return self::SUCCESS;
        }

        $snapshot = $health->snapshot();

        [$state, $stateReadable] = $this->readState();

        if ($snapshot->overall === HealthStatus::OK && ! $this->option('force')) {
            return $this->handleRecovery($snapshot, $state);
        }

        $consecutive = (int) ($state['consecutive_non_ok'] ?? 0) + 1;

        // Rule 1 — but rule 2 wins: unreadable state means no suppression.
        if ($stateReadable && $consecutive < 2 && ! $this->option('force')) {
            $this->comment("Non-OK observed once ({$snapshot->overall->value}) — holding one interval to rule out a deploy blip.");
            $this->writeState($snapshot, $state, $consecutive, notified: false);

            return self::SUCCESS;
        }

        $reason = $this->option('force')
            ? 'Manual send.'
            : $this->reasonToNotify($snapshot, $stateReadable ? $state : null);

        if ($reason === null) {
            $this->info('Already reported, nothing new — staying quiet.');
            $this->writeState($snapshot, $state, $consecutive, notified: false);

            return self::SUCCESS;
        }

        $sent = $this->send(new HealthAlertMail(
            snapshot: $snapshot,
            reason: $reason,
            episodeStartedAt: $state['episode_started_at'] ?? null,
        ));

        // Rule 4: an unsent alert is not a sent alert. Leaving notified_at
        // untouched makes the next tick try again.
        $this->writeState($snapshot, $state, $consecutive, notified: $sent);

        $this->info($sent
            ? "Alert sent: {$reason}"
            : 'Alert could not be sent — will retry on the next run.');

        return self::SUCCESS;
    }

    /** Rule 3 — a recovery nobody hears about is indistinguishable from a dead alerter. */
    private function handleRecovery(HealthSnapshot $snapshot, ?array $state): int
    {
        if ($state === null || ($state['notified_at'] ?? null) === null) {
            // Nothing was ever reported, so there is nothing to un-report.
            $this->clearState();
            $this->info('Healthy — nothing to report.');

            return self::SUCCESS;
        }

        $duration = isset($state['episode_started_at'])
            ? HealthFormat::age(max(0, now()->getTimestamp() - (int) $state['episode_started_at']))
            : 'an unknown period';

        $this->send(new HealthAlertMail(
            snapshot: $snapshot,
            reason: "Everything recovered after {$duration}.",
            isRecovery: true,
            episodeStartedAt: $state['episode_started_at'] ?? null,
        ));

        $this->clearState();
        $this->info('Recovery notice sent.');

        return self::SUCCESS;
    }

    /**
     * Why this observation deserves an email — or null for "you already know".
     */
    private function reasonToNotify(HealthSnapshot $snapshot, ?array $state): ?string
    {
        if ($state === null || ($state['notified_at'] ?? null) === null) {
            return 'New problem detected.';
        }

        $notifiedStatus = HealthStatus::tryFrom((string) ($state['notified_status'] ?? '')) ?? HealthStatus::OK;

        if ($snapshot->overall->severity() > $notifiedStatus->severity()) {
            return "Escalated from {$notifiedStatus->value} to {$snapshot->overall->value}.";
        }

        $newKeys = $this->newFailingKeys($snapshot, (string) ($state['notified_signature'] ?? ''));

        if ($newKeys !== []) {
            return 'New failing check'.(count($newKeys) === 1 ? '' : 's').': '.implode(', ', $newKeys).'.';
        }

        $since = now()->getTimestamp() - (int) $state['notified_at'];
        $interval = max(1, (int) config('health.realert_hours', 6)) * 3600;

        if ($since >= $interval) {
            return 'Still failing after '.HealthFormat::age($since).'.';
        }

        return null;
    }

    /**
     * Checks failing now that were not in the last thing we sent. A signature
     * that lost entries is a partial recovery, which is not news — the re-alert
     * timer covers it.
     *
     * @return array<int, string>
     */
    private function newFailingKeys(HealthSnapshot $snapshot, string $notifiedSignature): array
    {
        $previous = [];

        foreach (array_filter(explode('|', $notifiedSignature)) as $entry) {
            $previous[] = explode(':', $entry)[0];
        }

        $current = array_map(fn ($check) => $check->key, $snapshot->failing());

        return array_values(array_diff($current, $previous));
    }

    private function send(HealthAlertMail $mail): bool
    {
        $recipients = (array) config('health.alert_recipients', []);

        if ($recipients === []) {
            $this->warn('No HEALTH_ALERT_RECIPIENTS configured — nothing sent.');

            return false;
        }

        try {
            Mail::to($recipients)->send($mail);

            return true;
        } catch (Throwable $e) {
            Log::error('Health alert could not be sent', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{0: ?array, 1: bool} the state, and whether it could be read
     *                                   at all (false disables suppression)
     */
    private function readState(): array
    {
        try {
            $state = HealthMarkers::store()->get(HealthMarkers::ALERT_STATE);

            return [is_array($state) ? $state : null, true];
        } catch (Throwable $e) {
            Log::warning('Health alert state unreadable — suppression skipped', ['exception' => $e::class]);

            return [null, false];
        }
    }

    private function writeState(HealthSnapshot $snapshot, ?array $previous, int $consecutive, bool $notified): void
    {
        try {
            HealthMarkers::store()->forever(HealthMarkers::ALERT_STATE, [
                'signature' => $snapshot->signature(),
                'status' => $snapshot->overall->value,
                'consecutive_non_ok' => $consecutive,
                'episode_started_at' => $previous['episode_started_at'] ?? now()->getTimestamp(),
                'notified_at' => $notified ? now()->getTimestamp() : ($previous['notified_at'] ?? null),
                'notified_signature' => $notified ? $snapshot->signature() : ($previous['notified_signature'] ?? null),
                'notified_status' => $notified ? $snapshot->overall->value : ($previous['notified_status'] ?? null),
            ]);
        } catch (Throwable $e) {
            Log::warning('Health alert state could not be written', ['exception' => $e::class]);
        }
    }

    private function clearState(): void
    {
        try {
            HealthMarkers::store()->forget(HealthMarkers::ALERT_STATE);
        } catch (Throwable $e) {
            Log::warning('Health alert state could not be cleared', ['exception' => $e::class]);
        }
    }
}
