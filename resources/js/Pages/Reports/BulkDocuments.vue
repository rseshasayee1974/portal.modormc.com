<script setup lang="ts">
import { reactive, ref, computed, watch, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatCurrency } from '@/Utils/formatters';
import {
    DocumentTextIcon,
    ArrowPathIcon,
    PrinterIcon,
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    patrons?: Array<{ id: number; legal_name: string }>;
    ledgers?: Array<{ id: number; title: string }>;
    defaults?: { start_date?: string; end_date?: string };
}>();

const { can, isAdmin } = usePermissions();

const filters = reactive({
    start_date: props.defaults?.start_date || '',
    end_date: props.defaults?.end_date || '',
    type: '',
    subtype: '',
    patron_id: null as number | null,
    ledger_id: null as number | null,
    tax_type: '',
    reference: '',
});

const result = ref<{ count: number; documents: any[] } | null>(null);
const busy = ref(false);
const exportingPdf = ref(false);
const error = ref('');

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

const canExport = computed(() => {
    return (isAdmin.value || can('report.export')) && (result.value?.count ?? 0) > 0 && (result.value?.count ?? 0) <= 100;
});

const resetFilters = () => {
    filters.start_date = props.defaults?.start_date || '';
    filters.end_date = props.defaults?.end_date || '';
    filters.type = '';
    filters.subtype = '';
    filters.patron_id = null;
    filters.ledger_id = null;
    filters.tax_type = '';
    filters.reference = '';
    runPreview();
};

