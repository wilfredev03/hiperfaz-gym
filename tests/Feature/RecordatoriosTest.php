<?php

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Recordatorio;
use App\Models\RecordatorioAjuste;
use App\Models\RecordatorioPlantilla;
use App\Models\Suscripcion;
use App\Models\User;
use App\Models\WhatsAppEvento;
use App\Services\Recordatorios\GeneradorMensaje;
use App\Services\Recordatorios\MotorEnvio;
use App\Services\Recordatorios\RecordatorioService;
use App\Services\Recordatorios\SincronizadorEventosBot;
use App\Services\Recordatorios\WhatsAppCliente;
use Illuminate\Support\Facades\Http;

/** Suscripción activa de un cliente con teléfono válido que vence dentro de $dias. */
function suscripcionQueVenceEn(int $dias, array $cliente = [], int $duracion = 30): Suscripcion
{
    $plan = Plan::factory()->duracion($duracion)->create();

    return Suscripcion::factory()->create([
        'cliente_id' => Cliente::factory()->create(['telefono' => '04141234567', ...$cliente])->id,
        'plan_id' => $plan->id,
        'fecha_inicio' => today()->addDays($dias)->subDays($duracion - 1)->toDateString(),
        'fecha_vencimiento' => today()->addDays($dias)->toDateString(),
        'estado' => 'activa',
    ]);
}

function plantillasBase(): void
{
    RecordatorioPlantilla::create(['tipo' => 'por_vencer', 'nombre' => 'A', 'cuerpo' => 'Hola {nombre}, tu plan {plan} vence en {dias}.']);
    RecordatorioPlantilla::create(['tipo' => 'vence_hoy', 'nombre' => 'B', 'cuerpo' => '{nombre}, tu plan {plan} vence {dias}.']);
}

/** Ajustes con horario de todo el día y ritmo fijo, para que las pruebas no dependan de la hora ni del azar. */
function motorActivo(array $ajustes = []): RecordatorioAjuste
{
    $ajuste = RecordatorioAjuste::actual();
    $ajuste->update([
        'activo' => true,
        'hora_inicio' => '00:00',
        'hora_fin' => '23:59',
        'espera_min_seg' => 60,
        'espera_max_seg' => 60,
        'mensajes_descanso_min' => 2,
        'mensajes_descanso_max' => 2,
        'descanso_min' => 10,
        'descanso_max' => 10,
        ...$ajustes,
    ]);

    return $ajuste;
}

function pendiente(array $datos = []): Recordatorio
{
    $suscripcion = $datos['suscripcion'] ?? suscripcionQueVenceEn(3);
    unset($datos['suscripcion']);

    return Recordatorio::create([
        'cliente_id' => $suscripcion->cliente_id,
        'suscripcion_id' => $suscripcion->id,
        'tipo' => 'por_vencer',
        'telefono' => '584141234567',
        'mensaje' => 'Hola',
        'estado' => 'pendiente',
        'fecha_aviso' => today()->toDateString(),
        ...$datos,
    ]);
}

function botConectado(): void
{
    Http::fake([
        '*/estado' => Http::response(['conectado' => true, 'estado' => 'conectado', 'qr' => null, 'numero' => '584120000000']),
        '*/enviar' => Http::response(['id' => 'ABC123']),
    ]);
}

// --- Propuesta de avisos ---------------------------------------------------

test('propone avisos para quien vence en tres días y para quien vence hoy', function () {
    plantillasBase();
    $enTres = suscripcionQueVenceEn(3, ['nombre' => 'CARLOS PEREZ']);
    $hoy = suscripcionQueVenceEn(0);
    suscripcionQueVenceEn(5);  // todavía lejos
    suscripcionQueVenceEn(-1); // ya venció

    $resultado = app(RecordatorioService::class)->proponer();

    expect($resultado['creados'])->toBe(2);

    $aviso = Recordatorio::where('suscripcion_id', $enTres->id)->firstOrFail();
    expect($aviso->tipo)->toBe('por_vencer')
        ->and($aviso->estado)->toBe('pendiente')
        ->and($aviso->telefono)->toBe('584141234567')
        ->and($aviso->mensaje)->toContain('Hola Carlos')->toContain('3 días')
        ->and($aviso->variables['nombre'])->toBe('Carlos');

    expect(Recordatorio::where('suscripcion_id', $hoy->id)->value('tipo'))->toBe('vence_hoy');
});

