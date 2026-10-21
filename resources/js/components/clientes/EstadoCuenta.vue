<script setup lang="ts">
import { computed } from 'vue';
import { fmtFechaCorta, fmtFechaHora } from '@/lib/clientes';
import type {
    ClienteBase,
    DetalleVentaCompleto,
    NegocioInfo,
    VentaCabecera,
} from '@/lib/clientes';
import { fmtBs, fmtUsd } from '@/lib/ventas';

// Lo que recibe el cliente: se dibuja una sola vez en un bloque fuera de
// pantalla y se convierte en imagen (ver lib/compartir.ts). Por eso usa
// colores fijos en hex arbitrario (`bg-[#fff]`) y no clases con nombre
// (`bg-white`, `text-neutral-900`...): la imagen debe verse igual en modo
// claro y oscuro, y en cualquier celular, sin depender de la paleta del
// tema. Dos modos: toda la deuda del cliente, o una sola venta con sus
// productos y pagos.
const props = defineProps<{
    negocio: NegocioInfo;
    cliente: Pick<ClienteBase, 'nombre' | 'documento'>;
    // 'deuda' usa `ventas` (las por cobrar); 'venta' usa `detalle`
    modo: 'deuda' | 'venta';
    ventas: VentaCabecera[];
    detalle: DetalleVentaCompleto | null;
    // modo 'deuda': el detalle de cada venta por cobrar. Vacío = solo el resumen
    // (una fila por venta), que es lo que se usa cuando hay demasiadas líneas
    detalles: DetalleVentaCompleto[];
    // tasa vigente de hoy; sin ella solo se muestran dólares
    tasa: number | null;
}>();

