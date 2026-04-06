<?php

use App\Models\Agency;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Policies\ClientPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to view any client', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();

    $policy = new ClientPolicy;

    expect($policy->view($admin, $client))->toBeTrue();
});

it('allows user with can_manage_clients permission to view any client', function () {
    $role = Role::create([
        'name' => 'Client Manager',
        'permissions' => ['can_manage_clients' => true],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);
    $client = Client::factory()->create();

    $policy = new ClientPolicy;

    expect($policy->view($user, $client))->toBeTrue();
});

it('allows user assigned to the client via client_user pivot to view it', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client);

    $policy = new ClientPolicy;

    expect($policy->view($user, $client))->toBeTrue();
});

it('denies view to user not assigned to the client and not admin', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $policy = new ClientPolicy;

    expect($policy->view($user, $client))->toBeFalse();
});

it('denies view when user is assigned to a different client only', function () {
    $user = User::factory()->create();
    $assignedClient = Client::factory()->create();
    $otherClient = Client::factory()->create();

    $user->clients()->attach($assignedClient);

    $policy = new ClientPolicy;

    // User can view the assigned client
    expect($policy->view($user, $assignedClient))->toBeTrue();
    // But not the other client
    expect($policy->view($user, $otherClient))->toBeFalse();
});

it('allows user with agency access_all_clients to view clients in that agency', function () {
    $agency = Agency::factory()->create();
    $user = User::factory()->create();
    $user->agencies()->attach($agency, ['access_all_clients' => true]);

    $client = Client::factory()->create(['agency_id' => $agency->id]);

    $policy = new ClientPolicy;

    expect($policy->view($user, $client))->toBeTrue();
});

it('denies view to user with agency access when client belongs to a different agency', function () {
    $agency = Agency::factory()->create();
    $otherAgency = Agency::factory()->create();
    $user = User::factory()->create();
    $user->agencies()->attach($agency, ['access_all_clients' => true]);

    $clientInOtherAgency = Client::factory()->create(['agency_id' => $otherAgency->id]);

    $policy = new ClientPolicy;

    expect($policy->view($user, $clientInOtherAgency))->toBeFalse();
});
