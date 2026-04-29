<script setup lang="ts">
import Button from 'primevue/button';
import PatronFormFields from './PatronFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

defineProps<{
    form: any;
    patronTypes: any[];
    operationalStatuses: any[];
    states: any[];
    addBank: () => void;
    removeBank: (index: number) => void;
}>();

defineEmits<{
    submit: [];
    export: [];
    import: [];
    template: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-6 ">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4  bg-white dark:bg-gray-900 rounded-xl   border-gray-200 dark:border-gray-800">

    <!-- Left Section: Icon + Title -->
    <div class="flex items-start gap-3">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-900/40">
            <i class="pi pi-user-plus text-indigo-600 dark:text-indigo-400 text-xl"></i>
        </div>

        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 leading-tight">
                Create New Patron
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Add general details, primary contact, and bank accounts in one place.
            </p>
        </div>
    </div>

    <!-- Right Section: Actions -->
    <div class="flex flex-wrap items-center gap-2 justify-start md:justify-end">

        <!-- <Button 
            label="Template" 
            icon="pi pi-file-export" 
            text 
            class="text-gray-600 dark:text-gray-300"
            @click="$emit('template')" 
        /> -->

        <Button 
            label="Import CSV" 
            icon="pi pi-upload" 
            outlined 
            severity="secondary"
            @click="$emit('import')" 
        />

        <Button 
            label="Export CSV" 
            icon="pi pi-download" 
            severity="primary"
            @click="$emit('export')" 
        />
    </div>
</div>
        <form class="flex flex-col gap-8" @submit.prevent="$emit('submit')">
            <PatronFormFields
                :form="form"
                :patron-types="patronTypes"
                :operational-statuses="operationalStatuses"
                :states="states"
                :add-bank="addBank"
                :remove-bank="removeBank"
            />

            <BaseFormActions 
                submit-label="Create Patron"
                :show-cancel="false"
                :loading="form.processing"
                @submit="$emit('submit')"
            />
        </form>
    </div>
</template>
