<script setup lang="ts">
import { ArrowLeft, Check, Clock, SlidersHorizontal } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MetodoPagoOpcion, TasaVigente } from '@/lib/clientes';
import {
    cobroEnEfectivo,
    hayMembresia,
    membresiaElegida,
    textoDuracion,
} from '@/lib/socios';
import type {
    CampoErrorMembresia,
    MembresiaForm,
    PlanOpcion,
} from '@/lib/socios';
import { etiquetaMetodo, fmtBs, fmtUsd, iconoMetodo } from '@/lib/ventas';

const props = withDefaults(
    defineProps<{
        modelo: MembresiaForm;
        planes: PlanOpcion[];
        metodosPago: MetodoPagoOpcion[];
        tasa: TasaVigente | null;
        errores?: Partial<Record<CampoErrorMembresia, string>>;
        // en el alta del cliente la membresía es opcional: tocar la elegida la quita
        opcional?: boolean;
        // la página de alta muestra el total en su propio resumen
        mostrarTotal?: boolean;
        // el alta reparte la pantalla: el plan a la izquierda y el pago junto al resumen
        seccion?: 'todo' | 'plan' | 'pago';
    }>(),
    {
        errores: () => ({}),
        opcional: false,
        mostrarTotal: true,
        seccion: 'todo',
    },
);

const elegida = computed(() => membresiaElegida(props.modelo, props.planes));

const metodo = computed(
    () =>
        props.metodosPago.find((m) => m.id === props.modelo.metodo_pago_id) ??
        null,
);

const totalBs = computed(() =>
    elegida.value && props.tasa
        ? elegida.value.precio * Number(props.tasa.tasa)
        : null,
);

// el primer método queda elegido por defecto para cobrar con un solo toque
watch(
    () => hayMembresia(props.modelo),
    (hay) => {
        if (hay && props.modelo.metodo_pago_id === null) {
            props.modelo.metodo_pago_id = props.metodosPago[0]?.id ?? null;
        }
    },
);

// en efectivo/divisas se anota lo que entrega el cliente: por defecto es justo el
// total, y si es más la diferencia es el vuelto (en la moneda del método)
const efectivo = computed(() =>
    cobroEnEfectivo(props.modelo, props.planes, props.metodosPago, props.tasa),
);

watch(
    () => efectivo.value?.total ?? null,
    (total) => {
        props.modelo.monto_recibido = total === null ? '' : String(total);
    },
    { immediate: true },
);

function elegirPlan(id: number) {
    props.modelo.personalizada = false;
    props.modelo.plan_id =
        props.opcional && props.modelo.plan_id === id ? null : id;
}

function elegirPersonalizada() {
    props.modelo.plan_id = null;
    props.modelo.personalizada = true;
}

function volverAPlanes() {
    props.modelo.personalizada = false;
    props.modelo.plan_id = null;
}

function elegirMetodo(id: number) {
    props.modelo.es_credito = false;
    props.modelo.metodo_pago_id = id;
}

// un método en bolívares no se puede usar sin la tasa del día para convertir
function requiereTasa(met: MetodoPagoOpcion): boolean {
    return met.moneda === 'VES' && !props.tasa;
}

function metodoActivo(id: number): boolean {
    return !props.modelo.es_credito && props.modelo.metodo_pago_id === id;
}
</script>

