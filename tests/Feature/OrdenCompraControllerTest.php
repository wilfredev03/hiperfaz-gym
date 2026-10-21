<?php

use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TasaCambio;
use App\Models\User;

test('creates an orden de compra and moves inventory immediately', function () {
    $usuario = User::factory()->create();
    $proveedor = Proveedor::factory()->create();
    $producto = Producto::factory()->create(['costo_promedio' => 1, 'stock_actual' => 10, 'controla_inventario' => true]);

    $response = $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'proveedor_id' => $proveedor->id,
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 5, 'costo_unitario' => 3],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('estado', 'recibida_completa');
    $response->assertJsonPath('proveedor_id', $proveedor->id);
    $response->assertJsonPath('total', '15.0000');

    $this->assertDatabaseHas('orden_compra_detalle', [
        'producto_id' => $producto->id,
        'cantidad' => 5,
        'cantidad_recibida' => 5,
        'costo_unitario' => 3,
        'subtotal' => 15,
    ]);

    $this->assertDatabaseHas('movimientos_inventario', [
        'producto_id' => $producto->id,
        'tipo' => 'entrada_compra',
        'cantidad' => 5,
        'stock_anterior' => 10,
        'stock_nuevo' => 15,
        'referencia_tipo' => 'orden_compra',
    ]);

    $producto->refresh();
    // (10 * 1 + 5 * 3) / 15 = 1.6667
    expect((float) $producto->stock_actual)->toBe(15.0);
    expect((float) $producto->costo_promedio)->toBe(1.6667);
});

test('el proveedor es opcional', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create();

    $response = $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 2, 'costo_unitario' => 1.5],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('proveedor_id', null);
});

test('un producto sin control de inventario no genera movimiento ni cambia el stock ni el costo', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->sinControlDeInventario()->create(['costo_promedio' => 1]);

    $response = $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'items' => [
            ['producto_id' => $producto->id, 'cantidad' => 5, 'costo_unitario' => 3],
        ],
    ]);

    $response->assertCreated();

    $this->assertDatabaseCount('movimientos_inventario', 0);
    $producto->refresh();
    expect((float) $producto->stock_actual)->toBe(0.0);
    expect((float) $producto->costo_promedio)->toBe(1.0);
});

test('una compra con varios productos suma bien el total y mueve cada producto por separado', function () {
    $usuario = User::factory()->create();
    $productoA = Producto::factory()->create(['stock_actual' => 0, 'costo_promedio' => 0]);
    $productoB = Producto::factory()->create(['stock_actual' => 4, 'costo_promedio' => 2]);

    $response = $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'items' => [
            ['producto_id' => $productoA->id, 'cantidad' => 10, 'costo_unitario' => 1],
            ['producto_id' => $productoB->id, 'cantidad' => 6, 'costo_unitario' => 4],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('total', '34.0000');

    $this->assertDatabaseCount('movimientos_inventario', 2);
    $productoA->refresh();
    $productoB->refresh();
    expect((float) $productoA->stock_actual)->toBe(10.0);
    expect((float) $productoA->costo_promedio)->toBe(1.0);
    // (4 * 2 + 6 * 4) / 10 = 3.2
    expect((float) $productoB->stock_actual)->toBe(10.0);
    expect((float) $productoB->costo_promedio)->toBe(3.2);
});

test('una compra en bolívares se convierte a USD con la tasa del día', function () {
    $usuario = User::factory()->create();
    TasaCambio::factory()->create(['tasa' => 40, 'activa' => true]);
    $producto = Producto::factory()->create(['stock_actual' => 0, 'costo_promedio' => 0]);

    $response = $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'moneda' => 'VES',
        'items' => [
            // 200 Bs / 40 = 5 USD por unidad
            ['producto_id' => $producto->id, 'cantidad' => 3, 'costo_unitario' => 200],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('total', '15.0000');
    $this->assertDatabaseHas('orden_compra_detalle', ['producto_id' => $producto->id, 'costo_unitario' => 5]);
    expect((float) $producto->fresh()->costo_promedio)->toBe(5.0);
});

test('no se puede registrar en bolívares sin tasa de cambio', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create();

    $response = $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'moneda' => 'VES',
        'items' => [['producto_id' => $producto->id, 'cantidad' => 1, 'costo_unitario' => 100]],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['moneda']);
    $this->assertDatabaseCount('ordenes_compra', 0);
});

function registrarCompra(User $usuario, Producto $producto, float $cantidad, float $costo): OrdenCompra
{
    test()->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
        'items' => [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]],
    ])->assertCreated();

    return OrdenCompra::latest('id')->firstOrFail();
}

