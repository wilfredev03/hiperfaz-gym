<?php

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Services\TasaBcv\BcvScrapingTasaBcvProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    // miércoles fijo: día hábil "normal" que no cae en fin de semana, para
    // que las pruebas no dependan de en qué día de la semana se ejecuten
    Date::setTestNow(Date::parse('2025-01-08 09:00:00', 'America/Caracas'));
});

afterEach(function () {
    Date::setTestNow();
});

function paginaBcv(string $valor, string $fechaValor): string
{
    return <<<HTML
        <div id="dolar" class="col-sm-12 col-xs-12 ">
            <div class="field-content">
                <div class="row recuadrotsmc">
                    <div class="col-sm-6 col-xs-6"><span> USD</span></div>
                    <div class="col-sm-6 col-xs-6 centrado textp"> <strong class="strong-tb">{$valor}</strong> </div>
                </div>
            </div>
        </div>
        <div class="pull-right dinpro center">
        Fecha Valor: <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="{$fechaValor}">Lunes</span>
        <hr>
        </div>
        HTML;
}

test('returns the dolar rate parsed from the html when fecha valor is today', function () {
    Http::fake([
        'www.bcv.org.ve/*' => Http::response(
            paginaBcv('813,73610000', Date::now('America/Caracas')->toIso8601String())
        ),
    ]);

    $tasa = (new BcvScrapingTasaBcvProvider)->obtener();

    expect($tasa)->toBe(813.74);
});

test('rounds the dolar rate to 2 decimals', function () {
    Http::fake([
        'www.bcv.org.ve/*' => Http::response(
            paginaBcv('871,36890000', Date::now('America/Caracas')->toIso8601String())
        ),
    ]);

    $tasa = (new BcvScrapingTasaBcvProvider)->obtener();

    expect($tasa)->toBe(871.37);
});

test('throws when the endpoint responds with a non-2xx status', function () {
    Http::fake([
        'www.bcv.org.ve/*' => Http::response(null, 503),
    ]);

    expect(fn () => (new BcvScrapingTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('throws on a weekday when the fecha valor is not today', function () {
    expect(Date::now('America/Caracas')->isWeekend())->toBeFalse();

    Http::fake([
        'www.bcv.org.ve/*' => Http::response(
            paginaBcv('813,73610000', Date::now('America/Caracas')->subDay()->toIso8601String())
        ),
    ]);

    expect(fn () => (new BcvScrapingTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('accepts the "fecha valor" of the upcoming monday on a saturday', function () {
    // el viernes en la tarde el BCV publica, "adelantada", la tasa con
    // fecha valor del lunes (el próximo día hábil) — esa es la que debe
    // regir todo el fin de semana, aunque todavía no sea lunes
    Date::setTestNow(Date::parse('2025-01-04 09:00:00', 'America/Caracas')); // sábado
    expect(Date::now('America/Caracas')->isWeekend())->toBeTrue();

    Http::fake([
        'www.bcv.org.ve/*' => Http::response(
            paginaBcv('813,73610000', Date::parse('2025-01-06T00:00:00-04:00')->toIso8601String()) // lunes
        ),
    ]);

    $tasa = (new BcvScrapingTasaBcvProvider)->obtener();

    expect($tasa)->toBe(813.74);
});

test('accepts the "fecha valor" of the previous friday on a sunday', function () {
    // en fin de semana se usa lo que esté publicado en el BCV, sin exigir
    // que la fecha valor coincida con la de hoy
    Date::setTestNow(Date::parse('2025-01-05 09:00:00', 'America/Caracas')); // domingo
    expect(Date::now('America/Caracas')->isWeekend())->toBeTrue();

    Http::fake([
        'www.bcv.org.ve/*' => Http::response(
            paginaBcv('813,73610000', Date::parse('2025-01-03T00:00:00-04:00')->toIso8601String()) // viernes
        ),
    ]);

    $tasa = (new BcvScrapingTasaBcvProvider)->obtener();

    expect($tasa)->toBe(813.74);
});

test('throws when the html no longer contains the dolar value', function () {
    Http::fake([
        'www.bcv.org.ve/*' => Http::response('<div id="euro">otra estructura</div>'),
    ]);

    expect(fn () => (new BcvScrapingTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});

test('throws when the connection itself fails', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out.');
    });

    expect(fn () => (new BcvScrapingTasaBcvProvider)->obtener())
        ->toThrow(TasaBcvNoDisponibleException::class);
});
