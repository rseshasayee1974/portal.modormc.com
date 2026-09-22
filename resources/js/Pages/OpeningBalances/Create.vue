<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import OpeningBalanceFormFields from './OpeningBalanceFormFields.vue';
import { currentOpeningBalanceDate } from '@/Utils/openingBalanceDate';
import { usePermissions } from '@/Composables/usePermissions';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
const props = defineProps({
    records: { type: Array, default: () => [] },
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
});
const emit = defineEmits(['created', 'edit', 'busy']);
const { can } = usePermissions();
const kind = ref('patron');
const busy = ref(false);
const error = ref('');
const blank = () => ({
    record_id: null, version: 0, patron_id: null, account_id: null,
    cutover_date: currentOpeningBalanceDate(), side: 'Dr', amount: '',
    clearing_account_id: null, reference: '', notes: '', reason: '',
});
const form = reactive(blank());

function reset() {
    Object.assign(form, blank());
    error.value = '';
}

function changeKind(value) {
    kind.value = value;
    reset();
}

function selectTarget(id) {
    const existing = id && props.records.find(record => kind.value === 'patron'
        ? Number(record.patron_id) === Number(id)
        : !record.patron_id && Number(record.account_id) === Number(id));
    if (existing) {
        reset();
        emit('edit', existing);
        return;
    }
    form.patron_id = kind.value === 'patron' ? id : null;
    form.account_id = kind.value === 'ledger' ? id : null;
}

async function save() {
    if (busy.value || !can('OPENING_BALANCE.CREATE')) return;
    error.value = '';
    busy.value = true;
    emit('busy', true);
    let record;
    try {
        const { data } = await axios.put(route('opening-balances.save'), {
            ...form, balance_type: kind.value,
            account_id: kind.value === 'ledger' ? form.account_id : undefined,
            patron_id: kind.value === 'patron' ? form.patron_id : null,
        });
        record = data.record;
        reset();
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || e.response?.data?.message || 'Unable to save this opening balance.';
    } finally {
        busy.value = false;
        emit('busy', false);
    }
    if (record) emit('created', record);
}
</script>

<template>
    <section aria-label="Create opening balance" class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-6 py-4 dark:border-slate-700">
            <div>
                <h2 class="text-sm font-bold">Set Opening Balance</h2>
                <p class="mt-1 text-xs text-slate-500">Add a customer, vendor, or ledger balance. Existing balances open in the table below.</p>
            </div>
            <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-xs dark:bg-slate-900">
                <button v-for="option in [{ value: 'patron', label: 'Customers & Vendors' }, { value: 'ledger', label: 'General Ledgers' }]"
                    :key="option.value" type="button" :disabled="busy" @click="changeKind(option.value)"
                    class="rounded-lg px-3 py-2 font-semibold disabled:opacity-50"
                    :class="kind === option.value ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800' : 'text-slate-500'">
                    {{ option.label }}
                </button>
            </div>
        </div>
        <form class="space-y-5 p-6" @submit.prevent="save">
            <p v-if="error" role="alert" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
            <OpeningBalanceFormFields :form="form" :kind="kind" :ledgers="ledgers" :patrons="patrons" :busy="busy" @select-target="selectTarget" />
            <div class="flex justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
                <!-- <button type="button" :disabled="busy" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold disabled:opacity-50" @click="reset"> -->
                    <a v-if="can('OPENING_BALANCE.AUDIT_LOG')" :href="route('opening-balances.audit-export')" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold hover:bg-slate-50">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Export Audit Report
                </a>
                <!-- </button> -->
                <button type="submit" :disabled="busy || Number(form.amount) <= 0 || !form.amount || (!form.patron_id && !form.account_id) || !form.clearing_account_id || form.reason.trim().length < 5"
                    class="rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ busy ? 'Saving...' : 'Save & Post Opening Balance' }}
                </button>
            </div>
        </form>
    </section>
</template>
