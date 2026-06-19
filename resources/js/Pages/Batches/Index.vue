<script setup lang="ts">
import { ref, computed, watch, nextTick, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useWebSocket } from '@/Composables/useWebSocket';
import { useOfflineBatchSync } from '@/Composables/useOfflineBatchSync';
import { useBatchActions } from './useBatchActions';
import { useBatchTokenPreview } from './useBatchTokenPreview';
import { useInvoiceActions } from './useInvoiceActions';
import BatchCreateForm from './components/BatchCreateForm.vue';
import BatchEditForm from './components/BatchEditForm.vue';
import DispatchSection from './components/DispatchSection.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import { CubeIcon, ListBulletIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    batches: any[];
    workOrders: any[];
    trucks: any[];
    customers: any[];
    transporters: any[];
    sales_executives: any[];
    drivers: any[];
    taxes: any[];
    products: any[];
    loading_sites: any[];
    unloading_sites: any[];
    uoms: any[];
    statuses: { label: string; value: number }[];
    schemaWarning?: string | null;
    nextBatchNo: number;
    batchingSettings: any;
    payment_methods: any[];
    sales_ledgers: any[];
    concretePumpOptions?: any[];
}>();
const dropdownData = computed(() => ({
    trucks: props.trucks,
    transporters: props.transporters,
    drivers: props.drivers,
    sales_executives : props.sales_executives,
    taxes: props.taxes,
    uoms: props.uoms,
    unloading_sites : props.unloading_sites,
    loading_sites : props.loading_sites,
    payment_methods: props.payment_methods,
    sales_ledgers : props.sales_ledgers,
}));
const filters = ref({
    global: { value: null, matchMode: 'contains' },
    status: { value: null, matchMode: 'equals' },
    'work_order.id': { value: null, matchMode: 'equals' },
    'work_order.customer.id': { value: null, matchMode: 'equals' },
});

const dateFrom = ref<any>(null);
const dateTo = ref<any>(null);



const expandedRows     = ref<Record<number, boolean>>({});
// Our own tracking ref — independent of PrimeVue's internal expandedRows mutations
const expandedBatchId  = ref<number | null>(null);
const detailedBatches  = ref<Record<number, any>>({});
const isLoadingBatch   = ref<Record<number, boolean>>({});
const blinkingBatchId  = ref<number | null>(null);

// Fallback REST polling via Inertia reload
const fetchBatchesFallback = () => {
    router.reload({
        only: ['batches', 'nextBatchNo'],
        preserveScroll: true
    });
};

// ── Offline Sync ──────────────────────────────────────────────────────────
// Delegate all offline-batch logic to the dedicated composable.
const { localBatches, isSyncing, handleOfflineBatchAdded, syncOfflineBatches } =
    useOfflineBatchSync(props, fetchBatchesFallback);

const filteredBatches = computed(() => {
    let result = localBatches.value;
    
    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        result = result.filter(b => new Date(b.created_at) >= from);
    }
    
    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        result = result.filter(b => new Date(b.created_at) <= to);
    }
    
    return result;
});

// WebSocket event message handler
const handleWebSocketMessage = (data: any) => {
    if (data.event === 'BatchCreated' && data.batch) {
        const newBatch = data.batch;
        const exists = localBatches.value.some(b => b.id === newBatch.id);
        if (!exists) {
            localBatches.value.unshift(newBatch);
        }
    } else if (data.event === 'BatchUpdated' && data.batch) {
        const updatedBatch = data.batch;
        const index = localBatches.value.findIndex(b => b.id === updatedBatch.id);
        if (index !== -1) {
            localBatches.value[index] = updatedBatch;
            if (detailedBatches.value[updatedBatch.id]) {
                detailedBatches.value[updatedBatch.id] = updatedBatch;
            }
        }
    } else if (data.event === 'BatchDeleted' && data.id) {
        const deletedId = Number(data.id);
        localBatches.value = localBatches.value.filter(b => b.id !== deletedId);
        if (detailedBatches.value[deletedId]) {
            delete detailedBatches.value[deletedId];
        }
    }
};

// Hook up WebSocket connection with REST fallback
// useWebSocket({
//     channel: 'batches',
//     onMessage: handleWebSocketMessage,
//     fallbackPoll: fetchBatchesFallback,
//     pollIntervalMs: 15000
// });

