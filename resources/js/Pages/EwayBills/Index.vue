<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';
import { 
    TruckIcon, 
    DocumentTextIcon, 
    ArrowPathIcon, 
    CheckCircleIcon, 
    XCircleIcon,
    ClockIcon,
    ArrowTopRightOnSquareIcon,
    ClipboardDocumentCheckIcon,
    CalendarIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    ewaybills: any;
}>();

const toast = useToast();

// Flatten list whether it is an array or paginator
const ewaybillList = computed(() => {
    if (Array.isArray(props.ewaybills)) return props.ewaybills;
    return props.ewaybills?.data || [];
});

// Filters for BaseDataTable
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});
const perPage = ref(20);

// Key Stats
const totalCount = computed(() => ewaybillList.value.length);
const activeCount = computed(() => ewaybillList.value.filter((e: any) => e.ewaybill_status === 'ACT').length);
const cancelledCount = computed(() => ewaybillList.value.filter((e: any) => e.ewaybill_status === 'CNL').length);
const inboundCount = computed(() => ewaybillList.value.filter((e: any) => e.generation_type === 'inbound_transporter').length);

// Sync Dialog state
const syncDialogVisible = ref(false);
const syncLoading = ref(false);
const syncForm = ref({
    from: new Date().toISOString().split('T')[0],
    to: new Date().toISOString().split('T')[0],
});

// Details Modal state
const detailsDialogVisible = ref(false);
const activeEwb = ref<any>(null);

const openDetails = (row: any) => {
    activeEwb.value = row;
    detailsDialogVisible.value = true;
};

// Print helper
const printEwayBill = (ewb: any) => {
    if (!ewb || !ewb.id) return;
    window.open(route('ewaybills.print', ewb.id), '_blank');
};

// Copy helper
const copiedNo = ref<string | null>(null);
const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    copiedNo.value = text;
    toast.add({
        severity: 'success',
        summary: 'Copied',
        detail: `E-Way Bill ${text} copied to clipboard`,
        life: 2000,
    });
    setTimeout(() => {
        if (copiedNo.value === text) copiedNo.value = null;
    }, 2000);
};

// Submit Sync to PeriOne
const executeSync = () => {
    syncLoading.value = true;
    router.post(route('ewaybills.refresh'), {
        from: syncForm.value.from,
        to: syncForm.value.to,
    }, {
        onSuccess: () => {
            syncLoading.value = false;
            syncDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Sync Complete',
                detail: 'E-Way Bills successfully fetched from PeriOne',
                life: 3500,
            });
        },
        onError: (errors: any) => {
            syncLoading.value = false;
            toast.add({
                severity: 'error',
                summary: 'Sync Failed',
                detail: errors.error || 'Failed to sync with PeriOne gateway',
                life: 4000,
            });
        },
        onFinish: () => {
            syncLoading.value = false;
        }
    });
};

// Format date helper
const formatDate = (val: string | null) => {
    if (!val) return '—';
    try {
        const d = new Date(val);
        return isNaN(d.getTime()) ? val : d.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return val;
    }
};

// Expiry warning check (is within 12 hours of expiring)
const isExpiringSoon = (validUpto: string | null) => {
    if (!validUpto) return false;
    const expiry = new Date(validUpto).getTime();
    const now = Date.now();
    const diffHours = (expiry - now) / (1000 * 60 * 60);
    return diffHours > 0 && diffHours < 12;
};

const isExpired = (validUpto: string | null) => {
    if (!validUpto) return false;
    return new Date(validUpto).getTime() < Date.now();
};
</script>

