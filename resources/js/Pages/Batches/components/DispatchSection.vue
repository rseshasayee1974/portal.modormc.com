<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DispatchWeightsForm from './DispatchWeightsForm.vue';
import DispatchTransportForm from './DispatchTransportForm.vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import { PaperAirplaneIcon } from '@heroicons/vue/24/outline';

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
}>();

const form = useForm({
    work_order_id: props.workOrder?.id,
    batch_id: props.batch?.id,
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
        empty_weight_truck: 0,
        loaded_weight_truck: 0,
        empty_weight_time_load: null,
        loaded_weight_time_load: null,
        empty_weight_unload: 0,
        loaded_weight_unload: 0,
        empty_weight_time_unload: null,
        loaded_weight_time_unload: null,
        round_off: 0
    },

    financials: {
        load_units: props.batch?.batch_size || 0,
        load_rate: 0,
        load_tax_id: null,
        unload_units: props.batch?.batch_size || 0,
        unload_rate: 0,
        unload_tax_id: null,
        transport_units: props.batch?.batch_size || 0,
        transport_rate: 0,
        transport_tax_id: null,
        pass_amount: 0,
        discount_amount: 0,
        transport_expenses: 0,
        adjustment_amount: 0,
        round_off: 0
    },

    status: {
        dispatch_status: 'Draft',
        is_closed: false,
        driver_salary_status: 0,
        cleaner_salary_status: 0,
        is_load_tax_inclusive: false,
        is_unload_tax_inclusive: false,
        transport_km: 0,
        invoice_number: '',
        invoice_date: null,
        transport_invoice_number: '',
        transport_date: null
    }
});

const submit = () => {
    form.post(route('dispatches.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success logic
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
            
            <Button label="Initialize Dispatch" icon="pi pi-send" severity="info" size="small" class="!rounded-lg" @click="submit" :loading="form.processing" />
        </div>

        <div class="p-1 space-y-1">
           
            
           

            <DispatchWeightsForm 
                v-model="form" 
                :uoms="dropdownData.uoms"
                :taxes="dropdownData.taxes"
                :loading_sites="dropdownData.loading_sites"
                :unloading_sites="dropdownData.unloading_sites"
                :errors="form.errors" 
            />
 <hr class="border-slate-100" />
             <DispatchTransportForm 
                v-model="form" 
                :trucks="dropdownData.trucks" 
                :transporters="dropdownData.transporters"
                :personnel="dropdownData.personnel"
                :errors="form.errors"
            />
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