const first = ref(0);
const rows = ref(30);
const entriesOptions = [
    { label: '30', value: 30 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

/**
 * Always fetches fresh data from batches.show so the edit form always
 * has up-to-date materials, dispatch info, and workOrder relationships.
 */
const fetchBatchDetails = async (id: number) => {
    isLoadingBatch.value[id] = true;
    try {
        const response = await axios.get(route('batches.show', id));
        
        detailedBatches.value[id] = response.data;
    } catch (e) {
        console.error('Failed to fetch batch details:', e);
    } finally {
        isLoadingBatch.value[id] = false;
    }
};

/**
 * Toggle expand: uses our own expandedBatchId ref so we are never
 * affected by PrimeVue mutating the expandedRows object internally.
 * Note: fetchBatchDetails is driven by the watch below — no direct call needed here.
 */
const toggleExpand = (data: any) => {
    const id = Number(data.id);

    if (expandedBatchId.value === id) {
        // Collapse
        expandedBatchId.value = null;
        expandedRows.value     = {};
    } else {
        // Expand — setting expandedBatchId triggers the watch which fetches details
        expandedBatchId.value = id;
        expandedRows.value    = { [id]: true };
    }
};

// Fired by PrimeVue's own row expander (not our custom button) — keep in sync
const onRowExpand = (event: any) => {
    const id = Number(event.data.id);
    expandedBatchId.value = id;
    // watch on expandedBatchId handles the fetch automatically
};

/**
 * Watch expandedBatchId and fetch details whenever a row is opened.
 * Using the watch (instead of inline calls) means any code that sets
 * expandedBatchId will automatically get fresh data for the active row —
 * including the initial toggle AND any programmatic re-expand after a save.
 */
watch(
    () => expandedBatchId.value,
    async (newId) => {
        if (newId !== null) {
            await fetchBatchDetails(newId);
        }
    }
);

const collapseExpandedRows = (batchId?: number) => {
    // console.log('Index.vue: collapseExpandedRows called with batchId:', batchId);

    // Step 1: Clear expanded state IMMEDIATELY (synchronously) — before any renders.
    // This prevents PrimeVue from seeing an expandedRows entry while
    // the data props are being rebuilt by useOfflineBatchSync.
    if (batchId) {
        delete detailedBatches.value[batchId];
    }
    expandedBatchId.value = null;
    expandedRows.value    = {};
    // console.log('Index.vue: expandedRows cleared immediately');

    // Step 2: Belt-and-suspenders — force a second clear after one tick
    // in case PrimeVue's internal expansion state tries to restore the row.
    nextTick(() => {
        expandedRows.value = {};

        // Step 3: Blink the saved row 3 times after collapsing.
        if (batchId) {
            blinkingBatchId.value = batchId;
            console.log('Index.vue: blinkingBatchId set to', blinkingBatchId.value);
            setTimeout(() => {
                blinkingBatchId.value = null;
                console.log('Index.vue: blinkingBatchId cleared');
            }, 2100); // 3 blinks × 700ms each
        }
    });
};

// Global router success hook — belt-and-suspenders fallback.
// Fires whenever an Inertia visit succeeds (including form.put / form.post).
// This catches any case where the emit('saved') chain fails to reach us.
const cleanupSuccessHook = router.on('success', (event) => {
    const url = event.detail.visit?.url?.toString() || '';
    const method = event.detail.visit?.method?.toLowerCase() || '';
    console.log('Index.vue: Inertia success hook fired. URL:', url, '| method:', method, '| expandedBatchId:', expandedBatchId.value);
    // Modified to keep the row expanded on save.
});

onUnmounted(() => {
    cleanupSuccessHook();
});

const getRowClass = (data: any) => {
    if (blinkingBatchId.value === Number(data.id)) {
        return 'batch-row-blink';
    }
    return '';
};

// ── Batch Actions ───────────────────────────────────────────────────────
// activeMenuId + toggleMenu + closeAllMenus defined first (needed by useBatchTokenPreview)
const activeMenuId = ref<number | null>(null);
const actionMenu = ref();
const activeBatch = ref<any>(null);

const toggleMenu = (event: Event, id: number) => {
    event.stopPropagation();
    activeMenuId.value = activeMenuId.value === id ? null : id;
};

const toggleActionMenu = (event: Event, batch: any) => {
    event.stopPropagation();
    activeBatch.value = batch;
    if (actionMenu.value) {
        actionMenu.value.toggle(event);
    }
};

const closeAllMenus = () => {
    activeMenuId.value = null;
    if (actionMenu.value) {
        actionMenu.value.hide();
    }
};

const { destroy, downloadPdf, retrySync, statusSeverity, statusLabel } =
    useBatchActions(props);

const refreshBatchRow = async (id: number) => {
    try {
        const response = await axios.get(route('batches.show', id));
        const updatedBatch = response.data;
        
        // 1. Update in localBatches using splice() to guarantee Vue reactivity
        const index = localBatches.value.findIndex((b: any) => b.id === id);
        if (index !== -1) {
            localBatches.value.splice(index, 1, updatedBatch);
        }
        
        // 2. Update detailedBatches — always set so BatchEditForm watcher fires
        detailedBatches.value[id] = updatedBatch;
    } catch (e) {
        console.error('Failed to refresh batch row:', e);
    }
};

const handlePreviewClose = async (batchId: number | null) => {
    if (batchId) {
        await refreshBatchRow(batchId);
    }
};

const handleBatchSaved = async (payload?: { batchId: number, type: 'batching' | 'dispatch' }) => {
    if (payload) {
        const { batchId, type } = payload;
        
        // 1. Refresh row data so both localBatches and detailedBatches are up-to-date
        await refreshBatchRow(batchId);
        
        // 2. Wait one tick for Vue to flush the reactive updates before showing the modal
        await nextTick();
        
        // 3. Automatically open print preview
        viewToken(batchId, type);
    }
};

// ── Token Preview ─────────────────────────────────────────────────────
const {
    tokenPreviewVisible,
    tokenPreviewUrl,
    iframeHeight,
    previewTitle,
    previewWidth,
    previewIframeWidth,
    viewToken,
    closeTokenPreview,
    adjustIframeHeight,
    printTokenIframe,
} = useBatchTokenPreview(closeAllMenus, handlePreviewClose);

// ── Invoice Actions ──────────────────────────────────────────────────
const {
    generateInvoiceDirect,
    printInvoiceDirect,
    downloadInvoiceDirect,
    printEInvoiceDirect,
    deleteInvoiceDirect,
    sendWhatsAppDirect,
    sendBatchEmailDirect,
} = useInvoiceActions(props);

// ── Page Settings ────────────────────────────────────────────────────────────
const page           = usePage();
const customSettings = page.props.custom_settings as any;
const hideBatchForm  = computed(() => !!customSettings?.batching?.hide_batch_form);

// ── Flash Watchers (auto-show token on create/dispatch) ───────────────────────
const lastShownBatchId = ref<number | null>(null);
watch(() => page.props.flash?.new_batch_id, (newVal) => {
    if (newVal && Number(newVal) !== lastShownBatchId.value) {
        lastShownBatchId.value = Number(newVal);
        viewToken(Number(newVal));
    }
}, { immediate: true });

const lastDispatchedBatchId = ref<number | null>(null);
watch(() => page.props.flash?.dispatched_batch_id, (newVal) => {
    if (newVal && Number(newVal) !== lastDispatchedBatchId.value) {
        lastDispatchedBatchId.value = Number(newVal);
        viewToken(Number(newVal), 'dispatch');
    }
}, { immediate: true });

// Share Batch Report States
const showShareBatchModal = ref(false);
const shareBatchExpiry = ref('7');
const shareBatchLink = ref('');
const isGeneratingBatchLink = ref(false);
const selectedShareBatch = ref<any>(null);

const openShareBatch = (batch: any) => {
    selectedShareBatch.value = batch;
    shareBatchExpiry.value = '7';
    shareBatchLink.value = '';
    showShareBatchModal.value = true;
};

const generateShareBatchLink = async () => {
    if (!selectedShareBatch.value) return;
    isGeneratingBatchLink.value = true;
    try {
        const response = await axios.post(route('batches.share', selectedShareBatch.value.id), {
            document_type: 'batch',
            document_id: selectedShareBatch.value.id,
            expiry: shareBatchExpiry.value,
        });
        
        if (response.data && response.data.url) {
            shareBatchLink.value = response.data.url;
        } else {
            Swal.fire('Error', 'Failed to generate share link.', 'error');
        }
    } catch (error: any) {
        console.error('Error sharing batch:', error);
        Swal.fire('Error', error.response?.data?.message || 'An error occurred while generating the link.', 'error');
    } finally {
        isGeneratingBatchLink.value = false;
    }
};

const copyShareBatchLink = async () => {
    try {
        await navigator.clipboard.writeText(shareBatchLink.value);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Share link copied to clipboard!',
            showConfirmButton: false,
            timer: 2000
        });
    } catch (err) {
        console.error('Failed to copy text: ', err);
    }
};

