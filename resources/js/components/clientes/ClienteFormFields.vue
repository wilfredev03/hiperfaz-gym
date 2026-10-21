<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ClienteFormLike {
    nombre: string;
    documento: string;
    telefono: string;
    direccion: string;
    activo: boolean;
    errors: Partial<
        Record<
            'nombre' | 'documento' | 'telefono' | 'direccion' | 'activo',
            string
        >
    >;
}

withDefaults(
    defineProps<{
        form: ClienteFormLike;
        mostrarActivo?: boolean;
    }>(),
    {
        mostrarActivo: true,
    },
);
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="nombre">Nombre</Label>
            <Input id="nombre" v-model="form.nombre" autocomplete="name" />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="documento">Documento</Label>
                <Input
                    id="documento"
                    v-model="form.documento"
                    placeholder="V-12345678"
                />
                <InputError :message="form.errors.documento" />
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

        <div class="grid gap-2">
            <Label for="direccion">Dirección</Label>
            <Input
                id="direccion"
                v-model="form.direccion"
                placeholder="Calle, sector, ciudad"
            />
            <InputError :message="form.errors.direccion" />
        </div>

        <div v-if="mostrarActivo" class="flex items-center gap-2">
            <Checkbox id="activo" v-model="form.activo" />
            <Label for="activo">Cliente activo</Label>
        </div>
    </div>
</template>
