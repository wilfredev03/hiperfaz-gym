<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { toast } from 'vue-sonner';
import { show as ventaShow } from '@/actions/App/Http/Controllers/VentaController';
import EstadoCuenta from '@/components/clientes/EstadoCuenta.vue';
import type {
    ClienteBase,
    DetalleVentaCompleto,
    NegocioInfo,
    TasaVigente,
    VentaCabecera,
} from '@/lib/clientes';
import {
    compartirArchivo,
    descargarArchivo,
    generarImagen,
    nombreDeArchivo,
    puedeCompartirArchivo,
} from '@/lib/compartir';
import { fmtBs, fmtUsd } from '@/lib/ventas';

// No dibuja nada visible: al llamar `compartir()` arma el estado de cuenta
// fuera de pantalla, lo convierte en imagen y abre de inmediato el menú
// nativo del teléfono/tablet (WhatsApp, correo, Drive...). Donde el navegador
// no tiene ese menú (PC, o sin HTTPS) la imagen se descarga.
const props = defineProps<{ tasa: TasaVigente | null }>();

interface Pedido {
    cliente: Pick<ClienteBase, 'nombre' | 'documento'>;
    // las ventas por cobrar (modo "toda la deuda")
    ventas: VentaCabecera[];
    // null = toda la deuda; con id = una sola venta
    ventaId: number | null;
}

// con pocas líneas en total se muestran los productos de cada venta; si hay
// más, el estado de cuenta vuelve al resumen (una fila por venta) para que la
// imagen no quede larguísima en el celular de quien la recibe
const LIMITE_LINEAS = 12;

const page = usePage();

const negocio = computed<NegocioInfo>(() => {
    const sucursal = page.props.sucursalActiva;
    return {
        nombre: (page.props.name as string) ?? '',
        telefono: sucursal?.telefono ?? null,
        direccion: sucursal?.direccion ?? null,
        // la imagen compartida siempre tiene fondo blanco, sin importar el
        // tema de quien la genera, así que usa siempre el logo claro
        logoUrl: (page.props.logoUrlLight as string | null | undefined) ?? null,
    };
});

const tasaNumero = computed(() =>
    props.tasa ? Number(props.tasa.tasa) : null,
);

// lo que se está dibujando para fotografiarlo; null cuando no hay nada en curso
const actual = ref<
    | (Pedido & {
          detalle: DetalleVentaCompleto | null;
          detalles: DetalleVentaCompleto[];
      })
    | null
>(null);
const nodo = ref<HTMLElement | null>(null);
const ocupado = ref(false);

async function cargarDetalle(id: number): Promise<DetalleVentaCompleto> {
    const respuesta = await fetch(ventaShow.url(id), {
        headers: { Accept: 'application/json' },
    });
    if (!respuesta.ok) {
        throw new Error('No se pudo cargar la venta.');
    }
    return respuesta.json();
}

function esperarFotograma(): Promise<void> {
    return new Promise((siguiente) => requestAnimationFrame(() => siguiente()));
}

function mensajeDe(
    pedido: Pedido,
    detalle: DetalleVentaCompleto | null,
    adeudado: number,
): string {
    const asunto =
        pedido.ventaId === null
            ? 'tu estado de cuenta'
            : `el detalle de la venta ${detalle?.numero_venta ?? ''}`.trim();
    const base = `Hola ${pedido.cliente.nombre}, te comparto ${asunto} con ${negocio.value.nombre}.`;

    if (adeudado <= 0.00005) return base;

    const monto = `${fmtUsd(adeudado)}${tasaNumero.value ? ` (${fmtBs(adeudado * tasaNumero.value)})` : ''}`;
    return `${base} Saldo pendiente: ${monto}.`;
}

async function compartir(pedido: Pedido): Promise<void> {
    if (ocupado.value) return;
    ocupado.value = true;
    const aviso = toast.loading('Preparando imagen...');

    try {
        let detalle: DetalleVentaCompleto | null = null;
        let detalles: DetalleVentaCompleto[] = [];

        if (pedido.ventaId !== null) {
            detalle = await cargarDetalle(pedido.ventaId);
        } else if (pedido.ventas.length > 0) {
            try {
                const todos = await Promise.all(
                    pedido.ventas.map((venta) => cargarDetalle(venta.id)),
                );
                const lineas = todos.reduce(
                    (suma, venta) => suma + venta.detalles.length,
                    0,
                );
                detalles = lineas <= LIMITE_LINEAS ? todos : [];
            } catch {
                // sin el detalle igual se puede compartir el resumen
                detalles = [];
            }
        }

        actual.value = { ...pedido, detalle, detalles };

        // esperar a que el bloque exista, se pinte y su logo termine de cargar
        await nextTick();
        for (let intento = 0; intento < 20 && !nodo.value; intento++) {
            await esperarFotograma();
        }
        await esperarFotograma();
        const bloque = nodo.value;
        if (!bloque) throw new Error('No se pudo preparar la imagen.');
        await Promise.all(
            Array.from(bloque.querySelectorAll('img')).map((imagen) =>
                imagen.decode().catch(() => undefined),
            ),
        );

        const imagen = await generarImagen(bloque);
        const nombre =
            pedido.ventaId === null
                ? `estado-de-cuenta-${nombreDeArchivo(pedido.cliente.nombre)}.png`
                : `venta-${nombreDeArchivo(detalle?.numero_venta ?? 'detalle')}.png`;
        const archivo = new File([imagen], nombre, { type: 'image/png' });

        const adeudado =
            pedido.ventaId === null
                ? pedido.ventas.reduce(
                      (suma, venta) => suma + venta.saldo_pendiente,
                      0,
                  )
                : (detalle?.saldo_pendiente ?? 0);

        toast.dismiss(aviso);

        if (puedeCompartirArchivo(archivo)) {
            await compartirArchivo(
                archivo,
                pedido.ventaId === null
                    ? 'Estado de cuenta'
                    : `Venta ${detalle?.numero_venta ?? ''}`.trim(),
                mensajeDe(pedido, detalle, adeudado),
            );
        } else {
            descargarArchivo(archivo);
            toast.info(
                'Este navegador no tiene menú de compartir: la imagen se descargó para que la envíes.',
            );
        }
    } catch (error) {
        toast.dismiss(aviso);
        toast.error(
            error instanceof Error
                ? error.message
                : 'No se pudo compartir. Inténtalo de nuevo.',
        );
    } finally {
        actual.value = null;
        ocupado.value = false;
    }
}

defineExpose({ compartir, ocupado });
</script>

<template>
    <!-- bloque que se fotografía: fuera de pantalla pero con su tamaño real -->
    <Teleport to="body">
        <div
            v-if="actual"
            ref="nodo"
            class="pointer-events-none fixed top-0 -left-[10000px]"
            aria-hidden="true"
        >
            <EstadoCuenta
                :negocio="negocio"
                :cliente="actual.cliente"
                :modo="actual.ventaId === null ? 'deuda' : 'venta'"
                :ventas="actual.ventas"
                :detalle="actual.detalle"
                :detalles="actual.detalles"
                :tasa="tasaNumero"
            />
        </div>
    </Teleport>
</template>
