<?php

use App\Models\Asistencia;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\SuscripcionService;

// --- Planes (solo admin) ---------------------------------------------------------

test('el admin crea un plan: producto sin inventario más su duración', function () {
    $this->actingAs(User::factory()->create())->post(route('planes.store'), [
        'nombre' => 'Plan Semanal',
        'precio_venta' => 10,
        'duracion_dias' => 7,
        'activo' => true,
    ])->assertRedirect(route('planes.index'));

    $plan = Plan::firstOrFail();
    expect($plan->duracion_dias)->toBe(7)
        ->and($plan->producto->nombre)->toBe('Plan Semanal')
        ->and($plan->producto->controla_inventario)->toBeFalse()
        ->and($plan->producto->categoria->nombre)->toBe('Membresías');
});

test('un plan nuevo reutiliza la categoría de los planes existentes', function () {
    $existente = Plan::factory()->create();
    $existente->producto->update(['categoria_id' => Categoria::factory()->create(['nombre' => 'Pases'])->id]);

    $this->actingAs(User::factory()->create())->post(route('planes.store'), [
        'nombre' => 'Plan Mensual', 'precio_venta' => 25, 'duracion_dias' => 30, 'activo' => true,
    ])->assertRedirect();

    expect(Plan::latest('id')->first()->producto->categoria->nombre)->toBe('Pases');
});

test('acepta una duración personalizada y rechaza una inválida', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->post(route('planes.store'), [
        'nombre' => 'Plan 45 días', 'precio_venta' => 40, 'duracion_dias' => 45, 'activo' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('planes.store'), [
        'nombre' => 'Plan roto', 'precio_venta' => 40, 'duracion_dias' => 0, 'activo' => true,
    ])->assertSessionHasErrors('duracion_dias');

    expect(Plan::count())->toBe(1);
});

test('editar un plan no toca las fechas de las suscripciones ya emitidas', function () {
    $plan = Plan::factory()->duracion(30)->create();
    $suscripcion = Suscripcion::factory()->create(['plan_id' => $plan->id]);
    $vencimiento = $suscripcion->fecha_vencimiento->toDateString();

    $this->actingAs(User::factory()->create())->put(route('planes.update', $plan), [
        'nombre' => 'Mensual Plus', 'precio_venta' => 30, 'duracion_dias' => 60, 'activo' => true,
    ])->assertRedirect(route('planes.index'));

    expect($plan->fresh()->duracion_dias)->toBe(60)
        ->and($plan->producto->fresh()->nombre)->toBe('Mensual Plus')
        ->and($suscripcion->fresh()->fecha_vencimiento->toDateString())->toBe($vencimiento);
});

test('el vendedor no accede a los planes', function () {
    $this->actingAs(User::factory()->vendedor()->create())
        ->get(route('planes.index'))->assertForbidden();
});

