<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    form: {
        nombre: string;
        documento: string;
        telefono: string;
        email: string;
        direccion: string;
        activo: boolean;
        errors: Partial<
            Record<
                | 'nombre'
                | 'documento'
                | 'telefono'
                | 'email'
                | 'direccion'
                | 'activo',
                string
            >
        >;
    };
}>();
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="nombre">Nombre</Label>
            <Input
                id="nombre"
                v-model="form.nombre"
                placeholder="Frigorífico, distribuidora..."
            />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="documento">RIF / documento</Label>
                <Input id="documento" v-model="form.documento" />
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
            <Label for="email">Correo electrónico</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="email"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="direccion">Dirección</Label>
            <Input id="direccion" v-model="form.direccion" />
            <InputError :message="form.errors.direccion" />
        </div>

        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <Checkbox id="activo" v-model="form.activo" />
                <Label for="activo">Proveedor activo</Label>
            </div>
            <p class="text-muted-foreground text-xs">
                Los inactivos no se ofrecen al registrar una compra.
            </p>
            <InputError :message="form.errors.activo" />
        </div>
    </div>
</template>
