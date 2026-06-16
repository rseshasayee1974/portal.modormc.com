<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useWebSocket } from '@/Composables/useWebSocket';
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
import { CubeIcon, ListBulletIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    batches: any[];
    workOrders: any[];
    trucks: any[];
    customers: any[];
    transporters: any[];
    personnel: any[];
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
}>();
const dropdownData = computed(() => ({
    trucks: props.trucks,
    transporters: props.transporters,
    personnel: props.personnel,
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

// Local mutable copy of batches, including offline queued ones
const localBatches = ref<any[]>([]);

// Set initial batches list and prepend any locally saved offline batches
const loadInitialBatches = () => {
    const offline = JSON.parse(localStorage.getItem('offline_batches') || '[]');
    localBatches.value = [...offline, ...props.batches];
};

watch(() => props.batches, (newBatches) => {
    const offline = JSON.parse(localStorage.getItem('offline_batches') || '[]');
    localBatches.value = [...offline, ...newBatches];
}, { deep: true });

const handleOfflineBatchAdded = (batch: any) => {
    localBatches.value.unshift(batch);
};

const isSyncing = ref(false);

const syncOfflineBatches = async () => {
    if (!navigator.onLine || isSyncing.value) return;

    const offline = JSON.parse(localStorage.getItem('offline_batches') || '[]');
    if (offline.length === 0) return;

    isSyncing.value = true;
    let syncedCount = 0;
    
    // We make a copy of the queue to iterate
    const queue = [...offline];
    
    for (const batch of queue) {
        try {
            // Remove the temporary fields used only on frontend
            const payload = { ...batch };
            delete payload.id;
            delete payload.is_offline_pending;
            delete payload.truck_registration;
            delete payload.created_at;
            
            await axios.post(route('batches.store'), payload);
            syncedCount++;
            
            // Remove from local storage queue
            const currentQueue = JSON.parse(localStorage.getItem('offline_batches') || '[]');
            const updatedQueue = currentQueue.filter((b: any) => b.id !== batch.id);
            localStorage.setItem('offline_batches', JSON.stringify(updatedQueue));
            
            // Remove the temporary record from local list
            localBatches.value = localBatches.value.filter((b: any) => b.id !== batch.id);
        } catch (err) {
            console.error('Failed to sync offline batch:', err);
            // Stop syncing remaining items if we hit an error (e.g. server down)
            break;
        }
    }

    isSyncing.value = false;

    if (syncedCount > 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Synchronized ${syncedCount} offline batches successfully.`,
            showConfirmButton: false,
            timer: 2500
        });
        
        // Reload list to get fresh server-side synced records with IDs
        fetchBatchesFallback();
    }
};

onMounted(() => {
    loadInitialBatches();
    syncOfflineBatches();
    window.addEventListener('online', syncOfflineBatches);
});

onUnmounted(() => {
    window.removeEventListener('online', syncOfflineBatches);
});

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

const expandedRows = ref({});
const detailedBatches = ref<Record<number, any>>({});
const isLoadingBatch = ref<Record<number, boolean>>({});

// Fallback REST polling via Inertia reload
const fetchBatchesFallback = () => {
    router.reload({
        only: ['batches', 'nextBatchNo'],
        preserveScroll: true
    });
};

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

const fetchBatchDetails = async (id: number) => {
    if (!detailedBatches.value[id]) {
        isLoadingBatch.value[id] = true;
        try {
            const response = await axios.get(route('batches.show', id));
            detailedBatches.value[id] = response.data;
        } catch (e) {
            console.error('Failed to fetch batch details:', e);
        } finally {
            isLoadingBatch.value[id] = false;
        }
    }
};

const toggleExpand = async (data: any) => {
    if (expandedRows.value[data.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [data.id]: true };
        await fetchBatchDetails(data.id);
    }
};

const onRowExpand = async (event: any) => {
    await fetchBatchDetails(event.data.id);
};

const collapseExpandedRows = (batchId?: number) => {
    if (batchId) {
        delete detailedBatches.value[batchId];
    }
    expandedRows.value = {};
};

const statusSeverity = (status: number) => {
    if (status === 4) return 'success';
    if (status === 2 || status === 3) return 'info';
    if (status === 5) return 'danger';
    return 'warn';
};

const statusLabel = (status: number) => props.statuses.find((entry) => entry.value === status)?.label ?? 'Unknown';

const destroy = (row: any) => {
    Swal.fire({
        title: 'Delete batch?',
        text: `Batch #${row.batch_no} will be deleted.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('batches.destroy', row.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Batch deleted successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                });
            }
        });
    });
};
const downloadPdf = (id: number) => {
    // Use anchor click technique to avoid popup-blocker issues
    const url = route('batches.download', id);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', '');
    link.setAttribute('target', '_blank');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const tokenPreviewVisible = ref(false);
const tokenPreviewUrl = ref('');
const iframeHeight = ref('300px');
const previewTitle = ref('Batching Token Preview');
const previewWidth = ref('380px');
const previewIframeWidth = ref('340px');

const viewToken = (id: number, type: string = 'batching') => {
    if (type === 'dispatch') {
        previewTitle.value = 'Dispatch Token Preview';
        previewWidth.value = '380px';
        previewIframeWidth.value = '340px';
        tokenPreviewUrl.value = route('batches.dispatch-token', id);
    } else if (type === 'delivery') {
        previewTitle.value = 'Delivery Token Preview (A4)';
        previewWidth.value = '850px';
        previewIframeWidth.value = '810px';
        tokenPreviewUrl.value = route('batches.delivery-token', id);
    } else {
        previewTitle.value = 'Batching Token Preview';
        previewWidth.value = '380px';
        previewIframeWidth.value = '340px';
        tokenPreviewUrl.value = route('batches.token', id);
    }
    iframeHeight.value = '300px'; // Reset height before load
    tokenPreviewVisible.value = true;
};

const adjustIframeHeight = (event: any) => {
    const iframe = event.target;
    if (iframe && iframe.contentDocument) {
        setTimeout(() => {
            try {
                const doc = iframe.contentDocument;
                const height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
                iframeHeight.value = `${height + 15}px`; // Add 15px buffer to avoid vertical scrollbar inside iframe
            } catch (e) {
                console.error(e);
            }
        }, 150);
    }
};

const printTokenIframe = () => {
    const iframe = document.querySelector('.token-preview-dialog iframe') as HTMLIFrameElement;
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }
};

