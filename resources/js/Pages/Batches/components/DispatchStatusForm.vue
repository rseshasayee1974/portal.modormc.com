<script setup lang="ts">
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { ShieldCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    modelValue: any;
    errors: any;
}>();

const statusOptions = [
    { label: 'Draft', value: 'Draft' },
    // { label: 'Pending', value: 'Pending' },
    // { label: 'Loading', value: 'Loading' },
    // { label: 'In Transit', value: 'In Transit' },
    { label: 'Delivered', value: 'Delivered' },
    // { label: 'Completed', value: 'Completed' },
    { label: 'Cancelled', value: 'Cancelled' }
];
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
            <ShieldCheckIcon class="h-5 w-5 text-orange-500" />
            <h3 class="text-sm font-bold text-slate-700">Dispatch Status & Workflow</h3>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 md:col-span-4">
                <BaseSelect v-model="modelValue.dispatch_status" :options="statusOptions" optionLabel="label" optionValue="value" label="Current Status" />
            </div>
            
            <div class="col-span-12 md:col-span-4 flex items-end">
                <div class="flex items-center gap-3 h-10 px-4 rounded-lg bg-slate-50 border border-slate-200">
                    <input type="checkbox" v-model="modelValue.is_closed" id="is_closed_check" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="is_closed_check" class="text-xs font-bold text-slate-600 uppercase">Mark as Closed</label>
                </div>
            </div>

            <div class="col-span-12 md:col-span-4">
                <BaseInput v-model="modelValue.transport_km" label="Distance (KM)" type="number" step="0.01" />
            </div>

            <div class="col-span-12 md:col-span-6 grid grid-cols-2 gap-4">
                <!-- <BaseInput v-model="modelValue.invoice_number" label="Invoice #" /> -->
                <BaseInput v-model="modelValue.invoice_date" label="Invoice Date" type="date" />
            </div>

            <div class="col-span-12 md:col-span-6 grid grid-cols-2 gap-4">
                <BaseInput v-model="modelValue.transport_invoice_number" label="Transport Bill #" />
                <BaseInput v-model="modelValue.transport_date" label="Transport Date" type="date" />
            </div>
        </div>
    </div>
</template>
