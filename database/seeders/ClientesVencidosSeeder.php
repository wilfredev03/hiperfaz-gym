<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Plan;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\RegistraMembresiasHistoricas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración: 60 clientes cuya última membresía ya venció, para
 * probar "Por vencer > Vencidos", los recordatorios y la renovación. El 70 %
 * venció en los últimos 30 días (los que muestra la pestaña Vencidos) y el
 * resto hace entre 31 y 180 días; algunos tienen renovaciones anteriores y
 * unos pocos quedaron debiendo la última (venta a crédito).
 *
 * Requiere los planes de `GimnasioMenuSeeder` y los métodos de `MetodoPagoSeeder`.
 * No está en `DatabaseSeeder`; cada corrida agrega otros 60:
 * `php artisan db:seed --class=ClientesVencidosSeeder`.
 */
class ClientesVencidosSeeder extends Seeder
{
    use RegistraMembresiasHistoricas;

    private const CANTIDAD = 60;

    public function run(): void
    {
        $planes = $this->prepararMembresias();

        if (! $planes) {
            return;
        }

        DB::transaction(function () use ($planes) {
            Cliente::factory()
                ->count(self::CANTIDAD)
                ->create()
                ->each(fn (Cliente $cliente) => $this->sembrar($cliente, $planes));

            $this->totalizarCierres();
        });
    }

    /** @param  Collection<int, Plan>  $planes */
    private function sembrar(Cliente $cliente, Collection $planes): void
    {
        $plan = $planes->random();

        // último día con acceso: ayer o antes, así la membresía queda vencida
        $diasDesdeVencimiento = fake()->boolean(70) ? random_int(1, 30) : random_int(31, 180);
        $vencimiento = CarbonImmutable::today()->subDays($diasDesdeVencimiento);
        $inicio = $vencimiento->subDays($plan->duracion_dias - 1);

        $this->encadenarMembresias(
            $cliente,
            $planes,
            $plan,
            $inicio,
            fake()->randomElement([1, 1, 1, 2, 2, 3]),
            ultimaACredito: fake()->boolean(12),
        );
    }
}