<template>
    <AppLayout title="E-Way Bills">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Head title="E-Way Bill Registry" />
        <Toast />

        <div class="min-h-screen py-8 bg-slate-50/50 dark:bg-slate-900/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">

                <!-- Header / Actions Card -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-700/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 text-white">
                            <TruckIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl font-black tracking-tight text-slate-900 dark:text-white">
                                    E-Way Bill Registry
                                </h1>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    PeriOne Live Gateway
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Centralized tracking, standalone generation, and direct transporter synchronization via PeriOne.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <Button 
                            label="Sync from PeriOne" 
                            severity="contrast" 
                            size="small"
                            class="!rounded-xl !px-4 !py-2.5 !text-xs !font-bold shadow-sm hover:shadow transition-all"
                            @click="syncDialogVisible = true"
                        >
                            <template #icon>
                                <ArrowPathIcon class="w-4 h-4 mr-2" />
                            </template>
                        </Button>
                    </div>
                </div>

                <!-- KPI Metric Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <DocumentTextIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total E-Way Bills</span>
                            <span class="text-xl font-black text-slate-900 dark:text-white">{{ totalCount }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <CheckCircleIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Active Status</span>
                            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ activeCount }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                            <TruckIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Inbound Synced</span>
                            <span class="text-xl font-black text-amber-600 dark:text-amber-400">{{ inboundCount }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 flex items-center justify-center text-rose-600 dark:text-rose-400">
                            <XCircleIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Cancelled</span>
                            <span class="text-xl font-black text-rose-600 dark:text-rose-400">{{ cancelledCount }}</span>
                        </div>
                    </div>
                </div>

                <!-- Main Data Table Card -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm overflow-hidden p-2">
                    <BaseDataTable
                        v-model:filters="filters"
                        v-model:rows="perPage"
                        :value="ewaybillList"
                        dataKey="id"
                        :rowsPerPageOptions="[15, 30, 50, 100]"
                        showSearch
                        :globalFilterFields="['ewaybill_no', 'generation_type', 'ewaybill_status', 'invoice.prefix', 'invoice.invoice_number']"
                        showSerial
                        heading="Registered E-Way Bills"
                        headingIcon="TruckIcon"
                        showExport
                        exportFilename="ewaybills-registry-report"
                    >
                        <template #toolbar>
                            <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50/50 dark:bg-indigo-950/30 rounded-lg border border-indigo-100 dark:border-indigo-800/40">
                                <DocumentTextIcon class="w-3.5 h-3.5 text-indigo-500" />
                                <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">
                                    {{ ewaybillList.length }} Records
                                </span>
                            </div>
                        </template>

                        <!-- E-Way Bill Number -->
                        <Column field="ewaybill_no" header="E-Way Bill No" sortable>
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-black text-slate-900 dark:text-slate-100 tracking-tight">
                                        {{ data.ewaybill_no }}
                                    </span>
                                    <button 
                                        type="button" 
                                        @click="copyToClipboard(data.ewaybill_no)"
                                        class="p-1 rounded-md text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                        title="Copy EWB Number"
                                    >
                                        <ClipboardDocumentCheckIcon v-if="copiedNo === data.ewaybill_no" class="w-4 h-4 text-emerald-500" />
                                        <ClipboardDocumentCheckIcon v-else class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>

                        <!-- Document / Linked Origin -->
                        <Column header="Linked Invoice / Doc" sortable>
                            <template #body="{ data }">
                                <div v-if="data.invoice" class="space-y-0.5">
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        {{ (data.invoice.prefix || '') + (data.invoice.invoice_number || '') }}
                                    </span>
                                    <span v-if="data.invoice.total_amount" class="text-[10px] text-slate-400 block">
                                        ₹{{ Number(data.invoice.total_amount).toLocaleString('en-IN') }}
                                    </span>
                                </div>
                                <div v-else>
                                    <Tag value="Inbound Sync" severity="secondary" class="!text-[9px] !font-bold !px-2" />
                                </div>
                            </template>
                        </Column>

                        <!-- Generated Date -->
                        <Column field="ewaybill_date" header="Generated Date" sortable>
                            <template #body="{ data }">
                                <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">
                                    {{ formatDate(data.ewaybill_date) }}
                                </span>
                            </template>
                        </Column>

                        <!-- Valid Until -->
                        <Column field="valid_upto" header="Valid Until" sortable>
                            <template #body="{ data }">
                                <div class="flex items-center gap-1.5">
                                    <ClockIcon class="w-3.5 h-3.5 text-slate-400" />
                                    <span 
                                        class="text-xs font-semibold"
                                        :class="{
                                            'text-rose-600 dark:text-rose-400 font-bold': isExpired(data.valid_upto),
                                            'text-amber-600 dark:text-amber-400 font-bold': isExpiringSoon(data.valid_upto),
                                            'text-slate-700 dark:text-slate-200': !isExpired(data.valid_upto) && !isExpiringSoon(data.valid_upto)
                                        }"
                                    >
                                        {{ formatDate(data.valid_upto) }}
                                    </span>
                                    <span v-if="isExpired(data.valid_upto)" class="text-[9px] font-black uppercase text-rose-500 bg-rose-50 dark:bg-rose-950/40 px-1 rounded">
                                        Expired
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <!-- Generation Type -->
                        <Column field="generation_type" header="Type" sortable>
                            <template #body="{ data }">
                                <Tag 
                                    :value="data.generation_type === 'inbound_transporter' ? 'Inbound / Transporter' : (data.generation_type?.toLowerCase() === 'batch' ? 'Batch Dispatch' : 'Invoice Dispatch')" 
                                    :severity="data.generation_type === 'inbound_transporter' ? 'warn' : (data.generation_type?.toLowerCase() === 'batch' ? 'success' : 'info')"
                                    class="!text-[9px] !font-black uppercase tracking-wider"
                                />
                            </template>
                        </Column>

                        <!-- Status -->
                        <Column field="ewaybill_status" header="Status" sortable>
                            <template #body="{ data }">
                                <div class="flex items-center gap-1.5">
                                    <Tag 
                                        v-if="data.ewaybill_status === 'ACT'" 
                                        value="ACTIVE" 
                                        severity="success" 
                                        class="!text-[9px] !font-black !px-2.5 !py-0.5" 
                                    />
                                    <Tag 
                                        v-else-if="data.ewaybill_status === 'CNL'" 
                                        value="CANCELLED" 
                                        severity="danger" 
                                        class="!text-[9px] !font-black !px-2.5 !py-0.5" 
                                    />
                                    <Tag 
                                        v-else 
                                        :value="data.ewaybill_status || 'UNKNOWN'" 
                                        severity="secondary" 
                                        class="!text-[9px] !font-black !px-2.5 !py-0.5" 
                                    />
                                </div>
                            </template>
                        </Column>

                        <!-- Actions -->
                        <Column header="Actions" alignFrozen="right" frozen>
                            <template #body="{ data }">
                                <div class="flex items-center gap-1.5 justify-end">
                                    <Button 
                                        icon="pi pi-print" 
                                        severity="info" 
                                        text 
                                        rounded 
                                        size="small" 
                                        title="Print Official E-Way Bill"
                                        @click="printEwayBill(data)" 
                                    />
                                    <Button 
                                        icon="pi pi-eye" 
                                        severity="secondary" 
                                        text 
                                        rounded 
                                        size="small" 
                                        title="View Details"
                                        @click="openDetails(data)" 
                                    />
                                </div>
                            </template>
                        </Column>

                        <!-- Empty state -->
                        <template #empty>
                            <div class="py-12 text-center">
                                <TruckIcon class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300">No E-Way Bills Found</h3>
                                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                                    Click "Sync from PeriOne" to fetch transporter e-way bills, or generate directly from Invoices.
                                </p>
                            </div>
                        </template>
                    </BaseDataTable>
                </div>

            </div>
        </div>

        <!-- Sync From PeriOne Dialog -->
        <Dialog 
            v-model:visible="syncDialogVisible" 
            modal 
            header="Sync E-Way Bills from PeriOne" 
            :style="{ width: '450px' }"
            :closable="!syncLoading"
            class="rounded-2xl"
        >
            <div class="space-y-4 pt-2">
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-800/40 rounded-xl flex items-start gap-3">
                    <InformationCircleIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                    <p class="text-xs text-indigo-900 dark:text-indigo-200 leading-relaxed">
                        This queries the PeriOne portal for all transporter/taxpayer E-Way Bills within the specified date range and syncs new records to your registry.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            From Date
                        </label>
                        <input 
                            type="date" 
                            v-model="syncForm.from" 
                            class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 focus:ring-indigo-500"
                            :disabled="syncLoading"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            To Date
                        </label>
                        <input 
                            type="date" 
                            v-model="syncForm.to" 
                            class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 focus:ring-indigo-500"
                            :disabled="syncLoading"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3">
                    <Button 
                        label="Cancel" 
                        severity="secondary" 
                        text 
                        size="small" 
                        :disabled="syncLoading"
                        @click="syncDialogVisible = false" 
                    />
                    <Button 
                        label="Sync Now" 
                        severity="primary" 
                        size="small" 
                        :loading="syncLoading"
                        @click="executeSync" 
                    />
                </div>
            </template>
        </Dialog>

        <!-- EWB Details Modal -->
        <Dialog
            v-model:visible="detailsDialogVisible"
            modal
            header="E-Way Bill Details"
            :style="{ width: '550px' }"
            class="rounded-2xl"
        >
            <div v-if="activeEwb" class="space-y-4 pt-2">
                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">E-Way Bill Number</span>
                        <span class="text-lg font-mono font-black text-indigo-600 dark:text-indigo-400">{{ activeEwb.ewaybill_no }}</span>
                    </div>
                    <Tag 
                        :value="activeEwb.ewaybill_status === 'ACT' ? 'ACTIVE' : 'CANCELLED'" 
                        :severity="activeEwb.ewaybill_status === 'ACT' ? 'success' : 'danger'"
                        class="!text-[10px] !font-black !px-2.5 !py-1"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Generated Date</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatDate(activeEwb.ewaybill_date) }}</span>
                    </div>
                    <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Valid Until</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatDate(activeEwb.valid_upto) }}</span>
                    </div>
                    <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Type</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 capitalize">{{ activeEwb.generation_type || 'Standard' }}</span>
                    </div>
                    <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Linked Invoice</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">
                            {{ activeEwb.invoice ? ((activeEwb.invoice.prefix || '') + (activeEwb.invoice.invoice_number || '')) : 'None (Inbound)' }}
                        </span>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <Button 
                        label="Print E-Way Bill" 
                        icon="pi pi-print" 
                        severity="primary" 
                        size="small" 
                        @click="printEwayBill(activeEwb)" 
                    />
                    <Button 
                        label="Close" 
                        severity="secondary" 
                        size="small" 
                        @click="detailsDialogVisible = false" 
                    />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
</style>