const handleShowTokenEvent = (e: any) => {
    if (e.detail?.url) {
        tokenPreviewUrl.value = e.detail.url;
        if (e.detail.url.includes('delivery-token')) {
            previewTitle.value = 'Delivery Token Preview (A4)';
            previewWidth.value = '850px';
            previewIframeWidth.value = '810px';
        } else if (e.detail.url.includes('dispatch-token')) {
            previewTitle.value = 'Dispatch Token Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
        } else {
            previewTitle.value = 'Batching Token Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
        }
        iframeHeight.value = '300px';
        tokenPreviewVisible.value = true;
    }
};

onMounted(() => {
    window.addEventListener('show-batch-token', handleShowTokenEvent);
    window.addEventListener('click', closeAllMenus);
});

onUnmounted(() => {
    window.removeEventListener('show-batch-token', handleShowTokenEvent);
    window.removeEventListener('click', closeAllMenus);
});

const retrySync = (id: number) => {
    router.post(route('batches.sync', id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            const hasError = page.props?.flash?.error;
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: hasError ? 'error' : 'success',
                title: hasError ? 'Sync failed again.' : 'Sync successful.',
                showConfirmButton: false,
                timer: 2000,
            });
        }
    });
};

const page = usePage();
const customSettings = page.props.custom_settings as any;
const hideBatchForm = computed(() => !!customSettings?.batching?.hide_batch_form);

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

const activeMenuId = ref<number | null>(null);

const toggleMenu = (event: Event, id: number) => {
    event.stopPropagation();
    activeMenuId.value = activeMenuId.value === id ? null : id;
};

const closeAllMenus = () => {
    activeMenuId.value = null;
};

