<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Store } from '@lucide/vue';

const page = usePage();
const name = page.props.name as string | undefined;
const logoUrlLight = page.props.logoUrlLight as string | null | undefined;
const logoUrlDark = page.props.logoUrlDark as string | null | undefined;
</script>

<template>
    <div class="flex flex-1 items-center overflow-hidden">
        <img
            v-if="logoUrlLight"
            :src="logoUrlLight"
            :alt="name"
            class="h-10 w-auto max-w-full shrink-0 object-contain dark:hidden"
        />
        <img
            v-if="logoUrlDark"
            :src="logoUrlDark"
            :alt="name"
            class="hidden h-10 w-auto max-w-full shrink-0 object-contain dark:block"
        />
        <!-- sin logo propio configurado (BRAND_LOGO_URL_LIGHT/_DARK vacíos):
        nombre de la app con un ícono genérico, nunca el logo de otro negocio -->
        <span
            v-if="!logoUrlLight && !logoUrlDark"
            class="text-sidebar-foreground flex min-w-0 items-center gap-2"
        >
            <Store class="text-sidebar-primary size-6 shrink-0" />
            <span class="font-script truncate text-lg leading-none">
                {{ name }}
            </span>
        </span>
    </div>
</template>
