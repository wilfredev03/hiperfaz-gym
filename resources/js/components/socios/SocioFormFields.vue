<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Campo =
    | 'nombre'
    | 'documento'
    | 'telefono'
    | 'email'
    | 'direccion'
    | 'id_acceso'
    | 'activo';

withDefaults(
    defineProps<{
        form: {
            nombre: string;
            documento: string;
            telefono: string;
            email: string;
            direccion: string;
            id_acceso: string;
            activo: boolean;
            errors: Partial<Record<Campo, string>>;
        };
        mostrarActivo?: boolean;
    }>(),
    { mostrarActivo: true },
);
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="nombre">Nombre</Label>
            <Input id="nombre" v-model="form.nombre" autocomplete="off" />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="documento">Cédula</Label>
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
                autocomplete="off"
                placeholder="0414-1234567"
            />
            <InputError :message="form.errors.telefono" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Correo</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="off"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="direccion">Dirección</Label>
            <Input id="direccion" v-model="form.direccion" />
            <InputError :message="form.errors.direccion" />
        </div>

        <div class="grid gap-2">
            <Label for="id_acceso">ID de acceso (terminal)</Label>
            <Input
                id="id_acceso"
                v-model="form.id_acceso"
                placeholder="Opcional"
            />
            <InputError :message="form.errors.id_acceso" />
        </div>

        <div v-if="mostrarActivo" class="flex items-center gap-2 sm:col-span-2">
            <Checkbox id="activo" v-model="form.activo" />
            <Label for="activo">Cliente activo</Label>
        </div>
    </div>
</template>
