<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula una venta sin editarla ni borrarla (principios #1, #2 y #10): la
 * venta, su detalle y sus pagos quedan intactos y solo cambia su estado a
 * `anulada`. Lo demás se corrige con filas nuevas: cada salida de
 * inventario se revierte con un movimiento `devolucion` en el ledger, las
 * suscripciones que originó pasan a `cancelada` y la deuda que la venta
 * dejó al cliente se descuenta de su saldo.
 *
 * Solo se anula mientras el dinero de la venta siga en una caja abierta:
 * un cierre ya cerrado (y enviado por correo) no se reescribe.
 */
class AnulacionVentaService
{
    public function anular(Venta $venta, string $motivo, ?int $usuarioId): Venta
    {
        return DB::transaction(function () use ($venta, $motivo, $usuarioId) {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);

            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages(['anular' => 'Esta venta ya está anulada.']);
            }

            $this->exigirCajasAbiertas($venta);

            foreach ($venta->detalles()->get() as $detalle) {
                $producto = Producto::lockForUpdate()->findOrFail($detalle->producto_id);

                if ($producto->controla_inventario) {
                    $this->devolverAlInventario($producto, (float) $detalle->cantidad, $venta, $usuarioId);
                }

                // renovar nunca modifica una suscripción; anular tampoco toca
                // sus fechas, solo su estado
                $detalle->suscripcion()->update(['estado' => 'cancelada']);
            }

            if ($venta->es_credito && $venta->cliente_id) {
                $deuda = max(0.0, $venta->saldo_pendiente);

                if ($deuda > 0) {
                    Cliente::where('id', $venta->cliente_id)->decrement('saldo_pendiente', $deuda);
                }
            }

            $venta->update([
                'estado' => 'anulada',
                'anulada_en' => now(),
                'anulada_por_id' => $usuarioId,
                'motivo_anulacion' => $motivo,
            ]);

            return $venta;
        });
    }

    /**
     * La caja donde se facturó la venta y las de cada pago (un abono puede
     * haber entrado otro día) deben seguir abiertas.
     */
    private function exigirCajasAbiertas(Venta $venta): void
    {
        $cierraronCaja = $venta->cierreCaja?->estado === 'cerrado'
            || $venta->pagos()->whereHas('cierreCaja', fn ($query) => $query->where('estado', 'cerrado'))->exists();

        if ($cierraronCaja) {
            throw ValidationException::withMessages([
                'anular' => 'No se puede anular: la caja donde se registró esta venta (o alguno de sus pagos) ya está cerrada.',
            ]);
        }
    }

    private function devolverAlInventario(Producto $producto, float $cantidad, Venta $venta, ?int $usuarioId): void
    {
        $stockAnterior = (float) $producto->stock_actual;
        $stockNuevo = round($stockAnterior + $cantidad, 3);

        MovimientoInventario::create([
            'producto_id' => $producto->id,
            'tipo' => 'devolucion',
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id,
            'usuario_id' => $usuarioId,
        ]);

        $producto->update(['stock_actual' => $stockNuevo]);
    }
}
