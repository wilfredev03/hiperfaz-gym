<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'documento' => fake()->randomElement(['V', 'E']).'-'.fake()->unique()->numerify('########'),
            'telefono' => fake()->randomElement(['0414', '0424', '0412', '0416', '0426']).fake()->numerify('#######'),
            'email' => fake()->safeEmail(),
            'direccion' => fake()->address(),
            'saldo_pendiente' => 0,
            'activo' => true,
        ];
    }

    public function conSaldoPendiente(?float $monto = null): static
    {
        return $this->state(fn (array $attributes) => [
            'saldo_pendiente' => $monto ?? fake()->randomFloat(2, 5, 200),
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
