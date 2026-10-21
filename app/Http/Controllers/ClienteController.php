<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Models\Venta;
use App\Services\CobranzaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    private const FILTROS = ['todos', 'con_deuda'];

    private const FILTRO_INICIAL = 'con_deuda';

    /**
     * Cuentas por cobrar: todo lo que se fía vive aquí. Sin `cliente`: la lista
     * (filtrable por `busqueda` y `filtro`; por defecto, solo los clientes con
     * deuda), donde cada cliente trae sus ventas por cobrar para desplegarlas
     * y abonar ahí mismo. Con `cliente`: la ficha completa — historial con
     * detalle, datos de contacto, venta y abonos. `vista` abre la ficha en la
     * pestaña pedida.
     */
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('busqueda', ''));
        $filtro = in_array($request->query('filtro'), self::FILTROS, true) ? $request->query('filtro') : self::FILTRO_INICIAL;

        $clientes = Cliente::query()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($query) use ($busqueda) {
                    $query->where('nombre', 'ilike', "%{$busqueda}%")
                        ->orWhere('documento', 'ilike', "%{$busqueda}%");
                });
            })
            ->when($filtro === 'con_deuda', fn ($query) => $query
                ->where('saldo_pendiente', '>', 0)
                ->orderByDesc('saldo_pendiente'))
            ->orderBy('nombre')
            ->with(['ventasPendientes' => fn ($query) => $query
                ->withSum('pagos as total_pagado', 'monto')
                ->orderBy('fecha')
                ->orderBy('id')])
            ->paginate(20, ['id', 'nombre', 'documento', 'telefono', 'saldo_pendiente', 'activo'])
            ->withQueryString()
            ->through(fn (Cliente $cliente) => [
                ...$cliente->only(['id', 'nombre', 'documento', 'telefono', 'saldo_pendiente', 'activo']),
                'ventas_pendientes' => $cliente->ventasPendientes->map(fn (Venta $venta) => $this->cabeceraDe($venta))->values(),
            ]);

        $seleccionado = $request->filled('cliente')
            ? Cliente::find($request->integer('cliente'))
            : null;

        return Inertia::render('clientes/Index', [
            'clientes' => $clientes,
            'busqueda' => $busqueda,
            'filtro' => $filtro,
            'resumen' => [
                'clientes_con_deuda' => Cliente::where('saldo_pendiente', '>', 0)->count(),
                'total_por_cobrar' => round((float) Cliente::where('saldo_pendiente', '>', 0)->sum('saldo_pendiente'), 4),
            ],
            'seleccionado' => $seleccionado ? $this->cuentaDe($seleccionado) : null,
            'vista' => in_array($request->query('vista'), ['venta', 'datos'], true) ? $request->query('vista') : 'cuenta',
            'metodosPago' => MetodoPago::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'moneda', 'requiere_referencia']),
            'tasaCambio' => TasaCambio::vigenteHoy()?->only(['id', 'tasa', 'fecha']),
            // el catálogo solo se necesita al abrir "Nueva venta": el cliente lo
            // pide con una recarga parcial (only: ['productos'])
            'productos' => Inertia::optional(fn () => Producto::query()
                ->where('activo', true)
                ->with(['categoria:id,nombre', 'unidadMedida:id,nombre,abreviatura'])
                ->orderBy('nombre')
                ->get(['id', 'categoria_id', 'unidad_medida_id', 'nombre', 'precio_venta', 'stock_actual', 'controla_inventario', 'imagen'])),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClienteRequest $request): RedirectResponse
    {
        Cliente::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente creado.')]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente actualizado.')]);

        return back();
    }

    /**
     * Abono general: el monto se reparte entre las ventas pendientes del
     * cliente, de la más antigua a la más reciente.
     */
    public function abonar(Cliente $cliente, Request $request, CobranzaService $cobranza): RedirectResponse
    {
        $datos = $request->validate([
            'metodo_pago_id' => ['required', 'exists:metodos_pago,id'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        $pagos = $cobranza->abonarCliente(
            $cliente,
            (int) $datos['metodo_pago_id'],
            (float) $datos['monto'],
            $datos['referencia'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Abono registrado en :count venta.|Abono registrado en :count ventas.', $pagos->count()),
        ]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente eliminado.')]);

        return to_route('clientes.index');
    }

    /**
     * La cuenta de un cliente: ficha, historial de ventas (no anuladas: las
     * 100 más recientes más todas las que están por cobrar) con lo pagado y el saldo de cada una, y el total
     * histórico comprado / fecha de última compra.
     *
     * @return array<string, mixed>
     */
    private function cuentaDe(Cliente $cliente): array
    {
        $consulta = fn () => $cliente->ventas()
            ->where('estado', '!=', 'anulada')
            ->withSum('pagos as total_pagado', 'monto')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        // las ventas por cobrar siempre van completas (el reparto de un abono
        // general depende de ellas), aunque sean más viejas que las 100 recientes
        $ventas = $consulta()->limit(100)->get()
            ->merge($consulta()->whereIn('estado', ['pendiente', 'parcial'])->get())
            ->unique('id')
            ->sortBy([['fecha', 'desc'], ['id', 'desc']])
            ->values()
            ->map(fn (Venta $venta) => $this->cabeceraDe($venta));

        $totales = $cliente->ventas()
            ->where('estado', '!=', 'anulada')
            ->selectRaw('COALESCE(SUM(total), 0) as total_comprado, MAX(fecha) as fecha_ultima_compra')
            ->first();

        return [
            'cliente' => $cliente,
            'ventas' => $ventas,
            'total_comprado' => round((float) $totales->total_comprado, 4),
            'fecha_ultima_compra' => $totales->fecha_ultima_compra
                ? substr((string) $totales->fecha_ultima_compra, 0, 10)
                : null,
        ];
    }

    /**
     * Cabecera de una venta para el módulo: total, lo pagado y el saldo.
     * Requiere `total_pagado` cargado con withSum sobre los pagos.
     *
     * @return array<string, mixed>
     */
    private function cabeceraDe(Venta $venta): array
    {
        $pagado = (float) ($venta->total_pagado ?? 0);

        return [
            'id' => $venta->id,
            'numero_venta' => $venta->numero_venta,
            'fecha' => $venta->fecha->toDateString(),
            'total' => $venta->total,
            'total_pagado' => round($pagado, 4),
            'estado' => $venta->estado,
            'es_credito' => $venta->es_credito,
            'saldo_pendiente' => in_array($venta->estado, ['pendiente', 'parcial'], true)
                ? round((float) $venta->total - $pagado, 4)
                : 0,
        ];
    }
}
