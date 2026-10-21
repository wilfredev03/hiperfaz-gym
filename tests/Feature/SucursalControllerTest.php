<?php

use App\Models\Categoria;
use App\Models\Sucursal;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('sucursales.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of sucursales', function () {
    $usuario = User::factory()->create();
    Sucursal::factory()->count(3)->create();

    $response = $this->actingAs($usuario)->get(route('sucursales.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/sucursales/Index', false)
        ->has('sucursales.data', 3)
    );
});

test('renders the create form', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->get(route('sucursales.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('settings/sucursales/Create', false));
});

test('creates a sucursal and redirects to the index', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('sucursales.store'), [
        'nombre' => 'Sucursal Centro',
        'termino_orden' => 'Mesa',
        'direccion' => 'Av. Principal',
        'telefono' => '0212-1234567',
        'activa' => true,
    ]);

    $response->assertRedirect(route('sucursales.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Sucursal creada.']);
    $this->assertDatabaseHas('sucursales', [
        'nombre' => 'Sucursal Centro',
        'termino_orden' => 'Mesa',
    ]);
});

test('requires a nombre and termino_orden to create a sucursal', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('sucursales.store'), [
        'direccion' => 'Av. Principal',
        'activa' => true,
    ]);

    $response->assertSessionHasErrors(['nombre', 'termino_orden']);
    $this->assertDatabaseCount('sucursales', 0);
});

test('renders the edit form with the sucursal', function () {
    $usuario = User::factory()->create();
    $sucursal = Sucursal::factory()->create();

    $response = $this->actingAs($usuario)->get(route('sucursales.edit', $sucursal));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/sucursales/Edit', false)
        ->where('sucursal.id', $sucursal->id)
    );
});

test('updates a sucursal and redirects to the index', function () {
    $usuario = User::factory()->create();
    $sucursal = Sucursal::factory()->create(['termino_orden' => 'Orden']);

    $response = $this->actingAs($usuario)->put(route('sucursales.update', $sucursal), [
        'nombre' => $sucursal->nombre,
        'termino_orden' => 'Pedido',
        'direccion' => $sucursal->direccion,
        'telefono' => $sucursal->telefono,
        'activa' => true,
    ]);

    $response->assertRedirect(route('sucursales.index'));
    $this->assertDatabaseHas('sucursales', [
        'id' => $sucursal->id,
        'termino_orden' => 'Pedido',
    ]);
});

test('deletes a sucursal and redirects to the index', function () {
    $usuario = User::factory()->create();
    Sucursal::factory()->create();
    $sucursal = Sucursal::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('sucursales.destroy', $sucursal));

    $response->assertRedirect(route('sucursales.index'));
    $this->assertModelMissing($sucursal);
});

test('does not delete the last remaining sucursal', function () {
    $usuario = User::factory()->create();
    $sucursal = Sucursal::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('sucursales.destroy', $sucursal));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No se puede eliminar: debe quedar al menos una sucursal.']);
    $this->assertModelExists($sucursal);
});
