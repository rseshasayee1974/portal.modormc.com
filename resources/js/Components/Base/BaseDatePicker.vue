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
                emit('update:modelValue', props.showTime ? new Date() : formatDate(new Date())); // fallback to current date on submit if empty
            }
        }, { capture: true });
    }
});

const formatDate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const internalValue = computed({
    get() {
        const parseValue = (val: any): any => {
            if (!val) return null;
            if (val instanceof Date) return val;
            if (typeof val === 'string') {
                // If pure date string YYYY-MM-DD, parse as local time to avoid UTC shift
                if (/^\d{4}-\d{2}-\d{2}$/.test(val)) {
                    const [y, m, d] = val.split('-').map(Number);
                    return new Date(y, m - 1, d);
                }
                const parsed = new Date(val);
                return isNaN(parsed.getTime()) ? null : parsed;
            }
            if (Array.isArray(val)) return val.map(parseValue);
            return val;
        };
        return parseValue(props.modelValue);
    },
    set(val) {
        if (props.showTime) {
            emit('update:modelValue', val);
            return;
        }

        if (val instanceof Date) {
            emit('update:modelValue', formatDate(val));
        } else if (Array.isArray(val)) {
            const formatted = val.map(v => (v instanceof Date ? formatDate(v) : v));
            emit('update:modelValue', formatted);
        } else {
            emit('update:modelValue', val);
        }
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
<style scoped>
:deep(.p-component) {
   border-radius: 4px !important;
}

:deep(.p-datepicker-input) {
    background-color: white !important;
}

:deep(.p-datepicker.p-disabled .p-datepicker-input),
:deep(.p-datepicker-input:disabled),
:deep(.p-datepicker-input[readonly]) {
    background-color: #eff1f1 !important;
    border-color: #d5d7d8 !important;
    color: #475569 !important;
    opacity: 0.75 !important;
    cursor: not-allowed !important;
}

:deep(.p-datepicker.p-disabled .p-datepicker-dropdown) {
    background-color: #eff1f1 !important;
    border-color: #d5d7d8 !important;
    opacity: 0.75 !important;
    cursor: not-allowed !important;
}
</style>
