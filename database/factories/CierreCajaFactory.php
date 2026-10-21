<?php

namespace Database\Factories;

use App\Models\CierreCaja;
use App\Models\TasaCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CierreCaja>
 */
class CierreCajaFactory extends Factory
{
    protected $model = CierreCaja::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'tasa_cambio_id' => TasaCambio::factory(),
            'fecha' => now()->toDateString(),
            'hora_apertura' => now()->setTime(8, 0),
            'hora_cierre' => null,
            'total_ventas' => 0,
            'estado' => 'abierto',
        ];
    }

    public function cerrado(): static
    {
        return $this->state(function (array $attributes) {
            $totalVentas = fake()->randomFloat(2, 100, 500);

            return [
                'hora_cierre' => now()->setTime(20, 0),
                'total_ventas' => $totalVentas,
                'total_ventas_bs' => round($totalVentas * 40, 4),
                'estado' => 'cerrado',
            ];
        });
    }
}
