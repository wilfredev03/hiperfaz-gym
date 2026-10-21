<script setup lang="ts">
import ProductoFormFields from '@/components/productos/ProductoFormFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    store as guardarProducto,
    update as actualizarProducto,
} from '@/actions/App/Http/Controllers/ProductoController';
import { index as productosIndex } from '@/routes/productos';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Image as ImageIcon,
    PackageSearch,
    Pencil,
    Plus,
    Search,
    X,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';

interface Categoria {
    id: number;
    nombre: string;
}

interface UnidadMedida {
    id: number;
    nombre: string;
    abreviatura: string;
}

interface ProductoRow {
    id: number;
    nombre: string;
    descripcion: string | null;
    categoria_id: number;
    unidad_medida_id: number;
    precio_venta: string;
    stock_actual: string;
    imagen_url: string | null;
    controla_inventario: boolean;
    activo: boolean;
    categoria: Categoria | null;
    unidad_medida: UnidadMedida;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type Estado = 'activos' | 'inactivos' | 'todos';

interface TasaCambioActual {
    tasa: string;
    fecha: string;
}

const props = defineProps<{
    productos: {
        data: ProductoRow[];
        links: PaginationLink[];
    };
    categorias: Categoria[];
    unidadesMedida: UnidadMedida[];
    tasaCambio: TasaCambioActual | null;
    filtros: {
        buscar: string;
        categoria_id: number | null;
        estado: Estado;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogo de productos', href: productosIndex() },
        ],
    },
});

function fmtUsd(valor: string | number): string {
    return `$${Number(valor).toFixed(2)}`;
}

const tasaActual = computed(() =>
    props.tasaCambio ? Number(props.tasaCambio.tasa) : null,
);

function fmtBs(valorUsd: string | number): string | null {
    if (!tasaActual.value) {
        return null;
    }
    return `Bs. ${(Number(valorUsd) * tasaActual.value).toFixed(2)}`;
}

function fmtStock(producto: ProductoRow): string {
    const cantidad = Number(producto.stock_actual);
    return `${Number.isInteger(cantidad) ? cantidad : cantidad.toFixed(3)} ${producto.unidad_medida.abreviatura}`;
}

// --- Filtros ------------------------------------------------------------

const ESTADOS: { value: Estado; label: string }[] = [
    { value: 'activos', label: 'Activos' },
    { value: 'inactivos', label: 'Inactivos' },
    { value: 'todos', label: 'Todos' },
];

const filtros = reactive({
    buscar: props.filtros.buscar,
    categoriaId: props.filtros.categoria_id
        ? String(props.filtros.categoria_id)
        : 'todas',
    estado: props.filtros.estado,
});