async function runPreview() {
    busy.value = true;
    error.value = '';
    try {
        const response = await axios.get(route('reports.bulk-documents.preview'), {
            params: { ...filters },
        });
        result.value = response.data;
    } catch (e: any) {
        let data = e.response?.data;
        if (data instanceof Blob) {
            try {
                data = JSON.parse(await data.text());
            } catch {
                data = null;
            }
        }
        error.value = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Unable to load preview documents.';
    } finally {
        busy.value = false;
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

const printReport = () => {
    window.print();
};

onMounted(() => {
    runPreview();
});
</script>

<template>
    <AppLayout title="Bulk Invoice / Bill Export">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Back Navigation -->
                <div class="no-print">
                    <Link :href="route('reports.index', { module: 'accounting' })"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors group">
                        <ArrowLeftIcon class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" />
                        <span>← Back to Accounting &amp; Finance reports</span>
                    </Link>
                </div>

                <!-- Filter Header (Similar to General Ledger Report UI) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8 no-print">
                    <!-- Card Top Bar -->
                    <div
                        class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-50/50">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-indigo-600 rounded-xl text-white shadow-lg shadow-indigo-100 shrink-0">
                                <DocumentTextIcon class="h-6 w-6" />
                            </div>
                            <div>
                                <h1 class="text-xl font-black text-slate-800 tracking-tight">Bulk Invoice / Bill Export
                                </h1>
                                <p class="text-sm text-slate-500 font-medium italic">One combined PDF, one document per
                                    A4 page</p>
                            </div>
                        </div>

                        <!-- Header Action Buttons -->
                        <div class="flex items-center gap-3">
                            <BaseButton variant="outlined" severity="secondary" @click="printReport">
                                <PrinterIcon class="h-4 w-4 mr-2" />
                                Print
                            </BaseButton>

                            <BaseButton v-if="isAdmin || can('report.export')" variant="filled" severity="success"
                                :disabled="!canExport || exportingPdf" :loading="exportingPdf" @click="runExport">
                                <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
                                Download Combined PDF
                                <span v-if="result?.count && result.count <= 100" class="ml-1 text-xs opacity-90">
                                    ({{ result.count }} pgs)
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
                                <BaseSelect v-model="filters.subtype" label="Subtype" :options="subtypes"
                                    optionLabel="label" optionValue="value" placeholder="All Subtypes"
                                    :disabled="busy" />
                            </div>

                            <div>
                                <BaseSelect v-model="filters.patron_id" label="Patron / Subledger"
                                    :options="patrons || []" optionLabel="legal_name" optionValue="id"
                                    placeholder="All customers &amp; vendors" filter showClear :disabled="busy" />
                            </div>

                            <div>
                                <BaseSelect v-model="filters.ledger_id" label="Ledger Account" :options="ledgers || []"
                                    optionLabel="title" optionValue="id" placeholder="All ledgers" filter showClear
                                    :disabled="busy" />
                            </div>

                            <div>
                                <BaseSelect v-model="filters.tax_type" label="Tax Treatment" :options="taxOptions"
                                    optionLabel="label" optionValue="value" placeholder="All tax treatments"
                                    :disabled="busy" />
                            </div>

                            <div>
                                <BaseInput v-model="filters.reference" label="Invoice / Journal / Voucher Number"
                                    placeholder="Search document or voucher number..." :disabled="busy" />
                            </div>

                            <!-- Form Action Footer (Mirroring LedgerReport) -->
                            <div
                                class="col-span-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-2 border-t pt-6">
                                <div class="text-xs text-slate-500 space-y-0.5">
                                    <p>Dates use the document date. Ledger matches the document account or its journal
                                        lines.</p>
                                    <p class="font-medium text-slate-600">
                                        Max 100 documents per combined PDF export booklet. Cancelled/deleted records
                                        excluded.
                                    </p>
                                </div>

                                <div class="flex items-center gap-3 self-end sm:self-auto">
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

                <!-- Limit Exceeded Alert Banner -->
                <div v-if="result?.count && result.count > 100"
                    class="rounded-2xl bg-amber-50 border border-amber-200 p-4 sm:p-5 flex items-start gap-3.5 shadow-sm no-print">
                    <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="text-xs text-amber-900 leading-relaxed">
                        <div class="font-bold text-sm text-amber-950">
                            Showing the first 100 of {{ result.count }} documents
                        </div>
                        <p class="mt-0.5">
                            Combined PDF export compiles up to 100 documents per batch to ensure optimal print quality.
                            Please narrow the date range or choose a specific customer/vendor to export.
                        </p>
                    </div>
                </div>

                <!-- Error Alert -->
                <div v-if="error"
                    class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-700 flex items-start justify-between shadow-sm no-print">
                    <div>{{ error }}</div>
                    <button type="button" @click="error = ''" class="font-bold text-rose-600 hover:text-rose-800">
                        Dismiss
                    </button>
                </div>

                <!-- Report Content (Styled identical to General Ledger Report) -->
                <div v-if="result?.documents?.length"
                    class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden print:shadow-none print:border-none">
                    <!-- Report Header (Visible in Print) -->
                    <div class="hidden print:block p-8 text-center border-b-2 border-slate-900 mb-8">
                        <h1 class="text-3xl font-black uppercase tracking-widest">Bulk Invoice / Bill Statement</h1>
                        <p class="text-lg font-bold mt-2">
                            {{ filters.type ?
                                (filters.type === 'invoice' ? 'Sales Invoices' : 'Vendor Bills') :
                                'All Invoices & Bills' }}
                        </p>
                        <p class="text-sm mt-1 text-slate-600 italic">
                            Period: {{ filters.start_date }} to {{ filters.end_date }}
                        </p>
                    </div>

                    <div class="p-6 sm:p-8 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-100">
                                    <th class="pb-4 font-black">Date</th>
                                    <th class="pb-4 font-black">Document No</th>
                                    <th class="pb-4 font-black">Type</th>
                                    <th class="pb-4 font-black">Subtype</th>
                                    <th class="pb-4 font-black">Customer / Vendor</th>
                                    <th class="pb-4 text-right font-black">Tax</th>
                                    <th class="pb-4 text-right font-black">Total</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium">
                                <tr v-for="doc in result.documents" :key="doc.id"
                                    class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 text-slate-600 whitespace-nowrap">{{ doc.date }}</td>
                                    <td class="py-4 font-mono font-black text-indigo-600 tracking-tight">{{ doc.number
                                        }}</td>
                                    <td class="py-4">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider whitespace-nowrap"
                                            :class="doc.type === 'Invoice' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200'">
                                            {{ doc.type }}
                                        </span>
                                    </td>
                                    <td class="py-4 text-slate-500 font-medium text-xs">{{ doc.subtype || '—' }}</td>
                                    <td class="py-4 font-bold text-slate-800">{{ doc.patron || '—' }}</td>
                                    <td class="py-4 text-right text-slate-900 font-bold font-mono">
                                        {{ formatCurrency(doc.tax) }}
                                    </td>
                                    <td class="py-4 text-right font-black text-slate-800 font-mono">
                                        {{ formatCurrency(doc.total) }}
                                    </td>
                                </tr>

                                <!-- Summary Footer (Indigo Bar matching LedgerReport) -->
                                <tr class="bg-indigo-900 text-white shadow-xl shadow-indigo-100">
                                    <td colspan="5"
                                        class="py-6 px-4 text-right font-black uppercase tracking-widest text-[11px]">
                                        Total Summary ({{ result.count }} Documents)
                                    </td>
                                    <td class="py-6 px-4 text-right font-black text-lg font-mono">
                                        {{ formatCurrency(totalTaxAmount) }}
                                    </td>
                                    <td class="py-6 px-8 text-right font-black text-xl font-mono">
                                        {{ formatCurrency(totalGrossAmount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State (Styled identical to General Ledger Report) -->
                <div v-else-if="!busy"
                    class="bg-white rounded-2xl p-20 text-center border border-dashed border-slate-300">
                    <div class="mx-auto w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                        <DocumentTextIcon class="h-8 w-8 text-slate-300" />
                    </div>
                    <h3 class="text-lg font-black text-slate-800">No documents found</h3>
                    <p class="text-slate-500 max-w-xs mx-auto mt-2">
                        There are no invoices or bills matching the selected filter criteria for this period.
                    </p>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    body {
        background: white !important;
    }
}
</style>
