import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    // marcar el ítem también en sus subrutas (ej. /socios/create, /socios/5);
    // no usar si otro ítem cuelga de la misma ruta base (/ventas y /ventas/create)
    incluirSubrutas?: boolean;
};
