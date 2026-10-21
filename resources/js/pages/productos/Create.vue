<script setup lang="ts">
import { store as guardarProducto } from '@/actions/App/Http/Controllers/ProductoController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import ProductoFormFields from '@/components/productos/ProductoFormFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    create as crearProducto,
    index as productosIndex,
} from '@/routes/productos';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Categoria {
    id: number;
    nombre: string;
}

interface UnidadMedida {
    id: number;
    nombre: string;
    abreviatura: string;
}

defineProps<{
    categorias: Categoria[];
    unidadesMedida: UnidadMedida[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogo de productos', href: productosIndex() },
            { title: 'Nuevo producto', href: crearProducto() },
        ],
    },
});

const form = useForm({
    nombre: '',
    descripcion: '',
    categoria_id: '',
    unidad_medida_id: '',
    precio_venta: '',
    imagen: null as File | null,
    controla_inventario: true,
    activo: true,
});

const erroresGenerales = computed(() => Object.values(form.errors));

function guardar() {
    form.post(guardarProducto.url());
}
</script>

<template>
    <Head title="Nuevo producto" />

    <div class="flex flex-col gap-6">
        <Heading
            title="Nuevo producto"
            description="Agrega un producto al catálogo."
        />

        <AlertError v-if="erroresGenerales.length" :errors="erroresGenerales" />

        <Card class="max-w-2xl">
            <CardContent class="pt-6">
                <form class="flex flex-col gap-6" @submit.prevent="guardar">
                    <ProductoFormFields
                        :form="form"
                        :errors="form.errors"
                        :categorias="categorias"
                        :unidades-medida="unidadesMedida"
                        :mostrar-activo="false"
                    />

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing
                                    ? 'Guardando...'
                                    : 'Crear producto'
                            }}
                        </Button>
                        <Button type="button" variant="outline" as-child>
                            <a :href="productosIndex().url">Cancelar</a>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
