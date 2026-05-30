<script setup>
import { BanknotesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    },
    currentPage: {
        type: Number,
        default: 1
    }
});

const emit = defineEmits(['page-change']);

const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};
</script>

<template>
    <div>
        <!-- Vehicle PL KPI Block -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Fleet Revenue</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.trip_revenue) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Fleet Total Expenses</span>
                    <span class="text-lg font-black text-slate-700 mt-1 block">{{ formatCurrency(reportData.totals?.total_cost) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 flex justify-between items-center"
                 :class="(reportData.totals?.net_profit || 0) >= 0 ? 'bg-[#e2f0d9] border-[#c5e0b4]' : 'bg-red-50 border-red-200'"
            >
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider block"
                          :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-[#385723]' : 'text-red-700'"
                    >
                        Net Profit / Loss
                    </span>
                    <span class="text-lg font-black mt-1 block"
                          :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-[#385723]' : 'text-red-700'"
                    >
                        {{ formatCurrency(reportData.totals?.net_profit) }}
                    </span>
                </div>
                <BanknotesIcon class="w-5 h-5" :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-[#385723]' : 'text-red-500'" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="3%">#</th>
                        <th class="py-3 px-4 text-center" width="10%">Registration</th>
                        <th class="py-3 px-4" width="12%">Model</th>
                        <th class="py-3 px-4 text-right" width="11%">Trip Revenue</th>
                        <th class="py-3 px-4 text-right" width="11%">Trip Cost</th>
                        <th class="py-3 px-4 text-right" width="11%">Fuel Expense</th>
                        <th class="py-3 px-4 text-right" width="11%">Maintenance</th>
                        <th class="py-3 px-4 text-right" width="11%">Other Expense</th>
                        <th class="py-3 px-4 text-right" width="11%">Total Cost</th>
                        <th class="py-3 px-4 text-right" width="11%">Net Profit</th>
                        <th class="py-3 px-4 text-right" width="8%">Margin %</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">
                            {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.registration }}</td>
                        <td class="py-3 px-4 text-slate-800">{{ row.vehicle_model }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.trip_revenue) }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.trip_cost) }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.fuel_expenses) }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.maintenance_expenses) }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.other_expenses) }}</td>
                        <td class="py-3 px-4 text-right font-bold text-slate-800 bg-slate-50/20">{{ formatCurrency(row.total_cost) }}</td>
                        <td class="py-3 px-4 text-right font-bold" 
                            :class="row.net_profit >= 0 ? 'text-green-600' : 'text-red-600'"
                        >
                            {{ formatCurrency(row.net_profit) }}
                        </td>
                        <td class="py-3 px-4 text-right font-bold" 
                            :class="row.margin_pct >= 0 ? 'text-green-600 font-extrabold' : 'text-red-600 font-extrabold'"
                        >
                            {{ row.margin_pct.toFixed(2) }}%
                        </td>
                    </tr>
                    <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                        <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total P&L</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.trip_revenue) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.trip_cost) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.fuel_expenses) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.maintenance_expenses) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.other_expenses) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-100">{{ formatCurrency(reportData.totals?.total_cost) }}</td>
                        <td class="py-3.5 px-4 text-right font-black" 
                            :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-green-600 bg-slate-100' : 'text-red-600 bg-slate-100'"
                        >
                            {{ formatCurrency(reportData.totals?.net_profit) }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-black" 
                            :class="(reportData.totals?.margin_pct || 0) >= 0 ? 'text-green-600 bg-slate-100' : 'text-red-600 bg-slate-100'"
                        >
                            {{ (reportData.totals?.margin_pct || 0).toFixed(2) }}%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="reportData.pagination && reportData.pagination.last_page > 1" class="mt-4 flex justify-between items-center bg-slate-50 p-3 rounded border border-slate-200">
            <span class="text-xs text-slate-500 font-semibold">
                Showing page {{ reportData.pagination.current_page }} of {{ reportData.pagination.last_page }} (Total {{ reportData.pagination.total }} entries)
            </span>
            <div class="flex gap-2">
                <button 
                    @click="emit('page-change', currentPage - 1)"
                    :disabled="currentPage <= 1"
                    class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                >
                    Previous
                </button>
                <button 
                    @click="emit('page-change', currentPage + 1)"
                    :disabled="currentPage >= reportData.pagination.last_page"
                    class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
