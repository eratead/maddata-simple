# Lessons Learned

## Laravel 12 Exception Handling

**Laravel 12 does NOT auto-load `app/Exceptions/Handler.php`.** Exception customization lives exclusively in `bootstrap/app.php` via `->withExceptions(...)`. Subclassing the base `Handler` in `app/Exceptions/Handler.php` is dead code — it is never referenced or loaded. Verify with `grep -rn "App\\\\Exceptions\\\\Handler" . --exclude-dir=vendor` before assuming it is wired up.

### `prepareException()` transforms exceptions before render callbacks

The framework's `Handler::prepareException()` runs BEFORE render callbacks (`renderViaCallbacks`). This means:
- `AuthorizationException` (and subclasses like `MissingAbilityException`) → `AccessDeniedHttpException`
- `ModelNotFoundException` → `NotFoundHttpException`
- `BackedEnumCaseNotFoundException` → `NotFoundHttpException`

If you register a render closure for `AuthorizationException`, it will NOT catch authorization failures in practice — you must also handle `AccessDeniedHttpException`.

### `shouldRenderJsonWhen` REPLACES `expectsJson()`, it does not extend it

Calling `$exceptions->shouldRenderJsonWhen($callback)` replaces the default `$request->expectsJson()` check entirely. If your callback returns `false` for non-api paths, web routes with `Accept: application/json` (e.g., `getJson()`/`postJson()` in tests or real XHR) will no longer receive JSON error responses. Always include `|| $request->expectsJson()` as a fallback:

```php
$exceptions->shouldRenderJsonWhen(
    fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
);
```

### Sanctum guard enforces `expires_at` itself

Sanctum's `Guard::retrieve()` checks `$accessToken->expires_at->isPast()` and rejects the token BEFORE the request reaches any middleware. Custom `CheckTokenExpiry` middleware only fires when the user is authenticated via a different path (e.g., session). Expired Sanctum tokens return `AuthenticationException` ("Unauthenticated."), not the "Token expired." message from `CheckTokenExpiry`.

---

## Runtime upgrades (PHP) are never routine — stage them

**A PHP point-release upgrade can break Laravel. Treat it as a feature-sized change, not maintenance.** Recorded 2026-08-19, after production was found sitting on 13 pending `php8.4-*` packages (8.4.19 → 8.4.24 from the `deb.sury.org` PPA).

Rules:

1. **Never bundle a PHP upgrade into another request.** It was pending alongside "1 security update"; applying them together would have shipped a runtime bump under an authorization to patch a vulnerability. Runtime changes get their own window and their own approval.
2. **Staging first, always** — deploy, run the **full test suite** on the upgraded runtime, then smoke the real app before production goes anywhere near it.
3. **Restart what holds the runtime in memory.** `php8.4-fpm` and the queue worker both keep the old binary and extensions loaded until restarted; the app can look fine while half of it still runs the old PHP.
4. **Check the extension list, not just `php -v`.** Laravel fails on a *missing extension* far more often than on a language change — compare `php -m` before and after.
5. **Check that staging can actually run the suite before relying on it.** Staging lacked `php8.4-sqlite3`, so the SQLite-in-memory test suite failed 705 tests with "could not find driver" — nothing to do with the upgrade. A staging box that cannot run the tests provides no validation, only the appearance of it.
6. **Staging is not a perfect proxy here.** Staging runs Apache + mod_php on Ubuntu 22.04; production runs Nginx + PHP-FPM on 24.04, from a different starting version. Staging proves *the application code runs on the new PHP*; it does not prove the FPM restart path. Know which risk you have actually retired.

## `apt-check`'s security count is not actionable on its own

`/usr/lib/update-notifier/apt-check` counts packages in the full-upgrade change set that have a security-pocket version — **including packages that are not installed**. Production reported "1 pending security update" that was `libabsl20220623t64`, absent from the system and only reachable as a new dependency of `libgav1-1` during a full upgrade. `apt-get install --only-upgrade` refuses it; `unattended-upgrade` reports "no packages found".

Before acting on a security count, resolve it to actual packages:

```bash
python3 -c "
import apt
c = apt.Cache(); c.open(None); c.upgrade(True)
for p in c.get_changes():
    if p.is_installed and any((o.archive or '').endswith('-security') for o in p.candidate.origins):
        print(p.name, p.installed.version, '->', p.candidate.version)"
```

Note also that Ubuntu publishes security fixes to `-updates` as well as `-security`, so the absence of a `-security` tag in `apt list --upgradable` does not prove the absence of security content — check the candidate's origins.

