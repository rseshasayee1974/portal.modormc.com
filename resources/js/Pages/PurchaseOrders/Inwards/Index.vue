<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

// Components
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

import { 
    ArchiveBoxIcon, 
    CalendarDaysIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    TrashIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';
import PurchaseOrderPreviewDialog from '../components/PurchaseOrderPreviewDialog.vue';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const page = usePage();
const isManualWeightDisabled = computed(() => page.props.custom_settings?.orders?.manualweight == 1);
 console.log(isManualWeightDisabled);
const { isScaleConnected, captureWeight } = useWeighbridge();

const props = defineProps<{
    inwards: any[];
    purchaseOrders: any[];
    vehicles: any[];
}>();

// --- List Logic ---
const entriesPerPage = ref(30);
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const formatDate = (date: string) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
};

const previewVisible = ref(false);
const selectedOrder = ref(null);

const viewOrder = (order: any) => {
    selectedOrder.value = order;
    previewVisible.value = true;
};

// --- Form Logic ---
const showForm = ref(true);
const selectedPoId = ref(null);

const poOptions = computed(() => {
    return props.purchaseOrders.map(po => ({
        label: `${po.po_number} - ${po.vendor?.legal_name} (${formatDate(po.date_order)})`,
        value: po.id
    }));
});

const form = useForm({
    order_id: null as number | null,
    received_date: new Date().toISOString().substring(0, 10),
    inward_no: '',
    truck_id: props.vehicles?.length > 0 ? props.vehicles[0].value : null,
    truck_loaded: null as number | null,
    items: [] as any[]
});

const loadPoDetails = (poId: number | null) => {
    if (!poId) {
        form.items = [];
        return;
    }
    const po = props.purchaseOrders.find(p => p.id === poId);
    if (po) {
        form.items = po.items.map((item: any) => ({
            order_item_id: item.id,
            product_id: item.product_id,
            product_title: item.product?.title,
            ordered_qty: Number(item.product_quantity),
            received_qty_previously: Number(item.received_quantity || 0),
            uom: item.uom?.unit_code,
            received_qty: 0,
            truck_id: form.truck_id,
            truck_loaded: 0
        }));
    }
};

watch(selectedPoId, (newId) => {
    form.order_id = newId;
    loadPoDetails(newId);
});

const remainingToReceive = (item: any) => {
    return Math.max(0, item.ordered_qty - item.received_qty_previously);
};

const submitInward = () => {
    if (!form.order_id) return Swal.fire('Warning', 'Please select a Purchase Order', 'warning');
    
    // Sync truck_loaded to received_qty
    form.items.forEach(item => {
        item.received_qty = Number(item.truck_loaded) || 0;
    });

    if (!form.items.some(i => i.received_qty > 0)) return Swal.fire('Warning', 'Enter received quantity for at least one item', 'warning');
    
    const exceeding = form.items.find(i => (i.received_qty_previously + i.received_qty) > i.ordered_qty);
    if (exceeding) return Swal.fire('Error', `Received quantity for ${exceeding.product_title} exceeds ordered quantity.`, 'error');

    form.transform((data) => ({
        ...data,
        received_date: data.received_date ? new Date(data.received_date).toISOString().split('T')[0] : null
    })).post(route('inwards.store'), {
        onSuccess: () => {
             Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Recorded successfully', showConfirmButton: false, timer: 3000 });
             showForm.value = true;
             selectedPoId.value = null;
             form.reset();
        }
    });
};

const saveEmptyWeight = (inward: any, newWeight: number) => {
    if (newWeight <= 0) return;
    
    router.post(route('inwards.update-weight', inward.id), {
        truck_empty: newWeight
    }, {
        onSuccess: () => {
             Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Weight updated', showConfirmButton: false, timer: 3000 });
        }
    });
};

