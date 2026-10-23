<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\TasaCambio;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class MembresiaService
{
    public function __construct(
        private VentaService $ventas,
        private SuscripcionService $suscripciones,
    ) {}

    /**
     * Activa (o renueva) la membresía de un cliente: es una venta de un solo
     * plan que se paga completa con un método de pago o se deja a crédito.
     * La suscripción la crea `VentaService` dentro de la misma transacción.
     *
     * Con `personalizada` no se usa un plan del catálogo: el precio y la
     * duración los pone quien cobra, por cantidad de días (`modo = dias`, que
     * empieza hoy o al vencer la vigente) o por un rango de fechas (`modo = fechas`).
     *
     * @param  array{plan_id?: int|string|null, personalizada?: bool, precio?: float|string|null, modo?: string|null, dias?: int|string|null, fecha_inicio?: string|null, fecha_fin?: string|null, es_credito?: bool, metodo_pago_id?: int|string|null, referencia?: string|null}  $datos
     */
    public function activar(Cliente $cliente, array $datos): Venta
    {
        $esCredito = (bool) ($datos['es_credito'] ?? false);
        $linea = ! empty($datos['personalizada'])
            ? $this->lineaPersonalizada($cliente, $datos)
            : $this->lineaDePlan($datos);

        return $this->ventas->registrar([
            'cliente_id' => $cliente->id,
            'es_credito' => $esCredito,
            'items' => [$linea['item']],
            'pagos' => $esCredito ? [] : [[
                'metodo_pago_id' => $datos['metodo_pago_id'],
                'monto' => $linea['precio'],
                'referencia' => $datos['referencia'] ?? null,
            ]],
            'monto_recibido' => $esCredito ? null : $this->montoRecibidoUsd($datos, (float) $linea['precio']),
        ]);
    }

    /**
     * Lo que entregó el cliente, en USD, para que `VentaService` calcule el
     * vuelto. Solo cuenta en efectivo/divisas y viene en la moneda del método
     * (Bs o USD); los Bs se comparan contra el precio ya convertido y redondeado
     * a 2 decimales, que es lo que se le mostró a quien cobra. Sin monto, o
     * en otro método, se asume que pagó justo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function montoRecibidoUsd(array $datos, float $precio): float
    {
        $recibido = $datos['monto_recibido'] ?? null;
        $metodo = MetodoPago::find($datos['metodo_pago_id'] ?? null);

        if ($recibido === null || $recibido === '' || ! $metodo || ! str_contains(mb_strtolower($metodo->nombre), 'efectivo')) {
            return $precio;
        }

        $tasa = $metodo->moneda === 'VES' ? (float) TasaCambio::vigenteHoy()?->tasa : 1.0;
        if ($tasa <= 0) {
            return $precio; // sin tasa de hoy `VentaService` rechaza la venta
        }

        $total = round($precio * $tasa, 2);
        $diferencia = round((float) $recibido - $total, 2);

        if ($diferencia < 0) {
            throw ValidationException::withMessages([
                'monto_recibido' => 'El monto recibido no cubre el total de la membresía.',
            ]);
        }

        return round($precio + $diferencia / $tasa, 4);
    }

    /** La suscripción que dejó una venta de membresía. */
    public function suscripcionDe(Venta $venta): ?Suscripcion
    {
        return Suscripcion::whereIn('venta_detalle_id', $venta->detalles()->select('id'))->first();
    }

    /**
     * @return array{item: array<string, mixed>, precio: float|string}
     */
    private function lineaDePlan(array $datos): array
    {
        $plan = Plan::with('producto')->findOrFail($datos['plan_id']);

        if (! $plan->producto->activo || $plan->personalizado) {
            throw ValidationException::withMessages(['plan_id' => 'Ese plan no está disponible.']);
        }

        return [
            'item' => ['producto_id' => $plan->producto_id, 'cantidad' => 1],
            'precio' => $plan->producto->precio_venta,
        ];
    }

    /**
     * @return array{item: array<string, mixed>, precio: float|string}
     */
    private function lineaPersonalizada(Cliente $cliente, array $datos): array
    {
        $plan = Plan::personalizado();

        if (($datos['modo'] ?? null) === 'fechas') {
            $inicio = CarbonImmutable::parse($datos['fecha_inicio']);
            $fin = CarbonImmutable::parse($datos['fecha_fin']);
        } else {
            $inicio = $this->suscripciones->inicioPara($cliente);
            $fin = $inicio->addDays((int) $datos['dias'] - 1);
        }

        return [
            'item' => [
                'producto_id' => $plan->producto_id,
                'cantidad' => 1,
                'precio_unitario' => $datos['precio'],
                'suscripcion' => ['inicio' => $inicio->toDateString(), 'fin' => $fin->toDateString()],
            ],
            'precio' => $datos['precio'],
        ];
    }
}
