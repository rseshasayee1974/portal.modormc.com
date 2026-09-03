<script setup>
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: Object
});
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Overview summary banner -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white p-3 rounded border border-slate-200 shadow-sm">
                <span class="text-[9px] font-black uppercase text-slate-400">Total Trips</span>
                <p class="text-sm font-black text-slate-800 mt-1">{{ reportData.total_trips || 0 }}</p>
            </div>
            <div class="bg-white p-3 rounded border border-slate-200 shadow-sm">
                <span class="text-[9px] font-black uppercase text-slate-400">Batch Size</span>
                <p class="text-sm font-black text-indigo-700 mt-1">{{ formatQuantity(reportData.total_batch_size) }} m³</p>
            </div>
            <div class="bg-white p-3 rounded border border-slate-200 shadow-sm">
                <span class="text-[9px] font-black uppercase text-slate-400">Delivered Qty</span>
                <p class="text-sm font-black text-blue-700 mt-1">{{ formatQuantity(reportData.total_quantity) }} m³</p>
            </div>
            <div class="bg-white p-3 rounded border border-slate-200 shadow-sm">
                <span class="text-[9px] font-black uppercase text-slate-400">Net Wt</span>
                <p class="text-sm font-black text-emerald-700 mt-1">{{ formatQuantity(reportData.total_net_weight) }} T</p>
            </div>
            <div class="bg-white p-3 rounded border border-slate-200 shadow-sm">
                <span class="text-[9px] font-black uppercase text-slate-400">Total Revenue</span>
                <p class="text-sm font-black text-[#1d2d3e] mt-1">{{ formatCurrency(reportData.total_amount) }}</p>
            </div>
        </div>

        <!-- Product Consolidated Table -->
        <div class="bg-white rounded border border-slate-200 shadow-sm">
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Product Consolidated Report</h3>
                    <p class="text-[10px] text-slate-400">Mix design & concrete grade wise dispatches with batch size, volume, and revenue</p>
                </div>
                <span class="text-xs font-bold text-[#0064d2]">
                    {{ reportData.transactions?.length || 0 }} Items
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[950px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="24%">Mix Design Name</th>
                            <th class="py-3 px-3 text-center" width="12%">Grade</th>
                            <th class="py-3 px-3 text-center" width="6%">UOM</th>
                            <th class="py-3 px-3 text-center" width="6%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="10%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="10%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="9%">Net Wt</th>
                            <!-- <th class="py-3 px-3 text-right" width="9%">Avg Rate</th> -->
                            <th class="py-3 px-3 text-right" width="10%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.mix_name }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ row.concrete_grade }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-500 font-medium">{{ row.uom }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-slate-700">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(row.netweight) }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.transactions?.length">
                            <td colspan="10" class="py-8 text-center text-slate-400">No mix design dispatches found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="4" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Product Summary</td>
                            <td class="py-3 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_trips }}</td>
                            <!-- <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_batch_size) }}</td> -->
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_quantity) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_net_weight) }}</td>
                            <!-- <td></td> -->
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Unload Site based Product Consolidation -->
        <div v-if="reportData.product_site_summary?.length" class="mt-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>Unload Site based Product Consolidated Summary</span>
                <span class="text-slate-400 font-normal normal-case">Mix designs broken down by delivery/unloading sites</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="22%">Mix Design</th>
                            <th class="py-3 px-3 text-center" width="10%">Grade</th>
                            <th class="py-3 px-3" width="20%">Unloading Site</th>
                            <th class="py-3 px-3 text-center" width="6%">UOM</th>
                            <th class="py-3 px-3 text-center" width="6%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="8%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="8%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="8%">Net Wt</th>
                            <th class="py-3 px-3 text-right" width="8%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in reportData.product_site_summary" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.mix_name }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ row.concrete_grade }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.site_name }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-500 font-medium">{{ row.uom }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-slate-700">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(row.netweight) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Payment Mode Consolidation -->
        <div v-if="reportData.payment_mode_summary?.length" class="mt-6 mb-4">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>Payment Mode Consolidated Summary</span>
                <span class="text-slate-400 font-normal normal-case">Product dispatches consolidated by settlement mode</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[750px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3" width="40%">Payment Mode</th>
                            <th class="py-3 px-3 text-center" width="13%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="14%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="14%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="14%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in reportData.payment_mode_summary" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.payment_mode }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
