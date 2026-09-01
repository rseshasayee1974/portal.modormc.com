<script setup lang="ts">
import InputText from 'primevue/inputtext';
import BaseField from './BaseField.vue';

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null | undefined;
        label?: string;
        error?: ErrorValue;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
        type?: string;
        size?: 'small' | 'large';
        fluid?: boolean;
        inputClass?: string;
        fieldClass?: string;
    }>(),
    {
        type: 'text',
        size: 'small',
        fluid: true,
        required: false,
        disabled: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: string | number | null): void;
    (e: 'blur', ev: FocusEvent): void;
}>();
</script>

<template>
    <BaseField :label="label" :required="required" :error="error" :hint="hint" :disabled="disabled" :class="fieldClass">
        <template #default="{ invalid, inputId }">
            <InputText :id="inputId" :modelValue="modelValue ?? ''" :type="type" :placeholder="placeholder"
                :disabled="disabled" :size="size" :fluid="fluid" :class="[
                    inputClass,
                    invalid ? 'p-invalid' : null,
                ]" @update:modelValue="emit('update:modelValue', $event as any)" @blur="emit('blur', $event)" />
        </template>
    </BaseField>
</template>

<style scoped>
:deep(.p-component) {
    border-radius: 4px !important;
}

:deep(.p-inputtext) {
    background-color: white !important;
}

:deep(.p-inputtext:disabled),
:deep(.p-inputtext[readonly]),
:deep(.p-inputtext.p-disabled) {
    background-color: #eff1f1 !important;
    border-color: #d5d7d8 !important;
    color: #475569 !important;
    opacity: 0.75 !important;
    cursor: not-allowed !important;
}

.fieldClass {
    color: #1b1b1b !important;
}
</style>
