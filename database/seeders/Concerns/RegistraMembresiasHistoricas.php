<?php

namespace Database\Seeders\Concerns;

use App\Models\CierreCaja;
use App\Models\CierreCajaMetodo;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\TasaCambio;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lo común de los seeders de demostración que cargan membresías con fecha
 * pasada. Cada membresía nace de una venta real (venta + detalle + pago +
 * suscripción) ligada al cierre de caja de ese día, para que Clientes, Por
 * vencer, Membresías vendidas y Cierre de caja muestren datos coherentes.
 * No se usa `VentaService` porque siempre vende "hoy".
 */
trait RegistraMembresiasHistoricas
{
    /** @var array<string, CierreCaja> */
    private array $cierres = [];

    /** @var array<string, TasaCambio> */
    private array $tasas = [];

    private User $usuario;

    /** @var Collection<int, MetodoPago> */
    private Collection $metodos;

    /**
     * Carga los planes y métodos de pago que hacen falta. Devuelve los planes
     * del catálogo, o null (avisando) si falta algo que sembrar antes.
     *
     * @return Collection<int, Plan>|null
     */
    private function prepararMembresias(): ?Collection
    {
        $planes = Plan::with('producto')
            ->where('personalizado', false)
            ->get()
            ->filter(fn (Plan $plan) => $plan->producto?->activo)
            ->values();

        $this->metodos = MetodoPago::where('activo', true)->get();

        if ($planes->isEmpty() || $this->metodos->isEmpty()) {
            $this->command?->error('Faltan planes o métodos de pago: corre antes GimnasioMenuSeeder y MetodoPagoSeeder.');

            return null;
        }

        $this->usuario = User::where('rol', 'admin')->first() ?? User::first() ?? User::factory()->create();

        return $planes;
    }

    /**
     * Encadena las membresías del cliente hacia atrás desde la más reciente,
     * que arranca en `$inicioUltima`: cada renovación empieza el día
     * siguiente a que vence la anterior. Solo la más reciente puede quedar a
     * crédito.
     *
     * @param  Collection<int, Plan>  $planes
     */
    private function encadenarMembresias(Cliente $cliente, Collection $planes, Plan $planUltima, CarbonImmutable $inicioUltima, int $cantidad, bool $ultimaACredito): void
    {
        $plan = $planUltima;
        $inicio = $inicioUltima;
        $tramos = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $tramos[] = [$plan, $inicio];
            $plan = $planes->random();
            $inicio = $inicio->subDays($plan->duracion_dias);
        }

