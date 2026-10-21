<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useHttp } from '@inertiajs/vue3';
import { ChevronRight, ClipboardList, Lock, Mail, MailX } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { cerrarCaja as cerrarCajaAction } from '@/actions/App/Http/Controllers/CierreCajaController';
import AlertError from '@/components/AlertError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    esMetodoEfectivo,
    fmtBs,
    fmtFecha,
    fmtHora,
    fmtUsd,
} from '@/lib/ventas';
import { index as cierresCajaIndex } from '@/routes/cierres_caja';

interface MetodoPagoOption {
    id: number;
    nombre: string;
    moneda: 'USD' | 'VES';
}

interface VentaCredito {
    id: number;
    numero_venta: string;
    total: string;
    estado: string;
    cliente: { id: number; nombre: string } | null;
    tasa_cambio: { tasa: string } | null;
}

// las ventas a crédito solo traen su propio total en USD + la tasa con la
// que se facturaron (puede ser de hoy o, si vienen de días anteriores en el
// historial, la de ese día) — el Bs se calcula aquí, igual que en ventas/Index.vue
function totalBsDeVenta(venta: VentaCredito): number {
    return Number(venta.total) * Number(venta.tasa_cambio?.tasa ?? 0);
}

// --- Desglose por método de pago -------------------------------------------
// el negocio piensa en bolívares primero, así que ahí se muestra en negrita
// arriba y el equivalente en USD abajo. La excepción es la caja física en
// divisas ("Efectivo USD"): se rotula "Divisas USD", se muestra en USD
// primero y Bs después, y siempre va de última en la lista.

interface MetodoVisual {
    nombre: string;
    moneda: string;
}

function esDivisasUsd(metodo: MetodoVisual): boolean {
    return metodo.moneda === 'USD' && esMetodoEfectivo(metodo);
}

function etiquetaDesglose(metodo: MetodoVisual): string {
    return esDivisasUsd(metodo) ? 'Divisas USD' : metodo.nombre;
}

function ordenarPorMetodo<T>(
    items: T[],
    metodo: (item: T) => MetodoVisual,
): T[] {
    return [...items].sort(
        (a, b) =>
            Number(esDivisasUsd(metodo(a))) - Number(esDivisasUsd(metodo(b))),
    );
}

interface CierreCajaAbierto {
    id: number;
    fecha: string;
    hora_apertura: string | null;
    usuario: { id: number; name: string } | null;
}

// lo vendido se parte en membresías y productos; productos es lo que sobra del
// total, así las dos partes siempre suman el total vendido
function productosDe(total: number | string, membresias: number | string) {
    return Number(total) - Number(membresias);
}

interface ResumenCierre {
    total_ventas: number;
    total_ventas_bs: number;
    total_membresias: number;
    total_membresias_bs: number;
    desglose_metodos: {
        metodo_pago_id: number;
        nombre: string;
        moneda: string;
        monto: string;
        monto_bs: string;
    }[];
    ventas_credito: VentaCredito[];
}

interface DetalleMetodoFila {
    id: number;
    monto: string;
    monto_bs: string;
    metodo_pago: MetodoPagoOption;
}

interface CierreCajaHistorial {
    id: number;
    fecha: string;
    hora_apertura: string | null;
    hora_cierre: string | null;
    total_ventas: string;
    total_ventas_bs: string;
    // null en los cierres anteriores a la separación productos / membresías
    total_membresias: string | null;
    total_membresias_bs: string | null;
    correo_enviado_at: string | null;
    usuario: { id: number; name: string };
    detalle_metodos: DetalleMetodoFila[];
    ventas_credito: VentaCredito[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface HistorialPaginado {
    data: CierreCajaHistorial[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    cierreAbierto: CierreCajaAbierto | null;
    resumenAbierto: ResumenCierre | null;
    historial: HistorialPaginado;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cierre de caja', href: cierresCajaIndex() }],
    },
});

function recargar() {
    router.reload({ only: ['cierreAbierto', 'resumenAbierto', 'historial'] });
}

// --- Cerrar caja ------------------------------------------------------------

interface CierreCajaCerrado {
    correo_enviado_at: string | null;
}

const httpCerrar = useHttp<Record<string, never>, CierreCajaCerrado>({});

function cerrarCaja() {
    if (!props.cierreAbierto) {
        return;
    }

    httpCerrar.post(
        cerrarCajaAction.url({ cierre_caja: props.cierreAbierto.id }),
        {
            onSuccess: (data) => {
                const mensajeCorreo = data.correo_enviado_at
                    ? 'Reporte enviado por correo.'
                    : 'Cierre guardado (el correo no se envió; revisa la configuración de correo).';
                toast.success(`Caja cerrada. ${mensajeCorreo}`);
                recargar();
            },
            onError: () => toast.error('No se pudo cerrar la caja.'),
        },
    );
}

const erroresGenerales = computed(() => Object.values(httpCerrar.errors));

// --- Detalle de un cierre ya cerrado (reporte) -----------------------------
// el historial ya trae todo lo que necesita el detalle, así que el panel
// solo muestra lo que vino cargado en la fila: no hace falta pedir nada más
// al servidor.

const detalleAbierto = ref(false);
const detalleCierre = ref<CierreCajaHistorial | null>(null);

function abrirDetalle(cierre: CierreCajaHistorial) {
    detalleCierre.value = cierre;
    detalleAbierto.value = true;
}
</script>

<template>
    <Head title="Cierre de caja" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <h1 class="text-xl font-semibold">Cierre de caja</h1>

