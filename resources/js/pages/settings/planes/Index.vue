<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarClock, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import {
    store as guardarPlan,
    update as actualizarPlan,
} from '@/actions/App/Http/Controllers/PlanController';
import PlanFormFields from '@/components/planes/PlanFormFields.vue';
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
import { textoDuracion } from '@/lib/socios';
import { fmtUsd } from '@/lib/ventas';
import { index } from '@/routes/planes';

interface PlanRow {
    id: number;
    nombre: string;
    precio_venta: string;
    duracion_dias: number;
    activo: boolean;
    suscripciones_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    planes: { data: PlanRow[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Membresías', href: index() }],
    },
});

// --- Alta de plan (modal) ---------------------------------------------------

const dialogNuevoAbierto = ref(false);

const formNuevo = useForm({
    nombre: '',
    precio_venta: '' as number | string,
    duracion_dias: 30 as number | string,
    activo: true,
});

function abrirDialogNuevo() {
    formNuevo.reset();
    formNuevo.clearErrors();
    dialogNuevoAbierto.value = true;
}

function guardarNuevo() {
    formNuevo.post(guardarPlan.url(), {
        onSuccess: () => {
            dialogNuevoAbierto.value = false;
        },
    });
}

// --- Edición de plan (modal) ------------------------------------------------

const dialogEditarAbierto = ref(false);
const planEditandoId = ref<number | null>(null);

const formEditar = useForm({
    nombre: '',
    precio_venta: '' as number | string,
    duracion_dias: 30 as number | string,
    activo: true,
});

function abrirDialogEditar(plan: PlanRow) {
    planEditandoId.value = plan.id;
    formEditar.clearErrors();
    formEditar.nombre = plan.nombre;
    formEditar.precio_venta = Number(plan.precio_venta);
    formEditar.duracion_dias = plan.duracion_dias;
    formEditar.activo = plan.activo;
    dialogEditarAbierto.value = true;
}

function guardarEditar() {
    if (planEditandoId.value === null) {
        return;
    }
    formEditar.put(actualizarPlan.url(planEditandoId.value), {
        onSuccess: () => {
            dialogEditarAbierto.value = false;
        },
    });
}
</script>

<template>
    <Head title="Membresías" />

    <h1 class="sr-only">Membresías</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                Las membresías se venden desde el punto de venta y no aparecen
                en el catálogo de productos. Cambiar la duración o el precio
                solo afecta las ventas futuras.
            </p>
            <Button size="sm" @click="abrirDialogNuevo">
                <Plus class="size-4" />
                Nueva membresía
            </Button>
        </div>

        <div
            v-if="planes.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <CalendarClock class="size-8" />
            <p class="text-sm font-medium">
                Todavía no hay membresías registradas.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Membresía</th>
                        <th class="px-4 py-3 font-medium">Duración</th>
                        <th class="px-4 py-3 font-medium">Precio</th>
                        <th class="px-4 py-3 font-medium">Vendidos</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plan in planes.data"
                        :key="plan.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="font-medium hover:underline"
                                @click="abrirDialogEditar(plan)"
                            >
                                {{ plan.nombre }}
                            </button>
                            <Badge
                                v-if="!plan.activo"
                                variant="secondary"
                                class="ml-2"
                                >Inactivo</Badge
                            >
                        </td>
                        <td class="px-4 py-3">
                            {{ textoDuracion(plan.duracion_dias) }}
                        </td>
                        <td class="px-4 py-3">
                            {{ fmtUsd(plan.precio_venta) }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ plan.suscripciones_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                title="Editar"
                                @click="abrirDialogEditar(plan)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="planes.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in planes.links"
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
                    <DialogTitle>Nueva membresía</DialogTitle>
                    <DialogDescription
                        >Define cuánto dura y cuánto cuesta.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarNuevo"
                >
                    <PlanFormFields :form="formNuevo" />

                    <DialogFooter>
                        <Button type="submit" :disabled="formNuevo.processing">
                            {{
                                formNuevo.processing
                                    ? 'Guardando...'
                                    : 'Crear membresía'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogEditarAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar membresía</DialogTitle>
                    <DialogDescription
                        >Las membresías ya vendidas conservan sus
                        fechas.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarEditar"
                >
                    <PlanFormFields :form="formEditar" />

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
