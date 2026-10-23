<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    form: {
        nombre: string;
        precio_venta: number | string;
        duracion_dias: number | string;
        activo: boolean;
        errors: Partial<
            Record<
                'nombre' | 'precio_venta' | 'duracion_dias' | 'activo',
                string
            >
        >;
    };
}>();

// atajos habituales; cualquier otra duración se teclea en el campo
const DURACIONES_RAPIDAS = [
    { dias: 1, etiqueta: '1 día' },
    { dias: 7, etiqueta: '7 días' },
    { dias: 30, etiqueta: '30 días' },
];
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="nombre">Nombre</Label>
            <Input
                id="nombre"
                v-model="form.nombre"
                placeholder="Diario, Semanal, Mensual..."
            />
            <InputError :message="form.errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="duracion_dias">Duración (días)</Label>
            <div class="flex flex-wrap items-center gap-2">
                <Input
                    id="duracion_dias"
                    v-model="form.duracion_dias"
                    type="number"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    class="w-28"
                />
                <Button
                    v-for="rapida in DURACIONES_RAPIDAS"
                    :key="rapida.dias"
                    type="button"
                    size="sm"
                    :variant="
                        Number(form.duracion_dias) === rapida.dias
                            ? 'default'
                            : 'outline'
                    "
                    @click="form.duracion_dias = rapida.dias"
                >
                    {{ rapida.etiqueta }}
                </Button>
            </div>
            <InputError :message="form.errors.duracion_dias" />
        </div>

        <div class="grid gap-2">
            <Label for="precio_venta">Precio (USD)</Label>
            <Input
                id="precio_venta"
                v-model="form.precio_venta"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                class="w-40"
            />
            <InputError :message="form.errors.precio_venta" />
        </div>

        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <Checkbox id="activo" v-model="form.activo" />
                <Label for="activo">Membresía activa</Label>
            </div>
            <p class="text-muted-foreground text-xs">
                Los inactivos no se ofrecen en el punto de venta.
            </p>
            <InputError :message="form.errors.activo" />
        </div>
    </div>
</template>
