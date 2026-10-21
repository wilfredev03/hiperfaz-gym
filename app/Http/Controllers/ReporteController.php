<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    private const UNIDADES_AGRUPACION = [
        'dia' => 'day',
        'semana' => 'week',
        'mes' => 'month',
    ];

    /**
     * Total de ventas y cantidad de ventas por período (día/semana/mes),
     * agrupado con DATE_TRUNC sobre ventas.fecha (columna indexada).
     */
    public function ventasPorPeriodo(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rangoFechas($request);
        $unidad = $this->unidadAgrupacion($request);

        $ventas = Venta::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'anulada')
            ->select(DB::raw("DATE_TRUNC('{$unidad}', fecha) as periodo"))
            ->selectRaw('COUNT(*) as cantidad_ventas')
            ->selectRaw('COALESCE(SUM(total), 0) as total_ventas')
            ->selectRaw('COALESCE(SUM(descuento), 0) as total_descuentos')
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        return response()->json($ventas);
    }

    /**
     * Desglosa el monto recibido por método de pago para las ventas del
     * período, uniendo venta_pagos con ventas por venta_id (índice existente)
     * y filtrando por ventas.fecha (también indexada).
     */
    public function desglosePorMetodoPago(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rangoFechas($request);

        $desglose = MetodoPago::query()
            ->join('venta_pagos', 'venta_pagos.metodo_pago_id', '=', 'metodos_pago.id')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', '!=', 'anulada')
            ->select('metodos_pago.id as metodo_pago_id', 'metodos_pago.nombre', 'metodos_pago.moneda')
            ->selectRaw('COUNT(venta_pagos.id) as cantidad_pagos')
            ->selectRaw('COALESCE(SUM(venta_pagos.monto), 0) as total_recibido')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'metodos_pago.moneda')
            ->orderByDesc('total_recibido')
            ->get();

        return response()->json($desglose);
    }

    /**
     * Margen bruto del período (ingresos - costo_unitario_momento * cantidad),
     * uniendo venta_detalle con ventas por venta_id (índice existente).
     */
    public function margenGanancia(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rangoFechas($request);

        $margen = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalle.venta_id')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', '!=', 'anulada')
            ->selectRaw('COALESCE(SUM(venta_detalle.subtotal), 0) as total_ingresos')
            ->selectRaw('COALESCE(ROUND(SUM(venta_detalle.costo_unitario_momento * venta_detalle.cantidad), 4), 0) as total_costo')
            ->selectRaw('COALESCE(ROUND(SUM(venta_detalle.subtotal - (venta_detalle.costo_unitario_momento * venta_detalle.cantidad)), 4), 0) as margen_bruto')
            ->first();

        return response()->json($margen);
    }

    /**
     * Productos con mayor cantidad vendida en el período, uniendo
     * venta_detalle con ventas y productos por sus llaves foráneas indexadas.
     */
    public function productosMasVendidos(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rangoFechas($request);
        $limite = (int) ($request->validate([
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])['limite'] ?? 10);

        $productos = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalle.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_detalle.producto_id')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', '!=', 'anulada')
            ->select('productos.id as producto_id', 'productos.nombre')
            ->selectRaw('SUM(venta_detalle.cantidad) as cantidad_vendida')
            ->selectRaw('SUM(venta_detalle.subtotal) as total_ingresos')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad_vendida')
            ->limit($limite)
            ->get();

        return response()->json($productos);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rangoFechas(Request $request): array
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        return [$data['desde'], $data['hasta']];
    }

    private function unidadAgrupacion(Request $request): string
    {
        $agrupacion = $request->validate([
            'agrupacion' => ['sometimes', 'in:dia,semana,mes'],
        ])['agrupacion'] ?? 'dia';

        return self::UNIDADES_AGRUPACION[$agrupacion];
    }
}
