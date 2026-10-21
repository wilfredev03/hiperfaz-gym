<?php

use App\Models\Asistencia;
use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Database\QueryException;

function ventaDePlan($test, Plan $plan, ?Cliente $cliente, int|float $cantidad = 1, ?float $monto = null)
{
    $metodo = MetodoPago::factory()->efectivoUsd()->create();
    $producto = $plan->producto;

    return $test->actingAs(User::factory()->create())->postJson(route('ventas.store'), [
        'es_credito' => false,
        'cliente_id' => $cliente?->id,
        'items' => [['producto_id' => $producto->id, 'cantidad' => $cantidad]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => $monto ?? (float) $producto->precio_venta * $cantidad]],
    ]);
}

beforeEach(function () {
    CierreCaja::factory()->create();
    $this->plan = Plan::factory()->duracion(30)->create();
    $this->plan->producto->update(['precio_venta' => 25]);
});

test('vender un plan crea la suscripción en la misma venta', function () {
    $cliente = Cliente::factory()->create();

    ventaDePlan($this, $this->plan, $cliente)->assertCreated();

    $suscripcion = Suscripcion::firstOrFail();
    expect($suscripcion->cliente_id)->toBe($cliente->id)
        ->and($suscripcion->plan_id)->toBe($this->plan->id)
        ->and($suscripcion->estado)->toBe('activa')
        ->and($suscripcion->fecha_inicio->toDateString())->toBe(now()->toDateString())
        ->and($suscripcion->fecha_vencimiento->toDateString())->toBe(now()->addDays(29)->toDateString())
        ->and($suscripcion->ventaDetalle->producto_id)->toBe($this->plan->producto_id);
    expect($cliente->suscripcionVigente()?->id)->toBe($suscripcion->id);
});

test('el pase de día vence el mismo día', function () {
    $plan = Plan::factory()->duracion(1)->create();
    $plan->producto->update(['precio_venta' => 3]);

    ventaDePlan($this, $plan, Cliente::factory()->create())->assertCreated();

    $suscripcion = Suscripcion::firstOrFail();
    expect($suscripcion->fecha_vencimiento->toDateString())->toBe($suscripcion->fecha_inicio->toDateString());
});

test('comprar varias unidades suma los períodos', function () {
    ventaDePlan($this, $this->plan, Cliente::factory()->create(), 3)->assertCreated();

    expect(Suscripcion::firstOrFail()->fecha_vencimiento->toDateString())
        ->toBe(now()->addDays(89)->toDateString());
});

