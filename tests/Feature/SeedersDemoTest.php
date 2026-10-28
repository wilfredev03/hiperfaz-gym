<?php

use App\Models\Cliente;
use App\Models\CierreCaja;
use App\Models\Suscripcion;
use App\Models\Venta;
use Database\Seeders\ClientesMembresiasSeeder;
use Database\Seeders\ClientesVencidosSeeder;
use Database\Seeders\GimnasioMenuSeeder;
use Database\Seeders\MetodoPagoSeeder;

beforeEach(function () {
    $this->seed([GimnasioMenuSeeder::class, MetodoPagoSeeder::class]);
});

/** Cada venta sembrada tiene su suscripción, y las cajas cerradas cuadran con lo vendido. */
function expectDatosCoherentes(): void
{
    expect(Venta::count())->toBe(Suscripcion::count());

    $cerradas = CierreCaja::where('estado', 'cerrado')->get();
    $vendido = (float) Venta::whereIn('cierre_caja_id', $cerradas->pluck('id'))->sum('total');

    expect(round((float) $cerradas->sum('total_ventas'), 2))->toBe(round($vendido, 2));
}

test('ClientesVencidosSeeder deja 60 clientes cuya última membresía ya venció', function () {
    $this->seed(ClientesVencidosSeeder::class);

    expect(Cliente::count())->toBe(60);

    Cliente::with('suscripciones')->get()->each(function (Cliente $cliente) {
        expect($cliente->suscripciones)->not->toBeEmpty();
        expect($cliente->suscripciones->max('fecha_vencimiento')->isBefore(today()))->toBeTrue();
        expect($cliente->suscripciones->every(fn ($s) => $s->estado === 'vencida'))->toBeTrue();
    });

    expectDatosCoherentes();
});

test('ClientesMembresiasSeeder deja 200 clientes, todos con membresías', function () {
    $this->seed(ClientesMembresiasSeeder::class);

    expect(Cliente::count())->toBe(200)
        ->and(Cliente::doesntHave('suscripciones')->count())->toBe(0)
        // hay de las dos cosas: vigentes y vencidas
        ->and(Suscripcion::where('estado', 'activa')->exists())->toBeTrue()
        ->and(Suscripcion::where('estado', 'vencida')->exists())->toBeTrue();

    expectDatosCoherentes();
});
