<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
    ScaleIcon,
    PencilSquareIcon,
    XMarkIcon
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

// Keep store synchronized whenever Inertia props refresh
watch(() => props.entries, () => {
    store.setInitialData(props);
}, { deep: true });

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
    voucher_type: defaultVType,
    voucher_number: props.initialVoucherNumber || '',
    voucher_date: formatLocalDate(new Date()),
    posting_date: formatLocalDate(new Date()),
    narration: '',
    lines: [
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
    ] as JournalLine[]
});

const refreshVoucherNumber = () => {
    if (!journalForm.voucher_type || editingEntryId.value) return;
    const nextNum = store.computeNextVoucherNumber(journalForm.voucher_type);
    if (nextNum) {
        journalForm.voucher_number = nextNum;
    }
};

// Auto-fetch reference sequence when voucher type changes in Create mode
watch(() => journalForm.voucher_type, (newType) => {
    if (newType && !editingEntryId.value) {
        refreshVoucherNumber();
    }
});

const addLine = () => {
    journalForm.lines.push({ account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' });
};

const removeLine = (index: number) => {
    if (journalForm.lines.length > 2) {
        journalForm.lines.splice(index, 1);
    }
};

// Calculations
const totalDebit = computed(() => journalForm.lines.reduce((sum, line) => sum + (Number(line.debit_amount) || 0), 0));
const totalCredit = computed(() => journalForm.lines.reduce((sum, line) => sum + (Number(line.credit_amount) || 0), 0));
const balanceDifference = computed(() => Math.abs(totalDebit.value - totalCredit.value));
const isBalanced = computed(() => totalDebit.value > 0 && balanceDifference.value < 0.0001);

// Auto-balance helper
const autoBalance = () => {
    const diff = totalDebit.value - totalCredit.value;
    if (Math.abs(diff) < 0.0001) return;

    const lastLine = journalForm.lines[journalForm.lines.length - 1];
    if (diff > 0) {
        // Need more credit
        if (lastLine.debit_amount === 0 && lastLine.credit_amount === 0) {
            lastLine.credit_amount = parseFloat(diff.toFixed(2));
        } else {
            journalForm.lines.push({
                account_id: null,
                debit_amount: 0,
                credit_amount: parseFloat(diff.toFixed(2)),
                partner_id: null,
                line_narration: ''
            });
        }
    } else {
        // Need more debit
        const absDiff = Math.abs(diff);
        if (lastLine.debit_amount === 0 && lastLine.credit_amount === 0) {
            lastLine.debit_amount = parseFloat(absDiff.toFixed(2));
        } else {
            journalForm.lines.push({
                account_id: null,
                debit_amount: parseFloat(absDiff.toFixed(2)),
                credit_amount: 0,
                partner_id: null,
                line_narration: ''
            });
        }
    }
};

// Dropdown Options
const ledgerOptions = computed(() =>
    store.ledgers.map(l => ({
        label: `${l.code} - ${l.title}`,
        value: l.id
    }))
);

const voucherOptions = computed(() =>
    store.voucherTypes.map(v => ({
        label: `${v.journal_name} (${v.short_code})`,
        value: v.short_code
    }))
);

const partnerOptions = computed(() =>
    store.partners.map(p => ({
        label: p.legal_name,
        value: p.id
    }))
);

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
const submitForm = () => {
    if (totalDebit.value === 0) {
        Swal.fire({ icon: 'error', title: 'Empty Journal', text: 'You must enter amounts for debit and credit.' });
        return;
    }
    if (!isBalanced.value) {
        Swal.fire({
            icon: 'error',
            title: 'Unbalanced Journal',
            text: `Total Debit (₹${totalDebit.value.toFixed(2)}) must equal Total Credit (₹${totalCredit.value.toFixed(2)}). Difference: ₹${balanceDifference.value.toFixed(2)}`
        });
        return;
    }

    // Check account_id presence on active lines
    const invalidLine = journalForm.lines.find(l => (l.debit_amount > 0 || l.credit_amount > 0) && !l.account_id);
    if (invalidLine) {
        Swal.fire({ icon: 'error', title: 'Missing Account', text: 'Please select an Account / Ledger for all journal lines with amounts.' });
        return;
    }

    const payload = {
        ...journalForm.data(),
        voucher_date: formatLocalDate(journalForm.voucher_date),
        posting_date: formatLocalDate(journalForm.posting_date),
    };

    if (editingEntryId.value) {
        journalForm.transform(() => payload).put(route('journalentries.update', editingEntryId.value), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Entry Updated!',
                    text: 'Journal Entry updated successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
                cancelEdit();
            },
            onError: (errors) => {
                const errMsg = Object.values(errors).flat().join('\n') || 'Validation failed. Please check all line items and try again.';
                Swal.fire({ icon: 'error', title: 'Update Error', text: errMsg });
            }
        });
    } else {
        journalForm.transform(() => payload).post(route('journalentries.store'), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Entry Posted!',
                    text: 'Journal Entry created successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
                journalForm.reset();
                journalForm.voucher_type = defaultVType;
                journalForm.lines = [
                    { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
                    { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
                ];
                refreshVoucherNumber();
            },
            onError: (errors) => {
                const errMsg = Object.values(errors).flat().join('\n') || 'Validation failed. Please check all line items and try again.';
                Swal.fire({ icon: 'error', title: 'Submission Error', text: errMsg });
            }
        });
    }
};

