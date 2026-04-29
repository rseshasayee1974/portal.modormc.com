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
    <div class="border-gray-200 px-4 bg-blue-50">
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
