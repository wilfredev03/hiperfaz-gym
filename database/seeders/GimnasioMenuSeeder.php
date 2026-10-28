<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * Catálogo de arranque del gimnasio: planes de membresía (productos que no
 * controlan inventario, ver principio #4 de CLAUDE.md) y productos de
 * mostrador. Los precios son de ejemplo; el dueño los ajusta desde Productos.
 * Se ejecuta a mano una vez por instalación:
 * `php artisan db:seed --class=GimnasioMenuSeeder`.
 */
class GimnasioMenuSeeder extends Seeder
{
    public function run(): void
    {
        $und = UnidadMedida::firstOrCreate(['abreviatura' => 'und'], ['nombre' => 'Unidad']);

        $categorias = collect(['Membresías', 'Bebidas', 'Batidos', 'Suplementos'])
            ->mapWithKeys(fn (string $nombre) => [
                $nombre => Categoria::firstOrCreate(['nombre' => $nombre]),
            ]);

        // planes de membresía: producto sin inventario + su duración en días
        $planes = [
            ['Diario', 3.00, 1],
            ['Semanal', 10.00, 7],
            ['Mensual', 25.00, 30],
        ];

        foreach ($planes as [$nombre, $precio, $dias]) {
            $producto = Producto::firstOrCreate(['nombre' => $nombre], [
                'categoria_id' => $categorias['Membresías']->id,
                'unidad_medida_id' => $und->id,
                'precio_venta' => $precio,
                'costo_promedio' => 0,
                'stock_actual' => 0,
                'controla_inventario' => false,
                'activo' => true,
            ]);

            Plan::firstOrCreate(['producto_id' => $producto->id], ['duracion_dias' => $dias]);
        }

        $this->call(RecordatorioPlantillaSeeder::class);

        // sin inventario: batidos hechos al momento
        $sinInventario = [
            ['Batidos', 'Batido de Proteína', 4.00, 2.00],
            ['Batidos', 'Batido de Fresa', 3.00, 1.20],
        ];

        // con inventario: se descuenta stock al vender
        $conInventario = [
            ['Bebidas', 'Agua 600ml', 1.00, 0.45, 48],
            ['Bebidas', 'Bebida Hidratante 500ml', 2.00, 1.20, 36],
            ['Bebidas', 'Bebida Energética 250ml', 2.50, 1.60, 24],
            ['Suplementos', 'Proteína Whey (porción)', 3.50, 2.20, 30],
            ['Suplementos', 'Creatina Monohidratada (pote)', 30.00, 21.00, 8],
            ['Suplementos', 'Barra de Proteína', 2.50, 1.50, 40],
        ];

        foreach ($sinInventario as [$categoria, $nombre, $precio, $costo]) {
            Producto::firstOrCreate(['nombre' => $nombre], [
                'categoria_id' => $categorias[$categoria]->id,
                'unidad_medida_id' => $und->id,
                'precio_venta' => $precio,
                'costo_promedio' => $costo,
                'stock_actual' => 0,
                'controla_inventario' => false,
                'activo' => true,
            ]);
        }

        foreach ($conInventario as [$categoria, $nombre, $precio, $costo, $stock]) {
            Producto::firstOrCreate(['nombre' => $nombre], [
                'categoria_id' => $categorias[$categoria]->id,
                'unidad_medida_id' => $und->id,
                'precio_venta' => $precio,
                'costo_promedio' => $costo,
                'stock_actual' => $stock,
                'controla_inventario' => true,
                'activo' => true,
            ]);
        }
    }
}
