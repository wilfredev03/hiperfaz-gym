<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Ban, ChevronRight, Loader2, PackagePlus, Plus } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { fmtBs, fmtFecha, fmtHora, fmtUsd } from '@/lib/ventas';
import {
    anular,
    create as crearOrdenCompra,
    index as ordenesCompraIndex,
    show as ordenCompraShow,
} from '@/routes/ordenes_compra';

interface ProveedorResumen {
    id: number;
    nombre: string;
}

interface OrdenCompraFila {
    id: number;
    numero_orden: string;
    fecha: string;
    created_at: string;
    total: string;
    estado: Estado;
    proveedor: ProveedorResumen | null;
    tasa_cambio: { tasa: string } | null;
}

type Estado = 'recibida_completa' | 'cancelada';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface OrdenesCompraPaginadas {
    data: OrdenCompraFila[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

// --- Detalle de una compra (panel lateral) --------------------------------

interface OrdenCompraLinea {
    id: number;
    cantidad: string;
    costo_unitario: string;
    subtotal: string;
    producto: { id: number; nombre: string };
}

interface OrdenCompraDetalle {
    id: number;
    numero_orden: string;
    fecha: string;
    created_at: string;
    total: string;
    estado: Estado;
    notas: string | null;
    proveedor: { id: number; nombre: string; telefono: string | null } | null;
    usuario: { id: number; name: string };
    tasa_cambio: { id: number; tasa: string; fecha: string } | null;
    detalles: OrdenCompraLinea[];
}

const props = defineProps<{
    ordenes: OrdenesCompraPaginadas;
    proveedores: ProveedorResumen[];
    filtros: { proveedor_id: number | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Historial de compras', href: ordenesCompraIndex() },
        ],
    },
});

const ESTADO_ETIQUETAS: Record<Estado, string> = {
    recibida_completa: 'Registrada',
    cancelada: 'Anulada',
};

const ESTADO_ESTILOS: Record<Estado, string> = {
    recibida_completa:
        'border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    cancelada: 'border-neutral-500/30 bg-neutral-500/10 text-muted-foreground',
};

const TODOS = 'todos';

const cargando = ref(false);
const proveedorFiltro = ref(
    props.filtros.proveedor_id ? String(props.filtros.proveedor_id) : TODOS,
);

watch(proveedorFiltro, (proveedorId) => {
    cargando.value = true;
    router.get(
        ordenesCompraIndex.url(),
        { proveedor_id: proveedorId === TODOS ? undefined : proveedorId },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['ordenes', 'filtros'],
            onFinish: () => (cargando.value = false),
        },
    );
});

function irAPagina(url: string | null) {
    if (!url) {
        return;
    }
    cargando.value = true;
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['ordenes'],
            onFinish: () => (cargando.value = false),
        },
    );
}

// El total de cada compra está en USD; el equivalente en Bs usa la tasa que
// quedó guardada en esa compra, nunca la de hoy.
function totalBs(total: string, tasa: { tasa: string } | null): string | null {
    return tasa ? fmtBs(Number(total) * Number(tasa.tasa)) : null;
}

// --- Detalle de una compra (panel lateral) --------------------------------

const detalleAbierto = ref(false);
const detalleOrden = ref<OrdenCompraDetalle | null>(null);
const httpDetalle = useHttp<Record<string, never>, OrdenCompraDetalle>({});

function abrirDetalle(id: number) {
    detalleAbierto.value = true;
    detalleOrden.value = null;
    httpDetalle.get(ordenCompraShow.url(id), {
        onSuccess: (data) => {
            detalleOrden.value = data;
        },
    });
}

// Anular no borra ni edita: revierte el stock con movimientos nuevos y deja la
// compra marcada. Si ya se vendió parte de lo comprado, el servidor lo rechaza.
function anularCompra(orden: OrdenCompraDetalle) {
    if (
        !confirm(
            `¿Anular la compra ${orden.numero_orden}? Se descontará del inventario lo que entró.`,
        )
    ) {
        return;
    }
    router.post(
        anular.url(orden.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['ordenes'],
            onSuccess: () => abrirDetalle(orden.id),
            onError: (errores) => {
                toast.error(errores.anular ?? 'No se pudo anular la compra.');
            },
        },
    );
}
</script>

