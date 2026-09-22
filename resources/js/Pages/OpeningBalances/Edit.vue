<script setup>
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import OpeningBalanceFormFields from './OpeningBalanceFormFields.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    record: { type: Object, required: true },
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'removed', 'close', 'busy']);
const { can } = usePermissions();
const canUpdate = computed(() => can('OPENING_BALANCE.UPDATE'));
const kind = computed(() => props.record.patron_id ? 'patron' : 'ledger');
const line = props.record.lines?.[0] || {};
const form = reactive({
    record_id: props.record.id, version: props.record.version,
    patron_id: props.record.patron_id, account_id: props.record.account_id,
    cutover_date: props.record.cutover_date?.slice(0, 10),
    side: line.side || 'Dr', amount: line.amount || '',
    clearing_account_id: props.record.clearing_account_id,
    reference: line.reference || '', notes: props.record.notes || '', reason: '',
});
const busy = ref(false);
const error = ref('');

async function run(action) {
    if (busy.value) return null;
    busy.value = true;
    emit('busy', true);
    error.value = '';
    try {
        return await action();
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || e.response?.data?.message || 'Unable to update this opening balance.';
        return null;
    } finally {
        busy.value = false;
        emit('busy', false);
    }
}

async function save() {
    if (!canUpdate.value) return;
    const result = await run(() => axios.put(route('opening-balances.save'), {
        ...form, balance_type: kind.value,
        account_id: kind.value === 'ledger' ? form.account_id : undefined,
        patron_id: kind.value === 'patron' ? form.patron_id : null,
    }));
    if (result) emit('saved', result.data.record, props.record.id);
}

async function remove() {
    if (busy.value || !can('OPENING_BALANCE.DELETE')) return;
    const result = await Swal.fire({
        title: 'Remove Opening Balance?',
        text: 'This removes the balance and its opening journal. The audit history is retained.',
        icon: 'warning', input: 'text', inputValue: form.reason,
        inputLabel: 'Reason for removal (minimum 5 characters)',
        showCancelButton: true, confirmButtonText: 'Remove Balance', confirmButtonColor: '#e11d48',
        inputValidator: value => !value || value.trim().length < 5 ? 'Enter a reason of at least 5 characters.' : undefined,
    });
    if (!result.isConfirmed) return;
    const response = await run(() => axios.delete(route('opening-balances.remove', props.record.id), {
        data: { version: form.version, reason: result.value },
    }));
    if (response) emit('removed', props.record.id);
}
</script>

<template>
    <section :id="'opening-balance-edit-' + record.id" aria-label="Edit opening balance" class="space-y-4 bg-slate-50 p-5 sm:p-6 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ canUpdate ? 'Edit' : 'View' }} Opening Balance · Record #{{ record.id }}</h3>
                <p v-if="canUpdate" class="mt-1 text-xs text-slate-500">Save a replacement balance with a reason. The previous version remains in the audit history.</p>
            </div>
            <button type="button" :disabled="busy" @click="emit('close')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold disabled:opacity-50">Close</button>
        </div>
        <form class="space-y-5" @submit.prevent="save">
            <p v-if="error" role="alert" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
            <OpeningBalanceFormFields :form="form" :kind="kind" :ledgers="ledgers" :patrons="patrons" :busy="busy || !canUpdate" />
            <div class="flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <button v-if="can('OPENING_BALANCE.DELETE')" type="button" :disabled="busy" @click="remove" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-700 disabled:opacity-50">Remove Balance</button>
                <div class="flex gap-3">
                    <button type="button" :disabled="busy" @click="emit('close')" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold disabled:opacity-50">Cancel</button>
                    <button v-if="canUpdate" type="submit" :disabled="busy || !form.amount || Number(form.amount) <= 0 || !form.clearing_account_id || form.reason.trim().length < 5"
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ busy ? 'Saving...' : 'Replace & Post Balance' }}
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>
