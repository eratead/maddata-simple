<?php

namespace App\Services\Health\Listeners;

use App\Services\Health\HealthMarkers;
use Illuminate\Auth\Events\Failed;
use Throwable;

/**
 * Feeds check X2 — a burst of failed logins.
 *
 * There is no failed-login table and no plan to add one: the design for this
 * phase adds zero migrations. Instead each failure bumps a one-minute counter
 * in the marker store and X2 sums the last fifteen. The buckets expire
 * themselves, so nothing accumulates and nothing needs pruning.
 *
 * Registered explicitly in AppServiceProvider rather than left to Laravel's
 * listener auto-discovery. Discovery would work — but under it, a renamed
 * method or a dropped type-hint silently unregisters this listener, the buckets
 * stay empty, and X2 reports a confident green "0 failed logins" forever.
 * Nothing about that looks wrong, which is the worst property a check can have.
 *
 * And it deliberately does NOT live in app/Listeners: discovery IS enabled here
 * (Application::configure() chains ->withEvents(), and this app defines no
 * EventServiceProvider to turn it off), so a class sitting in that directory
 * with a type-hinted handle() gets registered a second time. That is not
 * theoretical — it double-counted every failed login until a test caught it.
 * Living with the rest of the health vertical keeps the explicit wiring honest.
 */
class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        try {
            $window = max(1, (int) config('health.failed_login_window_minutes', 15));
            $key = HealthMarkers::AUTH_FAIL_PREFIX.now()->format('YmdHi');
            $store = HealthMarkers::store();

            // add() then increment(): add is a no-op when the bucket exists, and
            // it is what puts the TTL on. increment() alone would either fail on
            // a missing key or create one that never expires.
            $store->add($key, 0, now()->addMinutes($window + 5));
            $store->increment($key);
        } catch (Throwable) {
            // Never rethrow. This runs inside the login request: a monitoring
            // counter must not be able to stop people signing in.
        }
    }
}
