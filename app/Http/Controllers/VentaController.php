<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\AnulacionVentaService;
use App\Services\CobranzaService;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    private const PERIODOS = ['hoy', 'semana', 'mes', 'personalizado'];

    /**
     * "Ventas del día" es solo de productos: las membresías se ven en su
     * propio módulo. De cada venta cuenta únicamente la parte de productos
     * (`total_productos`): su total completo si no lleva membresías, o el
     * prorrateo de las líneas que no son plan si es una venta mixta. Las
     * ventas que solo llevan membresías no aparecen. Una venta sin líneas
     * (no debería existir) se trata como de productos.
     */
    private const TOTAL_PRODUCTOS_SQL = 'CASE WHEN COALESCE(lp.lineas_plan, 0) = 0 THEN ventas.total ELSE lp.subtotal_productos * ventas.total / NULLIF(ventas.subtotal, 0) END';

    private const CON_PRODUCTOS_SQL = '(COALESCE(lp.lineas_plan, 0) = 0 OR lp.subtotal_productos > 0)';

    /**
     * Panel de ventas: listado del período filtrado (por defecto, hoy) con un
     * resumen (total en USD/Bs, cantidad de ventas, unidades vendidas y
     * desglose por método de pago) calculado sobre el mismo rango de
     * ventas.fecha, que ya está indexada.
     */
    /**
     * Por venta: cuántas líneas son planes de membresía y cuánto suman las
     * demás (los productos).
     */
    private function lineasPorVenta(): \Illuminate\Database\Query\Builder
    {
        return DB::table('venta_detalle as d')
            ->leftJoin('planes as pl', 'pl.producto_id', '=', 'd.producto_id')
            ->groupBy('d.venta_id')
            ->selectRaw('d.venta_id, COUNT(pl.id) as lineas_plan, COALESCE(SUM(CASE WHEN pl.id IS NULL THEN d.subtotal END), 0) as subtotal_productos');
    }

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

        // las ventas anuladas no cuentan como dinero vendido, ni en el total
        // ni en el desglose por método de pago
        $resumen = Venta::query()
            ->leftJoinSub($this->lineasPorVenta(), 'lp', 'lp.venta_id', '=', 'ventas.id')
            ->leftJoin('tasas_cambio', 'tasas_cambio.id', '=', 'ventas.tasa_cambio_id')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', '!=', 'anulada')
            ->whereRaw(self::CON_PRODUCTOS_SQL)
            ->selectRaw('COUNT(*) as cantidad_ventas')
            ->selectRaw('COALESCE(SUM('.self::TOTAL_PRODUCTOS_SQL.'), 0) as total_usd')
            ->selectRaw('COALESCE(SUM(('.self::TOTAL_PRODUCTOS_SQL.') * COALESCE(tasas_cambio.tasa, 0)), 0) as total_bs')
            ->first();

        // el desglose muestra siempre todos los métodos activos, también los
        // que no movieron dinero en el período (en 0): la lista es el
        // catálogo configurado, no el resultado de las ventas, así que no
        // cambia de tamaño ni de orden al cambiar el filtro. El subquery
        // suma solo los pagos del rango y el leftJoinSub deja en NULL —y de
        // ahí en 0— los métodos sin movimiento.
        // `venta_pagos.monto` siempre está en USD, sea cual sea la moneda del
        // método. El equivalente en Bs se calcula con la tasa que quedó
        // guardada en cada venta, nunca con la tasa de hoy: una venta pasada
        // no se recalcula con valores actuales (principio #1).
        // en una venta mixta (productos + membresía) cada pago se prorratea:
        // solo la parte que corresponde a los productos entra en este desglose
        $parteProductos = '(('.self::TOTAL_PRODUCTOS_SQL.') / NULLIF(ventas.total, 0))';

        $pagosDelPeriodo = VentaPago::query()
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->leftJoinSub($this->lineasPorVenta(), 'lp', 'lp.venta_id', '=', 'ventas.id')
            ->leftJoin('tasas_cambio', 'tasas_cambio.id', '=', 'ventas.tasa_cambio_id')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', '!=', 'anulada')
            ->whereRaw(self::CON_PRODUCTOS_SQL)
            ->groupBy('venta_pagos.metodo_pago_id')
            ->select('venta_pagos.metodo_pago_id')
            ->selectRaw("SUM(venta_pagos.monto * COALESCE($parteProductos, 1)) as total")
            ->selectRaw("SUM(venta_pagos.monto * COALESCE($parteProductos, 1) * COALESCE(tasas_cambio.tasa, 0)) as total_bs");

        $porMetodoPago = MetodoPago::query()
            ->leftJoinSub($pagosDelPeriodo, 'pagos_periodo', 'pagos_periodo.metodo_pago_id', '=', 'metodos_pago.id')
            ->where('metodos_pago.activo', true)
            ->orderBy('metodos_pago.orden')
            ->orderBy('metodos_pago.nombre')
            ->get([
                'metodos_pago.id',
                'metodos_pago.nombre',
                'metodos_pago.moneda',
                DB::raw('COALESCE(pagos_periodo.total, 0) as total'),
                DB::raw('COALESCE(pagos_periodo.total_bs, 0) as total_bs'),
            ]);

        $ventas = Venta::query()
            ->leftJoinSub($this->lineasPorVenta(), 'lp', 'lp.venta_id', '=', 'ventas.id')
            ->select('ventas.*')
            ->selectRaw(self::TOTAL_PRODUCTOS_SQL.' as total_productos')
            ->selectRaw('COALESCE(lp.lineas_plan, 0) > 0 as incluye_membresia')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->whereRaw(self::CON_PRODUCTOS_SQL)
            ->with([
                'tasaCambio:id,tasa',
                'pagos.metodoPago:id,nombre,moneda',
            ])
            ->orderByDesc('ventas.fecha')
            ->orderByDesc('ventas.created_at')
            ->paginate(20)
            ->withQueryString();

        $cantidadVentas = (int) $resumen->cantidad_ventas;
        $totalUsd = round((float) $resumen->total_usd, 2);

        return Inertia::render('ventas/Index', [
            'ventas' => $ventas,
            'resumen' => [
                'cantidad_ventas' => $cantidadVentas,
                'total_usd' => $totalUsd,
                'total_bs' => round((float) $resumen->total_bs, 2),
                'por_metodo_pago' => $porMetodoPago,
            ],
            'filtros' => [
                'periodo' => $periodo,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ]);
    }

    /**
     * Pantalla de nueva venta: carga el catálogo, clientes, métodos de pago
     * activos y la tasa de cambio vigente. No hace falta abrir caja a mano
     * antes: store() abre una automática si nadie lo hizo todavía.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('ventas/Create', [
            'productos' => Producto::query()
                ->sinPlan()
                ->where('activo', true)
                ->with(['categoria:id,nombre', 'unidadMedida:id,nombre,abreviatura', 'plan:id,producto_id,duracion_dias'])
                ->orderBy('nombre')
                ->get(['id', 'categoria_id', 'unidad_medida_id', 'nombre', 'precio_venta', 'stock_actual', 'controla_inventario', 'imagen']),
            'clientes' => Cliente::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'documento', 'saldo_pendiente']),
            'metodosPago' => MetodoPago::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'moneda', 'requiere_referencia']),
            'tasaCambio' => TasaCambio::actual(),
        ]);
    }

    /**
     * Detalle de una venta, consumido por el panel lateral de
     * ventas/Index.vue (no es una página Inertia propia).
     */
    public function show(Venta $venta): JsonResponse
    {
        $venta->load([
            'cliente:id,nombre,documento,telefono',
            'usuario:id,name',
            'anuladaPor:id,name',
            'tasaCambio:id,tasa,fecha',
            'detalles.producto:id,nombre',
            'detalles.producto.plan:id,producto_id',
            'pagos.metodoPago:id,nombre,moneda',
        ])->append('saldo_pendiente');

        // el panel separa los productos de las membresías de la misma venta
        $venta->detalles->each(fn (VentaDetalle $detalle) => $detalle->setAttribute('es_membresia', $detalle->producto->plan !== null));

        return response()->json($venta);
    }

    public function store(StoreVentaRequest $request, VentaService $ventas): JsonResponse
    {
        $data = $request->validated();

        // las membresías se activan desde Clientes (ahí se elige el plan y se
        // cobra), nunca desde el punto de venta
        $hayPlan = Plan::whereIn('producto_id', array_column($data['items'], 'producto_id'))->exists();

        if ($hayPlan) {
            throw ValidationException::withMessages([
                'items' => 'Las membresías no se venden aquí: actívalas desde el módulo de Clientes.',
            ]);
        }

        $venta = $ventas->registrar($data);

        return response()->json($venta->load(['detalles.producto', 'pagos.metodoPago']), 201);
    }

    /**
     * Registra el abono de un cliente a una venta a crédito ya existente.
     * No modifica la venta original, solo agrega un pago nuevo con el cierre de HOY.
     */
    public function abonar(Venta $venta, Request $request, CobranzaService $cobranza): JsonResponse
    {
        $request->validate([
            'metodo_pago_id' => ['required', 'exists:metodos_pago,id'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        $pago = $cobranza->abonarVenta(
            $venta,
            (int) $request->metodo_pago_id,
            (float) $request->monto,
            $request->referencia,
        );

        return response()->json($pago->load('metodoPago'), 201);
    }

    /**
     * Anula una venta mal hecha (solo admin): devuelve el stock, cancela la
     * membresía que originó y deja la venta marcada con quién y por qué.
     */
    public function anular(Venta $venta, Request $request, AnulacionVentaService $anulacion): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $anulacion->anular($venta, $data['motivo'], $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venta anulada.')]);

        return back();
    }
}
