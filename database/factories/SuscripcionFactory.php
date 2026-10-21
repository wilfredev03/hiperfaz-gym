<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suscripcion>
 */
class SuscripcionFactory extends Factory
{
    protected $model = Suscripcion::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'plan_id' => Plan::factory(),
            'venta_detalle_id' => VentaDetalle::factory(),
            'fecha_inicio' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(29)->toDateString(),
            'estado' => 'activa',
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => now()->subDays(40)->toDateString(),
            'fecha_vencimiento' => now()->subDays(10)->toDateString(),
            'estado' => 'vencida',
        ]);
    }

    public function congelada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'congelada']);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'cancelada']);
    }
}
