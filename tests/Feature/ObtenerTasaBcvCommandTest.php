<?php

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Models\TasaCambio;
use App\Services\TasaBcv\TasaBcvProvider;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

test('registers a new automatic tasa when the provider succeeds', function () {
    $anterior = TasaCambio::factory()->create(['activa' => true, 'tipo' => 'manual', 'fecha' => now()->subDay()]);

    mock(TasaBcvProvider::class)
        ->shouldReceive('obtener')
        ->once()
        ->andReturn(157.2345);

    $this->artisan('tasas:obtener-bcv')->assertExitCode(0);

    $this->assertDatabaseHas('tasas_cambio', [
        'tipo' => 'automatica',
        'tasa' => 157.2345,
        'usuario_id' => null,
        'activa' => true,
    ]);
    expect($anterior->fresh()->activa)->toBeFalse();
    expect(TasaCambio::where('activa', true)->count())->toBe(1);
});

test('logs an explicit failure and keeps the previous tasa active when the provider fails', function () {
    $anterior = TasaCambio::factory()->create(['activa' => true, 'tasa' => 150, 'fecha' => now()->subDay()]);

    mock(TasaBcvProvider::class)
        ->shouldReceive('obtener')
        ->once()
        ->andThrow(new TasaBcvNoDisponibleException('La fuente no respondió.'));

    Log::shouldReceive('error')
        ->once()
        ->with('No se pudo obtener la tasa BCV automática del día.', ['error' => 'La fuente no respondió.']);

    $this->artisan('tasas:obtener-bcv')->assertExitCode(1);

    $this->assertDatabaseCount('tasas_cambio', 1);
    expect($anterior->fresh()->activa)->toBeTrue();
    expect(TasaCambio::actual()->id)->toBe($anterior->id);
});

test('does not query the provider when today already has a tasa', function (string $tipo) {
    TasaCambio::factory()->{$tipo}()->create(['activa' => true]);

    mock(TasaBcvProvider::class)->shouldNotReceive('obtener');

    $this->artisan('tasas:obtener-bcv')->assertExitCode(0);

    $this->assertDatabaseCount('tasas_cambio', 1);
})->with(['automatica', 'manual']);
