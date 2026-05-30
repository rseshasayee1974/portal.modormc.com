<script setup>
import { ref } from 'vue';
import { CubeIcon, BanknotesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    },
    valuationMethod: {
        type: String,
        default: 'FIFO'
    }
});

const expandedProductId = ref(null);
const toggleProductExpand = (id) => {
    expandedProductId.value = expandedProductId.value === id ? null : id;
};

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
        <!-- Silo Stock Valuation KPI Block -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Opening Valuation</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_opening_value_formatted }}</span>
                </div>
                <CubeIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total COGS Consumed</span>
                    <span class="text-lg font-black text-red-650 mt-1 block">{{ reportData.total_consumed_value_formatted }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-red-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-[#e2f0d9] border-[#c5e0b4] flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Closing Stock Valuation</span>
                    <span class="text-lg font-black text-[#385723] mt-1 block">{{ reportData.total_ending_value_formatted }}</span>
                </div>
                <CubeIcon class="w-5 h-5 text-[#385723]" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[1200px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="3%">#</th>
                        <th class="py-3 px-4" width="15%">Product Name</th>
                        <th class="py-3 px-4" width="10%">Category</th>
                        <th class="py-3 px-4 text-center" width="5%">UOM</th>
                        <th class="py-3 px-4 text-right" width="8%">Opening Qty</th>
                        <th class="py-3 px-4 text-right" width="8%">Opening Value</th>
                        <th class="py-3 px-4 text-right" width="8%">Inward Qty</th>
                        <th class="py-3 px-4 text-right" width="8%">Inward Value</th>
                        <th class="py-3 px-4 text-right" width="8%">Consumed Qty</th>
                        <th class="py-3 px-4 text-right" width="8%">COGS Value</th>
                        <th class="py-3 px-4 text-right" width="8%">Ending Qty</th>
                        <th class="py-3 px-4 text-right" width="8%">Ending Value</th>
                        <th class="py-3 px-4 text-right" width="8%">Unit Cost</th>
                        <th class="py-3 px-4 text-center" width="5%">Trace</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <template v-for="(row, idx) in reportData.products" :key="row.product_id">
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                            <td class="py-3 px-4 text-slate-600">{{ row.category }}</td>
                            <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                            <td class="py-3 px-4 text-right">{{ row.opening_qty.toLocaleString() }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ row.opening_value_formatted }}</td>
                            <td class="py-3 px-4 text-right">{{ row.inward_qty.toLocaleString() }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ row.inward_value_formatted }}</td>
                            <td class="py-3 px-4 text-right text-red-650">{{ row.consumed_qty.toLocaleString() }}</td>
                            <td class="py-3 px-4 text-right text-red-650">{{ row.consumed_value_formatted }}</td>
                            <td class="py-3 px-4 text-right font-black text-slate-800">{{ row.ending_qty.toLocaleString() }}</td>
                            <td class="py-3 px-4 text-right font-black text-[#0064d2]">{{ row.ending_value_formatted }}</td>
                            <td class="py-3 px-4 text-right font-bold text-slate-600">{{ row.avg_unit_cost_formatted }}</td>
                            <td class="py-3 px-4 text-center">
                                <button 
                                    @click="toggleProductExpand(row.product_id)"
                                    class="px-2 py-1 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded text-[9px] font-bold text-slate-600 transition-all uppercase"
                                >
                                    {{ expandedProductId === row.product_id ? 'Hide' : 'Trace' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expandedProductId === row.product_id" class="bg-slate-50/70">
                            <td colspan="14" class="p-4 border-b border-slate-200">
                                <div class="bg-white rounded border border-slate-200 p-3 shadow-inner">
                                    <h5 class="text-[10px] font-black uppercase text-slate-400 tracking-wider mb-2.5">
                                        Chronological Audit Trail Ledger for {{ row.product_name }} ({{ valuationMethod }})
                                    </h5>
                                    <div v-if="row.detailed_events && row.detailed_events.length > 0" class="overflow-x-auto">
                                        <table class="w-full text-left border-collapse text-[10px]">
                                            <thead>
                                                <tr class="font-bold text-slate-500 border-b border-slate-100 uppercase bg-slate-50/50">
                                                    <th class="py-2 px-3">Date</th>
                                                    <th class="py-2 px-3">Doc No</th>
                                                    <th class="py-2 px-3">Transaction</th>
                                                    <th class="py-2 px-3">Reference / Source</th>
                                                    <th class="py-2 px-3 text-right">Inward Qty</th>
                                                    <th class="py-2 px-3 text-right">Consumed Qty</th>
                                                    <th class="py-2 px-3 text-right">Unit Rate</th>
                                                    <th class="py-2 px-3 text-right">Total Value</th>
                                                    <th class="py-2 px-3 text-right">Running Stock Qty</th>
                                                    <th class="py-2 px-3 text-right">Running Stock Value</th>
                                                </tr>
                                            </thead>
                                            <tbody class="font-medium text-slate-600">
                                                <tr v-for="(evt, eIdx) in row.detailed_events" :key="eIdx" class="border-b border-slate-100 hover:bg-slate-50">
                                                    <td class="py-2 px-3 text-slate-400">{{ evt.date }}</td>
                                                    <td class="py-2 px-3 font-mono font-bold">{{ evt.doc_no }}</td>
                                                    <td class="py-2 px-3">
                                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider"
                                                            :class="evt.type === 'inward' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100'"
                                                        >
                                                            {{ evt.type }}
                                                        </span>
                                                    </td>
                                                    <td class="py-2 px-3 italic">{{ evt.ref }}</td>
                                                    <td class="py-2 px-3 text-right font-semibold text-slate-800">{{ evt.type === 'inward' ? evt.qty.toLocaleString() : '-' }}</td>
                                                    <td class="py-2 px-3 text-right font-semibold text-red-600">{{ evt.type === 'consumption' ? evt.qty.toLocaleString() : '-' }}</td>
                                                    <td class="py-2 px-3 text-right">{{ formatCurrency(evt.price) }}</td>
                                                    <td class="py-2 px-3 text-right font-semibold" :class="evt.type === 'consumption' ? 'text-red-650' : 'text-emerald-750'">{{ formatCurrency(evt.value) }}</td>
                                                    <td class="py-2 px-3 text-right font-bold text-slate-800 bg-slate-50/20">{{ evt.running_qty.toLocaleString() }}</td>
                                                    <td class="py-2 px-3 text-right font-bold text-slate-800 bg-slate-50/20">{{ formatCurrency(evt.running_val) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-else class="text-center py-4 text-slate-400 text-xs italic">
                                        No transactions to trace in this date range.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
