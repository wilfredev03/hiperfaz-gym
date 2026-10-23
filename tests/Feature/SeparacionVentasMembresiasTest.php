<?php

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;

beforeEach(function () {
    CierreCaja::factory()->create();
    $this->metodo = MetodoPago::factory()->efectivoUsd()->create();
    $this->usuario = User::factory()->create();
    $this->plan = Plan::factory()->duracion(30)->create();
    $this->plan->producto->update(['precio_venta' => 25]);
    $this->batido = Producto::factory()->sinControlDeInventario()->create(['precio_venta' => 5]);
});

// las membresías ya no se venden por el punto de venta, pero una venta mixta
// puede existir (datos anteriores): se registra con el mismo servicio
function venderLineas($test, array $items, ?Cliente $cliente = null, ?float $monto = null): Venta
{
    $total = collect($items)->sum(fn ($i) => $i['precio'] * $i['cantidad']);

    $test->actingAs($test->usuario);

    return app(VentaService::class)->registrar([
        'es_credito' => false,
        'cliente_id' => $cliente?->id,
        'items' => collect($items)->map(fn ($i) => ['producto_id' => $i['producto']->id, 'cantidad' => $i['cantidad']])->all(),
        'pagos' => [['metodo_pago_id' => $test->metodo->id, 'monto' => $monto ?? $total]],
    ]);
}

test('ventas del día no incluye una venta que solo lleva membresías', function () {
    venderLineas($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], Cliente::factory()->create());
    venderLineas($this, [['producto' => $this->batido, 'precio' => 5, 'cantidad' => 2]]);

    $this->actingAs($this->usuario)->get(route('ventas.index'))
        ->assertInertia(fn ($page) => $page
            ->has('ventas.data', 1)
            ->where('resumen.cantidad_ventas', 1)
            ->where('resumen.total_usd', 10));
});

test('una venta mixta solo cuenta la parte de productos', function () {
    venderLineas($this, [
        ['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1],
        ['producto' => $this->batido, 'precio' => 5, 'cantidad' => 2],
    ], Cliente::factory()->create());

    $this->actingAs($this->usuario)->get(route('ventas.index'))
        ->assertInertia(fn ($page) => $page
            ->has('ventas.data', 1)
            ->where('ventas.data.0.incluye_membresia', true)
            ->where('ventas.data.0.total_productos', fn ($total) => (float) $total === 10.0)
            ->where('resumen.total_usd', 10)
            // el pago de 35 se prorratea: solo 10 corresponden a productos
            ->where('resumen.por_metodo_pago', fn ($metodos) => (float) collect($metodos)->firstWhere('id', $this->metodo->id)['total'] === 10.0));
});

test('una venta de solo productos conserva su total completo', function () {
    venderLineas($this, [['producto' => $this->batido, 'precio' => 5, 'cantidad' => 3]]);

    $this->actingAs($this->usuario)->get(route('ventas.index'))
        ->assertInertia(fn ($page) => $page
            ->where('ventas.data.0.incluye_membresia', false)
            ->where('resumen.total_usd', 15));
});

test('membresías vendidas distingue altas de renovaciones y suma lo vendido', function () {
    $nuevo = Cliente::factory()->create();
    $antiguo = Cliente::factory()->create();

    venderLineas($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], $antiguo);
    venderLineas($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], $antiguo); // renovación
    venderLineas($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], $nuevo);

    $this->actingAs($this->usuario)->get(route('membresias_vendidas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('membresias_vendidas/Index', false)
            ->where('resumen.cantidad', 3)
            ->where('resumen.altas', 2)
            ->where('resumen.renovaciones', 1)
            ->where('resumen.total_usd', 75)
            ->has('membresias.data', 3));
});

test('membresías vendidas no cuenta productos ni ventas fuera del rango', function () {
    venderLineas($this, [['producto' => $this->batido, 'precio' => 5, 'cantidad' => 1]]);
    venderLineas($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], Cliente::factory()->create());
    \App\Models\Venta::query()->update(['fecha' => now()->subDays(40)->toDateString()]);

    $this->actingAs($this->usuario)->get(route('membresias_vendidas.index', ['periodo' => 'hoy']))
        ->assertInertia(fn ($page) => $page->where('resumen.cantidad', 0));

    $this->actingAs($this->usuario)->get(route('membresias_vendidas.index', [
        'periodo' => 'personalizado',
        'desde' => now()->subDays(45)->toDateString(),
        'hasta' => now()->toDateString(),
    ]))->assertInertia(fn ($page) => $page->where('resumen.cantidad', 1));
});

test('membresías vendidas ignora las ventas anuladas', function () {
    venderLineas($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], Cliente::factory()->create());
    \App\Models\Venta::query()->update(['estado' => 'anulada']);

    $this->actingAs($this->usuario)->get(route('membresias_vendidas.index'))
        ->assertInertia(fn ($page) => $page->where('resumen.cantidad', 0));
});