const deleteInward = (inward: any) => {
    Swal.fire({
        title: 'Delete Inward Record?',
        text: "This will reverse the received quantity and adjust stock balances. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete Content'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('inwards.destroy', inward.id), {
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Record Deleted', showConfirmButton: false, timer: 3000 })
            });
        }
    });
};

</script>

<template>
    <AppLayout title="Inward Registry">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6 px-4 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Integrated Quick-Record Form -->
                <div v-if="showForm" class="bg-white rounded-sm shadow-sm p-4 border border-indigo-100 overflow-hidden ring-4 ring-indigo-50/30 transition-all">
                    <div class="flex items-center gap-2 px-1">
                        <div class="w-12 h-12 rounded-sm bg-indigo-600 flex items-center justify-center shadow-sm shadow-indigo-100">
                            <ArchiveBoxIcon class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h1 class="text-md font-semibold text-slate-800 uppercase tracking-tight">Stock Inward Portal</h1>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] mt-0.5">Inventory Acquisition History</p>
                        </div>
                    </div>
                    
                    <div class="px-2 py-4 border-b border-slate-100 bg-slate-50/30">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="space-y-1.5 md:col-span-1">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Target Purchase Order</label>
                                <BaseSelect 
                                    v-model="selectedPoId" 
                                    :options="poOptions" 
                                    placeholder="Search active orders..." 
                                    optionLabel="label"
                                    optionValue="value"
                                    filter 
                                    class="w-full !rounded-sm !h-10 !bg-white"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Entry Date</label>
                                <BaseDatePicker 
                                    v-model="form.received_date" 
                                    placeholder="Arrival Date"
                                    dateFormat="yy-mm-dd"
                                    class="w-full !rounded-sm !h-10"
                                />
                            </div>
                           
                        </div>
                    </div>

                    <div v-if="form.items.length > 0" class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead class="bg-gray-100 border-b border-slate-100">
                                <tr>
                                    <th class="px-8 py-3 text-left text-[9px] font-black text-slate-500 uppercase tracking-widest">Product Details</th>
                                     <!-- <th class="px-4 py-3 text-center text-[9px] font-black text-slate-500 uppercase tracking-widest w-40"></th> -->
                                      
                                     <th class="px-4 py-3 text-center text-[9px] font-black text-slate-500 uppercase tracking-widest w-40">Procurement Status</th>
                                    <th class="px-4 py-3 text-center text-[9px] font-black text-slate-500 uppercase tracking-widest w-40">Truck</th>
                                    <th class="px-8 py-3 text-right text-[9px] font-black text-slate-500 uppercase tracking-widest w-48">Loaded Wt</th>
                                    <!-- <th class="px-8 py-3 text-right text-[9px] font-black text-slate-500 uppercase tracking-widest w-48">Receiving Qty</th> -->
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-indigo-50/10 transition-colors">
                                    <td class="px-8 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-[13px] font-bold text-slate-800 uppercase tracking-tight">{{ item.product_title }}</span>
                                            <div class="flex items-center gap-3 mt-1.5">
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Ordered: {{ item.ordered_qty }} {{ item.uom }}</span>
                                                <div class="w-1 h-1 rounded-full bg-slate-300"></div>
                                                <span class="text-[9px] text-emerald-600 font-bold uppercase">Accepted: {{ item.received_qty_previously }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div v-if="remainingToReceive(item) <= 0" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full border border-emerald-100">
                                            <CheckCircleIcon class="w-3.5 h-3.5" />
                                            <span class="text-[8px] font-black uppercase tracking-widest">Received</span>
                                        </div>
                                        <div v-else class="flex flex-col items-center gap-1">
                                            <span class="text-[8px] font-bold text-amber-500 uppercase tracking-widest">{{ remainingToReceive(item) }} {{ item.uom }} Pending</span>
                                            <div class="w-20 bg-slate-100 h-1 rounded-full overflow-hidden">
                                                <div class="bg-amber-400 h-full transition-all duration-500" :style="{ width: (item.received_qty_previously / item.ordered_qty * 100) + '%' }"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                         <div class="space-y-1.5">
                                            <BaseSelect 
                                                v-model="item.truck_id" 
                                                :options="vehicles" 
                                                placeholder="Select Truck" 
                                                optionLabel="label"
                                                optionValue="value"
                                                filter 
                                                class="w-full !rounded-sm !h-10 !bg-white"
                                            />
                                        </div>
                            
                                    </td>
                                    <td class="px-8 py-4">
                                        <div class="flex items-center ">
                                            <BaseInputNumber 
                                                v-model="item.truck_loaded" 
                                                placeholder="Inward Weight"
                                                 :disabled="page.props.custom_settings?.orders?.manualweight == 0"
                                                :max="remainingToReceive(item)"
                                                :minFractionDigits="2"
                                                class="w-28 text-right  overflow-hidden"
                                                inputClass="!text-right !h-8 !bg-white"
                                                
                                            />
                                            <button v-if="remainingToReceive(item) > 0 && page.props.custom_settings?.orders?.manualweight == 0" @click="captureWeight((w) => { item.truck_loaded = w; item.received_qty = w; })" type="button" 
                                                    :class="['p-2 rounded transition-colors border shrink-0', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                                    :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                                </button>
                                        </div>
                                    </td>
                                    <!-- <td class="px-8 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <BaseInputNumber 
                                                v-model="item.received_qty" 
                                                :disabled="remainingToReceive(item) <= 0"
                                                :max="remainingToReceive(item)"
                                                :minFractionDigits="2"
                                                class="w-28 text-right !rounded-sm overflow-hidden border border-slate-200"
                                                inputClass="!text-right !h-8 !bg-white"
                                            />
                                            
                                            <span class="text-[10px] font-black text-slate-400 w-8 uppercase">{{ item.uom }}</span>
                                        </div>
                                    </td> -->
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="form.items.length > 0" class="p-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex flex-col gap-1 w-full md:w-auto">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest font-mono">Internal Batch / Inward Ref #</label>
                            <BaseInput v-model="form.inward_no" placeholder="System generated if empty" class="!rounded-sm !h-9 border-slate-200 w-full md:w-56 text-[10px] font-mono" />
                        </div>

                        <div class="flex gap-2 w-full md:w-auto">
                            <BaseButton label="Clear Items" variant="text" severity="secondary" @click="selectedPoId = null" class="!text-xs !font-bold" />
                            <BaseButton 
                                label="Record Goods Receipt" 
                                icon="pi pi-check-circle" 
                                variant="filled" 
                                :loading="form.processing"
                                @click="submitInward"
                                class="!rounded-sm !px-8 !h-10 !font-black !text-[10px] !uppercase !tracking-widest !bg-indigo-600"
                            />
                        </div>
                    </div>
                </div>

                <!-- Registry History List -->
                <div class="space-y-4">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 px-1">
                        <div class="flex items-center gap-2">
                             <div class="w-1.5 h-6 bg-indigo-600 rounded-full"></div>
                             <h2 class="text-sm font-black text-slate-700 uppercase tracking-widest">Historical Inward Registry</h2>
                        </div>
                    </div>

                    <div class="bg-white rounded-sm shadow-sm border border-slate-200 overflow-hidden">
                        <BaseDataTable 
                            :value="inwards" 
                            v-model:filters="filters"
                            v-model:rows="entriesPerPage"
                            :rowsPerPageOptions="[30, 50, 100, 200]"
                            :globalFilterFields="['inward_no', 'order.po_number', 'product.title', 'order.vendor.legal_name']"
                            showSearch
                            showSerial
                            stripedRows
                        >
                            <Column header="Inward #" sortable field="inward_no" style="min-width: 160px" class="py-4 px-4">
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-slate-700 font-inter tracking-tight">{{ slotProps.data.inward_no }}</span>
                                        <div class="flex items-center gap-1.5 mt-1 text-[9px] text-slate-400 font-bold uppercase">
                                            <CalendarDaysIcon class="w-3 h-3" />
                                            {{ formatDate(slotProps.data.received_date) }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Ref Po" sortable field="order.po_number" style="min-width: 130px">
                                <template #body="slotProps">
                                    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-tight">
                                        {{ slotProps.data.order?.vendor?.legal_name || '--' }}
                                    </div>
                                    <button 
                                        type="button" 
                                        @click="viewOrder(slotProps.data.order)"
                                        class="inline-flex items-center text-[10px] font-black px-2.5 py-1 bg-indigo-50/50 text-indigo-700 rounded border border-indigo-100 hover:bg-indigo-100 transition-colors uppercase cursor-pointer"
                                    >
                                        {{ slotProps.data.order?.po_number }}
                                    </button>
                                </template>
                            </Column>

                            <!-- <Column header="Vendor" style="min-width: 100px">
                                <template #body="slotProps">
                                    
                                </template>
                            </Column> -->

                            <Column header="Product" sortable field="product.title" style="min-width: 140px"> 
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-slate-700 uppercase tracking-tight leading-tight">{{ slotProps.data.product?.title }}</span>
                                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">{{ slotProps.data.product?.hsn_code || 'No HSN' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Truck" sortable field="truck.registration" style="min-width: 150px">
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1.5 px-1">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="slotProps.data.truck_id ? 'bg-indigo-500' : 'bg-slate-300'"></div>
                                            <span class="text-[10px] font-black text-slate-700 uppercase tracking-tight">{{ slotProps.data.truck?.registration || 'External Vehicle' }}</span>
                                        </div>
                                        <div v-if="slotProps.data.truck?.code" class="text-[8px] text-slate-400 font-bold uppercase tracking-widest pl-4">
                                            V-REF: {{ slotProps.data.truck?.code }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Weight Data" sortable field="truck_loaded" style="min-width: 220px">
                                <template #body="slotProps">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col bg-slate-50 p-2 rounded border border-slate-100">
                                            <span class="text-[7px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Gross (Loaded)</span>
                                            <span class="text-[11px] font-black text-slate-800">{{ Number(slotProps.data.truck_loaded || 0).toLocaleString() }}</span>
                                        </div>
                                        <div class="flex flex-col bg-amber-50/30 p-2 rounded border border-amber-100 group relative">
                                             <div class="flex items-center gap-1">
                                                <span class="text-[7px] text-amber-600 font-black uppercase tracking-[0.2em] mb-1">Tare (Empty)</span>
                                                <button 
                                                    @click="saveEmptyWeight(slotProps.data, slotProps.data.truck_empty)"
                                                    class="text-amber-500 hover:text-amber-700 transition-colors p-0.5 rounded hover:bg-amber-100"
                                                    title="Update Weight"
                                                >
                                                    <i class="pi pi-external-link text-[10px]"></i>
                                                </button>
                                            </div>
                                                <input 
                                                    type="number" 
                                                    v-model="slotProps.data.truck_empty"
                                                    :disabled="page.props.custom_settings?.orders?.manualweight == 0"
                                                    class="w-full bg-transparent border-none text-[11px] font-black text-amber-800 focus:ring-0 p-0"
                                                    placeholder="0.00"
                                                    @keyup.enter="saveEmptyWeight(slotProps.data, slotProps.data.truck_empty)"
                                                />
                                                
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Price" sortable field="unit_price" style="min-width: 110px" class="text-right">
                                <template #body="slotProps">
                                    <div class="flex flex-col items-center px-1 bg-slate-50/50 py-1.5 rounded">
                                        <span class="text-[12px] font-black text-slate-500">{{ Number(slotProps.data.unit_price) }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Received" sortable field="received_qty" style="min-width: 120px" class="text-right">
                                <template #body="slotProps">
                                    <div class=" bg-emerald-50/20 py-1.5 rounded border border-emerald-100/30">
                                        <span class="text-[13px] font-black text-emerald-600">{{ Number(slotProps.data.received_qty) }} /</span>
                                        <span class="text-[12px] font-black text-slate-500">{{ Number(slotProps.data.item.product_quantity) }} </span>
                                        <span class="text-[11px] text-gray-400 font-black uppercase tracking-widest">{{'  ' + slotProps.data.uom?.unit_code }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="" style="width: 80px" class="text-right">
                                <template #body="slotProps">
                                    <div class="flex items-center justify-end gap-1">
                                        <!-- <Button icon="pi pi-print" severity="secondary" variant="text" size="small" rounded class="!text-slate-300 hover:!text-indigo-600 h-8 w-8" /> -->
                                        <Button 
                                            icon="pi pi-trash" 
                                            severity="danger" 
                                            variant="text" 
                                            size="small" 
                                            rounded 
                                            class="!text-red-400 hover:!text-red-500 h-8 w-8" 
                                            @click.stop="deleteInward(slotProps.data)"
                                        />
                                    </div>
                                </template>
                            </Column>

                            <template #expansion="{ data }">
                                <div class="mm-expansion-panel">
                                    <div class="mm-expansion-label">
                                        <ShoppingCartIcon class="w-4 h-4 text-indigo-500" />
                                        <span class="mm-expansion-title">Full Order Composition — {{ data.order?.po_number }}</span>
                                    </div>

                                    <div class="bg-white border border-slate-200 rounded-sm overflow-hidden shadow-sm">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr class="bg-slate-50 border-b border-slate-200">
                                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Product</th>
                                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Commitment</th>
                                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Fulfilled</th>
                                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Balance</th>
                                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 uppercase">
                                                <tr v-for="item in data.order?.items" :key="'exp-'+item.id" class="hover:bg-slate-50/50 transition-colors">
                                                    <td class="px-4 py-4">
                                                        <div class="flex flex-col">
                                                            <span class="text-[11px] font-bold text-slate-700 tracking-tight">{{ item.product?.title }}</span>
                                                            <code class="text-[9px] text-slate-400 uppercase">{{ item.product?.code }}</code>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 text-center">
                                                        <span class="text-[10px] font-black text-slate-500 bg-slate-100 px-2 py-1 rounded">{{ Number(item.product_quantity) }} {{ item.uom?.unit_code }}</span>
                                                    </td>
                                                    <td class="px-4 py-4 text-center">
                                                        <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded">{{ Number(item.received_quantity) }} {{ item.uom?.unit_code }}</span>
                                                    </td>
                                                    <td class="px-4 py-4 text-center">
                                                        <span class="text-[10px] font-black" :class="Number(item.product_quantity) - Number(item.received_quantity) > 0 ? 'text-amber-600 bg-amber-50 px-2 py-1 rounded' : 'text-slate-300'">
                                                            {{ Math.max(0, Number(item.product_quantity) - Number(item.received_quantity)) }} {{ item.uom?.unit_code }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 text-right">
                                                        <Tag 
                                                            :value="Number(item.received_quantity) >= Number(item.product_quantity) ? 'FULFILLED' : 'PARTIAL'" 
                                                            :severity="Number(item.received_quantity) >= Number(item.product_quantity) ? 'success' : 'warn'"
                                                            class="!text-[8px] !font-black !px-2 !py-0.5"
                                                        />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <template #empty>
                                <div class="py-24 text-center">
                                    <ArchiveBoxIcon class="w-12 h-12 text-slate-100 mx-auto mb-4" />
                                    <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Inward registry is empty</h3>
                                    <p class="text-[10px] text-slate-300 mt-1">Confirmed goods receipts will appear here.</p>
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>
                </div>
            </div>
        </div>

        <PurchaseOrderPreviewDialog v-model:visible="previewVisible" :order="selectedOrder" />
    </AppLayout>
</template>

<style scoped>

</style>
