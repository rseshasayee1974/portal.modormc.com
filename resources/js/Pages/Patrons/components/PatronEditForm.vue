<script setup lang="ts">
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import PatronFormFields from './PatronFormFields.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
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
    cancel: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-2  ">
        <!-- <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">
                    Edit: {{ form.legal_name || 'Patron' }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">Update general details, contact, and bank accounts.</p>
            </div>
            <Tag severity="warn" value="Editing" rounded />
        </div> -->

        <form class="flex flex-col gap-2" @submit.prevent="$emit('submit')">
            <Message v-if="Object.keys(form.errors).length > 0" severity="error" :closable="false">
                <div class="font-bold">Please correct the following errors before updating:</div>
                <ul class="mt-1 list-disc pl-4 text-sm font-normal">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </Message>

            <PatronFormFields
                :form="form"
                :patron-types="patronTypes"
                :operational-statuses="operationalStatuses"
                :states="states"
                :add-bank="addBank"
                :remove-bank="removeBank"
            />

            <BaseFormActions 
                submit-label="Update patron"
                :loading="form.processing"
                @cancel="$emit('cancel')"
                @submit="$emit('submit')"
            />
        </form>
    </div>
</template>