test('renovar crea una fila nueva que empieza al vencer la vigente', function () {
    $cliente = Cliente::factory()->create();

    ventaDePlan($this, $this->plan, $cliente)->assertCreated();
    ventaDePlan($this, $this->plan, $cliente)->assertCreated();

    $suscripciones = Suscripcion::orderBy('id')->get();
    expect($suscripciones)->toHaveCount(2)
        ->and($suscripciones[1]->fecha_inicio->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($suscripciones[1]->fecha_vencimiento->toDateString())->toBe(now()->addDays(59)->toDateString())
        // la primera no se tocó
        ->and($suscripciones[0]->fecha_vencimiento->toDateString())->toBe(now()->addDays(29)->toDateString());
});

test('una suscripción vencida no bloquea que la nueva empiece hoy', function () {
    $cliente = Cliente::factory()->create();
    Suscripcion::factory()->vencida()->create(['cliente_id' => $cliente->id]);

    ventaDePlan($this, $this->plan, $cliente)->assertCreated();

    expect(Suscripcion::latest('id')->first()->fecha_inicio->toDateString())->toBe(now()->toDateString());
});

test('vender un plan sin socio se rechaza y no crea nada', function () {
    ventaDePlan($this, $this->plan, null)->assertJsonValidationErrors('cliente_id');

    expect(Suscripcion::count())->toBe(0);
    $this->assertDatabaseCount('ventas', 0);
});

test('la cantidad de un plan debe ser entera', function () {
    ventaDePlan($this, $this->plan, Cliente::factory()->create(), 1.5)->assertJsonValidationErrors('items');

    expect(Suscripcion::count())->toBe(0);
});

test('un producto sin plan no genera suscripción', function () {
    $producto = Producto::factory()->create(['precio_venta' => 2]);
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    $this->actingAs(User::factory()->create())->postJson(route('ventas.store'), [
        'es_credito' => false,
        'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => 2]],
    ])->assertCreated();

    expect(Suscripcion::count())->toBe(0);
});

test('una membresía a crédito crea la suscripción y deja el saldo', function () {
    $cliente = Cliente::factory()->create();

    $this->actingAs(User::factory()->create())->postJson(route('ventas.store'), [
        'es_credito' => true,
        'cliente_id' => $cliente->id,
        'items' => [['producto_id' => $this->plan->producto_id, 'cantidad' => 1]],
    ])->assertCreated()->assertJsonPath('estado', 'pendiente');

    expect(Suscripcion::count())->toBe(1)
        ->and((float) $cliente->fresh()->saldo_pendiente)->toBe(25.0);
});

test('una suscripción no se edita pero sí cambia de estado', function () {
    $suscripcion = Suscripcion::factory()->create();

    $suscripcion->update(['estado' => 'congelada']);
    expect($suscripcion->fresh()->estado)->toBe('congelada');

    expect(fn () => $suscripcion->update(['fecha_vencimiento' => now()->addYear()]))
        ->toThrow(LogicException::class);
});

test('vigentes excluye vencidas, congeladas y canceladas', function () {
    $cliente = Cliente::factory()->create();
    Suscripcion::factory()->vencida()->create(['cliente_id' => $cliente->id]);
    Suscripcion::factory()->congelada()->create(['cliente_id' => $cliente->id]);
    Suscripcion::factory()->cancelada()->create(['cliente_id' => $cliente->id]);

    expect($cliente->suscripcionVigente())->toBeNull();

    $activa = Suscripcion::factory()->create(['cliente_id' => $cliente->id]);
    expect($cliente->suscripcionVigente()->id)->toBe($activa->id);
});

test('la base rechaza un estado de suscripción inválido', function () {
    expect(fn () => Suscripcion::factory()->create(['estado' => 'regalada']))
        ->toThrow(QueryException::class);
});

test('la base rechaza un plan con duración cero y una vigencia invertida', function () {
    expect(fn () => Plan::factory()->duracion(0)->create())->toThrow(QueryException::class);
    expect(fn () => Suscripcion::factory()->create([
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]))->toThrow(QueryException::class);
});

test('una asistencia puede no tener socio y queda registrada', function () {
    $asistencia = Asistencia::factory()->denegada()->delTerminal()->create(['cliente_id' => null]);

    expect($asistencia->fresh())
        ->cliente_id->toBeNull()
        ->resultado->toBe('denegado')
        ->origen->toBe('terminal');
});

test('las asistencias son de solo inserción', function () {
    $asistencia = Asistencia::factory()->create();

    expect(fn () => $asistencia->update(['resultado' => 'denegado']))->toThrow(LogicException::class);
    expect(fn () => $asistencia->delete())->toThrow(LogicException::class);
    expect(Asistencia::count())->toBe(1);
});

test('la base rechaza resultado u origen inválidos en asistencias', function () {
    expect(fn () => Asistencia::factory()->create(['resultado' => 'quizas']))->toThrow(QueryException::class);
    expect(fn () => Asistencia::factory()->create(['origen' => 'telepatia']))->toThrow(QueryException::class);
});

test('id_acceso es único entre socios', function () {
    Cliente::factory()->create(['id_acceso' => 'HK-001']);

    expect(fn () => Cliente::factory()->create(['id_acceso' => 'HK-001']))->toThrow(QueryException::class);
});
