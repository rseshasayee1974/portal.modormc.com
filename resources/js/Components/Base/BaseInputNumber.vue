<script setup lang="ts">
import InputNumber from 'primevue/inputnumber';
import BaseField from './BaseField.vue';

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        modelValue: number | null | undefined;
        label?: string;
        error?: ErrorValue;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        readonly?: boolean;
        placeholder?: string;
        minFractionDigits?: number;
        maxFractionDigits?: number;
        min?: number;
        max?: number;
        prefix?: string;
        suffix?: string;
        mode?: 'decimal' | 'currency';
        currency?: string;
        useGrouping?: boolean;
        size?: 'small' | 'large';
        fluid?: boolean;
        fieldClass?: string;
        inputClass?: string;
    }>(),
    {
        required: false,
        disabled: false,
        readonly: false,
        minFractionDigits: 2,
        maxFractionDigits: 3,
        size: 'small',
        fluid: true,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: number | null): void;
    (e: 'input', ev: any): void;
}>();
</script>

<template>
    <BaseField
        :label="label"
        :required="required"
        :error="error"
        :hint="hint"
        :disabled="disabled"
        :class="fieldClass"
    >
        <template #default="{ invalid, inputId }">
            <InputNumber
                :id="inputId"
                :modelValue="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                :minFractionDigits="minFractionDigits"
                :maxFractionDigits="maxFractionDigits"
                :min="min"
                :max="max"
                :prefix="prefix"
                :suffix="suffix"
                :mode="mode"
                :currency="currency"
                :useGrouping="useGrouping"
                :size="size"
                :fluid="fluid"
                :class="[
                    inputClass,
                    invalid ? 'p-invalid' : null
                ]"
                @update:modelValue="emit('update:modelValue', $event)"
                @input="emit('input', $event)"
            />
        </template>
    </BaseField>
</template>
<style scoped>
:deep(.p-component) {
   border-radius: 4px !important;
}

:deep(.p-inputnumber-input) {
    background-color: white !important;
}

:deep(.p-inputnumber.p-disabled .p-inputnumber-input),
:deep(.p-inputnumber-input:disabled),
:deep(.p-inputnumber-input[readonly]) {
    background-color: #eff1f1 !important;
    border-color: #d5d7d8 !important;
    color: #475569 !important;
    opacity: 0.75 !important;
    cursor: not-allowed !important;
}
</style>
