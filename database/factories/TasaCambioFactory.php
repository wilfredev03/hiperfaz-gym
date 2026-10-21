<?php

namespace Database\Factories;

use App\Models\TasaCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TasaCambio>
 */
class TasaCambioFactory extends Factory
{
    protected $model = TasaCambio::class;

    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'tasa' => fake()->randomFloat(4, 150, 250),
            'tipo' => 'automatica',
            'usuario_id' => null,
            'activa' => true,
        ];
    }

    public function automatica(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'automatica',
            'usuario_id' => null,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'manual',
            'usuario_id' => User::factory(),
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => [
            'activa' => false,
        ]);
    }
}
