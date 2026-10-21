<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarClock, Phone } from '@lucide/vue';
import VigenciaBadge from '@/components/socios/VigenciaBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { fmtDia, textoRestante } from '@/lib/socios';
import type { Vigencia } from '@/lib/socios';
import { fmtUsd } from '@/lib/ventas';
import { show } from '@/routes/socios';
import { index } from '@/routes/vencimientos';
import { create as crearVenta } from '@/routes/ventas';

type Vista = 'por_vencer' | 'vencidos';

interface SocioVencimiento {
    id: number;
    nombre: string;
    telefono: string | null;
    saldo_pendiente: string;
    vence_el: string;
    vigencia: Vigencia;
    dias_restantes: number | null;
    dias_vencida: number | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    socios: { data: SocioVencimiento[]; links: PaginationLink[] };
    vista: Vista;
    resumen: {
        por_vencer: number;
        vencidos: number;
        dias_por_vencer: number;
        dias_vencidos: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Por vencer', href: index() }],
    },
});

function irA(vista: Vista) {
    router.get(
        index.url(),
        { vista },
        { preserveState: true, preserveScroll: true },
    );
}

function cuando(socio: SocioVencimiento): string {
    if (props.vista === 'por_vencer') {
        return textoRestante(socio.vigencia, socio.dias_restantes);
    }

    return socio.dias_vencida === 1
        ? 'Venció ayer'
        : `Venció hace ${socio.dias_vencida} días`;
}
</script>

<template>
    <Head title="Por vencer" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Renovaciones pendientes</h1>

            <div
                class="inline-flex items-center gap-1 rounded-full bg-neutral-500/10 p-1"
            >
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-all"
                    :class="
                        vista === 'por_vencer'
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="irA('por_vencer')"
                >
                    Por vencer ({{ resumen.por_vencer }})
                </button>
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-all"
                    :class="
                        vista === 'vencidos'
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="irA('vencidos')"
                >
                    Vencidos ({{ resumen.vencidos }})
                </button>
            </div>
        </div>

        <p class="text-muted-foreground text-sm">
            <template v-if="vista === 'por_vencer'">
                Clientes a los que les vence la membresía en los próximos
                {{ resumen.dias_por_vencer }} días.
            </template>
            <template v-else>
                Clientes cuya membresía venció en los últimos
                {{ resumen.dias_vencidos }} días y no han renovado.
            </template>
            Quien renueva sale de la lista solo.
        </p>

        <div
            v-if="socios.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <CalendarClock class="size-8" />
            <p class="text-sm font-medium">
                {{
                    vista === 'por_vencer'
                        ? 'Nadie tiene la membresía por vencer.'
                        : 'No hay clientes con la membresía vencida recientemente.'
                }}
            </p>
        </div>

        <Card v-else class="overflow-hidden py-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-b text-left">
                            <th class="px-4 py-3 font-medium">Cliente</th>
                            <th class="px-4 py-3 font-medium">Contacto</th>
                            <th class="px-4 py-3 font-medium">Vencimiento</th>
                            <th class="px-4 py-3">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="socio in socios.data"
                            :key="socio.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="show.url(socio.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ socio.nombre }}
                                </Link>
                                <Badge
                                    v-if="Number(socio.saldo_pendiente) > 0"
                                    variant="outline"
                                    class="ml-2 border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                >
                                    Debe {{ fmtUsd(socio.saldo_pendiente) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    v-if="socio.telefono"
                                    :href="`tel:${socio.telefono}`"
                                    class="text-muted-foreground inline-flex items-center gap-1.5 hover:underline"
                                >
                                    <Phone class="size-3.5" />
                                    {{ socio.telefono }}
                                </a>
                                <span v-else class="text-muted-foreground">
                                    Sin teléfono
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <VigenciaBadge :vigencia="socio.vigencia" />
                                    <span class="font-medium">
                                        {{ cuando(socio) }}
                                    </span>
                                </div>
                                <p class="text-muted-foreground text-xs">
                                    Último día: {{ fmtDia(socio.vence_el) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button as-child size="sm">
                                    <Link
                                        :href="
                                            crearVenta({
                                                query: { cliente: socio.id },
                                            })
                                        "
                                    >
                                        Renovar
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

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
