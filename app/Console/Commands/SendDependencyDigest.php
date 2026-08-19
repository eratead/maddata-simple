<?php

namespace App\Console\Commands;

use App\Dtos\HealthCheckResult;
use App\Mail\DependencyDigestMail;
use App\Services\Health\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The other half of the alerting split: everything on the nodes that
 * health:alert deliberately ignores (health.alert_excluded_nodes), reported
 * once a week.
 *
 * IT ALWAYS SENDS, including when nothing is wrong. A digest that only appears
 * on bad news is indistinguishable from a digest that has stopped working —
 * the same reason Phase 2 makes recovery notices mandatory.
 */
class SendDependencyDigest extends Command
{
    protected $signature = 'deps:digest';

    protected $description = 'Email the weekly dependency, runtime and patch-currency digest.';

    public function handle(SystemHealthService $health): int
    {
        $snapshot = $health->snapshot();
        $excluded = (array) config('health.alert_excluded_nodes', []);

        $checks = array_values(array_filter(
            $snapshot->checks,
            fn (HealthCheckResult $check) => in_array($check->node, $excluded, true),
        ));

        if ($checks === []) {
            // Nothing is routed to the digest — either the exclusion list is
            // empty or those checks are not registered. Say so rather than
            // sending an empty email every week.
            $this->warn('No checks are routed to the digest (health.alert_excluded_nodes) — nothing to send.');

            return self::SUCCESS;
        }

        $recipients = (array) config('health.deps_digest_recipients', []);

        if ($recipients === []) {
            $recipients = (array) config('health.alert_recipients', []);
        }

        if ($recipients === []) {
            $this->warn('No digest recipients configured — nothing sent.');

            return self::SUCCESS;
        }

        try {
            Mail::to($recipients)->send(new DependencyDigestMail($snapshot, $checks));
        } catch (Throwable $e) {
            // Same rule as health:alert — a broken mailer must never break the
            // scheduler, and it must be visible in the log.
            Log::error('Dependency digest could not be sent', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $this->warn('Digest could not be sent — logged.');

            return self::SUCCESS;
        }

        $failing = count(array_filter($checks, fn (HealthCheckResult $c) => $c->status->isFailing()));

        $this->info("Dependency digest sent — {$failing} of ".count($checks).' items need attention.');

        return self::SUCCESS;
    }
}
