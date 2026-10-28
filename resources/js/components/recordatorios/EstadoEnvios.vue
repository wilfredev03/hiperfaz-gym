<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Ajustes, MotorEstado, ProgresoCola } from '@/lib/recordatorios';

const props = defineProps<{
    ajustes: Ajustes;
    motor: MotorEstado;
    progreso: ProgresoCola;
    botConectado: boolean;
}>();

const emit = defineEmits<{ verFallidos: [] }>();

// Reloj propio, corregido con la hora del servidor: la cuenta regresiva sale
// de fechas que calcula el servidor y no debe depender de que el reloj del
// navegador esté en hora.
const ahoraLocal = ref(Date.now());
const desfase = ref(0);
let reloj: number | undefined;

watch(
    () => props.motor.servidor_ahora,
    (iso) => {
        desfase.value = new Date(iso).getTime() - Date.now();
    },
    { immediate: true },
);

onMounted(() => {
    reloj = window.setInterval(() => (ahoraLocal.value = Date.now()), 500);
});
onBeforeUnmount(() => window.clearInterval(reloj));

const ahora = computed(() => ahoraLocal.value + desfase.value);

function segundosHasta(iso: string | null): number {
    return iso ? Math.max(0, Math.ceil((new Date(iso).getTime() - ahora.value) / 1000)) : 0;
}

function mmss(segundos: number): string {
    const m = String(Math.floor(segundos / 60)).padStart(2, '0');
    const s = String(segundos % 60).padStart(2, '0');

    return `${m}:${s}`;
}

const segundosEspera = computed(() => segundosHasta(props.motor.proximo_envio_at));
const segundosDescanso = computed(() => segundosHasta(props.motor.descanso_hasta));

type Fase =
    | 'detenido'
    | 'fuera_de_horario'
    | 'sin_whatsapp'
    | 'descanso'
    | 'espera'
    | 'sin_pendientes'
    | 'enviando';

const fase = computed<Fase>(() => {
    if (!props.ajustes.activo) {
        return 'detenido';
    }
    if (!props.motor.dentro_de_horario) {
        return 'fuera_de_horario';
    }
    if (!props.botConectado) {
        return 'sin_whatsapp';
    }
    if (segundosDescanso.value > 0) {
        return 'descanso';
    }
    if (segundosEspera.value > 0) {
        return 'espera';
    }

    return props.progreso.pendientes === 0 ? 'sin_pendientes' : 'enviando';
});

// Cuánto de la espera (o descanso) en curso ya pasó, de 0 a 100. El tramo
// empieza en el último envío; si no hay uno reciente se parte de lo que quede.
const avanceEspera = computed(() => {
    const hasta = fase.value === 'descanso' ? props.motor.descanso_hasta : props.motor.proximo_envio_at;
    const restante = fase.value === 'descanso' ? segundosDescanso.value : segundosEspera.value;

    if (!hasta || restante <= 0) {
        return 0;
    }

    const desde = props.motor.ultimo_envio_at ? new Date(props.motor.ultimo_envio_at).getTime() : null;
    const fin = new Date(hasta).getTime();
    const total = desde !== null && fin > desde ? (fin - desde) / 1000 : restante;

    return Math.min(100, Math.max(0, (1 - restante / Math.max(total, restante)) * 100));
});

const TEXTOS: Record<Fase, string> = {
    detenido: 'Envíos detenidos',
    fuera_de_horario: 'Fuera del horario de trabajo',
    sin_whatsapp: 'Esperando la conexión de WhatsApp',
    descanso: 'En descanso, reanuda en',
    espera: 'Próximo mensaje en',
    sin_pendientes: 'No quedan mensajes por enviar',
    enviando: 'Enviando mensaje...',
};

const pct = (n: number) => (props.progreso.total ? (n / props.progreso.total) * 100 : 0);

const resueltos = computed(
    () => props.progreso.enviados + props.progreso.fallidos + props.progreso.cancelados,
);
</script>