        // se registran en orden cronológico; el índice 0 es la más reciente
        foreach (array_reverse($tramos, true) as $indice => [$planTramo, $inicioTramo]) {
            $this->registrarMembresia($cliente, $planTramo, $inicioTramo, $indice === 0 && $ultimaACredito);
        }
    }

    private function registrarMembresia(Cliente $cliente, Plan $plan, CarbonImmutable $inicio, bool $aCredito): void
    {
        $hoy = CarbonImmutable::today();
        $precio = (float) $plan->producto->precio_venta;
        $cierre = $this->cierreDe($inicio);

        $venta = Venta::create([
            'numero_venta' => 'V-'.str_pad((string) ((Venta::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT),
            'cliente_id' => $cliente->id,
            'usuario_id' => $this->usuario->id,
            'tasa_cambio_id' => $cierre->tasa_cambio_id,
            'cierre_caja_id' => $cierre->id,
            'subtotal' => $precio,
            'descuento' => 0,
            'total' => $precio,
            'es_credito' => $aCredito,
            'monto_recibido' => $aCredito ? null : $precio,
            'vuelto' => $aCredito ? null : 0,
            'estado' => $aCredito ? 'pendiente' : 'pagada',
            'fecha' => $inicio->toDateString(),
        ]);

        $detalle = VentaDetalle::create([
            'venta_id' => $venta->id,
            'producto_id' => $plan->producto_id,
            'cantidad' => 1,
            'precio_unitario' => $precio,
            'costo_unitario_momento' => $plan->producto->costo_promedio ?? 0,
            'subtotal' => $precio,
        ]);

        if ($aCredito) {
            $cliente->increment('saldo_pendiente', $precio);
        } else {
            $metodo = $this->metodos->random();

            VentaPago::create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $metodo->id,
                'cierre_caja_id' => $cierre->id,
                'monto' => $precio,
                'referencia' => $metodo->requiere_referencia ? fake()->numerify('######') : null,
                'fecha_pago' => $inicio->setTime(random_int(7, 20), random_int(0, 59)),
            ]);
        }

        $vencimiento = $inicio->addDays($plan->duracion_dias - 1);

        Suscripcion::create([
            'cliente_id' => $cliente->id,
            'plan_id' => $plan->id,
            'venta_detalle_id' => $detalle->id,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'estado' => $vencimiento->isBefore($hoy) ? 'vencida' : 'activa',
        ]);
    }

    /** Caja del día: abierta si es hoy, cerrada si es un día pasado. */
    private function cierreDe(CarbonImmutable $fecha): CierreCaja
    {
        $dia = $fecha->toDateString();
        $esHoy = $fecha->isSameDay(CarbonImmutable::today());

        return $this->cierres[$dia] ??= CierreCaja::firstOrCreate(
            ['fecha' => $dia],
            [
                'usuario_id' => $this->usuario->id,
                'tasa_cambio_id' => $this->tasaDe($fecha)->id,
                'hora_apertura' => $fecha->setTime(8, 0),
                'hora_cierre' => $esHoy ? null : $fecha->setTime(20, 0),
                'total_ventas' => 0,
                'estado' => $esHoy ? 'abierto' : 'cerrado',
            ],
        );
    }

    private function tasaDe(CarbonImmutable $fecha): TasaCambio
    {
        $dia = $fecha->toDateString();
        $esHoy = $fecha->isSameDay(CarbonImmutable::today());

        return $this->tasas[$dia] ??= TasaCambio::whereDate('fecha', $dia)->orderByDesc('activa')->first()
            ?? TasaCambio::create([
                'fecha' => $dia,
                // la tasa sube con el tiempo: más baja mientras más atrás
                'tasa' => round(max(1, 180 - $fecha->diffInDays(CarbonImmutable::today()) * 0.1), 4),
                'tipo' => 'automatica',
                'usuario_id' => null,
                'activa' => $esHoy,
            ]);
    }

    /**
     * Las cajas cerradas guardan lo cobrado ese día (todo membresías aquí),
     * en USD y en Bs con la tasa del cierre, y el desglose por método, igual
     * que lo haría el cierre real. Se calcula sobre todas las ventas de la
     * caja, no solo las sembradas aquí.
     */
    private function totalizarCierres(): void
    {
        foreach ($this->cierres as $cierre) {
            if ($cierre->estado !== 'cerrado') {
                continue;
            }

            $tasa = (float) (TasaCambio::find($cierre->tasa_cambio_id)?->tasa ?? 0);
            $total = (float) Venta::where('cierre_caja_id', $cierre->id)->where('estado', '!=', 'anulada')->sum('total');

            $cierre->update([
                'total_ventas' => $total,
                'total_ventas_bs' => round($total * $tasa, 4),
                'total_membresias' => $total,
                'total_membresias_bs' => round($total * $tasa, 4),
            ]);

            CierreCajaMetodo::where('cierre_caja_id', $cierre->id)->delete();

            VentaPago::where('cierre_caja_id', $cierre->id)
                ->selectRaw('metodo_pago_id, SUM(monto) as monto')
                ->groupBy('metodo_pago_id')
                ->get()
                ->each(fn ($fila) => CierreCajaMetodo::create([
                    'cierre_caja_id' => $cierre->id,
                    'metodo_pago_id' => $fila->metodo_pago_id,
                    'monto' => $fila->monto,
                    'monto_bs' => round((float) $fila->monto * $tasa, 4),
                ]));
        }
    }
}
