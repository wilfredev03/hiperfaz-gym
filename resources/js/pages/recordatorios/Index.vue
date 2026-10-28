<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ListPlus, Play, Square } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    detener,
    iniciar,
    proponer,
} from '@/actions/App/Http/Controllers/RecordatorioController';
import AjustesPanel from '@/components/recordatorios/AjustesPanel.vue';
import BotPanel from '@/components/recordatorios/BotPanel.vue';
import EstadoEnvios from '@/components/recordatorios/EstadoEnvios.vue';
import HistorialConexion from '@/components/recordatorios/HistorialConexion.vue';
import ListaRecordatorios from '@/components/recordatorios/ListaRecordatorios.vue';
import PlantillasPanel from '@/components/recordatorios/PlantillasPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    Ajustes,
    Conexiones,
    MotorEstado,
    Plantilla,
    ProgresoCola,
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
    motor: MotorEstado;
    progreso: ProgresoCola;
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

// Recarga suave de lo que cambia solo mientras la página está abierta. El
// estado del motor y el avance de la cola se piden seguido (la cuenta regresiva
// se dibuja en el navegador, pero cada mensaje enviado la reinicia); el
// historial y la conexión, que pesan más, con menos frecuencia.
const RECARGA_RAPIDA_MS = 4000;
let recarga: number | undefined;
let ciclos = 0;

onMounted(() => {
    recarga = window.setInterval(() => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        ciclos++;
        const lenta = ciclos % 8 === 0; // cada ~32 s

        router.reload({
            only: lenta
                ? ['motor', 'progreso', 'cola', 'ajustes', 'historial', 'conexiones']
                : ['motor', 'progreso', 'cola', 'ajustes'],
        });
    }, RECARGA_RAPIDA_MS);
});
onBeforeUnmount(() => window.clearInterval(recarga));

// el historial de conexión se refresca en cuanto cambia el estado de la
// sesión (se genera el QR, se escanea, se cae, se recupera)
function recargarConexiones() {
    router.reload({ only: ['conexiones'] });
}

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

                <EstadoEnvios
                    :ajustes="ajustes"
                    :motor="motor"
                    :progreso="progreso"
                    :bot-conectado="botConectado"
                    @ver-fallidos="filtrarHistorial('fallido')"
                />
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
