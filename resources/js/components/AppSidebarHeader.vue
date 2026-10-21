<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();

// el botón siempre alterna entre claro/oscuro (nunca deja "system"), para
// que su ícono sea predecible: refleja a qué modo cambia con un solo tap
function alternarApariencia() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header
        class="border-sidebar-border/70 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <Button variant="ghost" size="icon" @click="alternarApariencia">
            <Sun v-if="resolvedAppearance === 'dark'" class="size-4.5" />
            <Moon v-else class="size-4.5" />
            <span class="sr-only"
                >Cambiar a modo
                {{ resolvedAppearance === 'dark' ? 'claro' : 'oscuro' }}</span
            >
        </Button>
    </header>
</template>
