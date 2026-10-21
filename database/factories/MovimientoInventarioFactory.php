<?php

namespace Database\Factories;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoInventario>
 */
class MovimientoInventarioFactory extends Factory
{
    protected $model = MovimientoInventario::class;

    public function definition(): array
    {
        $stockAnterior = fake()->randomFloat(3, 10, 100);
        $cantidad = fake()->randomFloat(3, 1, 10);

        return [
            'producto_id' => Producto::factory(),
            'tipo' => 'entrada_compra',
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => round($stockAnterior + $cantidad, 3),
            'referencia_tipo' => null,
            'referencia_id' => null,
            'usuario_id' => User::factory(),
            'notas' => null,
        ];
    }

    /**
     * Los estados aceptan $stockAnterior/$cantidad explícitos porque un override
     * pasado a create() se aplica después de resolver los estados y no sería
     * visible para el cálculo de stock_nuevo si dependiera de $attributes.
     */
    public function entradaCompra(?float $stockAnterior = null, ?float $cantidad = null): static
    {
        return $this->state(function (array $attributes) use ($stockAnterior, $cantidad) {
            $anterior = $stockAnterior ?? $attributes['stock_anterior'];
            $cant = $cantidad ?? $attributes['cantidad'];

            return [
                'tipo' => 'entrada_compra',
                'stock_anterior' => $anterior,
                'cantidad' => $cant,
                'stock_nuevo' => round($anterior + $cant, 3),
                'referencia_tipo' => 'orden_compra',
            ];
        });
    }

    public function salidaVenta(?float $stockAnterior = null, ?float $cantidad = null): static
    {
        return $this->state(function (array $attributes) use ($stockAnterior, $cantidad) {
            $anterior = $stockAnterior ?? $attributes['stock_anterior'];
            $cant = $cantidad ?? $attributes['cantidad'];

            return [
                'tipo' => 'salida_venta',
                'stock_anterior' => $anterior,
                'cantidad' => $cant,
                'stock_nuevo' => round($anterior - $cant, 3),
                'referencia_tipo' => 'venta',
            ];
        });
    }

    public function ajustePositivo(?float $stockAnterior = null, ?float $cantidad = null): static
    {
        return $this->state(function (array $attributes) use ($stockAnterior, $cantidad) {
            $anterior = $stockAnterior ?? $attributes['stock_anterior'];
            $cant = $cantidad ?? $attributes['cantidad'];

            return [
                'tipo' => 'ajuste_positivo',
                'stock_anterior' => $anterior,
                'cantidad' => $cant,
                'stock_nuevo' => round($anterior + $cant, 3),
                'referencia_tipo' => 'ajuste',
            ];
        });
    }

    public function ajusteNegativo(?float $stockAnterior = null, ?float $cantidad = null): static
    {
        return $this->state(function (array $attributes) use ($stockAnterior, $cantidad) {
            $anterior = $stockAnterior ?? $attributes['stock_anterior'];
            $cant = $cantidad ?? $attributes['cantidad'];

            return [
                'tipo' => 'ajuste_negativo',
                'stock_anterior' => $anterior,
                'cantidad' => $cant,
                'stock_nuevo' => round($anterior - $cant, 3),
                'referencia_tipo' => 'ajuste',
            ];
        });
    }

    public function devolucion(?float $stockAnterior = null, ?float $cantidad = null): static
    {
        return $this->state(function (array $attributes) use ($stockAnterior, $cantidad) {
            $anterior = $stockAnterior ?? $attributes['stock_anterior'];
            $cant = $cantidad ?? $attributes['cantidad'];

            return [
                'tipo' => 'devolucion',
                'stock_anterior' => $anterior,
                'cantidad' => $cant,
                'stock_nuevo' => round($anterior + $cant, 3),
                'referencia_tipo' => 'venta',
            ];
        });
    }
}
