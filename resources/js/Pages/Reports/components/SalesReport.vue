<script setup>
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});
console.log('reportdata',props.reportData);

</script>

<template>
    <div>
        <!-- Sales Invoice wise Breakdown -->
        <div class="mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                Sales Invoice wise Breakdown
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" width="5%">#</th>
                            <th class="py-3 px-4 text-center" width="15%">Date</th>
                            <th class="py-3 px-4 text-center" width="20%">Invoice Number</th>
                            <th class="py-3 px-4" width="24%">Customer / Party</th>
                            <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.invoice_number }}</td>
                            <td class="py-3 px-4 font-medium text-slate-800">{{ row.customer_name }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.transactions?.length">
                            <td colspan="7" class="py-4 text-center text-slate-400">No invoices found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Sales</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_untaxed) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_tax) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sales Product wise Consolidated Summary -->
        <div class="mt-6 mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                Sales Product wise Consolidated Summary
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" width="5%">#</th>
                            <th class="py-3 px-4" width="35%">Product Name</th>
                            <th class="py-3 px-4 text-center" width="10%">UOM</th>
                            <th class="py-3 px-4 text-right" width="10%">Quantity</th>
                            <th class="py-3 px-4 text-right" width="12%">Avg Rate</th>
                            <th class="py-3 px-4 text-right" width="13%">Taxable Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                            <th class="py-3 px-4 text-right" width="13%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.product_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                            <td class="py-3 px-4 text-center text-slate-500 font-medium">{{ row.uom }}</td>
                            <td class="py-3 px-4 text-right text-slate-800 font-bold">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.product_summary?.length">
                            <td colspan="8" class="py-4 text-center text-slate-400">No product items found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total summary</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_quantity) }}</td>
                            <td></td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_untaxed) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_tax) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Concrete Grade / Mix design wise Dispatch -->
        <div class="mt-6 mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                Concrete Grade / Mix design wise Dispatch
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" width="5%">#</th>
                            <th class="py-3 px-4" width="28%">Mix Design Name</th>
                            <th class="py-3 px-4 text-center" width="14%">Concrete Grade</th>
                            <th class="py-3 px-4 text-center" width="8%">UOM</th>
                            <th class="py-3 px-4 text-right" width="10%">Quantity</th>
                            <th class="py-3 px-4 text-right" width="11%">Avg Rate</th>
                            <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                            <th class="py-3 px-4 text-right" width="11%">Tax Amt</th>
                            <th class="py-3 px-4 text-right" width="11%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.mix_design_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">{{ row.mix_name }}</td>
                            <td class="py-3 px-4 text-center font-bold text-indigo-700">{{ row.concrete_grade }}</td>
                            <td class="py-3 px-4 text-center text-slate-500 font-medium">{{ row.uom }}</td>
                            <td class="py-3 px-4 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.mix_design_summary?.length">
                            <td colspan="9" class="py-4 text-center text-slate-400">No dispatch items found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total grade dispatches</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_dispatch_quantity) }}</td>
                            <td></td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_dispatch_untaxed) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_dispatch_tax) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_dispatch_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Party wise Dispatch Summary -->
        <div class="mt-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                Party wise Dispatch Summary
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" width="5%">#</th>
                            <th class="py-3 px-4" width="35%">Customer / Party Name</th>
                            <th class="py-3 px-4 text-right" width="15%">Delivered Qty</th>
                            <th class="py-3 px-4 text-right" width="15%">Taxable Amt</th>
                            <th class="py-3 px-4 text-right" width="15%">Tax Amt</th>
                            <th class="py-3 px-4 text-right" width="15%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.party_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">{{ row.party_name }}</td>
                            <td class="py-3 px-4 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.party_summary?.length">
                            <td colspan="6" class="py-4 text-center text-slate-400">No party dispatches found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="2" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total party volume</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_party_quantity) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_untaxed) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_tax) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
