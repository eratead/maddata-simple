# Spec: Display Activity Log Timestamps in Israel Time

## Goal
Activity-log `created_at` timestamps currently render in the server's timezone (UTC — see `config/app.php:68`). MadData is an Israel-based operation and admins read these logs in Israel local time. Convert the timestamps at the **display layer** — in the admin activity log table and in the activity digest email — so they show `Asia/Jerusalem` time without ambiguity.

**Storage stays in UTC.** This is standard Laravel practice and must not change. We only convert at the boundary where humans read the value (Blade views, mail).

## Design Decision: Config-driven inline conversion

### Options considered

| Option | Pros | Cons | Verdict |
|---|---|---|---|
| **A. Global `APP_TIMEZONE=Asia/Jerusalem`** | One-line change | Catastrophic: changes meaning of every `now()` / `created_at` write, breaks diffing between old (UTC) and new (IL) rows, may break tests | ❌ Rejected |
| **B. Hardcode `->timezone('Asia/Jerusalem')` inline** | Zero indirection, mirrors existing precedent at `campaign_changes/show.blade.php:68` | Magic string repeated in 3 places | ❌ Rejected (DRY) |
| **C. Config key + inline `->timezone(config('app.display_timezone'))`** | One source of truth, env-overridable, testable, tiny footprint | One extra config line | ✅ **Chosen** |
| **D. Accessor / helper method on `ActivityLog`** | Cleanest call sites | Adds an Eloquent method; only used in 3 spots; over-abstraction for a cross-cutting display concern | ❌ Rejected |

### Rule of thumb
Timezone conversion is a **presentation concern**, not a data concern. Keep storage in UTC. Convert only where the value meets a human eye. Use a single config key so the magic string `'Asia/Jerusalem'` never appears in Blade files.

## Files To Change

### 1. `config/app.php`
Add one new entry, alongside the existing `'timezone' => 'UTC'`:

```php
'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jerusalem'),
```

### 2. `.env.example`
Add one line (under the `APP_TIMEZONE` area if present, otherwise near `APP_URL`):

```
APP_DISPLAY_TIMEZONE=Asia/Jerusalem
```

Builder should also set this in the local `.env` if it's missing, but the config default already covers it so this is belt-and-suspenders.

### 3. `resources/views/admin/activity_logs/index.blade.php` — line 182
Change:
```blade
{{ $log->created_at->format('M j, Y g:i A') }}
```
To:
```blade
{{ $log->created_at->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}
```

### 4. `resources/views/emails/activity_digest.blade.php` — line 48
Change:
```blade
{{ $log->created_at->format('H:i') }}
```
To:
```blade
{{ $log->created_at->timezone(config('app.display_timezone'))->format('H:i') }}
```

### 5. `resources/views/admin/campaign_changes/show.blade.php` — line 68 (refactor existing)
Replace the hardcoded `'Asia/Jerusalem'` so all three display sites read from the same config key:

```blade
{{ $log->created_at->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}
```

## What Stays (Out of Scope)
- `config('app.timezone')` = `'UTC'` — storage timezone. Do NOT touch.
- Any `created_at` stored in the DB, or any `now()` / `Carbon::now()` write. Those all stay UTC.
- The "changes" JSON diff in activity logs. If a row changed a datetime column (e.g., `start_date`), the before/after values are business dates, not log metadata. Leave them alone.
- Any other controller, model, or view displaying non-activity-log timestamps (e.g., campaign dashboards, placement dates). Out of scope for this task.
- `SystemStatusController.php` also references `Asia/Jerusalem` — that's a different feature. Don't touch it.

## Multi-Tenant / RBAC Impact
None. Pure display transformation inside already-authorized admin views and an admin-only digest email.

## Dependencies
None. No package, migration, or service change. Laravel's Carbon already supports `->timezone()`.

## Tests

### Test 1 — Activity log index renders in Israel time
Add to `tests/Feature/Admin/ActivityLogControllerTest.php` (or the existing activity log feature test file; builder to locate the nearest home):

```
it('renders activity log timestamps in the configured display timezone', function () {
    config(['app.display_timezone' => 'Asia/Jerusalem']);

    $admin = User::factory()->create(['is_admin' => true]);
    // Insert a log at a known UTC moment
    $log = ActivityLog::factory()->create([
        'created_at' => Carbon::parse('2026-01-15 10:00:00', 'UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.activity-logs.index'));

    $response->assertOk();
    // 10:00 UTC == 12:00 Jerusalem (IST, UTC+2) in January
    $response->assertSeeText('Jan 15, 2026 12:00 PM');
    $response->assertDontSeeText('Jan 15, 2026 10:00 AM');
});
```

**Watch for DST:** Israel is UTC+2 in winter (IST), UTC+3 in summer (IDT). Pick a fixture date unambiguously in one zone — e.g., **January 15** (IST, UTC+2) or **July 15** (IDT, UTC+3). Do not use shoulder-season dates.

### Test 2 — Digest email renders in Israel time
Add to `tests/Feature/Mail/ActivityDigestMailTest.php` (create if missing):

```
it('renders activity digest mail with Israel-time timestamps', function () {
    config(['app.display_timezone' => 'Asia/Jerusalem']);

    $log = ActivityLog::factory()->create([
        'created_at' => Carbon::parse('2026-07-15 09:00:00', 'UTC'),
    ]);
    $mail = new \App\Mail\ActivityDigestMail(collect([$log]));
    $rendered = $mail->render();

    // 09:00 UTC == 12:00 Jerusalem (IDT, UTC+3) in July
    expect($rendered)->toContain('12:00');
    expect($rendered)->not->toContain('09:00');
});
```

### Existing tests
Any existing assertions that compare activity-log timestamps to UTC-formatted strings will need to be updated. Builder should grep `tests/` for `activity_log.*format\(` / `assertSeeText.*created_at` in activity-log tests before running the suite, and fix any that break. If none break, great.

## Confirmed Decisions
1. **Scope is activity logs only** — admin index table + digest email + (refactor) campaign changes show view. Not a global timezone change.
2. **Storage remains UTC**. Only the display layer converts.
3. **Single config key** `app.display_timezone`, defaulting to `Asia/Jerusalem`, env-overridable.
