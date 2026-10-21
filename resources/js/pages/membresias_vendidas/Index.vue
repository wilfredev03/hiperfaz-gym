<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarCheck } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { fmtDia } from '@/lib/socios';
import { fmtBs, fmtUsd } from '@/lib/ventas';
import { index } from '@/routes/membresias_vendidas';
import { show } from '@/routes/socios';

type Periodo = 'hoy' | 'semana' | 'mes' | 'personalizado';

interface MembresiaFila {
    id: number;
    cliente_id: number;
    socio: string;
    plan: string;
    fecha_inicio: string;
    fecha_vencimiento: string;
    fecha_venta: string;
    numero_venta: string;
    estado_venta: 'pendiente' | 'parcial' | 'pagada' | 'anulada';
    monto: string;
    monto_bs: string;
    es_alta: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    membresias: { data: MembresiaFila[]; links: PaginationLink[] };
    resumen: {
        cantidad: number;
        altas: number;
        renovaciones: number;
        total_usd: number;
        total_bs: number;
    };
    filtros: { periodo: Periodo; desde: string; hasta: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Membresías vendidas', href: index() }],
    },
});

const SEGMENTOS: { value: Periodo; label: string }[] = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'semana', label: 'Esta semana' },
    { value: 'mes', label: 'Este mes' },
];

const desde = ref(props.filtros.desde);
const hasta = ref(props.filtros.hasta);

function irA(periodo: Periodo, extra: Record<string, string> = {}) {
    router.get(
        index.url(),
        { periodo, ...extra },
        { preserveState: true, preserveScroll: true },
    );
}

function aplicarRango() {
    if (desde.value && hasta.value) {
        irA('personalizado', { desde: desde.value, hasta: hasta.value });
    }
}

const ESTADO_VENTA: Record<MembresiaFila['estado_venta'], string> = {
    pagada: 'Pagada',
    parcial: 'Pago parcial',
    pendiente: 'Sin pagar',
    anulada: 'Anulada',
};
</script>

<template>
    <Head title="Membresías vendidas" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Membresías vendidas</h1>

            <div
                class="inline-flex items-center gap-1 rounded-full bg-neutral-500/10 p-1"
            >
                <button
                    v-for="segmento in SEGMENTOS"
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
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-muted-foreground">Rango:</span>
            <Input v-model="desde" type="date" class="w-40" />
            <span class="text-muted-foreground">a</span>
            <Input v-model="hasta" type="date" class="w-40" />
            <Button
                size="sm"
                :variant="
                    filtros.periodo === 'personalizado' ? 'default' : 'outline'
                "
                :disabled="!desde || !hasta"
                @click="aplicarRango"
            >
                Aplicar
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <Card class="py-3">
                <CardContent class="px-4">
                    <p class="text-muted-foreground text-xs">Vendidas</p>
                    <p class="text-2xl leading-tight font-semibold">
                        {{ resumen.cantidad }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{ resumen.altas }}
                        {{ resumen.altas === 1 ? 'alta' : 'altas' }} ·
                        {{ resumen.renovaciones }}
                        {{
                            resumen.renovaciones === 1
                                ? 'renovación'
                                : 'renovaciones'
                        }}
                    </p>
                </CardContent>
            </Card>
            <Card class="py-3 sm:col-span-2">
                <CardContent class="px-4">
                    <p class="text-muted-foreground text-xs">
                        Total en membresías
                    </p>
                    <p class="text-2xl leading-tight font-semibold">
                        {{ fmtUsd(resumen.total_usd) }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{ fmtBs(resumen.total_bs) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="membresias.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <CalendarCheck class="size-8" />
            <p class="text-sm font-medium">
                No se vendieron membresías en este rango.
            </p>
        </div>

        <Card v-else class="overflow-hidden py-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-b text-left">
                            <th class="px-4 py-3 font-medium">Cliente</th>
                            <th class="px-4 py-3 font-medium">Membresía</th>
                            <th class="px-4 py-3 font-medium">Vigencia</th>
                            <th class="px-4 py-3 font-medium">Monto</th>
                            <th class="px-4 py-3 font-medium">Venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="fila in membresias.data"
                            :key="fila.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="show.url(fila.cliente_id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ fila.socio }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                {{ fila.plan }}
                                <Badge
                                    variant="outline"
                                    class="ml-1"
                                    :class="
                                        fila.es_alta
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300'
                                    "
                                >
                                    {{ fila.es_alta ? 'Alta' : 'Renovación' }}
                                </Badge>
                            </td>
                            <td
                                class="text-muted-foreground px-4 py-3 whitespace-nowrap"
                            >
                                {{ fmtDia(fila.fecha_inicio) }} –
                                {{ fmtDia(fila.fecha_vencimiento) }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{ fmtUsd(fila.monto) }}
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    {{ fmtBs(fila.monto_bs) }}
                                </p>
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ fila.numero_venta }}
                                <p class="text-xs">
                                    {{ fmtDia(fila.fecha_venta) }} ·
                                    {{ ESTADO_VENTA[fila.estado_venta] }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <nav
            v-if="membresias.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in membresias.links"
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
