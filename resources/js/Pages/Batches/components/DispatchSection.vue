<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DispatchWeightsForm from './DispatchWeightsForm.vue';
import DispatchTransportForm from './DispatchTransportForm.vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import { PaperAirplaneIcon, ReceiptPercentIcon, CalculatorIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

const props = defineProps<{
    batch: any;
    workOrder: any;
    dropdownData: {
        trucks: any[];
        transporters: any[];
        loading_sites: any[];
        unloading_sites: any[];
        personnel: any[];
        taxes: any[];
        uoms: any[];
    };
    settings?: any;
}>();

const emit = defineEmits(['cancel', 'saved']);

const form = useForm({
    work_order_id: props.workOrder?.id,
    batch_id: props.batch?.id,
    batch_size: props.batch?.batch_size || 0,
    plant_sno: '',
    prefix: '',
    dispatch_no: '',
    dispatch_time: new Date(),
    delivered_qty: props.batch?.batch_size || 0,
    truck_id: props.batch?.truck_id,
    transport_id: props.workOrder?.transport_id,
    customer_id: props.workOrder?.customer_id,
    mixdesign_id: props.workOrder?.mix_design_id,
    load_site_id: null,
    unload_site_id: props.workOrder?.site_id,
    driver_id: null,
    cleaner_id: null,
    receiver_name: '',
    receive_mobile: '',
    note: '',
    payment_mode: 'credit',

    weights: {
        empty_weight_truck: props.batch?.empty_weight_truck || 0,
        loaded_weight_truck: props.batch?.loaded_weight_truck || 0,
        empty_weight_time_load: props.batch?.empty_time ? new Date(props.batch.empty_time) : null,
        loaded_weight_time_load: props.batch?.load_time ? new Date(props.batch.load_time) : null,
        empty_weight_unload: 0,
        loaded_weight_unload: 0,
        empty_weight_time_unload: null,
        loaded_weight_time_unload: null,
        round_off: 0
    },

    financials: {
        load_units: props.batch?.loaded_weight_truck ? Number((Number(props.batch.loaded_weight_truck) - Number(props.batch.empty_weight_truck || 0)).toFixed(3)) : (props.batch?.batch_size || 0),
        load_rate: 0,
        load_tax_id: null,
        load_uom_id: props.batch?.uom_id,
        unload_units: props.batch?.batch_size || 0,
        unload_rate: 0,
        unload_tax_id: null,
        unload_uom_id: props.batch?.uom_id,
        transport_units: props.batch?.batch_size || 0,
        transport_rate: 0,
        transport_tax_id: null,
        transport_uom_id: props.batch?.uom_id,
        pass_amount: 0,
        discount_amount: 0,
        transport_expenses: 0,
        adjustment_amount: 0,
        round_off: 0,
        invoice_number: '',
        invoice_date: null,
    },

    status: {
        dispatch_status: 'Draft',
        is_closed: false,
        driver_salary_status: 0,
        cleaner_salary_status: 0,
        is_load_tax_inclusive: false,
        is_unload_tax_inclusive: false,
        transport_km: 0,
        transport_invoice_number: '',
        transport_date: null
    },
    settings: props.settings || {}
});

const isMetricTon = computed(() => props.settings?.InvoiceInMetricTon == 1);

const displayUnits = computed(() => {
    return isMetricTon.value ? form.batch_size : form.financials.load_units;
});

const grossAmount = computed(() => {
    const units = isMetricTon.value ? Number(form.batch_size || 0) : Number(form.financials.load_units || 0);
    return units * Number(form.financials.load_rate || 0);
});

const taxAmount = computed(() => {
    const tax = props.dropdownData.taxes.find(t => t.id === form.financials.load_tax_id);
    const rate = tax ? Number(tax.tax_rate || 0) : 0;
    return (grossAmount.value * rate) / 100;
});

const totalAmount = computed(() => {
    return (
        grossAmount.value + 
        taxAmount.value - 
        Number(form.financials.discount_amount || 0) + 
        // Number(form.financials.transport_expenses || 0) + 
        Number(form.financials.pass_amount || 0) + 
        Number(form.financials.round_off || 0) +
        Number(form.financials.adjustment_amount || 0)
    );
});

const selectedUom = computed(() => {
    const uom = props.dropdownData.uoms.find(u => u.id === form.financials.load_uom_id);
    return uom ? uom.unit_code : 'UNIT';
});

