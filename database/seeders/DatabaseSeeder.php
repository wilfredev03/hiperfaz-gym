<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed común a cualquier instalación (ver CLAUDE.md): datos estructurales
     * base, sin el catálogo real del negocio. El catálogo de arranque del
     * gimnasio se siembra aparte, a mano, una vez por instalación:
     * `php artisan db:seed --class=GimnasioMenuSeeder`.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SucursalSeeder::class,
            UnidadMedidaSeeder::class,
            MetodoPagoSeeder::class,
        ]);
    }
}
