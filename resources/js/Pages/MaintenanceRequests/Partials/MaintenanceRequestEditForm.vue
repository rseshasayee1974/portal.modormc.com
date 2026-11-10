<!--
Author: Cipher
Created: 2026-11-10 13:00:28 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import MaintenanceRequestForm from './MaintenanceRequestForm.vue';

defineProps<{
    form: any;
    editingId: number;
    machineOptions: Array<{ label: string; value: number }>;
    vendorOptions: Array<{ label: string; value: number }>;
    userOptions: Array<{ label: string; value: number }>;
    taxOptions: Array<{ label: string; value: number; rate?: number }>;
    productOptions: Array<{ label: string; value: number }>;
    unitOptions: Array<{ label: string; value: number }>;
    maintenanceTypes: Array<{ label: string; value: number }>;
    priorityLevels: Array<{ label: string; value: number }>;
    requestStatuses: Array<{ label: string; value: number }>;
}>();

const emit = defineEmits<{
    (event: 'submit'): void;
    (event: 'cancel'): void;
}>();
</script>

<template>
    <div class="relative m-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
        <MaintenanceRequestForm
            :form="form"
            :editingId="editingId"
            :machineOptions="machineOptions"
            :vendorOptions="vendorOptions"
            :userOptions="userOptions"
            :taxOptions="taxOptions"
            :productOptions="productOptions"
            :unitOptions="unitOptions"
            :maintenanceTypes="maintenanceTypes"
            :priorityLevels="priorityLevels"
            :requestStatuses="requestStatuses"
            class="!my-0 !shadow-none !border-none !ring-0"
            @submit="emit('submit')"
            @cancel="emit('cancel')"
        />
    </div>
</template>
