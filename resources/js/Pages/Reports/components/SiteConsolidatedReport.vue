<script setup>
import { computed } from 'vue';

const props = defineProps({
    reportData: {
        type: Object,
        default: () => ({})
    }
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    }).format(val || 0);
};

const formatQuantity = (val) => {
    return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(val || 0);
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <div class="px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-xs text-slate-700 uppercase flex items-center justify-between">
                <span>Unload Site Consolidated Summary</span>
                <span class="text-slate-400 font-normal normal-case">Consolidated by delivery/unloading destination site with batch size and delivered volume</span>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[750px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3" width="28%">Unload Site Name</th>
                            <th class="py-3 px-3" width="23%">Customer / Party</th>
                            <th class="py-3 px-3 text-center" width="10%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="11%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="11%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="12%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.site_name }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ row.customer_name || '-' }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.transactions?.length">
                            <td colspan="7" class="py-8 text-center text-slate-400">No site dispatch records found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="3" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Site Volume</td>
                            <td class="py-3 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_trips }}</td>
                            <!-- <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_batch_size) }}</td> -->
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_quantity) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
