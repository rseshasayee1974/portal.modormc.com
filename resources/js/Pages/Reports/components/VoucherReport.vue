<script setup>
import { computed } from 'vue';
import { formatCurrency, formatDate } from '@/Utils/formatters';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    },
    startDate: {
        type: [String, Object, Date],
        default: null
    }
});

const isPayment = computed(() => {
    return (props.reportData?.voucher_type || '').toUpperCase() === 'PAYMENT';
});

const voucherTitle = computed(() => {
    return isPayment.value ? 'Payment Vouchers (Cash Outflows)' : 'Receipt Vouchers (Cash Inflows)';
});

const transactions = computed(() => {
    return props.reportData?.transactions || [];
});

const totalAmount = computed(() => {
    if (props.reportData?.total_amount !== undefined) {
        return props.reportData.total_amount;
    }
    return transactions.value.reduce((sum, trx) => sum + (Number(trx.amount) || 0), 0);
});

const totalCount = computed(() => {
    if (props.reportData?.total_count !== undefined) {
        return props.reportData.total_count;
    }
    return transactions.value.length;
});
</script>

<template>
    <div class="space-y-4">
        <!-- Summary KPI Header Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Vouchers</span>
                    <div class="text-xl font-black text-slate-800 mt-1">{{ totalCount }}</div>
                </div>
                <div class="w-10 h-10 rounded bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 font-bold text-sm">
                    #
                </div>
            </div>

            <div class="bg-white p-4 rounded border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Total {{ isPayment ? 'Paid Out' : 'Received' }}
                    </span>
                    <div class="text-xl font-black" :class="isPayment ? 'text-rose-600' : 'text-emerald-600'">
                        {{ formatCurrency(totalAmount) }}
                    </div>
                </div>
                <div 
                    class="w-10 h-10 rounded border flex items-center justify-center font-bold text-sm"
                    :class="isPayment ? 'bg-rose-50 text-rose-600 border-rose-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'"
                >
                    ₹
                </div>
            </div>

            <div class="bg-white p-4 rounded border border-slate-200 shadow-sm flex items-center justify-between sm:col-span-2">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Statement Scope</span>
                    <div class="text-xs font-bold text-slate-700 mt-1">
                        {{ voucherTitle }}
                    </div>
                </div>
                <span 
                    class="px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-wider border"
                    :class="isPayment ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                >
                    {{ isPayment ? 'PAYMENT' : 'RECEIPT' }}
                </span>
            </div>
        </div>

        <!-- Statement Table -->
        <div class="overflow-x-auto border border-slate-200 rounded bg-white shadow-sm">
            <table class="w-full text-left border-collapse min-w-[850px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-3 text-center" width="4%">#</th>
                        <th class="py-3 px-4 text-center" width="10%">Date</th>
                        <th class="py-3 px-4" width="16%">Voucher / Ref #</th>
                        <th class="py-3 px-4" width="22%">{{ isPayment ? 'Paid To (Party)' : 'Received From (Party)' }}</th>
                        <th class="py-3 px-4" width="18%">{{ isPayment ? 'Paid From (Account)' : 'Received Into (Account)' }}</th>
                        <th class="py-3 px-3 text-center" width="10%">Mode</th>
                        <th class="py-3 px-4 text-center" width="8%">Status</th>
                        <th class="py-3 px-4 text-right" width="12%">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr 
                        v-for="(trx, idx) in transactions" 
                        :key="trx.id || idx" 
                        class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors"
                    >
                        <td class="py-3 px-3 text-center text-slate-400 text-[10px]">{{ idx + 1 }}</td>
                        <td class="py-3 px-4 text-center text-slate-600 whitespace-nowrap">{{ formatDate(trx.date) }}</td>
                        <td class="py-3 px-4">
                            <span class="font-bold text-slate-900 tracking-tight">{{ trx.voucher_no || '-' }}</span>
                            <div v-if="trx.narration" class="text-[10px] text-slate-400 font-normal truncate max-w-xs mt-0.5" :title="trx.narration">
                                {{ trx.narration }}
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-800">{{ trx.party_name || 'N/A' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-semibold text-slate-600">{{ trx.account_name || 'N/A' }}</div>
                        </td>
                        <td class="py-3 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                {{ trx.payment_mode || 'Cash' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                                {{ trx.status || 'Paid' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">
                            {{ formatCurrency(trx.amount) }}
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="transactions.length === 0">
                        <td colspan="8" class="py-12 text-center text-slate-400 italic">
                            No {{ isPayment ? 'payment' : 'receipt' }} vouchers recorded for the selected period and query scopes.
                        </td>
                    </tr>

                    <!-- Total Summary Row -->
                    <tr v-if="transactions.length > 0" class="bg-[#1d2d3e] text-white">
                        <td colspan="7" class="py-3.5 px-6 text-right font-bold uppercase text-[10px] tracking-wider text-slate-300">
                            Total {{ isPayment ? 'Payments' : 'Receipts' }} ({{ transactions.length }} Vouchers)
                        </td>
                        <td class="py-3.5 px-4 text-right font-black text-base tracking-tight text-white">
                            {{ formatCurrency(totalAmount) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
