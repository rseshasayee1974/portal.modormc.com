<script setup>
import { BanknotesIcon } from '@heroicons/vue/24/outline';
import { formatCurrency } from '@/Utils/formatters';

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

const sumTotalTaxesKey = (key) => {
    if (!props.reportData || !props.reportData.data) return 0;
    return props.reportData.data.reduce((sum, row) => sum + (row.taxes?.[key] || 0), 0);
};
</script>

<template>
    <div>
        <!-- KPI Block Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Taxable Sales</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.taxable) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total GST Collected</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.gst) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-[#e2f0d9] border-[#c5e0b4] flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Grand Sales Total</span>
                    <span class="text-lg font-black text-[#385723] mt-1 block">{{ formatCurrency(reportData.totals?.grand_total) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-[#385723]" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse ">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="3%">#</th>
                        <th class="py-3 px-4" width="8%">Invoice No</th>
                        <th class="py-3 px-4 text-center" width="8%">Date</th>
                        <th class="py-3 px-4" width="14%">Customer Name</th>
                        <th class="py-3 px-4 text-center" width="9%">GSTIN</th>
                        <th class="py-3 px-4" width="10%">Product</th>
                        <th class="py-3 px-4 text-right" width="5%">Qty</th>
                        <!-- <th class="py-3 px-4 text-right" width="6%">Rate</th> -->
                        <th class="py-3 px-4 text-right" width="8%">Taxable Amt</th>
                        <!-- <th class="py-3 px-4 text-right" width="6%">CGST</th>
                        <th class="py-3 px-4 text-right" width="6%">SGST</th>
                        <th class="py-3 px-4 text-right" width="6%">IGST</th> -->
                        <!-- Dynamic tax rate columns -->
                        <!-- <th v-for="col in reportData.tax_columns" :key="col.key" class="py-3 px-4 text-right text-slate-650" width="6%">
                            {{ col.label }}
                        </th> -->
                        <th class="py-3 px-4 text-right" width="8%">Net Amt</th>
                        <th class="py-3 px-4 text-center" width="8%">Payment</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">
                            {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                        </td>
                        <td class="py-3 px-4 font-bold text-slate-800">{{ row.invoice_no }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.invoice_date }}</td>
                        <td class="py-3 px-4">{{ row.customer_name }}</td>
                        <td class="py-3 px-4 text-center text-slate-650">{{ row.gst_number || 'N/A' }}</td>
                        <td class="py-3 px-4 text-slate-600">{{ row.product_name }}</td>
                        <td class="py-3 px-4 text-right">{{ row.qty.toFixed(2) }}</td>
                        <!-- <td class="py-3 px-4 text-right">{{ formatCurrency(row.rate) }}</td> -->
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_amount) }}</td>
                        <!-- <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.cgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.sgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.igst) }}</td> -->
                        <!-- Dynamic tax rate columns values -->
                        <!-- <td v-for="col in reportData.tax_columns" :key="col.key" class="py-3 px-4 text-right text-slate-500">
                            {{ formatCurrency(row.taxes?.[col.key] || 0) }}
                        </td> -->
                        <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.net_amount) }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[9px] font-bold" 
                                :class="[
                                    row.payment_status === 'Paid' ? 'bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]' : 
                                    (row.payment_status === 'Partial' ? 'bg-[#fce4d6] text-[#c65911] border border-[#f8cbad]' : 'bg-red-50 text-red-700 border border-red-200')
                                ]"
                            >
                                {{ row.payment_status }}
                            </span>
                        </td>
                    </tr>
                    <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                        <td colspan="6" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Sales</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.totals?.qty.toFixed(2) }}</td>
                        <!-- <td></td> -->
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.taxable) }}</td>
                        <!-- <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.cgst) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.sgst) }}</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.igst) }}</td> -->
                        <!-- Dynamic tax rate columns totals -->
                        <!-- <td v-for="col in reportData.tax_columns" :key="col.key" class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">
                            {{ formatCurrency(sumTotalTaxesKey(col.key)) }}
                        </td> -->
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-100">{{ formatCurrency(reportData.totals?.grand_total) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="reportData.pagination && reportData.pagination.last_page > 1" class="mt-4 flex justify-between items-center bg-slate-50 p-3 rounded border border-slate-200 animate-in fade-in duration-100">
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
