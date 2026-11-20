<!--
Author: ragul-onemodo
Created: 2026-11-20 17:06:24 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({ reportData: { type: Object, default: () => ({}) } });
const rows = computed(() => props.reportData?.transactions || []);
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase text-slate-500">Total Credit Limit</div>
                <div class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(reportData.total_credit_limit || 0) }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs font-semibold uppercase text-amber-700">Used Credit</div>
                <div class="mt-1 text-lg font-bold text-amber-900">{{ formatCurrency(reportData.total_used_credit || 0) }}</div>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs font-semibold uppercase text-emerald-700">Available Credit</div>
                <div class="mt-1 text-lg font-bold text-emerald-900">{{ formatCurrency(reportData.total_available_credit || 0) }}</div>
            </div>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                    <tr><th class="px-4 py-3">Patron</th><th class="px-4 py-3 text-right">Credit Limit</th><th class="px-4 py-3 text-right">Used Credit</th><th class="px-4 py-3 text-right">Available Credit</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.patron_id">
                        <td class="px-4 py-3"><span class="font-medium text-slate-900">{{ row.patron_name }}</span><span v-if="row.code" class="ml-2 text-xs text-slate-500">{{ row.code }}</span></td>
                        <td class="px-4 py-3 text-right">{{ formatCurrency(row.credit_limit) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatCurrency(row.used_credit) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(row.available_credit) }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">No patron credit limits found for this plant.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
