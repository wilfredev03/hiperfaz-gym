<?php

use App\Models\Asistencia;
use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Suscripcion;
use App\Models\TasaCambio;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** Activa la membresía como lo hace recepción: desde el módulo de Clientes. */
function ventaDePlan($test, Plan $plan, Cliente $cliente, array $extra = [])
{
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    return $test->actingAs(User::factory()->create())->post(route('socios.membresia', $cliente), [
        'plan_id' => $plan->id,
        'metodo_pago_id' => $metodo->id,
        ...$extra,
    ]);
}

beforeEach(function () {
    CierreCaja::factory()->create();
    $this->plan = Plan::factory()->duracion(30)->create();
    $this->plan->producto->update(['precio_venta' => 25]);
});

test('vender un plan crea la suscripción en la misma venta', function () {
    $cliente = Cliente::factory()->create();

    ventaDePlan($this, $this->plan, $cliente)->assertSessionHasNoErrors();

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

    ventaDePlan($this, $plan, Cliente::factory()->create())->assertSessionHasNoErrors();

    $suscripcion = Suscripcion::firstOrFail();
    expect($suscripcion->fecha_vencimiento->toDateString())->toBe($suscripcion->fecha_inicio->toDateString());
});

test('comprar varias unidades suma los períodos', function () {
    $this->actingAs(User::factory()->create());
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    app(VentaService::class)->registrar([
        'es_credito' => false,
        'cliente_id' => Cliente::factory()->create()->id,
        'items' => [['producto_id' => $this->plan->producto_id, 'cantidad' => 3]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => 75]],
    ]);

    expect(Suscripcion::firstOrFail()->fecha_vencimiento->toDateString())
        ->toBe(now()->addDays(89)->toDateString());
});

test('renovar crea una fila nueva que empieza al vencer la vigente', function () {
    $cliente = Cliente::factory()->create();

    ventaDePlan($this, $this->plan, $cliente)->assertSessionHasNoErrors();
    ventaDePlan($this, $this->plan, $cliente)->assertSessionHasNoErrors();

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

    ventaDePlan($this, $this->plan, $cliente)->assertSessionHasNoErrors();

    expect(Suscripcion::latest('id')->first()->fecha_inicio->toDateString())->toBe(now()->toDateString());
});

test('vender un plan sin socio se rechaza y no crea nada', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();
    $this->actingAs(User::factory()->create());

    expect(fn () => app(VentaService::class)->registrar([
        'es_credito' => false,
        'items' => [['producto_id' => $this->plan->producto_id, 'cantidad' => 1]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => 25]],
    ]))->toThrow(ValidationException::class);

    expect(Suscripcion::count())->toBe(0);
    $this->assertDatabaseCount('ventas', 0);
});

test('la cantidad de un plan debe ser entera', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();
    $this->actingAs(User::factory()->create());

    expect(fn () => app(VentaService::class)->registrar([
        'es_credito' => false,
        'cliente_id' => Cliente::factory()->create()->id,
        'items' => [['producto_id' => $this->plan->producto_id, 'cantidad' => 1.5]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => 40]],
    ]))->toThrow(ValidationException::class);

    expect(Suscripcion::count())->toBe(0);
});

test('el punto de venta rechaza vender una membresía', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    $this->actingAs(User::factory()->create())->postJson(route('ventas.store'), [
        'es_credito' => false,
        'cliente_id' => Cliente::factory()->create()->id,
        'items' => [['producto_id' => $this->plan->producto_id, 'cantidad' => 1]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => 25]],
    ])->assertJsonValidationErrors('items');

    expect(Suscripcion::count())->toBe(0);
    $this->assertDatabaseCount('ventas', 0);
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

    $this->actingAs(User::factory()->create())->post(route('socios.membresia', $cliente), [
        'plan_id' => $this->plan->id,
        'es_credito' => true,
    ])->assertSessionHasNoErrors();

    expect(Suscripcion::count())->toBe(1)
        ->and(Venta::firstOrFail()->estado)->toBe('pendiente')
        ->and((float) $cliente->fresh()->saldo_pendiente)->toBe(25.0);
});

