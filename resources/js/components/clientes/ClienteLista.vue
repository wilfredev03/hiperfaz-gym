<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import {
    ChevronRight,
    CircleCheck,
    ExternalLink,
    HandCoins,
    Plus,
    Search,
    Share2,
    ShoppingCart,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { antiguedad, fmtFechaCorta, iniciales } from '@/lib/clientes';
import type { ClienteLista, VentaCabecera } from '@/lib/clientes';
import { ESTADO_ESTILOS, ESTADO_ETIQUETAS, fmtBs, fmtUsd } from '@/lib/ventas';
import { index } from '@/routes/clientes';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type Filtro = 'todos' | 'con_deuda';

const props = defineProps<{
    clientes: { data: ClienteLista[]; links: PaginationLink[] };
    busqueda: string;
    filtro: Filtro;
    resumen: { clientes_con_deuda: number; total_por_cobrar: number };
    // tasa vigente de hoy (null si nadie la cargó): pasa las deudas a Bs.
    tasa: number | null;
}>();

defineEmits<{
    (e: 'nuevo'): void;
    (e: 'abonar', cliente: ClienteLista, venta: VentaCabecera | null): void;
    (e: 'compartir', cliente: ClienteLista, venta: VentaCabecera | null): void;
}>();

const FILTROS: { valor: Filtro; etiqueta: string }[] = [
    { valor: 'con_deuda', etiqueta: 'Con deuda' },
    { valor: 'todos', etiqueta: 'Todos los clientes' },
];

const texto = ref(props.busqueda);
// un cliente desplegado a la vez; sobrevive a las recargas de la lista (tras un abono)
const abierto = ref<number | null>(null);

function alternar(id: number) {
    abierto.value = abierto.value === id ? null : id;
}

// "Registrar abono" es el único botón de abono de esta lista: con una sola
// venta pendiente no tiene sentido mostrar el reparto entre varias ventas
// (AbonoDialog), así que se abre directo sobre esa venta — el diálogo ya
// resuelve el reparto automático cuando se le pasa null con 2+ pendientes.
function ventaParaAbono(cliente: ClienteLista): VentaCabecera | null {
    return cliente.ventas_pendientes.length === 1
        ? cliente.ventas_pendientes[0]
        : null;
}

function enBs(usd: number): string | null {
    return props.tasa === null ? null : fmtBs(usd * props.tasa);
}

// arma la URL conservando búsqueda y filtro para poder volver a esta misma lista
function consulta(cambios: {
    busqueda?: string;
    filtro?: Filtro;
    cliente?: number;
    vista?: 'venta';
}) {
    const busqueda = (cambios.busqueda ?? texto.value).trim();
    const filtro = cambios.filtro ?? props.filtro;

    return {
        ...(busqueda ? { busqueda } : {}),
        // 'con_deuda' es el filtro por defecto: no hace falta en la URL
        ...(filtro !== 'con_deuda' ? { filtro } : {}),
        ...(cambios.cliente ? { cliente: cambios.cliente } : {}),
        ...(cambios.vista ? { vista: cambios.vista } : {}),
    };
}

function hrefCliente(id: number, vista?: 'venta'): string {
    return index.url({ query: consulta({ cliente: id, vista }) });
}

watchDebounced(
    texto,
    () => {
        router.get(index.url(), consulta({}), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { debounce: 300 },
);

function cambiarFiltro(filtro: Filtro) {
    if (filtro === props.filtro) return;
    router.get(index.url(), consulta({ filtro }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-56 flex-1">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="texto"
                    type="search"
                    placeholder="Buscar cliente por nombre o documento"
                    class="h-10 pl-9"
                    autocomplete="off"
                    aria-label="Buscar cliente"
                />
            </div>
            <Button class="h-10 shrink-0" @click="$emit('nuevo')">
                <Plus class="size-4" />
                Nuevo cliente
            </Button>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2"
        >
            <div
                class="flex flex-wrap gap-1.5"
                role="group"
                aria-label="Filtrar clientes"
            >
                <button
                    v-for="opcion in FILTROS"
                    :key="opcion.valor"
                    type="button"
                    class="focus-visible:ring-ring/50 rounded-full border px-3 py-1.5 text-sm transition-colors outline-none focus-visible:ring-[3px]"
                    :class="
                        filtro === opcion.valor
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    :aria-pressed="filtro === opcion.valor"
                    @click="cambiarFiltro(opcion.valor)"
                >
                    {{ opcion.etiqueta }}
                </button>
            </div>

            <p
                v-if="resumen.clientes_con_deuda > 0"
                class="text-muted-foreground text-sm"
            >
                Por cobrar
                <span class="text-foreground font-medium tabular-nums">{{
                    fmtUsd(resumen.total_por_cobrar)
                }}</span>
                <template v-if="enBs(resumen.total_por_cobrar)">
                    ·
                    <span class="text-foreground font-medium tabular-nums">{{
                        enBs(resumen.total_por_cobrar)
                    }}</span></template
                >
                en {{ resumen.clientes_con_deuda }} cliente{{
                    resumen.clientes_con_deuda === 1 ? '' : 's'
                }}
            </p>
        </div>

        <div
            v-if="clientes.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-14 text-center text-sm"
        >
            <Users class="size-6" />
            <p v-if="busqueda">Nadie coincide con «{{ busqueda }}».</p>
            <p v-else-if="filtro === 'con_deuda'">
                Ningún cliente tiene deuda pendiente.
            </p>
            <p v-else>
                Todavía no hay clientes. Crea el primero con «Nuevo
                cliente».
            </p>
        </div>

        <ul v-else class="divide-y overflow-hidden rounded-xl border">
            <li v-for="cliente in clientes.data" :key="cliente.id">
                <!-- cabecera: quién es y cuánto debe -->
                <button
                    type="button"
                    class="hover:bg-muted/40 focus-visible:ring-ring/50 grid w-full grid-cols-[2.25rem_1fr_auto_1rem] items-center gap-3 px-4 py-3 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-inset"
                    :class="abierto === cliente.id && 'bg-muted/40'"
                    :aria-expanded="abierto === cliente.id"
                    :aria-controls="`cliente-${cliente.id}`"
                    @click="alternar(cliente.id)"
                >
                    <Avatar class="size-9">
                        <AvatarFallback
                            class="bg-primary/10 text-primary text-xs font-semibold"
                        >
                            {{ iniciales(cliente.nombre) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="min-w-0">
                        <span
                            class="block truncate text-sm font-medium"
                            :class="!cliente.activo && 'text-muted-foreground'"
                        >
                            {{ cliente.nombre }}
                        </span>
                        <span
                            class="text-muted-foreground block truncate text-xs"
                        >
                            {{ cliente.documento ?? 'Sin documento'
                            }}<template v-if="!cliente.activo">
                                · Inactivo</template
                            >
                        </span>
                    </span>
                    <span
                        v-if="Number(cliente.saldo_pendiente) > 0"
                        class="text-right tabular-nums"
                    >
                        <span
                            class="block text-sm font-semibold text-amber-700 dark:text-amber-400"
                        >
                            {{ fmtUsd(cliente.saldo_pendiente) }}
                        </span>
                        <span
                            v-if="enBs(Number(cliente.saldo_pendiente))"
                            class="text-muted-foreground block text-xs"
                        >
                            {{ enBs(Number(cliente.saldo_pendiente)) }}
                        </span>
                    </span>
                    <span v-else class="text-muted-foreground text-xs"
                        >Al día</span
                    >
                    <ChevronRight
                        class="text-muted-foreground size-4 transition-transform"
                        :class="abierto === cliente.id && 'rotate-90'"
                    />
                </button>

                <!-- desplegado: ventas por cobrar y acciones, sin salir de la lista -->
                <div
                    v-if="abierto === cliente.id"
                    :id="`cliente-${cliente.id}`"
                    class="bg-muted/20 grid gap-4 border-t px-4 py-4"
                >
                    <div
                        v-if="cliente.ventas_pendientes.length"
                        class="grid gap-2"
                    >
                        <ul class="divide-y">
                            <li
                                v-for="venta in cliente.ventas_pendientes"
                                :key="venta.id"
                                class="flex items-center gap-3 py-2.5"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                                    >
                                        <span
                                            class="font-medium tabular-nums"
                                            >{{ venta.numero_venta }}</span
                                        >
                                        <Badge
                                            variant="outline"
                                            :class="
                                                ESTADO_ESTILOS[venta.estado]
                                            "
                                            >{{
                                                ESTADO_ETIQUETAS[venta.estado]
                                            }}</Badge
                                        >
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-0.5 text-xs"
                                    >
                                        {{ fmtFechaCorta(venta.fecha) }} ·
                                        {{ antiguedad(venta.fecha) }}
                                    </p>
                                </div>
                                <!-- con una sola venta pendiente este monto es el mismo
                                que ya se ve en la cabecera del cliente: repetirlo aquí
                                no aporta nada -->
                                <div
                                    v-if="cliente.ventas_pendientes.length > 1"
                                    class="text-right tabular-nums"
                                >
                                    <p
                                        class="text-sm font-semibold text-amber-700 dark:text-amber-400"
                                    >
                                        {{ fmtUsd(venta.saldo_pendiente) }}
                                    </p>
                                    <p
                                        v-if="enBs(venta.saldo_pendiente)"
                                        class="text-muted-foreground text-xs"
                                    >
                                        {{ enBs(venta.saldo_pendiente) }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <p
                        v-else
                        class="flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400"
                    >
                        <CircleCheck class="size-4" />
                        No debe nada.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="cliente.ventas_pendientes.length"
                            size="sm"
                            @click="
                                $emit(
                                    'abonar',
                                    cliente,
                                    ventaParaAbono(cliente),
                                )
                            "
                        >
                            <HandCoins class="size-4" />
                            Abonar
                        </Button>
                        <Button
                            v-if="cliente.ventas_pendientes.length"
                            size="sm"
                            variant="outline"
                            @click="$emit('compartir', cliente, null)"
                        >
                            <Share2 class="size-4" />
                            Compartir
                        </Button>
                        <Button size="sm" variant="outline" as-child>
                            <Link :href="hrefCliente(cliente.id, 'venta')">
                                <ShoppingCart class="size-4" />
                                Vender
                            </Link>
                        </Button>
                        <Button size="sm" variant="ghost" as-child>
                            <Link :href="hrefCliente(cliente.id)">
                                <ExternalLink class="size-4" />
                                Ver cliente
                            </Link>
                        </Button>
                    </div>
                </div>
            </li>
        </ul>

        <nav
            v-if="clientes.links.length > 3"
            class="flex flex-wrap items-center gap-1"
            aria-label="Paginación de clientes"
        >
            <template
                v-for="(link, linkIndex) in clientes.links"
                :key="linkIndex"
            >
                <span
                    v-if="!link.url"
                    class="text-muted-foreground rounded-md px-3 py-1.5 text-sm"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
