<?php

namespace Database\Factories;

use App\Models\CierreCaja;
use App\Models\MetodoPago;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaPago>
 */
class VentaPagoFactory extends Factory
{
    protected $model = VentaPago::class;

    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'metodo_pago_id' => MetodoPago::factory(),
            'cierre_caja_id' => CierreCaja::factory(),
            'monto' => fake()->randomFloat(2, 5, 200),
            'referencia' => null,
            'fecha_pago' => now(),
        ];
    }

    public function conReferencia(): static
    {
        return $this->state(fn (array $attributes) => [
            'referencia' => fake()->numerify('#####'),
        ]);
    }
}
