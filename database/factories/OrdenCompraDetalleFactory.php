<?php

namespace Database\Factories;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenCompraDetalle>
 */
class OrdenCompraDetalleFactory extends Factory
{
    protected $model = OrdenCompraDetalle::class;

    public function definition(): array
    {
        $cantidad = fake()->randomFloat(3, 5, 50);
        $costoUnitario = fake()->randomFloat(4, 0.3, 10);

        return [
            'orden_compra_id' => OrdenCompra::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => $cantidad,
            'cantidad_recibida' => 0,
            'costo_unitario' => $costoUnitario,
            'subtotal' => round($cantidad * $costoUnitario, 4),
        ];
    }

    public function recibidaCompleta(): static
    {
        return $this->state(fn (array $attributes) => [
            'cantidad_recibida' => $attributes['cantidad'],
        ]);
    }
}
