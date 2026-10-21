<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index as categoriasIndex } from '@/routes/categorias';
import { edit as editConexion } from '@/routes/conexion';
import { index as metodosPagoIndex } from '@/routes/metodos_pago';
import { index as planesIndex } from '@/routes/planes';
import { index as proveedoresIndex } from '@/routes/proveedores';
import { index as sucursalesIndex } from '@/routes/sucursales';
import { index as unidadesMedidaIndex } from '@/routes/unidades_medida';
import { index as usuariosIndex } from '@/routes/usuarios';
import type { NavItem } from '@/types';

const page = usePage();

// catálogo (categorías, unidades, proveedores, métodos de pago), sucursales
// y usuarios son configuración de administrador
const navItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (page.props.auth.user.rol === 'admin') {
        items.push(
            { title: 'Membresías', href: planesIndex() },
            { title: 'Categorías', href: categoriasIndex() },
            { title: 'Unidades de medida', href: unidadesMedidaIndex() },
            { title: 'Proveedores', href: proveedoresIndex() },
            { title: 'Métodos de pago', href: metodosPagoIndex() },
            { title: 'Sucursales', href: sucursalesIndex() },
            { title: 'Usuarios', href: usuariosIndex() },
        );
    }

    items.push({ title: 'Conexión', href: editConexion() });

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <nav
            class="border-border flex items-center gap-1 overflow-x-auto border-b"
            aria-label="Configuración"
        >
            <Link
                v-for="item in navItems"
                :key="toUrl(item.href)"
                :href="item.href"
                :class="[
                    '-mb-px inline-flex shrink-0 items-center border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                    isCurrentOrParentUrl(item.href)
                        ? 'border-primary text-foreground'
                        : 'text-muted-foreground hover:text-foreground border-transparent',
                ]"
            >
                {{ item.title }}
            </Link>
        </nav>

        <section class="max-w-2xl space-y-12 pt-6">
            <slot />
        </section>
    </div>
</template>