<template>
    <div class="space-y-4">
        <!-- Tiempo hasta el próximo mensaje -->
        <div class="bg-muted/40 rounded-xl border p-4">
            <p class="text-muted-foreground text-sm">{{ TEXTOS[fase] }}</p>

            <div
                v-if="fase === 'espera' || fase === 'descanso'"
                class="mt-1 flex items-end gap-3"
            >
                <span class="text-5xl leading-none font-semibold tabular-nums">
                    {{ mmss(fase === 'descanso' ? segundosDescanso : segundosEspera) }}
                </span>
                <span class="text-muted-foreground pb-1 text-sm">min : seg</span>
            </div>

            <p
                v-else-if="fase === 'enviando'"
                class="mt-1 flex items-center gap-2 text-2xl font-semibold"
            >
                <LoaderCircle class="size-6 animate-spin" />
                Enviando...
            </p>

            <p
                v-else-if="fase === 'fuera_de_horario'"
                class="mt-1 text-2xl font-semibold"
            >
                Reanuda a las {{ ajustes.hora_inicio }}
                <span class="text-muted-foreground text-base font-normal">
                    (trabaja de {{ ajustes.hora_inicio }} a {{ ajustes.hora_fin }})
                </span>
            </p>

            <div
                v-if="fase === 'espera' || fase === 'descanso'"
                class="bg-muted mt-3 h-1.5 overflow-hidden rounded-full"
            >
                <div
                    class="bg-primary h-full rounded-full transition-[width] duration-500"
                    :style="{ width: `${avanceEspera}%` }"
                />
            </div>

            <p
                v-if="
                    ajustes.activo &&
                    motor.mensajes_para_descanso &&
                    (fase === 'espera' || fase === 'enviando')
                "
                class="text-muted-foreground mt-3 text-xs"
            >
                Mensajes antes del próximo descanso:
                <span class="text-foreground font-medium tabular-nums">
                    {{ motor.enviados_desde_descanso }} de {{ motor.mensajes_para_descanso }}
                </span>
            </p>
        </div>

        <!-- Avance de la cola de hoy -->
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-sm font-medium">Cola de hoy</p>
                <p v-if="progreso.total" class="text-sm tabular-nums">
                    <span class="text-lg font-semibold">{{ progreso.enviados }}</span>
                    de {{ progreso.total }} enviados
                </p>
            </div>

            <div
                v-if="progreso.total"
                class="bg-muted mt-2 flex h-3 overflow-hidden rounded-full"
                role="progressbar"
                :aria-valuenow="resueltos"
                :aria-valuemax="progreso.total"
                aria-label="Avance de la cola de hoy"
            >
                <div
                    class="bg-primary h-full transition-[width] duration-500"
                    :style="{ width: `${pct(progreso.enviados)}%` }"
                />
                <div
                    class="bg-destructive h-full transition-[width] duration-500"
                    :style="{ width: `${pct(progreso.fallidos)}%` }"
                />
                <div
                    class="bg-muted-foreground/40 h-full transition-[width] duration-500"
                    :style="{ width: `${pct(progreso.cancelados)}%` }"
                />
            </div>

            <p v-else class="text-muted-foreground mt-1 text-sm">
                No hay avisos para hoy. Se arma sola cada mañana, o pulsa
                «Generar cola de hoy».
            </p>

            <div
                v-if="progreso.total"
                class="text-muted-foreground mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs"
            >
                <span class="flex items-center gap-1.5">
                    <span class="bg-primary size-2 rounded-full" />
                    Enviados {{ progreso.enviados }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="bg-muted-foreground/40 size-2 rounded-full" />
                    Por enviar
                    <span class="text-foreground font-medium">{{ progreso.pendientes }}</span>
                </span>
                <button
                    v-if="progreso.fallidos"
                    type="button"
                    class="text-destructive flex items-center gap-1.5 hover:underline"
                    @click="emit('verFallidos')"
                >
                    <span class="bg-destructive size-2 rounded-full" />
                    Fallidos {{ progreso.fallidos }}
                </button>
                <span v-if="progreso.cancelados" class="flex items-center gap-1.5">
                    <span class="bg-muted-foreground/40 size-2 rounded-full" />
                    Cancelados {{ progreso.cancelados }}
                </span>
            </div>
        </div>
    </div>
</template>
