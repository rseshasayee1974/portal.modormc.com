<script setup lang="ts">
import PatronEditForm from './PatronEditForm.vue';

defineProps<{
    patronId: number;
    form: any;
    patronTypes: any[];
    operationalStatuses: any[];
    states: any[];
    addBank: () => void;
    removeBank: (index: number) => void;
}>();

defineEmits<{
    submit: [];
    cancel: [];
}>();
</script>

<template>
    <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg p-6 text-base">
        <PatronEditForm
            :form="form"
            :patron-types="patronTypes"
            :operational-statuses="operationalStatuses"
            :states="states"
            :add-bank="addBank"
            :remove-bank="removeBank"
            @submit="$emit('submit')"
            @cancel="$emit('cancel')"
        />
    </div>
</template>
