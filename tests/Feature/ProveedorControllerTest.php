<?php

use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('proveedores.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of proveedores', function () {
    $usuario = User::factory()->create();
    Proveedor::factory()->count(3)->create();

    $response = $this->actingAs($usuario)->get(route('proveedores.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/proveedores/Index', false)
        ->has('proveedores.data', 3)
    );
});

test('creates a proveedor and redirects to the index', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('proveedores.store'), [
        'nombre' => 'Distribuidora La Económica C.A.',
        'documento' => 'J-12345678-9',
        'telefono' => '04141234567',
        'email' => 'contacto@laeconomica.test',
        'direccion' => 'Av. Principal, Caracas',
        'activo' => true,
    ]);

    $response->assertRedirect(route('proveedores.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Proveedor creado.']);
    $this->assertDatabaseHas('proveedores', [
        'nombre' => 'Distribuidora La Económica C.A.',
        'documento' => 'J-12345678-9',
    ]);
});

test('requires a nombre to create a proveedor', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('proveedores.store'), [
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['nombre']);
    $this->assertDatabaseCount('proveedores', 0);
});

test('updates a proveedor and redirects to the index', function () {
    $usuario = User::factory()->create();
    $proveedor = Proveedor::factory()->create(['nombre' => 'Original C.A.', 'activo' => true]);

    $response = $this->actingAs($usuario)->put(route('proveedores.update', $proveedor), [
        'nombre' => 'Actualizado C.A.',
        'activo' => false,
    ]);

    $response->assertRedirect(route('proveedores.index'));
    $this->assertDatabaseHas('proveedores', [
        'id' => $proveedor->id,
        'nombre' => 'Actualizado C.A.',
        'activo' => false,
    ]);
});

test('deletes a proveedor and redirects to the index', function () {
    $usuario = User::factory()->create();
    $proveedor = Proveedor::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('proveedores.destroy', $proveedor));

    $response->assertRedirect(route('proveedores.index'));
    $this->assertModelMissing($proveedor);
});

test('does not delete a proveedor that has ordenes de compra associated', function () {
    $usuario = User::factory()->create();
    $proveedor = Proveedor::factory()->create();
    OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);

    $response = $this->actingAs($usuario)->delete(route('proveedores.destroy', $proveedor));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No se puede eliminar: tiene órdenes de compra asociadas.']);
    $this->assertModelExists($proveedor);
});