const shareBatchWhatsApp = () => {
    const text = encodeURIComponent(`Here is the link to view the batch report: ${shareBatchLink.value}`);
    window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
};

const shareBatchEmail = () => {
    const batchNo = selectedShareBatch.value?.batch_no || selectedShareBatch.value?.id || '';
    const subject = encodeURIComponent(`Batch Sheet Report #${batchNo} - Shared Link`);
    const body = encodeURIComponent(`Dear Customer,\n\nPlease find the secure link to view the Batch Sheet Report online:\n\n${shareBatchLink.value}\n\nThank you.`);
    window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
};
</script>

<template>
    <AppLayout title="Batches">
        <div class="py-2 px-4">
            <ModuleSubTopNav />

            <div class="max-w-7xl mx-auto mt-4 space-y-4">
                <div v-if="schemaWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                    {{ schemaWarning }}
                </div>

                <BatchCreateForm
                    v-if="!hideBatchForm"
                    :workOrders="workOrders"
                    :trucks="trucks"
                    :transporters="transporters"
                    :drivers="drivers"
                    :sales_executives="sales_executives"
                    :products="products"
                    :uoms="uoms"
                    :unloading_sites="unloading_sites"
                    :loading_sites="loading_sites"
                    :taxes="taxes"
                    :statuses="statuses"
                    :nextBatchNo="nextBatchNo"
                    :concretePumpOptions="concretePumpOptions"
                    @offline-batch-added="handleOfflineBatchAdded"
                />

                <hr class="border-slate-200 border-dashed" />

                <div class="bg-white shadow-xl sm:rounded-lg">

                    <BaseDataTable
                        :value="filteredBatches"
                        v-model:first="first"
                        v-model:rows="rows"
                        v-model:filters="filters"
                        v-model:expandedRows="expandedRows"
                        v-model:dateFrom="dateFrom"
                        v-model:dateTo="dateTo"
                        dataKey="id"
                        paginator
                        stripedRows
                        removableSort
                        rowHover
                        filterDisplay="menu"
                        class="cursor-pointer"
                        :globalFilterFields="['batch_no', 'work_order.order_no', 'work_order.customer.legal_name', 'work_order.mix_design.design_name']"
                        showSerial
                        heading="List Of Batches"
                        headingIcon="ListBulletIcon"
                        showSearch
                        showExport
                        exportFilename="batch-report"
                        :rowClass="getRowClass"
                        @rowExpand="onRowExpand"
                    >
                        <template #filters>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Filter by Work Order</label>
                                <BaseSelect 
                                    v-model="filters['work_order.id'].value"
                                    :options="[{order_no: 'All Orders', id: null}, ...workOrders]"
                                    optionLabel="order_no"
                                    optionValue="id"
                                    placeholder="Select Order"
                                    class="!h-9 !text-xs !rounded-lg"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Filter by Customer</label>
                                <BaseSelect 
                                    v-model="filters['work_order.customer.id'].value"
                                    :options="[{legal_name: 'All Customers', id: null}, ...customers]"
                                    optionLabel="legal_name"
                                    optionValue="id"
                                    placeholder="Select Customer"
                                    class="!h-9 !text-xs !rounded-lg"
                                    filter
                                />
                            </div>
                        </template>
                        <Column field="start_time" header="Date" sortable>
                            <template #body="slotProps">
                                <div v-if="slotProps.data.start_time" class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-700">
                                        {{ new Date(slotProps.data.start_time).toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' }).replace(/\//g, '-') }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium uppercase">
                                        {{ new Date(slotProps.data.start_time).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                </div>
                                <span v-else class="text-xs text-slate-300 italic">N/A</span>
                            </template>
                        </Column>
                        <Column field="batch_no" header="Batch" sortable>
                            <template #body="slotProps">
                                <div>
                                    <span v-if="slotProps.data.is_offline_pending" class="text-slate-500 font-inter text-sm font-semibold">
                                        B{{ slotProps.data.batch_no }}
                                    </span>
                                    <button
                                        v-else
                                        class="text-indigo-700 font-inter text-sm font-semibold hover:underline"
                                        type="button"
                                        @click.stop="toggleExpand(slotProps.data)"
                                    >
                                        B{{ slotProps.data.batch_no }}
                                    </button>
                                    <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">{{ slotProps.data.work_order?.full_number || (slotProps.data.work_order ? (slotProps.data.work_order.prefix || '') + String(slotProps.data.work_order.order_no || '').padStart(4,'0') : '-') }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="work_order.customer.legal_name" header="Customer" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-700">{{ slotProps.data.work_order?.customer?.legal_name || '-' }}</span>
                                    <span class="text-[10px] text-slate-400 font-medium uppercase">{{ slotProps.data.work_order?.site?.name || 'Main Site' }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column field="work_order.mix_design.design_name" header="Design" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-slate-800">{{ slotProps.data.work_order?.mix_design?.design_name || '-' }}</span>
                                    <span class="text-[10px] text-emerald-600 font-black tracking-tighter uppercase">{{ slotProps.data.work_order?.mix_design?.design_code || '-' }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column header="Truck">
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-700">{{ slotProps.data.dispatches?.[0]?.truck?.registration || '-' }}</span>
                            </template>
                        </Column>
                        
                        <!-- <Column header="Sales Exec">
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-700">{{ slotProps.data.dispatches?.[0]?.sales_executive?.label || '-' }}</span>
                            </template>
                        </Column> -->

                        <Column field="batch_size" header="Qty" sortable>
                            <template #body="slotProps">
                                <span class="text-xs font-bold text-slate-700">{{ slotProps.data.batch_size }} m³</span>
                            </template>
                        </Column>

                        

                        <Column field="status" header="Status" sortable>
                            <template #body="slotProps">
                                <div class="flex items-center gap-2">
                                    <template v-if="slotProps.data.is_offline_pending">
                                        <Tag value="Offline Pending" severity="warn" rounded />
                                        <i class="pi pi-spinner animate-spin text-amber-500 text-lg" v-tooltip.top="'Pending Network Sync'"></i>
                                    </template>
                                    <template v-else>
                                        <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" rounded />
                                    </template>
                                </div>
                            </template>
                        </Column>

                        <Column header="Actions" headerStyle="width: 7rem; text-align: center" bodyStyle="overflow: visible; text-align: center">
                            <template #body="slotProps">
                                <div v-if="!slotProps.data.is_offline_pending" class="flex items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex justify-center items-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none transition-all duration-200"
                                        @click.stop="toggleActionMenu($event, slotProps.data)"
                                        v-tooltip.top="'Actions'"
                                    >
                                        <i class="pi pi-ellipsis-v text-sm font-bold"></i>
                                    </button>

                                    <i v-if="slotProps.data.sync_status === 'success'" 
                                       class="pi pi-check-circle text-emerald-500 text-lg cursor-help" 
                                       v-tooltip.top="'Synced to Scheduler'"></i>
                                       
                                    <i v-else-if="slotProps.data.sync_status === 'failed'" 
                                       class="pi pi-times-circle text-rose-500 text-lg cursor-pointer hover:text-rose-600 transition-colors" 
                                       v-tooltip.top="'Sync Failed - Click to Retry'" 
                                       @click.stop="retrySync(slotProps.data.id)"></i>
                                       
                                    <i v-else-if="slotProps.data.sync_status === 'pending'" 
                                       class="pi pi-cloud-upload text-amber-500 text-lg cursor-pointer hover:text-amber-600 transition-colors" 
                                       v-tooltip.top="'Pending - Click to Post'" 
                                       @click.stop="retrySync(slotProps.data.id)"></i>
                                    <!-- Dropdown Menu -->
                                    <transition
                                        enter-active-class="transition ease-out duration-100"
                                        enter-from-class="transform opacity-0 scale-95"
                                        enter-to-class="transform opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="transform opacity-100 scale-100"
                                        leave-to-class="transform opacity-0 scale-95"
                                    >
                                        <div
                                            v-if="activeMenuId === slotProps.data.id"
                                            class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-slate-800 shadow-2xl border border-slate-200/80 dark:border-slate-700/80 z-[1000] focus:outline-none divide-y divide-slate-100 dark:divide-slate-700/50 py-1"
                                            @click.stop
                                        >
                                            <!-- Group 1: General Batch Actions -->
                                            <div class="py-1 text-left">
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="router.get(route('batches.report', slotProps.data.id)); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-eye mr-2 text-indigo-500 font-bold"></i>
                                                    Preview Batch Sheet
                                                </button>
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="downloadPdf(slotProps.data.id); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-download mr-2 text-blue-500 font-bold"></i>
                                                    Download PDF Report
                                                </button>
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="sendBatchEmailDirect(slotProps.data); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-envelope mr-2 text-sky-500 font-bold"></i>
                                                    Send Email Report
                                                </button>
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="openShareBatch(slotProps.data); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-share-alt mr-2 text-indigo-500 font-bold"></i>
                                                    Share Batch Report
                                                </button>
                                               
                                            </div>

                                            <!-- Group 2: Token Printing Actions -->
                                            <div class="py-1 text-left">
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="viewToken(slotProps.data.id, 'batching'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-print mr-2 text-amber-500 font-bold"></i>
                                                    Print Batching Token
                                                </button>
                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="viewToken(slotProps.data.id, 'dispatch'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-ticket mr-2 text-emerald-500 font-bold"></i>
                                                    Print Dispatch Token
                                                </button>
                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="viewToken(slotProps.data.id, 'delivery'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-file mr-2 text-sky-500 font-bold"></i>
                                                    Print Delivery Challan (A4)
                                                </button>
                                            </div>

                                            <!-- Group 3: Invoice & Invoicing Actions -->
                                            <div v-if="slotProps.data.dispatches?.[0]" class="py-1 text-left">
                                                <!-- If Invoice not yet generated -->
                                                <button
                                                    v-if="slotProps.data.status >= 3 && (!slotProps.data.dispatches[0].status || slotProps.data.dispatches[0].status.invoice_status !== 1)"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:text-emerald-700 transition-colors"
                                                    @click="generateInvoiceDirect(slotProps.data.dispatches[0]); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-plus-circle mr-2 text-emerald-500 font-bold"></i>
                                                    Generate Invoice
                                                </button>

                                                <!-- If Invoice is generated -->
                                                <template v-if="slotProps.data.dispatches[0].status?.invoice_status === 1 && slotProps.data.dispatches[0].status?.invoice">
                                                    <button
                                                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                        @click="printInvoiceDirect(slotProps.data.dispatches[0].status.invoice); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-print mr-2 text-indigo-500 font-bold"></i>
                                                        Print Invoice
                                                    </button>
                                                    <button
                                                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                        @click="downloadInvoiceDirect(slotProps.data.dispatches[0].status.invoice); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-download mr-2 text-blue-500 font-bold"></i>
                                                        Download Invoice PDF
                                                    </button>
                                                    <!-- If IRN E-invoice is generated -->
                                                    <button
                                                        v-if="slotProps.data.dispatches[0].status.invoice.einvoice_status === 'generated'"
                                                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 transition-colors"
                                                        @click="printEInvoiceDirect(slotProps.data.dispatches[0].status.invoice); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-check-circle mr-2 text-purple-500 font-bold"></i>
                                                        E-Invoice Print
                                                    </button>
                                                    <button
                                                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                                                        @click="deleteInvoiceDirect(slotProps.data.dispatches[0]); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                                                        Delete Invoice
                                                    </button>
                                                </template>

                                                <!-- WhatsApp (if dispatched) -->
                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-emerald-700 transition-colors"
                                                    @click="sendWhatsAppDirect(slotProps.data.dispatches[0]); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-whatsapp mr-2 text-emerald-500 font-bold"></i>
                                                    WhatsApp Send
                                                </button>
                                            </div>

                                            <!-- Group 4: Delete Batch -->
                                            <div v-if="slotProps.data.status < 3" class="py-1 text-left">
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                                                    @click="destroy(slotProps.data); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                                                    Delete Batch
                                                </button>
                                            </div>
                                        </div>
                                    </transition>
                                </div>
                                <span v-else class="text-[10px] text-slate-400 font-bold uppercase">Syncing...</span>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <div class="p-4 bg-slate-50/50 border-y border-slate-100 relative min-h-[100px] space-y-6">
                                <div v-if="isLoadingBatch[slotProps.data.id]" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="pi pi-spinner animate-spin text-indigo-600 text-2xl"></i>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Loading Details...</span>
                                    </div>
                                </div>
                                
                                <!-- 1. Batch Production Form -->
                                <div v-if="!hideBatchForm" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                    
                                    <BatchEditForm
                                        :batch="detailedBatches[slotProps.data.id]"
                                        :workOrders="workOrders"
                                        :trucks="trucks"
                                        :transporters="transporters"
                                        :drivers="drivers"
                                        :sales_executives="sales_executives"
                                        :products="products"
                                        :uoms="uoms"
                                        :statuses="statuses"
                                        :loading_sites="loading_sites"
                                        :concretePumpOptions="concretePumpOptions"
                                        @saved="handleBatchSaved"
                                        @cancel="collapseExpandedRows()"
                                    />
                                </div>

                                <!-- 2. Dispatch History (Only if exists) -->
                                <!-- <div v-if="(detailedBatches[slotProps.data.id] || slotProps.data).dispatches?.length" class="p-4 bg-white rounded-xl border border-slate-100 shadow-sm">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                                            <PaperAirplaneIcon class="w-4 h-4" />
                                        </div>
                                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-800">Batch Dispatch History</h4>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr class="border-b border-slate-50 bg-slate-50/50">
                                                    <th class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Dispatch #</th>
                                                    <th class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Ref #</th>
                                                    <th class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Truck</th>
                                                    <th class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Qty</th>
                                                    <th class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-50">
                                                <tr v-for="d in (detailedBatches[slotProps.data.id] || slotProps.data).dispatches" :key="d.id" class="hover:bg-slate-50/50 transition-colors">
                                                    <td class="px-4 py-3 text-xs font-black text-indigo-600">{{ d.prefix }}{{ d.dispatch_no }}</td>
                                                    <td class="px-4 py-3 text-xs font-bold text-slate-600">{{ d.dispatch_reference || '---' }}</td>
                                                    <td class="px-4 py-3 text-xs font-semibold text-slate-700">{{ d.truck?.registration || '---' }}</td>
                                                    <td class="px-4 py-3 text-xs font-black text-slate-800">{{ d.delivered_qty }} m³</td>
                                                    <td class="px-4 py-3">
                                                        <Tag :value="d.dispatch_status" :severity="d.dispatch_status === 'Delivered' ? 'success' : 'warn'" rounded class="!text-[9px] !font-black !px-2" />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div> -->

                                <!-- 3. Dispatch Generation/Edit Form -->
                                <div v-if="slotProps.data.status === 3 || slotProps.data.status === 4"  class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                    <DispatchSection 
                                        :batch="detailedBatches[slotProps.data.id] || slotProps.data" 
                                        :workOrder="(detailedBatches[slotProps.data.id] || slotProps.data).work_order"
                                        :dispatch="(detailedBatches[slotProps.data.id] || slotProps.data).dispatches?.[0]"
                                        :dropdownData="dropdownData"
                                        :drivers="drivers"
                                        :sales_executives="sales_executives"
                                        :settings="batchingSettings"
                                        @saved="handleBatchSaved"
                                        @cancel="collapseExpandedRows()"
                                    />
                                </div>
                            </div>
                        </template>
                    </BaseDataTable>
                </div>
            </div>
        </div>
        <Dialog
            v-model:visible="tokenPreviewVisible"
            modal
            :style="{ width: previewWidth, maxWidth: '95vw' }"
            class="token-preview-dialog"
            :pt="{
                root: { class: 'border-0 shadow-2xl rounded-2xl overflow-hidden' },
                header: { class: 'bg-slate-50 border-b border-slate-100 py-2.5 px-4 flex items-center justify-between' },
                content: { class: 'p-0 bg-slate-50' },
                footer: { class: 'bg-white border-t border-slate-100 py-2 px-4 flex justify-end gap-2' }
            }"
        >
            <template #header>
                <div class="flex items-center gap-2">
                    <i class="pi pi-print text-indigo-600 text-sm"></i>
                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest">{{ previewTitle }}</span>
                </div>
            </template>

            <div class="w-full flex justify-center bg-slate-100 py-2.5 px-1 overflow-y-auto max-h-[70vh] border-b border-slate-100">
                <iframe
                    v-if="tokenPreviewUrl"
                    :src="tokenPreviewUrl"
                    :style="{ height: iframeHeight, width: previewIframeWidth }"
                    style="border: none; background: white; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); border-radius: 6px; display: block;"
                    sandbox="allow-same-origin allow-scripts allow-popups allow-forms"
                    @load="adjustIframeHeight"
                ></iframe>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="closeTokenPreview" text severity="secondary" class="!text-[10px] !font-bold !uppercase !tracking-wider" />
                <Button label="Print Token" icon="pi pi-print" @click="printTokenIframe" severity="primary" class="!text-[10px] !font-bold !uppercase !tracking-wider !px-4" />
            </template>
        </Dialog>

 
        <Popover
            ref="actionMenu"
            class="!shadow-2xl !border !border-slate-200/80 dark:!border-slate-700/80 !rounded-xl overflow-hidden"
            style="padding: 0; width: 14rem;"
            :pt="{ root: { id: 'batch-action-menu' } }"
        >
            <div v-if="activeBatch" class="divide-y divide-slate-100 dark:divide-slate-700/50 py-1 bg-white dark:bg-slate-800 text-left">
                <!-- Group 1: General Batch Actions -->
                <div class="py-1">
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="router.get(route('batches.report', activeBatch.id)); closeAllMenus();"
                    >
                        <i class="pi pi-eye mr-2 text-indigo-500 font-bold"></i>
                        Preview Batch Sheet
                    </button>
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="downloadPdf(activeBatch.id); closeAllMenus();"
                    >
                        <i class="pi pi-download mr-2 text-blue-500 font-bold"></i>
                        Download PDF Report
                    </button>
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="sendBatchEmailDirect(activeBatch); closeAllMenus();"
                    >
                        <i class="pi pi-envelope mr-2 text-sky-500 font-bold"></i>
                        Send Email Report
                    </button>
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="openShareBatch(activeBatch); closeAllMenus();"
                    >
                        <i class="pi pi-share-alt mr-2 text-indigo-500 font-bold"></i>
                        Share Batch Report
                    </button>
                </div>

                <!-- Group 2: Token Printing Actions -->
                <div class="py-1">
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="viewToken(activeBatch.id, 'batching'); closeAllMenus();"
                    >
                        <i class="pi pi-print mr-2 text-amber-500 font-bold"></i>
                        Print Batching Token
                    </button>
                    <button
                        v-if="activeBatch.status >= 3"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="viewToken(activeBatch.id, 'dispatch'); closeAllMenus();"
                    >
                        <i class="pi pi-ticket mr-2 text-emerald-500 font-bold"></i>
                        Print Dispatch Token
                    </button>
                    <button
                        v-if="activeBatch.status >= 3"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="viewToken(activeBatch.id, 'delivery'); closeAllMenus();"
                    >
                        <i class="pi pi-file mr-2 text-sky-500 font-bold"></i>
                        Print Delivery Challan (A4)
                    </button>
                </div>

                <!-- Group 3: Invoice & Invoicing Actions -->
                <div v-if="activeBatch.dispatches?.[0]" class="py-1">
                    <!-- If Invoice not yet generated -->
                    <button
                        v-if="activeBatch.status >= 3 && (!activeBatch.dispatches[0].status || activeBatch.dispatches[0].status.invoice_status !== 1)"
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:text-emerald-700 transition-colors"
                        @click="generateInvoiceDirect(activeBatch.dispatches[0]); closeAllMenus();"
                    >
                        <i class="pi pi-plus-circle mr-2 text-emerald-500 font-bold"></i>
                        Generate Invoice
                    </button>

                    <!-- If Invoice is generated -->
                    <div v-if="activeBatch.dispatches[0].status?.invoice_status === 1 && activeBatch.dispatches[0].status?.invoice">
                        <button
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                            @click="printInvoiceDirect(activeBatch.dispatches[0].status.invoice); closeAllMenus();"
                        >
                            <i class="pi pi-print mr-2 text-indigo-500 font-bold"></i>
                            Print Invoice
                        </button>
                        <button
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                            @click="downloadInvoiceDirect(activeBatch.dispatches[0].status.invoice); closeAllMenus();"
                        >
                            <i class="pi pi-download mr-2 text-blue-500 font-bold"></i>
                            Download Invoice PDF
                        </button>
                        <!-- If IRN E-invoice is generated -->
                        <button
                            v-if="activeBatch.dispatches[0].status.invoice.einvoice_status === 'generated'"
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 transition-colors"
                            @click="printEInvoiceDirect(activeBatch.dispatches[0].status.invoice); closeAllMenus();"
                        >
                            <i class="pi pi-check-circle mr-2 text-purple-500 font-bold"></i>
                            E-Invoice Print
                        </button>
                        <button
                            class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                            @click="deleteInvoiceDirect(activeBatch.dispatches[0]); closeAllMenus();"
                        >
                            <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                            Delete Invoice
                        </button>
                    </div>

                    <!-- WhatsApp (if dispatched) -->
                    <button
                        v-if="activeBatch.status >= 3"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-emerald-700 transition-colors"
                        @click="sendWhatsAppDirect(activeBatch.dispatches[0]); closeAllMenus();"
                    >
                        <i class="pi pi-whatsapp mr-2 text-emerald-500 font-bold"></i>
                        WhatsApp Send
                    </button>
                </div>

                <!-- Group 4: Delete Batch -->
                <div v-if="activeBatch.status < 3" class="py-1">
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                        @click="destroy(activeBatch); closeAllMenus();"
                    >
                        <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                        Delete Batch
                    </button>
                </div>
            </div>
        </Popover>
                <!-- Premium Share Batch Dialog -->
        <Dialog v-model:visible="showShareBatchModal" modal header="Share Batch Report" :style="{ width: '450px' }" class="premium-dialog">
            <div class="p-2">
                <p class="text-xs text-slate-500 mb-4">
                    Generate a secure, read-only link to share this batch sheet report with your customer.
                </p>

                <!-- Expiry Options -->
                <div class="mb-5">
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2">Link Expiry</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button 
                            v-for="opt in [
                                { label: '1 Day', value: '1' },
                                { label: '7 Days', value: '7' },
                                { label: '30 Days', value: '30' },
                                { label: 'Never', value: '0' }
                            ]" 
                            :key="opt.value"
                            type="button"
                            @click="shareBatchExpiry = opt.value"
                            class="px-2 py-2 text-xs font-semibold rounded-lg border text-center transition-all"
                            :class="[
                                shareBatchExpiry === opt.value
                                ? 'bg-indigo-50 border-indigo-500 text-indigo-700'
                                : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <!-- Action Button or Generated Link Display -->
                <div v-if="!shareBatchLink" class="mt-6 flex justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" text severity="secondary" @click="showShareBatchModal = false" class="!text-xs font-bold uppercase" />
                    <Button 
                        label="Generate Link" 
                        icon="pi pi-link" 
                        severity="primary" 
                        @click="generateShareBatchLink" 
                        :loading="isGeneratingBatchLink"
                        class="!text-xs font-bold uppercase bg-indigo-600 hover:bg-indigo-700 text-white border-0" 
                    />
                </div>

                <div v-else class="mt-6 space-y-4 animate-in fade-in duration-200">
                    <!-- Link Textbox -->
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Secure Share Link</label>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                readonly 
                                :value="shareBatchLink" 
                                class="flex-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-600 dark:text-slate-300 font-mono focus:outline-none"
                            />
                            <button 
                                @click="copyShareBatchLink"
                                class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold flex items-center gap-1 transition-all"
                            >
                                <i class="pi pi-copy"></i>
                                Copy
                            </button>
                        </div>
                    </div>

                    <!-- Social Share Action Buttons -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                        <button 
                            @click="shareBatchWhatsApp"
                            class="flex-1 py-2 px-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-sm"
                        >
                            <i class="pi pi-whatsapp text-sm"></i>
                            WhatsApp
                        </button>
                        <button 
                            @click="shareBatchEmail"
                            class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-sm"
                        >
                            <i class="pi pi-envelope text-sm"></i>
                            Email
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>



<style scoped>
@keyframes batch-blink {
    0%          { background-color: transparent;  box-shadow: none; }
    14%         { background-color: #bbf7d0 !important; box-shadow: inset 0 0 0 2px #16a34a; }
    28%         { background-color: transparent;  box-shadow: none; }
    42%         { background-color: #bbf7d0 !important; box-shadow: inset 0 0 0 2px #16a34a; }
    56%         { background-color: transparent;  box-shadow: none; }
    70%         { background-color: #bbf7d0 !important; box-shadow: inset 0 0 0 2px #16a34a; }
    100%        { background-color: transparent;  box-shadow: none; }
}

:deep(tr.batch-row-blink > td) {
    animation: batch-blink 2.1s ease-in-out forwards !important;
    transition: none !important;
}
</style>