        <AlertError v-if="erroresGenerales.length" :errors="erroresGenerales" />

        <!-- sin caja abierta todavía: se abrirá solo con la primera venta del día -->
        <div
            v-if="!cierreAbierto"
            class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
        >
            Todavía no se ha registrado ninguna venta hoy. La caja se abre
            automáticamente con la primera venta del día.
        </div>

        <!-- caja abierta: resumen en vivo y botón de cierre -->
        <template v-else>
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Lock class="text-muted-foreground size-4" />
                        Caja abierta
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Abierta por</span>
                        <span>{{ cierreAbierto.usuario?.name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Hora de apertura</span
                        >
                        <span v-if="cierreAbierto.hora_apertura">{{
                            fmtHora(cierreAbierto.hora_apertura)
                        }}</span>
                        <span v-else>—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Total vendido</span>
                        <span class="flex flex-col items-end">
                            <span class="font-semibold">{{
                                fmtBs(resumenAbierto?.total_ventas_bs ?? 0)
                            }}</span>
                            <span class="text-muted-foreground text-xs">{{
                                fmtUsd(resumenAbierto?.total_ventas ?? 0)
                            }}</span>
                        </span>
                    </div>
                    <div
                        v-if="resumenAbierto"
                        class="flex flex-col gap-2 border-t pt-3"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Productos</span>
                            <span class="flex flex-col items-end">
                                <span class="font-medium">{{
                                    fmtBs(
                                        productosDe(
                                            resumenAbierto.total_ventas_bs,
                                            resumenAbierto.total_membresias_bs,
                                        ),
                                    )
                                }}</span>
                                <span class="text-muted-foreground text-xs">{{
                                    fmtUsd(
                                        productosDe(
                                            resumenAbierto.total_ventas,
                                            resumenAbierto.total_membresias,
                                        ),
                                    )
                                }}</span>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Membresías</span
                            >
                            <span class="flex flex-col items-end">
                                <span class="font-medium">{{
                                    fmtBs(resumenAbierto.total_membresias_bs)
                                }}</span>
                                <span class="text-muted-foreground text-xs">{{
                                    fmtUsd(resumenAbierto.total_membresias)
                                }}</span>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >Desglose por método de pago</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div
                        v-if="!resumenAbierto?.desglose_metodos.length"
                        class="text-muted-foreground text-sm"
                    >
                        Todavía no se ha recibido ningún pago hoy.
                    </div>
                    <div
                        v-for="metodo in ordenarPorMetodo(
                            resumenAbierto?.desglose_metodos ?? [],
                            (m) => m,
                        )"
                        :key="metodo.metodo_pago_id"
                        class="flex items-center justify-between border-b pb-2 text-sm last:border-0 last:pb-0"
                    >
                        <span class="font-medium">{{
                            etiquetaDesglose(metodo)
                        }}</span>
                        <span
                            v-if="esDivisasUsd(metodo)"
                            class="flex flex-col items-end tabular-nums"
                        >
                            <span class="font-semibold">{{
                                fmtUsd(metodo.monto)
                            }}</span>
                            <span class="text-muted-foreground text-xs">{{
                                fmtBs(metodo.monto_bs)
                            }}</span>
                        </span>
                        <span
                            v-else
                            class="flex flex-col items-end tabular-nums"
                        >
                            <span class="font-semibold">{{
                                fmtBs(metodo.monto_bs)
                            }}</span>
                            <span class="text-muted-foreground text-xs">{{
                                fmtUsd(metodo.monto)
                            }}</span>
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >Ventas a crédito de hoy</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div
                        v-if="!resumenAbierto?.ventas_credito.length"
                        class="text-muted-foreground text-sm"
                    >
                        No hay ventas a crédito registradas hoy.
                    </div>
                    <div
                        v-for="venta in resumenAbierto?.ventas_credito ?? []"
                        :key="venta.id"
                        class="flex items-center justify-between border-b pb-2 text-sm last:border-0 last:pb-0"
                    >
                        <span>
                            <span class="font-medium">{{
                                venta.numero_venta
                            }}</span>
                            <span class="text-muted-foreground">
                                — {{ venta.cliente?.nombre ?? '—' }}</span
                            >
                        </span>
                        <span class="flex flex-col items-end tabular-nums">
                            <span class="font-semibold">{{
                                fmtUsd(venta.total)
                            }}</span>
                            <span class="text-muted-foreground text-xs">{{
                                fmtBs(totalBsDeVenta(venta))
                            }}</span>
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Button
                class="w-fit"
                :disabled="httpCerrar.processing"
                @click="cerrarCaja"
            >
                {{
                    httpCerrar.processing
                        ? 'Cerrando...'
                        : 'Cerrar caja y enviar reporte'
                }}
            </Button>
        </template>

        <!-- historial de cierres ya cerrados -->
        <div class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Historial de cierres</h2>

            <div
                v-if="historial.data.length === 0"
                class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
            >
                Todavía no hay cierres registrados.
            </div>

            <Card v-else class="overflow-hidden py-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="text-muted-foreground border-b text-left"
                            >
                                <th class="px-4 py-3 font-medium">Fecha</th>
                                <th class="px-4 py-3 font-medium">
                                    Responsable
                                </th>
                                <th class="px-4 py-3 font-medium">Total</th>
                                <th class="px-4 py-3 font-medium">Correo</th>
                                <th class="px-4 py-3">
                                    <span class="sr-only">Reporte</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="cierre in historial.data"
                                :key="cierre.id"
                                class="hover:bg-accent/50 cursor-pointer border-b transition-colors last:border-0"
                                @click="abrirDetalle(cierre)"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ fmtFecha(cierre.fecha) }}
                                </td>
                                <td class="text-muted-foreground px-4 py-3">
                                    {{ cierre.usuario.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span>{{
                                            fmtUsd(cierre.total_ventas)
                                        }}</span>
                                        <span
                                            class="text-muted-foreground text-xs"
                                            >{{
                                                fmtBs(cierre.total_ventas_bs)
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        v-if="cierre.correo_enviado_at"
                                        variant="outline"
                                        class="gap-1 border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        <Mail class="size-3" />
                                        Enviado
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="text-muted-foreground gap-1"
                                    >
                                        <MailX class="size-3" />
                                        Sin enviar
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="text-muted-foreground"
                                        @click.stop="abrirDetalle(cierre)"
                                    >
                                        <ChevronRight class="size-4" />
                                        <span class="sr-only">Ver reporte</span>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <p
                v-if="historial.data.length"
                class="text-muted-foreground text-sm"
            >
                Mostrando {{ historial.from }}–{{ historial.to }} de
                {{ historial.total }}
            </p>
        </div>
    </div>

    <Sheet v-model:open="detalleAbierto">
        <SheetContent class="w-full gap-0 overflow-y-auto p-0 sm:max-w-md">
            <template v-if="detalleCierre">
                <SheetHeader
                    class="bg-primary/5 flex flex-col gap-3 border-b border-dashed px-6 py-5 pr-12"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                        >
                            <ClipboardList class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <SheetTitle class="truncate"
                                >Cierre del
                                {{ fmtFecha(detalleCierre.fecha) }}</SheetTitle
                            >
                            <SheetDescription class="text-xs">
                                {{ detalleCierre.usuario.name }}
                                <span v-if="detalleCierre.hora_apertura">
                                    ·
                                    {{ fmtHora(detalleCierre.hora_apertura) }} –
                                </span>
                                <span v-if="detalleCierre.hora_cierre">{{
                                    fmtHora(detalleCierre.hora_cierre)
                                }}</span>
                            </SheetDescription>
                        </div>
                    </div>
                    <Badge
                        v-if="detalleCierre.correo_enviado_at"
                        variant="outline"
                        class="w-fit gap-1 border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                    >
                        <Mail class="size-3" />
                        Correo enviado
                    </Badge>
                    <Badge
                        v-else
                        variant="outline"
                        class="text-muted-foreground w-fit gap-1"
                    >
                        <MailX class="size-3" />
                        Correo sin enviar
                    </Badge>
                </SheetHeader>

                <div class="flex flex-col gap-6 px-6 py-5">
                    <div class="flex justify-between text-base">
                        <span class="font-semibold">Total vendido</span>
                        <span class="flex flex-col items-end">
                            <span class="font-semibold">{{
                                fmtBs(detalleCierre.total_ventas_bs)
                            }}</span>
                            <span class="text-muted-foreground text-xs">{{
                                fmtUsd(detalleCierre.total_ventas)
                            }}</span>
                        </span>
                    </div>

                    <div
                        v-if="
                            detalleCierre.total_membresias !== null &&
                            detalleCierre.total_membresias_bs !== null
                        "
                        class="-mt-3 flex flex-col gap-2 text-sm"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Productos</span>
                            <span class="flex flex-col items-end">
                                <span class="font-medium">{{
                                    fmtBs(
                                        productosDe(
                                            detalleCierre.total_ventas_bs,
                                            detalleCierre.total_membresias_bs,
                                        ),
                                    )
                                }}</span>
                                <span class="text-muted-foreground text-xs">{{
                                    fmtUsd(
                                        productosDe(
                                            detalleCierre.total_ventas,
                                            detalleCierre.total_membresias,
                                        ),
                                    )
                                }}</span>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Membresías</span
                            >
                            <span class="flex flex-col items-end">
                                <span class="font-medium">{{
                                    fmtBs(detalleCierre.total_membresias_bs)
                                }}</span>
                                <span class="text-muted-foreground text-xs">{{
                                    fmtUsd(detalleCierre.total_membresias)
                                }}</span>
                            </span>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-sm font-semibold">
                            Desglose por método de pago
                        </h3>
                        <div
                            v-if="!detalleCierre.detalle_metodos.length"
                            class="text-muted-foreground text-sm"
                        >
                            No se recibieron pagos este día.
                        </div>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="detalle in ordenarPorMetodo(
                                    detalleCierre.detalle_metodos,
                                    (d) => d.metodo_pago,
                                )"
                                :key="detalle.id"
                                class="flex items-center justify-between rounded-md border p-2.5 text-sm"
                            >
                                <span class="font-medium">{{
                                    etiquetaDesglose(detalle.metodo_pago)
                                }}</span>
                                <span
                                    v-if="esDivisasUsd(detalle.metodo_pago)"
                                    class="flex flex-col items-end"
                                >
                                    <span class="font-semibold">{{
                                        fmtUsd(detalle.monto)
                                    }}</span>
                                    <span
                                        class="text-muted-foreground text-xs"
                                        >{{ fmtBs(detalle.monto_bs) }}</span
                                    >
                                </span>
                                <span v-else class="flex flex-col items-end">
                                    <span class="font-semibold">{{
                                        fmtBs(detalle.monto_bs)
                                    }}</span>
                                    <span
                                        class="text-muted-foreground text-xs"
                                        >{{ fmtUsd(detalle.monto) }}</span
                                    >
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-sm font-semibold">
                            Ventas a crédito del día
                        </h3>
                        <div
                            v-if="!detalleCierre.ventas_credito.length"
                            class="text-muted-foreground text-sm"
                        >
                            No hubo ventas a crédito este día.
                        </div>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="venta in detalleCierre.ventas_credito"
                                :key="venta.id"
                                class="flex items-center justify-between rounded-md border p-2.5 text-sm"
                            >
                                <span>
                                    <span class="font-medium">{{
                                        venta.numero_venta
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        —
                                        {{ venta.cliente?.nombre ?? '—' }}</span
                                    >
                                </span>
                                <span class="flex flex-col items-end">
                                    <span class="font-semibold">{{
                                        fmtUsd(venta.total)
                                    }}</span>
                                    <span
                                        class="text-muted-foreground text-xs"
                                        >{{
                                            fmtBs(totalBsDeVenta(venta))
                                        }}</span
                                    >
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
