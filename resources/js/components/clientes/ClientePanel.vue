<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { CircleCheck, HandCoins, Share2, ShoppingCart } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import AbonoDialog from '@/components/clientes/AbonoDialog.vue';
import CompartidorEstado from '@/components/clientes/CompartidorEstado.vue';
import ClienteFormFields from '@/components/clientes/ClienteFormFields.vue';
import HistorialDeuda from '@/components/clientes/HistorialDeuda.vue';
import NuevaVentaCliente from '@/components/clientes/NuevaVentaCliente.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { fmtFechaCorta, iniciales } from '@/lib/clientes';
import type {
    CuentaCliente,
    MetodoPagoOpcion,
    ProductoVendible,
    TasaVigente,
    VentaCabecera,
} from '@/lib/clientes';
import { fmtBs, fmtUsd } from '@/lib/ventas';
import { update } from '@/routes/clientes';

type Vista = 'cuenta' | 'venta' | 'datos';

const props = defineProps<{
    cuenta: CuentaCliente;
    metodosPago: MetodoPagoOpcion[];
    tasa: TasaVigente | null;
    productos: ProductoVendible[] | undefined;
    vistaInicial: Vista;
}>();

const VISTAS: { valor: Vista; etiqueta: string }[] = [
    { valor: 'cuenta', etiqueta: 'Historial' },
    { valor: 'venta', etiqueta: 'Nueva venta' },
    { valor: 'datos', etiqueta: 'Datos' },
];

const cliente = computed(() => props.cuenta.cliente);
const deuda = computed(() => Number(cliente.value.saldo_pendiente));
// la deuda en bolívares siempre a la tasa vigente de hoy
const deudaBs = computed(() =>
    props.tasa && deuda.value > 0
        ? deuda.value * Number(props.tasa.tasa)
        : null,
);
const tasaTexto = computed(() =>
    props.tasa
        ? Number(props.tasa.tasa).toLocaleString('es-VE', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 4,
          })
        : null,
);
const ventasPorCobrar = computed(
    () =>
        props.cuenta.ventas.filter((venta) => venta.saldo_pendiente > 0).length,
);

const vista = ref<Vista>('cuenta');
// la venta en curso sobrevive a un vistazo a otra pestaña: una vez abierta, solo se oculta
const ventaAbierta = ref(false);

function ir(nueva: Vista) {
    vista.value = nueva;
    // el catálogo pesa: solo se pide cuando se va a vender
    if (nueva === 'venta') {
        ventaAbierta.value = true;
        if (props.productos === undefined) {
            router.reload({ only: ['productos'] });
        }
    }
}

// se puede llegar directo a "Nueva venta" desde la lista
onMounted(() => ir(props.vistaInicial));

function ventaRegistrada() {
    vista.value = 'cuenta';
    ventaAbierta.value = false;
    router.reload({ only: ['seleccionado', 'clientes', 'resumen'] });
}

// --- Abono ------------------------------------------------------------------

const abonoAbierto = ref(false);
const ventaAbono = ref<VentaCabecera | null>(null);

function abrirAbono(venta: VentaCabecera | null) {
    ventaAbono.value = venta;
    abonoAbierto.value = true;
}

// --- Compartir ----------------------------------------------------------------

const compartidor = ref<InstanceType<typeof CompartidorEstado> | null>(null);

// null = toda la deuda; con una venta = solo esa. Abre directo el menú nativo.
function abrirCompartir(venta: VentaCabecera | null) {
    compartidor.value?.compartir({
        cliente: cliente.value,
        ventas: ventasPendientes.value,
        ventaId: venta?.id ?? null,
    });
}

const ventasPendientes = computed(() =>
    props.cuenta.ventas
        .filter((venta) => venta.saldo_pendiente > 0)
        .sort((a, b) => a.fecha.localeCompare(b.fecha) || a.id - b.id),
);

// --- Datos de contacto ------------------------------------------------------

function valoresDe(datos: typeof cliente.value) {
    return {
        nombre: datos.nombre,
        documento: datos.documento ?? '',
        telefono: datos.telefono ?? '',
        direccion: datos.direccion ?? '',
        activo: datos.activo,
    };
}

const contactoForm = useForm(valoresDe(cliente.value));

watch(cliente, (nuevo) => {
    contactoForm.defaults(valoresDe(nuevo));
    contactoForm.reset();
    contactoForm.clearErrors();
});

