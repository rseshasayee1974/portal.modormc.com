<script setup>
import { TruckIcon, BanknotesIcon } from '@heroicons/vue/24/outline';

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
        <!-- Machine Summary KPI Block -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Trips</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.totals?.trips_count }}</span>
                </div>
                <TruckIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Revenue</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.total_revenue) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total General Expenses</span>
                    <span class="text-lg font-black text-red-650 mt-1 block">{{ formatCurrency(reportData.totals?.general_expenses) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-red-400" />
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
                        <th class="py-3 px-4 text-center" width="10%">Type</th>
                        <th class="py-3 px-4 text-center" width="8%">Make Year</th>
                        <th class="py-3 px-4 text-right" width="8%">Capacity</th>
                        <th class="py-3 px-4" width="12%">Owner</th>
                        <th class="py-3 px-4 text-center" width="6%">Trips</th>
                        <th class="py-3 px-4 text-right" width="8%">Qty</th>
                        <th class="py-3 px-4 text-right" width="10%">Revenue</th>
                        <th class="py-3 px-4 text-right" width="10%">Expenses</th>
                        <th class="py-3 px-4" width="15%">Alerts</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">
                            {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.registration }}</td>
                        <td class="py-3 px-4 text-slate-800">{{ row.vehicle_model }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                {{ row.vehicle_type }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.make_year }}</td>
                        <td class="py-3 px-4 text-right text-slate-700">{{ row.capacity }}</td>
                        <td class="py-3 px-4 text-slate-650">{{ row.owner }}</td>
                        <td class="py-3 px-4 text-center">{{ row.trips_count }}</td>
                        <td class="py-3 px-4 text-right">
                            {{ row.total_qty.toFixed(2) }}
                            <div class="text-[9px] text-slate-400 font-normal">{{ row.total_weight_tons.toFixed(2) }} Tons</div>
                        </td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.total_revenue) }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.general_expenses) }}</td>
                        <td class="py-3 px-4">
                            <div v-for="(alert, aIdx) in row.alerts" :key="aIdx" 
                                class="text-[9px] font-bold leading-tight"
                                :class="alert.status === 'expired' ? 'text-red-600' : 'text-amber-600'"
                            >
                                ⚠️ {{ alert.message }}
                            </div>
                            <span v-if="!row.alerts || row.alerts.length === 0" class="text-green-600 text-[9px] font-bold">✓ Active</span>
                        </td>
                    </tr>
                    <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                        <td colspan="7" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Fleet Summary</td>
                        <td class="py-3.5 px-4 text-center text-[#1d2d3e] font-black">{{ reportData.totals?.trips_count }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">
                            {{ reportData.totals?.total_qty.toFixed(2) }}
                            <div class="text-[9px] text-slate-500 font-semibold">{{ reportData.totals?.total_weight_tons.toFixed(2) }} Tons</div>
                        </td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-50/50">{{ formatCurrency(reportData.totals?.total_revenue) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-50/50">{{ formatCurrency(reportData.totals?.general_expenses) }}</td>
                        <td></td>
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
