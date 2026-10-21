<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { computed } from 'vue';
import {
    cantidadDesdeInput,
    cantidadMostrada,
    decrementada,
    incrementada,
    minimoInput,
    normalizada,
    pasoInput,
} from '@/lib/cantidades';
import type { LineaCantidad, UnidadEntrada } from '@/lib/cantidades';

// La píldora de cantidad del mostrador: −, número, toggle g/kg (solo en
// productos que se pesan) y +. La cantidad que entra y sale siempre está en kg.
const props = defineProps<{
    cantidad: string;
    fraccionable: boolean;
    unidadEntrada: UnidadEntrada;
    nombre: string;
}>();

const emit = defineEmits<{
    (e: 'update:cantidad', valor: string): void;
    (e: 'update:unidadEntrada', valor: UnidadEntrada): void;
    (e: 'quitar'): void;
}>();

const linea = computed<LineaCantidad>(() => ({
    cantidad: props.cantidad,
    fraccionable: props.fraccionable,
    unidadEntrada: props.unidadEntrada,
}));

function disminuir() {
    const nueva = decrementada(linea.value);
    if (nueva === null) {
        emit('quitar');
        return;
    }
    emit('update:cantidad', nueva);
}

function aumentar() {
    emit('update:cantidad', incrementada(linea.value));
}

function escribir(evento: Event) {
    emit(
        'update:cantidad',
        cantidadDesdeInput(
            linea.value,
            (evento.target as HTMLInputElement).value,
        ),
    );
}

function confirmar() {
    emit('update:cantidad', normalizada(linea.value));
}

// el toggle cambia solo cómo se lee/escribe el número, no la cantidad guardada
function alternarUnidad() {
    emit('update:unidadEntrada', props.unidadEntrada === 'g' ? 'kg' : 'g');
}
</script>

<template>
    <div
        class="border-primary/25 bg-primary/5 flex h-6.5 w-fit shrink-0 items-stretch justify-self-start overflow-hidden rounded-full border shadow-sm"
    >
        <button
            type="button"
            class="text-primary hover:bg-destructive/15 hover:text-destructive flex w-5.5 shrink-0 items-center justify-center transition-colors"
            :aria-label="`Quitar una unidad de ${nombre}`"
            @click="disminuir"
        >
            <Minus class="size-3" />
        </button>
        <input
            :value="cantidadMostrada(linea)"
            type="number"
            :step="pasoInput(linea)"
            :min="minimoInput(linea)"
            :class="fraccionable ? 'w-10' : 'w-8'"
            :aria-label="`Cantidad de ${nombre}`"
            class="focus-visible:bg-background text-primary shrink-0 [appearance:textfield] border-0 bg-transparent text-center text-xs font-semibold tabular-nums outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @input="escribir"
            @change="confirmar"
        />
        <button
            v-if="fraccionable"
            type="button"
            class="text-primary/70 hover:bg-primary/15 hover:text-primary w-5 shrink-0 text-[9px] font-semibold tracking-tight transition-colors"
            :aria-label="`Cantidad en ${unidadEntrada === 'g' ? 'gramos' : 'kilos'}: cambiar unidad`"
            @click="alternarUnidad"
        >
            {{ unidadEntrada }}
        </button>
        <button
            type="button"
            class="text-primary hover:bg-primary/15 flex w-5.5 shrink-0 items-center justify-center transition-colors"
            :aria-label="`Agregar una unidad de ${nombre}`"
            @click="aumentar"
        >
            <Plus class="size-3" />
        </button>
    </div>
</template>