test('anular una compra revierte el stock con un ajuste en el ledger y restaura el costo promedio', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['stock_actual' => 10, 'costo_promedio' => 2, 'controla_inventario' => true]);

    // costo mal digitado: 100 en vez de 10 → el promedio se dispara
    $orden = registrarCompra($usuario, $producto, 10, 100);
    expect((float) $producto->fresh()->costo_promedio)->toBe(51.0);

    $response = $this->actingAs($usuario)->post(route('ordenes_compra.anular', $orden));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Compra anulada.']);
    expect($orden->fresh()->estado)->toBe('cancelada');

    $producto->refresh();
    expect((float) $producto->stock_actual)->toBe(10.0);
    expect((float) $producto->costo_promedio)->toBe(2.0);

    $this->assertDatabaseHas('movimientos_inventario', [
        'producto_id' => $producto->id,
        'tipo' => 'ajuste_negativo',
        'cantidad' => 10,
        'stock_anterior' => 20,
        'stock_nuevo' => 10,
        'referencia_tipo' => 'orden_compra',
        'referencia_id' => $orden->id,
    ]);
    // el ledger es append-only: la entrada original sigue ahí
    $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'entrada_compra', 'referencia_id' => $orden->id]);
});

test('no se puede anular una compra si ya se vendió parte de lo comprado', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['stock_actual' => 0, 'costo_promedio' => 0, 'controla_inventario' => true]);
    $orden = registrarCompra($usuario, $producto, 10, 3);
    $producto->update(['stock_actual' => 4]); // se vendieron 6

    $response = $this->actingAs($usuario)->post(route('ordenes_compra.anular', $orden));

    $response->assertSessionHasErrors(['anular']);
    expect($orden->fresh()->estado)->toBe('recibida_completa');
    expect((float) $producto->fresh()->stock_actual)->toBe(4.0);
    $this->assertDatabaseMissing('movimientos_inventario', ['tipo' => 'ajuste_negativo']);
});

test('una compra no se puede anular dos veces', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['stock_actual' => 0, 'costo_promedio' => 0, 'controla_inventario' => true]);
    $orden = registrarCompra($usuario, $producto, 5, 2);

    $this->actingAs($usuario)->post(route('ordenes_compra.anular', $orden));
    $response = $this->actingAs($usuario)->post(route('ordenes_compra.anular', $orden));

    $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Esta compra ya está anulada.']);
    expect((float) $producto->fresh()->stock_actual)->toBe(0.0);
    $this->assertDatabaseCount('movimientos_inventario', 2);
});

test('un vendedor no puede ver ni anular compras', function () {
    $admin = User::factory()->create();
    $producto = Producto::factory()->create(['controla_inventario' => true]);
    $orden = registrarCompra($admin, $producto, 1, 1);
    $vendedor = User::factory()->vendedor()->create();

    $this->actingAs($vendedor)->get(route('ordenes_compra.index'))->assertForbidden();
    $this->actingAs($vendedor)->post(route('ordenes_compra.anular', $orden))->assertForbidden();
    expect($orden->fresh()->estado)->toBe('recibida_completa');
});

test('el detalle de una compra se devuelve como JSON para el panel lateral', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['controla_inventario' => true]);
    $orden = registrarCompra($usuario, $producto, 2, 3);

    $this->actingAs($usuario)->getJson(route('ordenes_compra.show', $orden))
        ->assertOk()
        ->assertJsonPath('numero_orden', $orden->numero_orden)
        ->assertJsonPath('detalles.0.producto.id', $producto->id);
});

test('el costo de cada producto es obligatorio y mayor que cero', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create();

    foreach ([0, null] as $costo) {
        $this->actingAs($usuario)->postJson(route('ordenes_compra.store'), [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1, 'costo_unitario' => $costo]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.costo_unitario']);
    }

    $this->assertDatabaseCount('ordenes_compra', 0);
});

test('la pantalla de nueva compra no sugiere costo y solo da el de la última compra no anulada como referencia', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['costo_promedio' => 7, 'stock_actual' => 0, 'controla_inventario' => true]);
    $sinCompras = Producto::factory()->create();

    registrarCompra($usuario, $producto, 1, 4);
    registrarCompra($usuario, $producto, 1, 6);
    $anulada = registrarCompra($usuario, $producto, 1, 99);
    $this->actingAs($usuario)->post(route('ordenes_compra.anular', $anulada));

    $this->actingAs($usuario)->get(route('ordenes_compra.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ordenes_compra/Create', false)
            ->where('productos', fn ($productos) => collect($productos)->every(fn ($p) => ! array_key_exists('costo_promedio', (array) $p))
                && (float) collect($productos)->firstWhere('id', $producto->id)['ultimo_costo'] === 6.0
                && collect($productos)->firstWhere('id', $sinCompras->id)['ultimo_costo'] === null)
        );
});
