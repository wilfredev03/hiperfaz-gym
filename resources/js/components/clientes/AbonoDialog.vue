<script setup lang="ts">
import { router, useForm, useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { abonar as abonarVenta } from '@/actions/App/Http/Controllers/VentaController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtFechaCorta, redondear } from '@/lib/clientes';
import type {
    MetodoPagoOpcion,
    TasaVigente,
    VentaCabecera,
} from '@/lib/clientes';
import { etiquetaMetodo, fmtBs, fmtUsd, iconoMetodo } from '@/lib/ventas';
import { abonar as abonarCliente } from '@/routes/clientes';

const props = defineProps<{
    open: boolean;
    cliente: { id: number; nombre: string };
    // con venta: abono a esa venta; sin venta: abono general al cliente
    venta: VentaCabecera | null;
    ventas: VentaCabecera[];
    metodosPago: MetodoPagoOpcion[];
    tasa: TasaVigente | null;
}>();

const emit = defineEmits<{ (e: 'update:open', valor: boolean): void }>();

const monto = ref('');
const metodoId = ref<number | null>(null);
const referencia = ref('');

const httpVenta = useHttp({ metodo_pago_id: 0, monto: 0, referencia: '' });
const formCliente = useForm({ metodo_pago_id: 0, monto: 0, referencia: '' });

// las ventas por cobrar de la más antigua a la más reciente: en ese orden las
// salda un abono general (mismo criterio que CobranzaService::abonarCliente)
const porCobrar = computed(() =>
    props.ventas
        .filter((venta) => venta.saldo_pendiente > 0)
        .sort((a, b) => a.fecha.localeCompare(b.fecha) || a.id - b.id),
);

const maximo = computed(() =>
    props.venta
        ? props.venta.saldo_pendiente
        : redondear(
              porCobrar.value.reduce(
                  (suma, venta) => suma + venta.saldo_pendiente,
                  0,
              ),
          ),
);

const metodo = computed(
    () =>
        props.metodosPago.find((opcion) => opcion.id === metodoId.value) ??
        null,
);

// el cliente paga en bolívares salvo que se elija un método en divisas
// (Efectivo USD / "Divisas"): el monto que se teclea siempre está en la
// moneda del método seleccionado, pero `venta_pagos.monto` guarda USD sin
// importar con qué se pagó, así que todo se convierte antes de enviarlo.
function montoEnMoneda(valorUsd: number, met: MetodoPagoOpcion | null): number {
    if (met?.moneda === 'VES' && props.tasa) {
        return redondear(valorUsd * Number(props.tasa.tasa));
    }
    return redondear(valorUsd);
}

function montoAUsd(valor: string, met: MetodoPagoOpcion | null): number {
    const numero = Number(valor) || 0;
    if (met?.moneda === 'VES' && props.tasa) {
        return numero / Number(props.tasa.tasa);
    }
    return numero;
}

const montoNumero = computed(() => montoAUsd(monto.value, metodo.value));

// un método en bolívares no se puede usar sin la tasa del día para convertir
function requiereTasa(met: MetodoPagoOpcion): boolean {
    return met.moneda === 'VES' && !props.tasa;
}

// cambiar de método no cambia cuánto se va a aplicar (siempre en USD): solo
// recalcula cómo se muestra el número ya escrito, en la moneda del nuevo método
function elegirMetodo(id: number) {
    const nuevo = props.metodosPago.find((opcion) => opcion.id === id);
    if (!nuevo || requiereTasa(nuevo)) return;
    if (monto.value) {
        monto.value = montoEnMoneda(
            montoAUsd(monto.value, metodo.value),
            nuevo,
        ).toFixed(2);
    }
    metodoId.value = id;
}

const procesando = computed(
    () => httpVenta.processing || formCliente.processing,
);

// cómo se repartirá un abono general
const reparto = computed(() => {
    if (props.venta || montoNumero.value <= 0) return [];
    let restante = montoNumero.value;
    const filas: {
        venta: VentaCabecera;
        aplicado: number;
        saldada: boolean;
    }[] = [];
    for (const venta of porCobrar.value) {
        if (restante <= 0.00005) break;
        const aplicado = redondear(Math.min(restante, venta.saldo_pendiente));
        filas.push({
            venta,
            aplicado,
            saldada: redondear(venta.saldo_pendiente - aplicado) <= 0,
        });
        restante = redondear(restante - aplicado);
    }
    return filas;
});

const errorMonto = computed(() => {
    if (montoNumero.value > maximo.value + 0.00005) {
        const limite = montoEnMoneda(maximo.value, metodo.value);
        const texto =
            metodo.value?.moneda === 'VES' ? fmtBs(limite) : fmtUsd(limite);
        return `No puede ser mayor a lo que debe: ${texto}.`;
    }
    return undefined;
});

const puedeRegistrar = computed(
    () =>
        montoNumero.value > 0 &&
        !errorMonto.value &&
        metodo.value !== null &&
        !requiereTasa(metodo.value) &&
        (!metodo.value.requiere_referencia || referencia.value.trim() !== '') &&
        !procesando.value,
);

// referencia en la otra moneda de lo que se está tecleando, para que quede
// claro cuánto es en total sin importar en qué moneda paga el cliente
const equivalente = computed(() => {
    if (montoNumero.value <= 0) return null;
    if (metodo.value?.moneda === 'VES') {
        return fmtUsd(montoNumero.value);
    }
    return props.tasa
        ? fmtBs(montoNumero.value * Number(props.tasa.tasa))
        : null;
});

const erroresServidor = computed(() => {
    const errores = props.venta ? httpVenta.errors : formCliente.errors;
    return {
        monto: errores.monto,
        metodo: errores.metodo_pago_id,
        referencia: errores.referencia,
        caja: (errores as Record<string, string>).cierre_caja,
    };
});

watch(
    () => props.open,
    (abierto) => {
        if (!abierto) return;
        metodoId.value = props.metodosPago[0]?.id ?? null;
        monto.value =
            maximo.value > 0
                ? montoEnMoneda(maximo.value, metodo.value).toFixed(2)
                : '';
        referencia.value = '';
        httpVenta.clearErrors();
        formCliente.clearErrors();
    },
    // el diálogo de la lista se monta ya abierto: también hay que inicializarlo entonces
    { immediate: true },
);

function cerrar() {
    emit('update:open', false);
}

function recargar() {
    router.reload({ only: ['seleccionado', 'clientes', 'resumen'] });
}

function registrar() {
    if (!puedeRegistrar.value || !metodo.value) return;

    const datos = {
        metodo_pago_id: metodo.value.id,
        monto: montoNumero.value,
        referencia: referencia.value.trim(),
    };

    if (props.venta) {
        httpVenta.metodo_pago_id = datos.metodo_pago_id;
        httpVenta.monto = datos.monto;
        httpVenta.referencia = datos.referencia;
        httpVenta.post(abonarVenta.url(props.venta.id), {
            onSuccess: () => {
                toast.success(
                    `Abono de ${fmtUsd(datos.monto)} registrado en ${props.venta?.numero_venta}.`,
                );
                cerrar();
                recargar();
            },
        });
        return;
    }

    formCliente
        .transform(() => datos)
        .post(abonarCliente.url(props.cliente.id), {
            preserveScroll: true,
            onSuccess: cerrar,
        });
}
</script>

<template>
    <Dialog :open="open" @update:open="(valor) => emit('update:open', valor)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    venta ? `Abonar a ${venta.numero_venta}` : 'Registrar abono'
                }}</DialogTitle>
                <DialogDescription>
                    <template v-if="venta">
                        {{ cliente.nombre }} · venta del
                        {{ fmtFechaCorta(venta.fecha) }} · debe
                        {{ fmtUsd(venta.saldo_pendiente) }}
                    </template>
                    <template v-else>
                        {{ cliente.nombre }} · debe {{ fmtUsd(maximo) }} en
                        {{ porCobrar.length }} venta{{
                            porCobrar.length === 1 ? '' : 's'
                        }}
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="registrar">
                <div class="grid gap-2">
                    <Label for="abono-monto"
                        >Monto recibido ({{
                            metodo?.moneda === 'VES' ? 'Bs' : 'USD'
                        }})</Label
                    >
                    <div class="flex gap-2">
                        <Input
                            id="abono-monto"
                            v-model="monto"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0.01"
                            class="h-11 text-lg tabular-nums"
                            :aria-invalid="!!errorMonto"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 shrink-0"
                            @click="
                                monto = montoEnMoneda(maximo, metodo).toFixed(2)
                            "
                        >
                            Saldar todo
                        </Button>
                    </div>
                    <p
                        v-if="equivalente"
                        class="text-muted-foreground text-xs tabular-nums"
                    >
                        Equivale a {{ equivalente }}
                    </p>
                    <InputError
                        :message="errorMonto ?? erroresServidor.monto"
                    />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm leading-none font-medium">
                        Método de pago
                    </legend>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="opcion in metodosPago"
                            :key="opcion.id"
                            type="button"
                            class="focus-visible:ring-ring/50 flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50"
                            :class="
                                metodoId === opcion.id
                                    ? 'border-primary bg-primary/5 font-medium'
                                    : 'hover:bg-accent'
                            "
                            :disabled="requiereTasa(opcion)"
                            :title="
                                requiereTasa(opcion)
                                    ? 'No hay tasa de cambio activa'
                                    : undefined
                            "
                            :aria-pressed="metodoId === opcion.id"
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
                    </div>
                    <InputError :message="erroresServidor.metodo" />
                </fieldset>

                <div v-if="metodo?.requiere_referencia" class="grid gap-2">
                    <Label for="abono-referencia">Referencia</Label>
                    <Input
                        id="abono-referencia"
                        v-model="referencia"
                        maxlength="100"
                        autocomplete="off"
                    />
                    <InputError :message="erroresServidor.referencia" />
                </div>

                <!-- adónde va el dinero de un abono general -->
                <div
                    v-if="reparto.length"
                    class="bg-muted/40 rounded-lg px-3 py-2.5 text-sm"
                >
                    <p class="text-muted-foreground mb-1.5 text-xs font-medium">
                        Se aplica primero a las ventas más antiguas
                    </p>
                    <ul class="grid gap-1">
                        <li
                            v-for="fila in reparto"
                            :key="fila.venta.id"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <span>
                                <span class="tabular-nums">{{
                                    fila.venta.numero_venta
                                }}</span>
                                <span class="text-muted-foreground text-xs">
                                    ·
                                    {{
                                        fila.saldada
                                            ? 'queda pagada'
                                            : 'queda parcial'
                                    }}</span
                                >
                            </span>
                            <span class="font-medium tabular-nums">{{
                                fmtUsd(fila.aplicado)
                            }}</span>
                        </li>
                    </ul>
                </div>

                <InputError :message="erroresServidor.caja" />

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="cerrar"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="!puedeRegistrar">
                        {{
                            procesando
                                ? 'Registrando...'
                                : montoNumero > 0
                                  ? `Registrar ${fmtUsd(montoNumero)}`
                                  : 'Registrar abono'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