test('proponer es idempotente', function () {
    plantillasBase();
    suscripcionQueVenceEn(3);

    app(RecordatorioService::class)->proponer();
    $segunda = app(RecordatorioService::class)->proponer();

    expect(Recordatorio::count())->toBe(1)
        ->and($segunda['creados'])->toBe(0)
        ->and($segunda['ya_propuestos'])->toBe(1);
});

test('no propone a quien no corresponde', function () {
    plantillasBase();

    suscripcionQueVenceEn(3, ['telefono' => '0212-1234567']);          // fijo, no sirve para WhatsApp
    suscripcionQueVenceEn(3, ['activo' => false]);                      // cliente inactivo
    suscripcionQueVenceEn(3, duracion: 1);                              // pase de día
    $renovo = suscripcionQueVenceEn(3);                                 // ya tiene una membresía posterior
    Suscripcion::factory()->create([
        'cliente_id' => $renovo->cliente_id,
        'plan_id' => $renovo->plan_id,
        'fecha_inicio' => today()->addDays(4)->toDateString(),
        'fecha_vencimiento' => today()->addDays(33)->toDateString(),
        'estado' => 'activa',
    ]);

    $resultado = app(RecordatorioService::class)->proponer();

    expect(Recordatorio::count())->toBe(0)
        ->and($resultado['sin_telefono'])->toBe(1);
});

test('sin plantilla activa no se genera ningún aviso', function () {
    suscripcionQueVenceEn(3);

    $resultado = app(RecordatorioService::class)->proponer();

    expect(Recordatorio::count())->toBe(0)->and($resultado['sin_plantilla'])->toBe(1);
});

// --- Mensajes --------------------------------------------------------------

test('las alternativas entre corchetes se sortean y las variables se sustituyen', function () {
    $plantilla = new RecordatorioPlantilla(['cuerpo' => '[Hola|Buenas] {nombre}, tu plan {plan} vence en {dias}.']);

    $mensajes = collect(range(1, 40))
        ->map(fn () => app(GeneradorMensaje::class)->generar($plantilla, ['nombre' => 'Ana', 'plan' => 'Mensual', 'dias' => '3 días']))
        ->unique()
        ->sort()
        ->values();

    expect($mensajes->all())->toBe([
        'Buenas Ana, tu plan Mensual vence en 3 días.',
        'Hola Ana, tu plan Mensual vence en 3 días.',
    ]);
});

// --- Motor de envío --------------------------------------------------------

test('no envía nada mientras está detenido', function () {
    botConectado();
    pendiente();

    expect(app(MotorEnvio::class)->tick())->toBe('inactivo');
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/enviar'));
});

test('no envía fuera del horario de trabajo', function () {
    botConectado();
    motorActivo(['hora_inicio' => '08:00', 'hora_fin' => '18:00']);
    pendiente();
    $this->travelTo(now()->setTime(22, 0));

    expect(app(MotorEnvio::class)->tick())->toBe('fuera_de_horario');
});

test('no envía si WhatsApp no está conectado', function () {
    Http::fake(['*/estado' => Http::response(['conectado' => false, 'estado' => 'esperando_qr', 'qr' => 'data:image/png;base64,xx'])]);
    motorActivo();
    $aviso = pendiente();

    expect(app(MotorEnvio::class)->tick())->toBe('bot_desconectado')
        ->and($aviso->fresh()->estado)->toBe('pendiente');
});

