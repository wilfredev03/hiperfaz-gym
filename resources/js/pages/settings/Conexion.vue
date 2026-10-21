<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CloudOff, TriangleAlert } from '@lucide/vue';
import { edit } from '@/routes/conexion';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Conexión', href: edit() }],
    },
});
</script>

<template>
    <Head title="Conexión" />

    <h1 class="sr-only">Conexión</h1>

    <div class="space-y-6">
        <div class="flex items-center gap-3 rounded-xl border p-4">
            <span class="relative flex size-2.5">
                <span
                    class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500/30"
                />
                <span
                    class="relative inline-flex size-2.5 rounded-full bg-emerald-500"
                />
            </span>
            <p class="text-sm font-medium">En línea</p>
        </div>

        <div class="space-y-3">
            <div>
                <h2 class="mb-0.5 text-base font-medium">
                    Trabajo sin Internet
                </h2>
                <p class="text-muted-foreground text-sm">
                    Cómo se comporta el punto de venta cuando se cae la señal.
                </p>
            </div>

            <div
                class="text-muted-foreground flex gap-3 rounded-xl border border-dashed p-4 text-sm"
            >
                <CloudOff class="mt-0.5 size-5 shrink-0" />
                <div class="space-y-2">
                    <p class="text-foreground font-medium">
                        Todavía no disponible
                    </p>
                    <p>
                        El sistema se sirve desde el servidor, así que hoy
                        necesita Internet para cargar y para guardar cada venta.
                        Trabajar sin conexión no es una opción que se pueda
                        activar aquí: requiere que el punto de venta se guarde
                        primero en este equipo y se sincronice al volver la
                        señal.
                    </p>
                </div>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0 text-amber-600" />
            <p class="text-muted-foreground">
                Mientras tanto, si se cae la señal en medio de un cobro, la
                venta no se guarda y hay que volver a cargarla cuando regrese la
                conexión.
            </p>
        </div>
    </div>
</template>
