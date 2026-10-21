<script setup lang="ts" generic="P extends ProductoSelectorItem">
import { ImageIcon, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export interface ProductoSelectorCategoria {
    id: number;
    nombre: string;
}

export interface ProductoSelectorItem {
    id: number;
    nombre: string;
    precio_venta: string;
    imagen_url: string | null;
    categoria: ProductoSelectorCategoria | null;
}

// Buscador + chips de categoría + cuadrícula táctil de productos con foto: es
// la misma pieza en la venta de mostrador y en la venta a mayoristas. No sabe
// nada de carritos ni de rubros: lo que cada pantalla quiere ajustar (cómo se
// llama un producto, cómo se ordena, qué cantidad lleva ya) llega por props.
const props = withDefaults(
    defineProps<{
        productos: P[];
        // texto del badge "ya en el pedido" sobre la tarjeta; '' = no está
        cantidadLabel?: (productoId: number) => string;
        nombreDe?: (producto: P) => string;
        etiquetaCategoria?: (categoria: ProductoSelectorCategoria) => string;
        compararCategorias?: (
            a: ProductoSelectorCategoria,
            b: ProductoSelectorCategoria,
        ) => number;
        compararProductos?: (a: P, b: P) => number;
        alturaImagen?: (producto: P) => string;
        mostrarPrecio?: boolean;
        idBuscador?: string;
    }>(),
    {
        cantidadLabel: () => '',
        nombreDe: (producto: P) => producto.nombre,
        etiquetaCategoria: (categoria: ProductoSelectorCategoria) =>
            categoria.nombre,
        compararCategorias: () => 0,
        compararProductos: () => 0,
        alturaImagen: () => 'h-14',
        mostrarPrecio: false,
        idBuscador: 'buscador-productos',
    },
);

const emit = defineEmits<{ (e: 'agregar', producto: P): void }>();

defineSlots<{
    // etiqueta que una pantalla quiera pegar arriba a la izquierda de la tarjeta
    etiqueta?(props: { producto: P }): unknown;
}>();

// Sin texto de búsqueda se muestra todo el catálogo: en tablet es más rápido
// tocar un producto visible que escribir su nombre completo.
const busqueda = ref('');

// `null` = sin filtro de categoría ("Todos"). Las categorías se calculan a
// partir de los productos recibidos, en el orden que pida cada pantalla.
const categoriaSeleccionada = ref<number | null>(null);

const categoriasDisponibles = computed(() => {
    const vistas = new Map<number, ProductoSelectorCategoria>();
    for (const producto of props.productos) {
        if (producto.categoria && !vistas.has(producto.categoria.id)) {
            vistas.set(producto.categoria.id, producto.categoria);
        }
    }
    return Array.from(vistas.values()).sort(props.compararCategorias);
});

function alternarCategoria(categoriaId: number) {
    categoriaSeleccionada.value =
        categoriaSeleccionada.value === categoriaId ? null : categoriaId;
}

const resultadosBusqueda = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();
    return props.productos
        .filter((producto) => {
            const coincideTexto =
                !termino || producto.nombre.toLowerCase().includes(termino);
            const coincideCategoria =
                categoriaSeleccionada.value === null ||
                producto.categoria?.id === categoriaSeleccionada.value;
            return coincideTexto && coincideCategoria;
        })
        .sort(props.compararProductos);
});

function fmtPrecio(valor: string): string {
    return `$${Number(valor).toFixed(2)}`;
}
</script>

<template>
    <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-3">
        <div class="relative flex-shrink-0">
            <Label :for="idBuscador" class="sr-only">Buscar producto</Label>
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            />
            <Input
                :id="idBuscador"
                v-model="busqueda"
                class="h-11 pr-9 pl-9 text-base"
                placeholder="Buscar producto por nombre..."
                autocomplete="off"
            />
            <button
                v-if="busqueda"
                type="button"
                class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                aria-label="Borrar búsqueda"
                @click="busqueda = ''"
            >
                <X class="size-4" />
            </button>
        </div>

        <!-- filtro de categorías: chips angostos, en una sola fila que se
        desliza horizontal — pensado para tocar con el dedo en tablet, no
        para envolver en varias líneas -->
        <div
            class="flex min-w-0 flex-shrink-0 snap-x gap-1.5 overflow-x-auto pb-1"
        >
            <button
                type="button"
                class="flex-none snap-start rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors"
                :class="
                    categoriaSeleccionada === null
                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:border-primary/50 hover:bg-accent'
                "
                @click="categoriaSeleccionada = null"
            >
                Todos
            </button>
            <button
                v-for="categoria in categoriasDisponibles"
                :key="categoria.id"
                type="button"
                class="flex-none snap-start rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors"
                :class="
                    categoriaSeleccionada === categoria.id
                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:border-primary/50 hover:bg-accent'
                "
                @click="alternarCategoria(categoria.id)"
            >
                {{ etiquetaCategoria(categoria) }}
            </button>
        </div>

        <div
            class="grid min-h-0 flex-1 grid-cols-2 content-start gap-2 overflow-y-auto pr-1 sm:grid-cols-3 lg:grid-cols-4"
        >
            <button
                v-for="producto in resultadosBusqueda"
                :key="producto.id"
                type="button"
                class="hover:border-primary/50 hover:bg-accent relative flex h-fit flex-col items-center gap-2 rounded-lg border p-2 text-center transition-colors active:scale-[0.98]"
                :class="
                    cantidadLabel(producto.id)
                        ? 'border-primary/40 bg-primary/5 shadow-sm'
                        : ''
                "
                @click="emit('agregar', producto)"
            >
                <slot name="etiqueta" :producto="producto" />

                <span
                    v-if="cantidadLabel(producto.id)"
                    class="bg-primary text-primary-foreground absolute -top-px -right-px z-10 flex min-w-5 items-center justify-center rounded-tr-lg rounded-bl-md px-1 py-0.5 text-[10px] font-semibold"
                >
                    {{ cantidadLabel(producto.id) }}
                </span>

                <div
                    class="flex w-full shrink-0 items-end justify-center"
                    :class="alturaImagen(producto)"
                >
                    <img
                        v-if="producto.imagen_url"
                        :src="producto.imagen_url"
                        :alt="nombreDe(producto)"
                        class="max-h-full w-auto max-w-full object-contain"
                        loading="lazy"
                    />
                    <ImageIcon v-else class="text-muted-foreground/50 size-8" />
                </div>

                <span
                    class="line-clamp-2 min-h-8 text-center text-xs leading-tight font-medium"
                >
                    {{ nombreDe(producto) }}
                </span>
                <span
                    v-if="mostrarPrecio"
                    class="text-muted-foreground -mt-1 text-xs tabular-nums"
                >
                    {{ fmtPrecio(producto.precio_venta) }}
                </span>
            </button>

            <p
                v-if="!resultadosBusqueda.length"
                class="text-muted-foreground col-span-full py-6 text-center text-sm"
            >
                No se encontraron productos.
            </p>
        </div>
    </div>
</template>
