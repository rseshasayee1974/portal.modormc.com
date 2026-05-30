<script setup>
import { computed } from 'vue';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    },
    startDate: {
        type: String,
        required: true
    }
});

const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

const transactionsWithBalance = computed(() => {
    if (!props.reportData) return [];
    let balance = props.reportData.opening_balance;
    return props.reportData.transactions.map(trx => {
        balance += (trx.debit - trx.credit);
        return { ...trx, running_balance: balance };
    });
});
</script>

<template>
    <div class="overflow-x-auto border border-slate-200 rounded">
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead>
                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Particulars</th>
                    <th class="py-3 px-4">Reference</th>
                    <th class="py-3 px-4 text-right">Amount</th>
                    <th class="py-3 px-4 text-center">Type</th>
                    <th class="py-3 px-4 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="text-[11px] font-semibold text-slate-700">
                <!-- Opening -->
                <tr class="bg-slate-50 border-b border-slate-200">
                    <td class="py-3 px-4 text-slate-400 italic">{{ startDate }}</td>
                    <td class="py-3 px-4 font-bold text-[#1d2d3e] uppercase">Opening Balance</td>
                    <td class="py-3 px-4">---</td>
                    <td class="py-3 px-4 text-right font-bold">{{ formatCurrency(Math.abs(reportData.opening_balance)) }}</td>
                    <td class="py-3 px-4 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                            {{ reportData.opening_balance >= 0 ? 'DR' : 'CR' }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e]">
                        {{ formatCurrency(reportData.opening_balance) }}
                    </td>
                </tr>

                <!-- Lines -->
                <tr v-for="(trx, idx) in transactionsWithBalance" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                    <td class="py-3 px-4 text-slate-500 whitespace-nowrap">{{ trx.date }}</td>
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-800 leading-tight">{{ trx.narration }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">{{ trx.voucher_type }}</span>
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <div class="text-slate-800 font-bold tracking-tighter">{{ trx.voucher_no }}</div>
                    </td>
                    <td class="py-3 px-4 text-right text-slate-900 font-bold">
                        {{ formatCurrency(trx.amount) }}
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="trx.type === 'Dr' ? 'bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]' : 'bg-[#fce4d6] text-[#c65911] border border-[#f8cbad]'">
                            {{ trx.type.toUpperCase() }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/55">
                        {{ formatCurrency(Math.abs(trx.running_balance)) }}
                        <span class="text-[9px] ml-1 uppercase text-slate-400">{{ trx.running_balance >= 0 ? 'Dr' : 'Cr' }}</span>
                    </td>
                </tr>

                <!-- Closing -->
                <tr class="bg-[#1d2d3e] text-white">
                    <td colspan="3" class="py-4 px-6 text-right font-bold uppercase text-[10px] tracking-wider text-slate-300">Net Closing Balance</td>
                    <td colspan="3" class="py-4 px-8 text-right font-black text-lg tracking-tight">
                        {{ formatCurrency(transactionsWithBalance.length > 0 ? Math.abs(transactionsWithBalance[transactionsWithBalance.length - 1].running_balance) : Math.abs(reportData.opening_balance)) }}
                        <span class="text-xs ml-2 uppercase opacity-60 font-semibold">
                            {{ (transactionsWithBalance.length > 0 ? transactionsWithBalance[transactionsWithBalance.length - 1].running_balance : reportData.opening_balance) >= 0 ? 'Debit' : 'Credit' }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
