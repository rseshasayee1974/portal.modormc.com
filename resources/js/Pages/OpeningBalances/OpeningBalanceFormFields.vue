<script setup>
import { computed } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = defineProps({
    form: { type: Object, required: true },
    kind: { type: String, required: true },
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
    busy: Boolean,
});
const emit = defineEmits(['select-target']);
const form = props.form;
const selectTarget = id => emit('select-target', id);
const ledgerName = id => props.ledgers.find(ledger => Number(ledger.id) === Number(id))?.title || '—';
const money = value => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const patrons = computed(() => (props.patrons || []).map(x => ({
    label: `${x.code ? `[${x.code}] ` : ''}${x.legal_name}`,
    value: x.id,
    code: x.code,
    legal_name: x.legal_name
})));

const ledgers = computed(() => (props.ledgers || []).map(x => ({
    label: `${x.code ? `[${x.code}] ` : ''}${x.title}`,
    value: x.id,
    code: x.code,
    title: x.title
})));

const sideOptions = [
    { label: 'Debit (Dr) — Assets / Receivables', value: 'Dr' },
    { label: 'Credit (Cr) — Liabilities / Payables', value: 'Cr' }
];

const patronLedger = computed(() => {
    const patron = (props.patrons || []).find(p => Number(p.id) === Number(form.patron_id));
    return form.side === 'Dr' ? patron?.debit_ledger_id : patron?.credit_ledger_id;
});

const selectedPatronDetail = computed(() => {
    return (props.patrons || []).find(p => Number(p.id) === Number(form.patron_id));
});

const selectedLedgerDetail = computed(() => {
    return (props.ledgers || []).find(l => Number(l.id) === Number(form.account_id));
});

const clearing = computed(() => (props.ledgers || [])
    .filter(x => !x.is_pnl && Number(x.id) !== Number(props.kind === 'patron' ? patronLedger.value : form.account_id) && !(props.patrons || []).some(p => Number(p.debit_ledger_id) === Number(x.id) || Number(p.credit_ledger_id) === Number(x.id)))
    .map(x => ({ label: `${x.code ? `[${x.code}] ` : ''}${x.title}`, value: x.id }))
);
</script>

<template>
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-5 items-start">
    <!-- 1. Target Account / Party (BaseSelect) -->
    <div class="lg:col-span-1">
        <div v-if="kind === 'patron'">
            <BaseSelect
                :modelValue="form.patron_id"
                @update:modelValue="selectTarget"
                :options="patrons"
                optionLabel="label"
                optionValue="value"
                label="Customer or Vendor"
                :required="true"
                :disabled="busy || !!form.record_id"
                placeholder="Select customer or vendor..."
                :filterFields="['label', 'legal_name', 'code']"
                :filter="true"
                :showClear="true"
            />
            <div v-if="selectedPatronDetail" class="mt-1 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                <span>Mapped Ledger:</span>
                <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                    {{ form.side === 'Dr' ? (ledgerName(selectedPatronDetail.debit_ledger_id) || 'Default Debit Ledger') : (ledgerName(selectedPatronDetail.credit_ledger_id) || 'Default Credit Ledger') }}
                </span>
            </div>
        </div>
        <div v-else>
            <BaseSelect
                :modelValue="form.account_id"
                @update:modelValue="value => selectTarget(value)"
                :options="ledgers"
                optionLabel="label"
                optionValue="value"
                label="General Ledger Account"
                :required="true"
                :disabled="busy || !!form.record_id"
                placeholder="Select general ledger account..."
                :filterFields="['label', 'title', 'code']"
                :filter="true"
                :showClear="true"
            />
            <div v-if="selectedLedgerDetail" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                <span>Account Code: </span>
                <strong class="font-mono text-slate-700 dark:text-slate-300">{{ selectedLedgerDetail.code || 'N/A' }}</strong>
            </div>
        </div>
    </div>

    <!-- 2. Cutover Date (BaseDatePicker) -->
    <div>
        <BaseDatePicker
            :model-value="form.cutover_date"
            label="Cutover Date"
            dateFormat="yy-mm-dd"
            :required="true"
            :disabled="true"
            placeholder="YYYY-MM-DD"
             />
    </div>

    <!-- 3. Balance Side (BaseSelect + Quick Buttons) -->
    <div>
        <BaseSelect
            v-model="form.side"
            :options="sideOptions"
            optionLabel="label"
            optionValue="value"
            label="Balance Side"
            :required="true"
            :disabled="busy"
        />
        <div class="grid grid-cols-2 gap-2 mt-2">
            <button
                type="button"
                @click="form.side = 'Dr'"
                :disabled="busy"
                :class="[
                    'py-1.5 px-2.5 rounded-lg border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer',
                    form.side === 'Dr'
                        ? 'bg-blue-600 text-white border-blue-600 shadow-xs ring-2 ring-blue-500/20'
                        : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50'
                ]"
            >
                <!-- <ArrowUpRightIcon class="w-3.5 h-3.5" /> -->
                <span>Debit (Dr)</span>
            </button>
            <button
                type="button"
                @click="form.side = 'Cr'"
                :disabled="busy"
                :class="[
                    'py-1.5 px-2.5 rounded-lg border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer',
                    form.side === 'Cr'
                        ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs ring-2 ring-emerald-500/20'
                        : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50'
                ]"
            >
                <!-- <ArrowDownLeftIcon class="w-3.5 h-3.5" /> -->
                <span>Credit (Cr)</span>
            </button>
        </div>
    </div>

    <!-- 4. Amount (BaseInput) -->
    <div>
        <BaseInput
            v-model="form.amount"
            label="Amount (₹)"
            type="text"
            placeholder="0.00"
            :required="true"
            :disabled="busy"
            :hint="Number(form.amount) > 0 ? `Formatted: ₹ ${money(form.amount)}` : undefined"
        />
    </div>

    <!-- 5. Balancing / Clearing Ledger (BaseSelect) -->
    <div>
        <BaseSelect
            v-model="form.clearing_account_id"
            :options="clearing"
            optionLabel="label"
            optionValue="value"
            label="Balancing / Clearing Ledger"
            :disabled="busy"
            placeholder="Select balancing account"
            :required="true"
            :filter="true"
            :showClear="true"
        />
    </div>

    <!-- 6. Reference Number (BaseInput) -->
    <div>
        <BaseInput
            v-model="form.reference"
            label="Reference / Document #"
            placeholder="e.g. Legacy Inv # / Voucher #"
            :disabled="busy"
        />
    </div>

    <!-- 7. Notes & Narration (BaseInput) -->
    <div>
        <BaseInput
            v-model="form.notes"
            label="Notes & Narration"
            placeholder="Optional remarks or ledger notes"
            :disabled="busy"
        />
    </div>

    <!-- 8. Audit Reason (BaseInput) -->
    <div class="lg:col-span-1">
        <BaseInput
            v-model="form.reason"
            label="Audit Reason"
            placeholder="Explain why this balance is being set or updated..."
            :required="true"
            :disabled="busy"
            :hint="form.record_id ? 'Required when updating an existing opening balance' : 'Recorded in the compliance audit trail'"
        />
    </div>
</div>
</template>
