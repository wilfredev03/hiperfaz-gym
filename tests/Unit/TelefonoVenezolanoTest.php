<?php

use App\Services\Recordatorios\TelefonoVenezolano;

test('normaliza los formatos habituales de un celular venezolano', function (string $entrada) {
    expect(TelefonoVenezolano::normalizar($entrada))->toBe('584141234567');
})->with([
    '0414-1234567',
    '04141234567',
    '+58 414 1234567',
    '584141234567',
    '4141234567',
    '(0414) 123 45 67',
]);

test('rechaza lo que no es un celular venezolano', function (?string $entrada) {
    expect(TelefonoVenezolano::normalizar($entrada))->toBeNull();
})->with([
    'vacío' => '',
    'nulo' => null,
    'fijo de Caracas' => '0212-1234567',
    'operadora inexistente' => '0400-1234567',
    'muy corto' => '0414-12345',
    'muy largo' => '0414-123456789',
    'texto' => 'sin teléfono',
]);
