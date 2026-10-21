<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaDetalle>
 */
class VentaDetalleFactory extends Factory
{
    protected $model = VentaDetalle::class;

    public function definition(): array
    {
        $cantidad = fake()->randomFloat(3, 0.5, 10);
        $precioUnitario = fake()->randomFloat(4, 0.5, 15);

        return [
            'venta_id' => Venta::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'costo_unitario_momento' => round($precioUnitario * fake()->randomFloat(2, 0.5, 0.8), 4),
            'subtotal' => round($cantidad * $precioUnitario, 4),
        ];
    }
}
