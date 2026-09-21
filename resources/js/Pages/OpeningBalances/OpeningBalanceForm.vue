<script setup>
import { currentOpeningBalanceDate } from '@/Utils/openingBalanceDate';
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import FinancialYearDate from './FinancialYearDate.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import {
    PlusIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon,
    DocumentDuplicateIcon,
    ArrowPathIcon,
    ShieldExclamationIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    batch: Object,
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'busy']);

const initialLines = JSON.parse(JSON.stringify(props.batch?.lines ?? []));
if (!initialLines.length) {
    initialLines.push({
        account_id: '',
        partner_id: '',
        side: 'Dr',
        amount: '',
        reference: ''
    });
}

const form = reactive({
    version: props.batch?.version ?? 0,
    cutover_date: props.batch?.cutover_date?.slice(0, 10) ?? currentOpeningBalanceDate(),
    clearing_account_id: props.batch?.clearing_account_id ?? '',
    notes: props.batch?.notes ?? '',
    lines: initialLines,
});

const posted = ref(props.batch?.status === 'POSTED');
const tab = ref('patron');
const busy = ref(false);
const errors = ref([]);
const message = ref('');
const saved = ref(JSON.stringify(form));
const dirty = computed(() => JSON.stringify(form) !== saved.value);
const preview = ref(null);
const reason = ref('');
const reversing = ref(false);
const confirming = ref(false);

