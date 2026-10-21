<?php

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\MovimientoInventario;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Suscripcion;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    CierreCaja::factory()->create();
    $this->metodo = MetodoPago::factory()->efectivoUsd()->create();
    $this->admin = User::factory()->create();
    $this->plan = Plan::factory()->duracion(30)->create();
    $this->plan->producto->update(['precio_venta' => 25]);
    $this->suplemento = Producto::factory()->create([
        'controla_inventario' => true,
        'stock_actual' => 10,
        'precio_venta' => 5,
    ]);
});

function venderAnulable($test, array $items, ?Cliente $cliente = null, bool $credito = false)
{
    $total = collect($items)->sum(fn ($i) => $i['precio'] * $i['cantidad']);

    $datos = [
        'es_credito' => $credito,
        'cliente_id' => $cliente?->id,
        'items' => collect($items)->map(fn ($i) => ['producto_id' => $i['producto']->id, 'cantidad' => $i['cantidad']])->all(),
    ];

    if (! $credito) {
        $datos['pagos'] = [['metodo_pago_id' => $test->metodo->id, 'monto' => $total]];
    }

    return Venta::findOrFail($test->actingAs($test->admin)->postJson(route('ventas.store'), $datos)->assertCreated()->json('id'));
}

test('anular devuelve el stock con un movimiento nuevo y deja la venta marcada', function () {
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 4]]);
    expect($this->suplemento->fresh()->stock_actual)->toEqual(6);

    $this->actingAs($this->admin)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Cobro duplicado'])
        ->assertSessionHasNoErrors();

    $venta->refresh();
    expect($venta->estado)->toBe('anulada')
        ->and($venta->motivo_anulacion)->toBe('Cobro duplicado')
        ->and($venta->anulada_por_id)->toBe($this->admin->id)
        ->and($venta->anulada_en)->not->toBeNull()
        ->and($this->suplemento->fresh()->stock_actual)->toEqual(10);

    $devolucion = MovimientoInventario::where('tipo', 'devolucion')->where('referencia_id', $venta->id)->sole();
    expect((float) $devolucion->stock_anterior)->toBe(6.0)
        ->and((float) $devolucion->stock_nuevo)->toBe(10.0);

    // el ledger conserva la salida original: solo se agregó una fila
    expect(MovimientoInventario::where('tipo', 'salida_venta')->where('referencia_id', $venta->id)->count())->toBe(1);
});

test('anular cancela la suscripción sin tocar sus fechas', function () {
    $socio = Cliente::factory()->create();
    $venta = venderAnulable($this, [['producto' => $this->plan->producto, 'precio' => 25, 'cantidad' => 1]], $socio);
    $suscripcion = Suscripcion::where('cliente_id', $socio->id)->sole();
    $inicio = $suscripcion->fecha_inicio->toDateString();
    $vencimiento = $suscripcion->fecha_vencimiento->toDateString();

    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => 'Plan equivocado']);

    $suscripcion->refresh();
    expect($suscripcion->estado)->toBe('cancelada')
        ->and($suscripcion->fecha_inicio->toDateString())->toBe($inicio)
        ->and($suscripcion->fecha_vencimiento->toDateString())->toBe($vencimiento)
        ->and($socio->fresh()->suscripcionVigente())->toBeNull();
});

test('anular una venta a crédito descuenta la deuda del cliente', function () {
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 0]);
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 2]], $cliente, credito: true);
    expect((float) $cliente->fresh()->saldo_pendiente)->toBe(10.0);

    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => 'Error']);

    expect((float) $cliente->fresh()->saldo_pendiente)->toBe(0.0);
});

test('no se puede anular dos veces', function () {
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 1]]);

    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => 'Error']);
    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => 'Otra vez'])
        ->assertSessionHasErrors('anular');

    expect(MovimientoInventario::where('tipo', 'devolucion')->count())->toBe(1);
});

test('no se puede anular si la caja de la venta ya está cerrada', function () {
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 1]]);
    CierreCaja::query()->update(['estado' => 'cerrado']);

    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => 'Tarde'])
        ->assertSessionHasErrors('anular');

    expect($venta->fresh()->estado)->not->toBe('anulada')
        ->and($this->suplemento->fresh()->stock_actual)->toEqual(9);
});

test('el motivo es obligatorio', function () {
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 1]]);

    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
});

test('un vendedor no puede anular ventas', function () {
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 1]]);

    $this->actingAs(User::factory()->vendedor()->create())
        ->post(route('ventas.anular', $venta), ['motivo' => 'Quiero'])
        ->assertForbidden();

    expect($venta->fresh()->estado)->toBe('pagada');
});

test('una venta anulada no cuenta en ventas del día ni se puede abonar', function () {
    $cliente = Cliente::factory()->create(['saldo_pendiente' => 0]);
    $venta = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 2]], $cliente, credito: true);
    venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 1]]);

    $this->actingAs($this->admin)->post(route('ventas.anular', $venta), ['motivo' => 'Error']);

    $this->actingAs($this->admin)->get(route('ventas.index'))
        ->assertInertia(fn ($page) => $page->where('resumen.cantidad_ventas', 1)->where('resumen.total_usd', 5));

    $this->actingAs($this->admin)
        ->postJson(route('ventas.abonar', $venta), ['metodo_pago_id' => $this->metodo->id, 'monto' => 1])
        ->assertUnprocessable();
});

test('el cierre de caja no suma las ventas anuladas', function () {
    $anulada = venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 2]]);
    venderAnulable($this, [['producto' => $this->suplemento, 'precio' => 5, 'cantidad' => 1]]);

    $this->actingAs($this->admin)->post(route('ventas.anular', $anulada), ['motivo' => 'Error']);

    $cierre = CierreCaja::query()->latest('id')->first();

    $this->actingAs($this->admin)->postJson(route('cierres_caja.cerrar', $cierre))
        ->assertOk()
        ->assertJsonPath('total_ventas', fn ($total) => (float) $total === 5.0)
        ->assertJsonPath('detalle_metodos.0.monto', fn ($monto) => (float) $monto === 5.0);
});
