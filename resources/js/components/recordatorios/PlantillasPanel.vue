<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/RecordatorioPlantillaController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { TIPOS } from '@/lib/recordatorios';
import type { Plantilla, TipoRecordatorio } from '@/lib/recordatorios';

defineProps<{
    plantillas: Plantilla[];
    variables: Record<string, string>;
}>();

const dialogAbierto = ref(false);
const editandoId = ref<number | null>(null);

const form = useForm({
    tipo: 'por_vencer' as TipoRecordatorio,
    nombre: '',
    cuerpo: '',
    activa: true,
});

function abrirNueva() {
    editandoId.value = null;
    form.reset();
    form.clearErrors();
    dialogAbierto.value = true;
}

function abrirEditar(plantilla: Plantilla) {
    editandoId.value = plantilla.id;
    form.clearErrors();
    form.tipo = plantilla.tipo;
    form.nombre = plantilla.nombre;
    form.cuerpo = plantilla.cuerpo;
    form.activa = plantilla.activa;
    dialogAbierto.value = true;
}

function guardar() {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            dialogAbierto.value = false;
        },
    };

    if (editandoId.value === null) {
        form.post(store.url(), opciones);
    } else {
        form.put(update.url(editandoId.value), opciones);
    }
}

function eliminar(plantilla: Plantilla) {
    if (confirm(`¿Eliminar la plantilla "${plantilla.nombre}"?`)) {
        router.delete(destroy.url(plantilla.id), { preserveScroll: true });
    }
}

// Vista previa con datos de ejemplo: la primera alternativa de cada [a|b|c]
// y las variables sustituidas. En el envío real se sortea una al azar.
const EJEMPLO: Record<string, string> = {
    nombre: 'Carlos',
    nombre_completo: 'Carlos Pérez',
    plan: 'Mensual',
    fecha: '30/10/2026',
};

const vistaPrevia = computed(() =>
    form.cuerpo
        .replace(/\[([^\[\]|]+(?:\|[^\[\]|]+)+)\]/g, (_, opciones: string) =>
            opciones.split('|')[0],
        )
        .replace(/\{(\w+)\}/g, (completo, nombre: string) =>
            nombre === 'dias'
                ? form.tipo === 'vence_hoy'
                    ? 'hoy'
                    : '3 días'
                : (EJEMPLO[nombre] ?? completo),
        ),
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="text-muted-foreground max-w-xl text-sm">
                Cada aviso usa una plantilla activa elegida al azar (sin repetir
                la anterior). Escribe varias para que los mensajes no sean
                iguales. Usa <code>[Hola|Buenas|Qué tal]</code> para que el
                sistema sortee una de esas palabras en cada envío.
            </p>
            <Button size="sm" @click="abrirNueva">
                <Plus class="size-4" />
                Nueva plantilla
            </Button>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div
                v-for="plantilla in plantillas"
                :key="plantilla.id"
                class="flex flex-col gap-2 rounded-xl border p-4"
                :class="{ 'opacity-60': !plantilla.activa }"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ plantilla.nombre }}</span>
                        <Badge
                            :variant="plantilla.tipo === 'vence_hoy' ? 'default' : 'outline'"
                        >
                            {{ TIPOS[plantilla.tipo] }}
                        </Badge>
                        <Badge v-if="!plantilla.activa" variant="secondary">
                            Inactiva
                        </Badge>
                    </div>
                    <div class="flex">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            title="Editar"
                            @click="abrirEditar(plantilla)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-destructive hover:text-destructive size-8"
                            title="Eliminar"
                            @click="eliminar(plantilla)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
                <p class="text-muted-foreground text-sm whitespace-pre-line">
                    {{ plantilla.cuerpo }}
                </p>
            </div>
        </div>

        <p
            v-if="plantillas.length === 0"
            class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
        >
            No hay plantillas. Sin una plantilla activa de cada tipo no se
            generan avisos.
        </p>

        <Dialog v-model:open="dialogAbierto">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editandoId === null ? 'Nueva plantilla' : 'Editar plantilla' }}
                    </DialogTitle>
                    <DialogDescription>
                        El mensaje que recibe el cliente por WhatsApp.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="guardar">
                    <div class="grid gap-2">
                        <Label>¿Cuándo se usa?</Label>
                        <div class="inline-flex w-fit gap-1 rounded-full bg-neutral-500/10 p-1">
                            <button
                                v-for="(texto, tipo) in TIPOS"
                                :key="tipo"
                                type="button"
                                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-all"
                                :class="
                                    form.tipo === tipo
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="form.tipo = tipo"
                            >
                                {{ texto }}
                            </button>
                        </div>
                        <InputError :message="form.errors.tipo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="plantilla_nombre">Nombre interno</Label>
                        <Input
                            id="plantilla_nombre"
                            v-model="form.nombre"
                            placeholder="Aviso amistoso"
                        />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="plantilla_cuerpo">Mensaje</Label>
                        <Textarea
                            id="plantilla_cuerpo"
                            v-model="form.cuerpo"
                            rows="5"
                            placeholder="[Hola|Buenas] {nombre}, tu plan {plan} vence en {dias}."
                        />
                        <InputError :message="form.errors.cuerpo" />
                        <p class="text-muted-foreground text-xs">
                            Variables:
                            <span v-for="(descripcion, nombre) in variables" :key="nombre">
                                <code :title="descripcion">{{ '{' + nombre + '}' }}</code
                                >{{ ' ' }}
                            </span>
                        </p>
                    </div>

                    <div v-if="form.cuerpo" class="bg-muted/50 rounded-lg p-3 text-sm">
                        <p class="text-muted-foreground mb-1 text-xs">Vista previa</p>
                        <p class="whitespace-pre-line">{{ vistaPrevia }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="plantilla_activa" v-model="form.activa" />
                        <Label for="plantilla_activa">Plantilla activa</Label>
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Guardando...' : 'Guardar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
