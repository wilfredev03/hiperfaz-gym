<?php

namespace App\Http\Controllers;

use App\Mail\CierreCajaCerrado;
use App\Models\CierreCaja;
use App\Models\CierreCajaMetodo;
use App\Models\VentaPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CierreCajaController extends Controller
{
    /**
     * Pantalla de cierre de caja: el cierre abierto de hoy (si ya hubo
     * alguna venta) con su resumen en vivo, y el historial de cierres ya
     * cerrados con su reporte.
     */
    public function index(): Response
    {
        $cierreAbierto = CierreCaja::abiertoDeHoy()?->loadMissing('usuario:id,name');

        return Inertia::render('cierres_caja/Index', [
            'cierreAbierto' => $cierreAbierto,
            'resumenAbierto' => $cierreAbierto ? $this->resumenEnVivo($cierreAbierto) : null,
            'historial' => CierreCaja::where('estado', 'cerrado')
                ->with([
                    'usuario:id,name',
                    'detalleMetodos.metodoPago:id,nombre,moneda',
                    'ventasCredito.cliente:id,nombre',
                    'ventasCredito.tasaCambio:id,tasa',
                ])
                ->orderByDesc('fecha')
                ->orderByDesc('hora_cierre')
                ->paginate(15),
        ]);
    }

    /**
     * Cierra la caja abierta de hoy: no pide ningún monto digitado, solo
     * calcula el total de las ventas del día (en USD y su equivalente en
     * bolívares con la tasa del cierre), el desglose por método de pago (a
     * partir de venta_pagos) y guarda ese detalle en cierre_caja_metodos
     * antes de enviar el reporte por correo.
     */
    public function cerrarCaja(CierreCaja $cierre_caja): JsonResponse
    {
        if ($cierre_caja->estado !== 'abierto') {
            throw ValidationException::withMessages([
                'cierre_caja' => 'Este cierre de caja ya está cerrado.',
            ]);
        }

        $cierre_caja = DB::transaction(function () use ($cierre_caja) {
            $tasa = $this->tasaDelCierre($cierre_caja);

            foreach ($this->desglosePorMetodo($cierre_caja) as $fila) {
                CierreCajaMetodo::create([
                    'cierre_caja_id' => $cierre_caja->id,
                    'metodo_pago_id' => $fila->metodo_pago_id,
                    'monto' => round((float) $fila->monto, 4),
                    'monto_bs' => round((float) $fila->monto * $tasa, 4),
                ]);
            }

            $totalVentas = (float) $cierre_caja->ventas()->where('estado', '!=', 'anulada')->sum('total');
            $totalMembresias = $this->totalMembresias($cierre_caja);

            $cierre_caja->update([
                'hora_cierre' => now(),
                'total_ventas' => $totalVentas,
                'total_ventas_bs' => round($totalVentas * $tasa, 4),
                'total_membresias' => $totalMembresias,
                'total_membresias_bs' => round($totalMembresias * $tasa, 4),
                'estado' => 'cerrado',
            ]);

            return $cierre_caja->fresh()->load(
                'detalleMetodos.metodoPago',
                'ventasCredito.cliente:id,nombre',
                'ventasCredito.tasaCambio:id,tasa',
                'usuario',
            );
        });

        $this->enviarReportePorCorreo($cierre_caja);

        return response()->json($cierre_caja);
    }

    /**
     * Resumen en vivo del cierre todavía abierto: lo mismo que verá el
     * reporte final, calculado sobre la marcha (sin persistir nada) para
     * que el usuario vea qué va a cerrar antes de confirmar.
     */
    private function resumenEnVivo(CierreCaja $cierre): array
    {
        $tasa = $this->tasaDelCierre($cierre);
        $totalVentas = (float) $cierre->ventas()->where('estado', '!=', 'anulada')->sum('total');
        $totalMembresias = $this->totalMembresias($cierre);

        return [
            'total_ventas' => $totalVentas,
            'total_ventas_bs' => round($totalVentas * $tasa, 4),
            'total_membresias' => $totalMembresias,
            'total_membresias_bs' => round($totalMembresias * $tasa, 4),
            'desglose_metodos' => $this->desglosePorMetodo($cierre)->values(),
            'ventas_credito' => $cierre->ventasCredito()
                ->with(['cliente:id,nombre', 'tasaCambio:id,tasa'])
                ->orderBy('created_at')
                ->get(['id', 'numero_venta', 'cliente_id', 'tasa_cambio_id', 'total', 'estado', 'created_at']),
        ];
    }

    /**
     * Cuánto de lo facturado en el cierre son membresías (las líneas de venta
     * de un plan); el resto son productos. El descuento de una venta se
     * reparte a prorrata entre sus líneas, así la suma de membresías más
     * productos siempre da `total_ventas`. Las ventas anuladas no cuentan.
     */
    private function totalMembresias(CierreCaja $cierre): float
    {
        return round((float) DB::table('venta_detalle as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->join('planes as pl', 'pl.producto_id', '=', 'd.producto_id')
            ->where('v.cierre_caja_id', $cierre->id)
            ->where('v.estado', '!=', 'anulada')
            ->selectRaw('COALESCE(SUM(d.subtotal * v.total / NULLIF(v.subtotal, 0)), 0) as total')
            ->value('total'), 4);
    }

    /**
     * venta_pagos.monto siempre está en USD, sea cual sea la moneda del
     * método de pago. El equivalente en Bs se calcula con la tasa del
     * propio cierre (la vigente el día en que ese dinero entró a caja),
     * nunca con la tasa de hoy si se está viendo un cierre ya cerrado.
     */
    private function desglosePorMetodo(CierreCaja $cierre): Collection
    {
        $tasa = $this->tasaDelCierre($cierre);

        return VentaPago::where('venta_pagos.cierre_caja_id', $cierre->id)
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->where('ventas.estado', '!=', 'anulada')
            ->select(
                'metodos_pago.id as metodo_pago_id',
                'metodos_pago.nombre',
                'metodos_pago.moneda',
                DB::raw('SUM(venta_pagos.monto) as monto'),
            )
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'metodos_pago.moneda')
            ->orderBy('metodos_pago.nombre')
            ->get()
            ->map(function ($fila) use ($tasa) {
                $fila->monto_bs = round((float) $fila->monto * $tasa, 4);

                return $fila;
            });
    }

    private function tasaDelCierre(CierreCaja $cierre): float
    {
        return (float) ($cierre->tasaCambio?->tasa ?? 0);
    }

    /**
     * El envío del correo nunca puede tumbar el cierre: si el SMTP falla
     * (credenciales sin configurar, sin conexión, etc.) el cierre ya quedó
     * guardado y solo se registra el error en el log.
     */
    private function enviarReportePorCorreo(CierreCaja $cierre_caja): void
    {
        $destinatario = config('services.cierre_caja.email_notificacion');

        if (! $destinatario) {
            return;
        }

        try {
            Mail::to($destinatario)->send(new CierreCajaCerrado($cierre_caja));
            $cierre_caja->update(['correo_enviado_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo del cierre de caja.', [
                'cierre_caja_id' => $cierre_caja->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
