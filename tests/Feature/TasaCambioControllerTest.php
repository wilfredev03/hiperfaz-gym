<?php

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Models\TasaCambio;
use App\Models\User;
use App\Services\TasaBcv\TasaBcvProvider;

use function Pest\Laravel\mock;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('tasas_cambio.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of tasas with the current one', function () {
    $usuario = User::factory()->create();
    TasaCambio::factory()->count(2)->create(['activa' => false]);
    $actual = TasaCambio::factory()->create(['activa' => true]);

    $response = $this->actingAs($usuario)->get(route('tasas_cambio.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tasas_cambio/Index', false)
        ->has('tasas.data', 3)
        ->where('tasaActual.id', $actual->id)
    );
});

test('registers a manual tasa and redirects to the index', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('tasas_cambio.store'), [
        'tasa' => 162.5,
    ]);

    $response->assertRedirect(route('tasas_cambio.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Tasa de cambio registrada.']);
    $this->assertDatabaseHas('tasas_cambio', [
        'tasa' => 162.5,
        'tipo' => 'manual',
        'usuario_id' => $usuario->id,
        'activa' => true,
        'fecha' => now()->toDateString(),
    ]);
});

test('deactivates the previously active tasa when registering a manual one', function () {
    $usuario = User::factory()->create();
    $anterior = TasaCambio::factory()->create(['activa' => true]);

    $response = $this->actingAs($usuario)->post(route('tasas_cambio.store'), [
        'tasa' => 170,
    ]);

    $response->assertRedirect(route('tasas_cambio.index'));
    expect($anterior->fresh()->activa)->toBeFalse();
    expect(TasaCambio::where('activa', true)->count())->toBe(1);
});

test('loads the automatic tasa on demand, recording who asked for it', function () {
    $usuario = User::factory()->create();
    $manualDeHoy = TasaCambio::factory()->manual()->create(['activa' => true]);

    mock(TasaBcvProvider::class)->shouldReceive('obtener')->once()->andReturn(857.8876);

    $response = $this->actingAs($usuario)->post(route('tasas_cambio.cargar_automatica'));

    $response->assertRedirect(route('tasas_cambio.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Tasa BCV cargada automáticamente.']);
    $this->assertDatabaseHas('tasas_cambio', [
        'tasa' => 857.8876,
        'tipo' => 'automatica',
        'usuario_id' => $usuario->id,
        'activa' => true,
        'fecha' => now()->toDateString(),
    ]);
    expect($manualDeHoy->fresh()->activa)->toBeFalse();
});

test('keeps the current tasa and shows an error when the automatic load fails', function () {
    $usuario = User::factory()->create();
    $anterior = TasaCambio::factory()->create(['activa' => true, 'fecha' => now()->subDay()]);

    mock(TasaBcvProvider::class)
        ->shouldReceive('obtener')
        ->once()
        ->andThrow(new TasaBcvNoDisponibleException('La fuente no respondió.'));

    $response = $this->actingAs($usuario)->post(route('tasas_cambio.cargar_automatica'));

    $response->assertRedirect(route('tasas_cambio.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'No se pudo obtener la tasa del BCV. Intenta más tarde o regístrala manualmente.',
    ]);
    $this->assertDatabaseCount('tasas_cambio', 1);
    expect($anterior->fresh()->activa)->toBeTrue();
});

test('does not duplicate the automatic tasa already loaded today', function () {
    $usuario = User::factory()->create();
    TasaCambio::factory()->automatica()->create(['activa' => true]);

    mock(TasaBcvProvider::class)->shouldNotReceive('obtener');

    $response = $this->actingAs($usuario)->post(route('tasas_cambio.cargar_automatica'));

    $response->assertInertiaFlash('toast', ['type' => 'info', 'message' => 'La tasa BCV de hoy ya se cargó automáticamente.']);
    $this->assertDatabaseCount('tasas_cambio', 1);
});

test('requires a positive numeric tasa', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('tasas_cambio.store'), [
        'tasa' => -5,
    ]);

    $response->assertSessionHasErrors(['tasa']);
    $this->assertDatabaseCount('tasas_cambio', 0);
});
