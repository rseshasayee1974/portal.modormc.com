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

// Calculate GSTR-3B Totals
const table31Total = computed(() => {
    const t = props.reportData.table31 || {};
    return Object.values(t).reduce((acc, row) => {
        acc.taxable += row.taxable || 0;
        acc.igst += row.igst || 0;
        acc.cgst += row.cgst || 0;
        acc.sgst += row.sgst || 0;
        return acc;
    }, { taxable: 0, igst: 0, cgst: 0, sgst: 0 });
});

const table4Total = computed(() => {
    const t = props.reportData.table4 || {};
    return Object.values(t).reduce((acc, row) => {
        acc.igst += row.igst || 0;
        acc.cgst += row.cgst || 0;
        acc.sgst += row.sgst || 0;
        return acc;
    }, { igst: 0, cgst: 0, sgst: 0 });
});

const netTaxLiability = computed(() => {
    const outputGst = table31Total.value.igst + table31Total.value.cgst + table31Total.value.sgst;
    const inputCredit = table4Total.value.igst + table4Total.value.cgst + table4Total.value.sgst;
    return outputGst - inputCredit;
});
</script>

<template>
    <div>
        <!-- KPI Block Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Taxable Outward</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(table31Total.taxable) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Eligible ITC (Table 4)</span>
                    <span class="text-lg font-black text-emerald-600 mt-1 block">{{ formatCurrency(table4Total.igst + table4Total.cgst + table4Total.sgst) }}</span>
                </div>
                <ShieldCheckIcon class="w-5 h-5 text-emerald-500" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-[#f2f7fc] border-[#d2e4f9] flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-[#0064d2] uppercase tracking-wider block">Net Payable Tax (Output - ITC)</span>
                    <span class="text-lg font-black text-[#0064d2] mt-1 block">{{ formatCurrency(netTaxLiability) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-[#0064d2]" />
            </div>
        </div>

        <!-- Table 3.1 -->
        <div class="mb-8">
            <div class="bg-slate-50 px-4 py-3 border border-slate-200 border-b-0 rounded-t flex justify-between items-center">
                <h4 class="text-xs font-black uppercase text-[#1d2d3e] tracking-wider">
                    Table 3.1: Details of Outward Supplies & Inward Supplies Liable to Reverse Charge
                </h4>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4" width="40%">Nature of Supplies</th>
                            <th class="py-3 px-4 text-right" width="15%">Total Taxable Value</th>
                            <th class="py-3 px-4 text-right" width="15%">Integrated Tax (IGST)</th>
                            <th class="py-3 px-4 text-right" width="15%">Central Tax (CGST)</th>
                            <th class="py-3 px-4 text-right" width="15%">State/UT Tax (SGST)</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 font-bold text-slate-800">(a) Outward Taxable Supplies (other than zero rated, nil rated, exempted)</td>
                            <td class="py-3 px-4 text-right">{{ formatCurrency(reportData.table31?.a?.taxable) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.a?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.a?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.a?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 font-bold text-slate-800">(b) Outward Taxable Supplies (zero rated / exports)</td>
                            <td class="py-3 px-4 text-right">{{ formatCurrency(reportData.table31?.b?.taxable) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.b?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.b?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.b?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 font-bold text-slate-800">(c) Other Outward Supplies (nil rated, exempted)</td>
                            <td class="py-3 px-4 text-right">{{ formatCurrency(reportData.table31?.c?.taxable) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.c?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.c?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.c?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 font-bold text-slate-800">(d) Inward Supplies (liable to reverse charge)</td>
                            <td class="py-3 px-4 text-right">{{ formatCurrency(reportData.table31?.d?.taxable) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.d?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.d?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.d?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 font-bold text-slate-800">(e) Non-GST Outward Supplies</td>
                            <td class="py-3 px-4 text-right">{{ formatCurrency(reportData.table31?.e?.taxable) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.e?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.e?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table31?.e?.sgst) }}</td>
                        </tr>
                        <tr class="bg-slate-100 font-bold text-xs text-slate-800">
                            <td class="py-3.5 px-4 uppercase font-bold text-center">Total Table 3.1 Supplies</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(table31Total.taxable) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(table31Total.igst) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(table31Total.cgst) }}</td>
                            <td class="py-3.5 px-4 text-right font-black bg-slate-150">{{ formatCurrency(table31Total.sgst) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table 4 -->
        <div>
            <div class="bg-slate-50 px-4 py-3 border border-slate-200 border-b-0 rounded-t flex justify-between items-center">
                <h4 class="text-xs font-black uppercase text-[#1d2d3e] tracking-wider">
                    Table 4: Eligible Input Tax Credit (ITC)
                </h4>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4" width="40%">Details</th>
                            <th class="py-3 px-4 text-right" width="20%">Integrated Tax (IGST)</th>
                            <th class="py-3 px-4 text-right" width="20%">Central Tax (CGST)</th>
                            <th class="py-3 px-4 text-right" width="20%">State/UT Tax (SGST)</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr class="bg-slate-50/60 font-bold border-b border-slate-200">
                            <td colspan="4" class="py-2.5 px-4 text-slate-500 uppercase tracking-wide">(A) ITC Available (whether in full or part):</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 pl-8 font-bold text-slate-800">(1) Import of goods</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.import_goods?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.import_goods?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.import_goods?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 pl-8 font-bold text-slate-800">(2) Import of services</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.import_services?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.import_services?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.import_services?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 pl-8 font-bold text-slate-800">(3) Inward supplies liable to reverse charge (other than 1 & 2 above)</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.reverse_charge?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.reverse_charge?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.reverse_charge?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 pl-8 font-bold text-slate-800">(4) Inward supplies from Input Service Distributor (ISD)</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.isd_itc?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.isd_itc?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.isd_itc?.sgst) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 pl-8 font-bold text-slate-800">(5) All other ITC</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.other_itc?.igst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.other_itc?.cgst) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(reportData.table4?.other_itc?.sgst) }}</td>
                        </tr>
                        <tr class="bg-slate-100 font-bold text-xs text-slate-800">
                            <td class="py-3.5 px-4 uppercase font-bold text-center">Total Table 4 Eligible ITC</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(table4Total.igst) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(table4Total.cgst) }}</td>
                            <td class="py-3.5 px-4 text-right font-black bg-slate-150">{{ formatCurrency(table4Total.sgst) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
