<script setup lang="ts">
import Select from 'primevue/select';
import Button from 'primevue/button';
import BaseField from './BaseField.vue';

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
        filter?: boolean;
        size?: 'small' | 'medium' | 'large';
        fluid?: boolean;
        fieldClass?: string;
        addLabel?: string;
        addIcon?: string;
        showAddButton?: boolean;
    }>(),
    {
        required: false,
        disabled: false,
        filter: true,
        size: 'medium',
        fluid: true,
        addLabel: 'Add New',
        addIcon: 'pi pi-plus',
        showAddButton: true
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: any): void;
    (e: 'change', ev: any): void;
    (e: 'add'): void;
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
            <Select
                :id="inputId"
                :modelValue="modelValue"
                :options="options"
                :optionLabel="optionLabel"
                :optionValue="optionValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :filter="filter"
                :size="size"
                :fluid="fluid"
                :class="invalid ? 'p-invalid' : null"
                @update:modelValue="emit('update:modelValue', $event)"
                @change="emit('change', $event)"
            >
                <!-- Forward standard slots if needed (optional) -->
                <template #option="slotProps">
                    <slot name="option" v-bind="slotProps">
                        {{ optionLabel ? slotProps.option[optionLabel] : slotProps.option }}
                    </slot>
                </template>

                <template v-if="showAddButton" #footer>
                    <div class="p-2 border-t bg-slate-50/50">
                        <Button 
                            :label="addLabel" 
                            :icon="addIcon" 
                            fluid 
                            severity="secondary" 
                            variant="text" 
                            size="small" 
                            class="!justify-start font-bold text-indigo-600"
                            @click="emit('add')" 
                        />
                    </div>
                </template>
            </Select>
        </template>
    </BaseField>
</template>
