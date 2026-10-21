<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

defineProps<{
    form: {
        nombre: string;
        moneda: string;
        requiere_referencia: boolean;
        activo: boolean;
        orden: number | string;
        errors: Partial<
            Record<
                | 'nombre'
                | 'moneda'
                | 'requiere_referencia'
                | 'activo'
                | 'orden',
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
                placeholder="Efectivo USD, Pago móvil, Zelle..."
            />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="moneda">Moneda</Label>
                <Select v-model="form.moneda">
                    <SelectTrigger id="moneda" class="w-full">
                        <SelectValue placeholder="Elige la moneda" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="USD">USD (dólares)</SelectItem>
                        <SelectItem value="VES">VES (bolívares)</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.moneda" />
            </div>

            <div class="grid gap-2">
                <Label for="orden">Orden en el cobro</Label>
                <Input
                    id="orden"
                    v-model="form.orden"
                    type="number"
                    min="0"
                    max="65535"
                />
                <InputError :message="form.errors.orden" />
            </div>
        </div>

        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="requiere_referencia"
                    v-model="form.requiere_referencia"
                />
                <Label for="requiere_referencia"
                    >Pide número de referencia</Label
                >
            </div>
            <p class="text-muted-foreground text-xs">
                Para pago móvil, transferencia, Zelle y similares.
            </p>
            <InputError :message="form.errors.requiere_referencia" />
        </div>

        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <Checkbox id="activo" v-model="form.activo" />
                <Label for="activo">Método activo</Label>
            </div>
            <p class="text-muted-foreground text-xs">
                Los inactivos no aparecen en la pantalla de cobro.
            </p>
            <InputError :message="form.errors.activo" />
        </div>
    </div>
</template>
