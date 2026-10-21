<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->company().' C.A.',
            'documento' => 'J-'.fake()->unique()->numerify('########').'-'.fake()->randomDigit(),
            'telefono' => fake()->randomElement(['0414', '0424', '0412', '0416', '0426']).fake()->numerify('#######'),
            'email' => fake()->companyEmail(),
            'direccion' => fake()->address(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
