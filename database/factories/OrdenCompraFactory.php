<?php

namespace Database\Factories;

use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\TasaCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenCompra>
 */
class OrdenCompraFactory extends Factory
{
    protected $model = OrdenCompra::class;

    public function definition(): array
    {
        return [
            'numero_orden' => 'OC-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'proveedor_id' => Proveedor::factory(),
            'usuario_id' => User::factory(),
            'tasa_cambio_id' => TasaCambio::factory(),
            'estado' => 'pendiente',
            'fecha' => now()->toDateString(),
            'total' => 0,
            'notas' => null,
        ];
    }

    public function recibidaParcial(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'recibida_parcial',
        ]);
    }

    public function recibidaCompleta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'recibida_completa',
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'cancelada',
        ]);
    }
}
