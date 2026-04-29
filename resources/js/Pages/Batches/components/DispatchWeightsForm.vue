<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { ScaleIcon, BanknotesIcon, ShieldCheckIcon, TruckIcon } from '@heroicons/vue/24/outline';
import { computed, watch } from 'vue';

const props = defineProps<{
    modelValue: any; // The whole form object now
    uoms: any[];
    taxes: any[];
    loading_sites: any[];
    unloading_sites: any[];
    errors: any;
}>();

const emit = defineEmits(['update:modelValue']);

watch(
    [() => props.modelValue.weights.loaded_weight_truck, () => props.modelValue.weights.empty_weight_truck],
    ([loaded, empty]) => {
        const l = Number(loaded || 0);
        const e = Number(empty || 0);
        if (l > 0) {
            props.modelValue.financials.load_units = Number((l - e).toFixed(3));
        }
    }
);

watch(
    [() => props.modelValue.weights.loaded_weight_unload, () => props.modelValue.weights.empty_weight_unload],
    ([loaded, empty]) => {
        const l = Number(loaded || 0);
        const e = Number(empty || 0);
        if (l > 0) {
            props.modelValue.financials.unload_units = Number((l - e).toFixed(3));
        }
    }
);

const statusOptions = [
    { label: 'Draft', value: 'Draft' },
    // { label: 'Pending', value: 'Pending' },
    // { label: 'Loading', value: 'Loading' },
    // { label: 'In Transit', value: 'In Transit' },
    { label: 'Delivered', value: 'Delivered' },
    // { label: 'Completed', value: 'Completed' },
    { label: 'Cancelled', value: 'Cancelled' }
];

// Helper for subtotal calculations
const calculateSubtotal = (units: number, rate: number) => {
    return (Number(units || 0) * Number(rate || 0)).toFixed(2);
};
</script>

<template>
    <div class="space-y-1">
        <!-- 1. Header & Status -->
        <!-- <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-50 rounded-lg">
                    <ShieldCheckIcon class="h-6 w-6 text-orange-500" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-700 uppercase tracking-tight">Workflow & Status</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Dispatch Lifecycle Management</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-48">
                    <BaseSelect v-model="modelValue.status.dispatch_status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Select Status" />
                </div>
                <div class="flex items-center gap-3 h-10 px-4 rounded-lg bg-slate-50 border border-slate-200">
                    <input type="checkbox" v-model="modelValue.status.is_closed" id="is_closed_check" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="is_closed_check" class="text-[10px] font-black text-slate-600 uppercase">Closed</label>
                </div>
            </div>
        </div> -->

        <!-- 2. Dual Column Reconciliation -->
        <div class="grid grid-cols-12 gap-2">
            <!-- Source / Loading Side -->
            <div class="col-span-12 space-y-2">
                <div class="flex items-center gap-2 ">
                    <ScaleIcon class="h-4 w-4 text-indigo-500" />
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-500">Source: Loading Reconciliation</h4>
                </div>
                
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                    <!-- Weight Section -->
                    <div class="p-4 bg-slate-50/50 border-b border-slate-100">
                        <div class="grid grid-cols-6 gap-4 mb-4">
                            <BaseInputNumber v-model="modelValue.weights.empty_weight_truck" label="Empty Truck (kg)" :error="errors['weights.empty_weight_truck']" />
                            <BaseInputNumber v-model="modelValue.weights.loaded_weight_truck" label="Loaded Truck (kg)" :error="errors['weights.loaded_weight_truck']" />
                            <BaseInputNumber v-model="modelValue.financials.load_units" label="Load Quantity" />
                            <BaseSelect v-model="modelValue.financials.load_uom_id" :options="uoms" optionLabel="unit_code" optionValue="id" label="Unit of Measure" filter />
                            
                            <BaseInputNumber v-model="modelValue.financials.load_rate" label="Load Rate" :minFractionDigits="2" />
                            <BaseSelect v-model="modelValue.financials.load_tax_id" :options="taxes" optionLabel="tax_name" optionValue="id" label="Tax Group" filter showClear />
                        </div>
                        <div class="grid grid-cols-5 gap-4">
                            <BaseSelect v-model="modelValue.load_site_id" :options="loading_sites" optionLabel="name" optionValue="id" filter label="Loading Site" />
                            <BaseDatePicker v-model="modelValue.weights.empty_weight_time_load" label="Empty Time" showTime fluid />
                            <BaseDatePicker v-model="modelValue.weights.loaded_weight_time_load" label="Loaded Time" showTime fluid />
                        </div>
                        <div class="mt-4 flex items-center justify-between p-3 rounded-xl bg-indigo-50/50 border border-indigo-100">
                            <span class="text-[10px] font-black uppercase text-indigo-400">Load Subtotal</span>
                            <span class="text-lg font-black text-indigo-700">₹ {{ calculateSubtotal(modelValue.financials.load_units, modelValue.financials.load_rate) }}</span>
                        </div>
                    </div>
                </div>
            </div>
