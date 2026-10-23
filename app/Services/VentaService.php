<?php

namespace App\Services;

use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function __construct(private SuscripcionService $suscripciones) {}

    /**
     * Registra una venta completa en una transacción: cabecera, detalle,
     * descuento de inventario, pagos, saldo del cliente y, si una línea es un
     * plan, su suscripción. Lo usan el punto de venta (productos) y el módulo
     * de clientes (membresías), así que la regla de negocio vive en un solo sitio.
     *
     * Una línea puede traer `precio_unitario` y `suscripcion` (`inicio` y `fin`)
     * para una membresía personalizada; el punto de venta nunca los envía.
     *
     * @param  array{cliente_id?: int|null, es_credito: bool, descuento?: float|string|null, items: array<int, array{producto_id: int, cantidad: float|int|string, precio_unitario?: float|string, suscripcion?: array{inicio: string, fin: string}}>, pagos?: array<int, array{metodo_pago_id: int, monto: float|int|string, referencia?: string|null}>|null, monto_recibido?: float|string|null}  $data
     */
    public function registrar(array $data): Venta
    {
        // sin la tasa de HOY cargada no se puede facturar: si la última tasa
        // activa quedó pegada de un día anterior (nadie cargó la de hoy),
        // esto no es una tasa vigente y la venta debe bloquearse en vez de
        // facturar con un valor desactualizado
        $tasaCambio = TasaCambio::vigenteHoy();

        if (! $tasaCambio) {
            throw ValidationException::withMessages([
                'tasa_cambio' => 'No se puede facturar: todavía no se ha cargado la tasa de cambio de hoy.',
            ]);
        }

        // la venta se registra dentro del cierre de caja abierto de hoy; si
        // nadie lo abrió a mano todavía, se abre uno automático con fondo en
        // 0 para que nunca se bloquee una venta por esto
        $cierre = CierreCaja::abiertoDeHoy() ?? CierreCaja::abrirAutomaticaDeHoy(auth()->id());

        return DB::transaction(function () use ($data, $cierre, $tasaCambio) {
            // 1. calcular totales a partir de los productos enviados
            $subtotal = 0;
            $lineas = [];

            foreach ($data['items'] as $item) {
                $producto = Producto::with('plan')->lockForUpdate()->findOrFail($item['producto_id']);
                $cantidad = (float) $item['cantidad'];

                // un plan de membresía genera una suscripción: necesita socio
                // y una cantidad entera de períodos
                if ($producto->plan) {
                    if (empty($data['cliente_id'])) {
                        throw ValidationException::withMessages([
                            'cliente_id' => 'Una membresía se vende a un cliente: selecciónalo antes de cobrar.',
                        ]);
                    }

                    if ($cantidad != floor($cantidad)) {
                        throw ValidationException::withMessages([
                            'items' => 'La cantidad de una membresía debe ser un número entero.',
                        ]);
                    }
                }
                $precioUnitario = (float) ($item['precio_unitario'] ?? $producto->precio_venta);
                $subtotalLinea = round($cantidad * $precioUnitario, 4);

                $subtotal += $subtotalLinea;

                $lineas[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'costo_unitario_momento' => $producto->costo_promedio,
                    'subtotal' => $subtotalLinea,
                    'suscripcion' => $item['suscripcion'] ?? null,
                ];
            }

            $descuento = (float) ($data['descuento'] ?? 0);
            $total = round($subtotal - $descuento, 4);

            $esCredito = (bool) $data['es_credito'];
            $pagosInput = $data['pagos'] ?? [];
            $totalPagado = array_sum(array_column($pagosInput, 'monto'));

            // 2. determinar estado de la venta según lo pagado vs el total
            if ($esCredito) {
                $estado = $totalPagado <= 0 ? 'pendiente' : ($totalPagado >= $total ? 'pagada' : 'parcial');
            } else {
                if ($totalPagado < $total) {
                    throw ValidationException::withMessages([
                        'pagos' => 'Una venta al contado debe cubrir el total con los pagos registrados.',
                    ]);
                }
                $estado = 'pagada';
            }

            // 3. calcular vuelto: solo tiene sentido si se pagó de más (normalmente en efectivo)
            $montoRecibido = (float) ($data['monto_recibido'] ?? $totalPagado);
            $vuelto = $esCredito ? null : max(0, round($montoRecibido - $total, 4));

            // 4. crear la venta (cabecera)
            $venta = Venta::create([
                'numero_venta' => $this->generarNumeroVenta(),
                'cliente_id' => $data['cliente_id'] ?? null,
                'usuario_id' => auth()->id(),
                'tasa_cambio_id' => $tasaCambio->id,
                'cierre_caja_id' => $cierre->id,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'es_credito' => $esCredito,
                'monto_recibido' => $esCredito ? null : $montoRecibido,
                'vuelto' => $vuelto,
                'estado' => $estado,
                'fecha' => now()->toDateString(),
            ]);

            // 5. detalle de la venta + descuento de inventario
            foreach ($lineas as $linea) {
                $detalle = $venta->detalles()->create([
                    'producto_id' => $linea['producto']->id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'costo_unitario_momento' => $linea['costo_unitario_momento'],
                    'subtotal' => $linea['subtotal'],
                ]);

                $this->registrarSalidaInventario($linea['producto'], $linea['cantidad'], $venta);

                if ($linea['producto']->plan) {
                    $this->suscripciones->emitir($detalle, $linea['producto']->plan, Cliente::findOrFail($venta->cliente_id), $linea['suscripcion']);
                }
            }

            // 6. pagos recibidos (si los hay) — el cierre del pago es siempre el de HOY
            foreach ($pagosInput as $pago) {
                VentaPago::create([
                    'venta_id' => $venta->id,
                    'metodo_pago_id' => $pago['metodo_pago_id'],
                    'cierre_caja_id' => $cierre->id,
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'fecha_pago' => now(),
                ]);
            }

            // 7. si quedó saldo pendiente, actualizar el saldo cacheado del cliente
            if ($esCredito && $estado !== 'pagada' && $venta->cliente_id) {
                Cliente::where('id', $venta->cliente_id)
                    ->increment('saldo_pendiente', $total - $totalPagado);
            }

            return $venta;
        });
    }

    private function registrarSalidaInventario(Producto $producto, float $cantidad, Venta $venta): void
    {
        if (! $producto->controla_inventario) {
            return;
        }

        $stockAnterior = (float) $producto->stock_actual;
        $stockNuevo = round($stockAnterior - $cantidad, 3);

        MovimientoInventario::create([
            'producto_id' => $producto->id,
            'tipo' => 'salida_venta',
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id,
            'usuario_id' => auth()->id(),
        ]);

        $producto->update(['stock_actual' => $stockNuevo]);
    }

    private function generarNumeroVenta(): string
    {
        $ultimo = Venta::max('id') ?? 0;

        return 'V-'.str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT);
    }
}
