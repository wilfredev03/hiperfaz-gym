<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\RegistraMembresiasHistoricas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración: 200 clientes, cada uno con 1 a 3 membresías de planes
 * al azar, la mayoría vigentes (algunas por vencer) y el resto ya vencidas.
 * Ver `RegistraMembresiasHistoricas` para cómo se arma cada venta.
 *
 * Requiere los planes de `GimnasioMenuSeeder` y los métodos de `MetodoPagoSeeder`.
 * No está en `DatabaseSeeder`; se corre a mano y cada corrida agrega otros 200:
 * `php artisan db:seed --class=ClientesMembresiasSeeder`.
 */
class ClientesMembresiasSeeder extends Seeder
{
    use RegistraMembresiasHistoricas;

    private const CANTIDAD = 200;

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

    /** @param  Collection<int, \App\Models\Plan>  $planes */
    private function sembrar(Cliente $cliente, Collection $planes): void
    {
        $hoy = CarbonImmutable::today();
        $plan = $planes->random();
        $vigente = fake()->boolean(70);

        $inicio = $vigente
            ? $hoy->subDays(random_int(0, $plan->duracion_dias - 1))
            : $hoy->subDays($plan->duracion_dias + random_int(1, 60));

        $this->encadenarMembresias(
            $cliente,
            $planes,
            $plan,
            $inicio,
            fake()->randomElement([1, 1, 1, 1, 1, 1, 1, 2, 2, 3]),
            // solo una membresía vigente puede quedar a crédito
            ultimaACredito: $vigente && fake()->boolean(10),
        );
    }
}
