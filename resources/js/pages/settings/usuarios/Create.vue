<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import UsuarioFormFields from '@/components/usuarios/UsuarioFormFields.vue';
import { create, index, store } from '@/routes/usuarios';

defineProps<{
    roles: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios', href: index() },
            { title: 'Nuevo usuario', href: create() },
        ],
    },
});

const form = useForm({
    name: '',
    email: '',
    rol: 'vendedor',
    password: '',
});

function submit() {
    form.post(store.url());
}
</script>

<template>
    <Head title="Nuevo usuario" />

    <h1 class="sr-only">Nuevo usuario</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Nuevo usuario"
            description="Solo un administrador puede crear usuarios."
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <UsuarioFormFields :form="form" :roles="roles" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando...' : 'Crear usuario' }}
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
