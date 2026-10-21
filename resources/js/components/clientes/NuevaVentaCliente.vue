<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { AlertTriangle, ShoppingCart } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { store as guardarVenta } from '@/actions/App/Http/Controllers/VentaController';
import AlertError from '@/components/AlertError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import CantidadStepper from '@/components/ventas/CantidadStepper.vue';
import ProductoSelector from '@/components/ventas/ProductoSelector.vue';
import { esFraccionable } from '@/lib/cantidades';
import type { UnidadEntrada } from '@/lib/cantidades';
import { redondear } from '@/lib/clientes';
import type { ProductoVendible, TasaVigente } from '@/lib/clientes';
import { fmtBs, fmtUsd } from '@/lib/ventas';
import { index as tasasCambioIndex } from '@/routes/tasas_cambio';

interface Linea {
    producto: ProductoVendible;
    // siempre en kg (o la unidad base del producto); el toggle g/kg solo cambia cómo se lee
    cantidad: string;
    fraccionable: boolean;
    unidadEntrada: UnidadEntrada;
}

const props = defineProps<{
    cliente: { id: number; nombre: string; saldo_pendiente: string };
    productos: ProductoVendible[] | undefined;
    tasa: TasaVigente | null;
}>();

const emit = defineEmits<{ (e: 'registrada'): void }>();

const lineas = ref<Linea[]>([]);

// los precios en bolívares siempre a la tasa vigente de hoy (la del día que
// congela la venta al registrarla); sin tasa cargada solo se ve el dólar
function enBs(usd: number): string | null {
    return props.tasa ? fmtBs(usd * Number(props.tasa.tasa)) : null;
}

// Bs primero (lo que de verdad paga el mayorista), el dólar entre paréntesis
// como referencia; sin tasa cargada solo se ve el dólar
function fmtDual(valorUsd: number): string {
    return props.tasa
        ? `${fmtBs(valorUsd * Number(props.tasa.tasa))} (${fmtUsd(valorUsd)})`
        : fmtUsd(valorUsd);
}

// --- Productos ------------------------------------------------------------

function agregarProducto(producto: ProductoVendible) {
    const existente = lineas.value.find(
        (linea) => linea.producto.id === producto.id,
    );
    if (existente) {
        const actual = Number(existente.cantidad) || 0;
        existente.cantidad = String(
            existente.fraccionable ? actual + 1 : Math.round(actual) + 1,
        );
        return;
    }

    const fraccionable = esFraccionable(
        producto.unidad_medida?.abreviatura ?? '',
    );
    lineas.value.push({
        producto,
        cantidad: '1',
        fraccionable,
        // en mayoristas se vende por kilo: arranca en kg (el toggle permite pasar a gramos)
        unidadEntrada: 'kg',
    });
}

// badge sobre la tarjeta del producto que ya está en el pedido: en gramos
// para lo que se pesa (igual que el input), unidad entera para el resto
function cantidadEnPedido(productoId: number): string {
    const linea = lineas.value.find((item) => item.producto.id === productoId);
    const cantidad = linea ? Number(linea.cantidad) || 0 : 0;
    if (!linea || cantidad <= 0) return '';
    if (linea.fraccionable) {
        return linea.unidadEntrada === 'g'
            ? `${Math.round(cantidad * 1000)}g`
            : `${cantidad}kg`;
    }
    return String(cantidad);
}

function quitarLinea(indice: number) {
    lineas.value.splice(indice, 1);
}

function subtotalLinea(linea: Linea): number {
    return redondear(
        (Number(linea.cantidad) || 0) * Number(linea.producto.precio_venta),
    );
}

function sinStock(linea: Linea): boolean {
    return (
        linea.producto.controla_inventario &&
        (Number(linea.cantidad) || 0) > Number(linea.producto.stock_actual)
    );
}

// --- Total ------------------------------------------------------------------

const total = computed(() =>
    redondear(
        lineas.value.reduce((suma, linea) => suma + subtotalLinea(linea), 0),
    ),
);

// --- Envío ------------------------------------------------------------------

const http = useHttp<
    {
        es_credito: boolean;
        cliente_id: number;
        items: { producto_id: number; cantidad: number }[];
        pagos: { metodo_pago_id: number; monto: number; referencia?: string }[];
    },
    { numero_venta: string }
>({ es_credito: true, cliente_id: 0, items: [], pagos: [] });

const erroresGenerales = computed(() => Object.values(http.errors));

const problema = computed(() => {
    if (!props.tasa) return 'Falta cargar la tasa de cambio de hoy.';
    if (lineas.value.length === 0) return 'Agrega al menos un producto.';
    if (lineas.value.some((linea) => !(Number(linea.cantidad) > 0)))
        return 'Todas las cantidades deben ser mayores a 0.';
    if (total.value <= 0) return 'El total de la venta debe ser mayor a 0.';
    return null;
});

function limpiar() {
    lineas.value = [];
    http.clearErrors();
}

