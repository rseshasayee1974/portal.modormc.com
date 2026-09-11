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
        autoFilterFocus?: boolean;
        panelClass?: any;
        panelStyle?: any;
        panelWidth?: string | number;
        optionWidth?: string | number;
        overlayClass?: any;
        overlayStyle?: any;
        overlayWidth?: string | number;
        appendTo?: string;
        allowEmpty?: boolean;
        emptyLabel?: string;
    }>(),
    {
        options: () => [],
        required: false,
        disabled: false,
        filter: true,
        size: 'medium',
        fluid: true,
        showClear: false,
        autoFilterFocus: true,
        allowEmpty: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
}>();

const selectRef = ref(null);

const getOptionLabelText = (option: any) => {
    if (!option && option !== 0) return '';
    if (typeof option !== 'object') return String(option);
    if (typeof props.optionLabel === 'function') {
        return props.optionLabel(option);
    }
    if (typeof props.optionLabel === 'string' && option[props.optionLabel] !== undefined) {
        return option[props.optionLabel];
    }
    return option.label !== undefined ? option.label : String(option);
};

const getOptionValue = (option: any) => {
    if (!option && option !== 0) return option;
    if (typeof option !== 'object') return option;
    if (typeof props.optionValue === 'function') {
        return props.optionValue(option);
    }
    if (typeof props.optionValue === 'string' && option[props.optionValue] !== undefined) {
        return option[props.optionValue];
    }
    return option.value !== undefined ? option.value : option;
};

const isSpecialOption = (option: any) => {
    if (!option && option !== 0) return false;
    const val = getOptionValue(option);
    const label = String(getOptionLabelText(option) || '').trim();

    if (val === null || val === '' || String(val).toUpperCase() === 'ALL') return true;
    const lowerLabel = label.toLowerCase();
    if (
        label.startsWith('--') ||
        lowerLabel.includes('auto') ||
        lowerLabel.includes('none') ||
        lowerLabel.startsWith('all') ||
        lowerLabel.includes(' all ') ||
        lowerLabel.startsWith('select') ||
        lowerLabel.includes('not select') ||
        lowerLabel.includes('default')
    ) {
        return true;
    }
    return false;
};

const getSpecialOptionTag = (option: any) => {
    if (!option) return 'Default';
    const label = String(getOptionLabelText(option) || '').toLowerCase();
    const val = String(getOptionValue(option) || '').toUpperCase();
    if (label.includes('auto')) return 'Auto';
    if (label.includes('none')) return 'None';
    if (label.includes('all') || val === 'ALL') return 'All';
    if (label.includes('select')) return 'Select';
    return 'Default';
};

const normalizedOptions = computed(() => {
    const rawOpts = Array.isArray(props.options) ? [...props.options] : [];
    if (props.allowEmpty || props.emptyLabel) {
        const hasEmpty = rawOpts.some(o => {
            const v = getOptionValue(o);
            return v === null || v === '';
        });
        if (!hasEmpty) {
            const emptyText = props.emptyLabel || (props.placeholder ? `-- ${props.placeholder} --` : (props.label ? `-- Select ${props.label} --` : '-- None --'));
            const emptyObj: Record<string, any> = {};
            const lblKey = typeof props.optionLabel === 'string' ? props.optionLabel : 'label';
            const valKey = typeof props.optionValue === 'string' ? props.optionValue : 'value';
            emptyObj[lblKey] = emptyText;
            emptyObj[valKey] = null;
            rawOpts.unshift(emptyObj);
        }
    }
    return rawOpts;
});

const effectiveFilterFields = computed(() => {
    if (props.filterFields) return props.filterFields;
    if (typeof props.optionLabel === 'string') return [props.optionLabel];
    return [];
});

const effectiveOverlayStyle = computed(() => {
    const widthVal = props.panelWidth || props.optionWidth || props.overlayWidth;
    const styleObj: Record<string, any> = {};
    if (widthVal !== undefined && widthVal !== null && widthVal !== '') {
        const formatted = typeof widthVal === 'number' ? `${widthVal}px` : widthVal;
        styleObj.width = formatted;
        styleObj.minWidth = formatted;
    }
    const userStyle = props.overlayStyle || props.panelStyle;
    if (typeof userStyle === 'object' && userStyle !== null) {
        return { ...styleObj, ...userStyle };
    }
    return Object.keys(styleObj).length > 0 ? styleObj : undefined;
});

