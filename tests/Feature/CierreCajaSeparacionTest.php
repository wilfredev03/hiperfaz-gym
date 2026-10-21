<?php

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->cierre = CierreCaja::factory()->create();
    $this->metodo = MetodoPago::factory()->efectivoUsd()->create();
    $this->usuario = User::factory()->create();
    $this->plan = Plan::factory()->duracion(30)->create();
    $this->plan->producto->update(['precio_venta' => 25]);
    $this->batido = Producto::factory()->sinControlDeInventario()->create(['precio_venta' => 5]);
});

function venderEnCierre($test, array $items, float $descuento = 0): Venta
{
    $subtotal = collect($items)->sum(fn ($i) => $i['producto']->precio_venta * $i['cantidad']);

    $id = $test->actingAs($test->usuario)->postJson(route('ventas.store'), [
        'es_credito' => false,
        'cliente_id' => Cliente::factory()->create()->id,
        'descuento' => $descuento,
        'items' => collect($items)->map(fn ($i) => ['producto_id' => $i['producto']->id, 'cantidad' => $i['cantidad']])->all(),
        'pagos' => [['metodo_pago_id' => $test->metodo->id, 'monto' => $subtotal - $descuento]],
    ])->assertCreated()->json('id');

    return Venta::findOrFail($id);
}

test('el resumen en vivo separa membresías y productos y suman el total', function () {
    venderEnCierre($this, [['producto' => $this->plan->producto, 'cantidad' => 1]]);
    // venta mixta con descuento: el descuento se reparte a prorrata (25 + 10 = 35 → 31.50)
    venderEnCierre($this, [
        ['producto' => $this->plan->producto, 'cantidad' => 1],
        ['producto' => $this->batido, 'cantidad' => 2],
    ], descuento: 3.5);

    $this->actingAs($this->usuario)->get(route('cierres_caja.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumenAbierto.total_ventas', 56.5)
            ->where('resumenAbierto.total_membresias', 47.5));
});

test('cerrar la caja guarda el total de membresías con su equivalente en bolívares', function () {
    venderEnCierre($this, [
        ['producto' => $this->plan->producto, 'cantidad' => 1],
        ['producto' => $this->batido, 'cantidad' => 3],
    ]);
    $tasa = (float) $this->cierre->tasaCambio->tasa;

    $this->actingAs($this->usuario)->postJson(route('cierres_caja.cerrar', $this->cierre))->assertOk();

    $cierre = $this->cierre->fresh();
    expect((float) $cierre->total_membresias)->toBe(25.0)
        ->and((float) $cierre->total_membresias_bs)->toBe(round(25 * $tasa, 4))
        ->and((float) $cierre->total_ventas)->toBe(40.0);
});

test('una venta anulada no cuenta como membresía vendida en el cierre', function () {
    $anulada = venderEnCierre($this, [['producto' => $this->plan->producto, 'cantidad' => 1]]);
    venderEnCierre($this, [['producto' => $this->batido, 'cantidad' => 2]]);

    $this->actingAs($this->usuario)->post(route('ventas.anular', $anulada), ['motivo' => 'Error']);

    $this->actingAs($this->usuario)->get(route('cierres_caja.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumenAbierto.total_ventas', 10)
            ->where('resumenAbierto.total_membresias', 0));
});

test('un cierre sin membresías guarda cero, no nulo', function () {
    venderEnCierre($this, [['producto' => $this->batido, 'cantidad' => 1]]);

    $this->actingAs($this->usuario)->postJson(route('cierres_caja.cerrar', $this->cierre))->assertOk();

    expect((float) $this->cierre->fresh()->total_membresias)->toBe(0.0);
});
