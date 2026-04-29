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
        size?: 'small' | 'large';
        fluid?: boolean;
        fieldClass?: string;
        inputClass?: string;
    }>(),
    {
        required: false,
        disabled: false,
        readonly: false,
        minFractionDigits: 0,
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
<style>
.p-component {
   border-radius:4px !important;
}
.p-inputnumber-fluid .p-inputnumber-input {
    background-color: white !important;
}
</style>