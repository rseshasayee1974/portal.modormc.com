<script setup lang="ts">
import { entityToday } from '@/Utils/entityDateTime';
import { reactive, ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import { formatCurrency } from '@/Utils/formatters';
import Dialog from 'primevue/dialog';
import MultiSelect from 'primevue/multiselect';
import {
    DocumentTextIcon,
    ArrowPathIcon,
    PrinterIcon,
    ArrowDownTrayIcon,
    ExclamationTriangleIcon,
    BanknotesIcon,
    DocumentDuplicateIcon,
    CheckCircleIcon,
    FolderArrowDownIcon,
    ClockIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    patrons?: Array<{ id: number; legal_name: string }>;
    ledgers?: Array<{ id: number; title: string }>;
    defaultStartDate?: string;
    defaultEndDate?: string;
    allowExport?: boolean;
}>();


const filters = reactive({
    start_date: props.defaultStartDate?.substring(0, 10) || entityToday().substring(0, 7) + '-01',
    end_date: props.defaultEndDate?.substring(0, 10) || entityToday(),
    type: '',
    subtype: '',
    patron_id: null as number | null,
    ledger_id: null as number | null,
    tax_type: '',
    reference: '',
    invoice_ids: [] as number[],
});

const result = ref<{ count: number; documents: any[] } | null>(null);
const busy = ref(false);
const exportingPdf = ref(false);
const error = ref('');
const invoiceOptions = ref<Array<{ id: number; number: string; date: string; label: string }>>([]);
const optionsLoading = ref(false);
const optionsError = ref('');
let optionsGeneration = 0;
let previewGeneration = 0;
let optionsTimer: ReturnType<typeof setTimeout>;
let previewTimer: ReturnType<typeof setTimeout>;

const scopeFilters = computed(() => ({
    start_date: filters.start_date, end_date: filters.end_date,
    type: filters.type, subtype: filters.subtype,
    patron_id: filters.patron_id, ledger_id: filters.ledger_id,
    tax_type: filters.tax_type, reference: filters.reference,
}));

async function loadInvoiceOptions() {
    const generation = ++optionsGeneration;
    optionsLoading.value = true;
    optionsError.value = '';
    try {
        const response = await axios.get(route('reports.bulk-documents.options'), { params: scopeFilters.value });
        if (generation !== optionsGeneration) return;
        invoiceOptions.value = response.data.options.map((option: any) => ({
            ...option, label: `${option.number} · ${option.date}`,
        }));
    } catch (e: any) {
        if (generation === optionsGeneration) optionsError.value = 'Unable to load invoice numbers. Check the date range and try Generate Report again.';
    } finally {
        if (generation === optionsGeneration) optionsLoading.value = false;
    }
}

watch(scopeFilters, () => {
    optionsGeneration++;
    invoiceOptions.value = [];
    filters.invoice_ids = [];
    optionsLoading.value = true;
    clearTimeout(optionsTimer);
    optionsTimer = setTimeout(loadInvoiceOptions, 250);
});

watch(filters, () => {
    previewGeneration++;
    result.value = null;
    clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 300);
}, { deep: true, flush: 'sync' });

onUnmounted(() => {
    optionsGeneration++;
    previewGeneration++;
    clearTimeout(optionsTimer);
    clearTimeout(previewTimer);
});

const documentTypeOptions = [
    { label: 'All Invoices & Bills', value: '' },
    { label: 'Sales Invoices Only', value: 'invoice' },
    { label: 'Vendor Bills Only', value: 'bill' },
];

const taxOptions = [
    { label: 'All Tax Treatments', value: '' },
    { label: 'GST (CGST + SGST)', value: 'gst' },
    { label: 'IGST (Interstate)', value: 'igst' },
    { label: 'No Tax / Nil Rated', value: 'no_tax' },
];

