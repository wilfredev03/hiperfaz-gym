<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import AbonoDialog from '@/components/clientes/AbonoDialog.vue';
import CompartidorEstado from '@/components/clientes/CompartidorEstado.vue';
import ClienteFormFields from '@/components/clientes/ClienteFormFields.vue';
import ClienteLista from '@/components/clientes/ClienteLista.vue';
import ClientePanel from '@/components/clientes/ClientePanel.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    ClienteLista as ClienteListaItem,
    CuentaCliente,
    MetodoPagoOpcion,
    ProductoVendible,
    TasaVigente,
    VentaCabecera,
} from '@/lib/clientes';
import { index, store } from '@/routes/clientes';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type Filtro = 'todos' | 'con_deuda';

const props = defineProps<{
    clientes: { data: ClienteListaItem[]; links: PaginationLink[] };
    busqueda: string;
    filtro: Filtro;
    resumen: { clientes_con_deuda: number; total_por_cobrar: number };
    seleccionado: CuentaCliente | null;
    vista: 'cuenta' | 'venta' | 'datos';
    metodosPago: MetodoPagoOpcion[];
    tasaCambio: TasaVigente | null;
    productos?: ProductoVendible[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cuentas por cobrar', href: index() }],
    },
});

// volver de la ficha a la lista conserva búsqueda y filtro
const hrefLista = computed(() =>
    index.url({
        query: {
            ...(props.busqueda ? { busqueda: props.busqueda } : {}),
            ...(props.filtro !== 'con_deuda' ? { filtro: props.filtro } : {}),
        },
    }),
);

const tasaNumero = computed(() =>
    props.tasaCambio ? Number(props.tasaCambio.tasa) : null,
);

// --- Abono desde la lista -----------------------------------------------------

const abonoAbierto = ref(false);
const clienteAbono = ref<ClienteListaItem | null>(null);
const ventaAbono = ref<VentaCabecera | null>(null);

function abrirAbono(cliente: ClienteListaItem, venta: VentaCabecera | null) {
    clienteAbono.value = cliente;
    ventaAbono.value = venta;
    abonoAbierto.value = true;
}

// --- Compartir desde la lista ---------------------------------------------------

const compartidor = ref<InstanceType<typeof CompartidorEstado> | null>(null);

// null = toda la deuda; con una venta = solo esa. Abre directo el menú nativo.
function abrirCompartir(
    cliente: ClienteListaItem,
    venta: VentaCabecera | null,
) {
    compartidor.value?.compartir({
        cliente,
        ventas: cliente.ventas_pendientes,
        ventaId: venta?.id ?? null,
    });
}

// --- Nuevo cliente ------------------------------------------------------------

const dialogAbierto = ref(false);

const nuevoClienteForm = useForm({
    nombre: '',
    documento: '',
    telefono: '',
    direccion: '',
    activo: true,
});

function abrirNuevoCliente() {
    nuevoClienteForm.reset();
    nuevoClienteForm.clearErrors();
    dialogAbierto.value = true;
}

function crearCliente() {
    nuevoClienteForm.post(store.url(), {
        onSuccess: () => {
            dialogAbierto.value = false;
        },
    });
}
</script>

<template>
    <Head title="Cuentas por cobrar" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <!-- ficha completa del cliente -->
        <template v-if="seleccionado">
            <Link
                :href="hrefLista"
                class="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-sm"
            >
                <ArrowLeft class="size-4" />
                Cuentas por cobrar
            </Link>

            <ClientePanel
                :key="seleccionado.cliente.id"
                :cuenta="seleccionado"
                :metodos-pago="metodosPago"
                :tasa="tasaCambio"
                :productos="productos"
                :vista-inicial="vista"
            />
        </template>

        <!-- vista principal: cada cliente despliega sus ventas por cobrar en su fila -->
        <template v-else>
            <h1 class="text-xl font-semibold">Cuentas por cobrar</h1>

            <ClienteLista
                :clientes="clientes"
                :busqueda="busqueda"
                :filtro="filtro"
                :resumen="resumen"
                :tasa="tasaNumero"
                @nuevo="abrirNuevoCliente"
                @abonar="abrirAbono"
                @compartir="abrirCompartir"
            />

            <CompartidorEstado ref="compartidor" :tasa="tasaCambio" />

            <AbonoDialog
                v-if="clienteAbono"
                v-model:open="abonoAbierto"
                :cliente="clienteAbono"
                :venta="ventaAbono"
                :ventas="clienteAbono.ventas_pendientes"
                :metodos-pago="metodosPago"
                :tasa="tasaCambio"
            />
        </template>
    </div>

    <Dialog v-model:open="dialogAbierto">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nuevo cliente</DialogTitle>
                <DialogDescription
                    >Solo el nombre es obligatorio.</DialogDescription
                >
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="crearCliente">
                <ClienteFormFields
                    :form="nuevoClienteForm"
                    :mostrar-activo="false"
                />

                <Button type="submit" :disabled="nuevoClienteForm.processing">
                    {{
                        nuevoClienteForm.processing
                            ? 'Guardando...'
                            : 'Crear cliente'
                    }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