<template>
    <Head title="Historial de compras" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Historial de compras</h1>

            <div class="flex flex-wrap items-center gap-2">
                <Select v-model="proveedorFiltro">
                    <SelectTrigger class="w-52">
                        <SelectValue placeholder="Proveedor" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="TODOS"
                            >Todos los proveedores</SelectItem
                        >
                        <SelectItem
                            v-for="proveedor in proveedores"
                            :key="proveedor.id"
                            :value="String(proveedor.id)"
                        >
                            {{ proveedor.nombre }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button as-child>
                    <Link :href="crearOrdenCompra()">
                        <Plus class="size-4" />
                        Registrar compra
                    </Link>
                </Button>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 transition-opacity duration-150"
            :class="{ 'pointer-events-none opacity-60': cargando }"
        >
            <div
                v-if="ordenes.data.length === 0"
                class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
            >
                No hay compras registradas con este filtro.
            </div>

            <Card v-else class="overflow-hidden py-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="text-muted-foreground border-b text-left"
                            >
                                <th class="px-4 py-3 font-medium">Compra</th>
                                <th class="px-4 py-3 font-medium">Fecha</th>
                                <th class="px-4 py-3 font-medium">Proveedor</th>
                                <th class="px-4 py-3 font-medium">Total</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3">
                                    <span class="sr-only">Detalle</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="orden in ordenes.data"
                                :key="orden.id"
                                class="hover:bg-accent/50 cursor-pointer border-b transition-colors last:border-0"
                                @click="abrirDetalle(orden.id)"
                            >
                                <td
                                    class="px-4 py-3 font-medium whitespace-nowrap"
                                >
                                    {{ orden.numero_orden }}
                                </td>
                                <td
                                    class="text-muted-foreground px-4 py-3 whitespace-nowrap"
                                >
                                    {{ fmtFecha(orden.fecha) }} ·
                                    {{ fmtHora(orden.created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="orden.proveedor">{{
                                        orden.proveedor.nombre
                                    }}</span>
                                    <span
                                        v-else
                                        class="text-muted-foreground italic"
                                        >Sin proveedor</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">
                                        {{ fmtUsd(orden.total) }}
                                    </p>
                                    <p
                                        v-if="orden.tasa_cambio"
                                        class="text-muted-foreground text-xs"
                                    >
                                        {{
                                            totalBs(
                                                orden.total,
                                                orden.tasa_cambio,
                                            )
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant="outline"
                                        :class="ESTADO_ESTILOS[orden.estado]"
                                    >
                                        {{ ESTADO_ETIQUETAS[orden.estado] }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="text-muted-foreground"
                                        @click.stop="abrirDetalle(orden.id)"
                                    >
                                        <ChevronRight class="size-4" />
                                        <span class="sr-only">Ver detalle</span>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <div
                v-if="ordenes.data.length"
                class="flex items-center justify-between"
            >
                <p class="text-muted-foreground text-sm">
                    Mostrando {{ ordenes.from }}–{{ ordenes.to }} de
                    {{ ordenes.total }}
                </p>
                <nav
                    v-if="ordenes.links.length > 3"
                    class="flex flex-wrap items-center gap-1"
                >
                    <template
                        v-for="(link, index) in ordenes.links"
                        :key="index"
                    >
                        <span
                            v-if="!link.url"
                            class="text-muted-foreground rounded-md px-3 py-1.5 text-sm"
                            v-html="link.label"
                        />
                        <button
                            v-else
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-accent'
                            "
                            v-html="link.label"
                            @click="irAPagina(link.url)"
                        />
                    </template>
                </nav>
            </div>
        </div>
    </div>

    <Sheet v-model:open="detalleAbierto">
        <SheetContent class="w-full gap-0 overflow-y-auto p-0 sm:max-w-md">
            <template v-if="detalleOrden">
                <SheetHeader
                    class="bg-primary/5 flex flex-col gap-3 border-b border-dashed px-6 py-5 pr-12"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                        >
                            <PackagePlus class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <SheetTitle class="truncate tabular-nums"
                                >Compra
                                {{ detalleOrden.numero_orden }}</SheetTitle
                            >
                            <SheetDescription class="text-xs">
                                {{ fmtFecha(detalleOrden.fecha) }} ·
                                {{ fmtHora(detalleOrden.created_at) }}
                            </SheetDescription>
                        </div>
                    </div>
                    <Badge
                        variant="outline"
                        :class="ESTADO_ESTILOS[detalleOrden.estado]"
                        class="w-fit"
                    >
                        {{ ESTADO_ETIQUETAS[detalleOrden.estado] }}
                    </Badge>
                </SheetHeader>

                <div class="flex flex-col gap-6 px-6 py-5">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold">Productos</h3>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="linea in detalleOrden.detalles"
                                :key="linea.id"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ linea.producto.nombre }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ Number(linea.cantidad) }} ×
                                        {{ fmtUsd(linea.costo_unitario) }}
                                    </p>
                                </div>
                                <p class="shrink-0 font-medium">
                                    {{ fmtUsd(linea.subtotal) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 text-sm">
                        <h3 class="text-sm font-semibold">Detalles</h3>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Proveedor</span>
                            <span v-if="detalleOrden.proveedor">{{
                                detalleOrden.proveedor.nombre
                            }}</span>
                            <span v-else class="text-muted-foreground italic"
                                >Sin proveedor</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Registrada por</span
                            >
                            <span>{{ detalleOrden.usuario.name }}</span>
                        </div>
                        <div
                            v-if="detalleOrden.tasa_cambio"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Tasa BCV</span>
                            <span
                                >{{ fmtBs(detalleOrden.tasa_cambio.tasa) }} /
                                USD</span
                            >
                        </div>
                        <div v-if="detalleOrden.notas" class="flex flex-col">
                            <span class="text-muted-foreground">Notas</span>
                            <span>{{ detalleOrden.notas }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 text-sm">
                        <h3 class="text-sm font-semibold">Totales</h3>
                        <Separator />
                        <div
                            class="flex justify-between text-base font-semibold"
                        >
                            <span>Total</span>
                            <span>{{ fmtUsd(detalleOrden.total) }}</span>
                        </div>
                        <div
                            v-if="detalleOrden.tasa_cambio"
                            class="text-muted-foreground flex justify-between"
                        >
                            <span>Equivalente</span>
                            <span>{{
                                totalBs(
                                    detalleOrden.total,
                                    detalleOrden.tasa_cambio,
                                )
                            }}</span>
                        </div>
                    </div>

                    <Button
                        v-if="detalleOrden.estado !== 'cancelada'"
                        variant="outline"
                        class="text-destructive hover:text-destructive w-full"
                        @click="anularCompra(detalleOrden)"
                    >
                        <Ban class="size-4" />
                        Anular compra
                    </Button>
                </div>
            </template>

            <div v-else class="flex flex-1 items-center justify-center p-6">
                <Loader2 class="text-muted-foreground size-6 animate-spin" />
            </div>
        </SheetContent>
    </Sheet>
</template>
