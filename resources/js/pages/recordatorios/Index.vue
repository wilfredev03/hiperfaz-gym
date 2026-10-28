<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ListPlus, Play, Square, TriangleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    detener,
    iniciar,
    proponer,
} from '@/actions/App/Http/Controllers/RecordatorioController';
import AjustesPanel from '@/components/recordatorios/AjustesPanel.vue';
import BotPanel from '@/components/recordatorios/BotPanel.vue';
import HistorialConexion from '@/components/recordatorios/HistorialConexion.vue';
import ListaRecordatorios from '@/components/recordatorios/ListaRecordatorios.vue';
import PlantillasPanel from '@/components/recordatorios/PlantillasPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cuentaRegresiva } from '@/lib/recordatorios';
import type {
    Ajustes,
    Conexiones,
    Plantilla,
    RecordatorioFila,
} from '@/lib/recordatorios';
import { index } from '@/routes/recordatorios';

type Vista = 'cola' | 'historial' | 'conexion' | 'plantillas' | 'ajustes';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    ajustes: Ajustes;
    motor: {
        dentro_de_horario: boolean;
        proximo_envio_at: string | null;
        descanso_hasta: string | null;
    };
    resumen: {
        pendientes: number;
        enviados_hoy: number;
        fallidos: number;
        cancelados_hoy: number;
    };
    cola: RecordatorioFila[];
    historial: { data: RecordatorioFila[]; links: PaginationLink[] };
    filtro: 'enviado' | 'fallido' | 'cancelado' | null;
    conexiones: Conexiones;
    plantillas: Plantilla[];
    variables: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recordatorios', href: index() }],
    },
});

const vista = ref<Vista>('cola');
const botConectado = ref(false);

// reloj para las cuentas regresivas y recarga suave de los datos del motor
const ahora = ref(Date.now());
let reloj: number | undefined;
let recarga: number | undefined;

onMounted(() => {
    reloj = window.setInterval(() => (ahora.value = Date.now()), 1000);
    recarga = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            router.reload({
                only: ['motor', 'resumen', 'cola', 'historial', 'ajustes', 'conexiones'],
            });
        }
    }, 15000);
});

// el historial de conexión se refresca en cuanto cambia el estado de la
// sesión (se genera el QR, se escanea, se cae, se recupera)
function recargarConexiones() {
    router.reload({ only: ['conexiones'] });
}
onBeforeUnmount(() => {
    window.clearInterval(reloj);
    window.clearInterval(recarga);
});

const estadoMotor = computed(() => {
    if (!props.ajustes.activo) {
        return 'Detenido';
    }
    if (!props.motor.dentro_de_horario) {
        return `Fuera de horario (${props.ajustes.hora_inicio} a ${props.ajustes.hora_fin})`;
    }
    if (!botConectado.value) {
        return 'Esperando la conexión de WhatsApp';
    }

    const descanso = cuentaRegresiva(props.motor.descanso_hasta, ahora.value);
    if (props.motor.descanso_hasta && descanso !== '0:00') {
        return `En descanso: reanuda en ${descanso}`;
    }

    const espera = cuentaRegresiva(props.motor.proximo_envio_at, ahora.value);
    if (props.motor.proximo_envio_at && espera !== '0:00') {
        return `Próximo mensaje en ${espera}`;
    }

    return props.resumen.pendientes > 0
        ? 'Enviando...'
        : 'Sin mensajes pendientes';
});

function accion(url: string) {
    router.post(url, {}, { preserveScroll: true });
}

function filtrarHistorial(estado: string | null) {
    router.get(
        index.url(),
        estado ? { estado } : {},
        { preserveScroll: true, preserveState: true, only: ['historial', 'filtro'] },
    );
    vista.value = 'historial';
}

const VISTAS: { id: Vista; texto: string }[] = [
    { id: 'cola', texto: 'Cola' },
    { id: 'historial', texto: 'Historial' },
    { id: 'conexion', texto: 'Conexión' },
    { id: 'plantillas', texto: 'Plantillas' },
    { id: 'ajustes', texto: 'Ajustes' },
];

