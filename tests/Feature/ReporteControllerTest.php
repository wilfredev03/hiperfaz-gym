<?php

use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;

test('rejects report requests without a desde/hasta range', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->getJson(route('reportes.ventas_por_periodo'));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['desde', 'hasta']);
});

test('groups ventas por periodo by day excluding anuladas', function () {
    $usuario = User::factory()->create();

    Venta::factory()->create(['fecha' => '2026-01-10', 'total' => 20, 'descuento' => 0, 'estado' => 'pagada']);
    Venta::factory()->create(['fecha' => '2026-01-10', 'total' => 30, 'descuento' => 5, 'estado' => 'pagada']);
    Venta::factory()->create(['fecha' => '2026-01-11', 'total' => 40, 'estado' => 'pagada']);
    Venta::factory()->create(['fecha' => '2026-01-10', 'total' => 999, 'estado' => 'anulada']);
    Venta::factory()->create(['fecha' => '2026-01-01', 'total' => 999, 'estado' => 'pagada']);

    $response = $this->actingAs($usuario)->getJson(route('reportes.ventas_por_periodo', [
        'desde' => '2026-01-10',
        'hasta' => '2026-01-11',
        'agrupacion' => 'dia',
    ]));

    $response->assertOk();
    $dias = collect($response->json())->keyBy(fn ($fila) => substr($fila['periodo'], 0, 10));

    expect($dias->get('2026-01-10')['cantidad_ventas'])->toBe(2)
        ->and($dias->get('2026-01-10')['total_ventas'])->toBe('50.0000')
        ->and($dias->get('2026-01-11')['cantidad_ventas'])->toBe(1)
        ->and($dias->get('2026-01-11')['total_ventas'])->toBe('40.0000')
        ->and($dias->has('2026-01-01'))->toBeFalse();
});

test('desglosa el monto recibido por metodo de pago dentro del rango', function () {
    $usuario = User::factory()->create();
    $efectivo = MetodoPago::factory()->efectivoUsd()->create();
    $pagoMovil = MetodoPago::factory()->pagoMovil()->create();

    $ventaEnRango = Venta::factory()->create(['fecha' => '2026-02-05', 'estado' => 'pagada']);
    VentaPago::factory()->create(['venta_id' => $ventaEnRango->id, 'metodo_pago_id' => $efectivo->id, 'monto' => 30]);
    VentaPago::factory()->create(['venta_id' => $ventaEnRango->id, 'metodo_pago_id' => $pagoMovil->id, 'monto' => 15]);

    $ventaAnulada = Venta::factory()->create(['fecha' => '2026-02-05', 'estado' => 'anulada']);
    VentaPago::factory()->create(['venta_id' => $ventaAnulada->id, 'metodo_pago_id' => $efectivo->id, 'monto' => 1000]);

    $ventaFueraDeRango = Venta::factory()->create(['fecha' => '2026-02-20', 'estado' => 'pagada']);
    VentaPago::factory()->create(['venta_id' => $ventaFueraDeRango->id, 'metodo_pago_id' => $efectivo->id, 'monto' => 1000]);

    $response = $this->actingAs($usuario)->getJson(route('reportes.desglose_metodo_pago', [
        'desde' => '2026-02-01',
        'hasta' => '2026-02-10',
    ]));

    $response->assertOk();
    $porMetodo = collect($response->json())->keyBy('metodo_pago_id');

    expect($porMetodo->get($efectivo->id)['total_recibido'])->toBe('30.0000')
        ->and($porMetodo->get($pagoMovil->id)['total_recibido'])->toBe('15.0000');
});

test('calcula el margen bruto del periodo usando costo_unitario_momento', function () {
    $usuario = User::factory()->create();
    $venta = Venta::factory()->create(['fecha' => '2026-03-01', 'estado' => 'pagada']);

    VentaDetalle::factory()->create([
        'venta_id' => $venta->id,
        'cantidad' => 2,
        'precio_unitario' => 10,
        'costo_unitario_momento' => 6,
        'subtotal' => 20,
    ]);
    VentaDetalle::factory()->create([
        'venta_id' => $venta->id,
        'cantidad' => 1,
        'precio_unitario' => 5,
        'costo_unitario_momento' => 3,
        'subtotal' => 5,
    ]);

    $ventaAnulada = Venta::factory()->create(['fecha' => '2026-03-01', 'estado' => 'anulada']);
    VentaDetalle::factory()->create([
        'venta_id' => $ventaAnulada->id,
        'cantidad' => 100,
        'precio_unitario' => 100,
        'costo_unitario_momento' => 1,
        'subtotal' => 10000,
    ]);

    $response = $this->actingAs($usuario)->getJson(route('reportes.margen_ganancia', [
        'desde' => '2026-03-01',
        'hasta' => '2026-03-01',
    ]));

    $response->assertOk();
    // ingresos = 20 + 5 = 25; costo = (2*6) + (1*3) = 15; margen = 10
    $response->assertJsonPath('total_ingresos', '25.0000');
    $response->assertJsonPath('total_costo', '15.0000');
    $response->assertJsonPath('margen_bruto', '10.0000');
});

test('lista los productos mas vendidos ordenados por cantidad y respeta el limite', function () {
    $usuario = User::factory()->create();
    $venta = Venta::factory()->create(['fecha' => '2026-04-01', 'estado' => 'pagada']);

    $masVendido = Producto::factory()->create(['nombre' => 'Proteína Whey Especial']);
    $menosVendido = Producto::factory()->create(['nombre' => 'Barra Energética']);

    VentaDetalle::factory()->create([
        'venta_id' => $venta->id,
        'producto_id' => $masVendido->id,
        'cantidad' => 10,
        'subtotal' => 100,
    ]);
    VentaDetalle::factory()->create([
        'venta_id' => $venta->id,
        'producto_id' => $menosVendido->id,
        'cantidad' => 3,
        'subtotal' => 30,
    ]);

    $response = $this->actingAs($usuario)->getJson(route('reportes.productos_mas_vendidos', [
        'desde' => '2026-04-01',
        'hasta' => '2026-04-01',
        'limite' => 1,
    ]));

    $response->assertOk();
    $productos = $response->json();

    expect($productos)->toHaveCount(1)
        ->and($productos[0]['producto_id'])->toBe($masVendido->id)
        ->and($productos[0]['cantidad_vendida'])->toBe('10.000');
});