function guardarContacto() {
    contactoForm.put(update.url(cliente.value.id), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <header
            class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4"
        >
            <div class="flex min-w-0 items-center gap-3">
                <Avatar class="size-12 shrink-0">
                    <AvatarFallback
                        class="bg-primary/10 text-primary font-semibold"
                        >{{ iniciales(cliente.nombre) }}</AvatarFallback
                    >
                </Avatar>
                <div class="min-w-0">
                    <h1 class="truncate text-xl leading-tight font-semibold">
                        {{ cliente.nombre }}
                    </h1>
                    <p class="text-muted-foreground truncate text-sm">
                        {{ cliente.documento ?? 'Sin documento'
                        }}<template v-if="cliente.telefono">
                            · {{ cliente.telefono }}</template
                        >
                    </p>
                    <div v-if="!cliente.activo" class="mt-1.5 flex gap-1.5">
                        <Badge variant="outline">Inactivo</Badge>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-start gap-3 sm:items-end">
                <div v-if="deuda > 0" class="sm:text-right">
                    <p class="text-muted-foreground text-sm">
                        Debe en {{ ventasPorCobrar }} venta{{
                            ventasPorCobrar === 1 ? '' : 's'
                        }}
                    </p>
                    <p
                        class="text-2xl leading-tight font-semibold text-amber-700 tabular-nums dark:text-amber-400"
                    >
                        {{ fmtUsd(deuda) }}
                    </p>
                    <p
                        v-if="deudaBs !== null"
                        class="text-lg leading-tight font-medium text-amber-700 tabular-nums dark:text-amber-400"
                    >
                        {{ fmtBs(deudaBs) }}
                    </p>
                    <p
                        v-if="tasaTexto"
                        class="text-muted-foreground mt-0.5 text-xs tabular-nums"
                    >
                        Tasa del día: Bs. {{ tasaTexto }}
                    </p>
                    <p v-else class="text-muted-foreground mt-0.5 text-xs">
                        Falta cargar la tasa de hoy para ver el monto en Bs.
                    </p>
                </div>
                <p
                    v-else
                    class="flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400"
                >
                    <CircleCheck class="size-4" />
                    Sin deuda pendiente
                </p>

                <div class="flex flex-wrap gap-2">
                    <Button v-if="deuda > 0" @click="abrirAbono(null)">
                        <HandCoins class="size-4" />
                        Registrar abono
                    </Button>
                    <Button
                        :variant="deuda > 0 ? 'outline' : 'default'"
                        @click="ir('venta')"
                    >
                        <ShoppingCart class="size-4" />
                        Nueva venta
                    </Button>
                    <Button
                        v-if="deuda > 0"
                        variant="outline"
                        @click="abrirCompartir(null)"
                    >
                        <Share2 class="size-4" />
                        Compartir deuda
                    </Button>
                </div>
            </div>
        </header>

        <div
            class="flex gap-1 border-b"
            role="tablist"
            aria-label="Secciones del cliente"
        >
            <button
                v-for="opcion in VISTAS"
                :id="`tab-${opcion.valor}`"
                :key="opcion.valor"
                type="button"
                role="tab"
                class="focus-visible:ring-ring/50 -mb-px border-b-2 px-4 py-2.5 text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-inset"
                :class="
                    vista === opcion.valor
                        ? 'border-primary font-medium'
                        : 'text-muted-foreground hover:text-foreground border-transparent'
                "
                :aria-selected="vista === opcion.valor"
                :aria-controls="`panel-${opcion.valor}`"
                @click="ir(opcion.valor)"
            >
                {{ opcion.etiqueta }}
            </button>
        </div>

        <div
            v-if="vista === 'cuenta'"
            id="panel-cuenta"
            role="tabpanel"
            aria-labelledby="tab-cuenta"
            class="flex flex-col gap-5"
        >
            <p class="text-muted-foreground text-sm">
                <template v-if="cuenta.ventas.length">
                    Ha comprado
                    <span class="text-foreground font-medium tabular-nums">{{
                        fmtUsd(cuenta.total_comprado)
                    }}</span>
                    <template v-if="cuenta.fecha_ultima_compra">
                        · última compra el
                        {{
                            fmtFechaCorta(cuenta.fecha_ultima_compra)
                        }}</template
                    >
                </template>
                <template v-else>Todavía no ha comprado nada.</template>
            </p>
            <HistorialDeuda
                :ventas="cuenta.ventas"
                @abonar="abrirAbono"
                @compartir="abrirCompartir"
            />
        </div>

        <div
            v-if="ventaAbierta"
            v-show="vista === 'venta'"
            id="panel-venta"
            role="tabpanel"
            aria-labelledby="tab-venta"
        >
            <NuevaVentaCliente
                :cliente="cliente"
                :productos="productos"
                :tasa="tasa"
                @registrada="ventaRegistrada"
            />
        </div>

        <div
            v-if="vista === 'datos'"
            id="panel-datos"
            role="tabpanel"
            aria-labelledby="tab-datos"
            class="max-w-xl"
        >
            <form class="grid gap-6" @submit.prevent="guardarContacto">
                <ClienteFormFields :form="contactoForm" />
                <div class="flex items-center gap-3">
                    <Button
                        type="submit"
                        :disabled="
                            contactoForm.processing || !contactoForm.isDirty
                        "
                    >
                        {{
                            contactoForm.processing
                                ? 'Guardando...'
                                : 'Guardar cambios'
                        }}
                    </Button>
                    <p
                        v-if="contactoForm.recentlySuccessful"
                        class="text-muted-foreground text-sm"
                        aria-live="polite"
                    >
                        Guardado.
                    </p>
                </div>
            </form>
        </div>

        <CompartidorEstado ref="compartidor" :tasa="tasa" />

        <AbonoDialog
            v-model:open="abonoAbierto"
            :cliente="cliente"
            :venta="ventaAbono"
            :ventas="cuenta.ventas"
            :metodos-pago="metodosPago"
            :tasa="tasa"
        />
    </div>
</template>
