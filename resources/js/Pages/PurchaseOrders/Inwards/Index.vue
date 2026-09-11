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
import Dialog from 'primevue/dialog';
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
    ArrowDownTrayIcon,
    CameraIcon,
    EyeIcon
} from '@heroicons/vue/24/outline';
import PurchaseOrderPreviewDialog from '../components/PurchaseOrderPreviewDialog.vue';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const page = usePage();
const isManualWeightDisabled = computed(() => page.props.custom_settings?.batching?.manual_weight == 1);
const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();

const imageModalVisible = ref(false);
const imageModalSrc = ref('');
const imageModalTitle = ref('');

const openImageModal = (src: string, title: string = 'Weight Snapshot') => {
    imageModalSrc.value = src;
    imageModalTitle.value = title;
    imageModalVisible.value = true;
};

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

const dateFrom = ref(null);
const dateTo = ref(null);

const filteredInwards = computed(() => {
    let result = props.inwards;
    
    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        from.setHours(0, 0, 0, 0);
        result = result.filter(i => new Date(i.received_date) >= from);
    }
    
    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        result = result.filter(i => new Date(i.received_date) <= to);
    }
    
    return result;
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
console.log(props.purchaseOrders);
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
    truck_id: null as number | null,
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
            truck_loaded: 0,
            loaded_weight_photo: null
        }));
    }
};

const captureInwardLoadedWeight = async (item: any) => {
    await captureWeight(async (w: number) => {
        item.truck_loaded = w;
        item.received_qty = w;

        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_1 || customSettings.batching.camera_url || customSettings.batching.camera_url_2;
            try {
                const snap = await captureCameraSnap(cameraUrl);
                item.loaded_weight_photo = snap;
            } catch (err) {
                console.error('Inward camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    });
};

const takeInwardSnapOnly = async (item: any) => {
    const customSettings: any = page.props.custom_settings || {};
    const cameraUrl = customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_2;
    if (!cameraUrl) {
        Swal.fire('Warning', 'No camera URL configured in settings', 'warning');
        return;
    }
    try {
        const snap = await captureCameraSnap(cameraUrl);
        item.loaded_weight_photo = snap;
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Camera snapshot captured',
            showConfirmButton: false,
            timer: 1500
        });
    } catch (err) {
        console.error('Camera snapshot failed:', err);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Camera snapshot failed',
            showConfirmButton: false,
            timer: 1500
        });
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
             Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Recorded successfully', showConfirmButton: false, timer: 1500 });
             showForm.value = true;
             selectedPoId.value = null;
             form.reset();
        }
    });
};

const saveEmptyWeight = (inward: any, newWeight: number, photo?: string | null) => {
    if (newWeight <= 0) return;
    
    router.post(route('inwards.update-weight', inward.id), {
        truck_empty: newWeight,
        empty_weight_photo: photo || inward._empty_snap || null
    }, {
        onSuccess: () => {
             Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Weight updated', showConfirmButton: false, timer: 1500 });
        }
    });
};