const submit = () => {
    form.post(route('dispatches.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
        }
    });
};
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-slate-50/50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-600 p-2 text-white shadow-md shadow-indigo-100">
                    <PaperAirplaneIcon class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-tight">Generate Dispatch</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Batch #{{ batch.batch_no }} &bull; WO #{{ workOrder.order_no }}</p>
                </div>
            </div>
            
            <Button label="Initialize Dispatch" icon="pi pi-send" severity="info" size="small" class="!rounded-lg" @click="submit" :loading="form.processing" v-if="false" />
        </div>

        <div class="grid grid-cols-12">
            <!-- Left Side: Forms -->
            <div class="col-span-12 lg:col-span-8 p-1 border-r border-slate-100 space-y-1">
           
            <DispatchWeightsForm 
                v-model="form" 
                :uoms="dropdownData.uoms"
                :taxes="dropdownData.taxes"
                :loading_sites="dropdownData.loading_sites"
                :unloading_sites="dropdownData.unloading_sites"
                :errors="form.errors" 
            />
 <!-- <hr class="border-slate-100" /> -->
             <!-- <DispatchTransportForm 
                v-model="form" 
                :trucks="dropdownData.trucks" 
                :transporters="dropdownData.transporters"
                :personnel="dropdownData.personnel"
                :errors="form.errors"
            /> -->
            </div>

            <!-- Right Side: Receipt Sidebar -->
            <div class="col-span-12 lg:col-span-4 bg-amber-200/50 p-5 flex flex-col justify-between">
                <div>
                    <div class="text-center mb-6">
                        <span class="text-[17px] font-black uppercase tracking-[0.2em] text-slate-800">Receipt Summary</span>
                    </div>

                    <div class="space-y-6">
                        <!-- Receipt # -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold uppercase tracking-widest text-slate-800">Batch #</span>
                            <span class="text-sm font-black text-slate-800 tracking-tight">{{ batch.batch_no || '---' }}</span>
                        </div>

                        <div class="border-t border-dotted border-slate-700"></div>

                        <!-- Net Weight / Batch Size -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold uppercase tracking-widest text-slate-600">
                                {{ isMetricTon ? 'Batch Size' : 'Net Weight' }}
                            </span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-lg font-black text-indigo-600">{{ displayUnits }}</span>
                                <span class="text-[10px] font-black text-indigo-300 uppercase">{{ isMetricTon ? 'm³' : selectedUom }}</span>
                            </div>
                        </div>

                        <!-- Gross Amount -->
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Gross Amount</span>
                            <span class="text-xs font-black text-slate-700">₹ {{ grossAmount.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                        </div>

                        <!-- Total Tax -->
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Total Tax</span>
                            <span class="text-xs font-black text-slate-700">₹ {{ taxAmount.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                        </div>

                        <!-- <div class="border-t border-dotted border-slate-200"></div> -->

                        <!-- Adjustments -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Discount</span>
                                <span class="text-xs font-bold text-rose-500">- ₹ {{ Number(form.financials.discount_amount || 0).toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Hire Charge</span>
                                <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.transport_expenses || 0).toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Pass Amount</span>
                                <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.pass_amount || 0).toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Round Off</span>
                                <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.round_off || 0).toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-600">Adjustment</span>
                                <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.adjustment_amount || 0).toLocaleString() }}</span>
                            </div>
                        </div>
                        <div class="border-t border-dotted border-slate-700  !mt-2"></div> 
                         
                            <div class="flex items-center justify-between  !mt-3">
                                <span class="text-md font-black uppercase tracking-[0.2em] text-indigo-400">Total </span>
                                <span class="text-lg font-black text-slate-800 tracking-tighter">₹ {{ totalAmount.toLocaleString(undefined, {minimumFractionDigits: 0}) }}</span>
                            </div>
                        
                        <div class="border-t border-dotted border-slate-700 !mt-3"></div> 
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-3 flex items-center gap-3">
                    <BaseButton 
                        label="Cancel Trip" 
                        variant="outlined" 
                        severity="contrast" 
                        class="flex-1 !py-3 !text-[10px] !border-white !font-black uppercase !bg-white tracking-widest"
                        @click="emit('cancel')"
                    />
                    
                    <BaseButton 
                        label="Save Trip" 
                        variant="filled" 
                        severity="primary" 
                        class="flex-1 !py-3  !text-[10px] !font-black uppercase tracking-widest shadow-lg shadow-indigo-100"
                        :loading="form.processing"
                        @click="submit"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-tabview-nav) {
    @apply border-b border-slate-100 bg-transparent;
}

:deep(.p-tabview-nav-link) {
    @apply !text-[10px] !font-black !uppercase !tracking-widest !text-slate-400 !border-b-2 !border-transparent !bg-transparent !py-4 !px-6 transition-all;
}

:deep(.p-tabview-selected .p-tabview-nav-link) {
    @apply !text-indigo-600 !border-indigo-600 !bg-transparent;
}

:deep(.p-tabview-panels) {
    @apply !p-0;
}
</style>
