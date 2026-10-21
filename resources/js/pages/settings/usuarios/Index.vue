<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/usuarios';

interface UsuarioRow {
    id: number;
    name: string;
    email: string;
    rol: 'admin' | 'vendedor';
}

defineProps<{
    usuarios: UsuarioRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const page = usePage();
const yoId = computed(() => page.props.auth.user.id);

function eliminar(usuario: UsuarioRow) {
    if (confirm(`¿Eliminar al usuario "${usuario.email}"?`)) {
        router.delete(destroy.url(usuario.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Usuarios" />

    <h1 class="sr-only">Usuarios</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                El administrador gestiona todo el sistema; el vendedor solo
                ventas, clientes y cierre de caja.
            </p>
            <Button as-child size="sm">
                <Link :href="create()">
                    <Plus class="size-4" />
                    Nuevo usuario
                </Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Usuario</th>
                        <th class="px-4 py-3 font-medium">Rol</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="usuario in usuarios"
                        :key="usuario.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ usuario.name }}
                        </td>
                        <td class="px-4 py-3">{{ usuario.email }}</td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    usuario.rol === 'admin'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{
                                    usuario.rol === 'admin'
                                        ? 'Administrador'
                                        : 'Vendedor'
                                }}
                            </Badge>
                        </td>
                        <td class="space-x-3 px-4 py-3 text-right">
                            <Link
                                :href="edit(usuario.id)"
                                class="text-muted-foreground text-sm hover:underline"
                            >
                                Editar
                            </Link>
                            <button
                                v-if="usuario.id !== yoId"
                                type="button"
                                class="text-destructive text-sm hover:underline"
                                @click="eliminar(usuario)"
                            >
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
