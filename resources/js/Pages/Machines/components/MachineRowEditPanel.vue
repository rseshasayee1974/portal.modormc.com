<script setup lang="ts">
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import MachineFormFields from './MachineFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    machineId: number;
    form: any;
    vehicleOptions: any[];
    docTypeOptions: any[];
    transportOwnerOptions: any[];
    addDocument: () => void;
    removeDocument: (index: number) => void;
    addLoan: () => void;
    removeLoan: (index: number) => void;
}>();

const emit = defineEmits(['submit', 'cancel']);
</script>

<template>
    <div class="expansion-panel-container">
        <!-- Header Section -->
        <div class="flex items-center gap-3 mb-6 px-2">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600">
                <PencilSquareIcon class="w-4 h-4" />
            </div>
            <div>
                <h4 class="text-[11px] font-black text-indigo-900 uppercase tracking-widest leading-none">Update Asset Ledger</h4>
                <p class="text-[9px] font-bold text-indigo-400 mt-1 uppercase tracking-tight">Syncing registration: {{ form.registration }}</p>
            </div>
        </div>

        <!-- Form Fields -->
        <MachineFormFields
            :form="form"
            :vehicle-options="vehicleOptions"
            :doc-type-options="docTypeOptions"
            :transport-owner-options="transportOwnerOptions"
            :add-document="addDocument"
            :remove-document="removeDocument"
            :add-loan="addLoan"
            :remove-loan="removeLoan"
        />

        <!-- Form Actions -->
        <div class="mt-8 pt-6 border-t border-indigo-100">
            <BaseFormActions
                label="Update Asset"
                cancel-label="Cancel"
                :loading="form.processing"
                @submit="$emit('submit')"
                @cancel="$emit('cancel')"
            />
        </div>
    </div>
</template>

<style scoped>
.expansion-panel-container {
    padding: 24px;
    background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    border-left: 4px solid #6366f1;
    border-top: 1px solid #c7d2fe;
    border-bottom: 1px solid #c7d2fe;
}
</style>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
