<script setup>
import { CubeIcon } from '@heroicons/vue/24/outline';

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
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Consolidated Stock</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_quantity }}</span>
                </div>
                <CubeIcon class="w-5 h-5 text-slate-400" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="5%">#</th>
                        <th class="py-3 px-4 text-center" width="15%">Date</th>
                        <th class="py-3 px-4" width="40%">Product Name</th>
                        <th class="py-3 px-4 text-center" width="10%">UOM</th>
                        <th class="py-3 px-4 text-right" width="15%">Opening Qty</th>
                        <th class="py-3 px-4 text-right" width="15%">Current Stock</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                        <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                        <td class="py-3 px-4 text-right text-slate-600">{{ row.opening_qty }}</td>
                        <td class="py-3 px-4 text-right font-black text-[#1d2d3e] bg-slate-50/55">{{ row.quantity }}</td>
                    </tr>
                    <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                        <td colspan="5" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Current Stock</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