## Verify as the UID that runs the code, not as root

**Root ignores permission bits, so a root shell is the one user who cannot tell you whether a permission problem exists.** Recorded 2026-08-19, after an entire day of "25 of 26 checks green" turned out to be measured over SSH as root while `www-data` — which runs the scheduler, PHP-FPM and the alerter — saw a full OUTAGE and sent a false alarm.

The mechanism: the health monitor's backup marker was moved into `/var/backups/maddata`, a directory the backup script prescribes as `700 root:root`. POSIX requires `+x` on every path component, so `www-data` could not traverse it; `is_readable()` returned false and the check reported `CRIT "marker missing"`. Root read the same file without trouble.

Rules:

1. **Any check that touches the filesystem must be verified as the service user**: `sudo -u www-data php artisan ...`. This applies to health checks, storage paths, cache paths, log writes and uploaded files.
2. **A monitor whose answer depends on who asks is worse than no monitor**, because the operator's convenient vantage point (a root SSH session) is systematically the wrong one, and the wrong answer is the reassuring one.
3. **Distinguish "cannot read" from "not there".** They are different facts with different fixes, and collapsing them turns a permission bug into a phantom data-loss alarm.
4. **Enforce the permission in the script that depends on it**, not by hand — a hand-applied `chmod` is silently lost the next time the directory is recreated.

## A check must be able to reach both of its answers

Before shipping a check that distinguishes two states, confirm the data it reads
can actually express both. **A test whose answer is structurally fixed is worse
than no test, because it reads like a finding.**

Recorded 2026-08-19. Health check `d2` decides whether a runtime past its
upstream EOL is an outage or a migration to plan, by looking for a distro marker
(`ubuntu`, `debian`) in the version string. That works for MySQL, which bakes
its package suffix into `select version()` — `8.0.46-0ubuntu0.24.04.3`. It can
never work for Redis, which answers `INFO server` with a bare upstream `7.0.15`
even when the installed package is `5:7.0.15-1ubuntu0.24.04.4`. So `d2-redis`
shipped to production reporting CRIT "security support ended 751 days ago" on a
box Canonical was actively patching, and no possible state of the world would
have made it say otherwise.

The tests passed, because they fed the check a string containing "ubuntu" — a
string the real probe cannot produce. **A fixture that the production data path
cannot generate proves nothing.**

Rules:

1. **For each branch of a check, ask what real input reaches it.** If you cannot
   name one, the branch is dead code wearing a finding's clothes.
2. **Prefer authoritative evidence to a self-report.** The package manager knows
   what is installed; a daemon knows only what it was compiled as.
3. **When the authority is out of reach, move the question, not the rule.** PHP-FPM
   is not allowed to shell out to `dpkg`, so the root-owned facts script asks and
   publishes the answer — the same root-writes/app-reads hand-off the rest of
   the monitor uses. The fix was never to relax the zero-grant rule.
4. **Keep the fallback weaker, not wrong.** Missing package facts degrade to the
   self-report test; they must not degrade to a confident CRIT.

## `npm run build` is part of the deploy whenever Blade or Tailwind changed

`/public/build` is gitignored, so compiled assets exist only on each server. A
deploy that runs `git pull && composer install` and clears caches ships the new
markup against the **old** stylesheet — and because Tailwind only emits classes
it can see at build time, any new utility class is simply absent. The page
renders, so nothing errors; it just comes out unstyled, which is the kind of
failure that reaches users before it reaches a log.

Recorded 2026-08-19, deploying the health monitor's admin page: the documented
"code-only deploy" recipe had no build step, and the page relies on
`bg-emerald-500` / `bg-amber-500` / `bg-red-500` for its entire meaning.

Rules:

1. **If the change touches `resources/`, the deploy runs `npm ci && npm run build`.**
   Composer-only deploys are for PHP-only changes.
2. **Prove it took**, rather than trusting that the command ran:
   `grep -c 'bg-emerald-500' public/build/assets/*.css` — a specific new class
   from this change, not a class that was already there.
3. **Never interpolate Tailwind class names.** `bg-{$token}-500` and
   `` `bg-${token}-500` `` are invisible to the JIT compiler, so they survive
   every local test and vanish in production. Map tokens to complete literals.
4. **Expect a brief 500 window.** `npm run build` empties and rewrites
   `manifest.json` in place, so live requests landing in that ~5s gap fail with
   "Vite manifest not found". It self-resolves and one was observed on the
   production deploy. If that ever becomes unacceptable, build to a temp
   directory and swap it in atomically — but do not mistake the log line for a
   deploy failure.