const FILTROS: { id: string | null; texto: string }[] = [
    { id: null, texto: 'Todos' },
    { id: 'enviado', texto: 'Enviados' },
    { id: 'fallido', texto: 'Fallidos' },
    { id: 'cancelado', texto: 'Cancelados' },
];
</script>

<template>
    <Head title="Recordatorios" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <div>
            <h1 class="text-xl font-semibold">Recordatorios por WhatsApp</h1>
            <p class="text-muted-foreground text-sm">
                Avisos de membresías por vencer, enviados con esperas al azar y
                descansos para cuidar el número.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div class="flex flex-col justify-between gap-4 rounded-xl border p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold">Envíos</h2>
                            <Badge :variant="ajustes.activo ? 'default' : 'outline'">
                                {{ ajustes.activo ? 'Activos' : 'Detenidos' }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground text-sm tabular-nums">
                            {{ estadoMotor }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" @click="accion(proponer.url())">
                            <ListPlus class="size-4" />
                            Generar cola de hoy
                        </Button>
                        <Button
                            v-if="!ajustes.activo"
                            @click="accion(iniciar.url())"
                        >
                            <Play class="size-4" />
                            Iniciar envíos
                        </Button>
                        <Button
                            v-else
                            variant="destructive"
                            @click="accion(detener.url())"
                        >
                            <Square class="size-4" />
                            Detener
                        </Button>
                    </div>
                </div>

                <p
                    v-if="ajustes.activo && !botConectado"
                    class="bg-muted/60 flex items-start gap-2 rounded-lg p-3 text-sm"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    Los envíos están activos pero WhatsApp no está conectado:
                    no saldrá ningún mensaje hasta vincular el número.
                </p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border p-3">
                        <p class="text-muted-foreground text-xs">Pendientes</p>
                        <p class="text-2xl font-semibold">{{ resumen.pendientes }}</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-muted-foreground text-xs">Enviados hoy</p>
                        <p class="text-2xl font-semibold">{{ resumen.enviados_hoy }}</p>
                    </div>
                    <button
                        type="button"
                        class="hover:bg-accent rounded-lg border p-3 text-left"
                        @click="filtrarHistorial('fallido')"
                    >
                        <p class="text-muted-foreground text-xs">Fallidos hoy</p>
                        <p
                            class="text-2xl font-semibold"
                            :class="{ 'text-destructive': resumen.fallidos > 0 }"
                        >
                            {{ resumen.fallidos }}
                        </p>
                    </button>
                    <div class="rounded-lg border p-3">
                        <p class="text-muted-foreground text-xs">Cancelados hoy</p>
                        <p class="text-2xl font-semibold">{{ resumen.cancelados_hoy }}</p>
                    </div>
                </div>
            </div>

            <BotPanel
                @conectado="botConectado = $event"
                @cambio="recargarConexiones"
            />
        </div>

        <div class="inline-flex w-fit items-center gap-1 rounded-full bg-neutral-500/10 p-1">
            <button
                v-for="v in VISTAS"
                :key="v.id"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-all"
                :class="
                    vista === v.id
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="vista = v.id"
            >
                {{ v.texto }}
                <template v-if="v.id === 'cola'">({{ cola.length }})</template>
            </button>
        </div>

        <ListaRecordatorios v-if="vista === 'cola'" :filas="cola" modo="cola" />

        <div v-else-if="vista === 'historial'" class="space-y-3">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="f in FILTROS"
                    :key="f.texto"
                    type="button"
                    class="rounded-full border px-3 py-1 text-sm transition-colors"
                    :class="
                        filtro === f.id
                            ? 'bg-accent font-medium'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="filtrarHistorial(f.id)"
                >
                    {{ f.texto }}
                </button>
            </div>

            <ListaRecordatorios :filas="historial.data" modo="historial" />

            <nav
                v-if="historial.links.length > 3"
                class="flex flex-wrap items-center gap-1"
            >
                <template v-for="(link, i) in historial.links" :key="i">
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

        <HistorialConexion
            v-else-if="vista === 'conexion'"
            :conexiones="conexiones"
        />

        <PlantillasPanel
            v-else-if="vista === 'plantillas'"
            :plantillas="plantillas"
            :variables="variables"
        />

        <AjustesPanel v-else :ajustes="ajustes" />
    </div>
</template>
