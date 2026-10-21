<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ImageIcon, X } from '@lucide/vue';
import { computed, ref } from 'vue';

interface Categoria {
    id: number;
    nombre: string;
}

interface UnidadMedida {
    id: number;
    nombre: string;
    abreviatura: string;
}

interface ProductoFormValues {
    nombre: string;
    descripcion: string;
    categoria_id: string;
    unidad_medida_id: string;
    precio_venta: string;
    imagen: File | null;
    controla_inventario: boolean;
    activo: boolean;
}

const props = withDefaults(
    defineProps<{
        form: ProductoFormValues;
        errors: Partial<Record<keyof ProductoFormValues, string>>;
        categorias: Categoria[];
        unidadesMedida: UnidadMedida[];
        imagenUrl?: string | null;
        mostrarActivo?: boolean;
    }>(),
    {
        imagenUrl: null,
        mostrarActivo: true,
    },
);

const opcionesCategorias = computed(() =>
    props.categorias.map((categoria) => ({
        value: String(categoria.id),
        label: categoria.nombre,
    })),
);

const opcionesUnidadesMedida = computed(() =>
    props.unidadesMedida.map((unidad) => ({
        value: String(unidad.id),
        label: `${unidad.nombre} (${unidad.abreviatura})`,
    })),
);

// mientras no se elija un archivo nuevo se muestra la imagen ya guardada
// (imagenUrl, solo en edición); al elegir uno se previsualiza ese archivo y
// se descarta el object URL anterior para no dejarlo colgado en memoria
const previewUrl = ref<string | null>(null);
const inputImagen = ref<HTMLInputElement | null>(null);

const imagenMostrada = computed(() => previewUrl.value ?? props.imagenUrl);

function onImagenSeleccionada(event: Event) {
    const archivo = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    props.form.imagen = archivo;
    previewUrl.value = archivo ? URL.createObjectURL(archivo) : null;
}

function quitarImagen() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = null;
    props.form.imagen = null;
    if (inputImagen.value) {
        inputImagen.value.value = '';
    }
}
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="nombre">Nombre</Label>
            <Input
                id="nombre"
                v-model="form.nombre"
                type="text"
                maxlength="255"
                autofocus
            />
            <InputError :message="errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="descripcion">Descripción</Label>
            <Textarea
                id="descripcion"
                v-model="form.descripcion"
                placeholder="Opcional"
                rows="3"
            />
            <InputError :message="errors.descripcion" />
        </div>

        <div class="grid gap-2">
            <Label for="imagen">Imagen</Label>
            <div class="flex items-center gap-3">
                <div
                    class="bg-muted flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border"
                >
                    <img
                        v-if="imagenMostrada"
                        :src="imagenMostrada"
                        alt=""
                        class="size-full object-cover"
                    />
                    <ImageIcon v-else class="text-muted-foreground/50 size-6" />
                </div>
                <div class="flex flex-col gap-1">
                    <input
                        id="imagen"
                        ref="inputImagen"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="text-muted-foreground file:bg-secondary file:text-secondary-foreground text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        @change="onImagenSeleccionada"
                    />
                    <button
                        v-if="previewUrl"
                        type="button"
                        class="text-muted-foreground hover:text-foreground flex w-fit items-center gap-1 text-xs"
                        @click="quitarImagen"
                    >
                        <X class="size-3" />
                        Quitar selección
                    </button>
                </div>
            </div>
            <InputError :message="errors.imagen" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="categoria_id">Categoría</Label>
                <Combobox
                    id="categoria_id"
                    v-model="form.categoria_id"
                    :options="opcionesCategorias"
                    placeholder="Buscar categoría..."
                    empty-text="No se encontraron categorías."
                />
                <InputError :message="errors.categoria_id" />
            </div>

            <div class="grid gap-2">
                <Label for="unidad_medida_id">Unidad de medida</Label>
                <Combobox
                    id="unidad_medida_id"
                    v-model="form.unidad_medida_id"
                    :options="opcionesUnidadesMedida"
                    placeholder="Buscar unidad..."
                    empty-text="No se encontraron unidades."
                />
                <InputError :message="errors.unidad_medida_id" />
            </div>
        </div>

        <div class="grid gap-2 sm:max-w-xs">
            <Label for="precio_venta">Precio de venta (USD)</Label>
            <Input
                id="precio_venta"
                v-model="form.precio_venta"
                type="number"
                step="0.01"
                min="0.01"
            />
            <InputError :message="errors.precio_venta" />
        </div>

        <div
            class="grid gap-4 rounded-lg border p-4"
            :class="mostrarActivo ? 'sm:grid-cols-2' : ''"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-0.5">
                    <Label for="controla_inventario">Controla inventario</Label>
                    <p class="text-muted-foreground text-xs">
                        Descuenta stock al vender. Desactívalo para productos
                        hechos al momento.
                    </p>
                </div>
                <Switch
                    id="controla_inventario"
                    v-model="form.controla_inventario"
                />
            </div>

            <div
                v-if="mostrarActivo"
                class="flex items-center justify-between gap-4"
            >
                <div class="space-y-0.5">
                    <Label for="activo">Activo</Label>
                    <p class="text-muted-foreground text-xs">
                        Los productos inactivos no aparecen al registrar una
                        venta.
                    </p>
                </div>
                <Switch id="activo" v-model="form.activo" />
            </div>
        </div>
    </div>
</template>
