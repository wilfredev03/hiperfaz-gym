<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useHttp } from '@inertiajs/vue3';
import { ImageIcon, Minus, Plus, Search, Trash2, X } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { store as guardarOrdenCompra } from '@/actions/App/Http/Controllers/OrdenCompraController';
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fmtBs, fmtUsd } from '@/lib/ventas';
import { create as crearOrdenCompra } from '@/routes/ordenes_compra';

interface UnidadMedida {
    id: number;
    nombre: string;
    abreviatura: string;
}

interface ProductoOption {
    id: number;
    nombre: string;
    ultimo_costo: string | null;
    unidad_medida: UnidadMedida;
}

interface ProveedorOption {
    id: number;
    nombre: string;
}

interface TasaCambioActual {
    tasa: string;
    fecha: string;
}

interface OrdenCompraCreada {
    numero_orden: string;
    total: string;
}

const props = defineProps<{
    productos: ProductoOption[];
    proveedores: ProveedorOption[];
    tasaCambio: TasaCambioActual | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Nueva compra', href: crearOrdenCompra() }],
    },
});

const tasaActual = computed(() =>
    props.tasaCambio ? Number(props.tasaCambio.tasa) : null,
);

function fmtDual(valorUsd: number): string {
    if (!tasaActual.value) {
        return fmtUsd(valorUsd);
    }
    return `${fmtBs(valorUsd * tasaActual.value)} (${fmtUsd(valorUsd)})`;
}

// Las unidades de peso/volumen aceptan cantidades decimales (0.850 kg); el
// resto se compra por pieza entera.
const UNIDADES_FRACCIONABLES = new Set(['kg', 'g', 'l', 'ml']);

function esFraccionable(abreviatura: string): boolean {
    return UNIDADES_FRACCIONABLES.has(abreviatura.trim().toLowerCase());
}

// --- Buscador de productos -------------------------------------------------

const busqueda = ref('');

const resultadosBusqueda = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();
    if (!termino) {
        return props.productos;
    }
    return props.productos.filter((producto) =>
        producto.nombre.toLowerCase().includes(termino),
    );
});

// --- Carrito de compra -------------------------------------------------

interface LineaCarrito {
    producto_id: number;
    nombre: string;
    cantidad: string;
    costoUnitario: string;
    // costo (en USD) de la última compra de este producto, solo de referencia
    ultimoCostoUsd: number | null;
    unidadAbreviatura: string;
    fraccionable: boolean;
}

const carrito = ref<LineaCarrito[]>([]);

function agregarProducto(producto: ProductoOption) {
    const existente = carrito.value.find((l) => l.producto_id === producto.id);
    if (existente) {
        const actual = Number(existente.cantidad) || 0;
        const nueva = existente.fraccionable
            ? actual + 1
            : Math.round(actual) + 1;
        existente.cantidad = String(nueva);
        return;
    }
    carrito.value.push({
        producto_id: producto.id,
        nombre: producto.nombre,
        cantidad: '1',
        // el costo lo fija el proveedor en cada compra: se digita siempre
        costoUnitario: '',
        ultimoCostoUsd:
            producto.ultimo_costo === null
                ? null
                : Number(producto.ultimo_costo),
        unidadAbreviatura: producto.unidad_medida.abreviatura,
        fraccionable: esFraccionable(producto.unidad_medida.abreviatura),
    });
}

function cantidadEnCarrito(productoId: number): number {
    const linea = carrito.value.find((l) => l.producto_id === productoId);
    return linea ? Number(linea.cantidad) || 0 : 0;
}

function quitarLinea(index: number) {
    carrito.value.splice(index, 1);
}

function normalizarCantidad(linea: LineaCarrito) {
    const valor = Number(linea.cantidad);
    if (!Number.isFinite(valor) || valor <= 0) {
        linea.cantidad = linea.fraccionable ? '0.001' : '1';
        return;
    }
    linea.cantidad = linea.fraccionable
        ? String(Math.round(valor * 1000) / 1000)
        : String(Math.round(valor));
}

function pasoCantidad(linea: LineaCarrito): number {
    return linea.fraccionable ? 0.1 : 1;
}

function incrementarCantidad(linea: LineaCarrito) {
    const nueva = (Number(linea.cantidad) || 0) + pasoCantidad(linea);
    linea.cantidad = linea.fraccionable
        ? String(Math.round(nueva * 1000) / 1000)
        : String(Math.round(nueva));
}

