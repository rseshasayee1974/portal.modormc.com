<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useWebSocket } from '@/Composables/useWebSocket';
import { useOfflineBatchSync } from '@/Composables/useOfflineBatchSync';
import { useBatchActions } from './useBatchActions';
import { useBatchTokenPreview } from './useBatchTokenPreview';
import { useInvoiceActions } from './useInvoiceActions';
import BatchCreateForm from './components/BatchCreateForm.vue';
import BatchEditForm from './components/BatchEditForm.vue';
import DispatchSection from './components/DispatchSection.vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import { CubeIcon, ListBulletIcon, PaperAirplaneIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

declare const route: any;

const props = withDefaults(defineProps<{
    batches?: any[];
    salesOrders?: any[];
    trucks?: any[];
    customers?: any[];
    transporters?: any[];
    drivers?: any[];
    operators?: any[];
    sales_executives?: any[];
    taxes?: any[];
    products?: any[];
    loading_sites?: any[];
    unloading_sites?: any[];
    uoms?: any[];
    statuses?: { label: string; value: number }[];
    schemaWarning?: string | null;
    nextBatchNo?: number;
    batchingSettings?: any;
    payment_methods?: any[];
    sales_ledgers?: any[];
    concretePumpOptions?: any[];
}>(), {
    batches: () => [],
    salesOrders: () => [],
    trucks: () => [],
    customers: () => [],
    transporters: () => [],
    drivers: () => [],
    operators: () => [],
    sales_executives: () => [],
    taxes: () => [],
    products: () => [],
    loading_sites: () => [],
    unloading_sites: () => [],
    uoms: () => [],
    statuses: () => [],
    nextBatchNo: 1,
    batchingSettings: () => ({}),
    payment_methods: () => [],
    sales_ledgers: () => [],
    concretePumpOptions: () => [],
});
const dropdownData = computed(() => ({
    trucks: props.trucks,
    transporters: props.transporters,
    drivers: props.drivers,
    operators: props.operators || [],
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
    'sales_order.id': { value: null, matchMode: 'equals' },
    'sales_order.customer.id': { value: null, matchMode: 'equals' },
});

const { isAdmin, isSuperAdmin, can } = usePermissions();

// console.log('props.drivers',props);

const dateFrom = ref<any>(null);
const dateTo = ref<any>(null);

const formatAmount = (val: any) => {
    if (val === null || val === undefined || val === '') return null;
    const num = Number(val);
    if (isNaN(num) || num <= 0) return null;
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};



const expandedRows     = ref<Record<number, boolean>>({});
// Our own tracking ref — independent of PrimeVue's internal expandedRows mutations
const expandedBatchId  = ref<number | null>(null);
const detailedBatches  = ref<Record<number, any>>({});
const isLoadingBatch   = ref<Record<number, boolean>>({});
const blinkingBatchId  = ref<number | null>(null);
const activeTabsPerBatch = ref<Record<number, number>>({});
const getBatchActiveTab = (batchId: number) => activeTabsPerBatch.value[batchId] ?? 0;
const setBatchActiveTab = (batchId: number, tabIndex: number) => {
    activeTabsPerBatch.value[batchId] = tabIndex;
};

const page = usePage();
const isRefreshing = ref(false);

// Fallback REST polling via Inertia reload
const fetchBatchesFallback = () => {
    router.reload();
};

const refreshBatches = async () => {
    isRefreshing.value = true;
    if (expandedBatchId.value) {
        await refreshBatchRow(expandedBatchId.value);
    }
    router.reload({
        only: ['batches', 'nextBatchNo', 'salesOrders'],
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

// ── Automatic Data Watchers & Window Focus Listener ───────────────────────

// 1. Watch server props updates (e.g. from background sync, pagination, or store events)
// watch(
//     () => props.batches,
//     async () => {
//         if (expandedBatchId.value) {
//             await refreshBatchRow(expandedBatchId.value);
//         }
//     },
//     { deep: true }
// );

// 2. Watch flash notifications or newly created batch ID
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.new_batch_id || flash?.status) {
            refreshBatches();
        }
    },
    { deep: true }
);

// 3. Auto-refresh when tab/window regains focus or visibility
// const handleVisibilityChange = () => {
//     if (document.visibilityState === 'visible') {
//         refreshBatches();
//     }
// };

// onMounted(() => {
//     window.addEventListener('focus', refreshBatches);
//     document.addEventListener('visibilitychange', handleVisibilityChange);
// });

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
 * has up-to-date materials, dispatch info, and salesOrder relationships.
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
const toggleExpand = (data: any, targetTab?: number) => {
    const id = Number(data.id);

    if (expandedBatchId.value === id) {
        if (targetTab !== undefined && getBatchActiveTab(id) !== targetTab) {
            // Row is already expanded, switch directly to the requested tab
            setBatchActiveTab(id, targetTab);
            return;
        }
        // Collapse
        expandedBatchId.value = null;
        expandedRows.value     = {};
    } else {
        if (targetTab !== undefined) {
            setBatchActiveTab(id, targetTab);
        }
        // Expand — setting expandedBatchId triggers the watch which fetches details
        expandedBatchId.value = id;
        expandedRows.value    = { [id]: true };
    }
};

const onStatusClick = (data: any) => {
    // If status is Dispatched (3), Completed (4), or Cancelled (5), directly open 2. Dispatch & Invoicing (tab 1)
    if (canAccessDispatchTab(data)) {
        toggleExpand(data, 1);
    } else {
        toggleExpand(data, 0);
    }
};

// Fired by PrimeVue's own row expander (not our custom button) — keep in sync
const onRowExpand = (event: any) => {
    const id = Number(event.data.id);
    expandedBatchId.value = id;
    // watch on expandedBatchId handles the fetch automatically
};

const onRowClick = (event: any) => {
    if (!event?.data) return;
    const target = event.originalEvent?.target as HTMLElement;
    if (target && (target.closest('button') || target.closest('a') || target.closest('.p-row-toggler') || target.closest('.p-datatable-row-expansion'))) {
        return;
    }
    toggleExpand(event.data);
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

const cleanupSuccessHook = router.on('success', (event) => {
    const url = event.detail.visit?.url?.toString() || '';
    const method = event.detail.visit?.method?.toLowerCase() || '';
    console.log('Index.vue: Inertia success hook fired. URL:', url, '| method:', method, '| expandedBatchId:', expandedBatchId.value);
    // Modified to keep the row expanded on save.
    collapseExpandedRows();
});

onUnmounted(() => {
    cleanupSuccessHook();
});

const isBatchCancelled = (data: any): boolean => {
    if (!data) return false;
    return Number(data.status) === 5 || data.dispatches?.[0]?.dispatch_status === 'Cancelled';
};

const canAccessDispatchTab = (batch: any, detailedBatch?: any): boolean => {
    const s1 = Number(batch?.status);
    const s2 = Number(detailedBatch?.status);
    if ([3, 4, 5].includes(s1) || [3, 4, 5].includes(s2)) return true;
    if (isBatchCancelled(batch) || isBatchCancelled(detailedBatch)) return true;
    if (batch?.dispatches?.length || detailedBatch?.dispatches?.length || batch?.dispatch || detailedBatch?.dispatch) return true;
    return false;
};

const getRowClass = (data: any) => {
    if (blinkingBatchId.value === Number(data.id)) {
        return 'batch-row-blink';
    }
    if (isBatchCancelled(data)) {
        return 'batch-row-cancelled';
    }
    return '';
};

// ── Batch Actions ───────────────────────────────────────────────────────
// activeMenuId + toggleMenu + closeAllMenus defined first (needed by useBatchTokenPreview)
const activeMenuId = ref<number | null>(null);
const actionMenu = ref();
const activeBatchId = ref<number | null>(null);
// Always read from localBatches so it reflects the latest refreshed data
const activeBatch = ref<any>(null);

const toggleMenu = (event: Event, id: number) => {
    event.stopPropagation();
    activeMenuId.value = activeMenuId.value === id ? null : id;
};

const toggleActionMenu = (event: Event, batch: any) => {
    event.stopPropagation();
    activeBatch.value = batch;
    activeBatchId.value = batch?.id ?? null;
    if (actionMenu.value) {
        actionMenu.value.toggle(event);
    }
};

const getBatchInvoice = (batch: any) => {
    if (!batch) return null;
    if (batch.dispatches?.[0]?.status?.invoice) {
        return batch.dispatches[0].status.invoice;
    }
    if (batch.dispatches?.[0]?.invoice) {
        return batch.dispatches[0].invoice;
    }
    if (batch.invoice_id) {
        return {
            id: batch.invoice_id,
            encrypted_id: batch.invoice_id,
            full_number: batch.invoice_number,
            invoice_number: batch.invoice_number,
            eway_bill_no: batch.eway_bill_no,
            has_eway_bill: batch.has_eway_bill,
            einvoice_irn: batch.einvoice_irn,
            einvoice_status: batch.einvoice_status,
        };
    }
    return null;
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
    // console.log(`[Index.vue] refreshBatchRow called for batchId: ${id}`);
    try {
        const response = await axios.get(route('batches.show', id));
        const updatedBatch = response.data;
        // console.log('[Index.vue] Received updated batch data from DB:', updatedBatch);
        
        // 1. Update in localBatches using splice() to guarantee Vue reactivity
        const index = localBatches.value.findIndex((b: any) => b.id === id);
        if (index !== -1) {
            const current = localBatches.value[index];
            const merged = {
                ...current,
                ...updatedBatch,
                load_total_amount: updatedBatch.load_total_amount 
                    ?? updatedBatch.dispatches?.[0]?.load_total_amount 
                    ?? updatedBatch.dispatch?.load_total_amount 
                    ?? current.load_total_amount,
                invoice_number: updatedBatch.invoice_number 
                    ?? updatedBatch.dispatches?.[0]?.status?.invoice_number 
                    ?? updatedBatch.dispatches?.[0]?.status?.invoice?.full_number 
                    ?? updatedBatch.dispatch?.status?.invoice_number 
                    ?? current.invoice_number,
                has_invoice: !!(updatedBatch.has_invoice 
                    || updatedBatch.dispatches?.[0]?.status?.invoice_id 
                    || updatedBatch.dispatch?.status?.invoice_id 
                    || current.has_invoice),
                eway_bill_no: updatedBatch.eway_bill_no 
                    ?? updatedBatch.dispatches?.[0]?.status?.invoice?.eway_bill_no 
                    ?? updatedBatch.dispatch?.status?.invoice?.eway_bill_no 
                    ?? current.eway_bill_no,
                has_eway_bill: !!(updatedBatch.has_eway_bill 
                    || updatedBatch.eway_bill_no 
                    || updatedBatch.dispatches?.[0]?.status?.invoice?.eway_bill_no 
                    || current.has_eway_bill),
            };
            localBatches.value.splice(index, 1, merged);
            if (activeBatch.value && (activeBatch.value.id === id || String(activeBatch.value.id) === String(id))) {
                activeBatch.value = merged;
            }
        }
        
        // 2. Update detailedBatches with new object copy so reactive watchers trigger cleanly
        detailedBatches.value = {
            ...detailedBatches.value,
            [id]: updatedBatch
        };
        // console.log(`[Index.vue] Updated detailedBatches[${id}] reactively.`);
    } catch (e) {
        console.error('Failed to refresh batch row:', e);
    }
};

const handlePreviewClose = async (batchId: number | null) => {
    if (batchId) {
        await refreshBatchRow(batchId);
    }
};
const handleBatchCreated = () => {
    router.reload({ 
        only: ['batches', 'nextBatchNo', 'salesOrders'],
    });
};
const handleBatchSaved = async (payload?: { batchId: number, type: 'batching' | 'dispatch' }) => {
    // console.log('[Index.vue] handleBatchSaved triggered with payload:', payload);
    if (payload?.batchId) {
        await refreshBatchRow(payload.batchId);
    }
};

const batchFormKey = ref(0);

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
} = useBatchTokenPreview({
    closeAllMenus,
    onClose: (batchId, reason) => {
        // This replaces your old emits + onPreviewClose
        if (batchId) refreshBatchRow(batchId);
        
        // Reset the BatchCreateForm by incrementing its key
        batchFormKey.value++;

        if (reason === 'print' || reason === 'close') {
            router.reload({ 
                only: ['batches', 'nextBatchNo'], 
            });
        }
    }
});
// ── Invoice Actions ──────────────────────────────────────────────────
const {
    generateInvoiceDirect,
    generateEInvoiceDirect,
    generateEwayBillDirect,
    printInvoiceDirect,
    printOriginalInvoiceDirect,
    printDuplicateInvoiceDirect,
    downloadInvoiceDirect,
    printEInvoiceDirect,
    deleteInvoiceDirect,
    sendWhatsAppDirect,
    sendBatchEmailDirect,
} = useInvoiceActions(props);

// ── Cancel Dispatch & Batch Action ──────────────────────────────────────────
const showCancelDispatchModal = ref(false);
const cancellingBatch = ref<any>(null);
const cancellationNotes = ref('');
const isSubmittingCancel = ref(false);

const cancellationWordCount = computed(() => {
    const text = cancellationNotes.value.trim().replace(/\s+/g, ' ');
    if (!text) return 0;
    return text.split(' ').filter(Boolean).length;
});

const isCancellationValid = computed(() => {
    return cancellationWordCount.value >= 5;
});

const openCancelDispatchModal = (batch: any) => {
    cancellingBatch.value = batch;
    cancellationNotes.value = '';
    showCancelDispatchModal.value = true;
};

const submitCancelDispatch = async () => {
    if (!isCancellationValid.value || isSubmittingCancel.value) return;

    const dispatch = cancellingBatch.value?.dispatches?.[0] || cancellingBatch.value?.dispatch;
    if (!dispatch?.id) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No dispatch found for this batch.' });
        return;
    }

    isSubmittingCancel.value = true;
    try {
        const response = await axios.post(route('dispatches.cancel', dispatch.id), {
            notes: cancellationNotes.value
        });

        showCancelDispatchModal.value = false;
        Swal.fire({
            icon: 'success',
            title: 'Cancelled Successfully',
            text: response.data.message || 'Dispatch and batch have been cancelled.',
            timer: 3500,
            showConfirmButton: false,
        });

        if (cancellingBatch.value?.id) {
            await refreshBatchRow(cancellingBatch.value.id);
        }
        router.reload({ only: ['batches'] });
    } catch (error: any) {
        const msg = error.response?.data?.message || error.response?.data?.error || error.message || 'Failed to cancel dispatch.';
        Swal.fire({ icon: 'error', title: 'Cancellation Failed', text: msg });
    } finally {
        isSubmittingCancel.value = false;
    }
};

