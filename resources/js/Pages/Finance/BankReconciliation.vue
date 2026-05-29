<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import {
    ArrowUpTrayIcon,
    CheckCircleIcon,
    LinkIcon,
    LinkSlashIcon,
    PlusIcon,
    BanknotesIcon,
    ArrowPathIcon,
    ExclamationCircleIcon,
    ClipboardDocumentCheckIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ShieldCheckIcon,
    SparklesIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import axios from 'axios';
import Swal from 'sweetalert2'; 

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';

const props = defineProps<{
    ledgers: any[];
    offsetLedgers: any[];
}>();

const selectedLedgerId = ref<number | null>(null);
const statementLines = ref<any[]>([]);
const selectedLine = ref<any | null>(null);
const isLoadingLines = ref(false);
const isUploading = ref(false);

// Drag & drop file upload state
const isDragActive = ref(false);

// Search and filter state
const searchQuery = ref('');
const statusFilter = ref<'all' | 'matched' | 'pending'>('all');
const typeFilter = ref<'all' | 'debit' | 'credit'>('all');

const ledgerOptions = computed(() => {
    return props.ledgers.map(l => ({
        label: `${l.code ?? 'LED'} - ${l.title}`,
        value: l.id
    }));
});

const offsetLedgerOptions = computed(() => {
    return props.offsetLedgers.map(l => ({
        label: `${l.code ?? 'LED'} - ${l.title}`,
        value: l.id
    }));
});

// BRS Statistics
const totalCount = computed(() => statementLines.value.length);
const matchedCount = computed(() => statementLines.value.filter(l => l.reconciled_line_id).length);
const pendingCount = computed(() => statementLines.value.filter(l => !l.reconciled_line_id).length);
const matchedPercentage = computed(() => {
    if (totalCount.value === 0) return 0;
    return Math.round((matchedCount.value / totalCount.value) * 100);
});

// Filtered statement lines for DataTable
const filteredStatementLines = computed(() => {
    let lines = statementLines.value;

    // Search query filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        lines = lines.filter(l => 
            (l.description && l.description.toLowerCase().includes(query)) ||
            (l.reference_no && l.reference_no.toLowerCase().includes(query)) ||
            (l.transaction_date && l.transaction_date.includes(query))
        );
    }

    // Status filter
    if (statusFilter.value === 'matched') {
        lines = lines.filter(l => l.reconciled_line_id);
    } else if (statusFilter.value === 'pending') {
        lines = lines.filter(l => !l.reconciled_line_id);
    }

    // Type filter
    if (typeFilter.value === 'debit') {
        lines = lines.filter(l => l.debit > 0);
    } else if (typeFilter.value === 'credit') {
        lines = lines.filter(l => l.credit > 0);
    }

    return lines;
});

// Upload form
const uploadForm = useForm({
    bank_ledger_id: null as number | null,
    statement_file: null as File | null,
});

// Quick Voucher form
const quickVoucherForm = useForm({
    statement_line_id: null as number | null,
    opposite_ledger_id: null as number | null,
    narration: '',
});

// Helper: Format INR currency
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
};

// Fetch statement lines for chosen ledger
const fetchLines = async () => {
    if (!selectedLedgerId.value) {
        statementLines.value = [];
        selectedLine.value = null;
        return;
    }

    isLoadingLines.value = true;
    try {
        const res = await axios.get(route('reconciliation.lines'), {
            params: { bank_ledger_id: selectedLedgerId.value }
        });
        statementLines.value = res.data.data;
        
        // Keep selection if it exists in the new list, otherwise pick first or null
        if (selectedLine.value) {
            const updated = statementLines.value.find(l => l.id === selectedLine.value.id);
            selectedLine.value = updated || null;
        } else if (statementLines.value.length > 0) {
            selectedLine.value = statementLines.value[0];
        }
    } catch (err: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.response?.data?.message || 'Failed to fetch statement records.'
        });
    } finally {
        isLoadingLines.value = false;
    }
};

// Watch ledger change
watch(selectedLedgerId, () => {
    fetchLines();
});

// Handle file drop/change
const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        uploadForm.statement_file = target.files[0];
    }
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    isDragActive.value = true;
};