const subtypes = computed(() => {
    const list = [
        { label: 'All Subtypes', value: '' },
        { value: 'manual_invoice', label: 'Manual Invoice (Tax Invoice)', type: 'invoice' },
        { value: 'dispatch_invoice', label: 'Dispatch Invoice', type: 'invoice' },
        { value: 'manual_bill', label: 'Manual Bill', type: 'bill' },
        { value: 'vendor_bill', label: 'Vendor Bill', type: 'bill' },
    ];
    if (!filters.type) return list;
    return [
        { label: 'All Subtypes', value: '' },
        ...list.filter(s => s.type === filters.type),
    ];
});

watch(() => filters.type, () => {
    filters.subtype = '';
});

const totalGrossAmount = computed(() => {
    if (!result.value?.documents?.length) return 0;
    return result.value.documents.reduce((sum, doc) => sum + (Number(doc.total) || 0), 0);
});

const totalTaxAmount = computed(() => {
    if (!result.value?.documents?.length) return 0;
    return result.value.documents.reduce((sum, doc) => sum + (Number(doc.tax) || 0), 0);
});

const invoiceCount = computed(() => {
    return result.value?.documents?.filter(d => d.type === 'Invoice').length || 0;
});

const billCount = computed(() => {
    return result.value?.documents?.filter(d => d.type === 'Bill').length || 0;
});

const canExport = computed(() => {
    return props.allowExport === true && (result.value?.count ?? 0) > 0 && (result.value?.count ?? 0) <= 100;
});

const canExportZip = computed(() => {
    return props.allowExport === true && (result.value?.count ?? 0) > 0;
});

const exportingZip = ref(false);
const zipStatus = ref<{
    status: string;
    progress?: number;
    processed?: number;
    total?: number;
    message?: string;
    url?: string;
    filename?: string;
    file_size?: string;
    error?: string;
} | null>(null);
const pollTimer = ref<any>(null);
const showZipModal = ref(false);
let pollGeneration = 0;
onUnmounted(() => {
    pollGeneration++;
    clearTimeout(pollTimer.value);
});

async function runZipExport() {
    if (exportingZip.value || !canExportZip.value) return;
    exportingZip.value = true;
    error.value = '';
    zipStatus.value = {
        status: 'queued',
        progress: 0,
        processed: 0,
        total: result.value?.count ?? 0,
        message: `Queuing ${result.value?.count ?? 0} documents for background export...`,
    };
    showZipModal.value = true;

    try {
        const response = await axios.post(route('reports.bulk-documents.export-zip'), { ...filters });
        if (response.data?.status_key) {
            pollExportStatus(response.data.status_key);
        } else {
            throw new Error('Missing export status key');
        }
    } catch (e: any) {
        exportingZip.value = false;
        zipStatus.value = {
            status: 'failed',
            error: e.response?.data?.message || 'Failed to initiate bulk ZIP export.',
        };
    }
}

function pollExportStatus(key: string) {
    if (pollTimer.value) clearTimeout(pollTimer.value);
    const generation = ++pollGeneration;
    const started = Date.now();
    let failures = 0;
    let delay = 2000;
    const stop = (message: string) => {
        exportingZip.value = false;
        zipStatus.value = { status: 'failed', error: message };
    };

    const check = async () => {
        if (generation !== pollGeneration) return;
        if (Date.now() - started > 65 * 60 * 1000) {
            stop('Export monitoring timed out. Please contact your administrator before retrying.');
            return;
        }
        try {
            const response = await axios.get(route('reports.export-status', { key }), { timeout: 15000 });
            if (generation !== pollGeneration) return;
            failures = 0;
            zipStatus.value = response.data;

            if (response.data.status === 'completed') {
                exportingZip.value = false;
                return;
            }

            if (response.data.status === 'failed') {
                exportingZip.value = false;
                return;
            }

            if (response.data.status === 'queued' && Date.now() - started > 120000) {
                stop('The export worker has not started after 2 minutes. Please check the worker or export log before retrying.');
                return;
            }
            delay = Math.min(delay * 1.5, 10000);
            pollTimer.value = setTimeout(check, delay);
        } catch (e: any) {
            if (generation !== pollGeneration) return;
            failures++;
            if ([401, 403, 404, 419].includes(e.response?.status) || failures >= 5) {
                stop(e.response?.status === 404 ? 'Export job not found or expired.' : 'Unable to check export status. Refresh the page and check your connection.');
                return;
            }
            pollTimer.value = setTimeout(check, Math.min(2000 * 2 ** failures, 15000));
        }
    };

    pollTimer.value = setTimeout(check, 2000);
}

