<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['nombre' => 'Kilogramo', 'abreviatura' => 'kg'],
            ['nombre' => 'Gramo', 'abreviatura' => 'g'],
            ['nombre' => 'Unidad', 'abreviatura' => 'und'],
            ['nombre' => 'Litro', 'abreviatura' => 'L'],
            ['nombre' => 'Mililitro', 'abreviatura' => 'ml'],
            ['nombre' => 'Paquete', 'abreviatura' => 'paq'],
            ['nombre' => 'Caja', 'abreviatura' => 'cja'],
            ['nombre' => 'Docena', 'abreviatura' => 'dcna'],
            ['nombre' => 'Bolsa', 'abreviatura' => 'bls'],
        ])->each(fn (array $unidad) => UnidadMedida::firstOrCreate(
            ['abreviatura' => $unidad['abreviatura']],
            ['nombre' => $unidad['nombre']],
        ));
    }
}
