<?php

namespace Database\Factories;

use App\Models\CierreCaja;
use App\Models\CierreCajaMetodo;
use App\Models\MetodoPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CierreCajaMetodo>
 */
class CierreCajaMetodoFactory extends Factory
{
    protected $model = CierreCajaMetodo::class;

    public function definition(): array
    {
        $monto = fake()->randomFloat(2, 10, 300);

        return [
            'cierre_caja_id' => CierreCaja::factory(),
            'metodo_pago_id' => MetodoPago::factory(),
            'monto' => $monto,
            'monto_bs' => round($monto * 40, 4),
        ];
    }
}
