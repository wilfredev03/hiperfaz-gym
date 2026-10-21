<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembresiaVendidaController extends Controller
{
    private const PERIODOS = ['hoy', 'semana', 'mes', 'personalizado'];

    /**
     * Membresías vendidas en un período (por fecha de la venta): cada fila es
     * una suscripción. Es un alta si el socio no tenía ninguna suscripción
     * anterior y una renovación si ya tenía. Lo vendido sale de la línea de
     * venta que originó cada suscripción; las ventas anuladas no cuentan.
     */
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'periodo' => ['nullable', 'in:'.implode(',', self::PERIODOS)],
            'desde' => ['required_if:periodo,personalizado', 'nullable', 'date'],
            'hasta' => ['required_if:periodo,personalizado', 'nullable', 'date', 'after_or_equal:desde'],
        ]);

        $periodo = $data['periodo'] ?? 'hoy';
        [$desde, $hasta] = match ($periodo) {
            'semana' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'mes' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'personalizado' => [$data['desde'], $data['hasta']],
            default => [now()->toDateString(), now()->toDateString()],
        };

        $base = fn () => Suscripcion::query()
            ->join('venta_detalle', 'venta_detalle.id', '=', 'suscripciones.venta_detalle_id')
            ->join('ventas', 'ventas.id', '=', 'venta_detalle.venta_id')
            ->leftJoin('tasas_cambio', 'tasas_cambio.id', '=', 'ventas.tasa_cambio_id')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', '!=', 'anulada');

        $esAlta = 'NOT EXISTS (select 1 from suscripciones previa where previa.cliente_id = suscripciones.cliente_id and previa.id < suscripciones.id)';

        $resumen = $base()
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw("COUNT(*) FILTER (WHERE $esAlta) as altas")
            ->selectRaw('COALESCE(SUM(venta_detalle.subtotal), 0) as total_usd')
            ->selectRaw('COALESCE(SUM(venta_detalle.subtotal * COALESCE(tasas_cambio.tasa, 0)), 0) as total_bs')
            ->first();

        $membresias = $base()
            ->join('clientes', 'clientes.id', '=', 'suscripciones.cliente_id')
            ->join('planes', 'planes.id', '=', 'suscripciones.plan_id')
            ->join('productos', 'productos.id', '=', 'planes.producto_id')
            ->select([
                'suscripciones.id',
                'suscripciones.cliente_id',
                'clientes.nombre as socio',
                'productos.nombre as plan',
                'suscripciones.fecha_inicio',
                'suscripciones.fecha_vencimiento',
                'suscripciones.estado',
                'ventas.id as venta_id',
                'ventas.numero_venta',
                'ventas.fecha as fecha_venta',
                'ventas.estado as estado_venta',
                'venta_detalle.subtotal as monto',
            ])
            ->selectRaw("$esAlta as es_alta")
            ->selectRaw('venta_detalle.subtotal * COALESCE(tasas_cambio.tasa, 0) as monto_bs')
            ->orderByDesc('ventas.fecha')
            ->orderByDesc('suscripciones.id')
            ->paginate(20)
            ->withQueryString();

        $cantidad = (int) $resumen->cantidad;

        return Inertia::render('membresias_vendidas/Index', [
            'membresias' => $membresias,
            'resumen' => [
                'cantidad' => $cantidad,
                'altas' => (int) $resumen->altas,
                'renovaciones' => $cantidad - (int) $resumen->altas,
                'total_usd' => round((float) $resumen->total_usd, 2),
                'total_bs' => round((float) $resumen->total_bs, 2),
            ],
            'filtros' => ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta],
        ]);
    }
}
