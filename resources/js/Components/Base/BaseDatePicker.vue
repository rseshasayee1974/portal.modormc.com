<script setup lang="ts">
import DatePicker from 'primevue/datepicker';
import BaseField from './BaseField.vue';

defineOptions({ inheritAttrs: false });

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        modelValue: any;
        label?: string;
        error?: ErrorValue;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
        dateFormat?: string;
        size?: 'small' | 'large';
        fluid?: boolean;
        showIcon?: boolean;
        iconDisplay?: 'input' | 'button';
        fieldClass?: string;
        inputClass?: string;
    }>(),
    {
        required: false,
        disabled: false,
        dateFormat: 'yy-mm-dd',
        size: 'small',
        fluid: true,
        showIcon: true,
        iconDisplay: 'button',
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
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
            <DatePicker
                v-bind="$attrs"
                :id="inputId"
                :modelValue="modelValue"
                :dateFormat="dateFormat"
                :placeholder="placeholder"
                :disabled="disabled"
                :size="size"
                :fluid="fluid"
                :showIcon="showIcon"
                :iconDisplay="iconDisplay"
                :class="[
                    inputClass,
                    invalid ? 'p-invalid' : null
                ]"
                @update:modelValue="emit('update:modelValue', $event)"
                @change="emit('change', $event)"
            />
        </template>
    </BaseField>
</template>
<style>
.p-component {
   border-radius:4px !important;
}
.p-datepicker-input {
    background-color: white !important;
}
</style>