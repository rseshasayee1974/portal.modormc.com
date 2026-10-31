<!--
Author: ragul-onemodo
Created: 2026-10-29 14:43:56 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { formatCurrency, formatDate } from '@/Utils/formatters';

defineProps({ reportData: { type: Object, required: true } });
const moneyField = field => field.endsWith('_amt');
const display = (row, field) => moneyField(field) ? formatCurrency(row[field] || 0)
    : ['date', 'source_date'].includes(field) ? (row[field] ? formatDate(row[field]) : '—') : (row[field] || '—');
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold text-slate-500">Credit Note Amount</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">{{ formatCurrency(reportData.credit_note_amount || 0) }}</p>
            </div>
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold text-slate-500">Debit Note Amount</p>
                <p class="mt-2 text-xl font-bold text-indigo-700">{{ formatCurrency(reportData.debit_note_amount || 0) }}</p>
            </div>
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold text-slate-500">Number of Notes</p>
                <p class="mt-2 text-xl font-bold text-slate-800">{{ reportData.total_count || 0 }}</p>
            </div>
        </div>
        <p class="text-xs text-slate-500">Amounts match the recorded notes. Cancelled, reversed and deleted notes are excluded. Credit and debit note totals are shown separately.</p>
        <div class="overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="w-full min-w-[1400px] text-xs text-left">
                <thead class="bg-slate-50 text-slate-600">
                    <tr><th class="py-3 px-3 text-center whitespace-nowrap">S/No</th>
                        <th v-for="(header, index) in reportData.headers" :key="header" class="p-3"
                        :class="moneyField(reportData.fields[index]) ? 'text-right' : ''">{{ header }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(row, serialIndex) in reportData.transactions || []" :key="row.id" class="border-t border-slate-100">
                        <td class="py-3 px-3 text-center text-slate-400">{{ serialIndex + 1 }}</td>
                        <td v-for="field in reportData.fields" :key="field" class="p-3"
                            :class="moneyField(field) ? 'text-right whitespace-nowrap' : ''">
                            <span :class="field === 'note_number' ? 'font-semibold' : ''">{{ display(row, field) }}</span>
                            <p v-if="field === 'note_number' && row.reason" class="mt-1 max-w-48 truncate text-slate-400" :title="row.reason">{{ row.reason }}</p>
                        </td>
                    </tr>
                    <tr v-if="!reportData.transactions?.length">
                        <td :colspan="(reportData.fields?.length || 12) + 1" class="p-8 text-center text-slate-500">No credit or debit notes found for the selected period.</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50 font-bold">
                    <tr><td class="py-3 px-3"></td>
                        <td v-for="(field, index) in reportData.fields" :key="field" class="p-3"
                        :class="moneyField(field) ? 'text-right whitespace-nowrap' : ''">
                        {{ index === 0 ? 'Total' : moneyField(field) ? formatCurrency(reportData.totals?.[field] || 0) : '' }}
                    </td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
