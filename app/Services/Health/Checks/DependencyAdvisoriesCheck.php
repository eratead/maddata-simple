<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthMarkers;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Check d1 — known security advisories against the DEPLOYED composer.lock.
 *
 * Three rules, each of which exists because the obvious implementation gets it
 * wrong:
 *
 *  1. AN UNREACHABLE FEED IS NEVER GREEN. Packagist being down means "we do not
 *     know", which is a warning, not a clean bill of health. We serve the last
 *     known good answer when we have one and warn plainly when we do not.
 *  2. THE CACHE IS KEYED BY THE LOCK'S HASH. A 24-hour cache keyed by nothing
 *     else would keep reporting "clean" for a day after a deploy introduced a
 *     vulnerable package — the one moment the answer changed.
 *  3. DEV PACKAGES CANNOT REACH CRIT. Production installs --no-dev, so a
 *     critical in a test-only package is not on the box. It is still on every
 *     developer's machine and in CI, so it warns rather than being ignored.
 */
class DependencyAdvisoriesCheck extends HealthCheck
{
    public function run(): array
    {
        return [$this->guard('d1', 'Composer advisories', 'platform', fn () => $this->probe())];
    }

    private function probe(): HealthCheckResult
    {
        $threshold = 'warn: any medium · crit: any high, critical or unrated';
        $lockPath = config('health.composer_lock_path') ?: base_path('composer.lock');

        if (! is_readable($lockPath)) {
            return $this->result(HealthStatus::STALE, 'composer.lock unreadable', $threshold);
        }

        $raw = (string) file_get_contents($lockPath);
        $lock = json_decode($raw, true);

        if (! is_array($lock)) {
            return $this->result(HealthStatus::STALE, 'composer.lock unparseable', $threshold);
        }

        $installed = $this->installedVersions($lock);

        if ($installed === []) {
            return $this->result(HealthStatus::STALE, 'no packages in composer.lock', $threshold);
        }

        [$advisories, $fromCache, $feedUp] = $this->advisories(array_keys($installed), hash('sha256', $raw));

        if ($advisories === null) {
            // Rule 1. No answer and no history to fall back on.
            return $this->result(HealthStatus::WARN, 'advisory feed unreachable', $threshold);
        }

        return $this->evaluate($advisories, $installed, $threshold, staleFeed: ! $feedUp && $fromCache);
    }

    /**
     * Both halves of the lock. packages-dev is included deliberately: it is not
     * deployed, but it is what every developer and CI runner installs.
     *
     * @return array<string, array{version: string, dev: bool}>
     */
    private function installedVersions(array $lock): array
    {
        $installed = [];

        foreach ([['packages', false], ['packages-dev', true]] as [$section, $isDev]) {
            foreach ((array) ($lock[$section] ?? []) as $package) {
                if (! isset($package['name'], $package['version'])) {
                    continue;
                }

                // ??=, so a package present in both sections keeps its
                // production severity instead of being capped as dev-only.
                $installed[$package['name']] ??= [
                    'version' => ltrim((string) $package['version'], 'v'),
                    'dev' => $isDev,
                ];
            }
        }

        return $installed;
    }

    /**
     * @return array{0: ?array, 1: bool, 2: bool} advisories, served-from-cache, feed-reachable
     */
    private function advisories(array $names, string $lockSha): array
    {
        $key = HealthMarkers::ADVISORIES.':'.substr($lockSha, 0, 16);

        try {
            $cached = $this->store()->get($key);

            if (is_array($cached)) {
                return [$cached, true, true];
            }
        } catch (Throwable) {
            // Cache unavailable is not fatal here — just means we ask upstream.
        }

        $config = (array) config('dependency_maintenance.advisories', []);

        try {
            $response = Http::asForm()
                // Packagist throttles anonymous and default user agents. This is
                // not politeness, it is whether the check works at all.
                ->withHeaders(['User-Agent' => (string) ($config['user_agent'] ?? 'MadData-HealthMonitor/1.0')])
                // Bounded because this runs inside a snapshot rebuild the
                // scheduler repeats every 60 seconds.
                ->timeout((int) ($config['timeout'] ?? 8))
                ->post((string) ($config['endpoint'] ?? ''), ['packages' => array_values($names)]);

            if (! $response->successful()) {
                throw new \RuntimeException('advisory feed returned '.$response->status());
            }

            $advisories = (array) ($response->json('advisories') ?? []);

            $this->remember($key, $advisories, (int) ($config['cache_hours'] ?? 24));

            return [$advisories, false, true];
        } catch (Throwable) {
            // Rule 1 — degrade to the last good answer, and say it is old.
            try {
                $last = $this->store()->get(HealthMarkers::ADVISORIES_LAST);

                if (is_array($last)) {
                    return [$last, true, false];
                }
            } catch (Throwable) {
                // fall through
            }

            return [null, false, false];
        }
    }

