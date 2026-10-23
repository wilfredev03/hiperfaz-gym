<?php

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\SuscripcionCorreccion;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    CierreCaja::factory()->create();
    $this->metodo = MetodoPago::factory()->efectivoUsd()->create();
    $this->usuario = User::factory()->vendedor()->create();
});

function activarPersonalizada($test, Cliente $cliente, array $datos)
{
    return $test->actingAs($test->usuario)->post(route('socios.membresia', $cliente), [
        'personalizada' => true,
        'precio' => 12,
        'metodo_pago_id' => $test->metodo->id,
        ...$datos,
    ]);
}

// --- Membresía personalizada ------------------------------------------------------

test('una membresía por días cobra el precio elegido y dura esos días', function () {
    $cliente = Cliente::factory()->create();

    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 9])->assertSessionHasNoErrors();

    $suscripcion = Suscripcion::firstOrFail();
    $venta = Venta::firstOrFail();

    expect($suscripcion->fecha_inicio->toDateString())->toBe(now()->toDateString())
        ->and($suscripcion->fecha_vencimiento->toDateString())->toBe(now()->addDays(8)->toDateString())
        ->and($suscripcion->estado)->toBe('activa')
        ->and($suscripcion->plan->personalizado)->toBeTrue()
        ->and((float) $venta->total)->toBe(12.0)
        ->and($venta->estado)->toBe('pagada')
        ->and((float) $venta->pagos->first()->monto)->toBe(12.0);
});

test('por días, la nueva empieza al día siguiente de que venza la vigente', function () {
    $cliente = Cliente::factory()->create();

    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 5]);
    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 3]);

    $segunda = Suscripcion::orderByDesc('id')->first();
    expect($segunda->fecha_inicio->toDateString())->toBe(now()->addDays(5)->toDateString())
        ->and($segunda->fecha_vencimiento->toDateString())->toBe(now()->addDays(7)->toDateString());
});

test('por fechas queda exactamente el rango elegido', function () {
    $cliente = Cliente::factory()->create();
    $desde = now()->addDays(3)->toDateString();
    $hasta = now()->addDays(12)->toDateString();

    activarPersonalizada($this, $cliente, ['modo' => 'fechas', 'fecha_inicio' => $desde, 'fecha_fin' => $hasta])
        ->assertSessionHasNoErrors();

    $suscripcion = Suscripcion::firstOrFail();
    expect($suscripcion->fecha_inicio->toDateString())->toBe($desde)
        ->and($suscripcion->fecha_vencimiento->toDateString())->toBe($hasta);
});

test('un rango que ya pasó queda como vencida', function () {
    $cliente = Cliente::factory()->create();

    activarPersonalizada($this, $cliente, [
        'modo' => 'fechas',
        'fecha_inicio' => now()->subDays(20)->toDateString(),
        'fecha_fin' => now()->subDays(10)->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(Suscripcion::firstOrFail()->estado)->toBe('vencida');
});

test('una membresía personalizada se puede dejar a crédito', function () {
    $cliente = Cliente::factory()->create();

    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 9, 'es_credito' => true, 'metodo_pago_id' => null])
        ->assertSessionHasNoErrors();

    expect(Venta::firstOrFail()->estado)->toBe('pendiente')
        ->and((float) $cliente->fresh()->saldo_pendiente)->toBe(12.0);
});

test('la personalizada valida precio, días y fechas', function () {
    $cliente = Cliente::factory()->create();

    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => null])->assertSessionHasErrors('dias');
    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 0])->assertSessionHasErrors('dias');
    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 5, 'precio' => 0])->assertSessionHasErrors('precio');
    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 5, 'precio' => null])->assertSessionHasErrors('precio');
    activarPersonalizada($this, $cliente, ['modo' => 'fechas'])->assertSessionHasErrors(['fecha_inicio', 'fecha_fin']);
    activarPersonalizada($this, $cliente, [
        'modo' => 'fechas',
        'fecha_inicio' => now()->addDays(5)->toDateString(),
        'fecha_fin' => now()->addDays(2)->toDateString(),
    ])->assertSessionHasErrors('fecha_fin');

    expect(Suscripcion::count())->toBe(0);
    $this->assertDatabaseCount('ventas', 0);
});

