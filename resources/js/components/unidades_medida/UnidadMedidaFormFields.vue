<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    form: {
        nombre: string;
        abreviatura: string;
        errors: Partial<Record<'nombre' | 'abreviatura', string>>;
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
                placeholder="Unidad, Kilogramo, Litro..."
            />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="abreviatura">Abreviatura</Label>
            <Input
                id="abreviatura"
                v-model="form.abreviatura"
                maxlength="10"
                placeholder="kg, und, dcna..."
            />
            <InputError :message="form.errors.abreviatura" />
        </div>
    </div>
</template>