const effectiveOverlayClass = computed(() => {
    return props.overlayClass || props.panelClass;
});

const handleShow = () => {
    // Force focus on the filter input after the overlay is shown
    nextTick(() => {
        setTimeout(() => {
            const overlay = document.querySelector('.p-select-overlay:not([style*="display: none"])');
            const filterInput = overlay?.querySelector('.p-select-filter-input') || document.querySelector('.p-select-filter-input');
            if (filterInput instanceof HTMLInputElement) {
                filterInput.focus();
            }
        }, 50);
    });
};

const selectedSpecialOption = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
        return normalizedOptions.value.find(o => isSpecialOption(o)) || null;
    }
    const found = normalizedOptions.value.find(o => getOptionValue(o) === props.modelValue);
    return (found && isSpecialOption(found)) ? found : null;
});

const hasMatchingSpecialSelected = computed(() => {
    return !!selectedSpecialOption.value;
});

const getSelectedLabelText = (val: any) => {
    if (selectedSpecialOption.value) {
        return getOptionLabelText(selectedSpecialOption.value);
    }
    if (val === null || val === undefined || val === '') {
        return props.placeholder || '';
    }
    const found = normalizedOptions.value.find(o => getOptionValue(o) === val);
    if (found) return getOptionLabelText(found);
    return String(val);
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
                :autoFilterFocus="autoFilterFocus"
                :checkmark="true"
                :showClear="false"
                :size="size"
                :fluid="fluid"
                :panelClass="effectiveOverlayClass"
                :overlayClass="effectiveOverlayClass"
                :panelStyle="effectiveOverlayStyle"
                :overlayStyle="effectiveOverlayStyle"
                :appendTo="appendTo"
                :resetFilterOnHide="true"
                @show="handleShow"
                :class="[
                    invalid ? 'p-invalid' : null,
                    dark ? 'dark-select' : ''
                ]"
                @update:modelValue="emit('update:modelValue', $event)"
                @change="emit('change', $event)"
            >
                <template #option="slotProps">
                    <slot v-if="$slots.option" name="option" v-bind="slotProps" />
                    <template v-else>
                        <div class="flex items-center justify-between w-full text-xs text-slate-700 dark:text-slate-200">
                            <span>{{ getOptionLabelText(slotProps.option) }}</span>
                        </div>
                    </template>
                </template>
                <template #value="slotProps">
                    <slot v-if="$slots.value" name="value" v-bind="slotProps" />
                    <template v-else>
                        <span v-if="slotProps.value !== null && slotProps.value !== undefined && slotProps.value !== ''" class="text-xs text-slate-800 dark:text-slate-100 font-semibold">
                            {{ getSelectedLabelText(slotProps.value) }}
                        </span>
                        <span v-else class="text-xs text-slate-400">
                            {{ slotProps.placeholder || placeholder }}
                        </span>
                    </template>
                </template>
                <template v-if="$slots.header" #header="slotProps">
                    <slot name="header" v-bind="slotProps" />
                </template>
                <template v-if="$slots.footer" #footer="slotProps">
                    <slot name="footer" v-bind="slotProps" />
                </template>
            </Select>
        </template>
    </BaseField>
</template>

<style scoped>
/* Clear icon styling */
:deep([data-pc-section="clearicon"]),
:deep(.p-select-clear-icon) {
    cursor: pointer !important;
    opacity: 0.6;
    transition: opacity 0.15s ease-in-out;
}
:deep([data-pc-section="clearicon"]:hover),
:deep(.p-select-clear-icon:hover) {
    opacity: 1 !important;
}

/* Disabled state styling */
:deep(.p-select.p-disabled) {
    background-color: #eff1f1 !important;
    border-color: #d5d7d8 !important;
    opacity: 0.75 !important;
    cursor: not-allowed !important;
}

:deep(.p-select.p-disabled .p-select-placeholder) {
    color: #cbd5e1 !important;
    cursor: not-allowed !important;
}

/* Dark mode disabled state styling */
.is-dark :deep(.p-select.p-disabled) {
    background-color: #0f172a !important;
    border-color: #1e293b !important;
    opacity: 0.65 !important;
}

.is-dark :deep(.p-select.p-disabled .p-select-label) {
    color: #000000 !important;
}

.is-dark :deep(.p-select.p-disabled .p-select-placeholder) {
    color: #334155 !important;
}

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
