<?php

use App\Models\Categoria;
use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TasaCambio;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('creates a venta al contado with a single payment method', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $producto = Producto::factory()->create(['precio_venta' => 10, 'controla_inventario' => true, 'stock_actual' => 20]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => false,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 2],
        ],
        'pagos' => [
            ['metodo_pago_id' => $metodoPago->id, 'monto' => 20],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('estado', 'pagada');
    $response->assertJsonPath('total', '20.0000');
    $response->assertJsonPath('vuelto', '0.0000');
    $response->assertJsonPath('cierre_caja_id', $cierre->id);

    $this->assertDatabaseHas('venta_detalle', [
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'precio_unitario' => 10,
        'subtotal' => 20,
    ]);
    $this->assertDatabaseHas('venta_pagos', [
        'metodo_pago_id' => $metodoPago->id,
        'cierre_caja_id' => $cierre->id,
        'monto' => 20,
    ]);
});

test('creates a venta al contado with payment split across two methods', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $producto = Producto::factory()->create(['precio_venta' => 15]);
    $efectivo = MetodoPago::factory()->efectivoUsd()->create();
    $pagoMovil = MetodoPago::factory()->pagoMovil()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => false,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 2],
        ],
        'pagos' => [
            ['metodo_pago_id' => $efectivo->id, 'monto' => 20],
            ['metodo_pago_id' => $pagoMovil->id, 'monto' => 10, 'referencia' => '123456'],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('estado', 'pagada');
    $response->assertJsonPath('total', '30.0000');

    $ventaId = $response->json('id');

    $this->assertDatabaseCount('venta_pagos', 2);
    $this->assertDatabaseHas('venta_pagos', [
        'venta_id' => $ventaId,
        'metodo_pago_id' => $efectivo->id,
        'monto' => 20,
    ]);
    $this->assertDatabaseHas('venta_pagos', [
        'venta_id' => $ventaId,
        'metodo_pago_id' => $pagoMovil->id,
        'monto' => 10,
        'referencia' => '123456',
    ]);
});

test('rejects a credit sale without a client with a validation error', function () {
    $usuario = User::factory()->create();
    CierreCaja::factory()->create();
    $producto = Producto::factory()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => true,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 1],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cliente_id']);
    $this->assertDatabaseCount('ventas', 0);
});

test('the database rejects a credit sale without a client via its CHECK constraint', function () {
    $usuario = User::factory()->create();

    // se inserta dentro de una transacción anidada (SAVEPOINT) para que el
    // error de Postgres no invalide la transacción externa que abrió RefreshDatabase
    $insertar = fn () => DB::transaction(fn () => DB::table('ventas')->insert([
        'numero_venta' => 'V-CHECK-TEST',
        'cliente_id' => null,
        'usuario_id' => $usuario->id,
        'tasa_cambio_id' => null,
        'cierre_caja_id' => null,
        'subtotal' => 10,
        'descuento' => 0,
        'total' => 10,
        'es_credito' => true,
        'monto_recibido' => null,
        'vuelto' => null,
        'estado' => 'pendiente',
        'fecha' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect($insertar)->toThrow(QueryException::class);
    $this->assertDatabaseCount('ventas', 0);
});

test('creates a credit sale with no initial payment and leaves it pending', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 0]);
    $producto = Producto::factory()->create(['precio_venta' => 12]);

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => true,
        'cliente_id' => $cliente->id,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 1],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('estado', 'pendiente');
    $response->assertJsonPath('cliente_id', $cliente->id);
    $response->assertJsonPath('monto_recibido', null);

    $this->assertDatabaseCount('venta_pagos', 0);
    expect($cliente->fresh()->saldo_pendiente)->toBe('12.0000');
});

test('creates a credit sale with a partial payment and leaves it parcial', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 0]);
    $producto = Producto::factory()->create(['precio_venta' => 20]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => true,
        'cliente_id' => $cliente->id,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 1],
        ],
        'pagos' => [
            ['metodo_pago_id' => $metodoPago->id, 'monto' => 8],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('estado', 'parcial');

    $this->assertDatabaseHas('venta_pagos', [
        'venta_id' => $response->json('id'),
        'monto' => 8,
        'cierre_caja_id' => $cierre->id,
    ]);
    expect($cliente->fresh()->saldo_pendiente)->toBe('12.0000');
});

