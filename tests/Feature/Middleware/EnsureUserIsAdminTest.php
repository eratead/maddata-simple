<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ===================================================================
// Test 5: EnsureUserIsAdmin null-user guard
// ===================================================================

it('redirects unauthenticated request to admin route to login without NPE', function () {
    // The auth middleware runs before EnsureUserIsAdmin and redirects to login.
    // The important invariant is no 500/NPE — the null check in EnsureUserIsAdmin
    // guards against any future middleware ordering change. Unauthenticated → 302.
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('returns 403 for non-admin authenticated user on admin route', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $this->actingAs($nonAdmin)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('allows admin authenticated user through admin route', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk();
});

it('redirects unauthenticated request to admin agencies route to login without NPE', function () {
    // Same null-guard invariant: must not be a 500.
    $this->get(route('admin.agencies.index'))
        ->assertRedirect(route('login'));
});

it('returns 403 for disabled legacy admin on admin route', function () {
    // is_active=false means hasPermission returns false → middleware aborts 403
    $disabledAdmin = User::factory()->create(['is_admin' => true, 'is_active' => false]);

    $this->actingAs($disabledAdmin)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('middleware handles null user gracefully throwing 403 HttpException not a TypeError', function () {
    // Directly invoke the middleware with a null-user request.
    // The null guard (if $user === null) must abort(403), NOT throw a TypeError/NPE.
    $middleware = new \App\Http\Middleware\EnsureUserIsAdmin;
    $request = \Illuminate\Http\Request::create('/admin/users', 'GET');
    // request->user() returns null since no auth guard is bootstrapped in this test

    $thrownException = null;
    try {
        $middleware->handle($request, fn () => response('passed'));
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $thrownException = $e;
    } catch (\TypeError $e) {
        // This would mean the null guard is missing — fail explicitly
        $thrownException = $e;
    }

    // Must be an HttpException (403), never a TypeError/NPE
    expect($thrownException)->toBeInstanceOf(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($thrownException->getStatusCode())->toBe(403);
});
