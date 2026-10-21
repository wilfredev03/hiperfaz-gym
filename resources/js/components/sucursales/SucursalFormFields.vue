<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface SucursalFormLike {
    nombre: string;
    termino_orden: string;
    direccion: string;
    telefono: string;
    activa: boolean;
    errors: Partial<
        Record<
            'nombre' | 'termino_orden' | 'direccion' | 'telefono' | 'activa',
            string
        >
    >;
}

withDefaults(
    defineProps<{
        form: SucursalFormLike;
        mostrarActiva?: boolean;
    }>(),
    {
        mostrarActiva: true,
    },
);
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="nombre">Nombre</Label>
            <Input
                id="nombre"
                v-model="form.nombre"
                placeholder="Sucursal Centro"
            />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="termino_orden">Cómo llamar las órdenes</Label>
            <Input
                id="termino_orden"
                v-model="form.termino_orden"
                placeholder="Orden"
            />
            <p class="text-muted-foreground text-xs">
                Se usa en la pantalla de nueva venta: "Orden 1", "Mesa 1",
                "Pedido 1"...
            </p>
            <InputError :message="form.errors.termino_orden" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    v-model="form.direccion"
                    placeholder="Calle, sector, ciudad"
                />
                <InputError :message="form.errors.direccion" />
            </div>
            <div class="grid gap-2">
                <Label for="telefono">Teléfono</Label>
                <Input
                    id="telefono"
                    v-model="form.telefono"
                    autocomplete="tel"
                    placeholder="0414-1234567"
                />
                <InputError :message="form.errors.telefono" />
            </div>
        </div>

        <div v-if="mostrarActiva" class="flex items-center gap-2">
            <Checkbox id="activa" v-model="form.activa" />
            <Label for="activa">Sucursal activa</Label>
        </div>
    </div>
</template>
