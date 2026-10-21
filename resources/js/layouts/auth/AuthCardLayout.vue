<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Store } from '@lucide/vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const name = page.props.name as string | undefined;
const logoUrlLight = page.props.logoUrlLight as string | null | undefined;
const logoUrlDark = page.props.logoUrlDark as string | null | undefined;
</script>

<template>
    <div
        class="bg-background flex min-h-dvh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div class="flex w-full max-w-md min-w-0 flex-col">
            <Card class="min-w-0 gap-0 overflow-hidden pt-0">
                <!-- la franja invierte respecto al fondo (negra en claro,
                blanca en oscuro) usando --foreground, el mismo token que ya
                se invierte solo con el tema -->
                <div class="bg-foreground h-1 w-full" />

                <Link
                    :href="home()"
                    class="flex flex-col items-center self-center pt-6"
                >
                    <img
                        v-if="logoUrlLight"
                        :src="logoUrlLight"
                        :alt="name"
                        class="h-10 w-auto object-contain dark:hidden"
                    />
                    <img
                        v-if="logoUrlDark"
                        :src="logoUrlDark"
                        :alt="name"
                        class="hidden h-10 w-auto object-contain dark:block"
                    />
                    <!-- sin logo propio configurado: ícono genérico + nombre,
                    nunca el logo de otro negocio -->
                    <span
                        v-if="!logoUrlLight && !logoUrlDark"
                        class="text-foreground flex items-center gap-2"
                    >
                        <Store class="size-7" />
                        <span class="text-lg font-medium">{{ name }}</span>
                    </span>
                </Link>

                <CardHeader class="pt-3 pb-0 text-center">
                    <CardTitle v-if="title" class="text-xl">
                        {{ title }}
                    </CardTitle>
                    <CardDescription v-if="description">
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="min-w-0 pt-5 pb-6">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
