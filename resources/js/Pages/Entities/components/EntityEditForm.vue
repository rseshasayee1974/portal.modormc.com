<script setup lang="ts">
import Message from 'primevue/message';
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
    cancel: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <form class="flex flex-col gap-2" @submit.prevent="$emit('submit')">
            <!-- Error Summary -->
            <Message
                v-if="form.errors && Object.keys(form.errors).length > 0"
                severity="error"
                :closable="false"
                class="mb-2"
            >
                <div class="font-bold">Please correct the following errors before updating:</div>
                <ul class="mt-1 list-disc pl-4 text-sm font-normal">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </Message>

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
                submit-label="Update Entity"
                submit-icon="pi pi-check"
                :loading="form.processing"
                @cancel="$emit('cancel')"
                @submit="$emit('submit')"
            />
        </form>
    </div>
</template>
