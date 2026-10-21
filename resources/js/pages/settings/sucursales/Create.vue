<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import SucursalFormFields from '@/components/sucursales/SucursalFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index, store } from '@/routes/sucursales';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sucursales', href: index() },
            { title: 'Nueva sucursal', href: create() },
        ],
    },
});

const form = useForm({
    nombre: '',
    termino_orden: 'Orden',
    direccion: '',
    telefono: '',
    activa: true,
});

function submit() {
    form.post(store.url());
}
</script>

<template>
    <Head title="Nueva sucursal" />

    <h1 class="sr-only">Nueva sucursal</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Nueva sucursal"
            description="Registra una nueva ubicación del negocio."
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <SucursalFormFields :form="form" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando...' : 'Crear sucursal' }}
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
