<!--
Author: ragul-onemodo
Created: 2026-10-29 14:11:18 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref } from 'vue';
import { formatCurrency, formatDate } from '@/Utils/formatters';

defineProps({ reportData: { type: Object, required: true } });
const showDetails = ref(false);
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div v-for="card in [
                { label: 'Customer Receipts', amount: reportData.total_receipt, color: 'text-emerald-700' },
                { label: 'Customer Payments', amount: reportData.total_payment, color: 'text-rose-700' },
                { label: 'Net Received', amount: reportData.net_received, color: 'text-indigo-700' },
            ]" :key="card.label" class="rounded border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold text-slate-500">{{ card.label }}</p>
                <p class="mt-2 text-xl font-bold" :class="card.color">{{ formatCurrency(card.amount || 0) }}</p>
            </div>
        </div>
        <p class="text-xs text-slate-500">
            Completed customer receipts and outgoing payments, including posted manual vouchers.
            Net received = receipts minus payments. Pending, failed and deleted transactions are excluded.
        </p>
        <div class="overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="w-full min-w-[650px] text-sm text-left">
                <thead class="bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="p-3">Customer</th>
                        <th class="p-3 text-right">Receipt Amount</th>
                        <th class="p-3 text-right">Payment Amount</th>
                        <th class="p-3 text-right">Net Received</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in reportData.transactions || []" :key="row.customer_id"
                        class="border-t border-slate-100">
                        <td class="p-3 font-semibold">{{ row.customer_name }}</td>
                        <td class="p-3 text-right">{{ formatCurrency(row.receipt_amt) }}</td>
                        <td class="p-3 text-right">{{ formatCurrency(row.payment_amt) }}</td>
                        <td class="p-3 text-right font-semibold">{{ formatCurrency(Math.abs(row.net_amt || 0)) }}</td>
                    </tr>
                    <tr v-if="!reportData.transactions?.length">
                        <td colspan="4" class="p-8 text-center text-slate-500">No customer receipts or payments found
                            for this period.</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50 font-bold">
                    <tr>
                        <td class="p-3">Total</td>
                        <td class="p-3 text-right">{{ formatCurrency(reportData.total_receipt || 0) }}</td>
                        <td class="p-3 text-right">{{ formatCurrency(reportData.total_payment || 0) }}</td>
                        <td class="p-3 text-right">{{ formatCurrency(reportData.net_received || 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <button type="button" class="text-sm font-semibold text-indigo-600" :aria-expanded="showDetails"
            @click="showDetails = !showDetails">{{ showDetails ? 'Hide' : 'Show' }} voucher details ({{
                reportData.voucher_count || 0 }})</button>
        <div v-if="showDetails" class="overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Customer</th>
                        <th class="p-3">Reference</th>
                        <th class="p-3">Type</th>
                        <th class="p-3">Mode</th>
                        <th class="p-3">Account</th>
                        <th class="p-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in reportData.details || []" :key="row.id" class="border-t border-slate-100">
                        <td class="p-3 whitespace-nowrap">{{ formatDate(row.date) }}</td>
                        <td class="p-3">{{ row.customer_name }}</td>
                        <td class="p-3">{{ row.reference }}</td>
                        <td class="p-3">{{ row.type }}</td>
                        <td class="p-3">{{ row.mode }}</td>
                        <td class="p-3">{{ row.account }}</td>
                        <td class="p-3 text-right">{{ formatCurrency(row.amount) }}</td>
                    </tr>
                    <tr v-if="!reportData.details?.length">
                        <td colspan="7" class="p-6 text-center text-slate-500">No vouchers found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