// ── Page Settings ────────────────────────────────────────────────────────────
const customSettings = page.props.custom_settings as any;
const hideBatchForm  = computed(() => !!customSettings?.batching?.hide_batch_form);

// const lastShownBatchId = ref<number | null>(null);
// watch(() => page.props.flash?.new_batch_id, (newVal) => {
//     if (newVal && Number(newVal) !== lastShownBatchId.value) {
//         lastShownBatchId.value = Number(newVal);
//         viewToken(Number(newVal));
//     }
// }, { immediate: true });


// ── Sync to Scheduler Action ──────────────────────────────────────────
const isSyncingBatch = ref<Record<number, boolean>>({});

const syncToScheduler = async (batchId: number) => {
    isSyncingBatch.value[batchId] = true;
    try {
        const res = await axios.post(route('batches.sync', batchId));
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: res.data?.message || 'Synced to scheduler successfully',
            timer: 2000,
            showConfirmButton: false,
        });
        await refreshBatchRow(batchId);
    } catch (e: any) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: e.response?.data?.message || 'Failed to sync to scheduler',
            timer: 3000,
            showConfirmButton: false,
        });
    } finally {
        isSyncingBatch.value[batchId] = false;
    }
};

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

const handleInvoiceGenerated = (payload?: any) => {
    if (payload?.batchId) {
        refreshBatchRow(payload.batchId);
    }
    router.reload({ 
        only: ['batches', 'nextBatchNo'],
        preserveScroll: true,
        preserveState: true,
    });
};

