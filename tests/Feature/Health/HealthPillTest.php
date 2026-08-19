<?php

use App\Enums\HealthStatus;
use App\Models\User;
use App\Services\Health\SystemHealthService;

/*
| The pill renders into layouts/app.blade.php, which every authenticated page
| uses — including client-facing ones. So it is tested on an UNRELATED page,
| which is where it actually lives, and the non-admin case asserts the markup is
| ABSENT rather than merely hidden.
*/

it('shows the health pill to an admin on an unrelated page', function () {
    app(SystemHealthService::class)->refresh();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee('data-testid="health-pill"', false);
});

it('renders no pill markup at all for a non-admin', function () {
    app(SystemHealthService::class)->refresh();

    // Not x-show, not hidden — absent. A hidden element would ship system
    // status into the DOM of every non-admin page.
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertDontSee('data-testid="health-pill"', false);
});

it('keeps an unrelated page alive when the health service blows up', function () {
    $this->mock(SystemHealthService::class, function ($mock) {
        $mock->shouldReceive('pillStatus')->andThrow(new RuntimeException('everything is down'));
    });

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee(HealthStatus::STALE->label());
});
