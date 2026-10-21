<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Truck, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ProveedorFormFields from '@/components/proveedores/ProveedorFormFields.vue';
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
} from '@/actions/App/Http/Controllers/ProveedorController';
import { destroy, index } from '@/routes/proveedores';

interface ProveedorRow {
    id: number;
    nombre: string;
    documento: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    activo: boolean;
    ordenes_compra_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    proveedores: { data: ProveedorRow[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Proveedores', href: index() }],
    },
});

function eliminar(fila: ProveedorRow) {
    if (confirm(`¿Eliminar el proveedor "${fila.nombre}"?`)) {
        router.delete(destroy.url(fila.id), { preserveScroll: true });
    }
}

// --- Alta (modal) ---------------------------------------------------------

const dialogNuevoAbierto = ref(false);

const formNuevo = useForm({
    nombre: '',
    documento: '',
    telefono: '',
    email: '',
    direccion: '',
    activo: true,
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
    documento: '',
    telefono: '',
    email: '',
    direccion: '',
    activo: true,
});

function abrirDialogEditar(x: ProveedorRow) {
    editandoId.value = x.id;
    formEditar.reset();
    formEditar.clearErrors();
    formEditar.nombre = x.nombre;
    formEditar.documento = x.documento ?? '';
    formEditar.telefono = x.telefono ?? '';
    formEditar.email = x.email ?? '';
    formEditar.direccion = x.direccion ?? '';
    formEditar.activo = x.activo;
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
    <Head title="Proveedores" />

    <h1 class="sr-only">Proveedores</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                A quién le compras la mercancía. Un proveedor con órdenes de
                compra no se puede eliminar: desactívalo.
            </p>
            <Button size="sm" @click="abrirDialogNuevo">
                <Plus class="size-4" />
                Nuevo proveedor
            </Button>
        </div>

        <div
            v-if="proveedores.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Truck class="size-8" />
            <p class="text-sm font-medium">
                Todavía no hay proveedores registrados.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Teléfono</th>
                        <th class="px-4 py-3 font-medium">Compras</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="fila in proveedores.data"
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
                            <Badge
                                v-if="!fila.activo"
                                variant="secondary"
                                class="ml-2"
                                >Inactivo</Badge
                            >
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ fila.telefono || '—' }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ fila.ordenes_compra_count }}
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
            v-if="proveedores.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in proveedores.links"
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
                    <DialogTitle>Nuevo proveedor</DialogTitle>
                    <DialogDescription
                        >Registra a quién le compras.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarNuevo"
                >
                    <ProveedorFormFields :form="formNuevo" />

                    <DialogFooter>
                        <Button type="submit" :disabled="formNuevo.processing">
                            {{
                                formNuevo.processing
                                    ? 'Guardando...'
                                    : 'Crear proveedor'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogEditarAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar proveedor</DialogTitle>
                    <DialogDescription
                        >Actualiza los datos del proveedor.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarEditar"
                >
                    <ProveedorFormFields :form="formEditar" />

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
