<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import {
    PlusIcon,
    TrashIcon,
    DocumentChartBarIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    EyeIcon,
    PrinterIcon,
    XMarkIcon,
    BoltIcon,
    DocumentTextIcon,
    ScaleIcon,
    CalendarIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';
import { useJournalStore, JournalEntry, JournalLine } from './useJournalStore';

// Custom Base UI Components
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseField from '@/Components/Base/BaseField.vue';

// PrimeVue Core Overlays & Displays
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';

const props = defineProps<{
    entries: JournalEntry[];
    ledgers: any[];
    voucherTypes: any[];
    partners: any[];
    nextVoucherNumbers?: Record<string, string>;
    initialVoucherNumber?: string;
    initialVoucherType?: string;
}>();

const store = useJournalStore();

onMounted(() => {
    store.setInitialData(props);
});

// UI State
const showViewModal = ref(false);
const activeViewEntry = ref<JournalEntry | null>(null);
const selectedVoucherTypeFilter = ref<string | null>(null);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

// ── Date Formatting Helpers ─────────────────────────────────
const formatLocalDate = (d: any): string => {
    if (!d) return '';
    if (typeof d === 'string') {
        const match = d.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (match) return `${match[1]}-${match[2]}-${match[3]}`;
        return d.slice(0, 10);
    }
    if (d instanceof Date && !isNaN(d.getTime())) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    return String(d);
};

const displayDate = (d: any): string => {
    if (!d) return '—';
    return formatLocalDate(d);
};

// ── Form State ─────────────────────────────────────────────
const defaultVType = props.initialVoucherType || (props.voucherTypes?.[0]?.short_code ?? 'JV');
const editingEntryId = ref<number | null>(null);

const journalForm = useForm({
    voucher_type: (props.initialVoucherType || defaultVType) as string | null,
    voucher_id: null as number | null,
    voucher_name: '' as string,
    voucher_number: (props.initialVoucherNumber || '') as string,
    voucher_date: new Date(),
    posting_date: new Date(),
    narration: '',
    lines: [
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
    ] as JournalLine[]
});

const refreshVoucherNumber = async () => {
    if (editingEntryId.value) return;
    try {
        const vType = journalForm.voucher_type || 'JV';
        const vDate = journalForm.voucher_date instanceof Date ? journalForm.voucher_date.toISOString().slice(0, 10) : (journalForm.voucher_date || '');
        const res = await axios.get(route('journalentries.voucher-number'), {
            params: {
                voucher_type: vType,
                voucher_id: journalForm.voucher_id,
                voucher_date: vDate
            }
        });
        if (res.data?.voucher_number) {
            journalForm.voucher_number = res.data.voucher_number;
        }
    } catch (e) {
        // Fallback
    }
};

const resetForm = () => {
    editingEntryId.value = null;
    journalForm.reset();
    journalForm.voucher_type = defaultVType;
    journalForm.voucher_id = null;
    journalForm.voucher_name = '';
    journalForm.voucher_date = new Date();
    journalForm.posting_date = new Date();
    journalForm.narration = '';
    journalForm.lines = [
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
    ];
    refreshVoucherNumber();
};

const addLine = () => {
    journalForm.lines.push({
        account_id: null,
        debit_amount: 0,
        credit_amount: 0,
        partner_id: null,
        line_narration: ''
    });
};

const removeLine = (index: number) => {
    if (journalForm.lines.length > 2) {
        journalForm.lines.splice(index, 1);
    }
};

const onDebitChange = (line: JournalLine) => {
    if (line.debit_amount && line.debit_amount > 0) {
        line.credit_amount = 0;
    }
};

const onCreditChange = (line: JournalLine) => {
    if (line.credit_amount && line.credit_amount > 0) {
        line.debit_amount = 0;
    }
};

// Auto-balance shortcut
const autoBalanceDifference = () => {
    const diff = totalDebit.value - totalCredit.value;
    if (Math.abs(diff) < 0.01) return;

    if (diff > 0) {
        // Need more credit
        journalForm.lines.push({
            account_id: null,
            debit_amount: 0,
            credit_amount: Number(diff.toFixed(2)),
            partner_id: null,
            line_narration: 'Balancing line'
        });
    } else {
        // Need more debit
        journalForm.lines.push({
            account_id: null,
            debit_amount: Number(Math.abs(diff).toFixed(2)),
            credit_amount: 0,
            partner_id: null,
            line_narration: 'Balancing line'
        });
    }
};

const totalDebit = computed(() => journalForm.lines.reduce((sum, line) => sum + (Number(line.debit_amount) || 0), 0));
const totalCredit = computed(() => journalForm.lines.reduce((sum, line) => sum + (Number(line.credit_amount) || 0), 0));
const balanceDifference = computed(() => Math.abs(totalDebit.value - totalCredit.value));
const isBalanced = computed(() => totalDebit.value > 0 && balanceDifference.value < 0.005);

const ledgerOptions = computed(() => [
    { label: '-- Auto / Select Ledger --', value: null },
    ...(store.ledgers || []).map(l => ({
        label: `${l.code ? l.code + ' - ' : ''}${l.title}`,
        value: l.id
    }))
]);

const getLedgerLabel = (val: any) => {
    if (val === null || val === undefined) return '-- Auto / Select Ledger --';
    const found = (store.ledgers || []).find(l => l.id === val);
    return found ? `${found.code ? found.code + ' - ' : ''}${found.title}` : val;
};

const voucherOptions = computed(() => (store.voucherTypes || []).map(v => ({
    id: v.id,
    voucher_id: v.id,
    name: v.journal_name,
    voucher_name: v.journal_name,
    journal_name: v.journal_name,
    short_code: v.short_code,
    label: `${v.journal_name} (${v.short_code})`,
    value: v.journal_name
})));

watch(() => journalForm.voucher_type, (newVal) => {
    if (!newVal) {
        journalForm.voucher_id = null;
        journalForm.voucher_name = '';
        return;
    }
    const found = (store.voucherTypes || []).find(v => v.journal_name === newVal || v.short_code === newVal || v.id === newVal);
    if (found) {
        journalForm.voucher_id = found.id;
        journalForm.voucher_name = found.journal_name;
    }
}, { immediate: true });

const filterVoucherOptions = computed(() => [
    { label: '-- All Voucher Types --', value: null },
    ...(store.voucherTypes || []).map(vt => ({
        label: `${vt.journal_name} (${vt.short_code})`,
        value: vt.short_code
    }))
]);

const partnerOptions = computed(() => [
    { label: '-- None (No Patron) --', value: null },
    ...(store.partners || []).map(p => ({
        label: p.legal_name || p.name,
        value: p.id,
        ledger_id: p.ledger_id,
        patron_type: p.patron_type
    }))
]);

const onPartnerChange = (line: any) => {
    if (!line.partner_id) return;
    const patron = (store.partners || []).find((p: any) => p.id === line.partner_id);
    if (patron && patron.ledger_id && !line.account_id) {
        line.account_id = patron.ledger_id;
    }
};

// Summary KPIs across store entries
const kpiStats = computed(() => {
    const list = store.entries || [];
    const count = list.length;
    const totalDebitSum = list.reduce((acc, e) => acc + (Number(e.total_debit) || 0), 0);
    const totalCreditSum = list.reduce((acc, e) => acc + (Number(e.total_credit) || 0), 0);

    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth();
    const thisMonthCount = list.filter(e => {
        if (!e.posting_date) return false;
        const d = new Date(e.posting_date);
        return d.getFullYear() === currentYear && d.getMonth() === currentMonth;
    }).length;

    return {
        count,
        totalDebitSum,
        totalCreditSum,
        thisMonthCount
    };
});

// Filtered entries by voucher type
const filteredEntries = computed(() => {
    let list = store.entries || [];
    if (selectedVoucherTypeFilter.value) {
        list = list.filter(e => e.voucher_type === selectedVoucherTypeFilter.value);
    }
    return list;
});

const formatCurrency = (amount: number | string | null | undefined) => {
    const num = Number(amount) || 0;
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
};

const getVoucherBadgeClass = (vType: string) => {
    const t = (vType || '').toUpperCase();
    if (t === 'JV') return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800';
    if (t === 'PAYMENT' || t === 'PAY') return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800';
    if (t === 'RECEIPT' || t === 'REC') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
    if (t === 'CONTRA') return 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800';
    if (t === 'SALES' || t === 'INV') return 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800';
    if (t === 'PURCHASE' || t === 'BILL') return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800';
    return 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700';
};

const openViewModal = (entry: JournalEntry) => {
    activeViewEntry.value = entry;
    showViewModal.value = true;
};

const printActiveVoucher = () => {
    window.print();
};

// Helpers to resolve names
const getVoucherTypeName = (shortCode: string) => {
    if (!shortCode) return '—';
    const v = store.voucherTypes.find(
        vt => vt.short_code?.toUpperCase() === shortCode?.toUpperCase()
    );
    return v ? `${v.journal_name} (${v.short_code})` : shortCode.toUpperCase();
};

const getLedgerName = (id: number | null) => {
    if (!id) return '—';
    const l = store.ledgers.find(item => item.id === id);
    return l ? `${l.code} - ${l.title}` : `Account #${id}`;
};

const getPartnerName = (id: number | null) => {
    if (!id) return '—';
    const p = store.partners.find(item => item.id === id);
    return p ? p.legal_name : `Patron #${id}`;
};

// Edit Mode
const editEntry = (entry: JournalEntry) => {
    editingEntryId.value = entry.id;
    journalForm.voucher_type = entry.voucher_type;
    journalForm.voucher_number = entry.voucher_number;
    journalForm.voucher_date = formatLocalDate(entry.voucher_date);
    journalForm.posting_date = formatLocalDate(entry.posting_date);
    journalForm.narration = entry.narration || '';
    journalForm.lines = entry.lines.map(l => ({
        id: l.id,
        account_id: l.account_id,
        debit_amount: Number(l.debit_amount) || 0,
        credit_amount: Number(l.credit_amount) || 0,
        partner_id: l.partner_id || null,
        line_narration: l.line_narration || ''
    }));

    if (journalForm.lines.length < 2) {
        while (journalForm.lines.length < 2) {
            journalForm.lines.push({ account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' });
        }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    editingEntryId.value = null;
    journalForm.reset();
    journalForm.voucher_type = defaultVType;
    journalForm.lines = [
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
    ];
    refreshVoucherNumber();
};

// Submit form (Create or Update via Inertia DB Refresh)
const submitForm = async () => {
    if (totalDebit.value === 0) {
        Swal.fire({ icon: 'error', title: 'Empty Journal', text: 'You must enter debit and credit amounts in the journal.' });
        return;
    }
    if (!isBalanced.value) {
        Swal.fire({
            icon: 'error',
            title: 'Unbalanced Journal',
            text: `Total Debit (₹ ${totalDebit.value.toFixed(2)}) must equal Total Credit (₹ ${totalCredit.value.toFixed(2)}). Difference: ₹ ${balanceDifference.value.toFixed(2)}`
        });
        return;
    }

    try {
        journalForm.processing = true;
        const selectedVoucher = (store.voucherTypes || []).find(v => v.journal_name === journalForm.voucher_type || v.short_code === journalForm.voucher_type || v.id === journalForm.voucher_type);
        const formData = {
            ...journalForm.data(),
            voucher_id: selectedVoucher ? selectedVoucher.id : journalForm.voucher_id,
            voucher_name: selectedVoucher ? selectedVoucher.journal_name : journalForm.voucher_name,
            voucher_date: journalForm.voucher_date instanceof Date ? journalForm.voucher_date.toISOString().slice(0, 10) : journalForm.voucher_date,
            posting_date: journalForm.posting_date instanceof Date ? journalForm.posting_date.toISOString().slice(0, 10) : journalForm.posting_date,
            lines: (journalForm.lines || []).map(l => ({
                account_id: l.account_id || null,
                partner_id: l.partner_id || null,
                debit_amount: Number(l.debit_amount) || 0,
                credit_amount: Number(l.credit_amount) || 0,
                line_narration: l.line_narration || ''
            }))
        };
        const res = await axios.post(route('journalentries.store'), formData);
        Swal.fire({
            icon: 'success',
            title: 'Voucher Posted!',
            text: res.data.message || 'Journal Entry Created successfully',
            timer: 2000,
            showConfirmButton: false
        });
        store.addEntry(res.data.entry);
        resetForm();
    } catch (err: any) {
        Swal.fire({
            icon: 'error',
            title: 'Posting Failed',
            text: err.response?.data?.message || 'Validation error while saving journal entry.'
        });
    } finally {
        journalForm.processing = false;
    }
};

// Soft Delete Entry (Frees voucher number for reuse via Inertia DB Refresh)
const deleteEntry = (id: number, voucherNumber?: string) => {
    Swal.fire({
        title: 'Delete Journal Entry?',
        text: 'Are you sure you want to delete this journal transaction? This action is audited.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                await axios.delete(route('journalentries.destroy', id));
                store.removeEntry(id);
                Swal.fire({ icon: 'success', title: 'Deleted', text: 'Journal Entry deleted.', timer: 1500, showConfirmButton: false });
            } catch (e: any) {
                Swal.fire({ icon: 'error', title: 'Error', text: e.response?.data?.message || 'Unable to delete.' });
            }
        }
    });
};
</script>

<template>
    <AppLayout title="Double-Entry Journal">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50/60 dark:bg-slate-950 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- ═════════════════════════════════════════════════════════════ -->
                <!-- TOP: JOURNAL VOUCHER CREATION FORM                            -->
                <!-- ═════════════════════════════════════════════════════════════ -->
                <div class="bg-white dark:bg-slate-900 shadow-xs rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 space-y-6">
                    <!-- Form Header with Icon (White Background) -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-5 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                                <DocumentTextIcon class="w-5 h-5" />
                            </div>
                            <div>
                                <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">
                                    General Journal Entry
                                </h1>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Double-entry general ledger voucher posting with real-time balancing
                                </p>
                            </div>
                        </div>

                        <!-- <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="resetForm"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750 transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                title="Reset all form fields"
                            >
                                <ArrowPathIcon class="w-3.5 h-3.5 text-slate-400" />
                                <span>Clear Fields</span>
                            </button>
                        </div> -->
                    </div>

                    <!-- Voucher Metadata Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <BaseSelect
                            v-model="journalForm.voucher_type"
                            label="Voucher Type"
                            required
                            :options="voucherOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Type (e.g. JV)"
                        />

                        <BaseDatePicker 
                            v-model="journalForm.voucher_date" 
                            label="Voucher Date"
                            required
                            dateFormat="yy-mm-dd"
                            class="w-full" 
                        />

                        <BaseDatePicker 
                            v-model="journalForm.posting_date" 
                            label="Posting Date"
                            required
                            dateFormat="yy-mm-dd"
                            class="w-full" 
                        />

                        <BaseInput
                            v-model="journalForm.voucher_number"
                            label="Reference #"
                            placeholder="Auto-generated on posting"
                            :disabled="!editingEntryId"
                        />
                    </div>

                    <!-- Voucher Main Narration -->
                    <div>
                        <BaseField label="Voucher Main Narration">
                            <Textarea
                                v-model="journalForm.narration"
                                placeholder="Enter transaction details / remarks..."
                                rows="2"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-2.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-800 dark:text-slate-100"
                            />
                        </BaseField>
                    </div>

                    <!-- Lines Grid -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                                Journal Line Items
                            </span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="autoBalanceDifference"
                                    :disabled="isBalanced || balanceDifference === 0"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 dark:hover:bg-amber-900/60 transition-colors disabled:opacity-40 cursor-pointer"
                                    title="Automatically create a balancing line with the remaining difference"
                                >
                                    <BoltIcon class="w-3.5 h-3.5" />
                                    <span>Auto-Balance Diff (₹ {{ balanceDifference.toFixed(2) }})</span>
                                </button>

                                <button
                                    type="button"
                                    @click="addLine"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors cursor-pointer"
                                >
                                    <PlusIcon class="w-3.5 h-3.5" />
                                    <span>Add Line</span>
                                </button>
                            </div>
                        </div>

                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-x-auto shadow-2xs">
                            <table class="w-full text-xs text-left">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 font-bold uppercase text-[11px]">
                                        <th class="py-2.5 px-3 w-10 text-center">#</th>
                                        <th class="py-2.5 px-3 min-w-[240px]">
                                            Account / General Ledger
                                            <span class="text-[10px] font-normal text-slate-400 dark:text-slate-400 lowercase">(or via Patron)</span>
                                        </th>
                                        <th class="py-2.5 px-3 min-w-[200px]">Patron / Sub-Ledger</th>
                                        <th class="py-2.5 px-3 w-36 text-right text-indigo-700 dark:text-indigo-300">Debit (₹)</th>
                                        <th class="py-2.5 px-3 w-36 text-right text-purple-700 dark:text-purple-300">Credit (₹)</th>
                                        <th class="py-2.5 px-3 min-w-[160px]">Line Memo</th>
                                        <th class="py-2.5 px-2 w-10 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-sans">
                                    <tr
                                        v-for="(line, idx) in journalForm.lines"
                                        :key="idx"
                                        class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                    >
                                        <td class="py-2.5 px-3 text-center font-mono font-bold text-slate-400">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2 px-3">
                                            <BaseSelect
                                                v-model="line.account_id"
                                                :options="ledgerOptions"
                                                optionLabel="label"
                                                optionValue="value"
                                                filter
                                                showClear
                                                :placeholder="line.partner_id ? 'Auto-resolves from Patron' : 'Select Ledger...'"
                                                class="w-full"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <BaseSelect
                                                v-model="line.partner_id"
                                                :options="partnerOptions"
                                                optionLabel="label"
                                                optionValue="value"
                                                filter
                                                showClear
                                                placeholder="Select Patron..."
                                                class="w-full"
                                                @change="onPartnerChange(line)"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <BaseInputNumber
                                                v-model="line.debit_amount"
                                                :minFractionDigits="2"
                                                :disabled="Number(line.credit_amount) > 0"
                                                @input="onDebitChange(line)"
                                                placeholder="0.00"
                                                class="w-full font-mono text-right"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <BaseInputNumber
                                                v-model="line.credit_amount"
                                                :minFractionDigits="2"
                                                :disabled="Number(line.debit_amount) > 0"
                                                @input="onCreditChange(line)"
                                                placeholder="0.00"
                                                class="w-full font-mono text-right"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <BaseInput
                                                v-model="line.line_narration"
                                                placeholder="Narration..."
                                                class="w-full"
                                            />
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button
                                                type="button"
                                                @click="removeLine(idx)"
                                                :disabled="journalForm.lines.length <= 2"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors disabled:opacity-20 cursor-pointer"
                                                title="Delete row"
                                            >
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Live Balancing Bar & Actions -->
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-4 w-full md:w-auto">
                            <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 min-w-[150px]">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Debit</span>
                                <div class="text-lg font-black font-mono text-indigo-700 dark:text-indigo-400">
                                    ₹ {{ totalDebit.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </div>
                            </div>

                            <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 min-w-[150px]">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Credit</span>
                                <div class="text-lg font-black font-mono text-purple-700 dark:text-purple-300">
                                    ₹ {{ totalCredit.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </div>
                            </div>

                            <!-- Balance Pill -->
                            <div
                                class="px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 border"
                                :class="isBalanced
                                    ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                    : (totalDebit === 0 && totalCredit === 0
                                        ? 'bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'
                                        : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800')"
                            >
                                <CheckCircleIcon v-if="isBalanced" class="w-4 h-4 text-emerald-500" />
                                <ScaleIcon v-else-if="totalDebit === 0 && totalCredit === 0" class="w-4 h-4 text-slate-400" />
                                <ExclamationTriangleIcon v-else class="w-4 h-4 text-rose-500" />
                                <span>
                                    {{ isBalanced 
                                        ? 'JOURNAL BALANCED' 
                                        : (totalDebit === 0 && totalCredit === 0 
                                            ? 'ENTER LINE AMOUNTS' 
                                            : `OUT OF BALANCE: ₹ ${balanceDifference.toFixed(2)}`) 
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2.5 w-full md:w-auto justify-end">
                            <button
                                type="button"
                                @click="resetForm"
                                class="px-4 py-2 text-xs font-semibold rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                            >
                                Clear Form
                            </button>

                            <button
                                type="button"
                                :disabled="!isBalanced || journalForm.processing"
                                @click="submitForm"
                                class="px-6 py-2.5 text-xs font-bold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <i v-if="journalForm.processing" class="pi pi-spin pi-spinner text-xs"></i>
                                <CheckCircleIcon v-else class="w-4 h-4" />
                                <span>{{ journalForm.processing ? 'Posting...' : 'Post Journal Entry' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ═════════════════════════════════════════════════════════════ -->
                <!-- FINANCIAL STATS & KPIS BANNER                                 -->
                <!-- ═════════════════════════════════════════════════════════════ -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- 1. Total Postings -->
                    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Journal Vouchers</span>
                            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white mt-1">
                                {{ kpiStats.count }}
                            </div>
                            <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5 block">
                                {{ kpiStats.thisMonthCount }} posted this month
                            </span>
                        </div>
                        <div class="p-3 bg-indigo-50 dark:bg-indigo-950/60 rounded-xl text-indigo-600 dark:text-indigo-400">
                            <DocumentTextIcon class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- 2. Total Debit Volume -->
                    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Debits</span>
                            <div class="text-xl font-black font-mono text-slate-900 dark:text-white mt-1">
                                {{ formatCurrency(kpiStats.totalDebitSum) }}
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono mt-0.5 block">Dr Cumulative</span>
                        </div>
                        <div class="p-3 bg-blue-50 dark:bg-blue-950/60 rounded-xl text-blue-600 dark:text-blue-400">
                            <ScaleIcon class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- 3. Total Credit Volume -->
                    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Credits</span>
                            <div class="text-xl font-black font-mono text-slate-900 dark:text-white mt-1">
                                {{ formatCurrency(kpiStats.totalCreditSum) }}
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono mt-0.5 block">Cr Cumulative</span>
                        </div>
                        <div class="p-3 bg-purple-50 dark:bg-purple-950/60 rounded-xl text-purple-600 dark:text-purple-400">
                            <ScaleIcon class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- 4. General Ledger Integrity -->
                    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Ledger Integrity</span>
                            <div class="flex items-center gap-1.5 mt-1">
                                <CheckCircleIcon class="w-5 h-5 text-emerald-500" />
                                <span class="text-base font-black uppercase text-emerald-600 dark:text-emerald-400">
                                    Balanced
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono mt-0.5 block">Sum(Dr) = Sum(Cr)</span>
                        </div>
                        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/60 rounded-xl text-emerald-600 dark:text-emerald-400">
                            <CheckCircleIcon class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <!-- ═════════════════════════════════════════════════════════════ -->
                <!-- RECENT TRANSACTIONS REGISTRY (DataTable)                      -->
                <!-- ═════════════════════════════════════════════════════════════ -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                    <BaseDataTable
                        :value="filteredEntries"
                        v-model:filters="filters"
                        :globalFilterFields="['posting_date', 'voucher_date', 'voucher_number', 'voucher_type', 'narration', 'is_status']"
                        showSearch
                        showSerial
                        heading="General Journal Transactions"
                        headingIcon="DocumentChartBarIcon"
                        :rows="15"
                    >
                        <template #toolbar>
                            <div class="flex items-center gap-2">
                                <!-- Voucher Type Filter -->
                                <BaseSelect
                                    v-model="selectedVoucherTypeFilter"
                                    :options="filterVoucherOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    size="small"
                                    placeholder="Filter by Voucher Type"
                                    class="w-56"
                                />
                            </div>
                        </template>

                        <!-- Posting Date -->
                        <Column field="posting_date" header="Date" style="width: 110px" sortable>
                            <template #body="slotProps">
                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ slotProps.data.posting_date ? slotProps.data.posting_date.substring(0, 10) : slotProps.data.voucher_date }}
                                </span>
                            </template>
                        </Column>

                        <!-- Voucher Number -->
                        <Column field="voucher_number" header="Voucher #" style="width: 150px" sortable>
                            <template #body="slotProps">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-black text-xs text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.voucher_number }}
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <!-- Voucher Type -->
                        <Column field="voucher_type" header="Type" style="width: 100px" sortable>
                            <template #body="slotProps">
                                <span
                                    :class="getVoucherBadgeClass(slotProps.data.voucher_type)"
                                    class="inline-block px-2 py-0.5 rounded-md font-mono text-[10px] font-black uppercase tracking-wider border"
                                >
                                    {{ slotProps.data.voucher_type }}
                                </span>
                            </template>
                        </Column>

                        <!-- Accounts Involved -->
                        <Column header="Accounts Summary" style="min-width: 220px">
                            <template #body="slotProps">
                                <div v-if="slotProps.data.lines && slotProps.data.lines.length" class="space-y-0.5 text-xs">
                                    <div class="flex items-center gap-1 font-semibold text-slate-800 dark:text-slate-200">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-bold">Dr:</span>
                                        <span class="truncate max-w-[180px]">
                                            {{ slotProps.data.lines.find((l: any) => Number(l.debit_amount) > 0)?.ledger?.title || 'Account Dr' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 font-semibold text-slate-600 dark:text-slate-400">
                                        <span class="text-purple-600 dark:text-purple-400 font-bold">Cr:</span>
                                        <span class="truncate max-w-[180px]">
                                            {{ slotProps.data.lines.find((l: any) => Number(l.credit_amount) > 0)?.ledger?.title || 'Account Cr' }}
                                        </span>
                                    </div>
                                </div>
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </template>
                        </Column>

                        <!-- Narration -->
                        <Column header="Narration" style="min-width: 180px">
                            <template #body="slotProps">
                                <div class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2" :title="slotProps.data.narration">
                                    {{ slotProps.data.narration || 'No description recorded' }}
                                </div>
                            </template>
                        </Column>

                        <!-- Total Amount -->
                        <Column header="Amount (₹)" align="right" style="width: 140px" sortable>
                            <template #body="slotProps">
                                <span class="font-mono font-black text-xs text-slate-900 dark:text-white">
                                    {{ formatCurrency(slotProps.data.total_debit) }}
                                </span>
                            </template>
                        </Column>

                        <!-- Status -->
                        <Column header="Status" style="width: 100px">
                            <template #body="slotProps">
                                <span
                                    v-if="slotProps.data.is_status === 'POSTED'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    POSTED
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                                >
                                    {{ slotProps.data.is_status }}
                                </span>
                            </template>
                        </Column>

                        <!-- Actions -->
                        <Column header="Actions" align="right" style="width: 100px">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        @click="openViewModal(slotProps.data)"
                                        class="p-1.5 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition-colors cursor-pointer"
                                        title="View Voucher"
                                    >
                                        <EyeIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteEntry(slotProps.data.id)"
                                        class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/60 transition-colors cursor-pointer"
                                        title="Delete Entry"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════ -->
        <!-- VOUCHER DETAIL & PRINT MODAL                                  -->
        <!-- ═════════════════════════════════════════════════════════════ -->
        <Dialog
            v-model:visible="showViewModal"
            modal
            header="General Journal Voucher"
            :style="{ width: '700px', maxWidth: '95vw' }"
            class="rounded-2xl"
        >
            <div v-if="activeViewEntry" class="space-y-4 py-2 text-xs print:p-0">
                <!-- Voucher Receipt Header -->
                <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 flex justify-between items-start">
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Voucher Number</div>
                        <div class="text-base font-black font-mono text-indigo-600 dark:text-indigo-400">
                            {{ activeViewEntry.voucher_number }}
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            Type: <strong class="font-mono text-slate-800 dark:text-slate-200">{{ activeViewEntry.voucher_type }}</strong>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Posting Date</div>
                        <div class="font-mono font-bold text-slate-800 dark:text-slate-200">
                            {{ activeViewEntry.posting_date ? activeViewEntry.posting_date.substring(0, 10) : activeViewEntry.voucher_date }}
                        </div>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            {{ activeViewEntry.is_status }}
                        </span>
                    </div>
                </div>

                <!-- Lines Table -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden shadow-2xs">
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 font-bold uppercase text-[11px]">
                                <th class="py-2.5 px-3 w-8 text-center">#</th>
                                <th class="py-2.5 px-3">Account Title & Code</th>
                                <th class="py-2.5 px-3">Line Memo</th>
                                <th class="py-2.5 px-3 text-right w-28 text-indigo-700 dark:text-indigo-300">Debit (₹)</th>
                                <th class="py-2.5 px-3 text-right w-28 text-purple-700 dark:text-purple-300">Credit (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-sans">
                            <tr
                                v-for="(line, lIdx) in (activeViewEntry.lines || [])"
                                :key="lIdx"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                            >
                                <td class="py-2 px-3 text-center font-mono text-slate-400">{{ lIdx + 1 }}</td>
                                <td class="py-2 px-3">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ line.ledger?.title || ('Ledger #' + line.account_id) }}
                                    </div>
                                    <div v-if="line.ledger?.code" class="text-[10px] font-mono text-slate-400">
                                        Code: {{ line.ledger.code }}
                                    </div>
                                </td>
                                <td class="py-2 px-3 text-slate-500 italic">
                                    {{ line.line_narration || '—' }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-indigo-700 dark:text-indigo-400">
                                    {{ Number(line.debit_amount) > 0 ? formatCurrency(line.debit_amount) : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-purple-700 dark:text-purple-300">
                                    {{ Number(line.credit_amount) > 0 ? formatCurrency(line.credit_amount) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-800/80 font-mono font-black border-t-2 border-slate-200 dark:border-slate-700 text-xs">
                            <tr>
                                <td colspan="3" class="py-2.5 px-3 text-slate-700 dark:text-slate-200 uppercase font-bold font-sans">
                                    Total Balanced Amount
                                </td>
                                <td class="py-2.5 px-3 text-right text-indigo-700 dark:text-indigo-400">
                                    {{ formatCurrency(activeViewEntry.total_debit) }}
                                </td>
                                <td class="py-2.5 px-3 text-right text-purple-700 dark:text-purple-300">
                                    {{ formatCurrency(activeViewEntry.total_credit) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Narration Details -->
                <div v-if="activeViewEntry.narration" class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Voucher Narration</span>
                    <p class="text-xs text-slate-700 dark:text-slate-300 italic">{{ activeViewEntry.narration }}</p>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        @click="printActiveVoucher"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-xl text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer shadow-2xs"
                    >
                        <PrinterIcon class="w-4 h-4" />
                        <span>Print Voucher</span>
                    </button>

                    <button
                        type="button"
                        @click="showViewModal = false"
                        class="px-4 py-1.5 text-xs font-semibold rounded-xl text-white bg-slate-900 hover:bg-black dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
