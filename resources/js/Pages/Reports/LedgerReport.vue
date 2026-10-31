<script setup>
import { APP_LOCALE } from '@/Utils/locale';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import {
    DocumentTextIcon,
    ArrowPathIcon,
    PrinterIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    ledgers: Array,
    filters: Object,
    reportData: Object
});

const form = useForm({
    ledger_id: props.filters.ledger_id,
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const reportData = ref(props.reportData || { opening_balance: 0, transactions: [] });
const generating = ref(false);
const reportError = ref('');
watch(() => props.reportData, data => { reportData.value = data || { opening_balance: 0, transactions: [] }; });

const submit = async () => {
    if (generating.value) return;
    generating.value = true;
    reportError.value = '';
    try {
        const response = await axios.get(route('reports.generate'), {
            params: { type: 'ledger', id: form.ledger_id, patron_id: props.filters?.patron_id,
                start_date: form.start_date, end_date: form.end_date },
        });
        reportData.value = response.data;
    } catch (error) {
        reportError.value = error.response?.data?.message || error.response?.data?.error || 'Unable to generate the ledger report.';
    } finally {
        generating.value = false;
    }
};

const transactionsWithBalance = computed(() => {
    let balance = Number(reportData.value.opening_balance || 0);
    return (reportData.value.transactions || []).map(trx => {
        balance = trx.running_balance != null ? Number(trx.running_balance)
            : Math.round((balance + Number(trx.debit || 0) - Number(trx.credit || 0)) * 10000) / 10000;
        return { ...trx, running_balance: balance };
    });
});

const openingDebit = computed(() => Number(reportData.value.opening_debit ?? Math.max(0, Number(reportData.value.opening_balance || 0))));
const openingCredit = computed(() => Number(reportData.value.opening_credit ?? Math.max(0, -Number(reportData.value.opening_balance || 0))));

const closingBalance = computed(() => Number(reportData.value.closing_balance
    ?? transactionsWithBalance.value.at(-1)?.running_balance ?? reportData.value.opening_balance ?? 0));

const formatCurrency = (val) => {
    return new Intl.NumberFormat(APP_LOCALE, {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

const printReport = () => {
    window.print();
};

</script>

<template>
    <AppLayout title="Ledger Report">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Filter Header -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8 no-print">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-indigo-600 rounded-xl text-white shadow-lg shadow-indigo-100">
                                <DocumentTextIcon class="h-6 w-6" />
                            </div>
                            <div>
                                <h1 class="text-xl font-black text-slate-800 tracking-tight">Ledger Statement</h1>
                                <p class="text-sm text-slate-500 font-medium italic">General Ledger Analysis</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <BaseButton variant="outlined" severity="secondary" @click="printReport">
                                <PrinterIcon class="h-4 w-4 mr-2" />
                                Print
                            </BaseButton>
                        </div>
                    </div>

                    <div class="p-6 bg-white">
                        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                            <div class="col-span-1 md:col-span-2">
                                <BaseSelect v-model="form.ledger_id" :options="ledgers" optionLabel="title"
                                    optionValue="id" label="Select Ledger" placeholder="Choose an account..." filter
                                    showClear />
                            </div>
                            <div>
                                <BaseDatePicker v-model="form.start_date" label="From Date" fluid />
                            </div>
                            <div>
                                <BaseDatePicker v-model="form.end_date" label="To Date" fluid />
                            </div>
                            <div class="col-span-full flex justify-end gap-3 mt-2 border-t pt-6">
                                <BaseButton type="submit" variant="filled" severity="primary"
                                    :loading="generating">
                                    <ArrowPathIcon class="h-4 w-4 mr-2" />
                                    Generate Report
                                </BaseButton>
                            </div>
                        </form>
                    </div>
                </div>

                <p v-if="reportError" role="alert" class="mb-4 rounded-lg bg-rose-50 p-4 text-rose-700">{{ reportError }}</p>

                <!-- Report Content -->
                <div v-if="reportData.transactions.length > 0 || openingDebit > 0 || openingCredit > 0"
                    class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden print:shadow-none print:border-none">

                    <!-- Report Header (Visible in Print) -->
                    <div class="hidden print:block p-8 text-center border-b-2 border-slate-900 mb-8">
                        <h1 class="text-3xl font-black uppercase tracking-widest">Ledger Statement</h1>
                        <p class="text-lg font-bold mt-2">{{ledgers.find(l => l.id == form.ledger_id)?.title}}</p>
                        <p class="text-sm mt-1 text-slate-600 italic">Period: {{ form.start_date }} to {{ form.end_date
                        }}</p>
                    </div>

                    <div class="p-8">
                        <p v-if="reportData.balance_scope === 'all_ledgers'" class="mb-4 text-sm text-slate-600">
                            All ledgers: opening debits and credits include balancing entries. The balance column shows their net.
                            Select an account ledger to view its opening balance.
                        </p>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-100">
                                    <th class="pb-4 font-black">Date</th>
                                    <th class="pb-4 font-black">Ledger / Journal</th>
                                    <th class="pb-4 font-black">Particulars</th>
                                    <th class="pb-4 font-black">Voucher Type</th>
                                    <th class="pb-4 font-black">Voucher</th>
                                    <th class="pb-4 text-right font-black">Debit</th>
                                    <th class="pb-4 text-right font-black">Credit</th>
                                    <th class="pb-4 text-right font-black">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium">
                                <!-- Opening Balance Row -->
                                <tr class="border-b border-slate-50 bg-slate-50/30">
                                    <td class="py-4 text-slate-400 italic">{{ form.start_date }}</td>
                                    <td class="py-4">---</td>
                                    <td class="py-4 font-black text-indigo-600 uppercase tracking-tighter">Opening
                                        Balance</td>
                                    <td class="py-4">---</td>
                                    <td class="py-4">---</td>
                                    <td class="py-4 text-right">
                                        {{ openingDebit > 0 ? formatCurrency(openingDebit) :
                                            '---' }}
                                    </td>
                                    <td class="py-4 text-right">
                                        {{ openingCredit > 0 ?
                                            formatCurrency(openingCredit) : '---' }} </td>
                                    <td class="py-4 text-right font-black"
                                        :class="reportData.opening_balance >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ formatCurrency(Math.abs(Number(reportData.opening_balance || 0))) }}
                                        <span class="text-[10px] ml-1 uppercase">{{ reportData.opening_balance >= 0 ?
                                            'Dr' : 'Cr' }}</span>
                                    </td>
                                </tr>

                                <!-- Transactions -->
                                <tr v-for="(trx, idx) in transactionsWithBalance" :key="idx"
                                    class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 text-slate-600 whitespace-nowrap">{{ trx.date }}</td>
                                    <td class="py-4 font-bold text-slate-800">{{ trx.ledger_name || 'General Account' }}
                                    </td>
                                    <td class="py-4">
                                        <div class="font-bold text-slate-800">{{ trx.narration }}</div>
                                    </td>
                                    <td class="py-4 text-[10px] text-slate-400 uppercase tracking-widest">{{ trx.voucher_type || 'JOURNAL' }}</td>
                                    <td class="py-4 text-indigo-600 font-black tracking-tighter">{{ trx.voucher_no }}
                                    </td>
                                    <td class="py-4 text-right text-slate-900 font-bold">
                                        {{ trx.debit > 0 ? formatCurrency(trx.debit) : '---' }}
                                    </td>
                                    <td class="py-4 text-right text-slate-900 font-bold">
                                        {{ trx.credit > 0 ? formatCurrency(trx.credit) : '---' }}
                                    </td>
                                    <td class="py-4 text-right font-black text-slate-800 bg-slate-50/20">
                                        {{ formatCurrency(Math.abs(trx.running_balance)) }}
                                        <span class="text-[10px] ml-1 uppercase text-slate-400">{{ trx.running_balance
                                            >= 0 ? 'Dr' : 'Cr' }}</span>
                                    </td>
                                </tr>

                                <!-- Closing Balance Footer -->
                                <tr class="bg-indigo-900 text-white shadow-xl shadow-indigo-100">
                                    <td colspan="5"
                                        class="py-6 px-4 text-right font-black uppercase tracking-widest text-[11px]">
                                        Closing Balance</td>
                                    <td colspan="3" class="py-6 px-8 text-right font-black text-xl">
                                        {{ formatCurrency(Math.abs(closingBalance)) }}
                                        <span class="text-xs ml-2 uppercase opacity-60">
                                            {{ closingBalance >= 0 ? 'Debit' : 'Credit' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="form.ledger_id"
                    class="bg-white rounded-2xl p-20 text-center border border-dashed border-slate-300">
                    <div class="mx-auto w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                        <DocumentTextIcon class="h-8 w-8 text-slate-300" />
                    </div>
                    <h3 class="text-lg font-black text-slate-800">No transactions found</h3>
                    <p class="text-slate-500 max-w-xs mx-auto mt-2">There are no journal entries for this ledger in the
                        selected date range.</p>
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
