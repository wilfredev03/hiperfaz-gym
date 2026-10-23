<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import MembresiaFields from '@/components/socios/MembresiaFields.vue';
import SocioFormFields from '@/components/socios/SocioFormFields.vue';
import { Button } from '@/components/ui/button';
import type { MetodoPagoOpcion, TasaVigente } from '@/lib/clientes';
import {
    CAMPOS_ERROR_MEMBRESIA,
    cobroEnEfectivo,
    hayMembresia,
    membresiaElegida,
    membresiaVacia,
} from '@/lib/socios';
import type { CampoErrorMembresia, PlanOpcion } from '@/lib/socios';
import { etiquetaMetodo, fmtBs, fmtUsd } from '@/lib/ventas';
import { index, store } from '@/routes/socios';

const props = defineProps<{
    planes: PlanOpcion[];
    metodosPago: MetodoPagoOpcion[];
    tasaCambio: TasaVigente | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: index() }],
    },
});

const form = useForm({
    nombre: '',
    documento: '',
    telefono: '',
    email: '',
    direccion: '',
    id_acceso: '',
    activo: true,
    // la membresía es opcional: sin plan elegido solo se crea el cliente
    membresia: membresiaVacia(),
});

const erroresMembresia = computed(() => {
    const errores = form.errors as Record<string, string>;

    return Object.fromEntries(
        CAMPOS_ERROR_MEMBRESIA.map((campo: CampoErrorMembresia) => [
            campo,
            campo === 'tasa_cambio' ? errores[campo] : errores[`membresia.${campo}`],
        ]),
    );
});

const plan = computed(() => membresiaElegida(form.membresia, props.planes));

const metodo = computed(
    () =>
        props.metodosPago.find((m) => m.id === form.membresia.metodo_pago_id) ??
        null,
);

const recibidoInsuficiente = computed(
    () =>
        (cobroEnEfectivo(
            form.membresia,
            props.planes,
            props.metodosPago,
            props.tasaCambio,
        )?.falta ?? 0) > 0,
);

const totalBs = computed(() =>
    plan.value && props.tasaCambio
        ? plan.value.precio * Number(props.tasaCambio.tasa)
        : null,
);

const formaDePago = computed(() => {
    if (!plan.value) {
        return null;
    }

    return form.membresia.es_credito
        ? 'A crédito'
        : metodo.value
          ? etiquetaMetodo(metodo.value)
          : 'Sin elegir';
});

const textoBoton = computed(() => {
    if (form.processing) {
        return 'Guardando...';
    }

    if (!plan.value) {
        return 'Crear cliente';
    }

    return form.membresia.es_credito
        ? 'Crear cliente y activar a crédito'
        : 'Crear cliente y cobrar membresía';
});

function crearSocio() {
    form.transform((datos) => ({
        ...datos,
        membresia: hayMembresia(datos.membresia) ? datos.membresia : null,
    })).post(store.url());
}
</script>

<template>
    <Head title="Nuevo cliente" />

    <form
        class="flex flex-1 flex-col gap-4 p-4 sm:p-6"
        @submit.prevent="crearSocio"
    >
        <Link
            :href="index.url()"
            class="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-sm"
        >
            <ArrowLeft class="size-4" />
            Regresar a clientes
        </Link>

        <div>
            <h1 class="text-xl font-semibold">Nuevo cliente</h1>
            <p class="text-muted-foreground text-sm">
                Solo el nombre es obligatorio. Si quieres, actívale la
                membresía de una vez.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <section class="rounded-xl border p-4 sm:p-5">
                    <h2 class="mb-4 text-sm font-semibold">
                        Datos del cliente
                    </h2>
                    <SocioFormFields :form="form" :mostrar-activo="false" />
                </section>

                <section class="rounded-xl border p-4 sm:p-5 lg:flex-1">
                    <h2 class="mb-4 text-sm font-semibold">
                        Membresía
                        <span class="text-muted-foreground font-normal"
                            >(opcional)</span
                        >
                    </h2>
                    <MembresiaFields
                        :modelo="form.membresia"
                        :planes="planes"
                        :metodos-pago="metodosPago"
                        :tasa="tasaCambio"
                        :errores="erroresMembresia"
                        :mostrar-total="false"
                        seccion="plan"
                        opcional
                    />
                </section>
            </div>

            <!-- resumen, forma de pago y botón: la tarjeta mide lo mismo que la columna
            izquierda y el botón queda siempre al pie -->
            <aside class="flex flex-col rounded-xl border p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Resumen</h2>

                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Cliente</dt>
                        <dd class="text-right font-medium">
                            {{ form.nombre.trim() || '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Membresía</dt>
                        <dd class="text-right">
                            <template v-if="plan">
                                {{ plan.nombre }}
                                <span class="text-muted-foreground block text-xs">
                                    {{ plan.duracion }}
                                </span>
                            </template>
                            <template v-else>Sin membresía</template>
                        </dd>
                    </div>
                    <div v-if="formaDePago" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Forma de pago</dt>
                        <dd class="text-right">{{ formaDePago }}</dd>
                    </div>
                </dl>

                <div
                    v-if="plan"
                    class="mt-4 flex items-baseline justify-between gap-3 border-t pt-4"
                >
                    <span class="text-muted-foreground text-sm">
                        {{
                            form.membresia.es_credito
                                ? 'Quedará debiendo'
                                : 'Total a cobrar'
                        }}
                    </span>
                    <span class="text-right">
                        <span class="text-lg font-semibold tabular-nums">{{
                            fmtUsd(plan.precio)
                        }}</span>
                        <span
                            v-if="totalBs !== null"
                            class="text-muted-foreground block text-xs tabular-nums"
                        >
                            {{ fmtBs(totalBs) }}
                        </span>
                    </span>
                </div>

                <div v-if="plan" class="mt-5 border-t pt-5">
                    <MembresiaFields
                        :modelo="form.membresia"
                        :planes="planes"
                        :metodos-pago="metodosPago"
                        :tasa="tasaCambio"
                        :errores="erroresMembresia"
                        :mostrar-total="false"
                        seccion="pago"
                        opcional
                    />
                </div>

                <div class="mt-auto pt-5">
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="
                            form.processing ||
                            recibidoInsuficiente ||
                            (plan !== null && !tasaCambio)
                        "
                    >
                        {{ textoBoton }}
                    </Button>
                </div>
            </aside>
        </div>
    </form>
</template>
