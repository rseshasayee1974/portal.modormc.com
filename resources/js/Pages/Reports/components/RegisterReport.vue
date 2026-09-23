<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    reportData: { type: Object, required: true },
    currentPage: { type: Number, default: 1 },
    kind: { type: String, default: 'sales' },
});
const emit = defineEmits(['page-change']);
const columns = computed(() => props.reportData.columns || []);
const rows = computed(() => props.reportData.data || []);
const pagination = computed(() => props.reportData.pagination || {});
const value = (row, key) => key.startsWith('taxes.') ? (row.taxes?.[key.slice(6)] ?? 0) : (row[key] ?? '');
const display = (row, column) => {
    const cell = value(row, column.key);
    if (column.format === 'number') return Number(cell || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (column.format === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(cell)) return cell.split('-').reverse().join('/');
    return cell === '' ? '—' : cell;
};
const total = (column) => column.total ? Number(value(props.reportData.totals || {}, column.total) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div v-for="metric in [{ key: 'taxable', label: kind === 'sales' ? 'Taxable Sales' : 'Taxable Purchases' }, { key: 'gst', label: 'Total Tax' }, { key: 'grand_total', label: 'Net Amount' }]" :key="metric.key"
                class="rounded border border-slate-200 bg-slate-50 p-4">
                <span class="block text-xs text-slate-500 font-semibold">{{ metric.label }}</span>
                <span class="block mt-1 text-xl font-bold text-slate-800">{{ formatCurrency(reportData.totals?.[metric.key] || 0) }}</span>
            </div>
        </div>
        <div class="flex flex-wrap justify-between gap-2 text-xs text-slate-500">
            <span>{{ reportData.register_view === 'summary' ? 'One row per invoice / bill' : 'One row per item' }} · {{ pagination.total || 0 }} records · Amounts in INR</span>
            <span>Totals include all matching records.</span>
        </div>
        <p v-if="reportData.note" class="text-xs text-slate-500">{{ reportData.note }}</p>
        <div class="overflow-auto max-h-[65vh] rounded border border-slate-200" tabindex="0" aria-label="Register report table">
            <table class="w-full text-xs text-left border-collapse whitespace-nowrap">
                <thead class="sticky top-0 z-10 bg-slate-100 text-slate-600">
                    <tr>
                        <th scope="col" class="p-3 border-b border-slate-200">#</th>
                        <th v-for="column in columns" :key="column.key" scope="col" class="p-3 border-b border-slate-200" :class="{ 'text-right': column.format === 'number' }">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                    <tr v-for="(row, index) in rows" :key="row.id" class="border-b border-slate-100 even:bg-slate-50 hover:bg-blue-50">
                        <td class="p-3 text-slate-400">{{ ((pagination.current_page || 1) - 1) * (pagination.per_page || 100) + index + 1 }}</td>
                        <td v-for="column in columns" :key="column.key" class="p-3" :class="[column.format === 'number' ? 'text-right tabular-nums' : '', column.key === 'net_amount' ? 'font-bold' : '']">
                            {{ display(row, column) }}
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td :colspan="columns.length + 1" class="p-10 text-center text-slate-500">No records found for the selected filters.</td></tr>
                </tbody>
                <tfoot class="bg-slate-100 font-bold text-slate-800">
                    <tr><td class="p-3">Total</td><td v-for="column in columns" :key="column.key" class="p-3 text-right tabular-nums">{{ total(column) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between gap-3 text-xs">
            <span class="text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)</span>
            <div class="flex gap-2">
                <button type="button" @click="emit('page-change', currentPage - 1)" :disabled="currentPage <= 1" class="rounded border border-slate-200 px-3 py-2 disabled:opacity-40">Previous</button>
                <button type="button" @click="emit('page-change', currentPage + 1)" :disabled="currentPage >= pagination.last_page" class="rounded border border-slate-200 px-3 py-2 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>
</template>
