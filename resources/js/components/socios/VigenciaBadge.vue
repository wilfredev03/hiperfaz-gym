<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    VIGENCIA_ESTILOS,
    VIGENCIA_ETIQUETAS,
    type Vigencia,
} from '@/lib/socios';

defineProps<{ vigencia: Vigencia }>();
</script>

<template>
    <Badge variant="outline" :class="VIGENCIA_ESTILOS[vigencia]">
        {{ VIGENCIA_ETIQUETAS[vigencia] }}
    </Badge>
</template>