const handleDragLeave = () => {
    isDragActive.value = false;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragActive.value = false;
    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        const file = e.dataTransfer.files[0];
        if (file.name.endsWith('.csv')) {
            uploadForm.statement_file = file;
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid File',
                text: 'Only CSV files are supported for bank statement import.'
            });
        }
    }
};

const clearSelectedFile = () => {
    uploadForm.statement_file = null;
};

// Submit BRS Statement Upload
const submitUpload = () => {
    if (!selectedLedgerId.value) {
        Swal.fire({ icon: 'error', title: 'Required', text: 'Please select a Bank account ledger first.' });
        return;
    }
    if (!uploadForm.statement_file) {
        Swal.fire({ icon: 'error', title: 'Required', text: 'Please select a CSV statement file.' });
        return;
    }

    isUploading.value = true;
    uploadForm.bank_ledger_id = selectedLedgerId.value;
    
    uploadForm.post(route('reconciliation.upload'), {
        onSuccess: () => {
            uploadForm.reset('statement_file');
            fetchLines();
            Swal.fire({ icon: 'success', title: 'Import Complete', text: 'Bank statement rows imported successfully!' });
        },
        onError: (errors) => {
            Swal.fire({ icon: 'error', title: 'Import Failed', text: errors.statement_file || 'Failed to parse file.' });
        },
        onFinish: () => {
            isUploading.value = false;
        }
    });
};

// Reconcile line with matching suggestion
const reconcileLine = async (journalLineId: number) => {
    if (!selectedLine.value) return;

    try {
        const res = await axios.post(route('reconciliation.reconcile'), {
            statement_line_id: selectedLine.value.id,
            journal_line_id: journalLineId
        });

        Swal.fire({ icon: 'success', title: 'Reconciled', text: res.data.message, timer: 1500, showConfirmButton: false });
        await fetchLines();
    } catch (err: any) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.response?.data?.message || 'Reconciliation failed.' });
    }
};

// Unreconcile linked line
const unreconcileLine = async (statementLineId: number) => {
    try {
        const res = await axios.post(route('reconciliation.unreconcile'), {
            statement_line_id: statementLineId
        });

        Swal.fire({ icon: 'success', title: 'Unlinked', text: res.data.message, timer: 1500, showConfirmButton: false });
        await fetchLines();
    } catch (err: any) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.response?.data?.message || 'Unlink failed.' });
    }
};

// Quick Create Voucher
const submitQuickVoucher = async () => {
    if (!selectedLine.value) return;
    if (!quickVoucherForm.opposite_ledger_id) {
        Swal.fire({ icon: 'error', title: 'Required', text: 'Please select an offsetting account ledger.' });
        return;
    }

    quickVoucherForm.statement_line_id = selectedLine.value.id;

    try {
        const res = await axios.post(route('reconciliation.create-voucher'), quickVoucherForm.data());
        Swal.fire({ icon: 'success', title: 'Created & Reconciled', text: res.data.message });
        quickVoucherForm.reset('opposite_ledger_id', 'narration');
        await fetchLines();
    } catch (err: any) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.response?.data?.message || 'Failed to create voucher.' });
    }
};

const selectLineRow = (line: any) => {
    selectedLine.value = line;
};