test('envía un mensaje, espera entre mensajes y descansa tras la meta', function () {
    botConectado();
    motorActivo();
    $a = pendiente();
    $b = pendiente();
    $c = pendiente();
    $motor = app(MotorEnvio::class);

    expect($motor->tick())->toBe('enviado');
    expect($a->fresh())->estado->toBe('enviado')->id_mensaje_whatsapp->toBe('ABC123');

    // aún no pasa la espera de 60 s
    expect($motor->tick())->toBe('espera');

    $this->travel(61)->seconds();
    expect($motor->tick())->toBe('enviado');
    expect($b->fresh()->estado)->toBe('enviado');

    // a los 2 mensajes (la meta fijada) toca descansar 10 min
    $this->travel(5)->minutes();
    expect($motor->tick())->toBe('descanso');

    $this->travel(6)->minutes();
    expect($motor->tick())->toBe('enviado');
    expect($c->fresh()->estado)->toBe('enviado');
});

test('un número sin WhatsApp falla de una vez, sin reintentos', function () {
    Http::fake([
        '*/estado' => Http::response(['conectado' => true, 'estado' => 'conectado']),
        '*/enviar' => Http::response(['error' => 'El número no tiene WhatsApp.'], 422),
    ]);
    motorActivo();
    $aviso = pendiente();

    expect(app(MotorEnvio::class)->tick())->toBe('fallido');
    expect($aviso->fresh())->estado->toBe('fallido')->detalle->toBe('El número no tiene WhatsApp.');
});

test('un error temporal vuelve a la cola y tras tres intentos queda fallido', function () {
    Http::fake([
        '*/estado' => Http::response(['conectado' => true, 'estado' => 'conectado']),
        '*/enviar' => Http::response(['error' => 'boom'], 500),
    ]);
    motorActivo();
    $aviso = pendiente();
    $motor = app(MotorEnvio::class);

    $motor->tick();
    expect($aviso->fresh())->estado->toBe('pendiente')->intentos->toBe(1);

    $this->travel(61)->seconds();
    $motor->tick();
    $this->travel(61)->seconds();
    $motor->tick();

    expect($aviso->fresh())->estado->toBe('fallido')->intentos->toBe(3);
});

test('cancela los avisos que ya no corresponden en lugar de enviarlos', function () {
    botConectado();
    motorActivo();

    $viejo = pendiente(['fecha_aviso' => today()->subDay()->toDateString()]);

    $renovado = suscripcionQueVenceEn(3);
    Suscripcion::factory()->create([
        'cliente_id' => $renovado->cliente_id,
        'plan_id' => $renovado->plan_id,
        'fecha_inicio' => today()->addDays(4)->toDateString(),
        'fecha_vencimiento' => today()->addDays(33)->toDateString(),
        'estado' => 'activa',
    ]);
    $yaRenovo = pendiente(['suscripcion' => $renovado]);

    $vigente = pendiente();

    expect(app(MotorEnvio::class)->tick())->toBe('enviado');

    expect($viejo->fresh())->estado->toBe('cancelado')->detalle->toBe('No se envió el día programado.');
    expect($yaRenovo->fresh())->estado->toBe('cancelado')->detalle->toBe('El cliente ya renovó.');
    expect($vigente->fresh()->estado)->toBe('enviado');
});

test('el aviso de vence hoy sale antes que los de por vencer', function () {
    botConectado();
    motorActivo();
    $porVencer = pendiente();
    $hoy = pendiente(['tipo' => 'vence_hoy', 'suscripcion' => suscripcionQueVenceEn(0)]);

    app(MotorEnvio::class)->tick();

    expect($hoy->fresh()->estado)->toBe('enviado')
        ->and($porVencer->fresh()->estado)->toBe('pendiente');
});

// --- Dashboard -------------------------------------------------------------

test('solo el admin entra al dashboard de recordatorios', function () {
    $this->actingAs(User::factory()->vendedor()->create())
        ->get(route('recordatorios.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('recordatorios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recordatorios/Index', false)
            ->where('ajustes.activo', false)
            ->where('resumen.pendientes', 0));
});

test('iniciar y detener cambian el interruptor', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->post(route('recordatorios.iniciar'))->assertRedirect();
    expect(RecordatorioAjuste::actual()->activo)->toBeTrue();

    $this->actingAs($admin)->post(route('recordatorios.detener'))->assertRedirect();
    expect(RecordatorioAjuste::actual()->activo)->toBeFalse();
});

