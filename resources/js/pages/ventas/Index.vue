<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue';
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import {
    CalendarDate,
    getLocalTimeZone,
    type DateValue,
} from '@internationalized/date';
import {
    Ban,
    Calendar as CalendarIcon,
    ChevronRight,
    Loader2,
    Receipt,
    Wallet,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    ESTADO_ESTILOS,
    ESTADO_ETIQUETAS,
    etiquetaMetodo,
    fmtBs,
    fmtFecha,
    fmtHora,
    fmtUsd,
    iconoMetodo,
    type EstadoVenta,
} from '@/lib/ventas';
import { toast } from 'vue-sonner';
import {
    anular,
    index as ventasIndex,
    show as ventaShow,
} from '@/routes/ventas';

type Periodo = 'hoy' | 'semana' | 'mes' | 'personalizado';
type Estado = EstadoVenta;

interface MetodoPagoResumen {
    id: number;
    nombre: string;
    moneda: 'USD' | 'VES';
}

interface PagoVenta {
    id: number;
    metodo_pago: MetodoPagoResumen;
}

interface VentaFila {
    id: number;
    numero_venta: string;
    fecha: string;
    created_at: string;
    total: string;
    // la parte de productos: una venta mixta (con membresía) solo cuenta esto aquí
    total_productos: string;
    incluye_membresia: boolean;
    estado: Estado;
    tasa_cambio: { tasa: string } | null;
    pagos: PagoVenta[];
}

// --- Detalle de una venta (panel lateral) --------------------------------

interface VentaDetalleLinea {
    id: number;
    cantidad: string;
    precio_unitario: string;
    subtotal: string;
    es_membresia: boolean;
    producto: { id: number; nombre: string };
}

interface VentaPagoLinea {
    id: number;
    monto: string;
    referencia: string | null;
    fecha_pago: string;
    metodo_pago: { id: number; nombre: string; moneda: 'USD' | 'VES' };
}

interface VentaDetalleCompleta {
    id: number;
    numero_venta: string;
    fecha: string;
    created_at: string;
    subtotal: string;
    descuento: string;
    total: string;
    es_credito: boolean;
    monto_recibido: string | null;
    vuelto: string | null;
    estado: Estado;
    saldo_pendiente: number;
    cliente: {
        id: number;
        nombre: string;
        documento: string | null;
        telefono: string | null;
    } | null;
    usuario: { id: number; name: string };
    anulada_en: string | null;
    motivo_anulacion: string | null;
    anulada_por: { id: number; name: string } | null;
    tasa_cambio: { id: number; tasa: string; fecha: string } | null;
    detalles: VentaDetalleLinea[];
    pagos: VentaPagoLinea[];
}

interface DesgloseMetodo {
    id: number;
    nombre: string;
    moneda: string;
    // el monto cobrado siempre se guarda en USD; total_bs es su equivalente
    // calculado con la tasa de cada venta, no con la de hoy
    total: string;
    total_bs: string;
}

interface Resumen {
    cantidad_ventas: number;
    total_usd: number;
    total_bs: number;
    por_metodo_pago: DesgloseMetodo[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface VentasPaginadas {
    data: VentaFila[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    ventas: VentasPaginadas;
    resumen: Resumen;
    filtros: { periodo: Periodo; desde: string; hasta: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ventas', href: ventasIndex() }],
    },
});

// cada método muestra su monto en su propia moneda: los de Bs con el
// equivalente ya convertido en el servidor, los de USD con el monto tal cual
function montoDeMetodo(metodo: DesgloseMetodo): string {
    return metodo.moneda === 'VES'
        ? fmtBs(metodo.total_bs)
        : fmtUsd(metodo.total);
}

function metodosDe(venta: VentaFila): string {
    if (!venta.pagos.length) {
        return '—';
    }
    return [
        ...new Set(venta.pagos.map((pago) => pago.metodo_pago.nombre)),
    ].join(' + ');
}

const SEGMENTOS_PERIODO: { value: Periodo; label: string }[] = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'semana', label: 'Esta semana' },
    { value: 'mes', label: 'Este mes' },
];

const cargando = ref(false);

