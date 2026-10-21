<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadMedida>
 */
class UnidadMedidaFactory extends Factory
{
    protected $model = UnidadMedida::class;

    public function definition(): array
    {
        $unidad = fake()->unique()->randomElement([
            ['nombre' => 'Kilogramo', 'abreviatura' => 'kg'],
            ['nombre' => 'Gramo', 'abreviatura' => 'g'],
            ['nombre' => 'Unidad', 'abreviatura' => 'und'],
            ['nombre' => 'Litro', 'abreviatura' => 'L'],
            ['nombre' => 'Mililitro', 'abreviatura' => 'ml'],
            ['nombre' => 'Paquete', 'abreviatura' => 'paq'],
            ['nombre' => 'Caja', 'abreviatura' => 'cja'],
            ['nombre' => 'Docena', 'abreviatura' => 'dcna'],
            ['nombre' => 'Bolsa', 'abreviatura' => 'bls'],
        ]);

        return [
            'nombre' => $unidad['nombre'],
            'abreviatura' => $unidad['abreviatura'],
        ];
    }
}