// Soft Delete Entry (Frees voucher number for reuse via Inertia DB Refresh)
const deleteEntry = (id: number, voucherNumber: string) => {
    Swal.fire({
        title: `Delete ${voucherNumber}?`,
        text: `Are you sure you want to delete this journal entry? The reference number "${voucherNumber}" will be freed and can be reused for new entries.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Delete & Free Reference'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('journalentries.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: `Journal entry ${voucherNumber} deleted and reference freed.`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    refreshVoucherNumber();
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Delete Failed',
                        text: 'Failed to delete journal entry.'
                    });
                }
            });
        }
    });
};

// Refresh List with Inertia DB Refresh
const refreshList = () => {
    router.reload({
        preserveScroll: true,
        onSuccess: () => {
            refreshVoucherNumber();
        }
    });
};

// ── View Details Modal ──────────────────────────────────────
const showViewModal = ref(false);
const viewingEntry = ref<JournalEntry | null>(null);

const openViewModal = (entry: JournalEntry) => {
    viewingEntry.value = entry;
    showViewModal.value = true;
};
</script>

<template>
    <AppLayout title="Double-Entry Journal">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 bg-slate-50 dark:bg-slate-950 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <!-- HEADER FORM CARD (Using Custom BaseCard) -->
                <BaseCard class="shadow-xl border border-slate-200 dark:border-slate-800">
                    <template #header>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 rounded-xl shadow-lg" :class="editingEntryId ? 'bg-amber-500 shadow-amber-500/30' : 'bg-indigo-600 shadow-indigo-600/30'">
                                    <PencilSquareIcon v-if="editingEntryId" class="w-6 h-6 text-white" />
                                    <DocumentChartBarIcon v-else class="w-6 h-6 text-white" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-black text-slate-900 dark:text-white tracking-tight uppercase">
                                        {{ editingEntryId ? `Edit Journal Voucher (${journalForm.voucher_number})` : 'New Double-Entry Journal' }}
                                    </h2>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                        {{ editingEntryId ? 'Update details, line accounts, or debit/credit allocations' : 'Record debit and credit transactions with global voucher sequence' }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="editingEntryId" class="flex items-center gap-2">
                                <BaseButton 
                                    label="Cancel Edit" 
                                    icon="pi pi-times" 
                                    severity="secondary" 
                                    variant="text" 
                                    size="small" 
                                    rounded 
                                    @click="cancelEdit" 
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Header Inputs -->
                    <div class="space-y-6 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Voucher Type (Custom BaseSelect) -->
                            <BaseSelect 
                                v-model="journalForm.voucher_type" 
                                :options="voucherOptions" 
                                optionLabel="label" 
                                optionValue="value" 
                                label="Voucher Type"
                                required
                                filter
                                :disabled="!!editingEntryId"
                                placeholder="Choose Type..." 
                                class="w-full" 
                            />

                            <!-- Reference / Voucher Number (Custom BaseInput with Refresh action) -->
                            <div class="flex flex-col">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-[10px] font-semibold capitalize text-gray-700">Reference # <span class="text-red-500">*</span></span>
                                    <button 
                                        v-if="!editingEntryId"
                                        type="button" 
                                        @click="refreshVoucherNumber"
                                        class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-semibold"
                                        title="Recalculate lowest available sequence"
                                    >
                                        <ArrowPathIcon class="w-3 h-3" />
                                        Auto-Gen
                                    </button>
                                </div>
                                <BaseInput 
                                    v-model="journalForm.voucher_number" 
                                    :disabled="!!editingEntryId"
                                    placeholder="e.g. JV-00001" 
                                    inputClass="font-mono font-bold"
                                    class="w-full" 
                                />
                            </div>

                            <!-- Voucher Date (Custom BaseDatePicker) -->
                            <BaseDatePicker 
                                v-model="journalForm.voucher_date" 
                                label="Voucher Date"
                                required
                                dateFormat="yy-mm-dd"
                                class="w-full" 
                            />

                            <!-- Posting Date (Custom BaseDatePicker) -->
                            <BaseDatePicker 
                                v-model="journalForm.posting_date" 
                                label="Posting Date"
                                required
                                dateFormat="yy-mm-dd"
                                class="w-full" 
                            />
                        </div>

                        <!-- Main Narration -->
                        <BaseField label="Main Narration / Description">
                            <Textarea 
                                v-model="journalForm.narration" 
                                placeholder="Describe the purpose of this journal entry..." 
                                rows="2" 
                                class="w-full rounded-xl" 
                            />
                        </BaseField>

                        <!-- JOURNAL LINES TABLE -->
                        <div class="mt-4 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                            <div class="bg-slate-100 dark:bg-slate-800/80 px-4 py-2.5 flex items-center justify-between border-b border-slate-200 dark:border-slate-800">
                                <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">Journal Lines</span>
                                <div class="flex items-center gap-2">
                                    <BaseButton 
                                        v-if="balanceDifference > 0 && (totalDebit > 0 || totalCredit > 0)"
                                        :label="`Auto-Balance (₹${balanceDifference.toFixed(2)})`"
                                        icon="pi pi-sliders-h"
                                        severity="primary"
                                        variant="filled"
                                        size="small"
                                        @click="autoBalance" 
                                    />
                                    <BaseButton 
                                        label="Add Line" 
                                        icon="pi pi-plus" 
                                        severity="primary" 
                                        variant="text" 
                                        size="small" 
                                        rounded 
                                        @click="addLine" 
                                    />
                                </div>
                            </div>

                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-[11px] font-black uppercase text-slate-600 dark:text-slate-400 tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 min-w-[220px]">Account / Ledger *</th>
                                        <th class="px-4 py-3 min-w-[180px]">Patron / Partner</th>
                                        <th class="px-4 py-3 w-36 text-right">Debit (₹)</th>
                                        <th class="px-4 py-3 w-36 text-right">Credit (₹)</th>
                                        <th class="px-4 py-3 min-w-[160px]">Line Note</th>
                                        <th class="px-2 py-3 w-12 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                                    <tr v-for="(line, idx) in journalForm.lines" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                        <td class="p-2">
                                            <BaseSelect 
                                                v-model="line.account_id" 
                                                :options="ledgerOptions" 
                                                optionLabel="label" 
                                                optionValue="value" 
                                                filter 
                                                placeholder="Select Ledger..." 
                                                class="w-full" 
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseSelect 
                                                v-model="line.partner_id" 
                                                :options="partnerOptions" 
                                                optionLabel="label" 
                                                optionValue="value" 
                                                filter 
                                                placeholder="Select Patron..." 
                                                class="w-full" 
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber 
                                                v-model="line.debit_amount" 
                                                :minFractionDigits="2" 
                                                :disabled="line.credit_amount > 0" 
                                                placeholder="0.00" 
                                                class="w-full" 
                                                inputClass="text-right font-mono font-semibold" 
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber 
                                                v-model="line.credit_amount" 
                                                :minFractionDigits="2" 
                                                :disabled="line.debit_amount > 0" 
                                                placeholder="0.00" 
                                                class="w-full" 
                                                inputClass="text-right font-mono font-semibold" 
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseInput 
                                                v-model="line.line_narration" 
                                                placeholder="Line memo..." 
                                                class="w-full text-xs" 
                                            />
                                        </td>
                                        <td class="p-2 text-center">
                                            <BaseButton 
                                                icon="pi pi-trash" 
                                                severity="danger" 
                                                variant="text" 
                                                rounded 
                                                @click="removeLine(idx)" 
                                                :disabled="journalForm.lines.length <= 2" 
                                                title="Remove Line"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- TOTALS & POST ACTION BAR -->
                        <div class="mt-6 flex flex-col md:flex-row justify-between items-center gap-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <!-- Debit / Credit Totals -->
                            <div class="flex flex-wrap items-center gap-4 w-full md:w-auto">
                                <div class="bg-indigo-50/50 dark:bg-indigo-950/30 px-5 py-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50 min-w-[160px]">
                                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 uppercase font-black tracking-widest block mb-0.5">Total Debit</span>
                                    <span class="text-xl font-black text-indigo-700 dark:text-indigo-300 font-mono">₹ {{ totalDebit.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="bg-indigo-50/50 dark:bg-indigo-950/30 px-5 py-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50 min-w-[160px]">
                                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 uppercase font-black tracking-widest block mb-0.5">Total Credit</span>
                                    <span class="text-xl font-black text-indigo-700 dark:text-indigo-300 font-mono">₹ {{ totalCredit.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                </div>
                            </div>

                            <!-- Balanced Indicator & Submit (Custom BaseButton) -->
                            <div class="flex flex-wrap items-center gap-4 w-full md:w-auto justify-end">
                                <div 
                                    v-if="totalDebit > 0 || totalCredit > 0" 
                                    class="flex items-center px-4 py-2 rounded-xl text-xs font-black tracking-wide uppercase shadow-sm"
                                    :class="isBalanced ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800'"
                                >
                                    <CheckCircleIcon v-if="isBalanced" class="w-5 h-5 mr-1.5 text-emerald-600 dark:text-emerald-400" />
                                    <ExclamationTriangleIcon v-else class="w-5 h-5 mr-1.5 text-rose-600 dark:text-rose-400" />
                                    <span>{{ isBalanced ? 'BALANCED' : `DIFF: ₹${balanceDifference.toFixed(2)}` }}</span>
                                </div>

                                <BaseButton 
                                    size="large" 
                                    :severity="editingEntryId ? 'warn' : 'primary'" 
                                    variant="filled" 
                                    rounded 
                                    class="px-8 h-12 text-sm font-black shadow-lg uppercase tracking-widest" 
                                    :disabled="!isBalanced || journalForm.processing" 
                                    :loading="journalForm.processing"
                                    @click="submitForm" 
                                    :label="journalForm.processing ? (editingEntryId ? 'SAVING...' : 'POSTING...') : (editingEntryId ? 'UPDATE JOURNAL ENTRY' : 'POST JOURNAL ENTRY')" 
                                />
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- RECENT TRANSACTIONS TABLE (Custom BaseDataTable) -->
                <div class="space-y-4">
                    <BaseDataTable
                        :value="store.entries"
                        v-model:filters="filters"
                        :globalFilterFields="['posting_date', 'voucher_type', 'voucher_number', 'narration', 'is_status']"
                        showSearch
                        showSerial
                        heading="Recent Journal Vouchers"
                        headingIcon="DocumentChartBarIcon"
                        :rows="15"
                    >
                        <template #toolbar>
                            <BaseButton 
                                icon="pi pi-refresh" 
                                severity="secondary" 
                                variant="text" 
                                rounded 
                                label="Refresh List" 
                                @click="refreshList" 
                            />
                        </template>

                        <Column field="posting_date" header="Date" style="width: 120px" sortable>
                            <template #body="slotProps">
                                <span class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    {{ displayDate(slotProps.data.posting_date) }}
                                </span>
                            </template>
                        </Column>
                        
                        <Column field="voucher_type" header="Type" style="width: 100px" sortable>
                            <template #body="slotProps">
                                <Tag severity="info" rounded class="text-[10px] font-black uppercase">
                                    {{ slotProps.data.voucher_type }}
                                </Tag>
                            </template>
                        </Column>

                        <Column field="voucher_number" header="Voucher #" style="width: 140px" sortable>
                            <template #body="slotProps">
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ slotProps.data.voucher_number }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Narration">
                            <template #body="slotProps">
                                <div class="text-xs text-slate-600 dark:text-slate-400 max-w-sm truncate" :title="slotProps.data.narration">
                                    {{ slotProps.data.narration || '—' }}
                                </div>
                            </template>
                        </Column>

                        <Column header="Total Amount" align="right" style="width: 140px">
                            <template #body="slotProps">
                                <span class="font-mono font-bold text-indigo-700 dark:text-indigo-400">
                                    ₹ {{ parseFloat(slotProps.data.total_debit?.toString() || '0').toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Status" style="width: 100px">
                            <template #body="slotProps">
                                <Tag :severity="slotProps.data.is_status === 'POSTED' ? 'success' : 'warn'" rounded class="text-[10px] font-black uppercase">
                                    {{ slotProps.data.is_status }}
                                </Tag>
                            </template>
                        </Column>

                        <Column header="Actions" align="right" style="width: 140px">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-1">
                                    <BaseButton 
                                        icon="pi pi-eye" 
                                        severity="secondary" 
                                        variant="text" 
                                        rounded 
                                        title="View Voucher Details"
                                        @click="openViewModal(slotProps.data)" 
                                    />
                                    <BaseButton 
                                        icon="pi pi-pencil" 
                                        severity="info" 
                                        variant="text" 
                                        rounded 
                                        title="Edit Journal Entry"
                                        @click="editEntry(slotProps.data)" 
                                    />
                                    <BaseButton 
                                        icon="pi pi-trash" 
                                        severity="danger" 
                                        variant="text" 
                                        rounded 
                                        title="Delete (Soft Delete & Free Reference #)"
                                        @click="deleteEntry(slotProps.data.id, slotProps.data.voucher_number)" 
                                    />
                                </div>
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

            </div>
        </div>

        <!-- ── VIEW JOURNAL DETAILS MODAL ───────────────────────── -->
        <Dialog 
            v-model:visible="showViewModal" 
            modal 
            :header="`JOURNAL VOUCHER: ${viewingEntry?.voucher_number || ''}`" 
            :style="{ width: '820px', maxWidth: '95vw' }"
        >
            <div v-if="viewingEntry" class="space-y-6 pt-2">
                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 text-xs">
                    <div>
                        <span class="text-slate-400 uppercase font-black text-[10px] block">Voucher Type</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 uppercase">
                            {{ getVoucherTypeName(viewingEntry.voucher_type) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 uppercase font-black text-[10px] block">Voucher Date</span>
                        <span class="font-bold font-mono text-slate-800 dark:text-slate-200">
                            {{ displayDate(viewingEntry.voucher_date) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 uppercase font-black text-[10px] block">Posting Date</span>
                        <span class="font-bold font-mono text-slate-800 dark:text-slate-200">
                            {{ displayDate(viewingEntry.posting_date) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 uppercase font-black text-[10px] block">Status</span>
                        <Tag :severity="viewingEntry.is_status === 'POSTED' ? 'success' : 'warn'" rounded class="text-[9px] font-black uppercase">
                            {{ viewingEntry.is_status }}
                        </Tag>
                    </div>
                </div>

                <!-- Main Narration -->
                <div v-if="viewingEntry.narration" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                    <span class="text-[10px] uppercase font-black text-slate-400 block mb-1">Narration</span>
                    <p class="text-xs text-slate-700 dark:text-slate-300 italic">{{ viewingEntry.narration }}</p>
                </div>

                <!-- Line Items Table -->
                <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-100 dark:bg-slate-800 text-[10px] font-black uppercase text-slate-600 dark:text-slate-400 tracking-wider">
                            <tr>
                                <th class="px-3.5 py-2.5">Account / Ledger</th>
                                <th class="px-3.5 py-2.5">Patron / Partner</th>
                                <th class="px-3.5 py-2.5 text-right w-32">Debit (₹)</th>
                                <th class="px-3.5 py-2.5 text-right w-32">Credit (₹)</th>
                                <th class="px-3.5 py-2.5">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                            <tr v-for="(line, idx) in viewingEntry.lines" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-3.5 py-2.5 font-semibold text-slate-800 dark:text-slate-200">
                                    {{ line.ledger ? `${line.ledger.code} - ${line.ledger.title}` : getLedgerName(line.account_id) }}
                                </td>
                                <td class="px-3.5 py-2.5 text-slate-600 dark:text-slate-400">
                                    {{ line.partner ? line.partner.legal_name : getPartnerName(line.partner_id) }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ Number(line.debit_amount) > 0 ? '₹ ' + Number(line.debit_amount).toFixed(2) : '—' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ Number(line.credit_amount) > 0 ? '₹ ' + Number(line.credit_amount).toFixed(2) : '—' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-slate-500 italic text-[11px]">
                                    {{ line.line_narration || '—' }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-800 font-bold">
                            <tr>
                                <td colspan="2" class="px-3.5 py-2.5 text-right uppercase text-[10px] font-black text-slate-500">Total:</td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-indigo-600 dark:text-indigo-400">
                                    ₹ {{ parseFloat(viewingEntry.total_debit?.toString() || '0').toFixed(2) }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-indigo-600 dark:text-indigo-400">
                                    ₹ {{ parseFloat(viewingEntry.total_credit?.toString() || '0').toFixed(2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Footer button (Custom BaseButton) -->
                <div class="flex justify-end pt-2">
                    <BaseButton label="Close" severity="secondary" variant="outlined" @click="showViewModal = false" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