// el backend habla en strings "YYYY-MM-DD"; el RangeCalendar habla en
// DateValue de @internationalized/date — estas dos funciones son el único
// punto donde se cruzan los dos mundos.
function stringAFecha(fecha: string): CalendarDate {
    const [year, month, day] = fecha.split('-').map(Number);
    return new CalendarDate(year, month, day);
}

function fechaAString(valor: DateValue): string {
    return valor.toString();
}

function fechaCorta(valor: DateValue): string {
    return valor.toDate(getLocalTimeZone()).toLocaleDateString('es-VE', {
        day: 'numeric',
        month: 'short',
    });
}

// shallowRef a propósito: CalendarDate trae campos privados que la
// reactividad profunda de Vue (UnwrapRef) desarma al tipar, rompiendo la
// asignabilidad a DateValue. El RangeCalendar de todas formas reemplaza el
// objeto completo en cada cambio, nunca muta start/end en el sitio.
const rango = shallowRef<{
    start: DateValue | undefined;
    end: DateValue | undefined;
}>({
    start: stringAFecha(props.filtros.desde),
    end: stringAFecha(props.filtros.hasta),
});
const popoverRangoAbierto = ref(false);

const etiquetaRango = computed(() => {
    if (!rango.value.start || !rango.value.end) {
        return 'Rango personalizado';
    }
    return `${fechaCorta(rango.value.start)} – ${fechaCorta(rango.value.end)}`;
});

watch(
    () => props.filtros,
    (filtros) => {
        rango.value = {
            start: stringAFecha(filtros.desde),
            end: stringAFecha(filtros.hasta),
        };
    },
);

function irA(periodo: Periodo, extra: Record<string, string> = {}) {
    cargando.value = true;
    router.get(
        ventasIndex.url(),
        { periodo, ...extra },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['ventas', 'resumen', 'filtros'],
            onFinish: () => (cargando.value = false),
        },
    );
}

function aplicarRangoPersonalizado() {
    if (!rango.value.start || !rango.value.end) {
        return;
    }
    popoverRangoAbierto.value = false;
    irA('personalizado', {
        desde: fechaAString(rango.value.start),
        hasta: fechaAString(rango.value.end),
    });
}

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
            only: ['ventas'],
            onFinish: () => (cargando.value = false),
        },
    );
}

// --- Detalle de una venta (panel lateral) --------------------------------

const detalleAbierto = ref(false);
const detalleVenta = ref<VentaDetalleCompleta | null>(null);
const httpDetalle = useHttp<Record<string, never>, VentaDetalleCompleta>({});

function abrirDetalle(id: number) {
    detalleAbierto.value = true;
    detalleVenta.value = null;
    httpDetalle.get(ventaShow.url(id), {
        onSuccess: (data) => {
            detalleVenta.value = data;
        },
    });
}

// anular es solo del admin (la ruta lo exige también en el servidor)
const esAdmin = computed(() => usePage().props.auth.user.rol === 'admin');

// Anular no borra ni edita: devuelve el stock, cancela la membresía y deja la
// venta marcada con el motivo. Si la caja ya se cerró, el servidor lo rechaza.
function anularVenta(venta: VentaDetalleCompleta) {
    const motivo = window.prompt(
        `¿Anular la venta ${venta.numero_venta}? Se devolverá el stock y se cancelará la membresía que incluya.\n\nMotivo de la anulación:`,
    );

    if (motivo === null) {
        return;
    }

    if (!motivo.trim()) {
        toast.error('Indica el motivo de la anulación.');
        return;
    }

    router.post(
        anular.url(venta.id),
        { motivo: motivo.trim() },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['ventas', 'resumen'],
            onSuccess: () => abrirDetalle(venta.id),
            onError: (errores) => {
                toast.error(
                    errores.anular ??
                        errores.motivo ??
                        'No se pudo anular la venta.',
                );
            },
        },
    );
}

// venta_pagos.monto siempre se guarda en USD, sin importar la moneda del
// método (ver comentario en VentaController::index); el equivalente en Bs
// se calcula aquí con la tasa que quedó congelada en esta venta, nunca con
// la tasa de hoy
function montoPagoFormateado(pago: VentaPagoLinea): string {
    if (pago.metodo_pago.moneda !== 'VES') {
        return fmtUsd(pago.monto);
    }
    const tasa = detalleVenta.value?.tasa_cambio?.tasa;
    return tasa ? fmtBs(Number(pago.monto) * Number(tasa)) : fmtUsd(pago.monto);
}
</script>

