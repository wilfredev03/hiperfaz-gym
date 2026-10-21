<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrdenCompraRequest;
use App\Models\MovimientoInventario;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TasaCambio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrdenCompraController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
        ]);

        $ordenes = OrdenCompra::query()
            ->when($data['proveedor_id'] ?? null, fn ($query, $proveedorId) => $query->where('proveedor_id', $proveedorId))
            ->with(['proveedor:id,nombre', 'tasaCambio:id,tasa'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('ordenes_compra/Index', [
            'ordenes' => $ordenes,
            'proveedores' => Proveedor::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => [
                'proveedor_id' => $data['proveedor_id'] ?? null,
            ],
        ]);
    }

    /**
     * El costo lo fija el proveedor en cada compra y sube o baja, así que la
     * pantalla no sugiere uno: solo muestra el de la última compra de cada
     * producto (sin contar anuladas) como referencia para ver la variación.
     */
    public function create(): Response
    {
        $ultimosCostos = DB::table('orden_compra_detalle as d')
            ->join('ordenes_compra as o', 'o.id', '=', 'd.orden_compra_id')
            ->where('o.estado', '!=', 'cancelada')
            ->orderBy('d.producto_id')
            ->orderByDesc('d.id')
            ->selectRaw('DISTINCT ON (d.producto_id) d.producto_id, d.costo_unitario')
            ->pluck('costo_unitario', 'producto_id');

        return Inertia::render('ordenes_compra/Create', [
            // una membresía no se compra a un proveedor
            'productos' => Producto::query()
                ->sinPlan()
                ->where('activo', true)
                ->with('unidadMedida:id,nombre,abreviatura')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'unidad_medida_id', 'stock_actual', 'controla_inventario'])
                ->each(fn (Producto $producto) => $producto->setAttribute('ultimo_costo', $ultimosCostos[$producto->id] ?? null)),
            'proveedores' => Proveedor::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            // solo la tasa de hoy sirve para convertir un costo digitado en Bs
            'tasaCambio' => TasaCambio::vigenteHoy(),
        ]);
    }

    /**
     * Detalle de una compra para el panel lateral del historial (mismo
     * esquema que `VentaController::show`).
     */
    public function show(OrdenCompra $orden_compra): JsonResponse
    {
        $orden_compra->load([
            'proveedor:id,nombre,telefono',
            'usuario:id,name',
            'tasaCambio:id,tasa,fecha',
            'detalles.producto:id,nombre',
        ]);

        return response()->json($orden_compra);
    }

    /**
     * Registra una compra completa: a diferencia de una venta no hay pagos
     * mixtos ni crédito, así que la orden entra directamente al inventario
     * en el momento de crearla (sin un paso separado de "recibir").
     */
    public function store(StoreOrdenCompraRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tasaCambio = TasaCambio::actual();
        $enBolivares = ($data['moneda'] ?? 'USD') === 'VES';

        // un proveedor que cobra en bolívares: el costo se digita en Bs y se
        // guarda en USD con la tasa del día, igual que el resto del sistema
        if ($enBolivares) {
            $tasaCambio = TasaCambio::vigenteHoy();

            if (! $tasaCambio) {
                throw ValidationException::withMessages([
                    'moneda' => 'No se puede registrar en bolívares: todavía no se ha cargado la tasa de cambio de hoy.',
                ]);
            }
        }

        return DB::transaction(function () use ($data, $tasaCambio, $enBolivares) {
            $total = 0;
            $lineas = [];

            foreach ($data['items'] as $item) {
                $producto = Producto::lockForUpdate()->findOrFail($item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $costoUnitario = (float) $item['costo_unitario'];
                if ($enBolivares) {
                    $costoUnitario = round($costoUnitario / (float) $tasaCambio->tasa, 4);
                }
                $subtotal = round($cantidad * $costoUnitario, 4);

                $total += $subtotal;
                $lineas[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                ];
            }

            $orden = OrdenCompra::create([
                'numero_orden' => $this->generarNumeroOrden(),
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'usuario_id' => auth()->id(),
                'tasa_cambio_id' => $tasaCambio?->id,
                'estado' => 'recibida_completa',
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'total' => round($total, 4),
                'notas' => $data['notas'] ?? null,
            ]);

            foreach ($lineas as $linea) {
                $orden->detalles()->create([
                    'producto_id' => $linea['producto']->id,
                    'cantidad' => $linea['cantidad'],
                    'cantidad_recibida' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'],
                    'subtotal' => $linea['subtotal'],
                ]);

                $this->registrarEntradaInventario($linea['producto'], $linea['cantidad'], $linea['costo_unitario'], $orden);
            }

            return response()->json($orden->load('detalles.producto'), 201);
        });
    }

    /**
     * Anula una compra mal digitada sin editarla ni borrarla (principio #1 y
     * #2): cada entrada se revierte con un `ajuste_negativo` nuevo en el
     * ledger y la orden queda `cancelada`. Si lo comprado ya se vendió y no
     * hay stock para revertirlo, no se anula nada.
     *
     * El costo promedio también se corrige: se saca el lote anulado del
     * promedio (valor del inventario menos lo que valía ese lote), porque si
     * no, un costo mal digitado seguiría falseando el margen de las ventas
     * futuras. Con stock en cero el promedio se deja tal cual: la próxima
     * compra lo reemplaza sola.
     */
    public function anular(OrdenCompra $orden_compra): RedirectResponse
    {
        if ($orden_compra->estado === 'cancelada') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Esta compra ya está anulada.')]);

            return back();
        }

        DB::transaction(function () use ($orden_compra) {
            $orden = OrdenCompra::lockForUpdate()->findOrFail($orden_compra->id);
            $lineas = [];

            foreach ($orden->detalles()->get() as $detalle) {
                // solo se revierte lo que de verdad entró al ledger
                $entro = MovimientoInventario::where('referencia_tipo', 'orden_compra')
                    ->where('referencia_id', $orden->id)
                    ->where('producto_id', $detalle->producto_id)
                    ->where('tipo', 'entrada_compra')
                    ->exists();

                if (! $entro) {
                    continue;
                }

                $producto = Producto::lockForUpdate()->findOrFail($detalle->producto_id);
                $cantidad = (float) $detalle->cantidad;

                if ((float) $producto->stock_actual < $cantidad) {
                    throw ValidationException::withMessages([
                        'anular' => "No se puede anular: de {$producto->nombre} ya se vendió parte de lo comprado (quedan ".(float) $producto->stock_actual.', se compraron '.$cantidad.').',
                    ]);
                }

                $lineas[] = [$producto, $cantidad, (float) $detalle->costo_unitario];
            }

            foreach ($lineas as [$producto, $cantidad, $costoUnitario]) {
                $stockAnterior = (float) $producto->stock_actual;
                $stockNuevo = round($stockAnterior - $cantidad, 3);

                MovimientoInventario::create([
                    'producto_id' => $producto->id,
                    'tipo' => 'ajuste_negativo',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'referencia_tipo' => 'orden_compra',
                    'referencia_id' => $orden->id,
                    'usuario_id' => auth()->id(),
                ]);

                $cambios = ['stock_actual' => $stockNuevo];

                if ($stockNuevo > 0) {
                    $promedio = round(
                        (($stockAnterior * (float) $producto->costo_promedio) - ($cantidad * $costoUnitario)) / $stockNuevo,
                        4,
                    );

                    // un resultado negativo solo sale de datos inconsistentes: se conserva el promedio
                    if ($promedio >= 0) {
                        $cambios['costo_promedio'] = $promedio;
                    }
                }

                $producto->update($cambios);
            }

            $orden->update(['estado' => 'cancelada']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compra anulada.')]);

        return back();
    }

    private function registrarEntradaInventario(Producto $producto, float $cantidad, float $costoUnitario, OrdenCompra $orden): void
    {
        if (! $producto->controla_inventario) {
            return;
        }

        $stockAnterior = (float) $producto->stock_actual;
        $stockNuevo = round($stockAnterior + $cantidad, 3);

        MovimientoInventario::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada_compra',
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'referencia_tipo' => 'orden_compra',
            'referencia_id' => $orden->id,
            'usuario_id' => auth()->id(),
        ]);

        // promedio ponderado entre el stock que ya había (a su costo promedio
        // vigente) y lo que entra ahora, a su propio costo en esta orden
        $costoPromedioNuevo = round(
            (($stockAnterior * (float) $producto->costo_promedio) + ($cantidad * $costoUnitario)) / $stockNuevo,
            4,
        );

        $producto->update([
            'stock_actual' => $stockNuevo,
            'costo_promedio' => $costoPromedioNuevo,
        ]);
    }

    private function generarNumeroOrden(): string
    {
        $ultimo = OrdenCompra::max('id') ?? 0;

        return 'OC-'.str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT);
    }
}