const isRefreshingTable = ref(false);

const handleRefreshTable = () => {
    isRefreshingTable.value = true;

    if (typeof syncOfflineBatches === 'function') {
        syncOfflineBatches();
    }

    const refreshPromises: Promise<any>[] = [];
    if (expandedBatchId.value) {
        refreshPromises.push(fetchBatchDetails(expandedBatchId.value));
    }

    router.reload({
        preserveScroll: true,
        preserveState: true,
        onFinish: async () => {
            if (refreshPromises.length > 0) {
                await Promise.all(refreshPromises);
            }
            isRefreshingTable.value = false;
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Table updated successfully',
                showConfirmButton: false,
                timer: 1500,
            });
        },
        onError: () => {
            isRefreshingTable.value = false;
        }
    });
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
                    :key="batchFormKey"
                    v-if="!hideBatchForm"
                    :salesOrders="salesOrders"
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
                    @created="handleBatchCreated"
                    @offline-batch-added="handleOfflineBatchAdded"
                />

                <hr class="border-slate-200 border-dashed" />

                <div class="bg-white shadow-xl sm:rounded-lg">

                    <BaseDataTable
                        :value="filteredBatches"
                        :loading="isRefreshing"
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
                        :globalFilterFields="['batch_no', 'sales_order.order_no', 'sales_order.full_number', 'customer_name', 'site_name', 'mix_design_name', 'truck_registration', 'invoice_number', 'sales_order.customer.legal_name', 'sales_order.mix_design.design_name']"
                        showSerial
                        heading="List Of Batches"
                        headingIcon="ListBulletIcon"
                        showSearch
                        showExport
                        exportFilename="batch-report"
                        :rowClass="getRowClass"
                        @rowExpand="onRowExpand"
                        @row-click="onRowClick"
                    >
                        <template #toolbar>
                            <button
                                type="button"
                                @click="handleRefreshTable"
                                :disabled="isRefreshingTable"
                                class="inline-flex items-center gap-1.5 px-3 py-2 h-10 border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.98] text-slate-700 hover:text-indigo-600 rounded-lg shadow-sm text-xs font-bold tracking-tight transition-all disabled:opacity-60 cursor-pointer"
                                title="Refresh table data"
                            >
                                <ArrowPathIcon :class="['w-4 h-4 transition-transform duration-500', isRefreshingTable ? 'animate-spin text-indigo-600' : 'text-slate-500']" />
                                <span class="hidden sm:inline">Refresh</span>
                            </button>
                        </template>

                        <template #filters>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Filter by Sales Order</label>
                                <BaseSelect 
                                    v-model="filters['sales_order.id'].value"
                                    :options="[{order_no: 'All Orders', id: null}, ...salesOrders]"
                                    optionLabel="order_no"
                                    optionValue="id"
                                    placeholder="Select Order"
                                    class="!h-9 !text-xs !rounded-lg"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Filter by Customer</label>
                                <BaseSelect 
                                    v-model="filters['sales_order.customer.id'].value"
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
                                    <div class="flex items-center gap-1.5">
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
                                        <span v-if="isBatchCancelled(slotProps.data)" class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-700 border border-rose-200 px-1.5 py-0.5 rounded shadow-xs">
                                            <i class="pi pi-times-circle text-[9px]"></i> Cancelled
                                        </span>
                                    </div>
                                    <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">{{ slotProps.data.sales_order?.full_number || (slotProps.data.sales_order ? (slotProps.data.sales_order.prefix || '') + String(slotProps.data.sales_order.order_no || '').padStart(4,'0') : '-') }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="customer_name" class="max-w-[200px]" header="Customer" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col min-w-0">
                                    <span class="text-xs font-bold text-slate-700 truncate" :title="slotProps.data.dispatches?.[0]?.customer?.legal_name || slotProps.data.customer_name || slotProps.data.sales_order?.customer?.legal_name">
                                        {{ slotProps.data.dispatches?.[0]?.customer?.legal_name || slotProps.data.customer_name || slotProps.data.sales_order?.customer?.legal_name || '-' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium uppercase truncate" :title="slotProps.data.dispatches?.[0]?.site?.name || slotProps.data.site_name || slotProps.data.sales_order?.site?.name || 'Main Site'">
                                        {{ slotProps.data.dispatches?.[0]?.site?.name || slotProps.data.site_name || slotProps.data.sales_order?.site?.name || 'Main Site' }}
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column field="mix_design_name" class="max-w-[220px]" header="Design" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col min-w-0">
                                    <span 
                                        class="text-xs font-bold text-slate-800 truncate leading-snug"
                                        :title="slotProps.data.dispatches?.[0]?.mix_design?.design_name || slotProps.data.mix_design_name || '-'"
                                    >
                                        {{ slotProps.data.dispatches?.[0]?.mix_design?.design_name || slotProps.data.mix_design_name || '-' }}
                                    </span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span 
                                            v-if="slotProps.data.dispatches?.[0]?.mix_design?.design_code"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100/90 text-slate-600 font-mono text-[10px] font-semibold tracking-wider border border-slate-200/80"
                                        >
                                            {{ slotProps.data.dispatches?.[0]?.mix_design?.design_code || '-' }}
                                        </span>
                                        <span v-else class="text-[10px] text-slate-400 font-medium">-</span>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column field="truck_registration" header="Truck" sortable>
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-700">
                                    {{ slotProps.data.dispatches?.[0]?.truck?.registration || slotProps.data.truck_registration || '-' }}
                                </span>
                            </template>
                        </Column>
                        
                        <Column field="batch_size" header="Batch Size" sortable>
                            <template #body="slotProps">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-xs font-bold text-slate-800">
                                        {{ slotProps.data.batch_size ?? '-' }}
                                    </span>
                                    <span v-if="slotProps.data.batch_size" class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        CBM
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column field="invoice_number" header="Invoice No" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col min-w-0"> 
                                    <span 
                                        class="text-xs font-black tracking-tight"
                                        :class="formatAmount(slotProps.data.load_total_amount ?? slotProps.data.dispatches?.[0]?.load_total_amount ?? slotProps.data.dispatch?.load_total_amount ?? slotProps.data.dispatches?.[0]?.status?.invoice?.total_amount) ? 'text-slate-800' : 'text-slate-300 font-medium italic'"
                                    >
                                        {{ formatAmount(slotProps.data.load_total_amount ?? slotProps.data.dispatches?.[0]?.load_total_amount ?? slotProps.data.dispatch?.load_total_amount ?? slotProps.data.dispatches?.[0]?.status?.invoice?.total_amount) || '-' }}
                                    </span>
                                    <div 
                                        v-if="slotProps.data.invoice_number || slotProps.data.dispatches?.[0]?.status?.invoice_number || slotProps.data.dispatches?.[0]?.status?.invoice?.full_number" 
                                        class="flex items-center gap-1 mt-0.5"
                                        :title="'Invoice #' + (slotProps.data.invoice_number || slotProps.data.dispatches?.[0]?.status?.invoice_number || slotProps.data.dispatches?.[0]?.status?.invoice?.full_number)"
                                    >
                                        <i class="pi pi-receipt text-[9px] text-indigo-500"></i>
                                        <span class="text-[10px] font-black text-indigo-600 uppercase tracking-wider truncate">
                                            {{ slotProps.data.invoice_number || slotProps.data.dispatches?.[0]?.status?.invoice_number || slotProps.data.dispatches?.[0]?.status?.invoice?.full_number }}
                                        </span>
                                    </div>
                                    <span v-else class="text-[10px] text-slate-400 font-medium uppercase tracking-tight">
                                        -
                                    </span>
                                </div>
                            </template>
                        </Column>

                        
                        <Column header="Actions" headerStyle="width: 7rem; text-align: center" bodyStyle="overflow: visible; text-align: center">
                            <template #body="slotProps">
                                <div v-if="!slotProps.data.is_offline_pending" class="flex items-center justify-center gap-2">
                                    <button
                                        v-if="!isBatchCancelled(slotProps.data)"
                                        type="button"
                                        class="inline-flex justify-center items-center w-8 h-8 rounded-full text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-700 focus:outline-none transition-all duration-200 cursor-pointer"
                                        @click.stop="toggleActionMenu($event, slotProps.data)"
                                        v-tooltip.top="'Actions'"
                                    >
                                        <i class="pi pi-ellipsis-v text-sm font-bold pointer-events-none"></i>
                                    </button>

                                    <!-- Status Menu -->
                                    <div class="flex items-center gap-2">
                                        <template v-if="slotProps.data.is_offline_pending">
                                            <Tag value="Offline Pending" severity="warn" rounded />
                                            <i class="pi pi-spinner animate-spin text-amber-500 text-lg" v-tooltip.top="'Pending Network Sync'"></i>
                                        </template>
                                        <template v-else-if="isBatchCancelled(slotProps.data)">
                                            <Tag 
                                                value="Cancelled" 
                                                severity="danger" 
                                                rounded 
                                                class="!bg-rose-600 !text-white font-black text-[10px] px-2.5 py-0.5 shadow-xs uppercase tracking-wider select-none cursor-pointer" 
                                                v-tooltip.top="slotProps.data.dispatches?.[0]?.cancelled_notes ? ('Cancelled: ' + slotProps.data.dispatches[0].cancelled_notes) : 'Dispatch & Batch Cancelled'"
                                                @click.stop="onStatusClick(slotProps.data)"
                                            />
                                        </template>
                                        <template v-else>
                                            <!-- E-Invoice Generated -->
                                            <Tag 
                                                v-if="slotProps.data.has_einvoice || slotProps.data.einvoice_irn || slotProps.data.einvoice_status === 'generated' || slotProps.data.dispatches?.[0]?.status?.invoice?.einvoice_irn || slotProps.data.dispatches?.[0]?.status?.invoice?.einvoice_status === 'generated'"
                                                value="E" 
                                                severity="help" 
                                                rounded
                                                class="cursor-pointer transition-all hover:scale-105 active:scale-95 select-none hover:shadow-sm !bg-purple-600 !text-white font-black"
                                                v-tooltip.top="slotProps.data.invoice_number ? ('E-Invoiced (#' + slotProps.data.invoice_number + ') - Click to open') : 'E-Invoiced - Click to open'"
                                                @click.stop="onStatusClick(slotProps.data)"
                                            />
                                            <!-- Standard Invoice Generated -->
                                            <Tag 
                                                v-else-if="slotProps.data.has_invoice || slotProps.data.invoice_id || slotProps.data.dispatches?.[0]?.status?.invoice_id || slotProps.data.dispatches?.[0]?.status?.invoice_status === 1"
                                                value="I" 
                                                severity="success" 
                                                rounded
                                                class="cursor-pointer transition-all hover:scale-105 active:scale-95 select-none hover:shadow-sm !bg-emerald-600 !text-white font-black"
                                                v-tooltip.top="slotProps.data.invoice_number ? ('Invoiced (#' + slotProps.data.invoice_number + ') - Click to open') : 'Invoiced - Click to open Dispatch & Invoicing'"
                                                @click.stop="onStatusClick(slotProps.data)"
                                            />
                                            <!-- E-Way Bill Generated Tag -->
                                            <Tag 
                                                v-if="slotProps.data.eway_bill_no || slotProps.data.has_eway_bill || slotProps.data.dispatches?.[0]?.status?.invoice?.eway_bill_no"
                                                value="W" 
                                                rounded
                                                class="cursor-pointer transition-all hover:scale-105 active:scale-95 select-none hover:shadow-sm !bg-teal-600 !text-white font-black"
                                                v-tooltip.top="'E-Way Bill: #' + (slotProps.data.eway_bill_no || slotProps.data.dispatches?.[0]?.status?.invoice?.eway_bill_no)"
                                                @click.stop="onStatusClick(slotProps.data)"
                                            />
                                            <!-- Other Statuses (Dispatched, Batching, Pending, etc.) -->
                                            <Tag 
                                                v-if="!slotProps.data.has_invoice && !slotProps.data.invoice_id && !slotProps.data.dispatches?.[0]?.status?.invoice_id && slotProps.data.dispatches?.[0]?.status?.invoice_status !== 1"
                                                :value="statusLabel(slotProps.data.status, slotProps.data)?.charAt(0)" 
                                                :severity="statusSeverity(slotProps.data.status, slotProps.data)" 
                                                rounded 
                                                :class="[
                                                    'cursor-pointer transition-all hover:scale-105 active:scale-95 select-none hover:shadow-sm font-bold',
                                                    slotProps.data.status === 3 ? '!bg-cyan-600 !text-white' : ''
                                                ]"
                                                v-tooltip.top="(slotProps.data.status === 3 || slotProps.data.status === 4) ? 'Dispatched (Pre-Invoice) - Click to open' : 'Click to open Production & Materials'"
                                                @click.stop="onStatusClick(slotProps.data)"
                                            />
                                            <Tag v-if="slotProps.data.is_verified" value="Verified" severity="success" rounded class="!bg-emerald-500/10 !text-emerald-600 !border-emerald-500/20" />
                                        </template>
                                    </div>
                                    <transition
                                        enter-active-class="transition ease-out duration-100"
                                        enter-from-class="transform opacity-0 scale-95"
                                        enter-to-class="transform opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="transform opacity-100 scale-100"
                                        leave-to-class="transform opacity-0 scale-95"
                                    >
                                        <div
                                            v-if="activeMenuId === slotProps.data.id && !isBatchCancelled(slotProps.data)"
                                            class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-slate-800 shadow-2xl border border-slate-200/80 dark:border-slate-700/80 z-[1000] focus:outline-none divide-y divide-slate-100 dark:divide-slate-700/50 py-1"
                                            @click.stop
                                        >
                                            <div v-if="slotProps.data.sync_status" class="py-1 text-left">
                                                <button
                                                    v-if="slotProps.data.sync_status === 'success'"
                                                    type="button"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-default"
                                                >
                                                    <i class="pi pi-check-circle mr-2 text-emerald-500 text-sm"></i>
                                                    Synced to Scheduler
                                                </button>
                                                <button
                                                    v-else-if="slotProps.data.sync_status === 'failed'"
                                                    type="button"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                                                    @click="retrySync(slotProps.data.id); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-times-circle mr-2 text-rose-500 text-sm"></i>
                                                    Sync Failed - Retry
                                                </button>
                                                <button
                                                    v-else-if="slotProps.data.sync_status === 'pending'"
                                                    type="button"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                                                    @click="retrySync(slotProps.data.id); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-cloud-upload mr-2 text-amber-500 text-sm"></i>
                                                    Pending - Click to Post
                                                </button>
                                            </div>

                                            <div class="py-1 text-left">
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="router.get(route('batches.report', slotProps.data.encrypted_id || slotProps.data.id)); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-eye mr-2 text-indigo-500 font-bold"></i>
                                                    Preview Batch Sheet
                                                </button>
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="downloadPdf(slotProps.data.encrypted_id || slotProps.data.id); activeMenuId = null;"
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

                                            <div class="py-1 text-left">
                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="viewToken(slotProps.data.encrypted_id || slotProps.data.id, 'batching'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-print mr-2 text-amber-500 font-bold"></i>
                                                    Print Batching Token
                                                </button>
                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="viewToken(slotProps.data.encrypted_id || slotProps.data.id, 'dispatch'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-ticket mr-2 text-emerald-500 font-bold"></i>
                                                    Print Dispatch Token
                                                </button>
                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    @click="viewToken(slotProps.data.encrypted_id || slotProps.data.id, 'delivery'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-file mr-2 text-sky-500 font-bold"></i>
                                                    Print Delivery Challan (A4)
                                                </button>
                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-rose-600 dark:hover:rose-400 transition-colors"
                                                    @click="viewToken(slotProps.data.encrypted_id || slotProps.data.id, 'gate-pass'); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-id-card mr-2 text-rose-500 font-bold"></i>
                                                    Print Gate Pass
                                                </button>
                                            </div>

                                            <div v-if="slotProps.data.dispatches?.[0]" class="py-1 text-left">
                                                <button
                                                    v-if="slotProps.data.status >= 3 && Number(slotProps.data.dispatches[0].load_rate) > 0 && Number(slotProps.data.dispatches[0].delivered_qty || slotProps.data.dispatches[0].load_units || 0) > 0 && slotProps.data.dispatches[0].uom_id && (!slotProps.data.dispatches[0].status || slotProps.data.dispatches[0].status.invoice_status !== 1) && !isBatchCancelled(slotProps.data)"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:text-emerald-700 transition-colors"
                                                    @click="generateInvoiceDirect(slotProps.data.dispatches[0]); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-plus-circle mr-2 text-emerald-500 font-bold"></i>
                                                    Generate Invoice
                                                </button>

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
                                                    <button
                                                        v-if="!slotProps.data.dispatches[0].status.invoice.einvoice_irn && slotProps.data.dispatches[0].status.invoice.einvoice_status !== 'generated' && !isBatchCancelled(slotProps.data)"
                                                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/20 transition-colors"
                                                        @click="generateEInvoiceDirect(slotProps.data.dispatches[0].status.invoice); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-bolt mr-2 text-purple-500 font-bold"></i>
                                                        Generate E-Invoice
                                                    </button>
                                                    <button
                                                        v-if="slotProps.data.dispatches[0].status.invoice.einvoice_status === 'generated' || slotProps.data.dispatches[0].status.invoice.einvoice_irn"
                                                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 transition-colors"
                                                        @click="printEInvoiceDirect(slotProps.data.dispatches[0].status.invoice); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-check-circle mr-2 text-purple-500 font-bold"></i>
                                                        E-Invoice Print
                                                    </button>
                                                    <button
                                                        v-if="!isBatchCancelled(slotProps.data)"
                                                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                                                        @click="deleteInvoiceDirect(slotProps.data.dispatches[0]); activeMenuId = null;"
                                                    >
                                                        <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                                                        Delete Invoice
                                                    </button>
                                                </template>

                                                <button
                                                    v-if="slotProps.data.status >= 3"
                                                    class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-emerald-700 transition-colors"
                                                    @click="sendWhatsAppDirect(slotProps.data.dispatches[0]); activeMenuId = null;"
                                                >
                                                    <i class="pi pi-whatsapp mr-2 text-emerald-500 font-bold"></i>
                                                    WhatsApp Send
                                                </button>   
                                            </div>

                                            <div v-if="Number(slotProps.data.status) < 3 && !isBatchCancelled(slotProps.data)" class="py-1 text-left">
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
                            <div class=" bg-slate-50/50 border-y border-slate-100 relative min-h-[100px] ">
                                <div v-if="isLoadingBatch[slotProps.data.id]" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="pi pi-spinner animate-spin text-indigo-600 text-2xl"></i>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Loading Details...</span>
                                    </div>
                                </div>
                                
                                <!-- Unified Tabbed Layout for Batch Edit & Dispatch -->
                                <div class="relative bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                    <TabView 
                                        :activeIndex="getBatchActiveTab(slotProps.data.id)" 
                                        @update:activeIndex="(val) => setBatchActiveTab(slotProps.data.id, val)" 
                                        class="expanded-batch-tabview"
                                    >
                                        <TabPanel>
                                            <template #header>
                                                <div :class="[
                                                    'flex items-center gap-2.5 px-4 py-2.5 rounded-xl transition-all duration-200 text-xs font-bold uppercase tracking-wider',
                                                    getBatchActiveTab(slotProps.data.id) === 0
                                                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/25 ring-1 ring-indigo-500'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-800'
                                                ]">
                                                    <CubeIcon :class="['w-4 h-4 transition-colors', getBatchActiveTab(slotProps.data.id) === 0 ? 'text-white' : 'text-slate-500']" />
                                                    <span>1. Production & Materials</span>
                                                </div>
                                            </template>
                                            <div class=" bg-slate-50/20">
                                                <BatchEditForm
                                                    v-if="!hideBatchForm && detailedBatches[slotProps.data.id]"
                                                    :batch="detailedBatches[slotProps.data.id]"
                                                    :salesOrders="salesOrders"
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
                                        </TabPanel>

                                        <TabPanel :disabled="!canAccessDispatchTab(slotProps.data, detailedBatches[slotProps.data.id])">
                                            <template #header>
                                                <div :class="[
                                                    'flex items-center gap-2.5 px-4 py-2.5 rounded-xl transition-all duration-200 text-xs font-bold uppercase tracking-wider',
                                                    !canAccessDispatchTab(slotProps.data, detailedBatches[slotProps.data.id])
                                                        ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400'
                                                        : getBatchActiveTab(slotProps.data.id) === 1
                                                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/25 ring-1 ring-indigo-500'
                                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-800'
                                                ]">
                                                    <PaperAirplaneIcon :class="['w-4 h-4 transition-colors', getBatchActiveTab(slotProps.data.id) === 1 ? 'text-white' : 'text-slate-500']" />
                                                    <span>2. Dispatch & Invoicing</span>
                                                </div>
                                            </template>
                                            <div class=" bg-slate-50/20">
                                                <DispatchSection 
                                                    v-if="canAccessDispatchTab(slotProps.data, detailedBatches[slotProps.data.id]) && detailedBatches[slotProps.data.id]"
                                                    :key="'dispatch-' + slotProps.data.id + '-' + (detailedBatches[slotProps.data.id].dispatches?.[0]?.id || detailedBatches[slotProps.data.id].dispatch?.id || 'new')"
                                                    :batch="detailedBatches[slotProps.data.id]" 
                                                    :salesOrder="detailedBatches[slotProps.data.id].sales_order || detailedBatches[slotProps.data.id].salesOrder || slotProps.data.sales_order"
                                                    :dispatch="detailedBatches[slotProps.data.id].dispatches?.[0] || detailedBatches[slotProps.data.id].dispatch"
                                                    :dropdownData="dropdownData"
                                                    :drivers="drivers"
                                                    :operators="operators"
                                                    :sales_executives="sales_executives"
                                                    :settings="batchingSettings"
                                                    :onSaved="handleBatchSaved"
                                                    @tripSaved="handleBatchSaved"
                                                    @generateInvoice="handleInvoiceGenerated"
                                                    @generateEInvoice="handleInvoiceGenerated"
                                                    @deleteInvoice="handleInvoiceGenerated"
                                                    @cancel="collapseExpandedRows()"
                                                />
                                                <div v-else class="text-center py-8 text-slate-400 font-medium">
                                                    Dispatch details will be available once the batch is dispatched.
                                                </div>
                                            </div>
                                        </TabPanel>
                                    </TabView>
                                    <span class="absolute top-0 right-0 z-10 px-3 py-2 text-[11px] font-black text-slate-600 tracking-widest select-none pointer-events-none">Batch # : {{ slotProps.data.batch_no }}</span>
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
                @hide="closeTokenPreview"

            :style="{ width: previewWidth, maxWidth: '95vw' }"
            class="token-preview-dialog"
            :pt="{
                        mask: { class: 'backdrop-blur-sm bg-slate-900/30' },

                root: { class: 'border-0 shadow-2xl rounded-2xl overflow-hidden ' },
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

            <div class="w-full flex justify-center bg-slate-100 py-2.5 px-1 overflow-x-hidden overflow-y-auto max-h-[75vh] border-b border-slate-100">
                <iframe
                    v-if="tokenPreviewUrl"
                    :src="tokenPreviewUrl"
                    :style="{ height: iframeHeight, width: previewIframeWidth, maxWidth: '100%' }"
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
            class="z-50 !shadow-2xl !border !border-slate-200/80 dark:!border-slate-700/80 !rounded-xl overflow-hidden"
            style="padding: 0; min-width: 14rem;"
            :pt="{ root: { id: 'batch-action-menu' } }"
        >
            <div v-if="activeBatch" class="divide-y divide-slate-100 dark:divide-slate-700/50 py-1 bg-white dark:bg-slate-800 text-left">
                <!-- Cancelled Notice (No other actions shown) -->
                <div v-if="isBatchCancelled(activeBatch)" class="p-3.5 space-y-2 text-left bg-rose-50/70">
                    <div class="flex items-center gap-2 text-rose-700 font-bold text-xs">
                        <i class="pi pi-times-circle text-rose-600 text-base"></i>
                        <span>Dispatch &amp; Batch Cancelled</span>
                    </div>
                    <p class="text-[11px] text-rose-600 font-medium leading-snug">
                        This dispatch is cancelled. All other actions are disabled.
                    </p>
                    <div v-if="activeBatch.dispatches?.[0]?.cancelled_notes" class="text-[10px] text-slate-700 bg-white/90 p-2.5 rounded border border-rose-200 italic">
                        <strong class="text-rose-900 not-italic font-semibold">Notes:</strong> {{ activeBatch.dispatches[0].cancelled_notes }}
                    </div>
                </div>

                <template v-else>
                    <!-- Group: Sync Actions -->
                <div v-if="activeBatch.sync_status" class="py-1 text-left">
                    <button
                        v-if="activeBatch.sync_status === 'success' || activeBatch.sync_status === 1 || activeBatch.sync_status === '1'"
                        type="button"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-default"
                    >
                        <i class="pi pi-check-circle mr-2 text-emerald-500 text-sm"></i>
                        Synced to Scheduler
                    </button>
                    <button
                        v-else-if="activeBatch.sync_status === 'failed'"
                        type="button"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                        :disabled="isSyncingBatch[activeBatch.id]"
                        @click="syncToScheduler(activeBatch.id)"
                    >
                        <i v-if="isSyncingBatch[activeBatch.id]" class="pi pi-spinner animate-spin mr-2 text-rose-500 text-sm"></i>
                        <i v-else class="pi pi-times-circle mr-2 text-rose-500 text-sm"></i>
                        Sync Failed - Retry
                    </button>
                    <button
                        v-else-if="activeBatch.sync_status === 'pending'"
                        type="button"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                        :disabled="isSyncingBatch[activeBatch.id]"
                        @click="syncToScheduler(activeBatch.id)"
                    >
                        <i v-if="isSyncingBatch[activeBatch.id]" class="pi pi-spinner animate-spin mr-2 text-amber-500 text-sm"></i>
                        <i v-else class="pi pi-cloud-upload mr-2 text-amber-500 text-sm"></i>
                        Pending - Click to Post
                    </button>
                </div>

                <!-- Group 1: General Batch Actions -->
                <div class="py-1">
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="router.get(route('batches.report', activeBatch.encrypted_id || activeBatch.id)); closeAllMenus();"
                    >
                        <i class="pi pi-eye mr-2 text-indigo-500 font-bold"></i>
                        Preview Batch Sheet
                    </button>
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="downloadPdf(activeBatch.encrypted_id || activeBatch.id); closeAllMenus();"
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
                        @click="viewToken(activeBatch.encrypted_id || activeBatch.id, 'batching'); closeAllMenus();"
                    >
                        <i class="pi pi-print mr-2 text-amber-500 font-bold"></i>
                        Print Batching Token
                    </button>
                    <button
                        v-if="activeBatch.status >= 3"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="viewToken(activeBatch.encrypted_id || activeBatch.id, 'dispatch'); closeAllMenus();"
                    >
                        <i class="pi pi-ticket mr-2 text-emerald-500 font-bold"></i>
                        Print Dispatch Token
                    </button>
                    <button
                        v-if="activeBatch.status >= 3"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="viewToken(activeBatch.encrypted_id || activeBatch.id, 'delivery'); closeAllMenus();"
                    >
                        <i class="pi pi-file mr-2 text-sky-500 font-bold"></i>
                        Print Delivery Challan (A4)
                    </button>
                    <button
                        v-if="activeBatch.status >= 3"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                        @click="viewToken(activeBatch.encrypted_id || activeBatch.id, 'gate-pass'); closeAllMenus();"
                    >
                        <i class="pi pi-id-card mr-2 text-rose-500 font-bold"></i>
                        Print Gate Pass
                    </button>
                </div>

                <!-- Group 3: Invoice & Invoicing Actions -->
                <div v-if="activeBatch.dispatches?.[0] || activeBatch.status >= 3" class="py-1">
                    <!-- If Invoice not yet generated -->
                    <button
                        v-if="!activeBatch.has_invoice && !activeBatch.invoice_id && activeBatch.status >= 3 && activeBatch.dispatches?.[0]"
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:text-emerald-700 transition-colors cursor-pointer"
                        @click="generateInvoiceDirect(activeBatch.dispatches[0]); closeAllMenus();"
                    >
                        <i class="pi pi-plus-circle mr-2 text-emerald-500 font-bold"></i>
                        Generate Invoice 
                    </button>   

                    <!-- If Invoice is generated -->
                    <template v-if="activeBatch.has_invoice || activeBatch.invoice_id || (activeBatch.dispatches?.[0]?.status?.invoice_status === 1 && activeBatch.dispatches?.[0]?.status?.invoice)">
                        <button
                            v-if="getBatchInvoice(activeBatch)"
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer"
                            @click="printInvoiceDirect(getBatchInvoice(activeBatch)); closeAllMenus();"
                        >
                            <i class="pi pi-print mr-2 text-indigo-500 font-bold"></i>
                            Print Invoice
                        </button>
                        <button
                            v-if="getBatchInvoice(activeBatch)"
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer"
                            @click="downloadInvoiceDirect(getBatchInvoice(activeBatch)); closeAllMenus();"
                        >
                            <i class="pi pi-download mr-2 text-blue-500 font-bold"></i>
                            Download Invoice PDF
                        </button>
                        <!-- Generate E-Invoice (if not yet generated) -->
                        <button
                            v-if="getBatchInvoice(activeBatch) && !activeBatch.has_einvoice && !activeBatch.einvoice_irn && !getBatchInvoice(activeBatch)?.einvoice_irn"
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/20 transition-colors cursor-pointer"
                            @click="generateEInvoiceDirect(getBatchInvoice(activeBatch)); closeAllMenus();"
                        >
                            <i class="pi pi-bolt mr-2 text-purple-500 font-bold"></i>
                            Generate E-Invoice
                        </button>
                        <!-- If IRN E-invoice is generated -->
                        <button
                            v-if="activeBatch.has_einvoice || activeBatch.einvoice_irn || getBatchInvoice(activeBatch)?.einvoice_irn"
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 transition-colors cursor-pointer"
                            @click="printEInvoiceDirect(getBatchInvoice(activeBatch)); closeAllMenus();"
                        >
                            <i class="pi pi-check-circle mr-2 text-purple-500 font-bold"></i>
                            E-Invoice Print
                        </button>
                        <button
                            v-if="activeBatch.dispatches?.[0] && !isBatchCancelled(activeBatch)"
                            class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors cursor-pointer"
                            @click="deleteInvoiceDirect(activeBatch.dispatches[0]); closeAllMenus();"
                        >
                            <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                            Delete Invoice
                        </button>
                    </template>

                    <!-- Standalone E-Way Bill (Always visible once batch is dispatched, status >= 3) -->
                    <template v-if="activeBatch.status >= 3">
                        <button
                            v-if="!activeBatch.eway_bill_no && !activeBatch.dispatches?.[0]?.status?.invoice?.eway_bill_no && !isBatchCancelled(slotProps.data)"
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-950/20 transition-colors cursor-pointer"
                            @click="generateEwayBillDirect(activeBatch); closeAllMenus();"
                        >
                            <i class="pi pi-send mr-2 text-teal-500 font-bold"></i>
                            Generate E-Way Bill
                        </button>
                        <div
                            v-else
                            class="flex w-full items-center px-4 py-2 text-xs font-semibold text-teal-700 dark:text-teal-300 bg-teal-50/50 dark:bg-teal-950/20 select-none"
                            v-tooltip.top="'E-Way Bill Active'"
                        >
                            <i class="pi pi-check-circle mr-2 text-teal-500 font-bold"></i>
                            EWB #{{ activeBatch.eway_bill_no || activeBatch.dispatches?.[0]?.status?.invoice?.eway_bill_no }}
                        </div>
                    </template>

                    <!-- WhatsApp (if dispatched) -->
                    <button
                        v-if="activeBatch.status >= 3 && activeBatch.dispatches?.[0]"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-emerald-700 transition-colors cursor-pointer"
                        @click="sendWhatsAppDirect(activeBatch.dispatches[0]); closeAllMenus();"
                    >
                        <i class="pi pi-whatsapp mr-2 text-emerald-500 font-bold"></i>
                        WhatsApp Send
                    </button>

                    <!-- Cancel Dispatch Action (Temporarily Hidden) -->
                    <!-- <button
                        v-if="activeBatch.dispatches?.[0] && activeBatch.dispatches[0].dispatch_status !== 'Cancelled' && activeBatch.status !== 5"
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors cursor-pointer border-t border-slate-100 dark:border-slate-800"
                        @click="openCancelDispatchModal(activeBatch); closeAllMenus();"
                    >
                        <i class="pi pi-ban mr-2 text-rose-500 font-bold"></i>
                        Cancel Dispatch
                    </button> -->
                    <div
                        v-if="activeBatch.dispatches?.[0]?.dispatch_status === 'Cancelled' || activeBatch.status === 5"
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-700 bg-rose-50 dark:bg-rose-950/30 select-none border-t border-slate-100 dark:border-slate-800"
                    >
                        <i class="pi pi-times-circle mr-2 text-rose-500 font-bold"></i>
                        Dispatch Cancelled
                    </div>
                </div>

                <!-- Group 4: Delete Batch -->
                <div v-if="Number(activeBatch.status) < 3 && !isBatchCancelled(activeBatch)" class="py-1">
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                        @click="destroy(activeBatch); closeAllMenus();"
                    >
                        <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                        Delete Batch
                    </button>
                </div>
                </template>
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

        <!-- Cancel Dispatch & Batch Modal Dialog -->
        <Dialog v-model:visible="showCancelDispatchModal" modal header="Cancel Dispatch & Batch" :style="{ width: '560px' }" class="premium-dialog">
            <div class="p-2 space-y-4">
                <!-- Alert Banner -->
                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
                    <div class="flex items-center gap-2 font-bold text-rose-900">
                        <i class="pi pi-exclamation-triangle text-rose-600"></i>
                        <span>Reversal Actions on Cancellation</span>
                    </div>
                    <ul class="list-disc pl-5 text-[11px] space-y-0.5 text-rose-700">
                        <li>Dispatch and Batch status will be permanently marked as <strong>Cancelled</strong>.</li>
                        <li>Sales Order delivered/produced volume will be automatically <strong>reversed</strong>.</li>
                        <li>If invoiced, the invoice will be marked Cancelled, active E-Invoice (IRN) cancelled, and an accounting <strong>Credit Note</strong> will be generated.</li>
                    </ul>
                </div>

                <!-- Trip Details Summary -->
                <div v-if="cancellingBatch" class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs grid grid-cols-2 gap-2">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Batch Number</span>
                        <span class="font-bold text-slate-800">B{{ cancellingBatch.batch_no }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Dispatch Number</span>
                        <span class="font-bold text-slate-800">{{ cancellingBatch.dispatches?.[0]?.dispatch_no || 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Customer</span>
                        <span class="font-semibold text-slate-700 truncate block">{{ cancellingBatch.dispatches?.[0]?.customer?.legal_name || cancellingBatch.salesOrder?.customer?.legal_name || 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Truck Registration</span>
                        <span class="font-semibold text-slate-700">{{ cancellingBatch.dispatches?.[0]?.truck?.reg_number || 'N/A' }}</span>
                    </div>
                </div>

                <!-- Notes Input with 50-Word Validation Counter -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">
                            Cancellation Reason & Audit Notes <span class="text-rose-500">*</span>
                        </label>
                        <span :class="[
                            'text-[11px] font-bold px-2 py-0.5 rounded-full border',
                            isCancellationValid 
                                ? 'text-emerald-700 bg-emerald-50 border-emerald-300' 
                                : 'text-rose-700 bg-rose-50 border-rose-300'
                        ]">
                            Words: {{ cancellationWordCount }} / 50 minimum required
                        </span>
                    </div>
                    <textarea
                        v-model="cancellationNotes"
                        rows="5"
                        placeholder="Provide detailed, comprehensive notes explaining why this dispatch is being cancelled (minimum 5 words required by policy)..."
                        class="w-full text-xs rounded-xl border border-slate-300 p-3 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none resize-none"
                    ></textarea>
                    <p v-if="!isCancellationValid" class="text-[11px] text-rose-500 mt-1 flex items-center gap-1 font-medium">
                        <i class="pi pi-info-circle text-[10px]"></i>
                        Please write at least {{ 50 - cancellationWordCount }} more word{{ (50 - cancellationWordCount) === 1 ? '' : 's' }} to enable cancellation.
                    </p>
                    <p v-else class="text-[11px] text-emerald-600 mt-1 flex items-center gap-1 font-medium">
                        <i class="pi pi-check text-[10px]"></i>
                        Minimum word requirement met. You may proceed.
                    </p>
                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showCancelDispatchModal = false"
                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        :disabled="!isCancellationValid || isSubmittingCancel"
                        @click="submitCancelDispatch"
                        :class="[
                            'flex items-center gap-2 px-5 py-2 text-xs font-bold text-white rounded-lg shadow-sm transition-all',
                            isCancellationValid && !isSubmittingCancel
                                ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/20 cursor-pointer'
                                : 'bg-slate-300 cursor-not-allowed text-slate-500'
                        ]"
                    >
                        <i v-if="isSubmittingCancel" class="pi pi-spinner animate-spin"></i>
                        <i v-else class="pi pi-ban"></i>
                        <span>Confirm Cancellation</span>
                    </button>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>



<style scoped>
:deep(.expanded-batch-tabview .p-tabview-nav) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.75rem 1.25rem;
    gap: 0.5rem;
    display: flex;
}
:deep(.expanded-batch-tabview .p-tabview-header) {
    margin: 0 !important;
}
:deep(.expanded-batch-tabview .p-tabview-nav-link) {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    box-shadow: none !important;
    border-radius: 0.75rem !important;
}
:deep(.expanded-batch-tabview .p-tabview-ink-bar) {
    display: none !important;
}
:deep(.expanded-batch-tabview .p-tabview-panels) {
    padding: 0 !important;
    background: transparent !important;
}

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

:deep(tr.batch-row-cancelled > td) {
    background-color: #fff1f2 !important; /* light red (rose-50) */
    border-color: #ffe4e6 !important;
}

:deep(tr.batch-row-cancelled:hover > td) {
    background-color: #ffe4e6 !important; /* rose-100 on hover */
}

:deep(tr.batch-row-cancelled) {
    background-color: #fff1f2 !important;
}
</style>