test('los ajustes no admiten esperas peligrosamente cortas', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('recordatorios.ajustes'), [
            'hora_inicio' => '08:00', 'hora_fin' => '18:00',
            'espera_min_seg' => 5, 'espera_max_seg' => 10,
            'mensajes_descanso_min' => 8, 'mensajes_descanso_max' => 12,
            'descanso_min' => 10, 'descanso_max' => 15,
            'dias_aviso' => 3, 'duracion_minima_dias' => 7,
        ])
        ->assertSessionHasErrors('espera_min_seg');
});

test('una plantilla con una variable inexistente se rechaza', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('recordatorio_plantillas.store'), [
            'tipo' => 'por_vencer', 'nombre' => 'X', 'cuerpo' => 'Hola {nombr}', 'activa' => true,
        ])
        ->assertSessionHasErrors('cuerpo');

    $this->actingAs(User::factory()->create())
        ->post(route('recordatorio_plantillas.store'), [
            'tipo' => 'por_vencer', 'nombre' => 'X', 'cuerpo' => 'Hola {nombre}', 'activa' => true,
        ])
        ->assertRedirect();

    expect(RecordatorioPlantilla::count())->toBe(1);
});

test('un aviso pendiente se puede cancelar y uno fallido reintentar', function () {
    $admin = User::factory()->create();
    $pendiente = pendiente();
    $fallido = pendiente(['estado' => 'fallido', 'intentos' => 3, 'detalle' => 'boom', 'suscripcion' => suscripcionQueVenceEn(3)]);

    $this->actingAs($admin)->post(route('recordatorios.cancelar', $pendiente))->assertRedirect();
    expect($pendiente->fresh()->estado)->toBe('cancelado');

    $this->actingAs($admin)->post(route('recordatorios.reintentar', $fallido))->assertRedirect();
    expect($fallido->fresh())->estado->toBe('pendiente')->intentos->toBe(0)->detalle->toBeNull();
});

// --- Historial de conexión del bot -----------------------------------------

function eventosDelBot(array $eventos, ?int $ultimoId = null): void
{
    Http::fake(['*/eventos*' => Http::response([
        'ultimo_id' => $ultimoId ?? collect($eventos)->max('id'),
        'eventos' => $eventos,
    ])]);
}

function eventoBot(int $id, string $tipo, string $cuando, ?string $detalle = null): array
{
    return ['id' => $id, 'tipo' => $tipo, 'detalle' => $detalle, 'codigo' => null, 'numero' => '584120000000', 'ocurrido_en' => $cuando];
}

test('guarda los eventos del bot una sola vez aunque se consulten varias veces', function () {
    eventosDelBot([
        eventoBot(1, 'servicio_iniciado', '2026-10-28T14:00:00.000Z'),
        eventoBot(2, 'conectado', '2026-10-28T14:00:05.000Z'),
    ]);

    expect(app(SincronizadorEventosBot::class)->sincronizar())->toBe(2);
    expect(app(SincronizadorEventosBot::class)->sincronizar())->toBe(0);
    expect(WhatsAppEvento::count())->toBe(2);
});

test('si el bot reinició su contador, vuelve a leer su historial desde el principio', function () {
    WhatsAppEvento::create(['evento_id' => 50, 'tipo' => 'conectado', 'ocurrido_en' => now()->subDay()]);

    eventosDelBot([
        eventoBot(1, 'servicio_iniciado', '2026-10-28T14:00:00.000Z'),
        eventoBot(2, 'qr_pendiente', '2026-10-28T14:00:03.000Z'),
    ], ultimoId: 2);

    expect(app(SincronizadorEventosBot::class)->sincronizar())->toBe(2);
});

test('sin servicio de WhatsApp la sincronización no falla ni guarda nada', function () {
    Http::fake(['*/eventos*' => Http::response(['error' => 'boom'], 500)]);

    expect(app(SincronizadorEventosBot::class)->sincronizar())->toBe(0);
    expect(WhatsAppEvento::count())->toBe(0);
});

