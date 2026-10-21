<?php

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Services\TasaBcv\DolarApiTasaBcvProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('returns the promedio price when the fecha de actualizacion is today', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'moneda' => 'USD',
            'fuente' => 'oficial',
            'nombre' => 'Dólar',
            'compra' => null,
            'venta' => null,
            'promedio' => 807.3862,
            'fechaActualizacion' => Date::now('America/Caracas')->toIso8601String(),
        ]),
    ]);

    $tasa = (new DolarApiTasaBcvProvider)->obtener();

    expect($tasa)->toBe(807.39);
});

test('rounds the promedio price to 2 decimals', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'promedio' => 871.3689,
            'fechaActualizacion' => Date::now('America/Caracas')->toIso8601String(),
        ]),
    ]);

    $tasa = (new DolarApiTasaBcvProvider)->obtener();

    expect($tasa)->toBe(871.37);
});

test('throws when the endpoint responds with a non-2xx status', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response(null, 503),
    ]);

    expect(fn () => (new DolarApiTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('throws when the fecha de actualizacion is not today', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'promedio' => 807.3862,
            'fechaActualizacion' => Date::now('America/Caracas')->subDay()->toIso8601String(),
        ]),
    ]);

    expect(fn () => (new DolarApiTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('throws when the fecha de actualizacion is missing or invalid', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'promedio' => 807.3862,
            'fechaActualizacion' => 'no-es-una-fecha',
        ]),
    ]);

    expect(fn () => (new DolarApiTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('throws when the response is missing the promedio price', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            'promedio' => null,
            'fechaActualizacion' => Date::now('America/Caracas')->toIso8601String(),
        ]),
    ]);

    expect(fn () => (new DolarApiTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('throws when the connection itself fails', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out.');
    });

    expect(fn () => (new DolarApiTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});