<template>
    <Head title="Ventas" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <!-- el filtro vive en la cabecera de la página, alineado con el
        título, en vez de ocupar una tarjeta propia: es un control de la
        vista, no un dato del resumen. Control segmentado (estilo iOS): un
        solo riel gris neutro con la opción activa en vinotinto de marca; el
        rango personalizado es un cuarto segmento que abre el calendario. -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- "Ventas" ya lo dice la migaja de pan del layout; el título de
            la página nombra lo que se está viendo, no la sección -->
            <h1 class="text-xl font-semibold">Resumen general</h1>

            <div
                class="inline-flex items-center gap-1 rounded-full bg-neutral-500/10 p-1"
            >
                <button
                    v-for="segmento in SEGMENTOS_PERIODO"
                    :key="segmento.value"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-all"
                    :class="
                        filtros.periodo === segmento.value
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="irA(segmento.value)"
                >
                    {{ segmento.label }}
                </button>

                <Popover v-model:open="popoverRangoAbierto">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all"
                            :class="
                                filtros.periodo === 'personalizado'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <CalendarIcon class="size-3.5" />
                            {{ etiquetaRango }}
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0" align="end">
                        <RangeCalendar v-model="rango" :number-of-months="2" />
                        <div class="flex justify-end border-t p-3">
                            <Button
                                type="button"
                                size="sm"
                                :disabled="!rango.start || !rango.end"
                                @click="aplicarRangoPersonalizado"
                            >
                                Aplicar
                            </Button>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 transition-opacity duration-150"
            :class="{ 'pointer-events-none opacity-60': cargando }"
        >
            <div class="grid gap-3 sm:grid-cols-2">
                <Card class="py-3 sm:col-span-2">
                    <CardContent class="flex items-center gap-3 px-3">
                        <span
                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                        >
                            <Wallet class="size-5" />
                        </span>
                        <div class="flex min-w-0 flex-col">
                            <p class="text-muted-foreground text-xs">
                                Total vendido
                            </p>
                            <p class="text-2xl leading-tight font-semibold">
                                {{ fmtUsd(resumen.total_usd) }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                {{ fmtBs(resumen.total_bs) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- desglose por método de pago: siempre están todos los métodos
            activos, aunque alguno no haya movido dinero en el período, con
            el mismo ícono que usa la pantalla de cobro para reconocerlos sin
            leer el nombre. Los que quedaron en cero se apagan en vez de
            desaparecer, para que la grilla no salte al cambiar el filtro. -->
            <div
                v-if="resumen.por_metodo_pago.length"
                class="grid grid-cols-2 gap-3 sm:flex sm:flex-nowrap"
            >
                <!-- las columnas no son iguales a propósito: un monto en Bs
                ("Bs. 1.234.567,89") ocupa el doble que uno en divisas
                ("$100"), así que los métodos en bolívares se llevan el ancho
                sobrante en vez de recortar el número con puntos suspensivos -->
                <div
                    v-for="metodo in resumen.por_metodo_pago"
                    :key="metodo.id"
                    class="bg-card flex items-center gap-2 rounded-xl border p-2 shadow-sm sm:min-w-0"
                    :class="[
                        Number(metodo.total) === 0 ? 'opacity-55' : '',
                        metodo.moneda === 'VES'
                            ? 'sm:flex-[1.2]'
                            : 'sm:flex-[0.9]',
                    ]"
                >
                    <span
                        class="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg"
                    >
                        <component :is="iconoMetodo(metodo)" class="size-4" />
                    </span>
                    <div class="flex min-w-0 flex-col">
                        <p
                            class="truncate text-base leading-tight font-semibold tabular-nums"
                            :title="montoDeMetodo(metodo)"
                        >
                            {{ montoDeMetodo(metodo) }}
                        </p>
                        <p class="text-muted-foreground truncate text-xs">
                            {{ etiquetaMetodo(metodo) }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="ventas.data.length === 0"
                class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
            >
                No hay ventas en este rango.
            </div>

            <Card v-else class="overflow-hidden py-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="text-muted-foreground border-b text-left"
                            >
                                <th class="px-4 py-3 font-medium">Venta</th>
                                <th class="px-4 py-3 font-medium">Fecha</th>
                                <th class="px-4 py-3 font-medium">Método(s)</th>
                                <th class="px-4 py-3 font-medium">Total</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3">
                                    <span class="sr-only">Detalle</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="venta in ventas.data"
                                :key="venta.id"
                                class="hover:bg-accent/50 cursor-pointer border-b transition-colors last:border-0"
                                @click="abrirDetalle(venta.id)"
                            >
                                <td
                                    class="px-4 py-3 font-medium whitespace-nowrap"
                                >
                                    {{ venta.numero_venta }}
                                </td>
                                <td
                                    class="text-muted-foreground px-4 py-3 whitespace-nowrap"
                                >
                                    {{ fmtFecha(venta.fecha) }} ·
                                    {{ fmtHora(venta.created_at) }}
                                </td>
                                <td class="text-muted-foreground px-4 py-3">
                                    {{ metodosDe(venta) }}
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">
                                        {{ fmtUsd(venta.total_productos) }}
                                    </p>
                                    <p
                                        v-if="venta.incluye_membresia"
                                        class="text-muted-foreground text-xs"
                                    >
                                        Solo productos · incluye membresía
                                    </p>
                                    <p
                                        v-if="venta.tasa_cambio"
                                        class="text-muted-foreground text-xs"
                                    >
                                        {{
                                            fmtBs(
                                                Number(venta.total_productos) *
                                                    Number(
                                                        venta.tasa_cambio.tasa,
                                                    ),
                                            )
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant="outline"
                                        :class="ESTADO_ESTILOS[venta.estado]"
                                    >
                                        {{ ESTADO_ETIQUETAS[venta.estado] }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="text-muted-foreground"
                                        @click.stop="abrirDetalle(venta.id)"
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
                v-if="ventas.data.length"
                class="flex items-center justify-between"
            >
                <p class="text-muted-foreground text-sm">
                    Mostrando {{ ventas.from }}–{{ ventas.to }} de
                    {{ ventas.total }}
                </p>
                <nav
                    v-if="ventas.links.length > 3"
                    class="flex flex-wrap items-center gap-1"
                >
                    <template
                        v-for="(link, index) in ventas.links"
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
            <template v-if="detalleVenta">
                <SheetHeader
                    class="bg-primary/5 flex flex-col gap-3 border-b border-dashed px-6 py-5 pr-12"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                        >
                            <Receipt class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <SheetTitle class="truncate tabular-nums"
                                >Venta
                                {{ detalleVenta.numero_venta }}</SheetTitle
                            >
                            <SheetDescription class="text-xs">
                                {{ fmtFecha(detalleVenta.fecha) }} ·
                                {{ fmtHora(detalleVenta.created_at) }}
                            </SheetDescription>
                        </div>
                    </div>
                    <Badge
                        variant="outline"
                        :class="ESTADO_ESTILOS[detalleVenta.estado]"
                        class="w-fit"
                    >
                        {{ ESTADO_ETIQUETAS[detalleVenta.estado] }}
                    </Badge>
                </SheetHeader>

                <div class="flex flex-col gap-6 px-6 py-5">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold">Productos</h3>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="linea in detalleVenta.detalles.filter(
                                    (l) => !l.es_membresia,
                                )"
                                :key="linea.id"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ linea.producto.nombre }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ Number(linea.cantidad) }} ×
                                        {{ fmtUsd(linea.precio_unitario) }}
                                    </p>
                                </div>
                                <p class="shrink-0 font-medium">
                                    {{ fmtUsd(linea.subtotal) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="detalleVenta.detalles.some((l) => l.es_membresia)"
                        class="bg-muted text-muted-foreground rounded-md px-3 py-2 text-xs"
                    >
                        Esta venta también incluyó una membresía
                        ({{
                            fmtUsd(
                                detalleVenta.detalles
                                    .filter((l) => l.es_membresia)
                                    .reduce((a, l) => a + Number(l.subtotal), 0),
                            )
                        }}), que se ve en Membresías vendidas. Los pagos y
                        totales de abajo son los de la venta completa.
                    </p>

                    <div>
                        <h3 class="mb-2 text-sm font-semibold">Pagos</h3>
                        <p
                            v-if="!detalleVenta.pagos.length"
                            class="text-muted-foreground text-sm"
                        >
                            Esta venta no tiene pagos registrados todavía.
                        </p>
                        <div v-else class="flex flex-col gap-2">
                            <div
                                v-for="pago in detalleVenta.pagos"
                                :key="pago.id"
                                class="flex items-center justify-between rounded-md border p-2.5 text-sm"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ pago.metodo_pago.nombre }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ fmtFecha(pago.fecha_pago) }}
                                        <span v-if="pago.referencia">
                                            · Ref. {{ pago.referencia }}</span
                                        >
                                    </p>
                                </div>
                                <p class="font-medium">
                                    {{ montoPagoFormateado(pago) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 text-sm">
                        <h3 class="text-sm font-semibold">Detalles</h3>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Cliente</span>
                            <span v-if="detalleVenta.cliente">{{
                                detalleVenta.cliente.nombre
                            }}</span>
                            <span v-else class="text-muted-foreground italic"
                                >Sin cliente</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Vendedor</span>
                            <span>{{ detalleVenta.usuario.name }}</span>
                        </div>
                        <div
                            v-if="detalleVenta.tasa_cambio"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Tasa BCV</span>
                            <span
                                >{{ fmtBs(detalleVenta.tasa_cambio.tasa) }} /
                                USD</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tipo</span>
                            <span>{{
                                detalleVenta.es_credito ? 'Crédito' : 'Contado'
                            }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 text-sm">
                        <h3 class="text-sm font-semibold">Totales</h3>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ fmtUsd(detalleVenta.subtotal) }}</span>
                        </div>
                        <div
                            v-if="Number(detalleVenta.descuento) > 0"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Descuento</span>
                            <span>-{{ fmtUsd(detalleVenta.descuento) }}</span>
                        </div>
                        <Separator />
                        <div
                            class="flex justify-between text-base font-semibold"
                        >
                            <span>Total</span>
                            <span>{{ fmtUsd(detalleVenta.total) }}</span>
                        </div>

                        <template v-if="!detalleVenta.es_credito">
                            <div
                                v-if="detalleVenta.monto_recibido"
                                class="flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Recibido</span
                                >
                                <span>{{
                                    fmtUsd(detalleVenta.monto_recibido)
                                }}</span>
                            </div>
                            <div
                                v-if="
                                    detalleVenta.vuelto &&
                                    Number(detalleVenta.vuelto) > 0
                                "
                                class="flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Vuelto</span
                                >
                                <span>{{ fmtUsd(detalleVenta.vuelto) }}</span>
                            </div>
                        </template>
                        <div
                            v-else-if="detalleVenta.saldo_pendiente > 0"
                            class="flex justify-between font-medium text-amber-600"
                        >
                            <span>Saldo pendiente</span>
                            <span>{{
                                fmtUsd(detalleVenta.saldo_pendiente)
                            }}</span>
                        </div>
                    </div>

                    <p
                        v-if="detalleVenta.estado === 'anulada'"
                        class="bg-muted text-muted-foreground rounded-md px-3 py-2 text-xs"
                    >
                        Venta anulada
                        <template v-if="detalleVenta.anulada_por">
                            por {{ detalleVenta.anulada_por.name }}
                        </template>
                        <template v-if="detalleVenta.anulada_en">
                            el {{ fmtFecha(detalleVenta.anulada_en) }} ·
                            {{ fmtHora(detalleVenta.anulada_en) }}
                        </template>
                        <template v-if="detalleVenta.motivo_anulacion">
                            — «{{ detalleVenta.motivo_anulacion }}»
                        </template>
                    </p>

                    <Button
                        v-else-if="esAdmin"
                        variant="outline"
                        class="text-destructive hover:text-destructive w-full"
                        @click="anularVenta(detalleVenta)"
                    >
                        <Ban class="size-4" />
                        Anular venta
                    </Button>
                </div>
            </template>

            <div v-else class="flex flex-1 items-center justify-center p-6">
                <Loader2 class="text-muted-foreground size-6 animate-spin" />
            </div>
        </SheetContent>
    </Sheet>
</template>