test('registers a movimiento_inventario and discounts stock for a product that controls inventory', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $producto = Producto::factory()->create([
        'precio_venta' => 5,
        'controla_inventario' => true,
        'stock_actual' => 50,
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => false,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 3.5],
        ],
        'pagos' => [
            ['metodo_pago_id' => $metodoPago->id, 'monto' => 17.5],
        ],
    ]);

    $response->assertCreated();
    $ventaId = $response->json('id');

    $this->assertDatabaseHas('movimientos_inventario', [
        'producto_id' => $producto->id,
        'tipo' => 'salida_venta',
        'cantidad' => 3.5,
        'stock_anterior' => 50,
        'stock_nuevo' => 46.5,
        'referencia_tipo' => 'venta',
        'referencia_id' => $ventaId,
    ]);
    expect((float) $producto->fresh()->stock_actual)->toBe(46.5);
});

test('does not register a movimiento_inventario for a product that does not control inventory', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create();
    $producto = Producto::factory()->sinControlDeInventario()->create(['precio_venta' => 2]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.store'), [
        'es_credito' => false,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 1],
        ],
        'pagos' => [
            ['metodo_pago_id' => $metodoPago->id, 'monto' => 2],
        ],
    ]);

    $response->assertCreated();
    $this->assertDatabaseCount('movimientos_inventario', 0);
    expect((float) $producto->fresh()->stock_actual)->toBe(0.0);
});

test('registers a partial abono to a credit sale and leaves it parcial', function () {
    $usuario = User::factory()->create();
    $cierreVenta = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    $cierre = CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 20]);
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreVenta->id,
        'es_credito' => true,
        'total' => 20,
        'estado' => 'pendiente',
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.abonar', $venta), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 8,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('monto', '8.0000');

    $this->assertDatabaseHas('venta_pagos', [
        'venta_id' => $venta->id,
        'metodo_pago_id' => $metodoPago->id,
        'cierre_caja_id' => $cierre->id,
        'monto' => 8,
    ]);
    expect($venta->fresh()->estado)->toBe('parcial');
    expect($cliente->fresh()->saldo_pendiente)->toBe('12.0000');
});

test('registers an abono that fully covers a credit sale and marks it pagada', function () {
    $usuario = User::factory()->create();
    $cierreVenta = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 15]);
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreVenta->id,
        'es_credito' => true,
        'total' => 15,
        'estado' => 'pendiente',
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.abonar', $venta), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 15,
    ]);

    $response->assertCreated();
    expect($venta->fresh()->estado)->toBe('pagada');
    expect($cliente->fresh()->saldo_pendiente)->toBe('0.0000');
});

test('records the abono under todays cierre_caja even when the venta belongs to an earlier one', function () {
    $usuario = User::factory()->create();
    $cierreVenta = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    $cierreHoy = CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 10]);
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreVenta->id,
        'es_credito' => true,
        'total' => 10,
        'estado' => 'pendiente',
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.abonar', $venta), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 10,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('venta_pagos', [
        'venta_id' => $venta->id,
        'cierre_caja_id' => $cierreHoy->id,
    ]);
    expect($venta->fresh()->cierre_caja_id)->toBe($cierreVenta->id);
});

test('rejects an abono greater than the saldo pendiente of the venta', function () {
    $usuario = User::factory()->create();
    $cierreVenta = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 10]);
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreVenta->id,
        'es_credito' => true,
        'total' => 10,
        'estado' => 'pendiente',
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.abonar', $venta), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 11,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['monto']);
    $this->assertDatabaseCount('venta_pagos', 0);
    expect($cliente->fresh()->saldo_pendiente)->toBe('10.0000');
});

