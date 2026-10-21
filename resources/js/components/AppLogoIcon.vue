<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

const page = usePage();
const name = page.props.name as string | undefined;
const initial = (name?.trim()?.[0] ?? '?').toUpperCase();
const iconUrlLight = page.props.iconUrlLight as string | null | undefined;
const iconUrlDark = page.props.iconUrlDark as string | null | undefined;
</script>

<template>
    <template v-if="iconUrlLight || iconUrlDark">
        <img
            v-if="iconUrlLight"
            :src="iconUrlLight"
            :alt="name"
            :class="className"
            v-bind="$attrs"
            class="object-contain dark:hidden"
        />
        <img
            v-if="iconUrlDark"
            :src="iconUrlDark"
            :alt="name"
            :class="className"
            v-bind="$attrs"
            class="hidden object-contain dark:block"
        />
    </template>
    <!-- sin ícono propio configurado (BRAND_ICON_URL_LIGHT/_DARK vacíos):
    inicial del nombre del negocio, nunca el ícono de otro negocio -->
    <span
        v-else
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
        class="font-script leading-none select-none"
        >{{ initial }}</span
    >
</template>
