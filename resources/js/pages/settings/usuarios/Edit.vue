<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import UsuarioFormFields from '@/components/usuarios/UsuarioFormFields.vue';
import { index, update } from '@/routes/usuarios';

const props = defineProps<{
    usuario: { id: number; name: string; email: string; rol: string };
    roles: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios', href: index() },
            { title: 'Editar usuario', href: '#' },
        ],
    },
});

const form = useForm({
    name: props.usuario.name,
    email: props.usuario.email,
    rol: props.usuario.rol,
    password: '',
});

function submit() {
    form.put(update.url(props.usuario.id));
}
</script>

<template>
    <Head title="Editar usuario" />

    <h1 class="sr-only">Editar usuario</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Editar usuario"
            :description="usuario.name"
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <UsuarioFormFields :form="form" :roles="roles" editando />

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