test('activar la membresía exige un método de pago salvo a crédito', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('socios.membresia', Cliente::factory()->create()), ['plan_id' => $this->plan->id])
        ->assertSessionHasErrors('metodo_pago_id');

    expect(Suscripcion::count())->toBe(0);
});

test('no se activa la membresía con un plan desactivado ni a un cliente inactivo', function () {
    $desactivado = Plan::factory()->create();
    $desactivado->producto->update(['activo' => false]);

    ventaDePlan($this, $desactivado, Cliente::factory()->create())->assertSessionHasErrors('plan_id');
    ventaDePlan($this, $this->plan, Cliente::factory()->inactivo()->create())->assertSessionHasErrors('plan_id');

    expect(Suscripcion::count())->toBe(0);
});

test('el cobro de la membresía queda como un pago de la venta', function () {
    ventaDePlan($this, $this->plan, Cliente::factory()->create());

    $venta = Venta::firstOrFail();
    expect($venta->estado)->toBe('pagada')
        ->and((float) $venta->total)->toBe(25.0)
        ->and($venta->pagos)->toHaveCount(1)
        ->and((float) $venta->pagos->first()->monto)->toBe(25.0)
        ->and($venta->cierre_caja_id)->not->toBeNull();
});

test('sin tasa de hoy no se cobra la membresía ni se crea nada', function () {
    TasaCambio::query()->delete();

    ventaDePlan($this, $this->plan, Cliente::factory()->create())->assertSessionHasErrors('tasa_cambio');

    expect(Suscripcion::count())->toBe(0);
    $this->assertDatabaseCount('ventas', 0);
});

test('crear el cliente con plan le activa la membresía en el mismo paso', function () {
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    $this->actingAs(User::factory()->create())->post(route('socios.store'), [
        'nombre' => 'Ana Torres',
        'activo' => true,
        'membresia' => ['plan_id' => $this->plan->id, 'metodo_pago_id' => $metodo->id, 'es_credito' => false, 'referencia' => ''],
    ])->assertRedirect(route('socios.show', Cliente::firstOrFail()));

    expect(Suscripcion::firstOrFail()->cliente_id)->toBe(Cliente::firstOrFail()->id)
        ->and(Venta::firstOrFail()->estado)->toBe('pagada');
});

test('crear el cliente sin plan no genera venta', function () {
    $this->actingAs(User::factory()->create())->post(route('socios.store'), [
        'nombre' => 'Luis Gómez',
        'activo' => true,
        'membresia' => ['plan_id' => null, 'metodo_pago_id' => null, 'es_credito' => false, 'referencia' => ''],
    ])->assertSessionHasNoErrors();

    expect(Cliente::count())->toBe(1)
        ->and(Suscripcion::count())->toBe(0);
    $this->assertDatabaseCount('ventas', 0);
});

test('si el cobro falla al crear el cliente con plan, el cliente tampoco se crea', function () {
    TasaCambio::query()->delete();
    $metodo = MetodoPago::factory()->efectivoUsd()->create();

    $this->actingAs(User::factory()->create())->post(route('socios.store'), [
        'nombre' => 'Marta Ruiz',
        'activo' => true,
        'membresia' => ['plan_id' => $this->plan->id, 'metodo_pago_id' => $metodo->id, 'es_credito' => false, 'referencia' => ''],
    ])->assertSessionHasErrors('tasa_cambio');

    expect(Cliente::count())->toBe(0);
});

test('el alta y la ficha traen los planes, los métodos de pago y la tasa', function () {
    $cliente = Cliente::factory()->create();
    $admin = User::factory()->create();

    foreach ([route('socios.create'), route('socios.show', $cliente)] as $url) {
        $this->actingAs($admin)->get($url)->assertInertia(fn ($page) => $page
            ->has('planes', 1)
            ->where('planes.0.id', $this->plan->id)
            ->has('metodosPago')
            ->where('tasaCambio.id', fn ($id) => $id !== null));
    }
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
