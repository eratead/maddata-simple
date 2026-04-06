<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('excel_safe helper function exists', function () {
    expect(function_exists('excel_safe'))->toBeTrue();
});

it('prefixes equals-sign formula with a single quote to prevent injection', function () {
    expect(excel_safe('=SUM(A1)'))->toBe("'=SUM(A1)");
});

it('prefixes plus-sign value with a single quote to prevent injection', function () {
    expect(excel_safe('+cmd|...'))->toBe("'+cmd|...");
});

it('prefixes minus-sign value with a single quote to prevent injection', function () {
    expect(excel_safe('-1+1'))->toBe("'-1+1");
});

it('prefixes at-sign value with a single quote to prevent injection', function () {
    expect(excel_safe('@SUM'))->toBe("'@SUM");
});

it('prefixes tab-prefixed value with a single quote to prevent injection', function () {
    expect(excel_safe("\tSUM"))->toBe("'\tSUM");
});

it('prefixes carriage-return-prefixed value with a single quote to prevent injection', function () {
    expect(excel_safe("\rSUM"))->toBe("'\rSUM");
});

it('returns normal text unchanged', function () {
    expect(excel_safe('Normal text'))->toBe('Normal text');
});

it('returns empty string for null input', function () {
    expect(excel_safe(null))->toBe('');
});

it('returns empty string for empty string input', function () {
    expect(excel_safe(''))->toBe('');
});

it('returns plain numbers unchanged', function () {
    expect(excel_safe('12345'))->toBe('12345');
});

it('returns plain campaign name unchanged', function () {
    expect(excel_safe('Summer 2026 Campaign'))->toBe('Summer 2026 Campaign');
});
