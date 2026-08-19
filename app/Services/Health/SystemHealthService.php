<?php

namespace App\Services\Health;

use App\Dtos\HealthCheckResult;
use App\Dtos\HealthSnapshot;
use App\Enums\HealthStatus;
use App\Services\Health\Checks\HealthCheck;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds and caches the system health snapshot.
 *
 * Three properties matter more than anything else here, and each is tested:
 *
 *  1. It never throws. A check class blowing up degrades that check to CRIT;
 *     the snapshot still builds. The monitor must work when the system does not.
 *  2. Single-flight. N open admin tabs must not stampede a rebuild that costs
 *     a file read, a MySQL round trip and a Redis INFO.
 *  3. Last-known-good fallback. A failed or in-flight rebuild serves the
 *     previous snapshot rather than a blank page.
 */
class SystemHealthService
{
    public function __construct(private readonly HostFacts $facts) {}

    /**
     * The snapshot a request should use. Cheap: normally a single cache read,
     * because the scheduler rebuilds off-path every minute.
     */
    public function snapshot(): HealthSnapshot
    {
        if ($cached = $this->cached(HealthMarkers::SNAPSHOT)) {
            return $cached;
        }

        try {
            $lock = $this->store()->lock(
                HealthMarkers::SNAPSHOT_LOCK,
                (int) config('health.snapshot_lock_seconds', 20)
            );

            if ($lock->get()) {
                try {
                    return $this->refresh();
                } finally {
                    $lock->release();
                }
            }
        } catch (Throwable $e) {
            // The cache store itself is unavailable — which on production means
            // Redis, one of the things we exist to report on. Build directly
            // rather than throwing: a broken cache must never be the reason the
            // monitor goes silent about the broken cache.
            Log::warning('Health snapshot lock unavailable, building uncached', ['exception' => $e::class]);

            return $this->build();
        }

        // Another process is rebuilding. Serve the last good snapshot rather
        // than queueing behind it. Only a cold start with no history at all
        // falls through to building, and then exactly one request pays for it.
        return $this->cached(HealthMarkers::SNAPSHOT_LAST) ?? $this->build();
    }

    /**
     * Rebuild unconditionally and re-cache. Called every minute by
     * health:refresh-snapshot so a real request never observes a cache miss.
     */
    public function refresh(): HealthSnapshot
    {
        $snapshot = $this->build();
        $payload = $snapshot->toArray();

        try {
            $this->store()->put(
                HealthMarkers::SNAPSHOT,
                $payload,
                (int) config('health.snapshot_ttl', 30)
            );
            $this->store()->forever(HealthMarkers::SNAPSHOT_LAST, $payload);
        } catch (Throwable $e) {
            // A cache write failing is itself a signal, but it must not stop us
            // returning the snapshot we just computed.
            Log::warning('Health snapshot could not be cached', ['exception' => $e::class]);
        }

        return $snapshot;
    }

    /**
     * Status for the header pill. Cache-only by design: rendering a pill must
     * never trigger a rebuild and slow down an unrelated page. If there is no
     * snapshot to read, the pill honestly says it does not know.
     */
    public function pillStatus(): HealthStatus
    {
        try {
            $snapshot = $this->cached(HealthMarkers::SNAPSHOT)
                ?? $this->cached(HealthMarkers::SNAPSHOT_LAST);

            return $snapshot?->overall->forPill() ?? HealthStatus::STALE;
        } catch (Throwable) {
            return HealthStatus::STALE;
        }
    }

    private function build(): HealthSnapshot
    {
        $this->facts->flush();

        $results = [];

        foreach ((array) config('health.checks', []) as $class) {
            $results = [...$results, ...$this->runCheck($class)];
        }

        return HealthSnapshot::fromResults($results);
    }

    /**
     * Belt and suspenders: HealthCheck::run() is contractually not allowed to
     * throw, but a bug in one check must not cost us the other twenty.
     *
     * @return array<int, HealthCheckResult>
     */
    private function runCheck(string $class): array
    {
        try {
            $check = app($class);

            return $check instanceof HealthCheck ? $check->run() : [];
        } catch (Throwable $e) {
            Log::error('Health check class failed', [
                'check' => $class,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [new HealthCheckResult(
                key: class_basename($class),
                label: class_basename($class),
                status: HealthStatus::CRIT,
                node: 'platform',
                value: 'check failed',
                threshold: class_basename($e),
            )];
        }
    }

    private function cached(string $key): ?HealthSnapshot
    {
        try {
            $payload = $this->store()->get($key);

            return is_array($payload) ? HealthSnapshot::fromArray($payload) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function store(): Repository
    {
        return HealthMarkers::store();
    }
}
