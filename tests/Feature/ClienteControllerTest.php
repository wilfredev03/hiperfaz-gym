<?php

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('clientes.index'));

    $response->assertRedirect(route('login'));
});

test('cuentas por cobrar lists by default only the clientes with deuda, whoever they are', function () {
    $usuario = User::factory()->create();
    Cliente::factory()->conSaldoPendiente(30)->count(3)->create();
    Cliente::factory()->count(2)->create();

    $response = $this->actingAs($usuario)->get(route('clientes.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('clientes/Index', false)
        ->where('filtro', 'con_deuda')
        ->has('clientes.data', 3)
        ->where('seleccionado', null)
    );
});

test('the todos filter lists every cliente, with or without deuda', function () {
    $usuario = User::factory()->create();
    Cliente::factory()->conSaldoPendiente(30)->count(3)->create();
    Cliente::factory()->count(2)->create();

    $this->actingAs($usuario)->get(route('clientes.index', ['filtro' => 'todos']))
        ->assertInertia(fn ($page) => $page->where('filtro', 'todos')->has('clientes.data', 5));
});

test('each cliente in the list carries its ventas por cobrar, oldest first', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->conSaldoPendiente(70)->create();

    $reciente = Venta::factory()->pendiente()->create(['cliente_id' => $cliente->id, 'total' => 40, 'fecha' => '2026-02-01']);
    $vieja = Venta::factory()->parcial()->create(['cliente_id' => $cliente->id, 'total' => 50, 'fecha' => '2026-01-01']);
    VentaPago::factory()->for($vieja)->create(['monto' => 20]);
    Venta::factory()->for($cliente)->pagada()->create(['total' => 99]);

    $response = $this->actingAs($usuario)->get(route('clientes.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('clientes.data.0.ventas_pendientes', 2)
        ->where('clientes.data.0.ventas_pendientes.0.id', $vieja->id)
        ->where('clientes.data.0.ventas_pendientes.0.saldo_pendiente', 30)
        ->where('clientes.data.0.ventas_pendientes.1.id', $reciente->id)
    );
});

test('a cliente ficha can be opened straight on the venta tab', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->create();

    $this->actingAs($usuario)->get(route('clientes.index', ['cliente' => $cliente->id, 'vista' => 'venta']))
        ->assertInertia(fn ($page) => $page->where('vista', 'venta'));

    $this->actingAs($usuario)->get(route('clientes.index', ['cliente' => $cliente->id]))
        ->assertInertia(fn ($page) => $page->where('vista', 'cuenta'));
});

test('filters clientes by nombre or documento', function () {
    $usuario = User::factory()->create();
    Cliente::factory()->create(['nombre' => 'Juan Pérez', 'documento' => 'V-11111111']);
    Cliente::factory()->create(['nombre' => 'María Gómez', 'documento' => 'V-22222222']);
    Cliente::factory()->create(['nombre' => 'Otro Cliente', 'documento' => 'E-33333333']);

    $porNombre = $this->actingAs($usuario)->get(route('clientes.index', ['filtro' => 'todos', 'busqueda' => 'maría']));
    $porNombre->assertInertia(fn ($page) => $page
        ->component('clientes/Index', false)
        ->has('clientes.data', 1)
        ->where('clientes.data.0.nombre', 'María Gómez')
    );

    $porDocumento = $this->actingAs($usuario)->get(route('clientes.index', ['filtro' => 'todos', 'busqueda' => '333333']));
    $porDocumento->assertInertia(fn ($page) => $page
        ->has('clientes.data', 1)
        ->where('clientes.data.0.nombre', 'Otro Cliente')
    );
});

test('filters clientes with deuda, largest balance first, and summarizes what is owed', function () {
    $usuario = User::factory()->create();
    Cliente::factory()->create(['saldo_pendiente' => 0]);
    $chico = Cliente::factory()->create(['saldo_pendiente' => 10]);
    $grande = Cliente::factory()->create(['saldo_pendiente' => 90]);
    $enorme = Cliente::factory()->create(['saldo_pendiente' => 500]);

    $response = $this->actingAs($usuario)->get(route('clientes.index', ['filtro' => 'con_deuda']));

    $response->assertInertia(fn ($page) => $page
        ->has('clientes.data', 3)
        ->where('clientes.data.0.id', $enorme->id)
        ->where('clientes.data.1.id', $grande->id)
        ->where('clientes.data.2.id', $chico->id)
        ->where('resumen.clientes_con_deuda', 3)
        ->where('resumen.total_por_cobrar', 600)
    );
});

test('creates a cliente and stays on the list', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->from(route('clientes.index'))->post(route('clientes.store'), [
        'nombre' => 'Juan Pérez',
        'documento' => 'V-12345678',
        'telefono' => '04141234567',
        'email' => 'juan@example.com',
        'direccion' => 'Calle Falsa 123',
        'activo' => true,
    ]);

    $response->assertRedirect(route('clientes.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cliente creado.']);
    $this->assertDatabaseHas('clientes', ['documento' => 'V-12345678', 'nombre' => 'Juan Pérez']);
});

test('requires a nombre to create a cliente', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('clientes.store'), [
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['nombre']);
    $this->assertDatabaseCount('clientes', 0);
});

test('rejects a duplicate documento when creating a cliente', function () {
    $usuario = User::factory()->create();
    Cliente::factory()->create(['documento' => 'V-12345678']);

    $response = $this->actingAs($usuario)->post(route('clientes.store'), [
        'nombre' => 'Otro Cliente',
        'documento' => 'V-12345678',
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['documento']);
    $this->assertDatabaseCount('clientes', 1);
});

test('updates a cliente', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->create(['nombre' => 'Original', 'activo' => true]);

    $response = $this->actingAs($usuario)
        ->from(route('clientes.index', ['cliente' => $cliente->id]))
        ->put(route('clientes.update', $cliente), [
            'nombre' => 'Actualizado',
            'documento' => $cliente->documento,
            'activo' => false,
        ]);

    $response->assertRedirect(route('clientes.index', ['cliente' => $cliente->id]));
    $this->assertDatabaseHas('clientes', [
        'id' => $cliente->id,
        'nombre' => 'Actualizado',
        'activo' => false,
    ]);
});

