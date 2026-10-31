<script setup>
import { computed } from 'vue';
import { formatDate, formatCurrency } from '@/Utils/formatters';
import PatronStatementView from './PatronStatementView.vue';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    },
    startDate: {
        type: [String, Object, Date],
        required: true
    }
});

const isSinglePatron = computed(() => !!props.reportData?.is_single_patron);

const openingDebit = computed(() => Number(props.reportData.opening_debit ?? Math.max(0, Number(props.reportData.opening_balance || 0))));
const openingCredit = computed(() => Number(props.reportData.opening_credit ?? Math.max(0, -Number(props.reportData.opening_balance || 0))));

const formattedStartDate = computed(() => formatDate(props.startDate));

const transactionsWithBalance = computed(() => {
    if (!props.reportData || isSinglePatron.value) return [];
    let balance = Number(props.reportData.opening_balance || 0);
    return (props.reportData.transactions || []).map(trx => {
        balance = trx.running_balance != null ? Number(trx.running_balance)
            : Math.round((balance + Number(trx.debit || 0) - Number(trx.credit || 0)) * 10000) / 10000;
        return { ...trx, running_balance: balance };
    });
});
</script>

<template>
    <!-- Single Patron Statement View -->
    <PatronStatementView v-if="isSinglePatron" :report-data="reportData" :show-back-button="false" />

    <!-- Standard Double-Entry General Ledger -->
    <div v-else class="overflow-x-auto border border-slate-200 rounded">
        <p v-if="reportData.balance_scope === 'all_ledgers'" class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
            All ledgers: opening debits and credits include both account balances and their balancing entries.
            The balance column shows their net. Select an account ledger or customer/vendor to view its opening balance.
        </p>
        <table class="w-full text-left border-collapse min-w-[1050px]">
            <thead>
                <tr
                    class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                    <th class="py-3 px-4 text-center whitespace-nowrap">S/No</th>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Ledger / Journal</th>
                    <th class="py-3 px-4">Particulars</th>
                    <th class="py-3 px-4">Voucher Type</th>
                    <th class="py-3 px-4">Reference</th>
                    <th class="py-3 px-4 text-right">Debit</th>
                    <th class="py-3 px-4 text-right">Credit</th>
                    <th class="py-3 px-4 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="text-[11px] font-semibold text-slate-700">
                <!-- Opening -->
                <tr class="bg-slate-50 border-b border-slate-200">
                    <td class="py-3 px-4 text-center text-slate-400">—</td>
                    <td class="py-3 px-4 text-slate-400 text-[0.7rem] italic">{{ formattedStartDate }}</td>
                    <td class="py-3 px-4">---</td>
                    <td class="py-3 px-4 font-bold text-[#1d2d3e] uppercase">Opening Balance</td>
                    <td class="py-3 px-4">---</td>
                    <td class="py-3 px-4">---</td>
                    <td class="py-3 px-4 text-right font-bold">{{ openingDebit > 0 ?
                        formatCurrency(openingDebit) : '---' }}</td>
                    <td class="py-3 px-4 text-right font-bold">{{ openingCredit > 0 ?
                        formatCurrency(openingCredit) : '---' }}</td>
                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e]">
                        {{ formatCurrency(Math.abs(reportData.opening_balance)) }}
                        <span class="text-[9px] ml-1 uppercase text-slate-400">{{ reportData.opening_balance >= 0 ? 'Dr'
                            : 'Cr' }}</span>
                    </td>
                </tr>

                <!-- Lines -->
                <tr v-for="(trx, idx) in transactionsWithBalance" :key="idx"
                    class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                    <td class="py-3 px-4 text-center text-slate-500">{{ idx + 1 }}</td>
                    <td class="py-3 px-4 text-slate-500 whitespace-nowrap">{{ trx.date }}</td>
                    <td class="py-3 px-4 font-bold text-slate-800">{{ trx.ledger_name || 'General Account' }}</td>
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-800 leading-tight">{{ trx.narration }}</div>
                    </td>
                    <td class="py-3 px-4">
                        <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">{{ trx.voucher_type || 'JOURNAL' }}</span>
                    </td>
                    <td class="py-3 px-4">
                        <div class="text-slate-800 font-bold tracking-tighter">{{ trx.voucher_no }}</div>
                    </td>
                    <td class="py-3 px-4 text-right text-slate-900 font-bold">
                        {{ trx.debit > 0 ? formatCurrency(trx.debit) : '---' }}
                    </td>
                    <td class="py-3 px-4 text-right text-slate-900 font-bold">
                        {{ trx.credit > 0 ? formatCurrency(trx.credit) : '---' }}
                    </td>
                    <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/55">
                        {{ formatCurrency(Math.abs(trx.running_balance)) }}
                        <span class="text-[9px] ml-1 uppercase text-slate-400">{{ trx.running_balance >= 0 ? 'Dr' : 'Cr' }}</span>
                    </td>
                </tr>

                <!-- Closing -->
                <tr class="bg-[#1d2d3e] text-white">
                    <td colspan="6"
                        class="py-4 px-6 text-right font-bold uppercase text-[10px] tracking-wider text-slate-300">Net
                        Closing Balance</td>
                    <td colspan="3" class="py-4 px-8 text-right font-black text-lg tracking-tight">
                        {{ formatCurrency(transactionsWithBalance.length > 0 ?
                            Math.abs(transactionsWithBalance[transactionsWithBalance.length - 1].running_balance) :
                            Math.abs(reportData.opening_balance)) }}
                        <span class="text-xs ml-2 uppercase opacity-60 font-semibold">
                            {{ (transactionsWithBalance.length > 0 ?
                                transactionsWithBalance[transactionsWithBalance.length - 1].running_balance :
                                reportData.opening_balance) >= 0 ? 'Debit' : 'Credit' }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
