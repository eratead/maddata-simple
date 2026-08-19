<?php

namespace App\Services\Health;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Every cache key the health vertical uses.
 *
 * Markers are written by one class and read by another; a typo would show up
 * as a permanently STALE check rather than an error, so both ends name the key
 * from here and never inline a string.
 */
final class HealthMarkers
{
    public const SNAPSHOT = 'health:snapshot';

    /** No TTL — the last good snapshot, served while a rebuild is in flight. */
    public const SNAPSHOT_LAST = 'health:snapshot:last';

    public const SNAPSHOT_LOCK = 'health:snapshot:lock';

    /** Written by QueueHeartbeatJob when it actually executes on the queue. */
    public const QUEUE_BEAT = 'health:queue:beat_at';

    /** Written by the scheduler itself — proves cron is running Laravel. */
    public const SCHEDULER_BEAT = 'health:scheduler:beat_at';

    public const CAMPAIGN_STATUS_OK = 'health:campaigns_status_ok_at';

    public const ACTIVITY_DIGEST_OK = 'health:activity_digest_ok_at';

    /** Written by health:probe — {ok, latency_ms, checked_at, consec_fails}. */
    public const PUBLIC_PROBE = 'health:probe:public';

    /** Written by health:mark-restore-drill — {ts, note}. */
    public const RESTORE_DRILL = 'health:restore_drill';

    /**
     * health:alert's memory of the current problem episode — what is failing,
     * how long it has been failing, and whether anyone has been told yet.
     */
    public const ALERT_STATE = 'health:alert:state';

    /* ── Phase 4: dependency & version currency ──────────────────────────── */

    /**
     * d1's advisory result, keyed by the deployed composer.lock's sha256 —
     * appended by the check. A deploy that changes the lock therefore re-queries
     * immediately instead of serving up to 24 hours of stale "clean".
     */
    public const ADVISORIES = 'health:deps:advisories';

    /** d1's last-known-good result, no TTL. Served when the feed is unreachable. */
    public const ADVISORIES_LAST = 'health:deps:advisories:last';

    /**
     * d3's since-marker: {count, first_seen}. apt only ever reports the CURRENT
     * pending count, so "unpatched for 30 days" has to be remembered here.
     */
    public const OS_PATCH_SINCE = 'health:os_patch:since';

    /** d4 — written by deps:mark-patch-run: {ts, lock_sha, note}. */
    public const PATCH_RUN = 'health:deps:patch_run';

    /**
     * X2's per-minute failed-login buckets, suffixed with YmdHi. Buckets rather
     * than a table: they expire themselves, and D1 of this phase's design says
     * no migrations.
     */
    public const AUTH_FAIL_PREFIX = 'health:auth:fail:';

    /**
     * The store every marker lives in. Pinned by config rather than inherited,
     * so changing CACHE_STORE can never silently orphan the markers and leave
     * every age check reading STALE.
     */
    public static function store(): Repository
    {
        return Cache::store(config('health.marker_store'));
    }

    /**
     * Stamp a marker with the current time. Swallows write failures: a marker
     * that cannot be written is a monitoring problem, and it must never take
     * down the scheduled job it was recording.
     */
    public static function record(string $key, mixed $value = null): void
    {
        try {
            self::store()->put($key, $value ?? now()->getTimestamp(), now()->addWeek());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
