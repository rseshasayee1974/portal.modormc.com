<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: Object
});

const perPage = ref(30);
const currentPage = ref(1);

const consolidatedList = computed(() => props.reportData?.consolidated || props.reportData?.transactions || []);
const totalRows = computed(() => consolidatedList.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalRows.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedList = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return consolidatedList.value.slice(start, start + perPage.value);
});

watch(consolidatedList, () => {
    currentPage.value = 1;
});
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Overview summary banner -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Trips</span>
                <p class="text-base font-black text-slate-800 mt-1">{{ reportData.totals?.trips_count || 0 }}</p>
            </div>
            <div class="bg-white p-4 rounded border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Batch Size</span>
                <p class="text-base font-black text-indigo-700 mt-1">{{ formatQuantity(reportData.totals?.batch_size) }} m³</p>
            </div>
            <div class="bg-white p-4 rounded border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Delivered Qty</span>
                <p class="text-base font-black text-blue-700 mt-1">{{ formatQuantity(reportData.totals?.quantity) }} m³</p>
            </div>
        </div>

        <!-- Sales Executive Consolidated Table -->
        <div class="bg-white rounded border border-slate-200 shadow-sm">
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Sales Executive Performance Summary</h3>
                    <p class="text-[10px] text-slate-400">Executive wise dispatch volumes, batch sizes, and trip counts</p>
                </div>
                <span class="text-xs font-bold text-[#0064d2]">
                    {{ totalRows }} Executives
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3" width="40%">Sales Executive Name</th>
                            <th class="py-3 px-3 text-center" width="15%">Code</th>
                            <th class="py-3 px-3 text-center" width="12%">Trips</th>
                            <th class="py-3 px-3 text-right" width="14%">Batch Size</th>
                            <th class="py-3 px-3 text-right" width="14%">Delivered Qty</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in paginatedList" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ (sanitizedPage - 1) * perPage + idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">
                                {{ row.sales_executive_name }}
                                <span v-if="row.executive_mobile" class="text-[10px] text-slate-400 block font-normal">{{ row.executive_mobile }}</span>
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-500">{{ row.executive_code || '-' }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                        </tr>
                        <tr v-if="!consolidatedList.length">
                            <td colspan="6" class="py-8 text-center text-slate-400">No sales executive records found for the selected period</td>
                        </tr>
                        <tr v-if="consolidatedList.length" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="3" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Grand Total</td>
                            <td class="py-3 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.totals?.trips_count }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.totals?.batch_size) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.totals?.quantity) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalPages > 1" class="px-4 py-3 bg-[#f8fafc] border-t border-slate-200 flex items-center justify-between">
                <span class="text-xs text-slate-500">
                    Showing {{ (sanitizedPage - 1) * perPage + 1 }} to {{ Math.min(sanitizedPage * perPage, totalRows) }} of {{ totalRows }} records
                </span>
                <div class="flex items-center gap-1">
                    <button
                        @click="currentPage--"
                        :disabled="sanitizedPage <= 1"
                        class="px-2.5 py-1 text-xs font-semibold rounded border bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-slate-700 cursor-pointer"
                    >
                        Prev
                    </button>
                    <span class="text-xs px-2 font-bold text-slate-700">Page {{ sanitizedPage }} of {{ totalPages }}</span>
                    <button
                        @click="currentPage++"
                        :disabled="sanitizedPage >= totalPages"
                        class="px-2.5 py-1 text-xs font-semibold rounded border bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-slate-700 cursor-pointer"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 2: Executive & Customer Breakdown -->
        <div v-if="reportData.executive_customer_summary?.length" class="bg-white rounded border border-slate-200 shadow-sm">
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Executive & Customer Volume Breakdown</h3>
                    <p class="text-[10px] text-slate-400">Customer account wise dispatch volume handled by each sales executive</p>
                </div>
                <span class="text-xs font-bold text-slate-500">
                    {{ reportData.executive_customer_summary.length }} Customer Links
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[650px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-2.5 px-3 text-center" width="5%">#</th>
                            <th class="py-2.5 px-3" width="35%">Sales Executive</th>
                            <th class="py-2.5 px-3" width="35%">Customer / Account</th>
                            <th class="py-2.5 px-3 text-center" width="11%">Trips</th>
                            <th class="py-2.5 px-3 text-right" width="14%">Delivered Qty</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in reportData.executive_customer_summary" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2 px-3 font-bold text-slate-800">{{ row.sales_executive_name }}</td>
                            <td class="py-2 px-3 text-slate-700">{{ row.customer_name }}</td>
                            <td class="py-2 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <td class="py-2 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
