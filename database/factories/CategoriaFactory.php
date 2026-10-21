<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement([
                'Suplementos',
                'Bebidas',
                'Batidos',
                'Snacks y Barras',
                'Accesorios',
                'Ropa Deportiva',
                'Cuidado Personal',
                'Proteínas',
                'Vitaminas',
                'Pre-entrenos',
                'Hidratación',
                'Equipamiento',
                'Café y Té',
                'Higiene',
            ]),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
