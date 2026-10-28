<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    CalendarCheck,
    CalendarClock,
    DoorOpen,
    HandCoins,
    History,
    Lock,
    Package,
    PackagePlus,
    Receipt,
    Settings,
    ShoppingCart,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { index as cierresCajaIndex } from '@/routes/cierres_caja';
import { index as cuentasPorCobrarIndex } from '@/routes/clientes';
import { index as asistenciasIndex } from '@/routes/asistencias';
import { edit as editConexion } from '@/routes/conexion';
import { index as membresiasVendidasIndex } from '@/routes/membresias_vendidas';
import {
    create as crearOrdenCompra,
    index as ordenesCompraIndex,
} from '@/routes/ordenes_compra';
import { index as productosIndex } from '@/routes/productos';
import { index as recordatoriosIndex } from '@/routes/recordatorios';
import { index as sociosIndex } from '@/routes/socios';
import { index as tasasCambioIndex } from '@/routes/tasas_cambio';
import { index as vencimientosIndex } from '@/routes/vencimientos';
import { create as crearVenta, index as ventasIndex } from '@/routes/ventas';
import type { NavItem } from '@/types';

type NavItemRol = NavItem & { soloAdmin?: boolean };

const page = usePage();

interface GrupoNav {
    label: string;
    items: NavItemRol[];
}

// el gimnasio (socios, asistencia) va separado de lo que se vende en él
// (ventas, inventario, caja) para que cada pantalla tenga un solo propósito
const todosLosGrupos: GrupoNav[] = [
    {
        label: 'Gimnasio',
        items: [
            {
                title: 'Clientes',
                href: sociosIndex(),
                icon: Users,
                incluirSubrutas: true,
            },
            { title: 'Asistencia', href: asistenciasIndex(), icon: DoorOpen },
            {
                title: 'Por vencer',
                href: vencimientosIndex(),
                icon: CalendarClock,
            },
            {
                title: 'Membresías vendidas',
                href: membresiasVendidasIndex(),
                icon: CalendarCheck,
            },
            {
                title: 'Recordatorios',
                soloAdmin: true,
                href: recordatoriosIndex(),
                icon: BellRing,
            },
        ],
    },
    {
        label: 'Ventas',
        items: [
            { title: 'Nueva venta', href: crearVenta(), icon: ShoppingCart },
            { title: 'Ventas del día', href: ventasIndex(), icon: Receipt },
            {
                title: 'Catálogo de productos',
                soloAdmin: true,
                href: productosIndex(),
                icon: Package,
            },
            {
                title: 'Registrar compra',
                soloAdmin: true,
                href: crearOrdenCompra(),
                icon: PackagePlus,
            },
            {
                title: 'Historial de compras',
                soloAdmin: true,
                href: ordenesCompraIndex(),
                icon: History,
            },
        ],
    },
    {
        label: 'Finanzas',
        items: [
            {
                title: 'Cuentas por cobrar',
                href: cuentasPorCobrarIndex(),
                icon: HandCoins,
            },
            { title: 'Cierre de caja', href: cierresCajaIndex(), icon: Lock },
        ],
    },
    {
        label: 'Sistema',
        items: [
            {
                title: 'Tasas de cambio',
                href: tasasCambioIndex(),
                icon: TrendingUp,
            },
            { title: 'Configuración', href: editConexion(), icon: Settings },
        ],
    },
];

// el vendedor no ve el catálogo ni las compras (el backend además lo rechaza con 403)
const grupos = computed<GrupoNav[]>(() =>
    todosLosGrupos.map((grupo) => ({
        ...grupo,
        items: grupo.items.filter(
            ({ soloAdmin }) =>
                !soloAdmin || page.props.auth.user.rol === 'admin',
        ),
    })),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="group-data-[collapsible=icon]:p-2">
            <Link
                :href="crearVenta()"
                class="flex items-center justify-center rounded-md px-2 py-3 transition-opacity group-data-[collapsible=icon]:hidden hover:opacity-90"
            >
                <AppLogo />
            </Link>
            <!-- con la barra colapsada a íconos, el wordmark completo no
            entra: se muestra la marca compacta (inicial) en su lugar, para
            que nunca desaparezca del todo la identidad de marca -->
            <Link
                :href="crearVenta()"
                class="hidden items-center justify-center rounded-md py-1 transition-opacity group-data-[collapsible=icon]:flex hover:opacity-90"
            >
                <AppLogoIcon class="text-sidebar-primary size-8" />
            </Link>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                v-for="grupo in grupos"
                :key="grupo.label"
                :label="grupo.label"
                :items="grupo.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
