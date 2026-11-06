<script setup lang="ts">
import { computed, onMounted, ref, useAttrs } from 'vue';
import DatePicker from 'primevue/datepicker';
import BaseField from './BaseField.vue';
import { entityToday, entityDateTime, entityCalendarDate, calendarDateString, calendarDateTimeString } from '@/Utils/entityDateTime';

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
        autoFillOnSubmit?: boolean;
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
        autoFillOnSubmit: true,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
}>();

const baseFieldRef = ref<any>(null);
const attrs = useAttrs();
const datePickerAttrs = () => Object.fromEntries(Object.entries(attrs).filter(([key]) => key !== 'class'));

onMounted(() => {
    const el = baseFieldRef.value?.$el;
    const form = el?.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            if (props.autoFillOnSubmit && (props.modelValue === null || props.modelValue === undefined || props.modelValue === '')) {
                emit('update:modelValue', props.showTime ? entityDateTime() : entityToday());
            }
        }, { capture: true });
    }
});

const formatDate = (date: Date): string => {
    return calendarDateString(date);
};

const formatDateTime = (date: Date): string => {
    return calendarDateTimeString(date);
};

const internalValue = computed({
    get() {
        const parseValue = (val: any): any => {
            if (!val) return null;
            if (val instanceof Date) return val;
            if (typeof val === 'string') {
                return entityCalendarDate(val);
            }
            if (Array.isArray(val)) return val.map(parseValue);
            return val;
        };
        return parseValue(props.modelValue);
    },
    set(val) {
        if (props.showTime) {
            if (val instanceof Date) {
                emit('update:modelValue', formatDateTime(val));
            } else if (Array.isArray(val)) {
                const formatted = val.map(v => (v instanceof Date ? formatDateTime(v) : v));
                emit('update:modelValue', formatted);
            } else {
                emit('update:modelValue', val);
            }
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
        :class="[fieldClass, attrs.class]"
    >
        <template #default="{ invalid, inputId }">
            <DatePicker
                v-bind="datePickerAttrs()"
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
