<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ChevronRight, HandCoins, Receipt, Share2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { show as ventaShow } from '@/actions/App/Http/Controllers/VentaController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    antiguedad,
    diasDeAntiguedad,
    fmtFechaCorta,
    fmtFechaHora,
} from '@/lib/clientes';
import type { VentaCabecera } from '@/lib/clientes';
import { ESTADO_ESTILOS, ESTADO_ETIQUETAS, fmtBs, fmtUsd } from '@/lib/ventas';

interface DetalleVenta {
    id: number;
    numero_venta: string;
    tasa_cambio: { tasa: string; fecha: string } | null;
    detalles: {
        id: number;
        cantidad: string;
        precio_unitario: string;
        subtotal: string;
        producto: { id: number; nombre: string };
    }[];
    pagos: {
        id: number;
        monto: string;
        referencia: string | null;
        fecha_pago: string;
        metodo_pago: { id: number; nombre: string; moneda: string };
    }[];
}

type Filtro = 'todas' | 'por_cobrar' | 'pagadas';

const props = defineProps<{
    ventas: VentaCabecera[];
}>();

defineEmits<{
    (e: 'abonar', venta: VentaCabecera): void;
    (e: 'compartir', venta: VentaCabecera): void;
}>();

const FILTROS: { valor: Filtro; etiqueta: string }[] = [
    { valor: 'todas', etiqueta: 'Todas' },
    { valor: 'por_cobrar', etiqueta: 'Por cobrar' },
    { valor: 'pagadas', etiqueta: 'Pagadas' },
];

const filtro = ref<Filtro>('todas');

const porCobrar = computed(
    () => props.ventas.filter((venta) => venta.saldo_pendiente > 0).length,
);

const visibles = computed(() => {
    if (filtro.value === 'por_cobrar')
        return props.ventas.filter((venta) => venta.saldo_pendiente > 0);
    if (filtro.value === 'pagadas')
        return props.ventas.filter((venta) => venta.estado === 'pagada');
    return props.ventas;
});

// --- Detalle de cada venta (se pide al abrirla y se recuerda) -------------

const abierta = ref<number | null>(null);
const detalles = ref<Record<number, DetalleVenta>>({});
const http = useHttp<Record<string, never>, DetalleVenta>({});

// tras un abono o una venta nueva el historial cambia: lo recordado ya no sirve
watch(
    () => props.ventas,
    () => {
        detalles.value = {};
        if (abierta.value !== null) cargarDetalle(abierta.value);
    },
);

function cargarDetalle(id: number) {
    http.get(ventaShow.url(id), {
        onSuccess: (data) => {
            detalles.value = { ...detalles.value, [id]: data };
        },
    });
}

function alternar(id: number) {
    if (abierta.value === id) {
        abierta.value = null;
        return;
    }
    abierta.value = id;
    if (!detalles.value[id]) cargarDetalle(id);
}

// venta_pagos.monto siempre se guarda en USD; el equivalente en Bs de un pago
// en bolívares usa la tasa congelada en la venta, nunca la de hoy
function montoPago(
    detalle: DetalleVenta,
    pago: DetalleVenta['pagos'][number],
): string {
    if (pago.metodo_pago.moneda !== 'VES' || !detalle.tasa_cambio)
        return fmtUsd(pago.monto);
    return `${fmtBs(Number(pago.monto) * Number(detalle.tasa_cambio.tasa))} (${fmtUsd(pago.monto)})`;
}

function urgente(venta: VentaCabecera): boolean {
    return venta.saldo_pendiente > 0 && diasDeAntiguedad(venta.fecha) >= 15;
}
</script>

