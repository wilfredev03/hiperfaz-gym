<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { DoorOpen, Search } from '@lucide/vue';
import { ref } from 'vue';
import { store as registrarEntrada } from '@/actions/App/Http/Controllers/AsistenciaController';
import VigenciaBadge from '@/components/socios/VigenciaBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { fmtDia, textoRestante } from '@/lib/socios';
import type { Vigencia } from '@/lib/socios';
import { fmtHora } from '@/lib/ventas';
import { index } from '@/routes/asistencias';
import { show } from '@/routes/socios';

interface Candidato {
    id: number;
    nombre: string;
    documento: string | null;
    activo: boolean;
    vence_el: string | null;
    vigencia: Vigencia;
    dias_restantes: number | null;
}

const props = defineProps<{
    busqueda: string;
    candidatos: Candidato[];
    resumen: { permitidas: number; denegadas: number };
    asistencias: {
        id: number;
        cliente_id: number | null;
        fecha_hora: string;
        resultado: 'permitido' | 'denegado';
        origen: 'manual' | 'terminal';
        cliente: {
            id: number;
            nombre: string;
            documento: string | null;
        } | null;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Asistencia', href: index() }],
    },
});

const texto = ref(props.busqueda);

watchDebounced(
    texto,
    () => {
        const busqueda = texto.value.trim();

        router.get(index.url(), busqueda ? { busqueda } : {}, {
            only: ['busqueda', 'candidatos'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { debounce: 250 },
);

const form = useForm({ cliente_id: 0 });

function registrar(candidato: Candidato) {
    form.cliente_id = candidato.id;
    form.post(registrarEntrada.url(), {
        preserveScroll: true,
        onSuccess: () => {
            texto.value = '';
        },
    });
}
</script>

<template>
    <Head title="Asistencia" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <div>
            <h1 class="text-xl font-semibold">Asistencia</h1>
            <p class="text-muted-foreground text-sm">
                Hoy: {{ resumen.permitidas }} entradas permitidas ·
                {{ resumen.denegadas }} denegadas
            </p>
        </div>

        <section class="rounded-xl border p-4">
            <div class="relative max-w-md">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="texto"
                    autofocus
                    placeholder="Busca al cliente por nombre o cédula..."
                    class="pl-9"
                />
            </div>

            <p
                v-if="busqueda && candidatos.length === 0"
                class="text-muted-foreground mt-3 text-sm"
            >
                No se encontró ningún cliente.
            </p>

            <ul v-if="candidatos.length" class="mt-3 grid gap-2">
                <li
                    v-for="candidato in candidatos"
                    :key="candidato.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                >
                    <div>
                        <Link
                            :href="show.url(candidato.id)"
                            class="font-medium hover:underline"
                        >
                            {{ candidato.nombre }}
                        </Link>
                        <p class="text-muted-foreground text-xs">
                            {{ candidato.documento ?? 'Sin cédula' }} ·
                            {{
                                textoRestante(
                                    candidato.vigencia,
                                    candidato.dias_restantes,
                                )
                            }}
                            <template v-if="candidato.vence_el">
                                (hasta {{ fmtDia(candidato.vence_el) }})
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <VigenciaBadge :vigencia="candidato.vigencia" />
                        <Button
                            size="sm"
                            :disabled="form.processing"
                            @click="registrar(candidato)"
                        >
                            <DoorOpen class="size-4" />
                            Registrar entrada
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border">
            <h2 class="px-4 py-3 text-sm font-semibold">Entradas de hoy</h2>
            <p
                v-if="asistencias.length === 0"
                class="text-muted-foreground border-t px-4 py-6 text-center text-sm"
            >
                Todavía no hay entradas registradas hoy.
            </p>
            <table v-else class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="asistencia in asistencias"
                        :key="asistencia.id"
                        class="border-t"
                    >
                        <td class="text-muted-foreground w-20 px-4 py-2.5">
                            {{ fmtHora(asistencia.fecha_hora) }}
                        </td>
                        <td class="px-4 py-2.5">
                            <Link
                                v-if="asistencia.cliente"
                                :href="show.url(asistencia.cliente.id)"
                                class="hover:underline"
                            >
                                {{ asistencia.cliente.nombre }}
                            </Link>
                            <span v-else class="text-muted-foreground">
                                Cliente no identificado
                            </span>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right"
                            :class="
                                asistencia.resultado === 'permitido'
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-red-700 dark:text-red-400'
                            "
                        >
                            {{
                                asistencia.resultado === 'permitido'
                                    ? 'Permitida'
                                    : 'Denegada'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
