<?php

use App\Models\CierreCaja;
use App\Models\CierreCajaMetodo;
use App\Models\MetodoPago;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('metodos_pago.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of metodos de pago', function () {
    $usuario = User::factory()->create();
    MetodoPago::factory()->count(3)->create();

    $response = $this->actingAs($usuario)->get(route('metodos_pago.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/metodos_pago/Index', false)
        ->has('metodosPago.data', 3)
    );
});

test('creates a metodo de pago and redirects to the index', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('metodos_pago.store'), [
        'nombre' => 'Zelle',
        'moneda' => 'USD',
        'requiere_referencia' => true,
        'activo' => true,
    ]);

    $response->assertRedirect(route('metodos_pago.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Método de pago creado.']);
    $this->assertDatabaseHas('metodos_pago', [
        'nombre' => 'Zelle',
        'moneda' => 'USD',
        'requiere_referencia' => true,
    ]);
});

test('requires a nombre and a valid moneda to create a metodo de pago', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('metodos_pago.store'), [
        'moneda' => 'EUR',
        'requiere_referencia' => false,
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['nombre', 'moneda']);
    $this->assertDatabaseCount('metodos_pago', 0);
});

test('updates a metodo de pago and redirects to the index', function () {
    $usuario = User::factory()->create();
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->put(route('metodos_pago.update', $metodoPago), [
        'nombre' => 'Efectivo USD',
        'moneda' => 'USD',
        'requiere_referencia' => false,
        'activo' => false,
    ]);

    $response->assertRedirect(route('metodos_pago.index'));
    $this->assertDatabaseHas('metodos_pago', [
        'id' => $metodoPago->id,
        'activo' => false,
    ]);
});

test('deletes a metodo de pago and redirects to the index', function () {
    $usuario = User::factory()->create();
    $metodoPago = MetodoPago::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('metodos_pago.destroy', $metodoPago));

    $response->assertRedirect(route('metodos_pago.index'));
    $this->assertModelMissing($metodoPago);
});

test('does not delete a metodo de pago that has cierre caja metodos associated', function () {
    $usuario = User::factory()->create();
    $metodoPago = MetodoPago::factory()->create();
    CierreCajaMetodo::factory()->create([
        'metodo_pago_id' => $metodoPago->id,
        'cierre_caja_id' => CierreCaja::factory(),
    ]);

    $response = $this->actingAs($usuario)->delete(route('metodos_pago.destroy', $metodoPago));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No se puede eliminar: tiene pagos o cierres de caja asociados.']);
    $this->assertModelExists($metodoPago);
});

test('updates the orden of a metodo de pago and keeps it when not sent', function () {
    $usuario = User::factory()->create();
    $metodoPago = MetodoPago::factory()->create(['orden' => 1]);
    $datos = [
        'nombre' => $metodoPago->nombre,
        'moneda' => $metodoPago->moneda,
        'requiere_referencia' => false,
        'activo' => true,
    ];

    $this->actingAs($usuario)->put(route('metodos_pago.update', $metodoPago), [...$datos, 'orden' => 5]);
    $this->assertDatabaseHas('metodos_pago', ['id' => $metodoPago->id, 'orden' => 5]);

    $this->actingAs($usuario)->put(route('metodos_pago.update', $metodoPago), $datos);
    $this->assertDatabaseHas('metodos_pago', ['id' => $metodoPago->id, 'orden' => 5]);
});
