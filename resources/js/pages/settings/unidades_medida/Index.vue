<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Ruler, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import UnidadMedidaFormFields from '@/components/unidades_medida/UnidadMedidaFormFields.vue';
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
    store as guardar,
    update as actualizar,
} from '@/actions/App/Http/Controllers/UnidadMedidaController';
import { destroy, index } from '@/routes/unidades_medida';

interface UnidadMedidaRow {
    id: number;
    nombre: string;
    abreviatura: string;
    productos_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    unidadesMedida: { data: UnidadMedidaRow[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Unidades de medida', href: index() }],
    },
});

function eliminar(fila: UnidadMedidaRow) {
    if (confirm(`¿Eliminar la unidad "${fila.nombre}"?`)) {
        router.delete(destroy.url(fila.id), { preserveScroll: true });
    }
}

// --- Alta (modal) ---------------------------------------------------------

const dialogNuevoAbierto = ref(false);

const formNuevo = useForm({
    nombre: '',
    abreviatura: '',
});

function abrirDialogNuevo() {
    formNuevo.reset();
    formNuevo.clearErrors();
    dialogNuevoAbierto.value = true;
}

function guardarNuevo() {
    formNuevo.post(guardar.url(), {
        onSuccess: () => {
            dialogNuevoAbierto.value = false;
        },
    });
}

// --- Edición (modal) ------------------------------------------------------

const dialogEditarAbierto = ref(false);
const editandoId = ref<number | null>(null);

const formEditar = useForm({
    nombre: '',
    abreviatura: '',
});

function abrirDialogEditar(x: UnidadMedidaRow) {
    editandoId.value = x.id;
    formEditar.reset();
    formEditar.clearErrors();
    formEditar.nombre = x.nombre;
    formEditar.abreviatura = x.abreviatura;
    dialogEditarAbierto.value = true;
}

function guardarEditar() {
    if (editandoId.value === null) {
        return;
    }
    formEditar.put(actualizar.url(editandoId.value), {
        onSuccess: () => {
            dialogEditarAbierto.value = false;
        },
    });
}
</script>

<template>
    <Head title="Unidades de medida" />

    <h1 class="sr-only">Unidades de medida</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                En qué se mide y se vende cada producto (kg, unidad, docena...).
                Una unidad con productos no se puede eliminar.
            </p>
            <Button size="sm" @click="abrirDialogNuevo">
                <Plus class="size-4" />
                Nueva unidad
            </Button>
        </div>

        <div
            v-if="unidadesMedida.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Ruler class="size-8" />
            <p class="text-sm font-medium">
                Todavía no hay unidades de medida registradas.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Abreviatura</th>
                        <th class="px-4 py-3 font-medium">Productos</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="fila in unidadesMedida.data"
                        :key="fila.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="font-medium hover:underline"
                                @click="abrirDialogEditar(fila)"
                            >
                                {{ fila.nombre }}
                            </button>
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ fila.abreviatura }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ fila.productos_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    title="Editar"
                                    @click="abrirDialogEditar(fila)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:text-destructive size-8"
                                    title="Eliminar"
                                    @click="eliminar(fila)"
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
            v-if="unidadesMedida.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in unidadesMedida.links"
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
                    <DialogTitle>Nueva unidad</DialogTitle>
                    <DialogDescription
                        >Define cómo se mide un producto.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarNuevo"
                >
                    <UnidadMedidaFormFields :form="formNuevo" />

                    <DialogFooter>
                        <Button type="submit" :disabled="formNuevo.processing">
                            {{
                                formNuevo.processing
                                    ? 'Guardando...'
                                    : 'Crear unidad'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogEditarAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar unidad de medida</DialogTitle>
                    <DialogDescription
                        >Actualiza los datos de la unidad.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarEditar"
                >
                    <UnidadMedidaFormFields :form="formEditar" />

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
