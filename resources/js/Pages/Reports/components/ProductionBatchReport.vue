<script setup>
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div>
        <!-- KPI Block Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Batched Volume</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_batch_size }} m³</span>
                </div>
                <Cog6ToothIcon class="w-5 h-5 text-slate-400" />
            </div>
        </div>

        <!-- Consolidated Material Consumption Summary -->
        <div class="mb-6 border border-slate-200 rounded" v-if="reportData.material_summary && reportData.material_summary.length > 0">
            <div class="px-4 py-2.5 bg-[#f2f4f7] border-b border-slate-200 font-bold text-[10px] text-slate-600 uppercase">
                Consolidated Raw Aggregate Consumption
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-200">
                <div v-for="(mRow, idx) in reportData.material_summary" :key="idx" class="p-4">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">{{ mRow.material_name }}</span>
                    <div class="flex justify-between items-end mt-2">
                        <div>
                            <span class="text-[9px] text-slate-400 block font-semibold">Actual</span>
                            <span class="text-sm font-black text-[#1d2d3e] block">{{ mRow.actual_qty.toFixed(1) }} kg</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] text-slate-400 block font-semibold">Target</span>
                            <span class="text-xs font-bold text-slate-500 block">{{ mRow.target_qty.toFixed(1) }} kg</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="5%">#</th>
                        <th class="py-3 px-4 text-center" width="12%">Start Date</th>
                        <th class="py-3 px-4 text-center" width="12%">Batch No</th>
                        <th class="py-3 px-4 text-center" width="12%">Sales Order</th>
                        <th class="py-3 px-4" width="25%">Mix Design</th>
                        <th class="py-3 px-4 text-right" width="12%">Batch Size</th>
                        <th class="py-3 px-4" width="12%">Operator</th>
                        <th class="py-3 px-4 text-center" width="10%">Status</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                        <td class="py-3 px-4 text-center font-bold text-slate-700">{{ row.batch_no }}</td>
                        <td class="py-3 px-4 text-center font-bold text-[#0064d2]">{{ row.sales_order }}</td>
                        <td class="py-3 px-4 text-slate-800">{{ row.mix_design }}</td>
                        <td class="py-3 px-4 text-right font-black text-[#1d2d3e] bg-slate-50/55">{{ row.batch_size }} m³</td>
                        <td class="py-3 px-4 text-slate-600 capitalize">{{ row.operator }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                                {{ row.status }}
                            </span>
                        </td>
                    </tr>
                    <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                        <td colspan="5" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Batched Volume</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_batch_size }} m³</td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
