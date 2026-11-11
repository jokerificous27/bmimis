<?php

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->followingRedirects()->get(route('dashboard'));
    $response->assertOk();
});

test('users are sent to the dashboard for their role', function (Role $role, string $expectedRoute) {
    seedReferenceData();
    $user = User::factory()->role($role, Unit::first())->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($expectedRoute));
})->with([
    'super admin' => [Role::SuperAdmin, 'dashboard.health-service'],
    'health service admin' => [Role::HealthServiceAdmin, 'dashboard.health-service'],
    'encoder' => [Role::HealthServiceEncoder, 'dashboard.health-service'],
    'unit representative' => [Role::UnitRepresentative, 'dashboard.unit'],
    'personnel' => [Role::Personnel, 'my.dashboard'],
]);