function registrar() {
    if (problema.value || http.processing) return;

    // este módulo solo registra ventas a crédito: no se cobra nada ahora,
    // los abonos se registran aparte desde la cuenta del cliente
    http.es_credito = true;
    http.cliente_id = props.cliente.id;
    http.items = lineas.value.map((linea) => ({
        producto_id: linea.producto.id,
        cantidad: Number(linea.cantidad),
    }));
    http.pagos = [];

    http.post(guardarVenta.url(), {
        onSuccess: (data) => {
            toast.success(
                `Venta ${data.numero_venta} registrada a crédito para ${props.cliente.nombre}.`,
            );
            limpiar();
            emit('registrada');
        },
        onError: () =>
            toast.error(
                'No se pudo registrar la venta. Revisa los avisos del formulario.',
            ),
    });
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="registrar">
        <Alert v-if="!tasa" variant="destructive">
            <AlertTriangle class="size-4" />
            <AlertTitle>Falta la tasa de cambio de hoy</AlertTitle>
            <AlertDescription>
                No se puede facturar sin la tasa del día.
                <Link
                    :href="tasasCambioIndex()"
                    class="font-medium underline underline-offset-4"
                    >Cargar la tasa</Link
                >
            </AlertDescription>
        </Alert>

        <AlertError
            v-if="erroresGenerales.length"
            :errors="erroresGenerales"
            title="No se pudo registrar la venta"
        />

        <!-- productos a la izquierda, pedido a la derecha, en cualquier pantalla: igual que el mostrador -->
        <div
            class="grid grid-cols-2 items-start gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] md:gap-6"
        >
            <!-- productos: sin precios en la tarjeta, los precios van en el pedido -->
            <section class="grid gap-3" aria-label="Productos">
                <div
                    v-if="productos === undefined"
                    class="grid gap-3"
                    aria-busy="true"
                >
                    <Skeleton class="h-11 w-full" />
                    <Skeleton class="h-56 w-full" />
                </div>
                <div
                    v-else
                    class="flex h-[calc(100dvh-14rem)] min-h-[20rem] min-w-0 flex-col rounded-xl border p-2 md:p-3"
                >
                    <ProductoSelector
                        id-buscador="venta-buscar-producto"
                        :productos="productos"
                        :cantidad-label="cantidadEnPedido"
                        @agregar="agregarProducto"
                    />
                </div>
            </section>

            <!-- pedido: la lista de productos tiene su propio scroll; el
            total y el botón de registrar quedan fijos abajo (footer), por
            eso la sección es flex-col en vez de un único bloque con scroll -->
            <section
                class="sticky top-4 flex max-h-[calc(100dvh-8rem)] min-w-0 flex-col gap-3"
                aria-label="Pedido"
            >
                <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                    <div
                        v-if="lineas.length === 0"
                        class="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center text-sm"
                    >
                        <ShoppingCart class="size-6" />
                        <p>
                            Todavía no hay productos. Toca uno para agregarlo.
                        </p>
                    </div>

                    <ul v-else class="divide-y rounded-xl border">
                        <li
                            v-for="(linea, indice) in lineas"
                            :key="linea.producto.id"
                            class="grid grid-cols-[1fr_auto] items-start gap-x-3 gap-y-1 px-3 py-2.5"
                        >
                            <p class="min-w-0 text-sm font-medium">
                                {{ linea.producto.nombre }}
                            </p>
                            <p
                                class="text-right text-sm font-semibold tabular-nums"
                            >
                                {{ fmtUsd(subtotalLinea(linea)) }}
                            </p>

                            <CantidadStepper
                                v-model:cantidad="linea.cantidad"
                                v-model:unidad-entrada="linea.unidadEntrada"
                                :fraccionable="linea.fraccionable"
                                :nombre="linea.producto.nombre"
                                @quitar="quitarLinea(indice)"
                            />
                            <p
                                v-if="enBs(subtotalLinea(linea))"
                                class="text-muted-foreground text-right text-xs tabular-nums"
                            >
                                {{ enBs(subtotalLinea(linea)) }}
                            </p>

                            <p
                                v-if="sinStock(linea)"
                                class="col-span-full flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                            >
                                <AlertTriangle class="size-3.5" />
                                Hay {{ Number(linea.producto.stock_actual) }} en
                                inventario; la venta lo dejará en negativo.
                            </p>
                        </li>
                    </ul>
                </div>

                <!-- footer fijo: total (Bs con el dólar de referencia entre
                paréntesis, en cero mientras el pedido está vacío), deuda
                acumulada del cliente y acciones — mismo patrón que el
                mostrador (ver ventas/Create.vue) -->
                <div class="flex flex-shrink-0 flex-col gap-2.5 border-t pt-3">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground text-sm">Total</span>
                        <span class="text-xl font-bold">{{
                            fmtDual(total)
                        }}</span>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            v-if="lineas.length"
                            type="button"
                            variant="ghost"
                            :disabled="http.processing"
                            @click="limpiar"
                            >Vaciar</Button
                        >
                        <Button
                            type="submit"
                            size="lg"
                            class="flex-1"
                            :disabled="!!problema || http.processing"
                        >
                            {{
                                http.processing
                                    ? 'Registrando...'
                                    : 'Registrar a crédito'
                            }}
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </form>
</template>