const cents = value => Math.round(Number(value || 0) * 100);
const money = value => (value / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const debit = computed(() => form.lines.reduce((sum, l) => sum + (l.side === 'Dr' ? cents(l.amount) : 0), 0));
const credit = computed(() => form.lines.reduce((sum, l) => sum + (l.side === 'Cr' ? cents(l.amount) : 0), 0));
const difference = computed(() => debit.value - credit.value);

const visibleLines = computed(() =>
    form.lines
        .map((line, index) => ({ line, index }))
        .filter(({ line }) => tab.value === 'patron' ? line.partner_id !== null : line.partner_id === null)
);

const patronLinesCount = computed(() => form.lines.filter(l => l.partner_id !== null).length);
const ledgerLinesCount = computed(() => form.lines.filter(l => l.partner_id === null).length);

// Ensure at least 1 default row exists whenever switching to a tab
watch(tab, (newTab) => {
    const hasLinesInTab = form.lines.some(l => newTab === 'patron' ? l.partner_id !== null : l.partner_id === null);
    if (!hasLinesInTab && !posted.value) {
        addLine();
    }
}, { immediate: true });

const journalDate = computed(() => {
    if (!form.cutover_date) return '—';
    const date = new Date(`${form.cutover_date}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() - 1);
    return Number.isNaN(date.getTime()) ? '—' : date.toISOString().slice(0, 10);
});

const rollup = computed(() => {
    const totals = new Map();
    form.lines.filter(l => l.account_id).forEach(l => {
        const row = totals.get(l.account_id) ?? { id: l.account_id, debit: 0, credit: 0 };
        row[l.side === 'Dr' ? 'debit' : 'credit'] += cents(l.amount);
        totals.set(l.account_id, row);
    });
    return [...totals.values()];
});

// Dropdown Options
const clearingLedgerOptions = computed(() => [
    { label: 'None — full trial balance is balanced', value: '' },
    ...props.ledgers
        .filter(l => !l.is_pnl)
        .map(l => ({ label: `${l.code} · ${l.title}`, value: l.id }))
]);

const ledgerOptions = computed(() =>
    props.ledgers.map(l => ({ label: `${l.code} · ${l.title}`, value: l.id }))
);

const patronOptions = computed(() =>
    props.patrons.map(p => ({
        label: `${p.code ? p.code + ' · ' : ''}${p.legal_name}`,
        value: p.id
    }))
);

const sideOptions = [
    { label: 'Dr', value: 'Dr' },
    { label: 'Cr', value: 'Cr' },
];

const ledgerName = id => props.ledgers.find(l => Number(l.id) === Number(id))?.title ?? `Ledger #${id}`;

function addLine() {
    form.lines.push({
        account_id: '',
        partner_id: tab.value === 'patron' ? '' : null,
        side: 'Dr',
        amount: '',
        reference: ''
    });
}

function removeLine(originalIndex) {
    form.lines.splice(originalIndex, 1);
    const hasLinesInTab = form.lines.some(l => tab.value === 'patron' ? l.partner_id !== null : l.partner_id === null);
    if (!hasLinesInTab && !posted.value) {
        addLine();
    }
}

function showError(error) {
    errors.value = Object.entries(error.response?.data?.errors ?? {}).flatMap(([key, values]) =>
        (Array.isArray(values) ? values : [values]).map(value => {
            const match = key.match(/^lines\.(\d+)\./);
            return match ? `Row ${Number(match[1]) + 1}: ${value}` : value;
        }));
    if (!errors.value.length) {
        errors.value = [error.response?.data?.message ?? 'Unable to complete this action. Please try again.'];
    }
}

async function run(action) {
    busy.value = true;
    emit('busy', true);
    errors.value = [];
    message.value = '';
    try {
        await action();
    } catch (error) {
        showError(error);
    } finally {
        busy.value = false;
        emit('busy', false);
    }
}

function save() {
    run(async () => {
        const isLineBlank = l => !l.account_id && (!l.amount || Number(l.amount) === 0) && (!l.partner_id || l.partner_id === '') && !l.reference;
        const activeLines = form.lines.filter(l => !isLineBlank(l));
        const payloadLines = activeLines.length ? activeLines : form.lines;

        if (payloadLines.some(line => line.partner_id === '')) {
            errors.value = ['Select a patron on every Patron balances row.'];
            return;
        }

        const payload = {
            ...form,
            lines: payloadLines
        };

        const { data } = await axios.put(route('opening-balances.save'), payload);
        form.version = data.batch.version;
        form.lines = data.batch.lines;
        if (!form.lines.length) {
            addLine();
        }
        saved.value = JSON.stringify(form);
        message.value = 'Draft saved. Review the totals, then post opening balances.';
        emit('saved', data.batch);
    });
}

function post() {
    run(async () => {
        const { data } = await axios.post(route('opening-balances.post'), { version: form.version });
        acceptBatch(data.batch, 'Opening balances posted successfully.');
    });
}

function reverse() {
    run(async () => {
        const { data } = await axios.post(route('opening-balances.reverse'), {
            version: form.version,
            reason: reason.value
        });
        acceptBatch(data.batch, 'Reversal posted. Update the draft and post the corrected balances.');
    });
}

function acceptBatch(batch, notice) {
    const batchLines = JSON.parse(JSON.stringify(batch.lines ?? []));
    if (!batchLines.length && batch.status !== 'POSTED') {
        batchLines.push({
            account_id: '',
            partner_id: tab.value === 'patron' ? '' : null,
            side: 'Dr',
            amount: '',
            reference: ''
        });
    }
    Object.assign(form, {
        version: batch.version,
        cutover_date: batch.cutover_date?.slice(0, 10) ?? currentOpeningBalanceDate(),
        clearing_account_id: batch.clearing_account_id ?? '',
        notes: batch.notes ?? '',
        lines: batchLines,
    });
    posted.value = batch.status === 'POSTED';
    saved.value = JSON.stringify(form);
    confirming.value = false;
    reversing.value = false;
    reason.value = '';
    message.value = notice;
    emit('saved', batch);
    router.reload({ only: ['history'] });
}

function importFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    run(async () => {
        const payload = new FormData();
        payload.append('file', file);
        payload.append('cutover_date', form.cutover_date);
        if (form.clearing_account_id) payload.append('clearing_account_id', form.clearing_account_id);
        const { data } = await axios.post(route('opening-balances.import'), payload);
        preview.value = data.lines;
    });
}

function applyImport() {
    form.lines = preview.value;
    tab.value = form.lines.some(l => l.partner_id !== null) ? 'patron' : 'ledger';
    preview.value = null;
    message.value = 'CSV rows applied to the form. Review and save the draft before posting.';
}
</script>

<template>
    <div class="space-y-6">
        <!-- Error Alerts -->
        <div v-if="errors.length" role="alert" class="rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-900/60 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-1">
                <ShieldExclamationIcon class="w-5 h-5 text-rose-600" />
                <p class="font-bold text-sm">Please check these details</p>
            </div>
            <ul class="list-disc pl-7 text-xs space-y-1">
                <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
            </ul>
        </div>

        <!-- Success Status Notice -->
        <div v-if="message" role="status" class="rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-900/60 p-4 shadow-sm flex items-center gap-2 text-sm font-semibold">
            <CheckCircleIcon class="w-5 h-5 text-emerald-600 shrink-0" />
            <span>{{ message }}</span>
        </div>

        <!-- ── Configuration Header Card ── -->
        <BaseCard title="Opening Parameters" subtitle="Choose the financial year and optional clearing account">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 pt-2">
                <div class="col-span-12 md:col-span-4">
                    <FinancialYearDate v-model="form.cutover_date" :disabled="posted || busy" />
                    <p class="mt-1 text-xs text-slate-500">Opening journal date: <strong class="font-mono">{{ journalDate }}</strong>.</p>
                </div>

                <div class="col-span-12 md:col-span-4">
                    <BaseSelect
                        v-model="form.clearing_account_id"
                        label="Clearing Ledger (For Discrepancies)"
                        :options="clearingLedgerOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select clearing ledger"
                        hint="Used only if Dr != Cr. Reconcile to zero when setup is balanced."
                        :disabled="posted || busy"
                    />
                </div>

                <div class="col-span-12 md:col-span-4">
                    <BaseInput
                        v-model="form.notes"
                        label="Notes / Source of Balances"
                        placeholder="e.g. Previous ERP closing trial balance as of March 31"
                        :disabled="posted || busy"
                    />
                </div>
            </div>
        </BaseCard>

        <!-- ── Metrics & Balance Indicator Cards ── -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" aria-label="Opening totals">
            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Debit</span>
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono mt-1">
                    ₹ {{ money(debit) }}
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Credit</span>
                <div class="text-2xl font-black text-purple-600 dark:text-purple-400 font-mono mt-1">
                    ₹ {{ money(credit) }}
                </div>
            </div>

            <div
                class="p-5 rounded-2xl border shadow-sm flex flex-col justify-between"
                :class="difference !== 0 ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/60' : 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/60'"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest" :class="difference !== 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ difference !== 0 ? 'Discrepancy (Unbalanced)' : 'Trial Balance Status' }}
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="difference !== 0 ? 'bg-amber-200/60 text-amber-800' : 'bg-emerald-200/60 text-emerald-800'">
                        {{ difference !== 0 ? 'Requires Clearing' : 'Balanced' }}
                    </span>
                </div>
                <div class="text-2xl font-black font-mono mt-1" :class="difference !== 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'">
                    ₹ {{ money(Math.abs(difference)) }} {{ difference ? (difference > 0 ? 'Cr' : 'Dr') : '' }}
                </div>
            </div>
        </div>

        <!-- ── Balances Table Card ── -->
        <BaseCard class="shadow-sm">
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <!-- Tab Switcher -->
                    <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-xl">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all"
                            :class="tab === 'patron' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                            @click="tab = 'patron'"
                        >
                            Patron Balances ({{ patronLinesCount }})
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all"
                            :class="tab === 'ledger' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                            @click="tab = 'ledger'"
                        >
                            Ledger Balances ({{ ledgerLinesCount }})
                        </button>
                    </div>

                    <!-- Add Row Button -->
                    <BaseButton
                        v-if="!posted"
                        variant="filled"
                        severity="primary"
                        size="small"
                        :disabled="busy"
                        @click="addLine"
                        label="Add Balance Row"
                    >
                        <template #icon>
                            <PlusIcon class="w-4 h-4 mr-1.5" />
                        </template>
                    </BaseButton>
                </div>
            </template>

            <div class="space-y-4">
                <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    <InformationCircleIcon class="w-4 h-4 text-indigo-500 shrink-0" />
                    <span>Patron amounts update their linked ledgers. Enter bank, cash, capital and nominal balances in the Ledger tab.</span>
                </div>

                <!-- Lines Table -->
                <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-800">
                    <table class="w-full text-xs text-left min-w-[850px]">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-[10px] text-slate-500 uppercase font-black tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="p-3 w-12 text-center">#</th>
                                <th v-if="tab === 'patron'" class="p-3 min-w-[220px]">Patron / Sub-Ledger</th>
                                <th class="p-3 min-w-[220px]">Ledger Account</th>
                                <th class="p-3 w-28 text-center">Side</th>
                                <th class="p-3 w-40 text-right">Amount (₹)</th>
                                <th class="p-3 min-w-[160px]">Reference</th>
                                <th v-if="!posted" class="p-3 w-12 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="{ line, index } in visibleLines" :key="index" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="p-2.5 text-center font-mono text-slate-400">{{ index + 1 }}</td>

                                <!-- Patron Selection -->
                                <td v-if="tab === 'patron'" class="p-2.5">
                                    <BaseSelect
                                        v-model="line.partner_id"
                                        :options="patronOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select Patron"
                                        :disabled="posted || busy"
                                        size="small"
                                    />
                                </td>

                                <!-- Ledger Selection -->
                                <td class="p-2.5">
                                    <BaseSelect
                                        v-model="line.account_id"
                                        :options="ledgerOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select Ledger"
                                        :disabled="posted || busy"
                                        size="small"
                                    />
                                </td>

                                <!-- Side Dr / Cr -->
                                <td class="p-2.5">
                                    <BaseSelect
                                        v-model="line.side"
                                        :options="sideOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        :filter="false"
                                        :disabled="posted || busy"
                                        size="small"
                                        class="w-full text-center font-bold"
                                    />
                                </td>

                                <!-- Amount -->
                                <td class="p-2.5">
                                    <BaseInputNumber
                                        v-model="line.amount"
                                        :min="0.01"
                                        :maxFractionDigits="2"
                                        placeholder="0.00"
                                        :disabled="posted || busy"
                                        size="small"
                                    />
                                </td>

                                <!-- Reference -->
                                <td class="p-2.5">
                                    <BaseInput
                                        v-model="line.reference"
                                        placeholder="Ref / Invoice / Note"
                                        :disabled="posted || busy"
                                        size="small"
                                    />
                                </td>

                                <!-- Remove Action -->
                                <td v-if="!posted" class="p-2.5 text-center">
                                    <button
                                        type="button"
                                        @click="removeLine(index)"
                                        :disabled="busy"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-all active:scale-95"
                                        title="Remove Row"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>

                            <!-- Empty Tab Notice -->
                            <tr v-if="!visibleLines.length">
                                <td :colspan="tab === 'patron' ? 7 : 6" class="py-12 text-center text-slate-400 font-medium">
                                    No {{ tab }} balances added yet. Click "+ Add Balance Row" or import from CSV.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CSV Import and Template Toolbar -->
                <div v-if="!posted" class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <a
                            :href="route('opening-balances.template')"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-indigo-600 bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-slate-700 transition-all"
                        >
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Download CSV Template
                        </a>

                        <label
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-indigo-600 bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-slate-700 transition-all cursor-pointer"
                            :class="{ 'opacity-50 pointer-events-none': busy || !form.cutover_date }"
                        >
                            <ArrowUpTrayIcon class="w-4 h-4" />
                            Preview CSV Import
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="sr-only"
                                :disabled="busy || !form.cutover_date"
                                @change="importFile"
                            />
                        </label>
                    </div>

                    <p class="text-[11px] text-slate-400">
                        Choose cutover date first before importing CSV files.
                    </p>
                </div>
            </div>
        </BaseCard>

        <!-- ── CSV Preview Section ── -->
        <BaseCard v-if="preview" class="border-indigo-300 dark:border-indigo-800 shadow-md">
            <template #header>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            CSV Preview · {{ preview.length }} Rows Detected
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Applying this import will replace all current rows in the form.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <BaseButton
                            variant="filled"
                            severity="primary"
                            size="small"
                            label="Replace Form Rows With Import"
                            @click="applyImport"
                        />
                        <BaseButton
                            variant="outlined"
                            severity="secondary"
                            size="small"
                            label="Cancel Import"
                            @click="preview = null"
                        />
                    </div>
                </div>
            </template>

            <div class="max-h-60 overflow-auto rounded-lg border border-slate-100 dark:border-slate-800">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800 text-[10px] uppercase font-bold text-slate-500">
                        <tr>
                            <th class="text-left p-2.5">Ledger / Patron</th>
                            <th class="text-right p-2.5">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="(line, index) in preview" :key="index">
                            <td class="p-2">
                                {{ ledgerName(line.account_id) }}
                                <span v-if="line.partner_id" class="text-indigo-600 dark:text-indigo-400 font-semibold">
                                    · {{ patrons.find(p => p.id === line.partner_id)?.legal_name }}
                                </span>
                            </td>
                            <td class="p-2 text-right font-mono font-bold">
                                {{ money(cents(line.amount)) }} {{ line.side }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseCard>

        <!-- ── Ledger Rollup Totals ── -->
        <BaseCard v-if="rollup.length" title="Ledger Totals (Consolidated)" subtitle="Includes consolidated patron sub-ledger totals">
            <div class="rounded-xl border border-slate-100 dark:border-slate-800 overflow-hidden">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800 text-[10px] uppercase font-black text-slate-500">
                        <tr>
                            <th class="text-left p-3">Ledger</th>
                            <th class="text-right p-3 w-36">Debit (₹)</th>
                            <th class="text-right p-3 w-36">Credit (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in rollup" :key="row.id">
                            <td class="p-2.5 font-medium">{{ ledgerName(row.id) }}</td>
                            <td class="p-2.5 text-right font-mono font-semibold">{{ money(row.debit) }}</td>
                            <td class="p-2.5 text-right font-mono font-semibold">{{ money(row.credit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseCard>

        <!-- ── Posting & Reversal Actions ── -->
        <BaseCard>
            <div v-if="!posted" class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <BaseButton
                        variant="outlined"
                        severity="primary"
                        :disabled="busy || !form.lines.length"
                        :loading="busy"
                        label="Save Draft"
                        @click="save"
                    />

                    <BaseButton
                        variant="filled"
                        severity="success"
                        :disabled="busy || dirty || !form.version || (difference !== 0 && !form.clearing_account_id)"
                        label="Review & Post Opening Balances"
                        @click="confirming = true"
                    />

                    <span v-if="dirty" class="text-xs text-amber-600 font-medium">
                        * Save draft changes before posting.
                    </span>
                </div>

                <!-- Confirmation Box -->
                <div v-if="confirming && !posted" class="rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 text-slate-800 dark:text-slate-200 border border-indigo-200 dark:border-indigo-900/60 p-5 space-y-4 shadow-sm animate-in fade-in duration-300">
                    <p class="text-xs font-semibold">
                        Post {{ form.lines.length }} opening rows dated <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ journalDate }}</strong>?
                        This will generate the opening journal entry and update patron and general ledger reports.
                    </p>
                    <div class="flex items-center gap-2">
                        <BaseButton
                            variant="filled"
                            severity="success"
                            size="small"
                            :disabled="busy || dirty"
                            label="Confirm & Post Balances"
                            @click="post"
                        />
                        <BaseButton
                            variant="outlined"
                            severity="secondary"
                            size="small"
                            :disabled="busy"
                            label="Cancel"
                            @click="confirming = false"
                        />
                    </div>
                </div>
            </div>

            <!-- Posted & Correction Workflow -->
            <div v-if="posted" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                            Balances Are Posted & Locked
                        </h4>
                        <p class="text-xs text-slate-400 mt-0.5">
                            To correct these balances, initiate a reversal to reopen the draft.
                        </p>
                    </div>

                    <BaseButton
                        variant="outlined"
                        severity="warn"
                        size="small"
                        :disabled="busy"
                        label="Correct Opening Setup"
                        @click="reversing = !reversing"
                    />
                </div>

                <div v-if="reversing" class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800 animate-in fade-in duration-300">
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        Reversal removes this setup's effect from historical balances using the original opening date. The original journal remains in the audit history.
                    </p>

                    <BaseInput
                        v-model="reason"
                        label="Reason for Correction"
                        placeholder="Provide reason for reversing and updating opening setup..."
                        required
                    />

                    <BaseButton
                        variant="filled"
                        severity="danger"
                        size="small"
                        :disabled="busy || reason.trim().length < 5"
                        label="Reverse and Reopen Draft"
                        @click="reverse"
                    />
                </div>
            </div>
        </BaseCard>

        <!-- ── Audit History Card ── -->
        <BaseCard v-if="history.length" title="Posting Audit History" subtitle="Previous journals and reversals related to this plant's opening setup">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="entry in history" :key="entry.id" class="py-3 text-xs flex flex-col gap-1">
                    <div class="flex justify-between items-center gap-3">
                        <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ entry.voucher_number }}</strong>
                        <span class="text-slate-400 font-mono">
                            {{ entry.voucher_date.slice(0, 10) }} · ₹ {{ Number(entry.total_debit).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                        </span>
                    </div>
                    <p class="text-slate-500">{{ entry.narration }}</p>
                </div>
            </div>
        </BaseCard>
    </div>
</template>
