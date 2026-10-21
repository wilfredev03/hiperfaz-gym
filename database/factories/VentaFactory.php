<?php

namespace Database\Factories;

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\TasaCambio;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    protected $model = Venta::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5, 200);
        $descuento = 0;
        $total = round($subtotal - $descuento, 4);

        return [
            'numero_venta' => 'V-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'cliente_id' => null,
            'usuario_id' => User::factory(),
            'tasa_cambio_id' => TasaCambio::factory(),
            'cierre_caja_id' => CierreCaja::factory(),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'total' => $total,
            'es_credito' => false,
            'monto_recibido' => $total,
            'vuelto' => 0,
            'estado' => 'pagada',
            'fecha' => now()->toDateString(),
        ];
    }

    /**
     * Venta a crédito: el CHECK de la base de datos exige cliente_id, así que
     * este estado siempre asigna uno.
     */
    public function credito(): static
    {
        return $this->state(fn (array $attributes) => [
            'cliente_id' => Cliente::factory(),
            'es_credito' => true,
            'monto_recibido' => null,
            'vuelto' => null,
            'estado' => 'pendiente',
        ]);
    }

    public function pendiente(): static
    {
        return $this->credito()->state(fn (array $attributes) => [
            'estado' => 'pendiente',
        ]);
    }

    public function parcial(): static
    {
        return $this->credito()->state(fn (array $attributes) => [
            'estado' => 'parcial',
        ]);
    }

    public function pagada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'pagada',
        ]);
    }

    public function anulada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'anulada',
        ]);
    }
}