function aplicarFiltros() {
    router.get(
        productosIndex.url(),
        {
            buscar: filtros.buscar || undefined,
            categoria_id:
                filtros.categoriaId !== 'todas'
                    ? filtros.categoriaId
                    : undefined,
            estado: filtros.estado,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let debounceHandle: ReturnType<typeof setTimeout> | undefined;
watch(
    () => filtros.buscar,
    () => {
        clearTimeout(debounceHandle);
        debounceHandle = setTimeout(aplicarFiltros, 300);
    },
);
watch([() => filtros.categoriaId, () => filtros.estado], aplicarFiltros);

const hayFiltrosActivos = computed(
    () =>
        Boolean(filtros.buscar) ||
        filtros.categoriaId !== 'todas' ||
        filtros.estado !== 'activos',
);

// alfabético, igual que los chips de ventas/Create.vue: el código no conoce
// los nombres de las categorías de un negocio (principio #4)
const categoriasOrdenadas = computed(() =>
    [...props.categorias].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')),
);

// --- Alta de producto (modal) --------------------------------------------

const dialogNuevoAbierto = ref(false);

const formNuevo = useForm({
    nombre: '',
    descripcion: '',
    categoria_id: '',
    unidad_medida_id: '',
    precio_venta: '',
    imagen: null as File | null,
    controla_inventario: true,
    activo: true,
});

function abrirDialogNuevo() {
    formNuevo.reset();
    formNuevo.clearErrors();
    dialogNuevoAbierto.value = true;
}

function guardarNuevo() {
    formNuevo.post(guardarProducto.url(), {
        onSuccess: () => {
            dialogNuevoAbierto.value = false;
        },
    });
}

// --- Edición de producto (modal) ------------------------------------------

const dialogEditarAbierto = ref(false);
const productoEditandoId = ref<number | null>(null);
const imagenUrlEditando = ref<string | null>(null);

const formEditar = useForm({
    nombre: '',
    descripcion: '',
    categoria_id: '',
    unidad_medida_id: '',
    precio_venta: '',
    imagen: null as File | null,
    controla_inventario: true,
    activo: true,
});

function abrirDialogEditar(producto: ProductoRow) {
    productoEditandoId.value = producto.id;
    imagenUrlEditando.value = producto.imagen_url;
    formEditar.reset();
    formEditar.clearErrors();
    formEditar.nombre = producto.nombre;
    formEditar.descripcion = producto.descripcion ?? '';
    formEditar.categoria_id = String(producto.categoria_id);
    formEditar.unidad_medida_id = String(producto.unidad_medida_id);
    formEditar.precio_venta = String(producto.precio_venta);
    formEditar.controla_inventario = producto.controla_inventario;
    formEditar.activo = producto.activo;
    dialogEditarAbierto.value = true;
}

function guardarEditar() {
    if (productoEditandoId.value === null) {
        return;
    }
    // PHP no parsea multipart/form-data en peticiones PUT: se manda como
    // POST con _method spoofeado para que la imagen (si se adjunta) llegue
    // al servidor. Ver app/Http/Controllers/ProductoController::update.
    formEditar
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(actualizarProducto.url(productoEditandoId.value), {
            onSuccess: () => {
                dialogEditarAbierto.value = false;
            },
        });
}
</script>

<template>
    <Head title="Catálogo de productos" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Card>
            <CardContent class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="relative min-w-48 flex-1 sm:max-w-xs">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        />
                        <Input
                            v-model="filtros.buscar"
                            placeholder="Buscar por nombre..."
                            class="pr-9 pl-9"
                            autocomplete="off"
                        />
                        <button
                            v-if="filtros.buscar"
                            type="button"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                            @click="filtros.buscar = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Select v-model="filtros.estado">
                            <SelectTrigger class="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="estado in ESTADOS"
                                    :key="estado.value"
                                    :value="estado.value"
                                >
                                    {{ estado.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button @click="abrirDialogNuevo">
                            <Plus class="size-4" />
                            Nuevo producto
                        </Button>
                    </div>
                </div>

                <!-- filtro de categorías: chips en una sola fila que se
                desliza horizontal, igual que en la pantalla de ventas -->
                <div class="flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        type="button"
                        class="flex-none rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors"
                        :class="
                            filtros.categoriaId === 'todas'
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:border-primary/50 hover:bg-accent'
                        "
                        @click="filtros.categoriaId = 'todas'"
                    >
                        Todas
                    </button>
                    <button
                        v-for="categoria in categoriasOrdenadas"
                        :key="categoria.id"
                        type="button"
                        class="flex-none rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors"
                        :class="
                            filtros.categoriaId === String(categoria.id)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:border-primary/50 hover:bg-accent'
                        "
                        @click="filtros.categoriaId = String(categoria.id)"
                    >
                        {{ categoria.nombre }}
                    </button>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="productos.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <PackageSearch class="size-8" />
            <p class="text-sm font-medium">
                {{
                    hayFiltrosActivos
                        ? 'No hay productos que coincidan con la búsqueda.'
                        : 'Todavía no hay productos en el catálogo.'
                }}
            </p>
            <Button
                v-if="!hayFiltrosActivos"
                size="sm"
                @click="abrirDialogNuevo"
                >Agregar el primer producto</Button
            >
        </div>

        <div
            v-else
            class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6"
        >
            <Card
                v-for="producto in productos.data"
                :key="producto.id"
                class="relative gap-0 p-2"
                :class="{ 'opacity-60': !producto.activo }"
            >
                <!-- misma idea que la tarjeta de producto en ventas: imagen
                contenida (no a sangre) y el ícono como respaldo mientras no
                haya foto real que mostrar -->
                <!-- esquina superior derecha "a sangre" contra el borde de la
                card, igual que el contador de cantidad en ventas/Create.vue -->
                <Button
                    size="icon"
                    class="absolute -top-px -right-px z-10 size-7 rounded-none rounded-tr-xl rounded-bl-md"
                    @click="abrirDialogEditar(producto)"
                >
                    <Pencil class="size-3.5" />
                </Button>

                <div class="flex h-14 shrink-0 items-end justify-center">
                    <img
                        v-if="producto.imagen_url"
                        :src="producto.imagen_url"
                        :alt="producto.nombre"
                        class="max-h-full w-auto max-w-full object-contain"
                        loading="lazy"
                    />
                    <ImageIcon v-else class="text-muted-foreground/50 size-7" />
                </div>

                <CardContent class="flex flex-col gap-0.5 p-0 pt-2">
                    <p class="truncate text-xs font-medium">
                        {{ producto.nombre }}
                    </p>
                    <p class="text-muted-foreground truncate text-[10px]">
                        {{ producto.unidad_medida.nombre }}
                    </p>

                    <div class="mt-1 flex items-center justify-between gap-1">
                        <p class="flex flex-col leading-tight">
                            <span class="text-xs font-semibold">
                                {{ fmtUsd(producto.precio_venta) }}
                            </span>
                            <span
                                v-if="fmtBs(producto.precio_venta)"
                                class="text-muted-foreground text-[9px]"
                            >
                                {{ fmtBs(producto.precio_venta) }}
                            </span>
                        </p>
                        <p
                            v-if="producto.controla_inventario"
                            class="text-muted-foreground truncate text-[10px]"
                        >
                            {{ fmtStock(producto) }}
                        </p>
                        <p
                            v-else
                            class="text-muted-foreground shrink-0 text-[10px] italic"
                        >
                            Sin inv.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <nav
            v-if="productos.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="(link, index) in productos.links" :key="index">
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
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo producto</DialogTitle>
                    <DialogDescription
                        >Agrega un producto al catálogo. Se crea activo por
                        defecto.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarNuevo"
                >
                    <ProductoFormFields
                        :form="formNuevo"
                        :errors="formNuevo.errors"
                        :categorias="categorias"
                        :unidades-medida="unidadesMedida"
                        :mostrar-activo="false"
                    />

                    <DialogFooter>
                        <Button type="submit" :disabled="formNuevo.processing">
                            {{
                                formNuevo.processing
                                    ? 'Guardando...'
                                    : 'Crear producto'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogEditarAbierto">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar producto</DialogTitle>
                    <DialogDescription
                        >Actualiza los datos del producto.</DialogDescription
                    >
                </DialogHeader>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="guardarEditar"
                >
                    <ProductoFormFields
                        :form="formEditar"
                        :errors="formEditar.errors"
                        :categorias="categorias"
                        :unidades-medida="unidadesMedida"
                        :imagen-url="imagenUrlEditando"
                    />

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