test('el plan personalizado es uno solo y no aparece como plan del catálogo', function () {
    $normal = Plan::factory()->create();
    $cliente = Cliente::factory()->create();

    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 2]);
    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 2]);

    expect(Plan::where('personalizado', true)->count())->toBe(1);

    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('socios.create'))
        ->assertInertia(fn ($page) => $page->has('planes', 1)->where('planes.0.id', $normal->id));

    $this->actingAs($admin)->get(route('planes.index'))
        ->assertInertia(fn ($page) => $page->has('planes.data', 1)->where('planes.data.0.id', $normal->id));

    $this->actingAs($admin)->get(route('productos.index'))
        ->assertInertia(fn ($page) => $page->has('productos.data', 0));
});

test('un plan normal no se puede usar como personalizado ni al revés', function () {
    $cliente = Cliente::factory()->create();
    $personalizado = Plan::personalizado();

    $this->actingAs($this->usuario)->post(route('socios.membresia', $cliente), [
        'plan_id' => $personalizado->id,
        'metodo_pago_id' => $this->metodo->id,
    ])->assertSessionHasErrors('plan_id');

    expect(Suscripcion::count())->toBe(0);
});

test('crear el cliente con una membresía personalizada la activa en el mismo paso', function () {
    $this->actingAs($this->usuario)->post(route('socios.store'), [
        'nombre' => 'Rosa Pérez',
        'activo' => true,
        'membresia' => [
            'plan_id' => null,
            'personalizada' => true,
            'precio' => 15,
            'modo' => 'dias',
            'dias' => 9,
            'es_credito' => false,
            'metodo_pago_id' => $this->metodo->id,
        ],
    ])->assertRedirect(route('socios.show', Cliente::firstOrFail()));

    expect(Suscripcion::firstOrFail()->fecha_vencimiento->toDateString())->toBe(now()->addDays(8)->toDateString())
        ->and((float) Venta::firstOrFail()->total)->toBe(15.0);
});

// --- Corregir fechas --------------------------------------------------------------

function suscripcionCorregible(): Suscripcion
{
    return Suscripcion::factory()->create([
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(29)->toDateString(),
    ]);
}

test('recepción y admin corrigen las fechas y queda registrado', function () {
    foreach ([User::factory()->vendedor()->create(), User::factory()->create()] as $usuario) {
        $suscripcion = suscripcionCorregible();
        $nuevoFin = now()->addDays(34)->toDateString();

        $this->actingAs($usuario)->put(route('suscripciones.corregir', $suscripcion), [
            'fecha_inicio' => now()->toDateString(),
            'fecha_vencimiento' => $nuevoFin,
            'motivo' => 'Se cargó de menos',
        ])->assertSessionHasNoErrors();

        $correccion = SuscripcionCorreccion::where('suscripcion_id', $suscripcion->id)->firstOrFail();
        expect($suscripcion->fresh()->fecha_vencimiento->toDateString())->toBe($nuevoFin)
            ->and($correccion->usuario_id)->toBe($usuario->id)
            ->and($correccion->motivo)->toBe('Se cargó de menos')
            ->and($correccion->fecha_vencimiento_anterior->toDateString())->toBe(now()->addDays(29)->toDateString())
            ->and($correccion->fecha_vencimiento_nueva->toDateString())->toBe($nuevoFin);
    }
});