test('lista los planes con cuántos se han vendido', function () {
    $plan = Plan::factory()->create();
    Suscripcion::factory()->count(2)->create(['plan_id' => $plan->id]);

    $this->actingAs(User::factory()->create())->get(route('planes.index'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/planes/Index', false)
            ->has('planes.data', 1)
            ->where('planes.data.0.suscripciones_count', 2));
});

// --- Socios -----------------------------------------------------------------------

function socioConVencimiento(string $nombre, ?int $diasHastaVencer): Cliente
{
    $socio = Cliente::factory()->create(['nombre' => $nombre]);

    if ($diasHastaVencer !== null) {
        Suscripcion::factory()->create([
            'cliente_id' => $socio->id,
            'fecha_inicio' => now()->addDays($diasHastaVencer)->subDays(29)->toDateString(),
            'fecha_vencimiento' => now()->addDays($diasHastaVencer)->toDateString(),
        ]);
    }

    return $socio;
}

test('la lista de socios clasifica la vigencia de cada uno', function () {
    socioConVencimiento('Vigente', 20);
    socioConVencimiento('Por vencer', 3);
    socioConVencimiento('Vence hoy', 0);
    socioConVencimiento('Vencida', -5);
    socioConVencimiento('Nunca', null);

    $this->actingAs(User::factory()->vendedor()->create())->get(route('socios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('socios/Index', false)
            ->has('socios.data', 5)
            ->where('resumen', ['vigentes' => 3, 'por_vencer' => 2, 'vencidas' => 1])
            ->where('socios.data', fn ($socios) => collect($socios)->pluck('vigencia', 'nombre')->all() === [
                'Nunca' => 'sin_membresia',
                'Por vencer' => 'por_vencer',
                'Vence hoy' => 'por_vencer',
                'Vencida' => 'vencida',
                'Vigente' => 'vigente',
            ]));
});

test('los filtros de vencimiento devuelven solo los socios que corresponden', function () {
    socioConVencimiento('Vigente', 20);
    socioConVencimiento('Por vencer', 3);
    socioConVencimiento('Vencida', -5);
    socioConVencimiento('Nunca', null);
    $admin = User::factory()->create();

    $nombres = fn (string $filtro) => collect(
        $this->actingAs($admin)->get(route('socios.index', ['filtro' => $filtro]))
            ->viewData('page')['props']['socios']['data']
    )->pluck('nombre')->sort()->values()->all();

    expect($nombres('vigentes'))->toBe(['Por vencer', 'Vigente'])
        ->and($nombres('por_vencer'))->toBe(['Por vencer'])
        ->and($nombres('vencidas'))->toBe(['Vencida'])
        ->and($nombres('sin_membresia'))->toBe(['Nunca']);
});

test('un socio con una suscripción cancelada o congelada no cuenta como vigente', function () {
    $socio = Cliente::factory()->create();
    Suscripcion::factory()->cancelada()->create(['cliente_id' => $socio->id]);
    Suscripcion::factory()->congelada()->create(['cliente_id' => $socio->id]);

    $this->actingAs(User::factory()->create())->get(route('socios.index', ['filtro' => 'sin_membresia']))
        ->assertInertia(fn ($page) => $page->has('socios.data', 1));
});

test('busca socios por nombre o cédula', function () {
    Cliente::factory()->create(['nombre' => 'María Pérez', 'documento' => 'V-111']);
    Cliente::factory()->create(['nombre' => 'Luis Gómez', 'documento' => 'V-222']);
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('socios.index', ['busqueda' => 'maría']))
        ->assertInertia(fn ($page) => $page->has('socios.data', 1));
    $this->actingAs($admin)->get(route('socios.index', ['busqueda' => 'V-222']))
        ->assertInertia(fn ($page) => $page->has('socios.data', 1)->where('socios.data.0.nombre', 'Luis Gómez'));
});

test('crea un socio con sus datos y va a su ficha', function () {
    $this->actingAs(User::factory()->vendedor()->create())->post(route('socios.store'), [
        'nombre' => 'Ana Torres',
        'documento' => 'V-123',
        'id_acceso' => 'HK-9',
        'activo' => true,
    ])->assertRedirect(route('socios.show', Cliente::firstOrFail()));

    expect(Cliente::first()->id_acceso)->toBe('HK-9');
});

test('rechaza una cédula o un id de acceso repetidos', function () {
    Cliente::factory()->create(['documento' => 'V-1', 'id_acceso' => 'HK-1']);

    $this->actingAs(User::factory()->create())->post(route('socios.store'), [
        'nombre' => 'Otro', 'documento' => 'V-1', 'id_acceso' => 'HK-1', 'activo' => true,
    ])->assertSessionHasErrors(['documento', 'id_acceso']);
});

test('actualizar un socio conserva su propia cédula sin chocar', function () {
    $socio = Cliente::factory()->create(['documento' => 'V-5', 'id_acceso' => 'HK-5']);

    $this->actingAs(User::factory()->create())->put(route('socios.update', $socio), [
        'nombre' => 'Nombre nuevo', 'documento' => 'V-5', 'id_acceso' => 'HK-5', 'activo' => true,
    ])->assertSessionHasNoErrors();

    expect($socio->fresh()->nombre)->toBe('Nombre nuevo');
});

test('la ficha muestra la membresía, el historial y las entradas', function () {
    $socio = socioConVencimiento('Ana', 10);
    Asistencia::factory()->create(['cliente_id' => $socio->id]);

    $this->actingAs(User::factory()->create())->get(route('socios.show', $socio))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('socios/Show', false)
            ->where('membresia.vigencia', 'vigente')
            ->where('membresia.dias_restantes', 10)
            ->has('suscripciones', 1)
            ->has('asistencias', 1));
});

// --- Asistencia (recepción) ----------------------------------------------------------

test('registrar la entrada de un socio vigente queda como permitida', function () {
    $socio = socioConVencimiento('Ana', 10);
    $usuario = User::factory()->vendedor()->create();

    $this->actingAs($usuario)->post(route('asistencias.store'), ['cliente_id' => $socio->id])->assertRedirect();

    $this->assertDatabaseHas('asistencias', [
        'cliente_id' => $socio->id, 'resultado' => 'permitido', 'origen' => 'manual', 'usuario_id' => $usuario->id,
    ]);
});

