<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        // el orden es el que se ve en la pantalla de cobro; updateOrCreate
        // para que también corrija el orden de métodos ya existentes
        // Transferencia no se usa: en la práctica ese pago se hace como
        // Pago Móvil, no hace falta un método aparte.
        collect([
            ['nombre' => 'Punto de Venta', 'moneda' => 'VES', 'requiere_referencia' => false, 'orden' => 1],
            ['nombre' => 'Efectivo Bs', 'moneda' => 'VES', 'requiere_referencia' => false, 'orden' => 2],
            ['nombre' => 'Pago Móvil', 'moneda' => 'VES', 'requiere_referencia' => true, 'orden' => 3],
            ['nombre' => 'Efectivo USD', 'moneda' => 'USD', 'requiere_referencia' => false, 'orden' => 4],
        ])->each(fn (array $metodo) => MetodoPago::updateOrCreate(
            ['nombre' => $metodo['nombre']],
            [
                'moneda' => $metodo['moneda'],
                'requiere_referencia' => $metodo['requiere_referencia'],
                'orden' => $metodo['orden'],
                'activo' => true,
            ],
        ));
    }
}
