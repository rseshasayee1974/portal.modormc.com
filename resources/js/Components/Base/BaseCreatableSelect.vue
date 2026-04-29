<script setup lang="ts">
import Select from 'primevue/select';
import Button from 'primevue/button';
import BaseField from './BaseField.vue';
import { ref, computed } from 'vue';

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        modelValue: any;
        options: any[];
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
        creating?: boolean;
    }>(),
    {
        required: false,
        disabled: false,
        size: 'medium',
        fluid: true,
        optionLabel: 'label',
        optionValue: 'value',
        creating: false
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
    (e: 'create', name: string): void;
}>();

const filterValue = ref('');

const onFilter = (event: any) => {
    filterValue.value = event.value || '';
};

const hasExactMatch = computed(() => {
    if (!filterValue.value) return true;
    const query = filterValue.value.toLowerCase().trim();
    return props.options.some(opt => {
        const label = (props.optionLabel ? opt[props.optionLabel] : opt).toString().toLowerCase().trim();
        return label === query;
    });
});

const handleCreate = () => {
    if (filterValue.value) {
        emit('create', filterValue.value);
        filterValue.value = '';
    }
};

const onHide = () => {
    filterValue.value = '';
};

const creatableLabel = computed(() => `Create "${filterValue.value}"`);

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
            <Select
                :id="inputId"
                :modelValue="modelValue"
                :options="options"
                :optionLabel="optionLabel"
                :optionValue="optionValue"
                :placeholder="placeholder"
                :disabled="disabled || creating"
                filter
                :size="size"
                :fluid="fluid"
                :class="invalid ? 'p-invalid' : null"
                @update:modelValue="emit('update:modelValue', $event)"
                @change="emit('change', $event)"
                @filter="onFilter"
                @hide="onHide"
            >
                <template #option="slotProps">
                    <slot name="option" v-bind="slotProps">
                        {{ optionLabel ? slotProps.option[optionLabel] : slotProps.option }}
                    </slot>
                </template>

                <template #footer>
                    <div v-if="filterValue && !hasExactMatch" class="p-2 border-t bg-slate-50/50">
                        <Button 
                            :label="creatableLabel" 
                            icon="pi pi-plus" 
                            fluid 
                            severity="secondary" 
                            variant="text" 
                            size="small" 
                            :loading="creating"
                            class="!justify-start font-bold text-indigo-600"
                            @click="handleCreate" 
                        />
                    </div>
                </template>

                <template #emptyfilter>
                    <div class="p-4 text-center">
                        <p class="text-xs text-slate-500 mb-3">No results found for "{{ filterValue }}"</p>
                        <Button 
                            :label="creatableLabel" 
                            icon="pi pi-plus" 
                            size="small"
                            severity="primary"
                            :loading="creating"
                            @click="handleCreate"
                        />
                    </div>
                </template>
            </Select>
        </template>
    </BaseField>
</template>
