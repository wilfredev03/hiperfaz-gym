<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    form: {
        nombre: string;
        activo: boolean;
        errors: Partial<Record<'nombre' | 'activo', string>>;
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
                placeholder="Bebidas, Batidos, Suplementos..."
            />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <Checkbox id="activo" v-model="form.activo" />
                <Label for="activo">Categoría activa</Label>
            </div>
            <p class="text-muted-foreground text-xs">
                Las inactivas no se ofrecen al crear productos ni en el punto de
                venta.
            </p>
            <InputError :message="form.errors.activo" />
        </div>
    </div>
</template>
