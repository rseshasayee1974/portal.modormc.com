<script setup lang="ts">
import EntityEditForm from './EntityEditForm.vue';

defineProps<{
    entityId: number;
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
    <div class="border-t-2 border-violet-100 dark:border-violet-900/30 px-6 py-5 bg-violet-50/40 dark:bg-violet-900/5">
        <div class="flex items-center gap-2 mb-4">
            <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-violet-600 shadow-sm">
                <i class="pi pi-pencil text-white text-xs"></i>
            </div>
            <span class="text-sm font-black uppercase tracking-widest text-violet-700 dark:text-violet-400">
                Editing Entity #{{ entityId }}
            </span>
        </div>

        <EntityEditForm
            :form="form"
            :entity-types="entityTypes"
            :address-types="addressTypes"
            :contact-types="contactTypes"
            :bank-account-types="bankAccountTypes"
            :countries="countries"
            :state-codes="stateCodes"
            @submit="$emit('submit')"
            @cancel="$emit('cancel')"
        />
    </div>
</template>
