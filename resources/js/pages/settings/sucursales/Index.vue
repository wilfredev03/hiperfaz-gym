<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/sucursales';

interface SucursalRow {
    id: number;
    nombre: string;
    termino_orden: string;
    direccion: string | null;
    telefono: string | null;
    activa: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    sucursales: { data: SucursalRow[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sucursales', href: index() }],
    },
});
</script>

<template>
    <Head title="Sucursales" />

    <h1 class="sr-only">Sucursales</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <p class="text-muted-foreground max-w-md text-sm">
                Cada sucursal tiene su propia forma de nombrar las órdenes en el
                punto de venta.
            </p>
            <Button as-child size="sm">
                <Link :href="create()">
                    <Plus class="size-4" />
                    Nueva sucursal
                </Link>
            </Button>
        </div>

        <div
            v-if="sucursales.data.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Building2 class="size-8" />
            <p class="text-sm font-medium">
                Todavía no hay sucursales registradas.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr class="text-muted-foreground text-left">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Órdenes se llaman</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="sucursal in sucursales.data"
                        :key="sucursal.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="edit(sucursal.id)"
                                class="font-medium hover:underline"
                            >
                                {{ sucursal.nombre }}
                            </Link>
                            <span
                                v-if="!sucursal.activa"
                                class="text-muted-foreground ml-2 text-xs"
                                >(inactiva)</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Badge variant="secondary">{{
                                sucursal.termino_orden
                            }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="edit(sucursal.id)"
                                class="text-muted-foreground text-sm hover:underline"
                            >
                                Editar
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="sucursales.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in sucursales.links"
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