const emision = computed(() =>
    new Date().toLocaleDateString('es-VE', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const totalAdeudado = computed(() =>
    props.modo === 'deuda'
        ? props.ventas.reduce((suma, venta) => suma + venta.saldo_pendiente, 0)
        : (props.detalle?.saldo_pendiente ?? 0),
);

function bs(usd: number): string | null {
    return props.tasa === null ? null : fmtBs(usd * props.tasa);
}

const tasaTexto = computed(() =>
    props.tasa === null
        ? null
        : props.tasa.toLocaleString('es-VE', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 4,
          }),
);

// con el detalle de todas las ventas se dibuja un bloque por venta, con sus productos
const conProductos = computed(
    () =>
        props.modo === 'deuda' &&
        props.ventas.length > 0 &&
        props.detalles.length === props.ventas.length,
);

function pagadoDe(detalle: DetalleVentaCompleto): number {
    return detalle.pagos.reduce((suma, pago) => suma + Number(pago.monto), 0);
}

const pagado = computed(() =>
    props.detalle
        ? props.detalle.pagos.reduce(
              (suma, pago) => suma + Number(pago.monto),
              0,
          )
        : 0,
);
</script>

<template>
    <div
        class="w-[520px] bg-[#fff] p-7 text-[13px] leading-snug text-[#171717]"
        style="
            font-family:
                system-ui,
                -apple-system,
                'Segoe UI',
                Roboto,
                sans-serif;
        "
    >
        <!-- negocio -->
        <header
            class="flex items-start justify-between gap-4 border-b border-[#e5e5e5] pb-4"
        >
            <div class="min-w-0">
                <img
                    v-if="negocio.logoUrl"
                    :src="negocio.logoUrl"
                    :alt="negocio.nombre"
                    class="h-12 w-auto max-w-[200px] object-contain"
                />
                <p v-else class="text-lg font-bold">{{ negocio.nombre }}</p>
            </div>
            <div class="text-right text-xs text-[#737373]">
                <p v-if="negocio.logoUrl" class="font-semibold text-[#404040]">
                    {{ negocio.nombre }}
                </p>
                <p v-if="negocio.telefono">{{ negocio.telefono }}</p>
                <p v-if="negocio.direccion" class="max-w-[220px]">
                    {{ negocio.direccion }}
                </p>
            </div>
        </header>

        <!-- a quién y de qué -->
        <section class="flex items-end justify-between gap-4 py-4">
            <div>
                <p class="text-xs text-[#737373]">
                    {{
                        modo === 'deuda'
                            ? 'Estado de cuenta'
                            : `Venta ${detalle?.numero_venta ?? ''}`
                    }}
                </p>
                <p class="text-lg leading-tight font-bold">
                    {{ cliente.nombre }}
                </p>
                <p v-if="cliente.documento" class="text-xs text-[#737373]">
                    {{ cliente.documento }}
                </p>
            </div>
            <div class="text-right text-xs text-[#737373]">
                <p>Emitido el {{ emision }}</p>
                <p v-if="modo === 'venta' && detalle">
                    Venta del {{ fmtFechaCorta(detalle.fecha) }}
                </p>
            </div>
        </section>

        <!-- toda la deuda: una fila por venta -->
        <div v-if="conProductos" class="grid gap-5">
            <section v-for="venta in detalles" :key="venta.id">
                <div
                    class="flex items-baseline justify-between gap-3 border-b border-[#d4d4d4] pb-1"
                >
                    <p class="font-semibold">
                        Venta
                        <span class="tabular-nums">{{
                            venta.numero_venta
                        }}</span>
                        <span class="font-normal text-[#737373]">
                            · {{ fmtFechaCorta(venta.fecha) }}</span
                        >
                    </p>
                    <p class="text-right text-xs text-[#525252]">
                        Saldo
                        <span
                            class="text-sm font-semibold text-[#171717] tabular-nums"
                            >{{ fmtUsd(venta.saldo_pendiente) }}</span
                        >
                    </p>
                </div>
                <table class="w-full border-collapse">
                    <tbody>
                        <tr
                            v-for="linea in venta.detalles"
                            :key="linea.id"
                            class="border-b border-[#f5f5f5]"
                        >
                            <td class="py-1.5 pr-2">
                                {{ linea.producto.nombre }}
                            </td>
                            <td
                                class="py-1.5 pr-2 text-right whitespace-nowrap text-[#525252] tabular-nums"
                            >
                                {{ Number(linea.cantidad) }} ×
                                {{ fmtUsd(linea.precio_unitario) }}
                            </td>
                            <td class="py-1.5 text-right tabular-nums">
                                {{ fmtUsd(linea.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-1.5 text-right text-xs text-[#525252]">
                    Total
                    <span class="tabular-nums">{{ fmtUsd(venta.total) }}</span>
                    · Abonado
                    <span class="tabular-nums">{{
                        fmtUsd(pagadoDe(venta))
                    }}</span>
                </p>
            </section>
        </div>

        <table v-else-if="modo === 'deuda'" class="w-full border-collapse">
            <thead>
                <tr
                    class="border-b border-[#d4d4d4] text-left text-[11px] text-[#737373]"
                >
                    <th class="py-1.5 pr-2 font-medium">Venta</th>
                    <th class="py-1.5 pr-2 font-medium">Fecha</th>
                    <th class="py-1.5 pr-2 text-right font-medium">Total</th>
                    <th class="py-1.5 pr-2 text-right font-medium">Abonado</th>
                    <th class="py-1.5 text-right font-medium">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="venta in ventas"
                    :key="venta.id"
                    class="border-b border-[#f5f5f5] align-top"
                >
                    <td class="py-2 pr-2 font-medium tabular-nums">
                        {{ venta.numero_venta }}
                    </td>
                    <td class="py-2 pr-2 whitespace-nowrap text-[#525252]">
                        {{ fmtFechaCorta(venta.fecha) }}
                    </td>
                    <td class="py-2 pr-2 text-right tabular-nums">
                        {{ fmtUsd(venta.total) }}
                    </td>
                    <td
                        class="py-2 pr-2 text-right text-[#525252] tabular-nums"
                    >
                        {{ fmtUsd(venta.total_pagado) }}
                    </td>
                    <td class="py-2 text-right font-semibold tabular-nums">
                        {{ fmtUsd(venta.saldo_pendiente) }}
                        <span
                            v-if="bs(venta.saldo_pendiente)"
                            class="block text-[11px] font-normal text-[#737373]"
                            >{{ bs(venta.saldo_pendiente) }}</span
                        >
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- una venta: productos y pagos -->
        <template v-else-if="detalle">
            <table class="w-full border-collapse">
                <thead>
                    <tr
                        class="border-b border-[#d4d4d4] text-left text-[11px] text-[#737373]"
                    >
                        <th class="py-1.5 pr-2 font-medium">Producto</th>
                        <th class="py-1.5 pr-2 text-right font-medium">
                            Cantidad
                        </th>
                        <th class="py-1.5 pr-2 text-right font-medium">
                            Precio
                        </th>
                        <th class="py-1.5 text-right font-medium">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="linea in detalle.detalles"
                        :key="linea.id"
                        class="border-b border-[#f5f5f5]"
                    >
                        <td class="py-2 pr-2">{{ linea.producto.nombre }}</td>
                        <td class="py-2 pr-2 text-right tabular-nums">
                            {{ Number(linea.cantidad) }}
                        </td>
                        <td
                            class="py-2 pr-2 text-right text-[#525252] tabular-nums"
                        >
                            {{ fmtUsd(linea.precio_unitario) }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ fmtUsd(linea.subtotal) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-3 grid gap-1 text-right text-xs">
                <p v-if="Number(detalle.descuento) > 0" class="text-[#525252]">
                    Descuento
                    <span class="tabular-nums"
                        >−{{ fmtUsd(detalle.descuento) }}</span
                    >
                </p>
                <p class="text-sm font-semibold">
                    Total de la venta
                    <span class="tabular-nums">{{
                        fmtUsd(detalle.total)
                    }}</span>
                </p>
            </div>

            <div v-if="detalle.pagos.length" class="mt-4">
                <p class="mb-1 text-[11px] font-medium text-[#737373]">
                    Pagos recibidos
                </p>
                <ul class="divide-y divide-neutral-100">
                    <li
                        v-for="pago in detalle.pagos"
                        :key="pago.id"
                        class="flex items-baseline justify-between gap-3 py-1.5 text-xs"
                    >
                        <span>
                            {{ pago.metodo_pago.nombre }}
                            <span class="text-[#737373]"
                                >· {{ fmtFechaHora(pago.fecha_pago)
                                }}<template v-if="pago.referencia">
                                    · ref. {{ pago.referencia }}</template
                                ></span
                            >
                        </span>
                        <span class="font-medium tabular-nums">{{
                            fmtUsd(pago.monto)
                        }}</span>
                    </li>
                </ul>
                <p class="mt-1 text-right text-xs text-[#525252]">
                    Pagado
                    <span class="font-medium tabular-nums">{{
                        fmtUsd(pagado)
                    }}</span>
                </p>
            </div>
        </template>

        <!-- lo que se debe -->
        <section class="mt-5 rounded-lg bg-[#f5f5f5] px-4 py-3">
            <div
                v-if="totalAdeudado > 0.00005"
                class="flex items-end justify-between gap-4"
            >
                <p class="text-sm font-semibold">Saldo pendiente</p>
                <p class="text-right tabular-nums">
                    <span class="block text-2xl leading-tight font-bold">{{
                        fmtUsd(totalAdeudado)
                    }}</span>
                    <span
                        v-if="bs(totalAdeudado)"
                        class="block text-base font-semibold text-[#404040]"
                        >{{ bs(totalAdeudado) }}</span
                    >
                </p>
            </div>
            <p v-else class="text-sm font-semibold text-[#047857]">
                Sin saldo pendiente. ¡Gracias!
            </p>
        </section>

        <p v-if="tasaTexto" class="mt-3 text-[11px] text-[#737373]">
            Montos en bolívares calculados a la tasa del día: Bs.
            {{ tasaTexto }} por US$ 1.
        </p>
    </div>
</template>
