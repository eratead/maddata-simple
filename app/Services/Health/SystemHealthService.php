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
        return $this->cached(HealthMarkers::SNAPSHOT) ?? $this->refreshOnDemand();
    }

    /**
     * Rebuild now, ignoring a warm cache — what the monitor page's "Refresh
     * now" button calls, and what snapshot() falls back to on a cache miss.
     *
     * The single-flight rule has to survive a button. Two admins staring at a
     * sick box will click this at the same time, and a sick box is exactly when
     * the probes are slowest: the MySQL round trip and the Redis INFO are
     * expensive *because* MySQL and Redis are what is broken. So the second
     * caller takes the in-flight result instead of queueing a second rebuild
     * behind the first.
     */
    public function refreshOnDemand(): HealthSnapshot
    {
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
     *
     * Reads alertStatus(), not overall — the same filter the alert mail uses.
     *
     * The pill is not the page. The page is pull: you open it deliberately and
     * read every row, so it shows `overall` and stays completely honest. The
     * pill is a push surface embedded in every admin page, and it has exactly
     * one bit of information to spend. Production runs two runtimes past their
     * upstream windows that only an OS migration can clear, so on `overall` the
     * pill would read amber permanently from day one — and a real new warning,
     * a failed backup upload or a job starting to fail, would produce no
     * visible change at all. A signal that never changes is not a signal.
     */
    public function pillStatus(): HealthStatus
    {
        try {
            $payload = $this->cachedArray();

            if ($payload === null) {
                return HealthStatus::STALE;
            }

            // Hydrate rather than reading $payload['overall'] directly: the
            // pill needs alertStatus(), which is a rollup over the checks and
            // not a stored field. Still cheaper than the old path, which
            // hydrated and then discarded the whole snapshot on every page.
            return HealthSnapshot::fromArray($payload)->alertStatus()->forPill();
        } catch (Throwable) {
            return HealthStatus::STALE;
        }
    }

    /**
     * The cached snapshot as the array the UI polls — no DTO round trip, and
     * crucially NO rebuild.
     *
     * Two audit findings meet here. Hydrating 37 results into objects just to
     * call toArray() on them again is pure waste that grows with the check
     * count. And more seriously: when the marker store itself is unreadable,
     * the rebuilding read path had every poll from every open tab performing a
     * full inline build — including d1's outbound HTTPS call, up to 8 seconds,
     * inside an FPM worker. On a single-core box with a small worker pool that
     * is the monitor taking the site down at the exact moment it should be
     * reporting on it.
     *
     * Returns null when there is nothing cached; the caller decides what to
     * show. Rebuilding is the scheduler's job, and the CLI's.
     */
    public function cachedArray(): ?array
    {
        try {
            $payload = $this->store()->get(HealthMarkers::SNAPSHOT)
                ?? $this->store()->get(HealthMarkers::SNAPSHOT_LAST);

            return is_array($payload) ? $payload : null;
        } catch (Throwable) {
            return null;
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

            if (! $check instanceof HealthCheck) {
                // Silent before: a registry entry that resolved to the wrong
                // thing produced zero results, zero logs, and a node that
                // simply stopped existing on the map.
                Log::error('Registered health check is not a HealthCheck', ['check' => $class]);

                return [$this->crashed($class, 'not a health check')];
            }

            return $check->run();
        } catch (Throwable $e) {
            Log::error('Health check class failed', [
                'check' => $class,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [$this->crashed($class, 'check failed', class_basename($e))];
        }
    }

    /**
     * The result for a check class that could not run at all.
     *
     * Tagged to `app`, NOT `platform`. Phase 4 turned `platform` into a
     * delivery channel — the node routed to the weekly digest — so filing a
     * crashed check there meant "the monitor is broken" reached nobody until
     * Monday. Worse, because the crashed check's own results vanish from the
     * snapshot, an open episode could drop to all-clear and send a RECOVERY
     * notice for a problem that was still failing.
     */
    private function crashed(string $class, string $value, ?string $threshold = null): HealthCheckResult
    {
        return new HealthCheckResult(
            key: class_basename($class),
            label: 'Health check crashed: '.class_basename($class),
            status: HealthStatus::CRIT,
            node: 'app',
            value: $value,
            threshold: $threshold,
        );
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
