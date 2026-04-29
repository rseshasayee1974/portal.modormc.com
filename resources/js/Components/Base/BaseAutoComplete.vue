<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete';
import BaseField from './BaseField.vue';

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        modelValue: any;
        suggestions: any[];
        optionLabel?: string;
        optionValue?: string;
        label?: string;
        error?: ErrorValue;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
        size?: 'small' | 'medium' | 'large';
        fluid?: boolean;
        fieldClass?: string;
        dropdown?: boolean;
        multiple?: boolean;
        forceSelection?: boolean;
    }>(),
    {
        required: false,
        disabled: false,
        size: 'medium',
        fluid: true,
        dropdown: true,
        multiple: false,
        forceSelection: true,
        optionLabel: 'label'
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'complete', ev: any): void;
    (e: 'item-select', ev: any): void;
}>();

const handleUpdate = (val: any) => {
    // If it's an item selection (object) and we want an ID
    if (val && typeof val === 'object' && props.optionValue) {
        emit('update:modelValue', val[props.optionValue]);
    } else if (typeof val === 'string' && props.forceSelection) {
        // If forceSelection is on and we only have a string, don't update until it's a selection
        // or clear it if empty
        if (!val) emit('update:modelValue', null);
    } else {
        emit('update:modelValue', val || null);
    }
};

const handleSelect = (ev: any) => {
    if (props.optionValue && ev.value && typeof ev.value === 'object') {
        emit('update:modelValue', ev.value[props.optionValue]);
    }
    emit('item-select', ev);
};
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
            <AutoComplete
                :id="inputId"
                :modelValue="modelValue"
                :suggestions="suggestions"
                :optionLabel="optionLabel"
                :optionValue="optionValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :dropdown="dropdown"
                :multiple="multiple"
                :forceSelection="forceSelection"
                :fluid="fluid"
                :class="invalid ? 'p-invalid' : null"
                @update:modelValue="handleUpdate"
                @item-select="handleSelect"
                @complete="emit('complete', $event)"
            />
        </template>
    </BaseField>
</template>
