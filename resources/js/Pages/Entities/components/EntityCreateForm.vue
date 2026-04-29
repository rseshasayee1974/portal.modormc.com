<script setup lang="ts">
import Button from 'primevue/button';
import EntityFormFields from './EntityFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

defineProps<{
    form: any;
    entityTypes: { id: number; type: string }[];
    addressTypes: { id: number; type: string }[];
    contactTypes: { id: number; type: string }[];
    bankAccountTypes: { id: number; type: string }[];
    countries: { id: number; country_name: string }[];
    stateCodes: { id: number; state_name: string; state_code: string; country_id: number }[];
}>();

defineEmits<{
    submit: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Header Row -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-900 rounded-xl border-gray-200 dark:border-gray-800">
            <!-- Left: Icon + Title -->
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-violet-100 dark:bg-violet-900/40">
                    <i class="pi pi-building text-violet-600 dark:text-violet-400 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 leading-tight">
                        Register New Entity
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Add a new organization, subsidiary, or operational unit.
                    </p>
                </div>
            </div>
        </div>

        <!-- Form -->
        <form class="flex flex-col gap-8" @submit.prevent="$emit('submit')">
            <EntityFormFields
                :form="form"
                :entity-types="entityTypes"
                :address-types="addressTypes"
                :contact-types="contactTypes"
                :bank-account-types="bankAccountTypes"
                :countries="countries"
                :state-codes="stateCodes"
            />

            <BaseFormActions
                submit-label="Create Entity"
                submit-icon="pi pi-building"
                :show-cancel="false"
                :loading="form.processing"
                @submit="$emit('submit')"
            />
        </form>
    </div>
</template>