function downloadZipFile() {
    if (!zipStatus.value?.url) return;
    const link = document.createElement('a');
    link.href = zipStatus.value.url;
    link.download = zipStatus.value.filename || 'Bulk_Documents.zip';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const resetFilters = () => {
    filters.start_date = props.defaultStartDate?.substring(0, 10) || entityToday().substring(0, 7) + '-01';
    filters.end_date = props.defaultEndDate?.substring(0, 10) || entityToday();
    filters.type = '';
    filters.subtype = '';
    filters.patron_id = null;
    filters.ledger_id = null;
    filters.tax_type = '';
    filters.reference = '';
    filters.invoice_ids = [];
    runPreview();
};

async function runPreview() {
    clearTimeout(previewTimer);
    if (optionsError.value) loadInvoiceOptions();
    const generation = ++previewGeneration;
    busy.value = true;
    error.value = '';
    try {
        const response = await axios.get(route('reports.bulk-documents.preview'), {
            params: { ...filters },
        });
        if (generation === previewGeneration) result.value = response.data;
    } catch (e: any) {
        let data = e.response?.data;
        if (data instanceof Blob) {
            try {
                data = JSON.parse(await data.text());
            } catch {
                data = null;
            }
        }
        if (generation === previewGeneration) error.value = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Unable to load preview documents.';
    } finally {
        if (generation === previewGeneration) busy.value = false;
    }
}

async function runExport() {
    if (!canExport.value || exportingPdf.value) return;
    exportingPdf.value = true;
    error.value = '';
    try {
        const response = await axios.post(
            route('reports.bulk-documents.export'),
            { ...filters },
            { responseType: 'blob' }
        );
        const blobUrl = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = blobUrl;
        const prefix = filters.type ? filters.type.toUpperCase() : 'DOCUMENTS';
        link.download = `Bulk_${prefix}_${filters.start_date}_to_${filters.end_date}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
    } catch (e: any) {
        let data = e.response?.data;
        if (data instanceof Blob) {
            try {
                data = JSON.parse(await data.text());
            } catch {
                data = null;
            }
        }
        error.value = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Unable to download combined PDF.';
    } finally {
        exportingPdf.value = false;
    }
}

const printingPdf = ref(false);

const printReport = async () => {
    if (!canExport.value || printingPdf.value) return;
    printingPdf.value = true;
    error.value = '';
    try {
        const response = await axios.post(
            route('reports.bulk-documents.export'),
            { ...filters },
            { responseType: 'blob' }
        );
        const blobUrl = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        window.open(blobUrl, '_blank');
        setTimeout(() => URL.revokeObjectURL(blobUrl), 10000);
    } catch (e: any) {
        let data = e.response?.data;
        if (data instanceof Blob) {
            try {
                data = JSON.parse(await data.text());
            } catch {
                data = null;
            }
        }
        error.value = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Unable to generate combined PDF for print preview.';
    } finally {
        printingPdf.value = false;
    }
};

onMounted(() => {
    loadInvoiceOptions();
    runPreview();
});
</script>

<template>
    <div class="min-w-0 max-w-full space-y-6">
        <!-- Filter Header Card (Styled similar to General Ledger Report UI) -->
        <div class="bg-white rounded shadow-sm border border-slate-200 overflow-hidden mb-6 no-print">
            <!-- Card Top Bar -->
            <div
                class="p-2 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-50/50">
                <div class="flex items-center gap-4">
                    <div class="p-2 bg-indigo-600 rounded text-white shadow-lg shadow-indigo-100 shrink-0">
                        <DocumentTextIcon class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-lg font-black text-slate-800">Bulk Invoice / Bill Export</h1>
                        <!-- <p class="text-sm text-slate-500 font-medium italic">One combined PDF, one document per A4 page</p> -->
                    </div>
                </div>

                <!-- Header Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <BaseButton variant="outlined" severity="secondary"
                        :disabled="!result?.count || printingPdf || busy" :loading="printingPdf" @click="printReport">
                        <PrinterIcon class="h-4 w-4 mr-1.5" />
                        Print Preview
                    </BaseButton>

                    <BaseButton v-if="allowExport" variant="outlined" severity="secondary"
                        :disabled="!canExport || exportingPdf" :loading="exportingPdf" @click="runExport"
                        title="Download single combined PDF booklet (up to 100 documents)">
                        <ArrowDownTrayIcon class="h-4 w-4 mr-1.5 text-rose-600" />
                        Combined PDF
                        <span v-if="result?.count && result.count <= 100"
                            class="ml-1 text-[11px] font-bold text-slate-500">
                            ({{ result.count }} )
                        </span>
                        <span v-else-if="result?.count && result.count > 100"
                            class="ml-1 text-[10px] text-amber-600 font-bold">
                            (Max 100)
                        </span>
                    </BaseButton>

                    <BaseButton v-if="allowExport" variant="filled" severity="success"
                        :disabled="!canExportZip || exportingZip" :loading="exportingZip" @click="runZipExport"
                        title="Export all documents as individual PDFs inside a ZIP archive (Supports 10,000+)">
                        <FolderArrowDownIcon class="h-4 w-4 mr-1.5" />
                        {{ filters.invoice_ids.length ? 'Export Selected to ZIP' : 'Export All to ZIP' }}
                        <span v-if="result?.count"
                            class="ml-1.5 px-1.5 py-0.5 text-[10px] font-black rounded-full bg-white/20">
                            {{ result.count }}
                        </span>
                    </BaseButton>
                </div>
            </div>

            <!-- Filter Form Grid -->
            <div class="p-6 bg-white">
                <form @submit.prevent="runPreview" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div>
                        <BaseDatePicker v-model="filters.start_date" label="From Date" placeholder="Start date"
                            :required="true" :disabled="busy" fluid />
                    </div>

                    <div>
                        <BaseDatePicker v-model="filters.end_date" label="To Date" placeholder="End date"
                            :required="true" :disabled="busy" fluid />
                    </div>

                    <div>
                        <BaseSelect v-model="filters.type" label="Document Type" :options="documentTypeOptions"
                            optionLabel="label" optionValue="value" placeholder="All Invoices &amp; Bills"
                            :disabled="busy" />
                    </div>

                    <div>
                        <BaseSelect v-model="filters.subtype" label="Subtype" :options="subtypes" optionLabel="label"
                            optionValue="value" placeholder="All Subtypes" :disabled="busy" />
                    </div>

                    <div>
                        <BaseSelect v-model="filters.patron_id" label="Patron / Subledger" :options="patrons || []"
                            optionLabel="legal_name" optionValue="id" placeholder="All customers &amp; vendors" filter
                            showClear :disabled="busy" />
                    </div>

                    <div>
                        <BaseSelect v-model="filters.ledger_id" label="Ledger Account" :options="ledgers || []"
                            optionLabel="title" optionValue="id" placeholder="All ledgers" filter showClear
                            :disabled="busy" />
                    </div>

                    <div>
                        <BaseSelect v-model="filters.tax_type" label="Tax Treatment" :options="taxOptions"
                            optionLabel="label" optionValue="value" placeholder="All tax treatments" :disabled="busy" />
                    </div>

                    <!-- <div >
                        <BaseInput
                            v-model="filters.reference"
                            label="Invoice / Journal / Voucher Number"
                            placeholder="Search document or voucher number..."
                            :disabled="busy"
                        />
                    </div> -->

                    <div class="col-span-2">
                        <label for="bulk-invoice-numbers"
                            class="block text-[10px] font-semibold text-slate-600 mb-2">Invoice / Bill Numbers</label>
                        <MultiSelect inputId="bulk-invoice-numbers" v-model="filters.invoice_ids"
                            :options="invoiceOptions" optionLabel="label" optionValue="id" filter showClear
                            :maxSelectedLabels="3" selectedItemsLabel="{0} documents selected" :selectionLimit="1000"
                            :virtualScrollerOptions="{ itemSize: 44 }" :loading="optionsLoading"
                            :disabled="optionsLoading || !!optionsError" class="w-full" />
                        <!-- <p v-if="optionsError" class="mt-2 text-sm text-red-600">{{ optionsError }}</p>
                        <p v-else class="mt-2 text-xs text-slate-500">
                            {{ filters.invoice_ids.length ? `${filters.invoice_ids.length} selected — preview, PDF and ZIP include only these documents.` : 'Leave empty to export all matching documents. Numbers follow the date range, subtype and other filters.' }}
                        </p> -->
                    </div>

                    <!-- Form Action Footer (Mirroring LedgerReport) -->
                    <div
                        class="col-span-full flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4 mt-2 border-t pt-6">
                        <!-- <div class="text-xs text-slate-500 space-y-0.5">
                            <p>Dates use the document date. Ledger matches the document account or its journal lines.</p>
                            <p class="font-medium text-slate-600">
                                Max 100 documents per combined PDF export booklet. Cancelled/deleted records excluded.
                            </p>
                        </div> -->

                        <div class="flex items-end gap-3 self-end sm:self-auto">
                            <BaseButton type="button" variant="outlined" severity="secondary" :disabled="busy"
                                @click="resetFilters">
                                Reset
                            </BaseButton>

                            <BaseButton type="submit" variant="filled" severity="primary" :loading="busy">
                                <ArrowPathIcon class="h-4 w-4 mr-2" />
                                Generate Report
                            </BaseButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Background ZIP Processing Banner (When modal is minimized/closed) -->
        <div v-if="zipStatus && zipStatus.status === 'processing' && !showZipModal"
            class="rounded-xl bg-indigo-50 border border-indigo-200 p-4 flex items-center justify-between shadow-xs no-print">
            <div class="flex items-center gap-3">
                <span
                    class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin shrink-0"></span>
                <div>
                    <span class="text-xs font-bold text-indigo-900 block">
                        Background ZIP Export in Progress: {{ zipStatus.progress || 0 }}%
                    </span>
                    <span class="text-[11px] text-indigo-700">
                        {{ zipStatus.message || `Processed ${zipStatus.processed || 0} of ${zipStatus.total || 0}
                        documents` }}
                    </span>
                </div>
            </div>
            <BaseButton variant="outlined" severity="secondary" size="small" @click="showZipModal = true">
                View Progress
            </BaseButton>
        </div>

        <!-- Limit Exceeded Alert Banner -->
        <div v-if="result?.count && result.count > 100"
            class="rounded-xl bg-amber-50 border border-amber-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-2xs no-print">
            <div class="flex items-start gap-3">
                <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div class="text-xs text-amber-950 leading-relaxed">
                    <div class="font-bold text-sm text-amber-950">
                        Large Volume: {{ result.count }} Documents Found
                    </div>
                    <p class="mt-0.5 text-amber-900">
                        The preview grid displays the first 100 records. Single combined PDF booklets are limited to 100
                        pages, but you can export all <strong>{{ result.count }} documents</strong> in the background
                        via <strong>ZIP Export</strong>.
                    </p>
                </div>
            </div>
            <BaseButton v-if="allowExport" variant="filled" severity="success" size="small" :loading="exportingZip" @click="runZipExport"
                class="shrink-0 self-start sm:self-center">
                <FolderArrowDownIcon class="w-4 h-4 mr-1.5" />
                {{ filters.invoice_ids.length ? 'Export Selected to ZIP' : 'Export All to ZIP' }} ({{ result.count }})
            </BaseButton>
        </div>

        <!-- Error Alert -->
        <div v-if="error"
            class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-700 flex items-start justify-between shadow-sm no-print">
            <div>{{ error }}</div>
            <button type="button" @click="error = ''" class="font-bold text-rose-600 hover:text-rose-800">
                Dismiss
            </button>
        </div>

        <!-- Report Content -->
        <div v-if="result?.documents?.length" class="space-y-6">
            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 no-print">
                <div
                    class="border border-slate-200 rounded p-4 bg-slate-50/50 flex justify-between items-center shadow-2xs">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total
                            Documents</span>
                        <span class="text-xl font-black text-[#1d2d3e] mt-1 block">{{ result.count }} Documents</span>
                        <span class="text-[10px] font-semibold text-slate-500 mt-0.5 block">
                            {{ invoiceCount }} Invoices &bull; {{ billCount }} Bills
                        </span>
                    </div>
                    <div class="p-2.5 bg-indigo-50 rounded text-indigo-600 border border-indigo-100">
                        <DocumentDuplicateIcon class="w-5 h-5" />
                    </div>
                </div>

                <div
                    class="border border-slate-200 rounded p-4 bg-slate-50/50 flex justify-between items-center shadow-2xs">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Tax
                            Breakdown</span>
                        <span class="text-xl font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(totalTaxAmount)
                        }}</span>
                        <span class="text-[10px] font-semibold text-slate-500 mt-0.5 block">CGST + SGST + IGST</span>
                    </div>
                    <div class="p-2.5 bg-slate-100 rounded text-slate-600 border border-slate-200">
                        <BanknotesIcon class="w-5 h-5" />
                    </div>
                </div>

                <div
                    class="border border-[#c5e0b4] rounded p-4 bg-[#e2f0d9] flex justify-between items-center shadow-2xs">
                    <div>
                        <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Grand Invoiced
                            Value</span>
                        <span class="text-xl font-black text-[#385723] mt-1 block">{{ formatCurrency(totalGrossAmount)
                        }}</span>
                        <span class="text-[10px] font-semibold text-[#385723]/80 mt-0.5 block">Cumulative Gross
                            Total</span>
                    </div>
                    <div class="p-2.5 bg-white/80 rounded text-[#385723] border border-[#c5e0b4]">
                        <CheckCircleIcon class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Statement Table Card (Styled matching Standard Double-Entry Ledger) -->
            <div
                class="bg-white rounded border border-slate-200 shadow-sm overflow-hidden print:border-none print:shadow-none">
                <!-- Card Header -->
                <div
                    class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/50 flex flex-wrap justify-between items-center gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-[#1d2d3e] uppercase tracking-wider">Statement Result
                            Grid</span>
                        <span
                            class="text-[10px] px-2 py-0.5 rounded font-bold bg-blue-50 text-[#0064d2] border border-blue-100">
                            Showing {{ result.documents.length }} of {{ result.count }} Documents
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-500 font-medium">
                        Period: <span class="font-bold text-slate-700">{{ filters.start_date }}</span> to <span
                            class="font-bold text-slate-700">{{ filters.end_date }}</span>
                    </div>
                </div>

                <!-- Print Header (Hidden on screen) -->
                <div class="hidden print:block p-6 text-center border-b-2 border-slate-900 mb-6">
                    <h1 class="text-2xl font-black uppercase tracking-widest">Bulk Invoice / Bill Statement</h1>
                    <p class="text-sm font-bold mt-1 text-slate-600">
                        Period: {{ filters.start_date }} to {{ filters.end_date }}
                    </p>
                </div>

                <!-- Table Grid -->
                <div class="min-w-0 p-4 sm:p-5">
                    <div class="statement-scroll border border-slate-200 rounded" role="region"
                        aria-label="Statement result grid, scroll to view all documents and amounts" tabindex="0">
                        <table class="statement-table w-full text-left border-collapse min-w-[850px]">
                            <thead>
                                <tr
                                    class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                    <th class="py-3 px-3 text-center" width="4%">#</th>
                                    <th class="py-3 px-3 text-center" width="10%">Date</th>
                                    <th class="py-3 px-4" width="16%">Document No</th>
                                    <th class="py-3 px-3 text-center" width="10%">Type</th>
                                    <th class="py-3 px-3" width="14%">Subtype</th>
                                    <th class="py-3 px-4" width="24%">Customer / Vendor</th>
                                    <th class="py-3 px-4 text-right" width="11%">Tax</th>
                                    <th class="py-3 px-4 text-right" width="11%">Total (INR)</th>
                                </tr>
                            </thead>
                            <tbody class="text-[11px] font-semibold text-slate-700">
                                <tr v-for="(doc, idx) in result.documents" :key="doc.id"
                                    class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors even:bg-slate-50/30">
                                    <td class="py-3 px-3 text-center text-slate-400 font-mono text-[10px]">
                                        {{ idx + 1 }}
                                    </td>
                                    <td class="py-3 px-3 text-center text-slate-500 whitespace-nowrap font-medium">
                                        {{ doc.date }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded font-mono font-bold text-xs bg-indigo-50 text-indigo-700 border border-indigo-200/80">
                                            {{ doc.number }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                                            :class="doc.type === 'Invoice' ? 'bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]' : 'bg-[#e0e7ff] text-[#3730a3] border border-[#c7d2fe]'">
                                            {{ doc.type }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                            {{ doc.subtype || 'Standard' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-slate-800">
                                        <div class="truncate max-w-[280px]" :title="doc.patron">
                                            {{ doc.patron || '—' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-700 font-mono font-semibold">
                                        {{ formatCurrency(doc.tax) }}
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-900 font-black font-mono">
                                        {{ formatCurrency(doc.total) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-[#1d2d3e] text-white">
                                    <td colspan="6"
                                        class="py-3.5 px-6 text-right font-bold uppercase text-[10px] tracking-wider text-slate-300">
                                        Net Total Summary ({{ result.count }} Documents)
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-xs font-mono text-slate-200">
                                        {{ formatCurrency(totalTaxAmount) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-sm font-mono text-emerald-300">
                                        {{ formatCurrency(totalGrossAmount) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State (Styled identical to General Ledger Report) -->
        <div v-else-if="!busy" class="bg-white rounded-2xl p-20 text-center border border-dashed border-slate-300">
            <div class="mx-auto w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                <DocumentTextIcon class="h-8 w-8 text-slate-300" />
            </div>
            <h3 class="text-lg font-black text-slate-800">No documents found</h3>
            <p class="text-slate-500 max-w-xs mx-auto mt-2">
                There are no invoices or bills matching the selected filter criteria for this period.
            </p>
        </div>

        <!-- Asynchronous ZIP Export Progress Dialog -->
        <Dialog v-model:visible="showZipModal" modal :closable="true" header="Bulk Document ZIP Export"
            :style="{ width: '480px' }" class="premium-dialog">
            <div class="p-4 space-y-4">
                <!-- Status Header -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" :class="{
                        'bg-indigo-50 text-indigo-600': zipStatus?.status === 'queued' || zipStatus?.status === 'processing',
                        'bg-emerald-50 text-emerald-600': zipStatus?.status === 'completed',
                        'bg-rose-50 text-rose-600': zipStatus?.status === 'failed',
                    }">
                        <span v-if="zipStatus?.status === 'processing' || zipStatus?.status === 'queued'"
                            class="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
                        <CheckCircleIcon v-else-if="zipStatus?.status === 'completed'" class="w-6 h-6" />
                        <ExclamationTriangleIcon v-else class="w-6 h-6" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-bold text-slate-800">
                            <span v-if="zipStatus?.status === 'queued'">Preparing Export Job...</span>
                            <span v-else-if="zipStatus?.status === 'processing'">Exporting Documents in
                                Background</span>
                            <span v-else-if="zipStatus?.status === 'completed'">Export Archive Ready!</span>
                            <span v-else-if="zipStatus?.status === 'failed'">Export Failed</span>
                        </h4>
                        <p class="text-xs text-slate-500 truncate mt-0.5">
                            {{
                                zipStatus?.message || (zipStatus?.error ?
                                    zipStatus.error : 'Packaging invoices into ZIP file...') }}
                        </p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div v-if="zipStatus?.status === 'processing' || zipStatus?.status === 'queued'" class="space-y-1.5">
                    <div class="flex justify-between text-xs font-semibold text-slate-600">
                        <span>Progress</span>
                        <span class="font-mono">{{ zipStatus?.progress || 0 }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-indigo-600 h-full rounded-full transition-all duration-300"
                            :style="{ width: `${zipStatus?.progress || 0}%` }"></div>
                    </div>
                    <div class="flex justify-between text-[11px] text-slate-400">
                        <span>Processed {{ zipStatus?.processed || 0 }} of {{ zipStatus?.total || 0 }} documents</span>
                        <span>Chunked safe export</span>
                    </div>
                </div>

                <!-- Completed State -->
                <div v-if="zipStatus?.status === 'completed'"
                    class="p-3 bg-emerald-50 rounded-lg border border-emerald-100 space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-semibold text-emerald-800">Archive File:</span>
                        <span class="font-mono font-bold text-emerald-900">{{ zipStatus.filename }}</span>
                    </div>
                    <div v-if="zipStatus.file_size" class="flex justify-between items-center text-xs">
                        <span class="font-semibold text-emerald-800">Total Archive Size:</span>
                        <span class="font-mono font-bold text-emerald-900">{{ zipStatus.file_size }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-semibold text-emerald-800">Documents Included:</span>
                        <span class="font-mono font-bold text-emerald-900">{{ zipStatus.total }} Documents</span>
                    </div>
                </div>

                <!-- Failed State -->
                <div v-if="zipStatus?.status === 'failed'"
                    class="p-3 bg-rose-50 rounded-lg border border-rose-100 text-xs text-rose-700">
                    {{ zipStatus.error || 'An unexpected error occurred during export.' }}
                </div>

                <!-- Dialog Actions -->
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <BaseButton variant="outlined" severity="secondary" size="small" @click="showZipModal = false">
                        {{ zipStatus?.status === 'processing' ? 'Run in Background' : 'Close' }}
                    </BaseButton>

                    <BaseButton v-if="zipStatus?.status === 'completed'" variant="filled" severity="success"
                        size="small" @click="downloadZipFile">
                        <ArrowDownTrayIcon class="w-4 h-4 mr-1.5" />
                        Download ZIP ({{ zipStatus.file_size || 'Archive' }})
                    </BaseButton>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.statement-scroll {
    max-width: 100%;
    max-height: 60vh;
    overflow: auto;
    scrollbar-gutter: stable;
}

.statement-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f2f4f7;
}

.statement-table th:nth-last-child(-n + 2),
.statement-table td:nth-last-child(-n + 2) {
    white-space: nowrap;
}

.statement-table tr> :last-child {
    position: sticky;
    right: 0;
    z-index: 1;
    box-shadow: -1px 0 0 #e2e8f0;
}

.statement-table tbody tr> :last-child {
    background: #fff;
}

.statement-table tbody tr:nth-child(even)> :last-child,
.statement-table tbody tr:hover> :last-child {
    background: #f8fafc;
}

.statement-table thead tr> :last-child {
    z-index: 3;
}

.statement-table tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 2;
    background: #1d2d3e;
}

.statement-table tfoot tr> :last-child {
    z-index: 3;
}

@media print {
    .statement-scroll {
        max-height: none;
        overflow: visible;
        scrollbar-gutter: auto;
    }

    .statement-table {
        min-width: 0;
    }

    .statement-table thead th,
    .statement-table tr> :last-child,
    .statement-table tfoot td {
        position: static;
        box-shadow: none;
    }
}
</style>
