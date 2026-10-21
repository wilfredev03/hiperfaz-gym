<?php

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Services\TasaBcv\FallbackTasaBcvProvider;
use App\Services\TasaBcv\TasaBcvProvider;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

test('returns the value from the first provider that succeeds', function () {
    $principal = mock(TasaBcvProvider::class);
    $principal->shouldReceive('obtener')->once()->andReturn(813.7361);

    $respaldo = mock(TasaBcvProvider::class);
    $respaldo->shouldNotReceive('obtener');

    $tasa = (new FallbackTasaBcvProvider([$principal, $respaldo]))->obtener();

    expect($tasa)->toBe(813.7361);
});

test('falls back to the next provider when the first one fails', function () {
    $principal = mock(TasaBcvProvider::class);
    $principal->shouldReceive('obtener')->once()->andThrow(
        new TasaBcvNoDisponibleException('El BCV aún no publica la tasa de hoy.')
    );

    $respaldo = mock(TasaBcvProvider::class);
    $respaldo->shouldReceive('obtener')->once()->andReturn(807.3862);

    Log::shouldReceive('warning')->once();

    $tasa = (new FallbackTasaBcvProvider([$principal, $respaldo]))->obtener();

    expect($tasa)->toBe(807.3862);
});

test('throws an explicit failure when every provider fails', function () {
    $principal = mock(TasaBcvProvider::class);
    $principal->shouldReceive('obtener')->once()->andThrow(
        new TasaBcvNoDisponibleException('El BCV no respondió.')
    );

    $respaldo = mock(TasaBcvProvider::class);
    $respaldo->shouldReceive('obtener')->once()->andThrow(
        new TasaBcvNoDisponibleException('dolarapi.com no respondió.')
    );

    Log::shouldReceive('warning')->twice();

    expect(fn () => (new FallbackTasaBcvProvider([$principal, $respaldo]))->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});
