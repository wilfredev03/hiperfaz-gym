<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as guardarTasaCambio } from '@/routes/tasas_cambio';

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    tasa: '',
});

// cada vez que se abre (desde cualquier pantalla) arranca en blanco, nunca
// con los datos/errores del último intento
watch(open, (valor) => {
    if (valor) {
        form.reset();
        form.clearErrors();
    }
});

function registrarTasa() {
    form.post(guardarTasaCambio.url(), {
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Nueva tasa de cambio</DialogTitle>
                <DialogDescription>
                    Las ventas ya facturadas no se recalculan con la tasa nueva.
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-6" @submit.prevent="registrarTasa">
                <div class="grid gap-2">
                    <Label for="tasa">Tasa (Bs. por USD)</Label>
                    <Input
                        id="tasa"
                        v-model="form.tasa"
                        type="number"
                        min="0.0001"
                        step="0.0001"
                        placeholder="Ej. 45.6789"
                        autofocus
                    />
                    <p v-if="form.errors.tasa" class="text-destructive text-sm">
                        {{ form.errors.tasa }}
                    </p>
                </div>

                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando...' : 'Registrar tasa' }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