const generateInvoiceDirect = (dispatch: any) => {
    if (!dispatch || !dispatch.id) return;
    
    const ledgersOptionsHtml = props.sales_ledgers.map(l => `<option value="${l.value}">${l.label}</option>`).join('');
    const defaultDate = new Date().toISOString().substring(0, 10);
    
    Swal.fire({
        title: 'Generate Invoice',
        html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Sales Ledger</label>
                    <select id="swal-ledger-id" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">Select Sales Account</option>
                        ${ledgersOptionsHtml}
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Invoice Date</label>
                    <input id="swal-invoice-date" type="date" value="${defaultDate}" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generate',
        confirmButtonColor: '#4f46e5',
        preConfirm: () => {
            const ledgerId = (document.getElementById('swal-ledger-id') as HTMLSelectElement).value;
            const invoiceDate = (document.getElementById('swal-invoice-date') as HTMLInputElement).value;
            if (!ledgerId) {
                Swal.showValidationMessage('Please select a Sales Ledger');
                return false;
            }
            if (!invoiceDate) {
                Swal.showValidationMessage('Please select an Invoice Date');
                return false;
            }
            return { ledgerId, invoiceDate };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('dispatches.generate-invoice', dispatch.id), {
                ledger_id: result.value.ledgerId,
                invoice_date: result.value.invoiceDate,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Invoice generated successfully.',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            });
        }
    });
};

const printInvoiceDirect = (invoice: any) => {
    if (!invoice || !invoice.encrypted_id) return;
    window.open(route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'view' }), '_blank');
};

const downloadInvoiceDirect = (invoice: any) => {
    if (!invoice || !invoice.encrypted_id) return;
    window.open(route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'download' }), '_blank');
};

const printEInvoiceDirect = (invoice: any) => {
    if (!invoice || !invoice.encrypted_id) return;
    window.open(route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'view' }), '_blank');
};

const deleteInvoiceDirect = (dispatch: any) => {
    if (!dispatch || !dispatch.id) return;
    Swal.fire({
        title: 'Delete Invoice?',
        text: 'This will delete the generated invoice and reset the dispatch billing status. This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('dispatches.delete-invoice', dispatch.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Invoice deleted successfully.',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            });
        }
    });
};

const sendWhatsAppDirect = async (dispatch: any) => {
    if (!dispatch || !dispatch.id) return;
    try {
        Swal.fire({
            title: 'Preparing WhatsApp message...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        const response = await axios.get(route('dispatches.whatsapp-url', dispatch.id));
        Swal.close();
        if (response.data.url) {
            window.open(response.data.url, '_blank');
        } else {
            Swal.fire('Error', 'Could not generate WhatsApp URL.', 'error');
        }
    } catch (error: any) {
        Swal.close();
        const msg = error.response?.data?.error || 'Failed to generate WhatsApp URL. Please check if customer mobile number exists.';
        Swal.fire('Error', msg, 'error');
    }
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
                    :personnel="personnel"
                    :products="products"
                    :uoms="uoms"
                    :unloading_sites="unloading_sites"
                    :loading_sites="loading_sites"
                    :taxes="taxes"
                    :statuses="statuses"
                    :nextBatchNo="nextBatchNo"
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
                                    </template>
                                </div>
                            </template>
                        </Column>

                        <Column header="Actions" headerStyle="width: 5rem; text-align: center" bodyStyle="overflow: visible; text-align: center">
                            <template #body="slotProps">
                                <div v-if="!slotProps.data.is_offline_pending" class="relative inline-block text-left">
                                    <button
                                        type="button"
                                        class="inline-flex justify-center items-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none transition-all duration-200"
                                        @click.stop="toggleMenu($event, slotProps.data.id)"
                                        v-tooltip.top="'Actions'"
                                    >
                                        <i class="pi pi-ellipsis-v text-sm font-bold"></i>
                                    </button>

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
                                        :batch="detailedBatches[slotProps.data.id] || slotProps.data"
                                        :workOrders="workOrders"
                                        :trucks="trucks"
                                        :transporters="transporters"
                                        :personnel="personnel"
                                        :products="products"
                                        :uoms="uoms"
                                        :statuses="statuses"
                                        :loading_sites="loading_sites"
                                        @saved="collapseExpandedRows(slotProps.data.id)"
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
                                        :settings="batchingSettings"
                                        @saved="collapseExpandedRows(slotProps.data.id)"
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
                    @load="adjustIframeHeight"
                ></iframe>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="tokenPreviewVisible = false" text severity="secondary" class="!text-[10px] !font-bold !uppercase !tracking-wider" />
                <Button label="Print Token" icon="pi pi-print" @click="printTokenIframe" severity="primary" class="!text-[10px] !font-bold !uppercase !tracking-wider !px-4" />
            </template>
        </Dialog>
    </AppLayout>
</template>
