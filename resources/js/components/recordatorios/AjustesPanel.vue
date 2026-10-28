<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ajustes as guardarAjustes } from '@/actions/App/Http/Controllers/RecordatorioController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Ajustes } from '@/lib/recordatorios';

const props = defineProps<{ ajustes: Ajustes }>();

const form = useForm({
    hora_inicio: props.ajustes.hora_inicio,
    hora_fin: props.ajustes.hora_fin,
    espera_min_seg: props.ajustes.espera_min_seg,
    espera_max_seg: props.ajustes.espera_max_seg,
    mensajes_descanso_min: props.ajustes.mensajes_descanso_min,
    mensajes_descanso_max: props.ajustes.mensajes_descanso_max,
    descanso_min: props.ajustes.descanso_min,
    descanso_max: props.ajustes.descanso_max,
    dias_aviso: props.ajustes.dias_aviso,
    duracion_minima_dias: props.ajustes.duracion_minima_dias,
});

// cada fila: etiqueta, ayuda y los campos (uno o un rango mínimo-máximo)
const grupos: {
    titulo: string;
    ayuda: string;
    campos: { id: string; etiqueta: string; tipo: 'time' | 'number'; sufijo?: string }[];
}[] = [
    {
        titulo: 'Horario de trabajo',
        ayuda: 'Fuera de este horario el bot no envía nada.',
        campos: [
            { id: 'hora_inicio', etiqueta: 'Desde', tipo: 'time' },
            { id: 'hora_fin', etiqueta: 'Hasta', tipo: 'time' },
        ],
    },
    {
        titulo: 'Espera entre mensajes',
        ayuda: 'Entre un mensaje y el siguiente espera un tiempo al azar dentro de este rango. Mínimo 30 s.',
        campos: [
            { id: 'espera_min_seg', etiqueta: 'Mínimo', tipo: 'number', sufijo: 'seg' },
            { id: 'espera_max_seg', etiqueta: 'Máximo', tipo: 'number', sufijo: 'seg' },
        ],
    },
    {
        titulo: 'Descansos',
        ayuda: 'Cada cierta cantidad de mensajes (al azar en este rango) el bot hace una pausa larga.',
        campos: [
            { id: 'mensajes_descanso_min', etiqueta: 'Mensajes mín.', tipo: 'number' },
            { id: 'mensajes_descanso_max', etiqueta: 'Mensajes máx.', tipo: 'number' },
            { id: 'descanso_min', etiqueta: 'Pausa mín.', tipo: 'number', sufijo: 'min' },
            { id: 'descanso_max', etiqueta: 'Pausa máx.', tipo: 'number', sufijo: 'min' },
        ],
    },
    {
        titulo: 'A quién se le avisa',
        ayuda: 'Se avisa con esta anticipación y el mismo día del vencimiento. Los planes más cortos que el mínimo (pase de día) no reciben aviso.',
        campos: [
            { id: 'dias_aviso', etiqueta: 'Días de anticipación', tipo: 'number', sufijo: 'días' },
            { id: 'duracion_minima_dias', etiqueta: 'Duración mínima del plan', tipo: 'number', sufijo: 'días' },
        ],
    },
];

function guardar() {
    form.put(guardarAjustes.url(), { preserveScroll: true });
}
</script>

<template>
    <form class="max-w-2xl space-y-6" @submit.prevent="guardar">
        <div v-for="grupo in grupos" :key="grupo.titulo" class="space-y-3 rounded-xl border p-4">
            <div>
                <h3 class="font-medium">{{ grupo.titulo }}</h3>
                <p class="text-muted-foreground text-sm">{{ grupo.ayuda }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="campo in grupo.campos" :key="campo.id" class="grid gap-1.5">
                    <Label :for="campo.id">
                        {{ campo.etiqueta }}
                        <span v-if="campo.sufijo" class="text-muted-foreground">
                            ({{ campo.sufijo }})
                        </span>
                    </Label>
                    <Input
                        :id="campo.id"
                        v-model="(form as any)[campo.id]"
                        :type="campo.tipo"
                        :min="campo.tipo === 'number' ? 1 : undefined"
                    />
                    <InputError :message="(form.errors as Record<string, string>)[campo.id]" />
                </div>
            </div>
        </div>

        <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Guardando...' : 'Guardar ajustes' }}
        </Button>
    </form>
</template>
