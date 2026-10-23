<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\VentaDetalle;
use Carbon\CarbonImmutable;

class SuscripcionService
{
    /** Días antes del vencimiento en que una membresía vigente pasa a "por vencer". */
    public const DIAS_POR_VENCER = 7;

    /**
     * Subconsulta SQL con el último día de acceso de un socio entre sus
     * suscripciones activas o ya vencidas (las canceladas no cuentan; NULL si
     * nunca tuvo). Se usa como columna (`selectRaw`) y como filtro
     * (`whereRaw`) en la lista de socios.
     */
    public const VENCE_EL_SQL = "(select max(s.fecha_vencimiento) from suscripciones s where s.cliente_id = clientes.id and s.estado in ('activa', 'vencida'))";

    /**
     * Crea la suscripción de una línea de venta que vendió un plan. Nunca
     * extiende una existente (principio #10): si el socio ya tiene una
     * vigente, la nueva arranca el día siguiente a su vencimiento; si no,
     * hoy. Por cada unidad vendida se suma una vez `duracion_dias`, y
     * `fecha_vencimiento` es el último día con acceso (inclusive).
     */
    public function emitir(VentaDetalle $detalle, Plan $plan, Cliente $cliente, ?array $fechas = null): Suscripcion
    {
        if ($fechas) {
            // membresía personalizada con fechas elegidas: tal cual se pidieron
            $inicio = CarbonImmutable::parse($fechas['inicio']);
            $fin = CarbonImmutable::parse($fechas['fin']);
        } else {
            $inicio = $this->fechaDeInicio($cliente);
            $fin = $inicio->addDays($plan->duracion_dias * (int) $detalle->cantidad - 1);
        }

        return Suscripcion::create([
            'cliente_id' => $cliente->id,
            'plan_id' => $plan->id,
            'venta_detalle_id' => $detalle->id,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_vencimiento' => $fin->toDateString(),
            // una membresía cargada con fechas ya pasadas nace vencida
            'estado' => $fin->isBefore(CarbonImmutable::today()) ? 'vencida' : 'activa',
        ]);
    }

    /**
     * Primer día de una membresía nueva: hoy, o el día siguiente al
     * vencimiento de la que el cliente tiene vigente.
     */
    public function inicioPara(Cliente $cliente): CarbonImmutable
    {
        return $this->fechaDeInicio($cliente);
    }

    /**
     * Situación de un socio según el último día de acceso que le queda:
     * `vigente`, `por_vencer`, `vencida` o `sin_membresia`. Devuelve también
     * los días restantes (0 el último día; null si no hay membresía vigente).
     *
     * @return array{vigencia: string, dias_restantes: int|null}
     */
    public function vigenciaDe(?string $venceEl): array
    {
        if (! $venceEl) {
            return ['vigencia' => 'sin_membresia', 'dias_restantes' => null];
        }

        $dias = (int) CarbonImmutable::today()->diffInDays(CarbonImmutable::parse($venceEl), false);

        if ($dias < 0) {
            return ['vigencia' => 'vencida', 'dias_restantes' => null];
        }

        return [
            'vigencia' => $dias <= self::DIAS_POR_VENCER ? 'por_vencer' : 'vigente',
            'dias_restantes' => $dias,
        ];
    }

    private function fechaDeInicio(Cliente $cliente): CarbonImmutable
    {
        $hoy = CarbonImmutable::today();

        $ultimoVencimiento = Suscripcion::query()
            ->where('cliente_id', $cliente->id)
            ->where('estado', 'activa')
            ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->max('fecha_vencimiento');

        return $ultimoVencimiento
            ? CarbonImmutable::parse($ultimoVencimiento)->addDay()
            : $hoy;
    }
}
