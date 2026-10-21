<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Bot,
    Clock,
    Landmark,
    Minus,
    PenLine,
    Plus,
    RefreshCw,
    ScrollText,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DialogoNuevaTasa from '@/components/tasas_cambio/DialogoNuevaTasa.vue';
import {
    cargar_automatica as cargarTasaAutomatica,
    index as tasasCambioIndex,
} from '@/routes/tasas_cambio';

interface UsuarioResumen {
    id: number;
    name: string;
}

interface TasaCambioFila {
    id: number;
    fecha: string;
    tasa: string;
    tipo: 'automatica' | 'manual';
    activa: boolean;
    usuario: UsuarioResumen | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TasaActual {
    id: number;
    tasa: string;
    fecha: string;
    tipo: TasaCambioFila['tipo'];
    usuario: UsuarioResumen | null;
}

const props = defineProps<{
    tasas: {
        data: TasaCambioFila[];
        links: PaginationLink[];
    };
    tasaActual: TasaActual | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tasas de cambio', href: tasasCambioIndex() }],
    },
});

const TIPO_ETIQUETAS: Record<TasaCambioFila['tipo'], string> = {
    automatica: 'Automática',
    manual: 'Manual',
};

function fmtTasa(valor: string | number): string {
    return Number(valor).toLocaleString('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
}

function diasDesdeHoy(fecha: string): number {
    const hoy = new Date();
    const dia = new Date(fecha);
    return Math.round(
        (Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()) -
            Date.UTC(
                dia.getUTCFullYear(),
                dia.getUTCMonth(),
                dia.getUTCDate(),
            )) /
            86_400_000,
    );
}

function fmtFechaRelativa(fecha: string): string {
    const dias = diasDesdeHoy(fecha);

    if (dias <= 0) return 'Hoy';
    if (dias === 1) return 'Ayer';
    if (dias < 7) return `Hace ${dias} días`;

    return fmtFechaCorta(fecha);
}

function fmtFechaCorta(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
}

function iniciales(nombre: string): string {
    const partes = nombre.trim().split(/\s+/).filter(Boolean);
    if (partes.length === 0) return '?';
    if (partes.length === 1) return partes[0].slice(0, 2).toUpperCase();
    return (partes[0][0] + partes[1][0]).toUpperCase();
}

/** Variación porcentual de `tasa` respecto a `anterior`, o null si no hay referencia. */
function variacion(
    tasa: string | number,
    anterior: string | number | undefined,
): number | null {
    if (anterior === undefined) return null;
    const actual = Number(tasa);
    const previa = Number(anterior);
    if (previa === 0) return null;
    return ((actual - previa) / previa) * 100;
}

const filaAnteriorALaActual = computed(() => {
    const indice = props.tasas.data.findIndex(
        (fila) => fila.id === props.tasaActual?.id,
    );
    return indice === -1 ? undefined : props.tasas.data[indice + 1]?.tasa;
});

const variacionActual = computed(() =>
    props.tasaActual
        ? variacion(props.tasaActual.tasa, filaAnteriorALaActual.value)
        : null,
);

// la card principal solo se llena con la tasa vigente si es de HOY; ver
// TasaCambio::vigenteHoy en el backend, mismo criterio
const tasaActualEsDeHoy = computed(
    () => !!props.tasaActual && diasDesdeHoy(props.tasaActual.fecha) <= 0,
);

// --- Nueva tasa (modal) -----------------------------------------------------

const dialogAbierto = ref(false);

function abrirNuevaTasa() {
    dialogAbierto.value = true;
}

// --- Carga automática (reintento) --------------------------------------------

// la misma consulta al BCV (con respaldo en dolarapi) que corre programada
// cada mañana; el botón existe para cuando esa corrida falló
const cargaAutomatica = useForm({});

function cargarAutomatica() {
    cargaAutomatica.post(cargarTasaAutomatica.url(), { preserveScroll: true });
}

// si la de hoy ya es automática no hay nada que reintentar
const puedeCargarAutomatica = computed(
    () => !(tasaActualEsDeHoy.value && props.tasaActual?.tipo === 'automatica'),
);
</script>

