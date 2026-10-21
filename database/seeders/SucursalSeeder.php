<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Run the database seeds. Todo negocio arranca con una única sucursal
     * "Principal"; el dueño la renombra o agrega más desde Sucursales.
     */
    public function run(): void
    {
        Sucursal::firstOrCreate(
            ['nombre' => 'Principal'],
            ['termino_orden' => 'Orden', 'activa' => true],
        );
    }
}