<template>
    <div class="grid gap-5">
        <p
            v-if="!tasa && seccion !== 'pago'"
            class="rounded-lg border border-amber-600/30 bg-amber-600/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400"
        >
            Todavía no se cargó la tasa de cambio de hoy: no se puede cobrar una
            membresía hasta cargarla en Tasas de cambio.
        </p>

        <div
            v-if="seccion !== 'pago'"
            role="group"
            aria-label="Plan"
            class="grid gap-2"
        >
            <!-- "Personalizada" es una card más de la cuadrícula; al elegirla reemplaza
            las cards por su formulario y ofrece volver a los planes -->
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm leading-none font-medium">{{
                    modelo.personalizada ? 'Membresía personalizada' : 'Plan'
                }}</span>
                <Button
                    v-if="modelo.personalizada"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click="volverAPlanes"
                >
                    <ArrowLeft class="size-3.5" />
                    Volver a planes
                </Button>
            </div>

            <div v-if="!modelo.personalizada" class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="opcion in planes"
                    :key="opcion.id"
                    type="button"
                    class="focus-visible:ring-ring/50 flex items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors outline-none focus-visible:ring-[3px]"
                    :class="
                        modelo.plan_id === opcion.id
                            ? 'border-primary bg-primary/5'
                            : 'hover:bg-accent'
                    "
                    :aria-pressed="modelo.plan_id === opcion.id"
                    @click="elegirPlan(opcion.id)"
                >
                    <span class="min-w-0">
                        <span
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Check
                                v-if="modelo.plan_id === opcion.id"
                                class="text-primary size-3.5 shrink-0"
                            />
                            <span class="truncate">{{ opcion.nombre }}</span>
                        </span>
                        <span
                            class="text-muted-foreground flex items-center gap-1 text-xs"
                        >
                            <Clock class="size-3 shrink-0" />
                            {{ textoDuracion(opcion.duracion_dias) }}
                        </span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block text-sm font-semibold tabular-nums">
                            {{ fmtUsd(opcion.precio_venta) }}
                        </span>
                        <span
                            v-if="tasa"
                            class="text-muted-foreground block text-xs tabular-nums"
                        >
                            {{
                                fmtBs(
                                    Number(opcion.precio_venta) *
                                        Number(tasa.tasa),
                                )
                            }}
                        </span>
                    </span>
                </button>
                <button
                    type="button"
                    class="focus-visible:ring-ring/50 hover:bg-accent flex items-center gap-2 rounded-lg border border-dashed px-3 py-2.5 text-left transition-colors outline-none focus-visible:ring-[3px]"
                    @click="elegirPersonalizada"
                >
                    <SlidersHorizontal
                        class="text-muted-foreground size-4 shrink-0"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium"
                            >Personalizada</span
                        >
                        <span class="text-muted-foreground block text-xs"
                            >Precio y duración a tu medida</span
                        >
                    </span>
                </button>
            </div>
            <p
                v-if="!modelo.personalizada && planes.length === 0"
                class="text-muted-foreground text-xs"
            >
                No hay planes activos. Créalos en Configuración > Membresías, o
                usa una membresía personalizada.
            </p>

            <!-- formulario a la medida, en horizontal para ocupar lo mismo que las cards -->
            <div
                v-else-if="modelo.personalizada"
                class="bg-muted/30 flex flex-wrap items-start gap-x-4 gap-y-3 rounded-lg border p-3"
            >
                <div class="grid w-28 gap-1.5">
                    <Label for="membresia-precio">Precio (USD)</Label>
                    <Input
                        id="membresia-precio"
                        v-model="modelo.precio"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0.01"
                        class="tabular-nums"
                    />
                    <InputError :message="errores.precio" />
                </div>

                <div class="grid gap-1.5">
                    <span class="text-sm leading-none font-medium"
                        >Duración</span
                    >
                    <div class="inline-flex rounded-lg border p-0.5">
                        <button
                            v-for="opcion in [
                                { valor: 'dias', etiqueta: 'Por días' },
                                { valor: 'fechas', etiqueta: 'Por fechas' },
                            ] as const"
                            :key="opcion.valor"
                            type="button"
                            class="focus-visible:ring-ring/50 rounded-md px-3 py-1.5 text-sm transition-colors outline-none focus-visible:ring-[3px]"
                            :class="
                                modelo.modo === opcion.valor
                                    ? 'bg-primary text-primary-foreground font-medium'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="modelo.modo === opcion.valor"
                            @click="modelo.modo = opcion.valor"
                        >
                            {{ opcion.etiqueta }}
                        </button>
                    </div>
                </div>

                <div v-if="modelo.modo === 'dias'" class="grid w-28 gap-1.5">
                    <Label for="membresia-dias">Días</Label>
                    <Input
                        id="membresia-dias"
                        v-model="modelo.dias"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        step="1"
                        placeholder="9"
                        class="tabular-nums"
                    />
                    <InputError :message="errores.dias" />
                </div>

                <template v-else>
                    <div class="grid w-40 gap-1.5">
                        <Label for="membresia-inicio">Desde</Label>
                        <Input
                            id="membresia-inicio"
                            v-model="modelo.fecha_inicio"
                            type="date"
                        />
                        <InputError :message="errores.fecha_inicio" />
                    </div>
                    <div class="grid w-40 gap-1.5">
                        <Label for="membresia-fin">Hasta</Label>
                        <Input
                            id="membresia-fin"
                            v-model="modelo.fecha_fin"
                            type="date"
                            :min="modelo.fecha_inicio || undefined"
                        />
                        <InputError :message="errores.fecha_fin" />
                    </div>
                </template>

                <p class="text-muted-foreground w-full text-xs">
                    {{
                        modelo.modo === 'dias'
                            ? 'Empieza hoy, o al día siguiente de que venza su membresía vigente.'
                            : '"Hasta" es el último día con acceso.'
                    }}
                </p>
            </div>

            <InputError :message="errores.plan_id" />
            <InputError :message="errores.tasa_cambio" />
        </div>

        <template v-if="elegida && seccion !== 'plan'">
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm leading-none font-medium">
                    Forma de pago
                </legend>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="opcion in metodosPago"
                        :key="opcion.id"
                        type="button"
                        class="focus-visible:ring-ring/50 flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50"
                        :class="
                            metodoActivo(opcion.id)
                                ? 'border-primary bg-primary/5 font-medium'
                                : 'hover:bg-accent'
                        "
                        :disabled="requiereTasa(opcion)"
                        :title="
                            requiereTasa(opcion)
                                ? 'No hay tasa de cambio activa'
                                : undefined
                        "
                        :aria-pressed="metodoActivo(opcion.id)"
                        @click="elegirMetodo(opcion.id)"
                    >
                        <component
                            :is="iconoMetodo(opcion)"
                            class="text-muted-foreground size-4 shrink-0"
                        />
                        <span class="truncate">{{
                            etiquetaMetodo(opcion)
                        }}</span>
                    </button>
                    <button
                        type="button"
                        class="focus-visible:ring-ring/50 col-span-2 flex min-h-11 items-center gap-2 rounded-lg border border-dashed px-3 py-2 text-left text-sm transition-colors outline-none focus-visible:ring-[3px]"
                        :class="
                            modelo.es_credito
                                ? 'border-primary bg-primary/5 font-medium'
                                : 'hover:bg-accent'
                        "
                        :aria-pressed="modelo.es_credito"
                        @click="modelo.es_credito = !modelo.es_credito"
                    >
                        <span class="truncate"
                            >Dejar a crédito (paga después)</span
                        >
                    </button>
                </div>
                <InputError :message="errores.metodo_pago_id" />
            </fieldset>

            <div v-if="efectivo" class="grid gap-2">
                <Label for="membresia-recibido">
                    Monto recibido ({{ efectivo.moneda === 'VES' ? 'Bs' : 'USD' }})
                </Label>
                <Input
                    id="membresia-recibido"
                    v-model="modelo.monto_recibido"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    class="tabular-nums"
                />
                <p
                    v-if="efectivo.falta > 0"
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    Falta
                    {{
                        efectivo.moneda === 'VES'
                            ? fmtBs(efectivo.falta)
                            : fmtUsd(efectivo.falta)
                    }}
                    para cubrir el total.
                </p>
                <p
                    v-else-if="efectivo.vuelto > 0"
                    class="flex items-baseline justify-between rounded-lg bg-emerald-600/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400"
                >
                    <span>Vuelto</span>
                    <strong class="tabular-nums">{{
                        efectivo.moneda === 'VES'
                            ? fmtBs(efectivo.vuelto)
                            : fmtUsd(efectivo.vuelto)
                    }}</strong>
                </p>
                <InputError :message="errores.monto_recibido" />
            </div>

            <div
                v-if="!modelo.es_credito && metodo?.requiere_referencia"
                class="grid gap-2"
            >
                <Label for="membresia-referencia">Referencia</Label>
                <Input
                    id="membresia-referencia"
                    v-model="modelo.referencia"
                    maxlength="100"
                    autocomplete="off"
                />
                <InputError :message="errores.referencia" />
            </div>

            <div
                v-if="mostrarTotal"
                class="bg-muted/40 flex items-baseline justify-between gap-3 rounded-lg px-3 py-2.5 text-sm"
            >
                <span class="text-muted-foreground">
                    {{
                        modelo.es_credito ? 'Quedará debiendo' : 'Total a cobrar'
                    }}
                </span>
                <span class="text-right">
                    <span class="font-semibold tabular-nums">{{
                        fmtUsd(elegida.precio)
                    }}</span>
                    <span
                        v-if="totalBs !== null"
                        class="text-muted-foreground block text-xs tabular-nums"
                    >
                        {{ fmtBs(totalBs) }}
                    </span>
                </span>
            </div>
        </template>
    </div>
</template>
