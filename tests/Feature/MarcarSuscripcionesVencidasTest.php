<?php

use App\Models\Cliente;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\SuscripcionService;

function suscripcionConFechas(string $estado, int $inicioHace, int $venceEn, ?Cliente $cliente = null): Suscripcion
{
    return Suscripcion::factory()->create([
        'cliente_id' => ($cliente ?? Cliente::factory()->create())->id,
        'fecha_inicio' => now()->subDays($inicioHace)->toDateString(),
        'fecha_vencimiento' => now()->addDays($venceEn)->toDateString(),
        'estado' => $estado,
    ]);
}

test('el comando marca vencidas solo las activas cuyo último día ya pasó', function () {
    $pasada = suscripcionConFechas('activa', 40, -1);
    $venceHoy = suscripcionConFechas('activa', 29, 0);
    $vigente = suscripcionConFechas('activa', 5, 25);
    $cancelada = suscripcionConFechas('cancelada', 40, -5);

    $this->artisan('suscripciones:marcar-vencidas')->assertSuccessful();

    expect($pasada->fresh()->estado)->toBe('vencida')
        ->and($venceHoy->fresh()->estado)->toBe('activa')
        ->and($vigente->fresh()->estado)->toBe('activa')
        ->and($cancelada->fresh()->estado)->toBe('cancelada');
});

test('el comando no cambia las fechas pactadas', function () {
    $suscripcion = suscripcionConFechas('activa', 40, -3);
    $inicio = $suscripcion->fecha_inicio->toDateString();
    $vencimiento = $suscripcion->fecha_vencimiento->toDateString();

    $this->artisan('suscripciones:marcar-vencidas');

    $suscripcion->refresh();
    expect($suscripcion->fecha_inicio->toDateString())->toBe($inicio)
        ->and($suscripcion->fecha_vencimiento->toDateString())->toBe($vencimiento);
});

test('un socio con la suscripción ya marcada vencida sigue figurando como vencido', function () {
    $socio = Cliente::factory()->create();
    suscripcionConFechas('activa', 40, -3, $socio);
    $this->artisan('suscripciones:marcar-vencidas');

    $this->actingAs(User::factory()->create())
        ->get(route('socios.index', ['filtro' => 'vencidas']))
        ->assertInertia(fn ($page) => $page->has('socios.data', 1)->where('socios.data.0.id', $socio->id));

    $this->actingAs(User::factory()->create())
        ->get(route('socios.show', $socio))
        ->assertInertia(fn ($page) => $page->where('membresia.vigencia', 'vencida'));

    expect($socio->suscripcionVigente())->toBeNull();
});

test('renovar tras marcar la vencida empieza hoy', function () {
    $socio = Cliente::factory()->create();
    suscripcionConFechas('activa', 40, -3, $socio);
    $this->artisan('suscripciones:marcar-vencidas');

    $nueva = app(SuscripcionService::class);
    $detalle = \App\Models\VentaDetalle::factory()->create();
    $plan = \App\Models\Plan::factory()->duracion(30)->create();

    $emitida = $nueva->emitir($detalle, $plan, $socio);

    expect($emitida->fecha_inicio->toDateString())->toBe(now()->toDateString());
});
