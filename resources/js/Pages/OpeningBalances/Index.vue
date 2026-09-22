<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Create from './Create.vue';
import OpeningBalanceList from './OpeningBalanceList.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { ArrowPathIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    records: { type: Array, default: () => [] },
    legacy: { type: Object, default: null },
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
});
const currentRecords = ref([...props.records]);
const { can } = usePermissions();
const list = ref(null);
const busy = ref(false);
const notice = ref('');
const convertingLedger = ref(props.legacy?.clearing_account_id || null);
const clearing = computed(() => props.ledgers
    .filter(ledger => !ledger.is_pnl && !props.patrons.some(patron =>
        Number(patron.debit_ledger_id) === Number(ledger.id) || Number(patron.credit_ledger_id) === Number(ledger.id)))
    .map(ledger => ({ label: ledger.title, value: ledger.id })));

watch(() => props.records, records => { currentRecords.value = [...records]; });
watch(() => props.legacy, legacy => { convertingLedger.value = legacy?.clearing_account_id || null; });

function saved(record, previousId = null) {
    const index = currentRecords.value.findIndex(item => Number(item.id) === Number(previousId));
    if (index >= 0) {
        const updated = [...currentRecords.value];
        updated[index] = record;
        currentRecords.value = updated;
    } else {
        currentRecords.value = [record, ...currentRecords.value.filter(item => Number(item.id) !== Number(record.id))];
    }
    notice.value = previousId ? 'Opening balance updated successfully.' : 'Opening balance saved and posted successfully.';
}

function removed(id) {
    currentRecords.value = currentRecords.value.filter(record => Number(record.id) !== Number(id));
    notice.value = 'Opening balance removed. Its audit history is retained.';
}

function editExisting(record) {
    if (!can('OPENING_BALANCE.UPDATE') && !can('OPENING_BALANCE.DELETE')) {
        notice.value = 'This account already has an opening balance. You do not have permission to change it.';
        return;
    }
    notice.value = 'This account already has an opening balance. Edit it in the expanded row below.';
    list.value?.open(record);
}

async function convert() {
    if (busy.value || !can('OPENING_BALANCE.CREATE')) return;
    const result = await Swal.fire({
        title: 'Convert Legacy Balances?',
        text: 'Convert the existing setup into separate customer, vendor and ledger balances.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Convert Balances',
    });
    if (!result.isConfirmed) return;
    busy.value = true;
    try {
        await axios.post(route('opening-balances.convert'), { clearing_account_id: convertingLedger.value });
        notice.value = 'Existing balances converted successfully.';
        router.reload({ only: ['records', 'legacy'], onFinish: () => { busy.value = false; } });
    } catch (e) {
        busy.value = false;
        Swal.fire('Conversion failed', Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || e.response?.data?.message || 'Unable to convert opening balances.', 'error');
    }
}
</script>

<template>
    <AppLayout title="Opening Balances">
        <template #header><ModuleSubTopNav /></template>
        <Head title="Opening Balances | Initial Setup" />
        <main class="max-w-7xl mx-auto   space-y-6 text-slate-800 dark:text-slate-100">
            <p v-if="notice" role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ notice }}</p>
            <section
                v-if="legacy"
                class="rounded-2xl border border-amber-300 dark:border-amber-700 bg-amber-50/80 dark:bg-amber-950/40 p-6 shadow-sm space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 shrink-0">
                        <SparklesIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-amber-900 dark:text-amber-100">
                            Convert Legacy Opening Balances Setup
                        </h2>
                        <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5 max-w-3xl leading-relaxed">
                            Your previous setup contains <strong class="font-bold">{{ legacy.lines?.length || 0 }} balances</strong> recorded as a single batch. Convert it once to manage each customer, vendor, or general ledger balance individually with instant replacement and granular audit histories.
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <div class="md:col-span-2">
                        <BaseSelect
                            v-model="convertingLedger"
                            :options="clearing"
                            optionLabel="label"
                            optionValue="value"
                            label="Balancing / Clearing Ledger Account"
                            :disabled="busy || !can('OPENING_BALANCE.CREATE') || !!legacy.clearing_account_id"
                            placeholder="Select balancing clearing account"
                            filter
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            v-if="can('OPENING_BALANCE.CREATE')"
                            type="button"
                            @click="convert"
                            :disabled="busy || !convertingLedger"
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm disabled:opacity-50 cursor-pointer"
                        >
                            <span v-if="busy" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <ArrowPathIcon v-else class="w-4 h-4" />
                            <span>Convert to Individual Balances</span>
                        </button>
                    </div>
                </div>
            </section>
            <template v-else>
                <Create v-if="can('OPENING_BALANCE.CREATE')" :records="currentRecords" :ledgers="ledgers" :patrons="patrons" @created="saved" @edit="editExisting" />
                <OpeningBalanceList ref="list" :records="currentRecords" :ledgers="ledgers" :patrons="patrons" @saved="saved" @removed="removed" />
            </template>
        </main>
    </AppLayout>
</template>