<template>
    <section aria-labelledby="titulo-historial" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="titulo-historial" class="text-base font-semibold">
                Historial de ventas
            </h2>
            <div class="flex gap-1.5" role="group" aria-label="Filtrar ventas">
                <button
                    v-for="opcion in FILTROS"
                    :key="opcion.valor"
                    type="button"
                    class="focus-visible:ring-ring/50 rounded-full border px-3 py-1 text-sm transition-colors outline-none focus-visible:ring-[3px]"
                    :class="
                        filtro === opcion.valor
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    :aria-pressed="filtro === opcion.valor"
                    @click="filtro = opcion.valor"
                >
                    {{ opcion.etiqueta }}
                    <span
                        v-if="opcion.valor === 'por_cobrar' && porCobrar > 0"
                        class="tabular-nums"
                        >· {{ porCobrar }}</span
                    >
                </button>
            </div>
        </div>

        <div
            v-if="visibles.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-10 text-center text-sm"
        >
            <Receipt class="size-6" />
            <p v-if="ventas.length === 0">
                Este cliente todavía no tiene ventas. Registra la primera desde
                «Nueva venta».
            </p>
            <p v-else-if="filtro === 'por_cobrar'">
                No debe nada: todas sus ventas están pagadas.
            </p>
            <p v-else>Ninguna venta coincide con este filtro.</p>
        </div>

        <ul v-else class="divide-y overflow-hidden rounded-xl border">
            <li v-for="venta in visibles" :key="venta.id">
                <!-- cabecera de la venta -->
                <div
                    class="flex items-center gap-2 pr-3"
                    :class="abierta === venta.id && 'bg-muted/40'"
                >
                    <button
                        type="button"
                        class="hover:bg-muted/40 focus-visible:ring-ring/50 grid min-w-0 flex-1 grid-cols-[1rem_1fr_auto] items-center gap-3 px-4 py-3 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-inset sm:grid-cols-[1rem_1fr_auto_auto]"
                        :aria-expanded="abierta === venta.id"
                        :aria-controls="`detalle-venta-${venta.id}`"
                        @click="alternar(venta.id)"
                    >
                        <ChevronRight
                            class="text-muted-foreground size-4 transition-transform"
                            :class="abierta === venta.id && 'rotate-90'"
                        />
                        <span class="min-w-0">
                            <span
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <span
                                    class="text-sm font-medium tabular-nums"
                                    >{{ venta.numero_venta }}</span
                                >
                                <Badge
                                    variant="outline"
                                    :class="ESTADO_ESTILOS[venta.estado]"
                                    >{{ ESTADO_ETIQUETAS[venta.estado] }}</Badge
                                >
                            </span>
                            <span
                                class="text-muted-foreground mt-0.5 block text-xs"
                            >
                                {{ fmtFechaCorta(venta.fecha) }}
                                <template v-if="venta.saldo_pendiente > 0">
                                    ·
                                    <span
                                        :class="
                                            urgente(venta) &&
                                            'font-medium text-amber-700 dark:text-amber-400'
                                        "
                                        >{{ antiguedad(venta.fecha) }}</span
                                    >
                                </template>
                            </span>
                        </span>
                        <span class="hidden text-right text-xs sm:block">
                            <span class="text-muted-foreground block"
                                >Total</span
                            >
                            <span class="text-sm tabular-nums">{{
                                fmtUsd(venta.total)
                            }}</span>
                        </span>
                        <span class="text-right">
                            <template v-if="venta.saldo_pendiente > 0">
                                <span
                                    class="text-muted-foreground block text-xs"
                                    >Debe</span
                                >
                                <span
                                    class="text-sm font-semibold text-amber-700 tabular-nums dark:text-amber-400"
                                    >{{ fmtUsd(venta.saldo_pendiente) }}</span
                                >
                            </template>
                            <template v-else>
                                <span class="text-sm tabular-nums">{{
                                    fmtUsd(venta.total)
                                }}</span>
                            </template>
                        </span>
                    </button>
                    <Button
                        v-if="venta.saldo_pendiente > 0"
                        size="sm"
                        variant="outline"
                        class="shrink-0"
                        @click="$emit('abonar', venta)"
                    >
                        <HandCoins class="size-4" />
                        Abonar
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="shrink-0"
                        :aria-label="`Compartir venta ${venta.numero_venta}`"
                        title="Compartir esta venta"
                        @click="$emit('compartir', venta)"
                    >
                        <Share2 class="size-4" />
                    </Button>
                </div>

                <!-- detalle: productos y pagos -->
                <div
                    v-if="abierta === venta.id"
                    :id="`detalle-venta-${venta.id}`"
                    class="bg-muted/20 grid gap-5 border-t px-4 py-4 sm:pl-11"
                >
                    <div
                        v-if="!detalles[venta.id]"
                        class="grid gap-2"
                        aria-busy="true"
                    >
                        <Skeleton class="h-4 w-2/3" />
                        <Skeleton class="h-4 w-1/2" />
                        <Skeleton class="h-4 w-3/5" />
                    </div>

                    <template v-else>
                        <div>
                            <h3
                                class="text-muted-foreground mb-2 text-xs font-medium"
                            >
                                Productos
                            </h3>
                            <table class="w-full text-sm">
                                <thead class="sr-only">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="linea in detalles[venta.id]
                                            .detalles"
                                        :key="linea.id"
                                    >
                                        <td class="py-1.5 pr-3">
                                            {{ linea.producto.nombre }}
                                        </td>
                                        <td
                                            class="text-muted-foreground py-1.5 pr-3 text-right tabular-nums"
                                        >
                                            {{ Number(linea.cantidad) }} ×
                                            {{ fmtUsd(linea.precio_unitario) }}
                                        </td>
                                        <td
                                            class="py-1.5 text-right font-medium tabular-nums"
                                        >
                                            {{ fmtUsd(linea.subtotal) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <h3
                                class="text-muted-foreground mb-2 text-xs font-medium"
                            >
                                Pagos y abonos
                                <span class="tabular-nums"
                                    >· {{ fmtUsd(venta.total_pagado) }} de
                                    {{ fmtUsd(venta.total) }}</span
                                >
                            </h3>
                            <p
                                v-if="detalles[venta.id].pagos.length === 0"
                                class="text-muted-foreground text-sm"
                            >
                                Todavía no se ha recibido ningún pago.
                            </p>
                            <ul v-else class="divide-y">
                                <li
                                    v-for="pago in detalles[venta.id].pagos"
                                    :key="pago.id"
                                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-1.5 text-sm"
                                >
                                    <span>
                                        {{ pago.metodo_pago.nombre }}
                                        <span
                                            class="text-muted-foreground text-xs"
                                        >
                                            ·
                                            {{ fmtFechaHora(pago.fecha_pago) }}
                                            <template v-if="pago.referencia">
                                                · ref.
                                                {{ pago.referencia }}</template
                                            >
                                        </span>
                                    </span>
                                    <span class="font-medium tabular-nums">{{
                                        montoPago(detalles[venta.id], pago)
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </li>
        </ul>
    </section>
</template>
