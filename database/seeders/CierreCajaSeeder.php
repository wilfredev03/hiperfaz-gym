<?php

namespace Database\Seeders;

use App\Models\CierreCaja;
use App\Models\TasaCambio;
use App\Models\User;
use Illuminate\Database\Seeder;

class CierreCajaSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::first() ?? User::factory()->create();

        // cierres cerrados de los últimos 6 días
        for ($diasAtras = 6; $diasAtras >= 1; $diasAtras--) {
            $fecha = now()->subDays($diasAtras);
            $tasa = TasaCambio::whereDate('fecha', $fecha->toDateString())->first();
            $totalVentas = fake()->randomFloat(2, 150, 600);

            CierreCaja::create([
                'usuario_id' => $usuario->id,
                'tasa_cambio_id' => $tasa?->id,
                'fecha' => $fecha->toDateString(),
                'hora_apertura' => $fecha->copy()->setTime(8, 0),
                'hora_cierre' => $fecha->copy()->setTime(20, 0),
                'total_ventas' => $totalVentas,
                'total_ventas_bs' => round($totalVentas * (float) ($tasa?->tasa ?? 0), 4),
                'estado' => 'cerrado',
            ]);
        }

        // caja abierta de hoy, para poder registrar ventas de demostración
        $tasaHoy = TasaCambio::actual();

        CierreCaja::firstOrCreate(
            ['fecha' => now()->toDateString(), 'estado' => 'abierto'],
            [
                'usuario_id' => $usuario->id,
                'tasa_cambio_id' => $tasaHoy?->id,
                'hora_apertura' => now(),
                'total_ventas' => 0,
                'estado' => 'abierto',
            ],
        );
    }
}
