<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { RotateCcw, X } from '@lucide/vue';
import {
    cancelar,
    reintentar,
} from '@/actions/App/Http/Controllers/RecordatorioController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ESTADOS, TIPOS, fmtFechaHora } from '@/lib/recordatorios';
import type { RecordatorioFila } from '@/lib/recordatorios';
import { show } from '@/routes/socios';

defineProps<{
    filas: RecordatorioFila[];
    // en el historial se muestra cuándo pasó; en la cola, el mensaje que saldrá
    modo: 'cola' | 'historial';
}>();

function accion(url: string) {
    router.post(url, {}, { preserveScroll: true });
}
</script>

<template>
    <div
        v-if="filas.length === 0"
        class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
    >
        <template v-if="modo === 'cola'">
            No hay avisos en la cola. Se arma sola cada mañana, o pulsa
            «Generar cola de hoy».
        </template>
        <template v-else>Todavía no hay avisos en el historial.</template>
    </div>

    <div v-else class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50">
                <tr class="text-muted-foreground text-left">
                    <th class="px-4 py-3 font-medium">Cliente</th>
                    <th class="px-4 py-3 font-medium">Aviso</th>
                    <th class="px-4 py-3 font-medium">Mensaje</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th v-if="modo === 'historial'" class="px-4 py-3 font-medium">
                        Cuándo
                    </th>
                    <th class="px-4 py-3 text-right font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="fila in filas" :key="fila.id" class="border-t align-top">
                    <td class="px-4 py-3">
                        <Link
                            :href="show(fila.cliente_id)"
                            class="font-medium hover:underline"
                        >
                            {{ fila.cliente ?? 'Cliente eliminado' }}
                        </Link>
                        <p class="text-muted-foreground text-xs">
                            +{{ fila.telefono }}
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <Badge
                            :variant="fila.tipo === 'vence_hoy' ? 'default' : 'outline'"
                        >
                            {{ TIPOS[fila.tipo] }}
                        </Badge>
                    </td>
                    <td class="max-w-md px-4 py-3">
                        <p class="text-muted-foreground line-clamp-3 whitespace-pre-line">
                            {{ fila.mensaje }}
                        </p>
                        <p
                            v-if="fila.detalle"
                            class="text-destructive mt-1 text-xs"
                        >
                            {{ fila.detalle }}
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <Badge :variant="ESTADOS[fila.estado].variante">
                            {{ ESTADOS[fila.estado].texto }}
                        </Badge>
                        <p
                            v-if="fila.intentos > 1"
                            class="text-muted-foreground mt-1 text-xs"
                        >
                            {{ fila.intentos }} intentos
                        </p>
                    </td>
                    <td
                        v-if="modo === 'historial'"
                        class="text-muted-foreground px-4 py-3 whitespace-nowrap"
                    >
                        {{ fmtFechaHora(fila.enviado_en ?? fila.actualizado_en) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <Button
                            v-if="fila.estado === 'pendiente'"
                            variant="ghost"
                            size="sm"
                            @click="accion(cancelar.url(fila.id))"
                        >
                            <X class="size-4" />
                            Cancelar
                        </Button>
                        <Button
                            v-else-if="fila.estado === 'fallido'"
                            variant="ghost"
                            size="sm"
                            @click="accion(reintentar.url(fila.id))"
                        >
                            <RotateCcw class="size-4" />
                            Reintentar
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
