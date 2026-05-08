<script setup lang="ts">
import Select from 'primevue/select';
import BaseField from './BaseField.vue';
import { computed, ref, nextTick } from 'vue';

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        modelValue: any;
        options: any[];
        optionLabel?: string | ((option: any) => any);
        optionValue?: string | ((option: any) => any);
        label?: string;
        error?: ErrorValue;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
        filter?: boolean;
        filterFields?: string[];
        size?: 'small' | 'medium' | 'large';
        fluid?: boolean;
        fieldClass?: string;
        showClear?: boolean;
        dark?: boolean;
    }>(),
    {
        options: () => [],
        required: false,
        disabled: false,
        filter: true,
        size: 'medium',
        fluid: true,
        showClear: true,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
}>();

const selectRef = ref(null);

const normalizedOptions = computed(() => (Array.isArray(props.options) ? props.options : []));

const effectiveFilterFields = computed(() => {
    if (props.filterFields) return props.filterFields;
    if (typeof props.optionLabel === 'string') return [props.optionLabel];
    return [];
});

const handleShow = () => {
    // Force focus on the filter input after the overlay is shown
    nextTick(() => {
        const filterInput = document.querySelector('.p-select-filter-input');
        if (filterInput instanceof HTMLInputElement) {
            filterInput.focus();
        }
    });
};

</script>

<template>
    <BaseField
        :label="label"
        :required="required"
        :error="error"
        :hint="hint"
        :disabled="disabled"
        :class="[fieldClass, { 'is-dark': dark }]"
    >
        <template #default="{ invalid, inputId }">
            <Select
                ref="selectRef"
                :id="inputId"
                :modelValue="modelValue"
                :options="normalizedOptions"
                :optionLabel="optionLabel"
                :optionValue="optionValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :filter="filter"
                :filterFields="effectiveFilterFields"
                :checkmark="true"
                :size="size"
                :fluid="fluid"
                :resetFilterOnHide="true"
                @show="handleShow"
                :class="[
                    invalid ? 'p-invalid' : null,
                    dark ? 'dark-select' : ''
                ]"
                @update:modelValue="emit('update:modelValue', $event)"
                @change="emit('change', $event)"
            />
        </template>
    </BaseField>
</template>

<style scoped>
/* Dark Theme Overrides */
.is-dark :deep(.p-select) {
    background: #1e293b !important;
    border-color: #334155 !important;
}

.is-dark :deep(.p-select-label) {
    color: white !important;
}

.is-dark :deep(.p-select-placeholder) {
    color: #64748b !important;
}

/* Ensure Search Input is clear and focused */
:deep(.p-select-filter-input) {
    background: white !important;
    color: black !important;
    border: 1px solid #e2e8f0 !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.875rem !important;
}

.is-dark :deep(.p-select-filter-input) {
    background: #0f172a !important;
    color: white !important;
    border-color: #334155 !important;
}

/* Fix dropdown list colors for dark mode */
.is-dark :deep(.p-select-overlay) {
    background: #1e293b !important;
    border-color: #334155 !important;
}

.is-dark :deep(.p-select-option) {
    color: #cbd5e1 !important;
}

.is-dark :deep(.p-select-option:hover) {
    background: #334155 !important;
    color: white !important;
}

.is-dark :deep(.p-select-option.p-highlight) {
    background: #4f46e5 !important;
    color: white !important;
}

/* Search Icon Fix */
.is-dark :deep(.p-select-filter-icon) {
    color: #64748b !important;
}

/* Global focus state for the filter */
:deep(.p-select-filter-input:focus) {
    outline: 2px solid #4f46e5 !important;
    border-color: transparent !important;
}
</style>
