<script setup lang="ts">
import { computed } from 'vue';
import BaseButton from './BaseButton.vue';

interface Props {
    // Main action label options
    label?: string;           // Generic fallback
    saveLabel?: string;
    updateLabel?: string;
    addLabel?: string;

    cancelLabel?: string;
    submitIcon?: string;
    cancelIcon?: string;
    loading?: boolean;
    showCancel?: boolean;
    disabled?: boolean;
    
    // Optional: force specific mode
    mode?: 'save' | 'update' | 'add';
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Save Changes',
    saveLabel: 'Save Changes',
    updateLabel: 'Update',
    addLabel: 'Add',
    cancelLabel: 'Cancel',
    submitIcon: 'pi pi-check',
    cancelIcon: 'pi pi-times',
    loading: false,
    showCancel: true,
    disabled: false,
    mode: 'save', // default mode
});

const emit = defineEmits<{
    submit: [];
    cancel: [];
    reset: [];
    save: [];
    update: [];
    add: [];
}>();

const onSubmit = () => {
    emit('submit');
    if (props.mode === 'save') emit('save');
    if (props.mode === 'update') emit('update');
    if (props.mode === 'add') emit('add');
};

// Computed label based on mode or direct props
const submitLabel = computed(() => {
    if (props.label && props.label !== 'Save Changes') return props.label; // if user passes label directly

    switch (props.mode) {
        case 'update':
            return props.updateLabel;
        case 'add':
            return props.addLabel;
        case 'save':
        default:
            return props.saveLabel;
    }
});

// Dynamic icon based on mode
const submitIcon = computed(() => {
    if (props.submitIcon !== 'pi pi-check') return props.submitIcon; // if overridden

    switch (props.mode) {
        case 'add':
            return 'pi pi-plus';
        case 'update':
            return 'pi pi-pencil';
        case 'save':
        default:
            return 'pi pi-check';
    }
});
</script>

<template>
    <div class="flex items-center justify-end gap-3 ">
        <!-- Cancel Button -->
        <BaseButton
            v-if="showCancel"
            :label="cancelLabel"
            :icon="cancelIcon"
            type="button"
            variant="outlined"
            class="!bg-white dark:!bg-slate-900 !text-slate-600 dark:!text-slate-400 
                   !border-slate-200 dark:!border-slate-700 
                   hover:!bg-slate-50 dark:hover:!bg-slate-800 
                   hover:!border-slate-300 dark:hover:!border-slate-600 
                   transition-all !px-6 !h-10 text-xs font-bold uppercase tracking-wide shadow-sm"
            :disabled="loading"
            @click="() => { $emit('cancel'); $emit('reset'); }"
        />

        <!-- Primary Action Button (Save / Update / Add) -->
        <BaseButton
            :label="submitLabel"
            :icon="submitIcon"
            :loading="loading"
            type="submit"
            class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700 active:!bg-indigo-800 
                   !text-white !px-8 !h-10 text-xs font-bold uppercase tracking-wide 
                   shadow-md shadow-indigo-100 dark:shadow-indigo-950/50 
                   transition-all disabled:opacity-70 disabled:cursor-not-allowed"
            :disabled="loading || disabled"
            @click="onSubmit"
        />
    </div>
</template>

<style scoped>
button:not(:disabled):hover {
    transform: translateY(-1px);
}
button:not(:disabled):active {
    transform: translateY(0);
}
</style>