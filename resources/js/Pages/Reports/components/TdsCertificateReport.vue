<script setup>
import { computed } from 'vue';
import { BanknotesIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

const totals = computed(() => {
    const list = props.reportData.transactions || [];
    return list.reduce((acc, row) => {
        acc.taxable += row.taxable_amount || 0;
        acc.tds += row.tds_amount || 0;
        return acc;
    }, { taxable: 0, tds: 0 });
});
</script>

<template>
    <div>
        <!-- Handle empty/unselected patron state -->
        <div v-if="!reportData.deductee" class="text-center py-10 bg-slate-50 border border-dashed border-slate-300 rounded text-slate-400 text-xs font-bold uppercase tracking-wider">
            Please select a Partner / Patron in the smart filters to generate the TDS Certificate log.
        </div>

        <div v-else>
            <!-- Profiles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Deductor Profile (Active Plant) -->
                <div class="border border-slate-200 rounded p-5 bg-slate-50/40">
                    <h4 class="text-[10px] font-black uppercase text-[#0064d2] tracking-wider mb-3">Tax Deductor (Plant Profile)</h4>
                    <div class="space-y-2 text-xs text-slate-700">
                        <div><span class="font-bold text-slate-400 w-24 inline-block">Legal Name:</span> <span class="font-bold text-[#1d2d3e]">{{ reportData.deductor?.name }}</span></div>
                        <div><span class="font-bold text-slate-400 w-24 inline-block">GSTIN:</span> <span class="font-bold tracking-wider">{{ reportData.deductor?.gstin }}</span></div>
                        <div><span class="font-bold text-slate-400 w-24 inline-block">PAN:</span> <span class="font-bold tracking-wider">{{ reportData.deductor?.pan }}</span></div>
                        <div><span class="font-bold text-slate-400 w-24 inline-block">Address:</span> <span class="text-slate-600 font-medium">{{ reportData.deductor?.address }}</span></div>
                    </div>
                </div>
                <!-- Deductee Profile (Selected Partner) -->
                <div class="border border-slate-200 rounded p-5 bg-slate-50/40">
                    <h4 class="text-[10px] font-black uppercase text-[#0064d2] tracking-wider mb-3">Tax Deductee (Partner Profile)</h4>
                    <div class="space-y-2 text-xs text-slate-700">
                        <div><span class="font-bold text-slate-400 w-24 inline-block">Legal Name:</span> <span class="font-bold text-[#1d2d3e]">{{ reportData.deductee?.name }}</span></div>
                        <div><span class="font-bold text-slate-400 w-24 inline-block">GSTIN:</span> <span class="font-bold tracking-wider">{{ reportData.deductee?.gstin || 'N/A' }}</span></div>
                        <div><span class="font-bold text-slate-400 w-24 inline-block">PAN:</span> <span class="font-bold tracking-wider">{{ reportData.deductee?.pan || 'N/A' }}</span></div>
                        <div><span class="font-bold text-slate-400 w-24 inline-block">Address:</span> <span class="text-slate-600 font-medium">{{ reportData.deductee?.address || 'N/A' }}</span></div>
                    </div>
                </div>
            </div>

            <!-- KPI Block Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Taxable Value Subject to TDS</span>
                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(totals.taxable) }}</span>
                    </div>
                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-[#e2f0d9] border-[#c5e0b4] flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Total TDS Deducted</span>
                        <span class="text-lg font-black text-[#385723] mt-1 block">{{ formatCurrency(totals.tds) }}</span>
                    </div>
                    <ShieldCheckIcon class="w-5 h-5 text-[#385723]" />
                </div>
            </div>

            <!-- Table Grid -->
            <div class="overflow-x-auto border border-slate-200 rounded">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Document Number</th>
                            <th class="py-3 px-4 text-center">Document Type</th>
                            <th class="py-3 px-4 text-right">Taxable Amount</th>
                            <th class="py-3 px-4 text-center">TDS Section</th>
                            <th class="py-3 px-4 text-right">TDS Rate</th>
                            <th class="py-3 px-4 text-right">TDS Amount</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-if="(reportData.transactions || []).length === 0">
                            <td colspan="7" class="py-8 text-center text-slate-400 bg-slate-50/50">
                                No TDS transactions found for this partner in the selected date range.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-slate-500 whitespace-nowrap">{{ row.date }}</td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ row.doc_no }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="row.doc_type === 'Sales Invoice' ? 'bg-[#f2f7fc] text-[#0064d2] border border-[#d2e4f9]' : 'bg-[#fff9e6] text-[#b38600] border border-[#ffe699]'">
                                    {{ row.doc_type }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_amount) }}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.tds_section }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ row.tds_rate.toFixed(2) }}%</td>
                            <td class="py-3 px-4 text-right font-black text-[#385723] bg-slate-50/40">{{ formatCurrency(row.tds_amount) }}</td>
                        </tr>
                        <tr v-if="(reportData.transactions || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                            <td colspan="3" class="py-3.5 px-4 uppercase text-center font-bold">Total</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(totals.taxable) }}</td>
                            <td colspan="2"></td>
                            <td class="py-3.5 px-4 text-right font-black bg-slate-100 text-[#385723]">{{ formatCurrency(totals.tds) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
