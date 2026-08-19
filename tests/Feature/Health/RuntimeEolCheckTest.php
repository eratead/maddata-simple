<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\RuntimeEolCheck;

/**
 * Asserted through the PHP row, because PHP_VERSION is the one runtime version
 * available in every test environment. MySQL/Redis/Nginx take the same code
 * path; only the probe differs.
 */
function phpBranch(): string
{
    return implode('.', array_slice(explode('.', PHP_VERSION), 0, 2));
}

function eolTable(array $rows, ?string $reviewedAt = null): void
{
    config([
        'dependency_maintenance.runtimes' => $rows,
        'dependency_maintenance.reviewed_at' => $reviewedAt ?? now()->toDateString(),
        'dependency_maintenance.thresholds.eol_warn_days' => 90,
        'dependency_maintenance.thresholds.table_review_months' => 6,
    ]);
}

function d2(string $key): App\Dtos\HealthCheckResult
{
    return checksByKey(app(RuntimeEolCheck::class))[$key];
}

it('passes a runtime well inside its support window', function () {
    eolTable([['product' => 'php', 'branch' => phpBranch(), 'security_support_until' => now()->addYears(2)->toDateString()]]);

    expect(d2('d2-php')->status)->toBe(HealthStatus::OK)
        ->and(d2('d2-php')->value)->toContain('supported until');
});

it('warns as the support window comes into view', function () {
    eolTable([['product' => 'php', 'branch' => phpBranch(), 'security_support_until' => now()->addDays(45)->toDateString()]]);

    expect(d2('d2-php')->status)->toBe(HealthStatus::WARN);
});

it('goes critical once security support has ended', function () {
    eolTable([['product' => 'php', 'branch' => phpBranch(), 'security_support_until' => now()->subDay()->toDateString()]]);

    expect(d2('d2-php')->status)->toBe(HealthStatus::CRIT)
        ->and(d2('d2-php')->value)->toContain('security support ended');
});

/*
| Driven through the nginx entry, because its version comes from the facts file
| and is therefore the one runtime a test can set freely.
|
| Note the fixtures below only ever use strings the facts script can actually
| emit. An earlier version of this file fed nginx "1.24.0 (Ubuntu)", which the
| script's own sed strips — proving a state production cannot reach.
*/
it('warns rather than going critical when a past-EOL runtime is distro-packaged', function () {
    // Production's real case: MySQL 8.0.46-0ubuntu0.24.04.3 and Redis 7.0.15 are
    // both past their upstream windows AND both receive Canonical's backported
    // fixes. CRIT would claim an exposure that does not exist — and nothing
    // short of an OS upgrade could ever clear it.
    eolTable([[
        'product' => 'nginx',
        'branch' => '1.24',
        'security_support_until' => now()->subYear()->toDateString(),
        'package' => ['nginx'],
    ]]);
    fakeHostFacts([
        'ts' => now()->getTimestamp(),
        'nginx_version' => '1.24.0',
        'packages' => ['nginx' => '1.24.0-2ubuntu7.15'],
    ]);

    expect(d2('d2-nginx')->status)->toBe(HealthStatus::WARN)
        ->and(d2('d2-nginx')->value)->toContain('upstream branch EOL')
        ->and(d2('d2-nginx')->value)->toContain('plan a migration');
});

it('still goes critical for a past-EOL runtime built from upstream', function () {
    eolTable([['product' => 'nginx', 'branch' => '1.24', 'security_support_until' => now()->subYear()->toDateString()]]);
    fakeHostFacts(['ts' => now()->getTimestamp(), 'nginx_version' => '1.24.0']);

    // No distro marker anywhere — nobody is backporting for you. Detected from
    // evidence, so swapping to an upstream build escalates this without anyone
    // remembering to flip a config flag.
    expect(d2('d2-nginx')->status)->toBe(HealthStatus::CRIT)
        ->and(d2('d2-nginx')->value)->toContain('security support ended');
});