    private function remember(string $key, array $advisories, int $hours): void
    {
        try {
            $this->store()->put($key, $advisories, now()->addHours(max(1, $hours)));
            // No TTL: this is the parachute for a feed outage, and an outage
            // lasting longer than the TTL is exactly when it is needed.
            $this->store()->forever(HealthMarkers::ADVISORIES_LAST, $advisories);
        } catch (Throwable $e) {
            // A cache we cannot write costs us the parachute, not the answer —
            // but it must never do so silently. An unwritable cache turns this
            // check into an outbound HTTPS call every 60 seconds forever, with
            // no status change and no log line, until Packagist throttles us
            // and d1 degrades to "feed unreachable" for a reason nobody can see.
            Log::warning('Advisory cache write failed — d1 will re-query every rebuild', [
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  array<string, array{version: string, dev: bool}>  $installed
     */
    private function evaluate(array $advisories, array $installed, string $threshold, bool $staleFeed): HealthCheckResult
    {
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'unrated' => 0];
        $dev = 0;
        $unmatched = 0;
        $worstProd = HealthStatus::OK;
        $worstDev = HealthStatus::OK;

        foreach ($advisories as $package => $entries) {
            $package = (string) $package;

            if (! isset($installed[$package])) {
                continue;
            }

            foreach ((array) $entries as $advisory) {
                $affects = (string) ($advisory['affectedVersions'] ?? '');

                try {
                    if ($affects === '' || ! Semver::satisfies($installed[$package]['version'], $affects)) {
                        continue;
                    }
                } catch (Throwable) {
                    // An unparseable constraint or a dev- version we cannot
                    // compare. Counted, never silently dropped: "I could not
                    // tell" is a warning, not a pass.
                    $unmatched++;

                    continue;
                }

                $severity = strtolower((string) ($advisory['severity'] ?? ''));
                $severity = in_array($severity, ['critical', 'high', 'medium', 'low'], true) ? $severity : 'unrated';
                $counts[$severity]++;

                // Unrated counts as high: an advisory nobody scored is not an
                // advisory nobody needs to read.
                $status = in_array($severity, ['critical', 'high', 'unrated'], true)
                    ? HealthStatus::CRIT
                    : HealthStatus::WARN;

                if ($installed[$package]['dev']) {
                    $dev++;
                    // Rule 3 — capped, never CRIT.
                    $worstDev = HealthStatus::worstOf($worstDev, HealthStatus::WARN);
                } else {
                    $worstProd = HealthStatus::worstOf($worstProd, $status);
                }
            }
        }

        $status = HealthStatus::worstOf($worstProd, $worstDev);

        if ($unmatched > 0) {
            $status = HealthStatus::worstOf($status, HealthStatus::WARN);
        }

        $total = array_sum($counts);

        $value = $total === 0
            ? 'no known advisories'
            : implode(', ', array_map(
                fn ($sev, $n) => "{$n} {$sev}",
                array_keys(array_filter($counts)),
                array_values(array_filter($counts)),
            ));

        if ($dev > 0) {
            $value .= " ({$dev} dev-only)";
        }

        if ($unmatched > 0) {
            $value .= ", {$unmatched} unmatched";
        }

        if ($staleFeed) {
            // Never silently present old data as current.
            $status = HealthStatus::worstOf($status, HealthStatus::WARN);
            $value .= ' — feed unreachable, last known result';
        }

        // Published for d4: lock drift only matters while something known-bad
        // is in the tree.
        try {
            $this->store()->put(HealthMarkers::ADVISORIES_WORST, $status->value, now()->addDay());
        } catch (Throwable) {
            // d4 treats a missing value as "nothing known-bad", which is the
            // conservative direction: it will not invent a warning.
        }

        return $this->result($status, $value, $threshold);
    }

    private function result(HealthStatus $status, string $value, string $threshold): HealthCheckResult
    {
        return new HealthCheckResult(
            key: 'd1',
            label: 'Composer advisories',
            status: $status,
            node: 'platform',
            value: $value,
            threshold: $threshold,
        );
    }
}