test('registrar la entrada de un socio vencido se deniega pero queda registrada', function () {
    $socio = socioConVencimiento('Ana', -3);

    $this->actingAs(User::factory()->create())->post(route('asistencias.store'), ['cliente_id' => $socio->id]);

    $this->assertDatabaseHas('asistencias', ['cliente_id' => $socio->id, 'resultado' => 'denegado']);
});

test('un socio inactivo con membresía vigente también se deniega', function () {
    $socio = socioConVencimiento('Ana', 10);
    $socio->update(['activo' => false]);

    $this->actingAs(User::factory()->create())->post(route('asistencias.store'), ['cliente_id' => $socio->id]);

    $this->assertDatabaseHas('asistencias', ['cliente_id' => $socio->id, 'resultado' => 'denegado']);
});

test('no se registra la entrada de un socio que no existe', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('asistencias.store'), ['cliente_id' => 999])->assertSessionHasErrors('cliente_id');

    expect(Asistencia::count())->toBe(0);
});

test('recepción busca al socio y ve las entradas de hoy', function () {
    $socio = socioConVencimiento('Ana Torres', 10);
    Asistencia::factory()->create(['cliente_id' => $socio->id]);
    Asistencia::factory()->denegada()->create(['cliente_id' => $socio->id, 'fecha_hora' => now()->subDay()]);

    $this->actingAs(User::factory()->vendedor()->create())->get(route('asistencias.index', ['busqueda' => 'torres']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('asistencias/Index', false)
            ->has('candidatos', 1)
            ->where('candidatos.0.vigencia', 'vigente')
            ->where('resumen', ['permitidas' => 1, 'denegadas' => 0])
            ->has('asistencias', 1));
});

test('sin búsqueda no se consultan candidatos', function () {
    socioConVencimiento('Ana', 10);

    $this->actingAs(User::factory()->create())->get(route('asistencias.index'))
        ->assertInertia(fn ($page) => $page->where('candidatos', []));
});

// --- Punto de venta ----------------------------------------------------------------

test('el punto de venta no ofrece los planes de membresía', function () {
    $plan = Plan::factory()->create();
    $producto = Producto::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('ventas.create'))
        ->assertInertia(fn ($page) => $page
            ->missing('clienteInicial')
            ->where('productos', fn ($productos) => collect($productos)->pluck('id')->all() === [$producto->id]));
});

// --- Vigencia --------------------------------------------------------------------

test('vigenciaDe cubre los límites de por vencer', function () {
    $servicio = new SuscripcionService;

    expect($servicio->vigenciaDe(null))->toBe(['vigencia' => 'sin_membresia', 'dias_restantes' => null])
        ->and($servicio->vigenciaDe(now()->subDay()->toDateString())['vigencia'])->toBe('vencida')
        ->and($servicio->vigenciaDe(now()->toDateString()))->toBe(['vigencia' => 'por_vencer', 'dias_restantes' => 0])
        ->and($servicio->vigenciaDe(now()->addDays(7)->toDateString())['vigencia'])->toBe('por_vencer')
        ->and($servicio->vigenciaDe(now()->addDays(8)->toDateString())['vigencia'])->toBe('vigente');
});

// --- Las membresías no son parte del catálogo ----------------------------------------

test('el catálogo de productos y las compras no incluyen los planes de membresía', function () {
    $plan = Plan::factory()->create();
    $producto = Producto::factory()->create(['nombre' => 'Agua 600ml']);
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('productos.index'))
        ->assertInertia(fn ($page) => $page
            ->has('productos.data', 1)
            ->where('productos.data.0.id', $producto->id));

    $this->actingAs($admin)->get(route('ordenes_compra.create'))
        ->assertInertia(fn ($page) => $page
            ->where('productos', fn ($productos) => collect($productos)->pluck('id')->all() === [$producto->id]));
});

test('el reporte de membresías vendidas trae todas las filas del período', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('membresias_vendidas.reporte', ['periodo' => 'hoy']))
        ->assertOk()
        ->assertJsonStructure(['desde', 'hasta', 'resumen' => ['cantidad', 'altas', 'renovaciones', 'total_usd', 'total_bs'], 'por_plan', 'filas']);

    $this->getJson(route('membresias_vendidas.reporte', ['periodo' => 'personalizado']))
        ->assertUnprocessable();
});
