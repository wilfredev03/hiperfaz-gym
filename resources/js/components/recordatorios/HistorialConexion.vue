<script setup lang="ts">
import { EVENTOS_BOT, fmtFechaHora, fmtFechaHoraSeg } from '@/lib/recordatorios';
import type { Conexiones } from '@/lib/recordatorios';

defineProps<{ conexiones: Conexiones }>();

const COLOR_PUNTO = {
    ok: 'bg-emerald-500',
    aviso: 'bg-amber-500',
    error: 'bg-destructive',
    neutro: 'bg-muted-foreground/60',
} as const;
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border p-3">
                <p class="text-muted-foreground text-xs">Última conexión</p>
                <p class="font-medium">
                    {{ fmtFechaHora(conexiones.resumen.ultima_conexion) }}
                </p>
            </div>
            <div class="rounded-lg border p-3">
                <p class="text-muted-foreground text-xs">Caídas hoy</p>
                <p
                    class="text-2xl font-semibold"
                    :class="{ 'text-destructive': conexiones.resumen.caidas_hoy > 0 }"
                >
                    {{ conexiones.resumen.caidas_hoy }}
                </p>
            </div>
            <div class="rounded-lg border p-3">
                <p class="text-muted-foreground text-xs">Reconexiones hoy</p>
                <p class="text-2xl font-semibold">
                    {{ conexiones.resumen.reconexiones_hoy }}
                </p>
            </div>
        </div>

        <p class="text-muted-foreground text-sm">
            Cada vez que se genera un QR, se vincula un número, se cae la
            conexión o se recupera, queda anotado aquí. Muchas caídas seguidas
            suelen indicar un problema de internet en el equipo del bot.
        </p>

        <p
            v-if="conexiones.eventos.length === 0"
            class="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm"
        >
            Todavía no hay eventos. Aparecen cuando el bot se inicia y se
            consulta por primera vez.
        </p>

        <ol v-else class="divide-y rounded-xl border">
            <li
                v-for="evento in conexiones.eventos"
                :key="evento.id"
                class="flex items-start gap-3 px-4 py-3"
            >
                <span
                    class="mt-1.5 size-2.5 shrink-0 rounded-full"
                    :class="COLOR_PUNTO[EVENTOS_BOT[evento.tipo].tono]"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                        <p class="font-medium">
                            {{ EVENTOS_BOT[evento.tipo].texto }}
                            <span
                                v-if="evento.numero"
                                class="text-muted-foreground font-normal"
                            >
                                · +{{ evento.numero }}
                            </span>
                        </p>
                        <time
                            class="text-muted-foreground text-xs tabular-nums"
                            :datetime="evento.ocurrido_en"
                        >
                            {{ fmtFechaHoraSeg(evento.ocurrido_en) }}
                        </time>
                    </div>
                    <p v-if="evento.detalle" class="text-muted-foreground text-sm">
                        {{ evento.detalle }}
                        <template v-if="evento.usuario">
                            ({{ evento.usuario }})
                        </template>
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