test('allows a cliente to keep its own documento when updating', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->create(['documento' => 'V-99999999']);

    $response = $this->actingAs($usuario)->put(route('clientes.update', $cliente), [
        'nombre' => $cliente->nombre,
        'documento' => 'V-99999999',
        'activo' => true,
    ]);

    $response->assertSessionDoesntHaveErrors();
});

test('rejects a duplicate documento when updating a cliente', function () {
    $usuario = User::factory()->create();
    Cliente::factory()->create(['documento' => 'V-11111111']);
    $cliente = Cliente::factory()->create(['documento' => 'V-22222222']);

    $response = $this->actingAs($usuario)->put(route('clientes.update', $cliente), [
        'nombre' => $cliente->nombre,
        'documento' => 'V-11111111',
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['documento']);
    $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'documento' => 'V-22222222']);
});

test('deletes a cliente and redirects to the index', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('clientes.destroy', $cliente));

    $response->assertRedirect(route('clientes.index'));
    $this->assertSoftDeleted($cliente);
});

test('the selected cuenta shows the purchase history, total historically bought and last purchase date, excluding anuladas', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->create();

    Venta::factory()->for($cliente)->pagada()->create([
        'total' => 50,
        'fecha' => '2026-01-10',
    ]);
    $ultima = Venta::factory()->for($cliente)->pagada()->create([
        'total' => 30,
        'fecha' => '2026-02-15',
    ]);
    Venta::factory()->for($cliente)->anulada()->create([
        'total' => 999,
        'fecha' => '2026-03-01',
    ]);

    $response = $this->actingAs($usuario)->get(route('clientes.index', ['cliente' => $cliente->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('clientes/Index', false)
        ->where('seleccionado.cliente.id', $cliente->id)
        ->has('seleccionado.ventas', 2)
        ->where('seleccionado.total_comprado', 80)
        ->where('seleccionado.fecha_ultima_compra', $ultima->fecha->toDateString())
    );
});

test('the selected cuenta reports what was paid and the outstanding balance of a partially paid credit sale', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->conSaldoPendiente(60)->create();

    $venta = Venta::factory()->parcial()->create(['cliente_id' => $cliente->id, 'total' => 100]);
    VentaPago::factory()->for($venta)->create(['monto' => 40]);

    $response = $this->actingAs($usuario)->get(route('clientes.index', ['cliente' => $cliente->id]));

    $response->assertInertia(fn ($page) => $page
        ->where('seleccionado.ventas.0.estado', 'parcial')
        ->where('seleccionado.ventas.0.total_pagado', 40)
        ->where('seleccionado.ventas.0.saldo_pendiente', 60)
    );
});

test('the catalog is only sent when explicitly requested', function () {
    $usuario = User::factory()->create();
    Producto::factory()->count(2)->create();

    $this->actingAs($usuario)->get(route('clientes.index'))
        ->assertInertia(fn ($page) => $page->missing('productos'));

    $this->actingAs($usuario)
        ->get(route('clientes.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'clientes/Index',
            'X-Inertia-Partial-Data' => 'productos',
            'X-Inertia-Version' => inertia()->getVersion(),
        ])
        ->assertJsonCount(2, 'props.productos');
});

test('an abono general is applied to the oldest pending ventas first', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 80]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $vieja = Venta::factory()->pendiente()->create([
        'cliente_id' => $cliente->id,
        'total' => 30,
        'fecha' => '2026-01-01',
    ]);
    $reciente = Venta::factory()->pendiente()->create([
        'cliente_id' => $cliente->id,
        'total' => 50,
        'fecha' => '2026-02-01',
    ]);

    $response = $this->actingAs($usuario)->post(route('clientes.abonar', $cliente), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 45,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('venta_pagos', ['venta_id' => $vieja->id, 'monto' => 30, 'cierre_caja_id' => $cierre->id]);
    $this->assertDatabaseHas('venta_pagos', ['venta_id' => $reciente->id, 'monto' => 15, 'cierre_caja_id' => $cierre->id]);
    expect($vieja->fresh()->estado)->toBe('pagada')
        ->and($reciente->fresh()->estado)->toBe('parcial')
        ->and($cliente->fresh()->saldo_pendiente)->toBe('35.0000');
});

test('rejects an abono general greater than the deuda total of the cliente', function () {
    $usuario = User::factory()->create();
    CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 20]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();
    Venta::factory()->pendiente()->create(['cliente_id' => $cliente->id, 'total' => 20]);

    $response = $this->actingAs($usuario)->post(route('clientes.abonar', $cliente), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 25,
    ]);

    $response->assertSessionHasErrors(['monto']);
    $this->assertDatabaseCount('venta_pagos', 0);
    expect($cliente->fresh()->saldo_pendiente)->toBe('20.0000');
});

test('rejects an abono general when there is no open caja today', function () {
    $usuario = User::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 20]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();
    // la fábrica de ventas abre una caja de hoy por su cuenta: se fija una cerrada de ayer
    $cierreAyer = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    Venta::factory()->pendiente()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreAyer->id,
        'total' => 20,
    ]);

    $response = $this->actingAs($usuario)->post(route('clientes.abonar', $cliente), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 10,
    ]);

    $response->assertSessionHasErrors(['cierre_caja']);
    $this->assertDatabaseCount('venta_pagos', 0);
});