<template>
    <Head title="Tasas de cambio" />

    <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <!-- Tasa de hoy: la card principal solo muestra la tasa vigente si
        es de HOY (mismo criterio que VentaController::store); una tasa
        vieja no se promueve aquí, para eso está el historial de abajo -->
        <Card v-if="tasaActual && tasaActualEsDeHoy">
            <CardContent
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <span
                        class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg"
                    >
                        <Landmark class="size-5" />
                    </span>
                    <div class="flex flex-col gap-1">
                        <p class="flex items-baseline gap-2">
                            <span
                                class="text-3xl font-semibold tracking-tight tabular-nums"
                            >
                                Bs. {{ fmtTasa(tasaActual.tasa) }}
                            </span>
                            <span
                                class="text-muted-foreground text-sm font-medium"
                                >por USD</span
                            >
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="variacionActual !== null"
                                class="bg-primary/10 text-primary inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                <TrendingUp
                                    v-if="variacionActual > 0"
                                    class="size-3.5"
                                />
                                <TrendingDown
                                    v-else-if="variacionActual < 0"
                                    class="size-3.5"
                                />
                                <Minus v-else class="size-3.5" />
                                {{
                                    variacionActual === 0
                                        ? 'Sin cambio'
                                        : `${Math.abs(variacionActual).toFixed(1)}%`
                                }}
                            </span>
                            <span
                                class="text-muted-foreground inline-flex items-center gap-1.5 text-xs"
                            >
                                <Clock class="size-3.5" />
                                Hoy
                            </span>
                            <span
                                class="text-muted-foreground inline-flex items-center gap-1.5 text-xs"
                            >
                                <template
                                    v-if="tasaActual.tipo === 'automatica'"
                                >
                                    <Bot class="size-3.5" />
                                    Automática · BCV
                                </template>
                                <template v-else>
                                    <PenLine class="size-3.5" />
                                    Manual{{
                                        tasaActual.usuario
                                            ? ` · ${tasaActual.usuario.name}`
                                            : ''
                                    }}
                                </template>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <Button
                        v-if="puedeCargarAutomatica"
                        variant="outline"
                        class="w-full sm:w-auto"
                        :disabled="cargaAutomatica.processing"
                        @click="cargarAutomatica"
                    >
                        <RefreshCw
                            class="size-4"
                            :class="{
                                'animate-spin': cargaAutomatica.processing,
                            }"
                        />
                        {{
                            cargaAutomatica.processing
                                ? 'Consultando BCV...'
                                : 'Cargar del BCV'
                        }}
                    </Button>
                    <Button class="w-full sm:w-auto" @click="abrirNuevaTasa">
                        <Plus class="size-4" />
                        Nueva tasa
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card v-else class="border-dashed shadow-none">
            <CardContent
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg"
                    >
                        <Landmark class="size-5" />
                    </span>
                    <div>
                        <p class="font-medium">
                            Todavía no se ha cargado la tasa de hoy
                        </p>
                        <p class="text-muted-foreground text-sm">
                            Los pagos en bolívares no se podrán cobrar hasta que
                            la registres. Se intenta cargar sola del BCV cada
                            hora entre 6:00 a.m. y 12:00 m.
                        </p>
                    </div>
                </div>
                <div
                    class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row"
                >
                    <Button
                        class="w-full sm:w-auto"
                        :disabled="cargaAutomatica.processing"
                        @click="cargarAutomatica"
                    >
                        <RefreshCw
                            class="size-4"
                            :class="{
                                'animate-spin': cargaAutomatica.processing,
                            }"
                        />
                        {{
                            cargaAutomatica.processing
                                ? 'Consultando BCV...'
                                : 'Cargar del BCV'
                        }}
                    </Button>
                    <Button
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="abrirNuevaTasa"
                    >
                        <Plus class="size-4" />
                        Registrar manual
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Historial -->
        <div class="flex items-center gap-2">
            <ScrollText class="text-muted-foreground size-4" />
            <h2 class="text-sm font-semibold">Historial</h2>
        </div>

        <div
            v-if="!tasas.data.length"
            class="text-muted-foreground flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center"
        >
            <div
                class="bg-muted flex size-12 items-center justify-center rounded-full"
            >
                <Landmark class="text-muted-foreground size-6" />
            </div>
            <div class="space-y-1">
                <p class="text-foreground font-medium">
                    Todavía no se ha registrado ninguna tasa
                </p>
                <p class="text-sm">
                    Registra la primera tasa Bs/USD para poder cobrar en
                    bolívares.
                </p>
            </div>
        </div>

        <Card v-else class="gap-0 overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground text-left">
                            <th class="px-5 py-3 font-medium">Fecha</th>
                            <th class="px-5 py-3 font-medium">Tasa (Bs.)</th>
                            <th class="px-5 py-3 font-medium">Tipo</th>
                            <th
                                class="hidden px-5 py-3 font-medium md:table-cell"
                            >
                                Registrada por
                            </th>
                            <th class="px-5 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(tasa, indice) in tasas.data"
                            :key="tasa.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="text-muted-foreground px-5 py-3.5">
                                {{ fmtFechaCorta(tasa.fecha) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 font-medium tabular-nums"
                                >
                                    {{ fmtTasa(tasa.tasa) }}
                                    <template
                                        v-if="
                                            variacion(
                                                tasa.tasa,
                                                tasas.data[indice + 1]?.tasa,
                                            ) !== null
                                        "
                                    >
                                        <TrendingUp
                                            v-if="
                                                variacion(
                                                    tasa.tasa,
                                                    tasas.data[indice + 1]
                                                        ?.tasa,
                                                )! > 0
                                            "
                                            class="text-muted-foreground size-3.5"
                                        />
                                        <TrendingDown
                                            v-else-if="
                                                variacion(
                                                    tasa.tasa,
                                                    tasas.data[indice + 1]
                                                        ?.tasa,
                                                )! < 0
                                            "
                                            class="text-muted-foreground size-3.5"
                                        />
                                    </template>
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        tasa.tipo === 'automatica'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <Bot
                                        v-if="tasa.tipo === 'automatica'"
                                        class="size-3.5"
                                    />
                                    <PenLine v-else class="size-3.5" />
                                    {{ TIPO_ETIQUETAS[tasa.tipo] }}
                                </span>
                            </td>
                            <td class="hidden px-5 py-3.5 md:table-cell">
                                <div
                                    v-if="tasa.usuario"
                                    class="flex items-center gap-2"
                                >
                                    <Avatar class="size-6 shrink-0">
                                        <AvatarFallback
                                            class="bg-primary/10 text-primary text-[10px] font-semibold"
                                        >
                                            {{ iniciales(tasa.usuario.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        class="text-muted-foreground truncate text-sm"
                                        >{{ tasa.usuario.name }}</span
                                    >
                                </div>
                                <!-- automática sin usuario = la cargó la tarea programada -->
                                <span
                                    v-else-if="tasa.tipo === 'automatica'"
                                    class="text-muted-foreground text-sm"
                                >
                                    Programada
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span
                                    v-if="tasa.activa"
                                    class="text-primary inline-flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <span
                                        class="bg-primary size-1.5 rounded-full"
                                    />
                                    Activa
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <nav
            v-if="tasas.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="(link, index) in tasas.links" :key="index">
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

    <DialogoNuevaTasa v-model:open="dialogAbierto" />
</template>
