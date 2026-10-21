<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref } from 'vue';

interface Option {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: Option[];
        placeholder?: string;
        emptyText?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Selecciona una opción',
        emptyText: 'Sin resultados.',
    },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const searchTerm = ref('');

const filteredOptions = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) {
        return props.options;
    }
    return props.options.filter((option) => option.label.toLowerCase().includes(term));
});

const selectedLabel = computed(() => props.options.find((option) => option.value === props.modelValue)?.label ?? '');
</script>

<template>
    <ComboboxRoot
        :model-value="modelValue"
        v-model:search-term="searchTerm"
        @update:model-value="(value) => emit('update:modelValue', (value as string) ?? '')"
    >
        <ComboboxAnchor
            :class="
                cn(
                    'border-input focus-within:border-ring focus-within:ring-ring/50 dark:bg-input/30 flex h-9 w-full items-center gap-2 rounded-md border bg-transparent px-3 shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]',
                )
            "
        >
            <ComboboxInput
                :id="id"
                :display-value="() => selectedLabel"
                :placeholder="placeholder"
                class="placeholder:text-muted-foreground h-full w-full bg-transparent text-sm outline-none"
            />
            <ComboboxTrigger class="text-muted-foreground shrink-0">
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="bg-popover text-popover-foreground z-50 max-h-(--reka-combobox-content-available-height) w-(--reka-combobox-trigger-width) overflow-x-hidden overflow-y-auto rounded-md border shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxEmpty class="text-muted-foreground py-2 text-center text-sm">
                        {{ emptyText }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in filteredOptions"
                        :key="option.value"
                        :value="option.value"
                        class="data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground flex cursor-pointer items-center justify-between rounded-sm px-2 py-1.5 text-sm outline-none select-none"
                    >
                        {{ option.label }}
                        <ComboboxItemIndicator>
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