function decrementarCantidad(linea: LineaCarrito, index: number) {
    const nueva = (Number(linea.cantidad) || 0) - pasoCantidad(linea);
    const minimo = linea.fraccionable ? 0.001 : 1;
    if (nueva < minimo) {
        quitarLinea(index);
        return;
    }
    linea.cantidad = linea.fraccionable
        ? String(Math.round(nueva * 1000) / 1000)
        : String(Math.round(nueva));
}

// Moneda en la que se digitan los costos. USD es lo normal; Bs es para el
// proveedor que cobra en bolívares: el servidor convierte con la tasa del día
// y siempre guarda en USD.
const opcionesMoneda = ['USD', 'VES'] as const;
const moneda = ref<'USD' | 'VES'>('USD');

function cambiarMoneda(nueva: 'USD' | 'VES') {
    if (nueva === moneda.value) {
        return;
    }
    if (nueva === 'VES' && !tasaActual.value) {
        toast.error('Carga la tasa de cambio para registrar en bolívares.');
        return;
    }
    // los costos ya digitados se convierten para no tener que volver a escribirlos
    for (const linea of carrito.value) {
        if (linea.costoUnitario === '') {
            continue;
        }
        const valor = Number(linea.costoUnitario) || 0;
        linea.costoUnitario = String(
            Math.round(
                (nueva === 'VES'
                    ? valor * tasaActual.value!
                    : valor / tasaActual.value!) * 10000,
            ) / 10000,
        );
    }
    moneda.value = nueva;
}

function fmtMoneda(valor: number): string {
    return moneda.value === 'VES' ? fmtBs(valor) : fmtUsd(valor);
}

// referencia de la última compra, en la moneda en que se está digitando
function ultimoCosto(linea: LineaCarrito): number | null {
    if (linea.ultimoCostoUsd === null) {
        return null;
    }
    return moneda.value === 'VES' && tasaActual.value
        ? linea.ultimoCostoUsd * tasaActual.value
        : linea.ultimoCostoUsd;
}

// variación contra la última compra: el proveedor sube y baja sus precios
function variacion(linea: LineaCarrito): number | null {
    const ultimo = ultimoCosto(linea);
    const actual = Number(linea.costoUnitario);
    if (!ultimo || !actual) {
        return null;
    }
    return ((actual - ultimo) / ultimo) * 100;
}

// no se registra una compra con un costo sin digitar
const faltanCostos = computed(() =>
    carrito.value.some((linea) => !(Number(linea.costoUnitario) > 0)),
);

function subtotalLinea(linea: LineaCarrito): number {
    return (Number(linea.cantidad) || 0) * (Number(linea.costoUnitario) || 0);
}

// suma en la moneda elegida; el equivalente en USD es lo que queda guardado
const total = computed(() =>
    carrito.value.reduce((acc, linea) => acc + subtotalLinea(linea), 0),
);

const totalUsd = computed(() =>
    moneda.value === 'VES' && tasaActual.value
        ? total.value / tasaActual.value
        : total.value,
);

// --- Envío --------------------------------------------------------------

const http = useHttp<
    {
        proveedor_id: string;
        fecha: string;
        notas: string;
        moneda: 'USD' | 'VES';
        items: {
            producto_id: number;
            cantidad: number;
            costo_unitario: number;
        }[];
    },
    OrdenCompraCreada
>({
    proveedor_id: '',
    fecha: new Date().toISOString().slice(0, 10),
    notas: '',
    moneda: 'USD',
    items: [],
});

const erroresGenerales = computed(() => Object.values(http.errors));

function registrarCompra() {
    if (!carrito.value.length || http.processing) {
        toast.error('Agrega al menos un producto a la compra.');
        return;
    }
    if (faltanCostos.value) {
        toast.error('Escribe el costo de cada producto.');
        return;
    }

    http.moneda = moneda.value;
    http.items = carrito.value.map((linea) => ({
        producto_id: linea.producto_id,
        cantidad: Number(linea.cantidad) || 0,
        costo_unitario: Number(linea.costoUnitario) || 0,
    }));

    http.post(guardarOrdenCompra.url(), {
        onSuccess: (data) => {
            toast.success(`Compra ${data.numero_orden} registrada.`);
            carrito.value = [];
        },
        onError: () => {
            toast.error('Revisa los datos de la compra.');
        },
    });
}
</script>

