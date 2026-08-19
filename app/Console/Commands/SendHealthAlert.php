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
 * A check's NODE decides whether it can trigger any of this. Nodes listed in
 * health.alert_excluded_nodes — currently `platform`, which is d1-d4 and X1/X2 —
 * are reported everywhere else but never alerted on; deps:digest owns them
 * weekly. Every decision below therefore reads alertStatus()/alertable(), never
 * overall/failing(): `overall` stays honest for the page and the pill.
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

        // alertStatus(), not overall: checks on an alert-excluded node (d1-d4,
        // X1/X2) are reported on the page and in the CLI but are owned by the
        // weekly deps:digest. A platform-only problem is "all quiet" here.
        if ($snapshot->alertStatus() === HealthStatus::OK && ! $this->option('force')) {
            return $this->handleRecovery($snapshot, $state);
        }

        $consecutive = (int) ($state['consecutive_non_ok'] ?? 0) + 1;

        // Rule 1 — but rule 2 wins: state we cannot read OR WRITE means no
        // suppression. A store that accepts reads and silently drops writes
        // would otherwise hold this branch forever and silence the alerter
        // permanently, without ever saying so.
        if ($stateReadable && $consecutive < 2 && ! $this->option('force')) {
            if ($this->writeState($snapshot, $state, $consecutive, notified: false)) {
                $this->comment("Non-OK observed once ({$snapshot->alertStatus()->value}) — holding one interval to rule out a deploy blip.");

                return self::SUCCESS;
            }

            $this->warn('Alert state is not persistable — suppression cannot be trusted, alerting anyway.');
        }

        $notify = $this->option('force')
            ? ['text' => 'Manual send.', 'urgent' => true]
            : $this->reasonToNotify($snapshot, $stateReadable ? $state : null);

        $reason = $notify['text'] ?? null;

        if ($reason === null) {
            $this->info('Already reported, nothing new — staying quiet.');
            $this->writeState($snapshot, $state, $consecutive, notified: false);

            return self::SUCCESS;
        }

        // A floor under the flood vector only. Two checks straddling their
        // thresholds and alternating produce a fresh "new failing check" on
        // every run, which would otherwise be an email every five minutes
        // forever. But an ESCALATION — the system got worse — is exactly what
        // you want to hear immediately, so it is never delayed.
        $floor = max(0, (int) config('health.min_notify_interval_minutes', 15)) * 60;
        $lastSent = (int) ($state['last_sent_at'] ?? 0);

        if (! ($notify['urgent'] ?? false) && $lastSent > 0 && (now()->getTimestamp() - $lastSent) < $floor) {
            $this->comment('Within the minimum notify interval — holding "'.$reason.'".');
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

        $sent = $this->send(new HealthAlertMail(
            snapshot: $snapshot,
            reason: "Everything recovered after {$duration}.",
            isRecovery: true,
            episodeStartedAt: $state['episode_started_at'] ?? null,
        ));

        if (! $sent) {
            // Rule 4 applies to recovery too: clearing the episode here would
            // lose the notice permanently, leaving the operator believing the
            // system is still broken.
            $this->warn('Recovery notice could not be sent — keeping the episode open to retry.');

            return self::SUCCESS;
        }

        $this->clearState();
        $this->info('Recovery notice sent.');

        return self::SUCCESS;
    }

    /**
     * Why this observation deserves an email — or null for "you already know".
     *
     * `urgent` marks the reasons that bypass the minimum-interval floor: the
     * first notice of an episode, and any increase in severity.
     *
     * @return array{text: string, urgent: bool}|null
     */
    private function reasonToNotify(HealthSnapshot $snapshot, ?array $state): ?array
    {
        if ($state === null || ($state['notified_at'] ?? null) === null) {
            return ['text' => 'New problem detected.', 'urgent' => true];
        }

        $notifiedStatus = HealthStatus::tryFrom((string) ($state['notified_status'] ?? '')) ?? HealthStatus::OK;

        if ($snapshot->alertStatus()->severity() > $notifiedStatus->severity()) {
            return [
                'text' => "Escalated from {$notifiedStatus->value} to {$snapshot->alertStatus()->value}.",
                'urgent' => true,
            ];
        }

        $newKeys = $this->newFailingKeys($snapshot, (string) ($state['notified_signature'] ?? ''));

        if ($newKeys !== []) {
            return [
                'text' => 'New failing check'.(count($newKeys) === 1 ? '' : 's').': '.implode(', ', $newKeys).'.',
                'urgent' => false,   // the alternating-checks flood vector
            ];
        }

        $since = now()->getTimestamp() - (int) $state['notified_at'];
        $interval = max(1, (int) config('health.realert_hours', 6)) * 3600;

        if ($since >= $interval) {
            return ['text' => 'Still failing after '.HealthFormat::age($since).'.', 'urgent' => false];
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

        $current = array_map(fn ($check) => $check->key, $snapshot->alertable());

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

    /** @return bool whether the state was actually persisted */
    private function writeState(HealthSnapshot $snapshot, ?array $previous, int $consecutive, bool $notified): bool
    {
        try {
            HealthMarkers::store()->forever(HealthMarkers::ALERT_STATE, [
                'last_sent_at' => $notified ? now()->getTimestamp() : ($previous['last_sent_at'] ?? null),
                'signature' => $snapshot->signature(),
                'status' => $snapshot->alertStatus()->value,
                'consecutive_non_ok' => $consecutive,
                'episode_started_at' => $previous['episode_started_at'] ?? now()->getTimestamp(),
                'notified_at' => $notified ? now()->getTimestamp() : ($previous['notified_at'] ?? null),
                'notified_signature' => $notified ? $snapshot->signature() : ($previous['notified_signature'] ?? null),
                'notified_status' => $notified ? $snapshot->alertStatus()->value : ($previous['notified_status'] ?? null),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Health alert state could not be written', ['exception' => $e::class]);

            return false;
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
