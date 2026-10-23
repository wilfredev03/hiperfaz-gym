<?php

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\TasaCambio;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->tasa = TasaCambio::factory()->create(['tasa' => 40, 'fecha' => now()->toDateString(), 'activa' => true]);
    CierreCaja::factory()->create(['tasa_cambio_id' => $this->tasa->id]);
    $this->plan = Plan::factory()->duracion(30)->create();
    $this->plan->producto->update(['precio_venta' => 25]);
    $this->cliente = Cliente::factory()->create();
});

function cobrarMembresia($test, MetodoPago $metodo, array $extra = [])
{
    return $test->actingAs(User::factory()->create())->post(route('socios.membresia', $test->cliente), [
        'plan_id' => $test->plan->id,
        'metodo_pago_id' => $metodo->id,
        ...$extra,
    ]);
}

test('pagar en divisas con un billete mayor deja el vuelto en la venta', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    cobrarMembresia($this, $metodo, ['monto_recibido' => 30])->assertSessionHasNoErrors();

    $venta = Venta::firstOrFail();
    expect((float) $venta->total)->toBe(25.0)
        ->and((float) $venta->monto_recibido)->toBe(30.0)
        ->and((float) $venta->vuelto)->toBe(5.0)
        ->and((float) $venta->pagos()->sum('monto'))->toBe(25.0);
});

test('pagar en bolívares en efectivo convierte el vuelto con la tasa', function () {
    $metodo = MetodoPago::factory()->efectivoBs()->create();

    // 25 USD x 40 = 1.000 Bs; entrega 1.200 Bs: vuelto 200 Bs = 5 USD
    cobrarMembresia($this, $metodo, ['monto_recibido' => 1200])->assertSessionHasNoErrors();

    $venta = Venta::firstOrFail();
    expect((float) $venta->vuelto)->toBe(5.0)
        ->and((float) $venta->pagos()->sum('monto'))->toBe(25.0);
});

test('sin monto recibido se cobra justo y no hay vuelto', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    cobrarMembresia($this, $metodo)->assertSessionHasNoErrors();

    expect((float) Venta::firstOrFail()->vuelto)->toBe(0.0);
});

test('un monto recibido menor al total se rechaza', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    cobrarMembresia($this, $metodo, ['monto_recibido' => 20])->assertSessionHasErrors('monto_recibido');

    expect(Venta::count())->toBe(0);
});

test('en un método que no es efectivo el monto recibido no genera vuelto', function () {
    $metodo = MetodoPago::factory()->pagoMovil()->create();

    cobrarMembresia($this, $metodo, ['monto_recibido' => 2000, 'referencia' => '123456'])->assertSessionHasNoErrors();

    expect((float) Venta::firstOrFail()->vuelto)->toBe(0.0);
});