<template>
    <Head title="Nueva compra" />

    <div class="flex h-[calc(100dvh-4rem)] flex-col gap-3 p-4">
        <AlertError v-if="erroresGenerales.length" :errors="erroresGenerales" />

        <div
            class="grid min-h-0 flex-1 grid-cols-2 gap-3 md:grid-cols-[1.25fr_1fr] md:gap-6"
        >
            <!-- panel de productos -->
            <Card class="flex min-h-0 flex-col py-3 md:py-4">
                <CardContent
                    class="flex min-h-0 flex-1 flex-col gap-3 px-3 md:px-6"
                >
                    <div class="relative flex-shrink-0">
                        <Label for="buscador-productos" class="sr-only"
                            >Buscar producto</Label
                        >
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        />
                        <Input
                            id="buscador-productos"
                            v-model="busqueda"
                            class="h-11 pr-9 pl-9 text-base"
                            placeholder="Buscar producto por nombre..."
                            autocomplete="off"
                        />
                        <button
                            v-if="busqueda"
                            type="button"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                            @click="busqueda = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div
                        class="grid min-h-0 flex-1 grid-cols-2 content-start gap-2 overflow-y-auto pr-1 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <button
                            v-for="producto in resultadosBusqueda"
                            :key="producto.id"
                            type="button"
                            class="hover:border-primary/50 hover:bg-accent relative flex h-fit flex-col items-start gap-2 rounded-lg border p-2 text-left transition-colors active:scale-[0.98]"
                            :class="
                                cantidadEnCarrito(producto.id) > 0
                                    ? 'border-primary/40 bg-primary/5'
                                    : ''
                            "
                            @click="agregarProducto(producto)"
                        >
                            <span
                                class="bg-primary text-primary-foreground absolute top-1.5 right-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                            >
                                {{ producto.unidad_medida.abreviatura }}
                            </span>

                            <div
                                class="bg-muted relative flex size-12 shrink-0 items-center justify-center rounded-md"
                            >
                                <ImageIcon
                                    class="text-muted-foreground/50 size-5"
                                />
                                <span
                                    v-if="cantidadEnCarrito(producto.id) > 0"
                                    class="bg-primary text-primary-foreground absolute -top-1.5 -left-1.5 flex size-5 items-center justify-center rounded-full text-[10px] font-semibold"
                                >
                                    {{ cantidadEnCarrito(producto.id) }}
                                </span>
                            </div>

                            <span
                                class="line-clamp-2 text-xs leading-tight font-medium"
                            >
                                {{ producto.nombre }}
                            </span>
                        </button>

                        <p
                            v-if="!resultadosBusqueda.length"
                            class="text-muted-foreground col-span-full py-6 text-center text-sm"
                        >
                            No se encontraron productos.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- panel de la compra en curso -->
            <Card class="flex min-h-0 flex-col py-3 md:py-4">
                <CardContent
                    class="flex min-h-0 flex-1 flex-col gap-3 px-3 md:px-6"
                >
                    <div class="grid flex-shrink-0 grid-cols-2 gap-2">
                        <div class="grid gap-1">
                            <Label for="proveedor_id" class="text-xs"
                                >Proveedor (opcional)</Label
                            >
                            <Select v-model="http.proveedor_id">
                                <SelectTrigger
                                    id="proveedor_id"
                                    class="h-9 w-full"
                                >
                                    <SelectValue placeholder="Sin proveedor" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="proveedor in proveedores"
                                        :key="proveedor.id"
                                        :value="String(proveedor.id)"
                                    >
                                        {{ proveedor.nombre }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1">
                            <Label for="fecha" class="text-xs">Fecha</Label>
                            <Input
                                id="fecha"
                                v-model="http.fecha"
                                type="date"
                                class="h-9"
                            />
                        </div>
                    </div>

                    <!-- el costo se digita en USD; Bs solo para el proveedor que
                    cobra en bolívares (se convierte con la tasa del día) -->
                    <div class="flex flex-shrink-0 items-center gap-2">
                        <span class="text-muted-foreground text-xs"
                            >Costos en</span
                        >
                        <div
                            class="border-primary/25 bg-primary/5 flex h-6.5 overflow-hidden rounded-full border text-[11px] font-semibold"
                            role="group"
                            aria-label="Moneda de los costos"
                        >
                            <button
                                v-for="opcion in opcionesMoneda"
                                :key="opcion"
                                type="button"
                                class="px-3 transition-colors disabled:opacity-40"
                                :class="
                                    moneda === opcion
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-primary/70 hover:bg-primary/15'
                                "
                                :disabled="opcion === 'VES' && !tasaActual"
                                :aria-pressed="moneda === opcion"
                                @click="cambiarMoneda(opcion)"
                            >
                                {{ opcion === 'VES' ? 'Bs' : 'USD' }}
                            </button>
                        </div>
                        <span
                            v-if="!tasaActual"
                            class="text-muted-foreground text-xs"
                            >Sin tasa de cambio cargada</span
                        >
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <p
                            v-if="!carrito.length"
                            class="text-muted-foreground flex h-full items-center justify-center text-sm"
                        >
                            Sin productos todavía
                        </p>

                        <div v-else class="flex flex-col">
                            <div
                                v-for="(linea, index) in carrito"
                                :key="linea.producto_id"
                                class="flex flex-col gap-1.5 border-b py-2.5 last:border-b-0"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="flex-1 text-sm font-medium">
                                        {{ linea.nombre }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="quitarLinea(index)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="text-destructive flex size-8 shrink-0 items-center justify-center rounded-md border"
                                        @click="
                                            decrementarCantidad(linea, index)
                                        "
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <input
                                        v-model="linea.cantidad"
                                        type="number"
                                        :step="
                                            linea.fraccionable ? '0.001' : '1'
                                        "
                                        :min="
                                            linea.fraccionable ? '0.001' : '1'
                                        "
                                        class="w-14 shrink-0 rounded border text-center text-sm tabular-nums"
                                        @change="normalizarCantidad(linea)"
                                    />
                                    <button
                                        type="button"
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md border text-emerald-600"
                                        @click="incrementarCantidad(linea)"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                    <span
                                        class="text-muted-foreground shrink-0 text-xs"
                                        >{{ linea.unidadAbreviatura }} ×</span
                                    >
                                    <div class="relative flex-1">
                                        <span
                                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-xs"
                                            >{{
                                                moneda === 'VES' ? 'Bs' : '$'
                                            }}</span
                                        >
                                        <input
                                            v-model="linea.costoUnitario"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="Costo"
                                            :aria-label="`Costo de ${linea.nombre}`"
                                            class="w-full rounded border py-1 text-right text-sm tabular-nums"
                                            :class="
                                                moneda === 'VES'
                                                    ? 'pl-7'
                                                    : 'pl-5'
                                            "
                                        />
                                    </div>
                                    <span
                                        class="w-16 shrink-0 text-right text-sm font-semibold"
                                    >
                                        {{
                                            Number(linea.costoUnitario) > 0
                                                ? fmtMoneda(
                                                      subtotalLinea(linea),
                                                  )
                                                : '—'
                                        }}
                                    </span>
                                </div>
                                <!-- referencia: lo que costó la última vez, para
                                notar si el proveedor subió o bajó el precio -->
                                <p
                                    v-if="ultimoCosto(linea) !== null"
                                    class="text-muted-foreground flex items-center gap-1.5 text-xs"
                                >
                                    Última compra:
                                    {{ fmtMoneda(ultimoCosto(linea)!) }}
                                    <span
                                        v-if="variacion(linea) !== null"
                                        class="font-medium"
                                        :class="
                                            variacion(linea)! > 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : variacion(linea)! < 0
                                                  ? 'text-emerald-600 dark:text-emerald-400'
                                                  : ''
                                        "
                                    >
                                        {{
                                            variacion(linea)! > 0
                                                ? '▲'
                                                : variacion(linea)! < 0
                                                  ? '▼'
                                                  : '='
                                        }}
                                        {{
                                            Math.abs(variacion(linea)!).toFixed(
                                                1,
                                            )
                                        }}%
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-shrink-0 flex-col gap-2.5 border-t pt-3"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">
                                Total
                            </span>
                            <span class="text-xl font-bold">
                                {{ fmtDual(totalUsd) }}
                            </span>
                        </div>
                        <Button
                            type="button"
                            size="lg"
                            class="h-11 w-full"
                            :disabled="
                                !carrito.length ||
                                faltanCostos ||
                                http.processing
                            "
                            @click="registrarCompra"
                        >
                            {{
                                http.processing
                                    ? 'Registrando...'
                                    : 'Registrar compra'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
