<?php

namespace Database\Factories;

use App\Models\MetodoPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetodoPago>
 */
class MetodoPagoFactory extends Factory
{
    protected $model = MetodoPago::class;

    public function definition(): array
    {
        $metodo = fake()->unique()->randomElement([
            ['nombre' => 'Efectivo USD', 'moneda' => 'USD', 'requiere_referencia' => false],
            ['nombre' => 'Efectivo Bs', 'moneda' => 'VES', 'requiere_referencia' => false],
            ['nombre' => 'Pago Móvil', 'moneda' => 'VES', 'requiere_referencia' => true],
            ['nombre' => 'Transferencia', 'moneda' => 'VES', 'requiere_referencia' => true],
        ]);

        return [
            'nombre' => $metodo['nombre'],
            'moneda' => $metodo['moneda'],
            'requiere_referencia' => $metodo['requiere_referencia'],
            'activo' => true,
        ];
    }

    public function efectivoUsd(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Efectivo USD',
            'moneda' => 'USD',
            'requiere_referencia' => false,
        ]);
    }

    public function efectivoBs(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Efectivo Bs',
            'moneda' => 'VES',
            'requiere_referencia' => false,
        ]);
    }

    public function pagoMovil(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Pago Móvil',
            'moneda' => 'VES',
            'requiere_referencia' => true,
        ]);
    }

    public function transferencia(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Transferencia',
            'moneda' => 'VES',
            'requiere_referencia' => true,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
