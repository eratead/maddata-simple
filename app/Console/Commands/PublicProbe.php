<?php

namespace App\Console\Commands;

use App\Services\Health\HealthMarkers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Check P1 — hits the public URL from outside the framework and records the
 * result for the monitor to read.
 *
 * Kept as a scheduled command rather than an inline probe on purpose: building
 * a snapshot must never be able to block on an external network call.
 *
 * consec_fails is what escalates to CRIT, not a single failure — one blip
 * during a deploy is not an outage, two in a row is.
 */
class PublicProbe extends Command
{
    protected $signature = 'health:probe';

    protected $description = 'Probe the public HTTPS endpoint and record the result for the health monitor.';

    public function handle(): int
    {
        $url = (string) config('health.probe_url');
        $timeout = (int) config('health.probe_timeout', 3);

        $previous = HealthMarkers::store()->get(HealthMarkers::PUBLIC_PROBE);
        $priorFails = is_array($previous) ? (int) ($previous['consec_fails'] ?? 0) : 0;

        $start = microtime(true);
        $ok = false;
        $error = null;

        try {
            $response = Http::timeout($timeout)
                ->withoutRedirecting()
                ->get($url);

            $ok = $response->successful();

            if (! $ok) {
                $error = 'HTTP '.$response->status();
            }
        } catch (Throwable $e) {
            $error = class_basename($e);
        }

        $latency = (int) round((microtime(true) - $start) * 1000);

        $marker = [
            'ok' => $ok,
            'latency_ms' => $latency,
            'checked_at' => now()->getTimestamp(),
            'consec_fails' => $ok ? 0 : $priorFails + 1,
            'error' => $error,
        ];

        HealthMarkers::store()->put(HealthMarkers::PUBLIC_PROBE, $marker, now()->addHour());

        if ($ok) {
            $this->info("Probe OK — {$url} in {$latency}ms.");

            return self::SUCCESS;
        }

        $this->warn("Probe FAILED — {$url} ({$error}), {$marker['consec_fails']} consecutive.");

        // Exit 0 regardless: a failing probe is data for the monitor, not a
        // broken command. A non-zero exit would only spam cron mail.
        return self::SUCCESS;
    }
}
