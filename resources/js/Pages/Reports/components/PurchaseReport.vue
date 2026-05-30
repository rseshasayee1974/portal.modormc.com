<script setup>
const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

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
        <!-- Purchase Order Summary Breakdown -->
        <div class="mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                Purchase Order Summary Breakdown
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" width="5%">#</th>
                            <th class="py-3 px-4 text-center" width="15%">Date</th>
                            <th class="py-3 px-4 text-center" width="20%">PO Number</th>
                            <th class="py-3 px-4" width="24%">Supplier / Vendor</th>
                            <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.po_number }}</td>
                            <td class="py-3 px-4">{{ row.vendor_name }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Purchase</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_untaxed) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_tax) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Material wise Summary -->
        <div class="mt-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                Material wise Summary
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" width="5%">#</th>
                            <th class="py-3 px-4" width="40%">Product Name</th>
                            <th class="py-3 px-4 text-center" width="10%">UOM</th>
                            <th class="py-3 px-4 text-right" width="10%">Quantity</th>
                            <th class="py-3 px-4 text-right" width="11%">Avg Rate</th>
                            <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                            <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.product_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                            <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                            <td class="py-3 px-4 text-right text-slate-600 font-bold">{{ row.quantity }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Goods breakdown</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                            <td></td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_untaxed) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_tax) }}</td>
                            <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