it('trusts the installed package version when the runtime under-reports itself', function () {
    /*
     * Production's actual failure. Redis answers INFO server with a bare
     * upstream semver even when the installed package is the Ubuntu build, so a
     * self-report-only test called it a CRIT outage on a box Canonical was
     * patching. dpkg is the only authority, the app may never ask it, so the
     * facts script carries the answer.
     */
    eolTable([[
        'product' => 'nginx',
        'branch' => '1.24',
        'security_support_until' => now()->subYear()->toDateString(),
        'package' => ['nginx'],
    ]]);

    fakeHostFacts([
        'ts' => now()->getTimestamp(),
        'nginx_version' => '1.24.0',                             // no marker here...
        'packages' => ['nginx' => '1.24.0-2ubuntu7.5'],          // ...but the truth is here
    ]);

    expect(d2('d2-nginx')->status)->toBe(HealthStatus::WARN)
        ->and(d2('d2-nginx')->value)->toContain('upstream branch EOL');
});

it('degrades to the weaker self-report test when no package facts exist', function () {
    /*
     * Audit finding: the previous version of this test fed nginx
     * "1.24.0 (Ubuntu)" and asserted WARN — but the facts script's
     * `sed 's|.*nginx/\([0-9.]*\).*|\1|p'` captures only `[0-9.]*` and drops
     * the vendor tag, so nginx's self-report can NEVER carry a distro marker.
     * The test proved a state the production data path cannot reach, which is
     * the exact defect docs/lessons.md was written about.
     *
     * What is actually true: without package facts, nginx has no evidence of
     * being distro-built, so it degrades to CRIT. That is the honest weaker
     * answer — MySQL, whose self-report does carry the suffix, is the runtime
     * the fallback genuinely serves.
     */
    eolTable([[
        'product' => 'nginx',
        'branch' => '1.24',
        'security_support_until' => now()->subYear()->toDateString(),
        'package' => ['nginx'],
    ]]);

    fakeHostFacts(['ts' => now()->getTimestamp(), 'nginx_version' => '1.24.0']);

    expect(d2('d2-nginx')->status)->toBe(HealthStatus::CRIT);
});

it('warns on a branch the table does not cover rather than passing it', function () {
    eolTable([['product' => 'php', 'branch' => '5.6', 'security_support_until' => '2099-01-01']]);

    // An unknown runtime is not a supported one, and the fix is to add the row.
    expect(d2('d2-php')->status)->toBe(HealthStatus::WARN)
        ->and(d2('d2-php')->value)->toContain('not in the support table');
});

it('passes a runtime whose currency another check owns', function () {
    eolTable([['product' => 'php', 'branch' => '*', 'security_support_until' => null, 'tracked_by' => 'd3']]);

    // nginx is the real case: no upstream window, patched by distro backports,
    // which d3 measures. A permanent amber here would be un-actionable.
    expect(d2('d2-php')->status)->toBe(HealthStatus::OK)
        ->and(d2('d2-php')->value)->toContain('tracked by d3');
});

it('warns when nobody publishes a window and no other check covers it', function () {
    eolTable([['product' => 'php', 'branch' => '*', 'security_support_until' => null]]);

    // The honest "we do not know" case. It must never read green.
    expect(d2('d2-php')->status)->toBe(HealthStatus::WARN);
});

it('warns when the support table itself has gone stale', function () {
    eolTable(
        [['product' => 'php', 'branch' => phpBranch(), 'security_support_until' => now()->addYears(2)->toDateString()]],
        reviewedAt: now()->subMonths(8)->toDateString(),
    );

    // A table nobody revisits reports "everything is supported" forever.
    expect(d2('d2-table')->status)->toBe(HealthStatus::WARN)
        ->and(d2('d2-php')->status)->toBe(HealthStatus::OK);
});

it('passes a recently reviewed table', function () {
    eolTable([['product' => 'php', 'branch' => phpBranch(), 'security_support_until' => now()->addYears(2)->toDateString()]]);

    expect(d2('d2-table')->status)->toBe(HealthStatus::OK);
});