test('corregir las fechas no toca la venta ni los pagos', function () {
    $cliente = Cliente::factory()->create();
    activarPersonalizada($this, $cliente, ['modo' => 'dias', 'dias' => 9]);
    $venta = Venta::firstOrFail();
    $totalAntes = $venta->total;

    $this->actingAs($this->usuario)->put(route('suscripciones.corregir', Suscripcion::firstOrFail()), [
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(14)->toDateString(),
        'motivo' => 'Eran 15 días',
    ])->assertSessionHasNoErrors();

    expect($venta->fresh()->total)->toEqual($totalAntes)
        ->and($venta->fresh()->pagos)->toHaveCount(1);
});

test('el estado se ordena según las fechas corregidas', function () {
    $suscripcion = suscripcionCorregible();

    $this->actingAs($this->usuario)->put(route('suscripciones.corregir', $suscripcion), [
        'fecha_inicio' => now()->subDays(20)->toDateString(),
        'fecha_vencimiento' => now()->subDays(5)->toDateString(),
        'motivo' => 'Fecha mal puesta',
    ])->assertSessionHasNoErrors();

    expect($suscripcion->fresh()->estado)->toBe('vencida');

    $this->actingAs($this->usuario)->put(route('suscripciones.corregir', $suscripcion), [
        'fecha_inicio' => now()->subDays(20)->toDateString(),
        'fecha_vencimiento' => now()->addDays(5)->toDateString(),
        'motivo' => 'Se había corregido de más',
    ]);

    expect($suscripcion->fresh()->estado)->toBe('activa');
});

test('la corrección exige motivo, fechas válidas y algún cambio', function () {
    $suscripcion = suscripcionCorregible();
    $url = route('suscripciones.corregir', $suscripcion);

    $this->actingAs($this->usuario)->put($url, [
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('motivo');

    $this->actingAs($this->usuario)->put($url, [
        'fecha_inicio' => now()->addDays(5)->toDateString(),
        'fecha_vencimiento' => now()->toDateString(),
        'motivo' => 'x',
    ])->assertSessionHasErrors('fecha_vencimiento');

    $this->actingAs($this->usuario)->put($url, [
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(29)->toDateString(),
        'motivo' => 'sin cambios',
    ])->assertSessionHasErrors('fecha_inicio');

    expect(SuscripcionCorreccion::count())->toBe(0);
});

test('una membresía cancelada no se corrige', function () {
    $suscripcion = Suscripcion::factory()->cancelada()->create();

    $this->actingAs($this->usuario)->put(route('suscripciones.corregir', $suscripcion), [
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(3)->toDateString(),
        'motivo' => 'intento',
    ])->assertSessionHasErrors('fecha_inicio');

    expect(SuscripcionCorreccion::count())->toBe(0);
});

test('las fechas siguen sin poder editarse por fuera de la corrección, y la bitácora es de solo inserción', function () {
    $suscripcion = suscripcionCorregible();

    expect(fn () => $suscripcion->update(['fecha_vencimiento' => now()->addDays(40)->toDateString()]))
        ->toThrow(LogicException::class);

    $suscripcion->corregirFechas(now()->toDateString(), now()->addDays(31)->toDateString(), 'ajuste', $this->usuario->id);
    $correccion = SuscripcionCorreccion::firstOrFail();

    expect(fn () => $correccion->update(['motivo' => 'otro']))->toThrow(LogicException::class)
        ->and(fn () => $correccion->delete())->toThrow(LogicException::class);
});

test('la ficha del cliente trae sus correcciones y marca la membresía corregida', function () {
    $suscripcion = suscripcionCorregible();
    $suscripcion->corregirFechas(now()->toDateString(), now()->addDays(31)->toDateString(), 'ajuste', $this->usuario->id);

    $this->actingAs($this->usuario)->get(route('socios.show', $suscripcion->cliente_id))
        ->assertInertia(fn ($page) => $page
            ->where('suscripciones.0.corregida', true)
            ->has('correcciones', 1)
            ->where('correcciones.0.motivo', 'ajuste')
            ->where('correcciones.0.usuario', $this->usuario->name));
});
