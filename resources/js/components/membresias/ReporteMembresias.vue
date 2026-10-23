<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { FileText, Image as ImageIcon, Loader2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    descargarArchivo,
    generarImagen,
    nombreDeArchivo,
} from '@/lib/compartir';
import { fmtDia } from '@/lib/socios';
import { fmtBs, fmtUsd } from '@/lib/ventas';
import { reporte as rutaReporte } from '@/routes/membresias_vendidas';

// Reporte de las membresías vendidas en el período que esté filtrado: se pide
// completo al servidor (sin paginar) y se puede bajar como imagen o guardar como
// PDF desde el diálogo de impresión del navegador.
const props = defineProps<{
    filtros: {
        periodo: 'hoy' | 'semana' | 'mes' | 'personalizado';
        desde: string;
        hasta: string;
    };
}>();

interface Reporte {
    desde: string;
    hasta: string;
    generado: string;
    resumen: {
        cantidad: number;
        altas: number;
        renovaciones: number;
        total_usd: number;
        total_bs: number;
    };
    por_plan: {
        plan: string;
        cantidad: number;
        total_usd: number;
        total_bs: number;
    }[];
    filas: {
        socio: string;
        plan: string;
        fecha_inicio: string;
        fecha_vencimiento: string;
        numero_venta: string;
        fecha_venta: string;
        monto: number;
        monto_bs: number;
        es_alta: boolean;
    }[];
}

const page = usePage();

const abierto = ref(false);
const cargando = ref(false);
const error = ref<string | null>(null);
const datos = ref<Reporte | null>(null);
const hoja = ref<HTMLElement | null>(null);
const generando = ref(false);

const negocio = computed(() => (page.props.name as string) ?? '');
const logo = computed(
    () => (page.props.logoUrlLight as string | null | undefined) ?? null,
);

const titulo = computed(() => {
    if (!datos.value) {
        return '';
    }

    return datos.value.desde === datos.value.hasta
        ? fmtDia(datos.value.desde)
        : `${fmtDia(datos.value.desde)} al ${fmtDia(datos.value.hasta)}`;
});

