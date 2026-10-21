<?php

use App\Models\Sucursal;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('shares the active sucursal on every page', function () {
    $user = User::factory()->create();
    Sucursal::factory()->create(['nombre' => 'Principal', 'termino_orden' => 'Mesa', 'activa' => true]);
    Sucursal::factory()->create(['activa' => false]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sucursalActiva.nombre', 'Principal')
        ->where('sucursalActiva.termino_orden', 'Mesa')
    );
});