const downloadSampleCSV = () => {
    const headers = "Date,Particulars,Reference,Debit,Credit,Balance\n";
    const row1 = "2026-05-01,Office Rent Payment,CHQ-12345,15000.00,0.00,35000.00\n";
    const row2 = "2026-05-05,Client Invoice Payment,TXN-987654,0.00,28500.00,63500.00\n";
    const row3 = "2026-05-10,Bank Interest Earned,INT-999,0.00,120.00,63620.00\n";
    const csvContent = headers + row1 + row2 + row3;
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "sample_bank_statement.csv");
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <AppLayout title="Bank Reconciliation">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-800 dark:text-slate-200">
            <div class="max-w-[1600px] mx-auto space-y-6">

                <!-- TOP CONTROL BAR / DASHBOARD HEADER -->
                <div class="bg-white dark:bg-slate-900 shadow-sm border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 transition duration-300">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        
                        <!-- Page Title & Icon -->
                        <div class="flex items-center gap-4">
                            <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 dark:from-indigo-600 dark:to-indigo-800 p-3 rounded-2xl shadow-md text-white">
                                <BanknotesIcon class="w-7 h-7" />
                            </div>
                            <div>
                                <h1 class="text-xl font-bold tracking-tight text-slate-950 dark:text-white">Bank Reconciliation</h1>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Match uploaded bank statement lines against internal ledger transactions.</p>
                            </div>
                        </div>

                        <!-- Ledger Select Picker -->
                        <div class="flex items-center gap-3 w-full md:w-96 justify-end">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 whitespace-nowrap">Bank Ledger</label>
                            <BaseSelect 
                                v-model="selectedLedgerId" 
                                :options="ledgerOptions" 
                                optionLabel="label" 
                                optionValue="value" 
                                filter 
                                placeholder="Select bank ledger account..." 
                                class="w-full shadow-sm" 
                            />
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC STATS PANEL (Visible only when ledger is selected and has lines) -->
                <div v-if="selectedLedgerId && totalCount > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- Total Count Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
                        <div class="bg-slate-100 dark:bg-slate-800 p-3 rounded-2xl text-slate-500 dark:text-slate-400">
                            <ClipboardDocumentCheckIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Total Transactions</span>
                            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ totalCount }}</span>
                        </div>
                    </div>

                    <!-- Matched Count Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
                        <div class="bg-emerald-50 dark:bg-emerald-950/30 p-3 rounded-2xl text-emerald-600 dark:text-emerald-400">
                            <ShieldCheckIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Matched (Reconciled)</span>
                            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ matchedCount }}</span>
                        </div>
                    </div>

                    <!-- Pending Count Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
                        <div class="bg-amber-50 dark:bg-amber-950/30 p-3 rounded-2xl text-amber-600 dark:text-amber-400 animate-pulse-slow">
                            <ExclamationCircleIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Pending Match</span>
                            <span class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ pendingCount }}</span>
                        </div>
                    </div>

                    <!-- Reconciliation Progress Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Completion Rate</span>
                            <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">{{ matchedPercentage }}%</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden">
                            <div 
                                class="bg-indigo-600 h-full rounded-full transition-all duration-500 ease-out shadow-sm"
                                :style="{ width: matchedPercentage + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- MAIN WORKSPACE -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    
                    <!-- LEFT COLUMN: IMPORTED STATEMENT ROWS -->
                    <div class="lg:col-span-7 xl:col-span-8 bg-white dark:bg-slate-900 shadow-sm rounded-3xl border border-slate-200/80 dark:border-slate-800 overflow-hidden flex flex-col min-h-[600px] transition duration-300">
                        
                        <!-- Left Panel Header -->
                        <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div class="flex items-center gap-2">
                                <ClipboardDocumentCheckIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                <span class="font-extrabold text-xs uppercase tracking-widest text-slate-700 dark:text-slate-300">Statement Transactions</span>
                            </div>
                            
                            <!-- Search & Filter Actions -->
                            <div v-if="selectedLedgerId && statementLines.length > 0" class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                                <!-- Refresh Button -->
                                <Button 
                                    icon="pi pi-refresh" 
                                    severity="secondary" 
                                    text 
                                    rounded 
                                    size="small" 
                                    class="!p-2.5 hover:!bg-slate-200/50 dark:hover:!bg-slate-800"
                                    @click="fetchLines" 
                                    :disabled="isLoadingLines" 
                                />
                            </div>
                        </div>

                        <!-- Fallback: Empty / Select Ledger -->
                        <div v-if="!selectedLedgerId" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-slate-50/30 dark:bg-slate-900/10">
                            <ExclamationCircleIcon class="w-14 h-14 text-slate-300 dark:text-slate-700 mb-3" />
                            <h4 class="text-sm font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide">No Bank Account Selected</h4>
                            <p class="text-xs text-slate-400 dark:text-slate-500 max-w-xs mt-1.5 leading-relaxed">Select a Bank Ledger in the top bar to display reconciliation lines and match records.</p>
                        </div>

                        <!-- Fallback: Upload statement if empty -->
                        <div v-else-if="statementLines.length === 0 && !isLoadingLines" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-slate-50/10 dark:bg-slate-900/5">
                            
                            <!-- Drag and Drop upload zone -->
                            <div 
                                class="border-2 border-dashed rounded-3xl p-10 max-w-lg w-full flex flex-col items-center transition duration-200 cursor-pointer"
                                :class="isDragActive ? 'border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'"
                                @dragover="handleDragOver"
                                @dragleave="handleDragLeave"
                                @drop="handleDrop"
                            >
                                <div class="bg-indigo-50 dark:bg-indigo-950/40 p-4 rounded-2xl text-indigo-600 dark:text-indigo-400 mb-4">
                                    <ArrowUpTrayIcon class="w-10 h-10" />
                                </div>
                                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Upload Bank CSV Statement</h4>
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 mb-6 text-center max-w-sm leading-relaxed">
                                    Drag and drop your bank statement file here, or select from your computer. CSV format with date, description, ref, debit, and credit columns is required.
                                </p>
                                
                                <input 
                                    type="file" 
                                    id="statement-csv" 
                                    accept=".csv" 
                                    class="hidden" 
                                    @change="handleFileChange" 
                                />

                                <div v-if="!uploadForm.statement_file" class="flex flex-col items-center gap-2">
                                    <label for="statement-csv" class="cursor-pointer bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-[10px] font-bold uppercase tracking-widest py-3 px-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 transition">
                                        Choose CSV File
                                    </label>
                                </div>
                                
                                <div v-else class="w-full flex flex-col items-center bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 p-4 rounded-2xl">
                                    <div class="flex items-center justify-between w-full">
                                        <div class="flex items-center gap-2 truncate pr-4">
                                            <span class="pi pi-file text-indigo-600 font-bold"></span>
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate">{{ uploadForm.statement_file.name }}</span>
                                        </div>
                                        <button @click.stop="clearSelectedFile" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition p-1">
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                    
                                    <Button 
                                        label="IMPORT STATEMENT" 
                                        severity="success" 
                                        class="mt-4 px-6 py-2.5 w-full text-[10px] font-black uppercase tracking-widest shadow-sm rounded-xl" 
                                        size="small"
                                        :loading="isUploading"
                                        @click="submitUpload" 
                                    />
                                </div>

                                <!-- Expected Format Helper Section -->
                                <div class="mt-8 pt-6 border-t border-slate-200/80 dark:border-slate-800/80 w-full" @click.stop>
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">Expected CSV Format</h5>
                                        <button 
                                            @click="downloadSampleCSV"
                                            class="text-[10px] text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-bold uppercase tracking-wider flex items-center gap-1 cursor-pointer transition bg-transparent border-0 p-0"
                                        >
                                            <span class="pi pi-download text-[9px]"></span>
                                            Sample Template
                                        </button>
                                    </div>
                                    <div class="overflow-x-auto w-full border border-slate-200/60 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-950/50 shadow-inner">
                                        <table class="w-full text-[9px] text-left border-collapse">
                                            <thead>
                                                <tr class="bg-slate-100/80 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 font-bold">
                                                    <th class="py-2 px-3">Date</th>
                                                    <th class="py-2 px-3">Particulars</th>
                                                    <th class="py-2 px-3">Reference</th>
                                                    <th class="py-2 px-3 text-right">Debit</th>
                                                    <th class="py-2 px-3 text-right">Credit</th>
                                                    <th class="py-2 px-3 text-right">Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-900 font-mono text-slate-500 dark:text-slate-400">
                                                <tr>
                                                    <td class="py-1.5 px-3">2026-05-01</td>
                                                    <td class="py-1.5 px-3">Office Rent</td>
                                                    <td class="py-1.5 px-3">CHQ-12345</td>
                                                    <td class="py-1.5 px-3 text-right text-rose-500 font-semibold">15,000.00</td>
                                                    <td class="py-1.5 px-3 text-right">0.00</td>
                                                    <td class="py-1.5 px-3 text-right">35,000.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1.5 px-3">2026-05-05</td>
                                                    <td class="py-1.5 px-3">Client Invoice</td>
                                                    <td class="py-1.5 px-3">TXN-987654</td>
                                                    <td class="py-1.5 px-3 text-right">0.00</td>
                                                    <td class="py-1.5 px-3 text-right text-emerald-500 font-semibold">28,500.00</td>
                                                    <td class="py-1.5 px-3 text-right">63,500.00</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-2 text-left leading-relaxed">
                                        * Note: System maps columns containing similar words (e.g. particular/narration/description, ref/cheque/chq, debit/withdrawal/dr/out, credit/deposit/cr/in).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Statement Table with search & filtering -->
                        <div v-else class="flex-1 flex flex-col overflow-hidden">
                            
                            <!-- Internal Search & Filters Bar -->
                            <div class="p-4 border-b border-slate-250/60 dark:border-slate-800 bg-slate-50/20 dark:bg-slate-900/10 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                
                                <!-- Text Search -->
                                <div class="relative md:col-span-4">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <MagnifyingGlassIcon class="h-4 h-4 text-slate-400" />
                                    </span>
                                    <input 
                                        v-model="searchQuery" 
                                        type="text" 
                                        placeholder="Search statement..." 
                                        class="w-full pl-9 pr-4 py-2 border border-slate-200 dark:border-slate-800 rounded-xl bg-white dark:bg-slate-950 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150"
                                    />
                                </div>

                                <!-- Filter pill row -->
                                <div class="md:col-span-8 flex flex-wrap items-center gap-4 justify-start md:justify-end">
                                    
                                    <!-- Status filter -->
                                    <div class="flex items-center gap-1.5">
                                        <FunnelIcon class="w-3.5 h-3.5 text-slate-400" />
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Status:</span>
                                        <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg border border-slate-200/50 dark:border-slate-700/50">
                                            <button 
                                                v-for="status in ['all', 'pending', 'matched']" 
                                                :key="status"
                                                @click="statusFilter = status as any"
                                                class="px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider rounded-md transition"
                                                :class="statusFilter === status 
                                                    ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-sm font-black' 
                                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                                            >
                                                {{ status }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Type filter -->
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Flow:</span>
                                        <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg border border-slate-200/50 dark:border-slate-700/50">
                                            <button 
                                                v-for="type in ['all', 'debit', 'credit']" 
                                                :key="type"
                                                @click="typeFilter = type as any"
                                                class="px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider rounded-md transition"
                                                :class="typeFilter === type 
                                                    ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-sm font-black' 
                                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                                            >
                                                {{ type === 'all' ? 'All' : type === 'debit' ? 'Out' : 'In' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- DataTable -->
                            <div class="flex-1 overflow-y-auto min-h-[480px]">
                                <DataTable 
                                    :value="filteredStatementLines" 
                                    class="modern-table brs-table border-0"
                                    selectionMode="single" 
                                    :selection="selectedLine" 
                                    @row-click="(e) => selectLineRow(e.data)" 
                                    stripedRows
                                    :loading="isLoadingLines"
                                    dataKey="id"
                                >
                                    <Column field="transaction_date" header="Date" style="width: 110px" class="font-semibold text-slate-600 dark:text-slate-400" />
                                    
                                    <Column header="Particulars / Description">
                                        <template #body="slotProps">
                                            <div class="font-semibold text-slate-900 dark:text-slate-100 max-w-[280px] break-words" :title="slotProps.data.description">
                                                {{ slotProps.data.description }}
                                            </div>
                                            <div v-if="slotProps.data.reference_no" class="flex items-center gap-1 mt-1">
                                                <span class="text-[9px] bg-slate-100 dark:bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded font-mono font-bold tracking-wide">
                                                    Ref: {{ slotProps.data.reference_no }}
                                                </span>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column header="Amount Flow" align="right" style="width: 180px">
                                        <template #body="slotProps">
                                            <div v-if="slotProps.data.debit > 0" class="flex items-center justify-end gap-1.5">
                                                <span class="text-rose-600 dark:text-rose-400 font-bold text-xs">
                                                    {{ formatCurrency(slotProps.data.debit) }}
                                                </span>
                                                <div class="bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 p-1 rounded-lg">
                                                    <ArrowDownIcon class="w-3 h-3 font-black" />
                                                </div>
                                            </div>
                                            <div v-if="slotProps.data.credit > 0" class="flex items-center justify-end gap-1.5">
                                                <span class="text-emerald-600 dark:text-emerald-400 font-bold text-xs">
                                                    {{ formatCurrency(slotProps.data.credit) }}
                                                </span>
                                                <div class="bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 p-1 rounded-lg">
                                                    <ArrowUpIcon class="w-3 h-3 font-black" />
                                                </div>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column header="Reconciled" align="center" style="width: 100px">
                                        <template #body="slotProps">
                                            <div class="flex items-center justify-center">
                                                <Tag 
                                                    v-if="slotProps.data.reconciled_line_id"
                                                    severity="success"
                                                    rounded 
                                                    class="px-2.5 py-0.5 text-[8px] font-extrabold tracking-wider uppercase flex items-center gap-1 shadow-sm"
                                                >
                                                    <span class="pi pi-check text-[7px]"></span>
                                                    Matched
                                                </Tag>
                                                <Tag 
                                                    v-else
                                                    severity="warn"
                                                    rounded 
                                                    class="px-2.5 py-0.5 text-[8px] font-extrabold tracking-wider uppercase flex items-center gap-1 shadow-sm"
                                                >
                                                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                                    Pending
                                                </Tag>
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: RECONCILIATION DETAILS / ACTION CARD -->
                    <div class="lg:col-span-5 flex flex-col gap-6">
                        
                        <!-- Upload Card (if data exists, show it as a collapsed utility card) -->
                        <div v-if="selectedLedgerId && statementLines.length > 0" class="bg-white dark:bg-slate-900 shadow-sm rounded-3xl p-5 border border-slate-200/80 dark:border-slate-800 transition duration-300">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500 block mb-3">Upload Statement</span>
                            <div class="flex gap-2 items-center">
                                <input 
                                    type="file" 
                                    id="statement-csv-mini" 
                                    accept=".csv" 
                                    class="hidden" 
                                    @change="handleFileChange" 
                                />
                                <label for="statement-csv-mini" class="flex-1 cursor-pointer bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 text-xs font-semibold py-2.5 px-4 rounded-xl border border-slate-200/80 dark:border-slate-800 text-center truncate hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition">
                                    {{ uploadForm.statement_file ? uploadForm.statement_file.name : 'Choose CSV File...' }}
                                </label>
                                <Button 
                                    v-if="uploadForm.statement_file"
                                    icon="pi pi-upload" 
                                    severity="success" 
                                    size="small"
                                    rounded
                                    class="shadow-sm"
                                    :loading="isUploading"
                                    @click="submitUpload" 
                                />
                            </div>
                        </div>

                        <!-- RECONCILIATION DETAIL WORKSPACE PANEL -->
                        <div class="bg-white dark:bg-slate-900 shadow-sm rounded-3xl border border-slate-200/80 dark:border-slate-800 overflow-hidden flex flex-col min-h-[500px] transition duration-300">
                            
                            <!-- Match workspace header -->
                            <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <LinkIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                    <span class="font-extrabold text-xs uppercase tracking-widest text-slate-700 dark:text-slate-300">Reconciliation Workspace</span>
                                </div>
                                <span v-if="selectedLine" class="text-[9px] bg-slate-100 dark:bg-slate-850 px-2 py-0.5 rounded font-bold uppercase tracking-wider text-slate-500">
                                    {{ selectedLine.reconciled_line_id ? 'Completed' : 'Action Required' }}
                                </span>
                            </div>

                            <!-- Fallback: Select a line -->
                            <div v-if="!selectedLine" class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                                <ClipboardDocumentCheckIcon class="w-14 h-14 text-slate-250 dark:text-slate-750 mb-3" />
                                <h4 class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Select a transaction</h4>
                                <p class="text-xs text-slate-400 dark:text-slate-500 max-w-xs mt-1.5 leading-relaxed">Choose a transaction from the statement panel to review suggested ledger links or manually generate corresponding entries.</p>
                            </div>

                            <div v-else class="p-6 space-y-6 flex-1 flex flex-col justify-between overflow-y-auto">
                                
                                <!-- Statement Transaction Details Box -->
                                <div class="bg-slate-50/80 dark:bg-slate-950/80 p-5 rounded-2xl border border-slate-200/50 dark:border-slate-850 relative overflow-hidden">
                                    
                                    <!-- Background absolute pattern for premium look -->
                                    <div class="absolute right-[-10px] top-[-10px] text-slate-200/10 dark:text-slate-800/15 pointer-events-none">
                                        <BanknotesIcon class="w-24 h-24 rotate-12" />
                                    </div>

                                    <div class="relative flex justify-between items-start">
                                        <div class="space-y-1 max-w-[65%]">
                                            <span class="text-[9px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest block">Selected Transaction</span>
                                            <h4 class="text-sm font-bold text-slate-900 dark:text-white break-words leading-snug">{{ selectedLine.description }}</h4>
                                            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium block">
                                                Date: {{ selectedLine.transaction_date }} <span v-if="selectedLine.reference_no">| Ref: {{ selectedLine.reference_no }}</span>
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] font-bold text-slate-400 dark:text-slate-500 block uppercase tracking-wider">Amount</span>
                                            <span class="text-lg font-black block mt-0.5" :class="selectedLine.debit > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                                                {{ formatCurrency(selectedLine.debit > 0 ? selectedLine.debit : selectedLine.credit) }}
                                            </span>
                                            <span class="text-[9px] font-extrabold uppercase inline-block px-2 py-0.5 rounded-full mt-1 border" 
                                                :class="selectedLine.debit > 0 
                                                    ? 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 border-rose-100 dark:border-rose-900/30' 
                                                    : 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-900/30'"
                                            >
                                                {{ selectedLine.debit > 0 ? 'Withdrawal' : 'Deposit' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- CASE A: ALREADY RECONCILED -->
                                <div v-if="selectedLine.reconciled_line_id" class="flex-1 flex flex-col justify-between gap-6 pt-2">
                                    <div class="space-y-4">
                                        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                            <CheckCircleIcon class="w-5 h-5 font-bold" />
                                            <span class="text-xs font-black uppercase tracking-widest">Reconciliation Match Complete</span>
                                        </div>

                                        <div v-if="selectedLine.reconciled_line" class="border border-emerald-100 dark:border-emerald-900/30 bg-emerald-50/20 dark:bg-emerald-950/5 p-5 rounded-2xl space-y-3 shadow-inner">
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="font-bold text-slate-500 dark:text-slate-400">Voucher Number</span>
                                                <span class="font-black text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-700 font-mono">{{ selectedLine.reconciled_line.voucher_number }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="font-bold text-slate-500 dark:text-slate-400">Voucher Date</span>
                                                <span class="font-semibold text-slate-850 dark:text-slate-200">{{ selectedLine.reconciled_line.voucher_date }}</span>
                                            </div>
                                            <div class="flex justify-between items-start text-xs gap-4">
                                                <span class="font-bold text-slate-500 dark:text-slate-400 shrink-0">Particulars</span>
                                                <span class="font-semibold text-slate-850 dark:text-slate-200 text-right break-words max-w-[240px]">{{ selectedLine.reconciled_line.narration }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-xs pt-2.5 border-t border-emerald-100 dark:border-emerald-900/30">
                                                <span class="font-bold text-slate-500 dark:text-slate-400">Linked Amount</span>
                                                <span class="font-black text-indigo-600 dark:text-indigo-400 text-sm">
                                                    {{ formatCurrency(selectedLine.reconciled_line.debit > 0 ? selectedLine.reconciled_line.debit : selectedLine.reconciled_line.credit) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <Button 
                                        label="UNLINK TRANSACTION" 
                                        severity="danger" 
                                        outlined
                                        class="w-full py-3.5 text-[10px] font-black uppercase tracking-widest rounded-2xl border-rose-250 dark:border-rose-900 hover:!bg-rose-500/10 transition"
                                        @click="unreconcileLine(selectedLine.id)"
                                    />
                                </div>

                                <!-- CASE B: UNRECONCILED (SUGGESTIONS + QUICK ENTRY) -->
                                <div v-else class="flex-1 space-y-6 flex flex-col justify-between">
                                    
                                    <!-- Suggestions Section -->
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-1">
                                            <SparklesIcon class="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500 block">Suggested System Matches</span>
                                        </div>
                                        
                                        <!-- Suggestions List -->
                                        <div v-if="selectedLine.suggestions && selectedLine.suggestions.length > 0" class="space-y-3">
                                            <div 
                                                v-for="sug in selectedLine.suggestions" 
                                                :key="sug.id"
                                                class="border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 hover:border-indigo-300 dark:hover:border-indigo-950 p-4 rounded-2xl flex justify-between items-center transition duration-200 shadow-sm"
                                            >
                                                <div class="space-y-1.5 max-w-[78%]">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="font-bold text-xs text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border dark:border-slate-700 font-mono">{{ sug.voucher_number }}</span>
                                                        
                                                        <Tag 
                                                            :severity="sug.score >= 80 ? 'success' : sug.score >= 50 ? 'info' : 'warn'" 
                                                            class="px-2 text-[8px] font-extrabold tracking-wider scale-95"
                                                        >
                                                            {{ sug.score }}% Match
                                                        </Tag>
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 font-medium block">
                                                        Date: {{ sug.voucher_date }} <span v-if="sug.narration">| {{ sug.narration }}</span>
                                                    </div>
                                                    <!-- Progress Bar representation of Match Score -->
                                                    <div class="w-32 bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                                        <div 
                                                            class="h-full rounded-full transition-all duration-300"
                                                            :class="sug.score >= 80 ? 'bg-emerald-500' : sug.score >= 50 ? 'bg-indigo-500' : 'bg-amber-500'"
                                                            :style="{ width: sug.score + '%' }"
                                                        ></div>
                                                    </div>
                                                </div>
                                                
                                                <Button 
                                                    icon="pi pi-link" 
                                                    severity="primary" 
                                                    rounded 
                                                    size="small"
                                                    class="!w-9 !h-9 shadow-sm flex items-center justify-center shrink-0 hover:scale-105 transition"
                                                    @click="reconcileLine(sug.id)" 
                                                />
                                            </div>
                                        </div>
                                        
                                        <!-- No Suggestions Warning -->
                                        <div v-else class="text-center py-6 bg-slate-50/50 dark:bg-slate-900/10 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800/80">
                                            <span class="text-xs text-slate-400 dark:text-slate-500 font-medium">No system ledger matching suggestions found within range.</span>
                                        </div>
                                    </div>

                                    <!-- Quick Entry creation -->
                                    <div class="border-t border-slate-200/80 dark:border-slate-800/80 pt-6 space-y-4">
                                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500 block">Manual Post & Reconcile</span>
                                        
                                        <div class="space-y-4">
                                            <div class="flex flex-col gap-1.5">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Offsetting Account (Expense/Income/Asset/Liability) *</label>
                                                <BaseSelect 
                                                    v-model="quickVoucherForm.opposite_ledger_id" 
                                                    :options="offsetLedgerOptions" 
                                                    optionLabel="label" 
                                                    optionValue="value" 
                                                    filter 
                                                    placeholder="Select offsetting account..." 
                                                    class="w-full" 
                                                />
                                            </div>

                                            <div class="flex flex-col gap-1.5">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Voucher Narration / Remarks</label>
                                                <BaseInput 
                                                    v-model="quickVoucherForm.narration" 
                                                    placeholder="Specify custom details (e.g. Bank charges, Interest etc.)" 
                                                    class="w-full" 
                                                />
                                            </div>

                                            <Button 
                                                label="POST VOUCHER & RECONCILE" 
                                                severity="success" 
                                                class="w-full py-3.5 text-[10px] font-black uppercase tracking-widest rounded-2xl shadow-sm hover:scale-[1.01] transition"
                                                :disabled="!quickVoucherForm.opposite_ledger_id || quickVoucherForm.processing"
                                                @click="submitQuickVoucher" 
                                            />
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-slate-50/80 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 font-bold uppercase text-[9px] tracking-widest py-4 border-b border-slate-200 dark:border-slate-800;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply py-4 px-4 border-b border-slate-100 dark:border-slate-800 text-xs transition duration-150;
}
:deep(.p-datatable-tbody > tr:hover > td) {
    @apply bg-slate-50/50 dark:bg-slate-900/30 cursor-pointer;
}
:deep(.p-datatable-tbody > tr.p-highlight > td) {
    @apply bg-indigo-50/40 dark:bg-indigo-950/20 text-slate-900 dark:text-white !border-indigo-300 dark:!border-indigo-950;
}
:deep(.p-datatable-tbody > tr.p-highlight) {
    @apply border-l-4 border-l-indigo-600 dark:border-l-indigo-500;
}
:deep(.p-tag-success) {
    @apply bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-500/20;
}
:deep(.p-tag-warn) {
    @apply bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 border border-amber-500/20;
}
:deep(.p-tag-info) {
    @apply bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 border border-indigo-500/20;
}

/* Pulse animation helper */
.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}
</style>
