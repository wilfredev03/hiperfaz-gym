<?php

namespace Database\Seeders;

use App\Models\TasaCambio;
use Illuminate\Database\Seeder;

class TasaCambioSeeder extends Seeder
{
    public function run(): void
    {
        // histórico de los últimos 6 días, tasa automática BCV
        $tasa = 180;
        for ($diasAtras = 6; $diasAtras >= 1; $diasAtras--) {
            $tasa += fake()->randomFloat(4, -1, 3);

            TasaCambio::create([
                'fecha' => now()->subDays($diasAtras)->toDateString(),
                'tasa' => $tasa,
                'tipo' => 'automatica',
                'usuario_id' => null,
                'activa' => false,
            ]);
        }

        // tasa activa de hoy
        TasaCambio::create([
            'fecha' => now()->toDateString(),
            'tasa' => $tasa + fake()->randomFloat(4, 0, 2),
            'tipo' => 'automatica',
            'usuario_id' => null,
            'activa' => true,
        ]);
    }
}
