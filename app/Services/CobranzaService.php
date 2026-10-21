<?php

namespace App\Services;

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra abonos a ventas a crédito. Un abono nunca reabre ni edita la
 * venta: inserta filas nuevas en venta_pagos con el cierre de HOY (principio
 * #6) y mantiene el saldo cacheado del cliente.
 */
class CobranzaService
{
    /**
     * El cierre de caja abierto de hoy, o error de validación si no hay.
     */
    public function cierreParaAbonar(): CierreCaja
    {
        $cierre = CierreCaja::abiertoDeHoy();

        if (! $cierre) {
            throw ValidationException::withMessages([
                'cierre_caja' => 'No hay una caja abierta hoy para registrar el abono.',
            ]);
        }

        return $cierre;
    }

    /**
     * Abono a una venta puntual.
     */
    public function abonarVenta(Venta $venta, int $metodoPagoId, float $monto, ?string $referencia): VentaPago
    {
        $cierre = $this->cierreParaAbonar();

        return DB::transaction(function () use ($venta, $metodoPagoId, $monto, $referencia, $cierre) {
            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages([
                    'venta' => 'No se puede abonar a una venta anulada.',
                ]);
            }

            $saldoAntes = $venta->saldo_pendiente;

            if (round($monto - $saldoAntes, 4) > 0) {
                throw ValidationException::withMessages([
                    'monto' => 'El abono no puede ser mayor al saldo pendiente de la venta.',
                ]);
            }

            return $this->registrarPago($venta, $cierre, $metodoPagoId, $monto, $referencia, $saldoAntes);
        });
    }

    /**
     * Abono general de un cliente: se reparte entre sus ventas pendientes o
     * parciales de la más antigua a la más reciente. Devuelve un pago por
     * cada venta tocada.
     *
     * @return Collection<int, VentaPago>
     */
    public function abonarCliente(Cliente $cliente, int $metodoPagoId, float $monto, ?string $referencia): Collection
    {
        $cierre = $this->cierreParaAbonar();

        return DB::transaction(function () use ($cliente, $metodoPagoId, $monto, $referencia, $cierre) {
            $ventas = $cliente->ventasPendientes()
                ->orderBy('fecha')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $deudaTotal = round($ventas->sum(fn (Venta $venta) => $venta->saldo_pendiente), 4);

            if (round($monto - $deudaTotal, 4) > 0) {
                throw ValidationException::withMessages([
                    'monto' => 'El abono no puede ser mayor a la deuda total del cliente.',
                ]);
            }

            $restante = round($monto, 4);
            $pagos = collect();

            foreach ($ventas as $venta) {
                if ($restante <= 0) {
                    break;
                }

                $saldo = $venta->saldo_pendiente;
                $aplicado = min($restante, $saldo);

                $pagos->push($this->registrarPago($venta, $cierre, $metodoPagoId, $aplicado, $referencia, $saldo));

                $restante = round($restante - $aplicado, 4);
            }

            return $pagos;
        });
    }

    private function registrarPago(
        Venta $venta,
        CierreCaja $cierre,
        int $metodoPagoId,
        float $monto,
        ?string $referencia,
        float $saldoAntes,
    ): VentaPago {
        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'metodo_pago_id' => $metodoPagoId,
            'cierre_caja_id' => $cierre->id,
            'monto' => $monto,
            'referencia' => $referencia,
            'fecha_pago' => now(),
        ]);

        $venta->update(['estado' => round($saldoAntes - $monto, 4) <= 0 ? 'pagada' : 'parcial']);

        if ($venta->cliente_id) {
            Cliente::where('id', $venta->cliente_id)->decrement('saldo_pendiente', $monto);
        }

        return $pago;
    }
}