test('rejects an abono when there is no cierre_caja open today', function () {
    $usuario = User::factory()->create();
    $cierreVenta = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 10]);
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreVenta->id,
        'es_credito' => true,
        'total' => 10,
        'estado' => 'pendiente',
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.abonar', $venta), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 5,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cierre_caja']);
    $this->assertDatabaseCount('venta_pagos', 0);
});

test('rejects an abono with a non positive monto', function () {
    $usuario = User::factory()->create();
    $cierreVenta = CierreCaja::factory()->cerrado()->create(['fecha' => now()->subDay()->toDateString()]);
    CierreCaja::factory()->create();
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 10]);
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreVenta->id,
        'es_credito' => true,
        'total' => 10,
        'estado' => 'pendiente',
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $metodoPago = MetodoPago::factory()->efectivoUsd()->create();

    $response = $this->actingAs($usuario)->postJson(route('ventas.abonar', $venta), [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => 0,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['monto']);
});

test('guests are redirected to the login page when viewing the ventas panel', function () {
    $response = $this->get(route('ventas.index'));

    $response->assertRedirect(route('login'));
});

test('the ventas panel defaults to showing only ventas from today', function () {
    $usuario = User::factory()->create();

    $deHoy = Venta::factory()->create(['fecha' => now()->toDateString(), 'total' => 20]);
    Venta::factory()->create(['fecha' => now()->subDay()->toDateString(), 'total' => 999]);

    $response = $this->actingAs($usuario)->get(route('ventas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        ->where('filtros.periodo', 'hoy')
        ->where('filtros.desde', now()->toDateString())
        ->where('filtros.hasta', now()->toDateString())
        ->has('ventas.data', 1)
        ->where('ventas.data.0.id', $deHoy->id)
    );
});

test('filters the ventas panel by esta semana', function () {
    $usuario = User::factory()->create();

    $dentro = Venta::factory()->create(['fecha' => now()->startOfWeek()->toDateString()]);
    Venta::factory()->create(['fecha' => now()->subWeek()->toDateString()]);

    $response = $this->actingAs($usuario)->get(route('ventas.index', ['periodo' => 'semana']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        ->has('ventas.data', 1)
        ->where('ventas.data.0.id', $dentro->id)
    );
});

test('filters the ventas panel by este mes', function () {
    $usuario = User::factory()->create();

    $dentro = Venta::factory()->create(['fecha' => now()->startOfMonth()->toDateString()]);
    Venta::factory()->create(['fecha' => now()->subMonthNoOverflow()->toDateString()]);

    $response = $this->actingAs($usuario)->get(route('ventas.index', ['periodo' => 'mes']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        ->has('ventas.data', 1)
        ->where('ventas.data.0.id', $dentro->id)
    );
});

test('filters the ventas panel by a custom date range', function () {
    $usuario = User::factory()->create();

    $dentro = Venta::factory()->create(['fecha' => '2026-03-15']);
    Venta::factory()->create(['fecha' => '2026-04-01']);

    $response = $this->actingAs($usuario)->get(route('ventas.index', [
        'periodo' => 'personalizado',
        'desde' => '2026-03-01',
        'hasta' => '2026-03-31',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        ->has('ventas.data', 1)
        ->where('ventas.data.0.id', $dentro->id)
        ->where('filtros.desde', '2026-03-01')
        ->where('filtros.hasta', '2026-03-31')
    );
});

test('rejects a custom date range without desde and hasta', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->get(route('ventas.index', ['periodo' => 'personalizado']));

    $response->assertSessionHasErrors(['desde', 'hasta']);
});

test('calculates the resumen totals in usd and bs and excludes anuladas', function () {
    $usuario = User::factory()->create();
    $tasa = TasaCambio::factory()->create(['tasa' => 100]);

    Venta::factory()->create(['fecha' => now()->toDateString(), 'tasa_cambio_id' => $tasa->id, 'total' => 20, 'estado' => 'pagada']);
    Venta::factory()->create(['fecha' => now()->toDateString(), 'tasa_cambio_id' => $tasa->id, 'total' => 30, 'estado' => 'pagada']);
    Venta::factory()->create(['fecha' => now()->toDateString(), 'tasa_cambio_id' => $tasa->id, 'total' => 999, 'estado' => 'anulada']);

    $response = $this->actingAs($usuario)->get(route('ventas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        // la anulada sigue apareciendo en el listado, pero no en el resumen
        ->has('ventas.data', 3)
        ->where('resumen.cantidad_ventas', 2)
        ->where('resumen.total_usd', fn ($valor) => (float) $valor === 50.0)
        ->where('resumen.total_bs', fn ($valor) => (float) $valor === 5000.0)
    );
});

test('breaks down the resumen by metodo de pago and excludes anuladas', function () {
    $usuario = User::factory()->create();
    $efectivo = MetodoPago::factory()->efectivoUsd()->create();
    $pagoMovil = MetodoPago::factory()->pagoMovil()->create();
    // un método activo sin un solo pago en el período: tiene que aparecer
    // igual, en 0, para que la grilla no cambie de tamaño según el filtro
    $transferencia = MetodoPago::factory()->transferencia()->create();
    MetodoPago::factory()->efectivoBs()->inactivo()->create();

    $venta = Venta::factory()->create(['fecha' => now()->toDateString(), 'estado' => 'pagada']);
    VentaPago::factory()->create(['venta_id' => $venta->id, 'metodo_pago_id' => $efectivo->id, 'monto' => 30]);
    VentaPago::factory()->create(['venta_id' => $venta->id, 'metodo_pago_id' => $pagoMovil->id, 'monto' => 15]);

    $ventaAnulada = Venta::factory()->create(['fecha' => now()->toDateString(), 'estado' => 'anulada']);
    VentaPago::factory()->create(['venta_id' => $ventaAnulada->id, 'metodo_pago_id' => $efectivo->id, 'monto' => 1000]);

    $response = $this->actingAs($usuario)->get(route('ventas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        // solo los tres activos, en el orden configurado (aquí, por nombre)
        ->has('resumen.por_metodo_pago', 3)
        ->where('resumen.por_metodo_pago.0.id', $efectivo->id)
        ->where('resumen.por_metodo_pago.0.total', fn ($valor) => (float) $valor === 30.0)
        ->where('resumen.por_metodo_pago.1.id', $pagoMovil->id)
        ->where('resumen.por_metodo_pago.1.total', fn ($valor) => (float) $valor === 15.0)
        ->where('resumen.por_metodo_pago.2.id', $transferencia->id)
        ->where('resumen.por_metodo_pago.2.total', fn ($valor) => (float) $valor === 0.0)
    );
});

test('converts each metodo de pago to bolivares with the tasa of its own venta', function () {
    $usuario = User::factory()->create();
    $pagoMovil = MetodoPago::factory()->pagoMovil()->create();

    // dos ventas del mismo día con tasas distintas: el equivalente en Bs se
    // arma con la tasa de cada venta, nunca con una sola tasa "de hoy"
    $tasaVieja = TasaCambio::factory()->create(['tasa' => 100]);
    $tasaNueva = TasaCambio::factory()->create(['tasa' => 200]);

    $ventaA = Venta::factory()->create(['fecha' => now()->toDateString(), 'estado' => 'pagada', 'tasa_cambio_id' => $tasaVieja->id]);
    $ventaB = Venta::factory()->create(['fecha' => now()->toDateString(), 'estado' => 'pagada', 'tasa_cambio_id' => $tasaNueva->id]);
    VentaPago::factory()->create(['venta_id' => $ventaA->id, 'metodo_pago_id' => $pagoMovil->id, 'monto' => 10]);
    VentaPago::factory()->create(['venta_id' => $ventaB->id, 'metodo_pago_id' => $pagoMovil->id, 'monto' => 10]);

    $response = $this->actingAs($usuario)->get(route('ventas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ventas/Index', false)
        // el monto siempre se guarda en USD
        ->where('resumen.por_metodo_pago.0.total', fn ($valor) => (float) $valor === 20.0)
        // 10 × 100 + 10 × 200
        ->where('resumen.por_metodo_pago.0.total_bs', fn ($valor) => (float) $valor === 3000.0)
    );
});