const captureTareWeight = async (inward: any) => {
    await captureWeight(async (w: number) => {
        inward.truck_empty = w;

        let snap: string | null = null;
        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_2 || customSettings.batching.camera_url || customSettings.batching.camera_url_1;
            try {
                snap = await captureCameraSnap(cameraUrl);
                inward._empty_snap = snap;
            } catch (err) {
                console.error('Tare camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Tare weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        saveEmptyWeight(inward, w, snap);
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
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Record Deleted', showConfirmButton: false, timer: 1500 })
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
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <BaseInputNumber 
                                                    v-model="item.truck_loaded" 
                                                    placeholder="Inward Weight"
                                                    :disabled="page.props.custom_settings?.batching?.manual_weight == 0"
                                                    :max="remainingToReceive(item)"
                                                    :minFractionDigits="2"
                                                    class="w-28 text-right overflow-hidden !rounded-sm border border-slate-200"
                                                    inputClass="!text-right !h-8 !bg-white font-mono font-bold"
                                                />
                                                <button v-if="remainingToReceive(item) > 0" 
                                                        @click="captureInwardLoadedWeight(item)" 
                                                        type="button" 
                                                        :class="['p-2 rounded transition-colors border shrink-0 flex flex-col items-center gap-0.5 shadow-xs', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                                        :title="isScaleConnected ? 'Capture Current Weight & Camera Snap' : 'Connect Weighbridge & Capture'">
                                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                                    <span v-if="page.props.custom_settings?.batching?.camera == 1" class="text-[7px] font-black uppercase tracking-widest leading-none">Snap</span>
                                                </button>
                                                <button v-if="page.props.custom_settings?.batching?.camera == 1" 
                                                        @click="takeInwardSnapOnly(item)" 
                                                        type="button" 
                                                        class="p-2 rounded text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 border border-indigo-200 transition-colors shrink-0 shadow-xs" 
                                                        title="Capture / Re-take Camera Snapshot only">
                                                    <CameraIcon class="w-4 h-4" />
                                                </button>
                                            </div>

                                            <div v-if="item.loaded_weight_photo" class="relative group w-32 h-16 rounded-md overflow-hidden border border-slate-200 shadow-xs bg-slate-100">
                                                <img :src="item.loaded_weight_photo" class="w-full h-full object-cover cursor-pointer" @click="openImageModal(item.loaded_weight_photo, 'Inward Weight Snap')" />
                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1.5">
                                                    <button @click="openImageModal(item.loaded_weight_photo, 'Inward Weight Snap')" type="button" class="p-1 bg-white/80 hover:bg-white text-slate-800 rounded text-xs" title="View Full Image">
                                                        <EyeIcon class="w-3 h-3" />
                                                    </button>
                                                    <button @click="item.loaded_weight_photo = null" type="button" class="p-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs" title="Remove Snap">
                                                        <TrashIcon class="w-3 h-3" />
                                                    </button>
                                                </div>
                                                <span class="absolute bottom-0.5 right-0.5 bg-black/60 text-white text-[7px] font-bold px-1 rounded">SNAP</span>
                                            </div>
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
                            :value="filteredInwards" 
                            v-model:filters="filters"
                            v-model:rows="entriesPerPage"
                            v-model:dateFrom="dateFrom"
                            v-model:dateTo="dateTo"
                            :rowsPerPageOptions="[30, 50, 100, 200]"
                            :globalFilterFields="['inward_no', 'order.po_number', 'product.title', 'order.vendor.legal_name']"
                            showSearch
                            showSerial
                            stripedRows
                            heading="Stock Inward Registry"
                            headingIcon="ArchiveBoxIcon"
                            showExport
                            exportFilename="stock-inward-report"
                        >
                            <template #toolbar>
                                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50 rounded-lg border border-indigo-100">
                                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ filteredInwards.length }} arrivals recorded</span>
                                </div>
                            </template>
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

                            <Column header="Weight Data" sortable field="truck_loaded" style="min-width: 250px">
                                <template #body="slotProps">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col bg-slate-50 p-2 rounded border border-slate-100">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[7px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Gross (Loaded)</span>
                                                <button 
                                                    v-if="slotProps.data.loaded_weight_image?.url" 
                                                    @click="openImageModal(slotProps.data.loaded_weight_image.url, 'Gross Weight Snap — ' + slotProps.data.inward_no)"
                                                    class="text-indigo-600 hover:text-indigo-800 p-0.5 rounded hover:bg-indigo-50 transition-colors"
                                                    title="View Gross Weight Snapshot"
                                                >
                                                    <CameraIcon class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <span class="text-[11px] font-black text-slate-800">{{ Number(slotProps.data.truck_loaded || 0).toLocaleString() }}</span>
                                            <div v-if="slotProps.data.loaded_weight_image?.url" class="mt-1">
                                                <img 
                                                    :src="slotProps.data.loaded_weight_image.url" 
                                                    @click="openImageModal(slotProps.data.loaded_weight_image.url, 'Gross Weight Snap — ' + slotProps.data.inward_no)"
                                                    class="w-12 h-8 object-cover rounded border border-slate-200 cursor-pointer hover:opacity-80 transition-opacity shadow-xs" 
                                                    alt="Gross Snap"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex flex-col bg-amber-50/30 p-2 rounded border border-amber-100 group relative">
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="text-[7px] text-amber-600 font-black uppercase tracking-[0.2em] mb-1">Tare (Empty)</span>
                                                <div class="flex items-center gap-1">
                                                    <button 
                                                        v-if="slotProps.data.empty_weight_image?.url" 
                                                        @click="openImageModal(slotProps.data.empty_weight_image.url, 'Tare Weight Snap — ' + slotProps.data.inward_no)"
                                                        class="text-amber-700 hover:text-amber-900 p-0.5 rounded hover:bg-amber-100 transition-colors"
                                                        title="View Tare Weight Snapshot"
                                                    >
                                                        <CameraIcon class="w-3.5 h-3.5" />
                                                    </button>
                                                    <button 
                                                        @click="captureTareWeight(slotProps.data)"
                                                        :class="['p-0.5 rounded transition-colors', isScaleConnected ? 'text-emerald-600 hover:bg-emerald-100' : 'text-amber-600 hover:bg-amber-100']"
                                                        :title="isScaleConnected ? 'Capture Tare Weight & Snap' : 'Connect Weighbridge & Capture'"
                                                    >
                                                        <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                                                    </button>
                                                    <button 
                                                        @click="saveEmptyWeight(slotProps.data, slotProps.data.truck_empty)"
                                                        class="text-amber-500 hover:text-amber-700 transition-colors p-0.5 rounded hover:bg-amber-100"
                                                        title="Save Tare Weight"
                                                    >
                                                        <i class="pi pi-check text-[10px]"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <input 
                                                type="number" 
                                                v-model="slotProps.data.truck_empty"
                                                :disabled="page.props.custom_settings?.batching?.manual_weight == 0"
                                                class="w-full bg-transparent border-none text-[11px] font-black text-amber-800 focus:ring-0 p-0"
                                                placeholder="0.00"
                                                @keyup.enter="saveEmptyWeight(slotProps.data, slotProps.data.truck_empty)"
                                            />
                                            <div v-if="slotProps.data.empty_weight_image?.url" class="mt-1">
                                                <img 
                                                    :src="slotProps.data.empty_weight_image.url" 
                                                    @click="openImageModal(slotProps.data.empty_weight_image.url, 'Tare Weight Snap — ' + slotProps.data.inward_no)"
                                                    class="w-12 h-8 object-cover rounded border border-amber-200 cursor-pointer hover:opacity-80 transition-opacity shadow-xs" 
                                                    alt="Tare Snap"
                                                />
                                            </div>
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
                                        <!-- :disabled="slotProps.data.received_qty > 0" -->
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

        <Dialog v-model:visible="imageModalVisible" modal :header="imageModalTitle" :style="{ width: '560px', maxWidth: '95vw' }" class="p-fluid rounded-2xl overflow-hidden shadow-2xl border-0">
            <div class="p-3 flex flex-col items-center justify-center bg-slate-900/5 rounded-xl">
                <img :src="imageModalSrc" class="w-full max-h-[70vh] object-contain rounded-lg border border-slate-200 shadow-md bg-white" alt="Weight Snapshot" />
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>

</style>
