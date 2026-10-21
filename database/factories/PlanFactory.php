<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory()->sinControlDeInventario(),
            'duracion_dias' => 30,
        ];
    }

    public function duracion(int $dias): static
    {
        return $this->state(fn (array $attributes) => ['duracion_dias' => $dias]);
    }
}