</div>

<div class="grid grid-cols-12 gap-1">
    
            <!-- Destination / Unloading Side -->
            <div class="col-span-12 space-y-2">
                <div class="flex items-center gap-2 mt-2">
                    <ScaleIcon class="h-4 w-4 text-emerald-500" />
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-500">Destination: Unloading Reconciliation</h4>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                    <!-- Weight Section -->
                    <div class="p-4 bg-slate-50/50 border-b border-slate-100">
                        <div class="grid grid-cols-6 gap-4 mb-4">
                            <BaseInputNumber v-model="modelValue.weights.empty_weight_unload" label="Empty Site (kg)" :error="errors['weights.empty_weight_unload']" />
                            <BaseInputNumber v-model="modelValue.weights.loaded_weight_unload" label="Loaded Site (kg)" :error="errors['weights.loaded_weight_unload']" />
                            <BaseInputNumber v-model="modelValue.financials.unload_units" label="Unload Quantity" />
                            <BaseSelect v-model="modelValue.financials.unload_uom_id" :options="uoms" optionLabel="unit_code" optionValue="id" label="Unit of Measure" filter />
                            <BaseInputNumber v-model="modelValue.financials.unload_rate" label="Unload Rate" :minFractionDigits="2" />
                            <BaseSelect v-model="modelValue.financials.unload_tax_id" :options="taxes" optionLabel="tax_name" optionValue="id" label="Tax Group" filter showClear />
                           </div>
                            <div class="grid grid-cols-5 gap-4 mb-4">
                            <BaseSelect v-model="modelValue.unload_site_id" :options="unloading_sites" optionLabel="name" optionValue="id" filter label="Unloading Site" />
                            <BaseDatePicker v-model="modelValue.weights.empty_weight_time_unload" label="Empty Time" showTime fluid />
                            <BaseDatePicker v-model="modelValue.weights.loaded_weight_time_unload" label="Loaded Time" showTime fluid />
                        </div>
                        <div class="mt-4 flex items-center justify-between p-3 rounded-xl bg-emerald-50/50 border border-emerald-100">
                            <span class="text-[10px] font-black uppercase text-emerald-400">Unload Subtotal</span>
                            <span class="text-lg font-black text-emerald-700">₹ {{ calculateSubtotal(modelValue.financials.unload_units, modelValue.financials.unload_rate) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Logistics & Billing Details -->
        <div class="grid grid-cols-12 gap-8 p-4 border-t border-slate-100">
            <div class="col-span-12 md:col-span-12 space-y-4">
                <div class="flex items-center gap-2">
                    <TruckIcon class="h-4 w-4 text-slate-400" />
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-500">Logistics Data</h4>
                </div>
                <div class="grid grid-cols-5 gap-4">
                    <BaseInput v-model="modelValue.status.invoice_number" label="Sales Invoice #" />
                    <BaseDatePicker v-model="modelValue.status.invoice_date" label="Invoice Date" showTime fluid />
                    <!-- <BaseInput v-model="modelValue.status.invoice_date" label="Invoice Date" type="date" /> -->
                </div>
                <!-- <div class="grid grid-cols-2 gap-4"> -->
                    <!-- <BaseInput v-model="modelValue.status.transport_invoice_number" label="Transport Bill #" /> -->
                    <!-- <BaseInput v-model="modelValue.status.transport_date" label="Bill Date" type="date" /> -->
                <!-- </div> -->
                <!-- <BaseInput v-model="modelValue.status.transport_km" label="Total Distance (KM)" type="number" step="0.01" /> -->
            </div>
</div>
<div class="grid grid-cols-12 gap-1 p-4">
            <div class="col-span-12 md:col-span-12 space-y-4">
                <div class="flex items-center gap-2">
                    <BanknotesIcon class="h-4 w-4 text-slate-400" />
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-500">Adjustments & Reconciliation</h4>
                </div>
                <div class="grid grid-cols-5 gap-4">
                    <BaseInputNumber v-model="modelValue.financials.pass_amount" label="Pass Amount" :minFractionDigits="2" />
                    <BaseInputNumber v-model="modelValue.financials.discount_amount" label="Discount" :minFractionDigits="2" />
                <!-- </div>
                <div class="grid grid-cols-2 gap-4"> -->
                    <BaseInputNumber v-model="modelValue.financials.transport_expenses" label="Padi" :minFractionDigits="2" />
                    <BaseInputNumber v-model="modelValue.financials.adjustment_amount" label="Adjustment" :minFractionDigits="2" />
                <!-- </div>
                <div class="grid grid-cols-2 gap-4"> -->
                    <!-- <BaseInputNumber v-model="modelValue.weights.round_off" label="Tolerance" :minFractionDigits="2" /> -->
                    <BaseInputNumber v-model="modelValue.financials.round_off" label="Round Off" :minFractionDigits="2" />
                </div>
            </div>
        </div>
    </div>
</template>

