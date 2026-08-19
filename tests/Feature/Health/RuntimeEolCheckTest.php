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
