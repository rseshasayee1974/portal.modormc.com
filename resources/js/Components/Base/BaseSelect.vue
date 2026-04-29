<script setup lang="ts">
import Select from 'primevue/select';
import BaseField from './BaseField.vue';
import { computed } from 'vue';

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
        size?: 'small' | 'medium' | 'large';
        fluid?: boolean;
        fieldClass?: string;
    }>(),
    {
        options: () => [],
        required: false,
        disabled: false,
        filter: false,
        size: 'medium',
        fluid: true,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
}>();

const normalizedOptions = computed(() => (Array.isArray(props.options) ? props.options : []));


</script>

<template>
    <BaseField
        :label="label"
        :required="required"
        :error="error"
        class="text-gray-700"
        :hint="hint"
        :disabled="disabled"
        :class="fieldClass"
    >
        <template #default="{ invalid, inputId }">
            <Select
                :id="inputId"
                :modelValue="modelValue"
                :options="normalizedOptions"
                :optionLabel="optionLabel"
                :optionValue="optionValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :filter="filter"
                :checkmark="true"
                :size="size"
                :fluid="fluid"
                :class="invalid ? 'p-invalid' : null"
                @update:modelValue="emit('update:modelValue', $event)"
                @change="emit('change', $event)"
            />
        </template>
    </BaseField>
</template>
<style>
.fieldClass{
    color : #1b1b1b !important;
}
</style>
