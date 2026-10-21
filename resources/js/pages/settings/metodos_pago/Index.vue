<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import MetodoPagoFormFields from '@/components/metodos_pago/MetodoPagoFormFields.vue';
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
} from '@/actions/App/Http/Controllers/MetodoPagoController';
import { destroy, index } from '@/routes/metodos_pago';

interface MetodoPagoRow {
    id: number;
    nombre: string;
    moneda: string;
    requiere_referencia: boolean;
    activo: boolean;
    orden: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    metodosPago: { data: MetodoPagoRow[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Métodos de pago', href: index() }],
    },
});

function eliminar(fila: MetodoPagoRow) {
    if (confirm(`¿Eliminar el método de pago "${fila.nombre}"?`)) {
        router.delete(destroy.url(fila.id), { preserveScroll: true });
    }
}

// --- Alta (modal) ---------------------------------------------------------

const dialogNuevoAbierto = ref(false);

const formNuevo = useForm({
    nombre: '',
    moneda: 'USD',
    requiere_referencia: false,
    activo: true,
    orden: 0,
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
    moneda: 'USD',
    requiere_referencia: false,
    activo: true,
    orden: 0,
});

function abrirDialogEditar(x: MetodoPagoRow) {
    editandoId.value = x.id;
    formEditar.reset();
    formEditar.clearErrors();
    formEditar.nombre = x.nombre;
    formEditar.moneda = x.moneda;
    formEditar.requiere_referencia = x.requiere_referencia;
    formEditar.activo = x.activo;
    formEditar.orden = x.orden;
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
    <Head title="Métodos de pago" />

    <h1 class="sr-only">Métodos de pago</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                Formas en que se cobra una venta. Uno con pagos registrados no
                se puede eliminar: desactívalo.
            </p>
            <Button size="sm" @click="abrirDialogNuevo">
                <Plus class="size-4" />
                Nuevo método
            </Button>
        </div>

        <div
            v-if="metodosPago.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <CreditCard class="size-8" />
            <p class="text-sm font-medium">
                Todavía no hay métodos de pago registrados.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Moneda</th>
                        <th class="px-4 py-3 font-medium">Orden</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="fila in metodosPago.data"
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
                            {{ fila.moneda }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ fila.orden }}
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
            v-if="metodosPago.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in metodosPago.links"
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
                    <DialogTitle>Nuevo método</DialogTitle>
                    <DialogDescription
                        >Configura una forma de cobro.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarNuevo"
                >
                    <MetodoPagoFormFields :form="formNuevo" />

                    <DialogFooter>
                        <Button type="submit" :disabled="formNuevo.processing">
                            {{
                                formNuevo.processing
                                    ? 'Guardando...'
                                    : 'Crear método'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogEditarAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar método de pago</DialogTitle>
                    <DialogDescription
                        >Actualiza los datos del método de
                        pago.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarEditar"
                >
                    <MetodoPagoFormFields :form="formEditar" />

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
