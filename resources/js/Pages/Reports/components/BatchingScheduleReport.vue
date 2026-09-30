<script setup>
import { formatQuantity } from '@/Utils/formatters';
defineProps({ reportData: { type: Object, required: true } });
</script>

<template>
    <div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50">
                <span class="text-xs font-bold text-slate-500">Schedule Slots</span>
                <div class="text-lg font-bold text-slate-800">{{ reportData.total_schedules }}</div>
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50">
                <span class="text-xs font-bold text-slate-500">Scheduled Volume (excluding cancelled)</span>
                <div class="text-lg font-bold text-slate-800">{{ formatQuantity(reportData.total_quantity) }} m³</div>
            </div>
        </div>
        <p class="text-xs text-slate-500 mb-3">Filtered by schedule date. Cancelled slots are included in the list.</p>
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-3 py-3">#</th>
                        <th v-for="(label, field) in reportData.columns" :key="field" class="px-3 py-3" :class="{ 'text-right': field === 'quantity' }">{{ label }}</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3">{{ idx + 1 }}</td>
                        <td v-for="(label, field) in reportData.columns" :key="field" class="px-3 py-3" :class="{ 'text-right': field === 'quantity' }">{{ field === 'quantity' ? formatQuantity(row[field]) : row[field] }}</td>
                    </tr>
                    <tr v-if="!reportData.transactions?.length">
                        <td :colspan="Object.keys(reportData.columns).length + 1" class="px-3 py-6 text-center text-slate-500">No batching schedules found for the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
