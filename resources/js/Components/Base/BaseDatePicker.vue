<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
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
        // new time props
        showTime?: boolean;
        hourFormat?: '12' | '24';
        showSeconds?: boolean;
    }>(),
    {
        required: false,
        disabled: false,
        dateFormat: 'yy-mm-dd',
        size: 'small',
        fluid: true,
        showIcon: true,
        iconDisplay: 'button',
        showTime: false,
        hourFormat: '24',
        showSeconds: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
}>();

const baseFieldRef = ref<any>(null);

onMounted(() => {
    const el = baseFieldRef.value?.$el;
    const form = el?.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
                emit('update:modelValue', new Date()); // fallback to current time on submit if empty
            }
        }, { capture: true });
    }
});

const internalValue = computed({
    get() {
        const parseValue = (val: any): any => {
            if (!val) return null;
            if (val instanceof Date) return val;
            if (typeof val === 'string') {
                const parsed = new Date(val);
                return isNaN(parsed.getTime()) ? null : parsed;
            }
            if (Array.isArray(val)) return val.map(parseValue);
            return val;
        };
        return parseValue(props.modelValue);
    },
    set(val) {
        emit('update:modelValue', val);
    }
});
</script>

<template>
    <BaseField
        ref="baseFieldRef"
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
                :modelValue="internalValue"
                :dateFormat="dateFormat"
                :placeholder="placeholder"
                :disabled="disabled"
                :size="size"
                :fluid="fluid"
                :showIcon="showIcon"
                :iconDisplay="iconDisplay"
                :showTime="showTime"
                :hourFormat="hourFormat"
                :showSeconds="showSeconds"
                :class="[inputClass, invalid ? 'p-invalid' : null]"
                @update:modelValue="internalValue = $event"
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
