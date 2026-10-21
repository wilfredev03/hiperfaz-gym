<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CategoriaFormFields from '@/components/categorias/CategoriaFormFields.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    store as guardarCategoria,
    update as actualizarCategoria,
} from '@/actions/App/Http/Controllers/CategoriaController';
import { destroy, index } from '@/routes/categorias';

interface CategoriaRow {
    id: number;
    nombre: string;
    activo: boolean;
    productos_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    categorias: { data: CategoriaRow[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categorías', href: index() }],
    },
});

function eliminar(categoria: CategoriaRow) {
    if (confirm(`¿Eliminar la categoría "${categoria.nombre}"?`)) {
        router.delete(destroy.url(categoria.id), { preserveScroll: true });
    }
}

// --- Alta de categoría (modal) -------------------------------------------

const dialogNuevoAbierto = ref(false);

const formNuevo = useForm({
    nombre: '',
    activo: true,
});

function abrirDialogNuevo() {
    formNuevo.reset();
    formNuevo.clearErrors();
    dialogNuevoAbierto.value = true;
}

function guardarNuevo() {
    formNuevo.post(guardarCategoria.url(), {
        onSuccess: () => {
            dialogNuevoAbierto.value = false;
        },
    });
}

// --- Edición de categoría (modal) -----------------------------------------

const dialogEditarAbierto = ref(false);
const categoriaEditandoId = ref<number | null>(null);

const formEditar = useForm({
    nombre: '',
    activo: true,
});

function abrirDialogEditar(categoria: CategoriaRow) {
    categoriaEditandoId.value = categoria.id;
    formEditar.reset();
    formEditar.clearErrors();
    formEditar.nombre = categoria.nombre;
    formEditar.activo = categoria.activo;
    dialogEditarAbierto.value = true;
}

function guardarEditar() {
    if (categoriaEditandoId.value === null) {
        return;
    }
    formEditar.put(actualizarCategoria.url(categoriaEditandoId.value), {
        onSuccess: () => {
            dialogEditarAbierto.value = false;
        },
    });
}
</script>

<template>
    <Head title="Categorías" />

    <h1 class="sr-only">Categorías</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                Agrupan los productos del catálogo. Una categoría con productos
                no se puede eliminar: desactívala.
            </p>
            <Button size="sm" @click="abrirDialogNuevo">
                <Plus class="size-4" />
                Nueva categoría
            </Button>
        </div>

        <div
            v-if="categorias.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Tags class="size-8" />
            <p class="text-sm font-medium">
                Todavía no hay categorías registradas.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Productos</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="categoria in categorias.data"
                        :key="categoria.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="font-medium hover:underline"
                                @click="abrirDialogEditar(categoria)"
                            >
                                {{ categoria.nombre }}
                            </button>
                            <Badge
                                v-if="!categoria.activo"
                                variant="secondary"
                                class="ml-2"
                                >Inactiva</Badge
                            >
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ categoria.productos_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    title="Editar"
                                    @click="abrirDialogEditar(categoria)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:text-destructive size-8"
                                    title="Eliminar"
                                    @click="eliminar(categoria)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="categorias.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in categorias.links"
                :key="linkIndex"
            >
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

        <Dialog v-model:open="dialogNuevoAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nueva categoría</DialogTitle>
                    <DialogDescription
                        >Agrupa los productos del catálogo.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarNuevo"
                >
                    <CategoriaFormFields :form="formNuevo" />

                    <DialogFooter>
                        <Button type="submit" :disabled="formNuevo.processing">
                            {{
                                formNuevo.processing
                                    ? 'Guardando...'
                                    : 'Crear categoría'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogEditarAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar categoría</DialogTitle>
                    <DialogDescription
                        >Actualiza los datos de la categoría.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarEditar"
                >
                    <CategoriaFormFields :form="formEditar" />

                    <DialogFooter>
                        <Button type="submit" :disabled="formEditar.processing">
                            {{
                                formEditar.processing
                                    ? 'Guardando...'
                                    : 'Guardar cambios'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