const generado = computed(() =>
    datos.value
        ? new Date(datos.value.generado).toLocaleString('es-VE', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '',
);

async function abrir() {
    abierto.value = true;
    cargando.value = true;
    error.value = null;
    datos.value = null;

    try {
        const consulta = new URLSearchParams({
            periodo: props.filtros.periodo,
        });

        if (props.filtros.periodo === 'personalizado') {
            consulta.set('desde', props.filtros.desde);
            consulta.set('hasta', props.filtros.hasta);
        }

        const respuesta = await fetch(`${rutaReporte.url()}?${consulta}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!respuesta.ok) {
            throw new Error();
        }

        datos.value = await respuesta.json();
    } catch {
        error.value = 'No se pudo cargar el reporte. Inténtalo de nuevo.';
    } finally {
        cargando.value = false;
    }
}

function nombreBase(): string {
    return nombreDeArchivo(
        `membresias ${datos.value?.desde ?? ''} ${datos.value?.hasta ?? ''}`,
    );
}

async function descargarImagen() {
    if (!hoja.value || !datos.value) {
        return;
    }

    generando.value = true;

    try {
        const imagen = await generarImagen(hoja.value);

        descargarArchivo(
            new File([imagen], `${nombreBase()}.png`, { type: 'image/png' }),
        );
    } catch {
        toast.error('No se pudo generar la imagen.');
    } finally {
        generando.value = false;
    }
}

// Se imprime solo la hoja, dentro de un iframe oculto con los mismos estilos de
// la página: así el PDF ("Guardar como PDF" en el diálogo de impresión) sale
// igual que la vista previa y sin el resto de la aplicación.
function imprimir() {
    if (!hoja.value) {
        return;
    }

    const estilos = [
        ...document.querySelectorAll('link[rel="stylesheet"], style'),
    ]
        .map((nodo) => nodo.outerHTML)
        .join('');

    const marco = document.createElement('iframe');
    marco.style.cssText =
        'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    marco.srcdoc = `<!doctype html><html lang="es"><head><meta charset="utf-8">
        <base href="${window.location.origin}/">
        <title>${nombreBase()}</title>${estilos}
        <style>
            @page { size: A4; margin: 12mm; }
            html, body { background: #fff !important; margin: 0; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr, .evitar-corte { break-inside: avoid; }
        </style></head><body>${hoja.value.outerHTML}</body></html>`;
    marco.onload = () => {
        marco.contentWindow?.focus();
        marco.contentWindow?.print();
        setTimeout(() => marco.remove(), 1000);
    };
    document.body.appendChild(marco);
}
</script>

<template>
    <Button type="button" size="sm" variant="outline" @click="abrir">
        <FileText class="size-4" />
        Generar reporte
    </Button>

    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Reporte de membresías vendidas</DialogTitle>
                <DialogDescription>
                    Del período filtrado. Descárgalo como imagen o guárdalo en
                    PDF desde el diálogo de impresión.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="cargando"
                class="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm"
            >
                <Loader2 class="size-4 animate-spin" />
                Preparando el reporte...
            </div>
            <p v-else-if="error" class="py-10 text-center text-sm text-red-600">
                {{ error }}
            </p>

            <template v-else-if="datos">
                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="generando"
                        @click="descargarImagen"
                    >
                        <Loader2 v-if="generando" class="size-4 animate-spin" />
                        <ImageIcon v-else class="size-4" />
                        Descargar imagen
                    </Button>
                    <Button type="button" size="sm" @click="imprimir">
                        <FileText class="size-4" />
                        Imprimir / guardar PDF
                    </Button>
                </div>

                <!-- la hoja siempre es blanca con letras oscuras, igual que sale en la
                imagen y en el PDF, sin importar el tema de quien la mira -->
                <div class="overflow-x-auto rounded-lg border">
                    <div
                        ref="hoja"
                        class="w-[720px] bg-white p-8 text-neutral-900"
                    >
                        <div class="border-b-[3px] border-neutral-900 pb-4">
                            <img
                                v-if="logo"
                                :src="logo"
                                :alt="negocio"
                                class="mx-auto h-14 w-auto"
                                crossorigin="anonymous"
                            />
                            <p v-else class="text-center text-xl font-bold">
                                {{ negocio }}
                            </p>
                        </div>

                        <p
                            class="mt-6 text-[11px] font-semibold tracking-[0.15em] text-neutral-500 uppercase"
                        >
                            Reporte de membresías vendidas
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ titulo }}</p>

                        <div class="evitar-corte mt-5 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-neutral-100 p-3">
                                <p class="text-xs text-neutral-500">Vendidas</p>
                                <p class="text-2xl font-semibold">
                                    {{ datos.resumen.cantidad }}
                                </p>
                                <p class="text-xs text-neutral-500">
                                    {{ datos.resumen.altas }}
                                    {{
                                        datos.resumen.altas === 1
                                            ? 'alta'
                                            : 'altas'
                                    }}
                                    · {{ datos.resumen.renovaciones }}
                                    {{
                                        datos.resumen.renovaciones === 1
                                            ? 'renovación'
                                            : 'renovaciones'
                                    }}
                                </p>
                            </div>
                            <div
                                class="col-span-2 rounded-lg bg-neutral-900 p-3 text-white"
                            >
                                <p class="text-xs text-neutral-300">
                                    Total en membresías
                                </p>
                                <p class="text-2xl font-semibold">
                                    {{ fmtUsd(datos.resumen.total_usd) }}
                                </p>
                                <p class="text-xs text-neutral-300">
                                    {{ fmtBs(datos.resumen.total_bs) }}
                                </p>
                            </div>
                        </div>

                        <template v-if="datos.por_plan.length">
                            <p class="mt-6 text-sm font-semibold">Por plan</p>
                            <table class="mt-2 w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-neutral-300 text-left text-xs text-neutral-500"
                                    >
                                        <th class="py-1.5 font-medium">Plan</th>
                                        <th
                                            class="py-1.5 text-right font-medium"
                                        >
                                            Cantidad
                                        </th>
                                        <th
                                            class="py-1.5 text-right font-medium"
                                        >
                                            USD
                                        </th>
                                        <th
                                            class="py-1.5 text-right font-medium"
                                        >
                                            Bs
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="plan in datos.por_plan"
                                        :key="plan.plan"
                                        class="border-b border-neutral-200"
                                    >
                                        <td class="py-1.5">{{ plan.plan }}</td>
                                        <td class="py-1.5 text-right">
                                            {{ plan.cantidad }}
                                        </td>
                                        <td class="py-1.5 text-right">
                                            {{ fmtUsd(plan.total_usd) }}
                                        </td>
                                        <td class="py-1.5 text-right">
                                            {{ fmtBs(plan.total_bs) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </template>

                        <p class="mt-6 text-sm font-semibold">Detalle</p>
                        <p
                            v-if="datos.filas.length === 0"
                            class="mt-2 text-sm text-neutral-500"
                        >
                            No se vendieron membresías en este período.
                        </p>
                        <table v-else class="mt-2 w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-neutral-300 text-left text-xs text-neutral-500"
                                >
                                    <th class="py-1.5 font-medium">Cliente</th>
                                    <th class="py-1.5 font-medium">
                                        Membresía
                                    </th>
                                    <th class="py-1.5 font-medium">Vigencia</th>
                                    <th class="py-1.5 text-right font-medium">
                                        Monto
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(fila, i) in datos.filas"
                                    :key="i"
                                    class="border-b border-neutral-200 align-top"
                                >
                                    <td class="py-1.5 pr-2">
                                        {{ fila.socio }}
                                        <span
                                            class="block text-xs text-neutral-500"
                                            >{{ fila.numero_venta }} ·
                                            {{ fmtDia(fila.fecha_venta) }}</span
                                        >
                                    </td>
                                    <td class="py-1.5 pr-2">
                                        {{ fila.plan }}
                                        <span
                                            class="block text-xs text-neutral-500"
                                            >{{
                                                fila.es_alta
                                                    ? 'Alta'
                                                    : 'Renovación'
                                            }}</span
                                        >
                                    </td>
                                    <td class="py-1.5 pr-2 text-xs">
                                        {{ fmtDia(fila.fecha_inicio) }} –
                                        {{ fmtDia(fila.fecha_vencimiento) }}
                                    </td>
                                    <td class="py-1.5 text-right">
                                        {{ fmtUsd(fila.monto) }}
                                        <span
                                            class="block text-xs text-neutral-500"
                                            >{{ fmtBs(fila.monto_bs) }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="mt-6 text-xs text-neutral-500">
                            {{ negocio }} · generado el {{ generado }}. No
                            incluye ventas anuladas.
                        </p>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