test('el dashboard muestra el historial de conexión y su resumen del día', function () {
    eventosDelBot([
        eventoBot(1, 'conexion_perdida', now()->subMinutes(5)->toIso8601String(), 'Se perdió la conexión con WhatsApp. Reconectando...'),
        eventoBot(2, 'reconectado', now()->subMinutes(4)->toIso8601String(), 'Conexión recuperada tras 12 s sin servicio.'),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('recordatorios.index'))
        ->assertInertia(fn ($page) => $page
            ->where('conexiones.resumen.caidas_hoy', 1)
            ->where('conexiones.resumen.reconexiones_hoy', 1)
            ->where('conexiones.eventos.0.tipo', 'reconectado')
            ->where('conexiones.eventos.1.tipo', 'conexion_perdida'));
});

test('cerrar la sesión desde el panel deja constancia de quién lo hizo', function () {
    Http::fake(['*/desconectar' => Http::response(['ok' => true])]);
    $admin = User::factory()->create();

    $this->actingAs($admin)->post(route('recordatorios.bot.desconectar'))->assertRedirect();

    $evento = WhatsAppEvento::firstOrFail();
    expect($evento)->tipo->toBe('cierre_manual')->usuario_id->toBe($admin->id);
    expect(RecordatorioAjuste::actual()->activo)->toBeFalse();
});

test('los eventos de WhatsApp son de solo inserción', function () {
    $evento = WhatsAppEvento::create(['tipo' => 'conectado', 'ocurrido_en' => now()]);

    expect(fn () => $evento->update(['detalle' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $evento->delete())->toThrow(LogicException::class);
});

test('distingue un token rechazado de un bot apagado', function () {
    Http::fake(['*/estado' => Http::sequence()
        ->push(['error' => 'No autorizado.'], 401)
        ->push('', 502)]);

    expect(app(WhatsAppCliente::class)->estado()['estado'])->toBe('no_autorizado');
    expect(app(WhatsAppCliente::class)->estado()['estado'])->toBe('sin_servicio');
});

// --- QR expirado y reconexión manual ---------------------------------------

test('el botón de QR nuevo le pide al bot reconectar y anota quién lo hizo', function () {
    Http::fake(['*/reconectar' => Http::response(['ok' => true])]);
    $admin = User::factory()->create();

    $this->actingAs($admin)->post(route('recordatorios.bot.reconectar'))->assertRedirect();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/reconectar'));
    expect(WhatsAppEvento::firstOrFail())->tipo->toBe('reconexion_manual')->usuario_id->toBe($admin->id);
});

test('si el bot no puede reconectar no se anota nada', function () {
    Http::fake(['*/reconectar' => Http::response(['error' => 'WhatsApp ya está conectado.'], 409)]);

    $this->actingAs(User::factory()->create())->post(route('recordatorios.bot.reconectar'))->assertRedirect();

    expect(WhatsAppEvento::count())->toBe(0);
});

test('el estado incluye los segundos que le quedan al QR y reconoce el QR expirado', function () {
    Http::fake(['*/estado' => Http::sequence()
        ->push(['conectado' => false, 'estado' => 'esperando_qr', 'qr' => 'data:image/png;base64,xx', 'qr_expira_en' => 95, 'numero' => null])
        ->push(['conectado' => false, 'estado' => 'qr_expirado', 'qr' => null, 'qr_expira_en' => null, 'numero' => null])]);

    expect(app(WhatsAppCliente::class)->estado())->toMatchArray(['estado' => 'esperando_qr', 'qr_expira_en' => 95]);
    expect(app(WhatsAppCliente::class)->estado())->toMatchArray(['estado' => 'qr_expirado', 'qr' => null]);
});

test('se guardan los eventos de QR expirado que reporta el bot', function () {
    eventosDelBot([eventoBot(1, 'qr_expirado', '2026-10-28T14:02:00.000Z', 'Nadie escaneó el código en 2 min.')]);

    expect(app(SincronizadorEventosBot::class)->sincronizar())->toBe(1);
    expect(WhatsAppEvento::firstOrFail()->tipo)->toBe('qr_expirado');
});
