<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import SucursalFormFields from '@/components/sucursales/SucursalFormFields.vue';
import { Button } from '@/components/ui/button';
import { index, update } from '@/routes/sucursales';

interface SucursalEditable {
    id: number;
    nombre: string;
    termino_orden: string;
    direccion: string | null;
    telefono: string | null;
    activa: boolean;
}

const props = defineProps<{
    sucursal: SucursalEditable;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sucursales', href: index() },
            { title: 'Editar sucursal', href: '#' },
        ],
    },
});

const form = useForm({
    nombre: props.sucursal.nombre,
    termino_orden: props.sucursal.termino_orden,
    direccion: props.sucursal.direccion ?? '',
    telefono: props.sucursal.telefono ?? '',
    activa: props.sucursal.activa,
});

function submit() {
    form.put(update.url(props.sucursal.id));
}
</script>

<template>
    <Head title="Editar sucursal" />

    <h1 class="sr-only">Editar sucursal</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Editar sucursal"
            :description="sucursal.nombre"
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <SucursalFormFields :form="form" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
                <Link
                    :href="index()"
                    class="text-muted-foreground text-sm hover:underline"
                    >Cancelar</Link
                >
            </div>
        </form>
    </div>
</template>
