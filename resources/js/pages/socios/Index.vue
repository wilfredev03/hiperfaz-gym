<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Plus, Search, Users } from '@lucide/vue';
import { ref } from 'vue';
import VigenciaBadge from '@/components/socios/VigenciaBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { fmtDia, textoRestante } from '@/lib/socios';
import type { SocioLista } from '@/lib/socios';
import { fmtUsd } from '@/lib/ventas';
import { create, index, show } from '@/routes/socios';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type Filtro =
    | 'todos'
    | 'vigentes'
    | 'por_vencer'
    | 'vencidas'
    | 'sin_membresia'
    | 'con_deuda';

const props = defineProps<{
    socios: { data: SocioLista[]; links: PaginationLink[] };
    busqueda: string;
    filtro: Filtro;
    resumen: { vigentes: number; por_vencer: number; vencidas: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: index() }],
    },
});

const FILTROS: { valor: Filtro; etiqueta: string }[] = [
    { valor: 'todos', etiqueta: 'Todos' },
    { valor: 'vigentes', etiqueta: 'Vigentes' },
    { valor: 'por_vencer', etiqueta: 'Por vencer' },
    { valor: 'vencidas', etiqueta: 'Vencidas' },
    { valor: 'sin_membresia', etiqueta: 'Sin membresía' },
    { valor: 'con_deuda', etiqueta: 'Con deuda' },
];

const texto = ref(props.busqueda);

function consulta(cambios: { busqueda?: string; filtro?: Filtro }) {
    const busqueda = (cambios.busqueda ?? texto.value).trim();
    const filtro = cambios.filtro ?? props.filtro;

    return {
        ...(busqueda ? { busqueda } : {}),
        ...(filtro !== 'todos' ? { filtro } : {}),
    };
}

watchDebounced(
    texto,
    () => {
        router.get(index.url(), consulta({}), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { debounce: 300 },
);

function cambiarFiltro(filtro: Filtro) {
    router.get(index.url(), consulta({ filtro }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Clientes" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Clientes</h1>
                <p class="text-muted-foreground text-sm">
                    {{ resumen.vigentes }} vigentes ·
                    {{ resumen.por_vencer }} por vencer ·
                    {{ resumen.vencidas }} vencidas
                </p>
            </div>
            <Button as-child size="sm">
                <Link :href="create.url()">
                    <Plus class="size-4" />
                    Nuevo cliente
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="texto"
                    placeholder="Buscar por nombre o cédula..."
                    class="pl-9"
                />
            </div>
            <div class="flex flex-wrap gap-1.5">
                <Button
                    v-for="opcion in FILTROS"
                    :key="opcion.valor"
                    size="sm"
                    :variant="filtro === opcion.valor ? 'default' : 'outline'"
                    @click="cambiarFiltro(opcion.valor)"
                >
                    {{ opcion.etiqueta }}
                </Button>
            </div>
        </div>

        <div
            v-if="socios.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Users class="size-8" />
            <p class="text-sm font-medium">No hay clientes con ese criterio.</p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium">Membresía</th>
                        <th class="hidden px-4 py-3 font-medium sm:table-cell">
                            Vence
                        </th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">
                            Debe
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="socio in socios.data"
                        :key="socio.id"
                        class="hover:bg-muted/40 cursor-pointer border-t"
                        @click="router.visit(show.url(socio.id))"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="show.url(socio.id)"
                                class="font-medium hover:underline"
                                @click.stop
                            >
                                {{ socio.nombre }}
                            </Link>
                            <p class="text-muted-foreground text-xs">
                                {{ socio.documento ?? 'Sin cédula' }}
                                <template v-if="!socio.activo">
                                    · Inactivo
                                </template>
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <VigenciaBadge :vigencia="socio.vigencia" />
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{
                                    textoRestante(
                                        socio.vigencia,
                                        socio.dias_restantes,
                                    )
                                }}
                            </p>
                        </td>
                        <td
                            class="text-muted-foreground hidden px-4 py-3 sm:table-cell"
                        >
                            {{ socio.vence_el ? fmtDia(socio.vence_el) : '—' }}
                        </td>
                        <td class="hidden px-4 py-3 md:table-cell">
                            <span
                                v-if="Number(socio.saldo_pendiente) > 0"
                                class="text-amber-700 dark:text-amber-400"
                            >
                                {{ fmtUsd(socio.saldo_pendiente) }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="socios.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in socios.links"
                :key="linkIndex"
            >
                <span
                    v-if="!link.url"
                    class="text-muted-foreground rounded-md px-3 py-1.5 text-sm"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
