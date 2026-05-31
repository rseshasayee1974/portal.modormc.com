<script setup>
import { ref, computed } from 'vue';
import { BanknotesIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

const activeTab = ref('b2b');

const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

const b2bTotal = computed(() => {
    const list = props.reportData.b2b || [];
    return list.reduce((acc, row) => {
        acc.value += row.invoice_value || 0;
        acc.taxable += row.taxable_value || 0;
        acc.cgst += row.cgst || 0;
        acc.sgst += row.sgst || 0;
        acc.igst += row.igst || 0;
        return acc;
    }, { value: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0 });
});

const b2cTotal = computed(() => {
    const list = props.reportData.b2c || [];
    return list.reduce((acc, row) => {
        acc.value += row.invoice_value || 0;
        acc.taxable += row.taxable_value || 0;
        acc.cgst += row.cgst || 0;
        acc.sgst += row.sgst || 0;
        acc.igst += row.igst || 0;
        return acc;
    }, { value: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0 });
});

const cdnrTotal = computed(() => {
    const list = props.reportData.cdnr || [];
    return list.reduce((acc, row) => {
        acc.value += row.note_value || 0;
        acc.taxable += row.taxable_value || 0;
        acc.cgst += row.cgst || 0;
        acc.sgst += row.sgst || 0;
        acc.igst += row.igst || 0;
        return acc;
    }, { value: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0 });
});

const expTotal = computed(() => {
    const list = props.reportData.exp || [];
    return list.reduce((acc, row) => {
        acc.value += row.invoice_value || 0;
        acc.taxable += row.taxable_value || 0;
        acc.igst += row.igst || 0;
        return acc;
    }, { value: 0, taxable: 0, igst: 0 });
});

const totalGst = computed(() => {
    return b2bTotal.value.cgst + b2bTotal.value.sgst + b2bTotal.value.igst +
           b2cTotal.value.cgst + b2cTotal.value.sgst + b2cTotal.value.igst +
           cdnrTotal.value.cgst + cdnrTotal.value.sgst + cdnrTotal.value.igst +
           expTotal.value.igst;
});

const grandTaxable = computed(() => {
    return b2bTotal.value.taxable + b2cTotal.value.taxable + cdnrTotal.value.taxable + expTotal.value.taxable;
});

const grandTotal = computed(() => {
    return b2bTotal.value.value + b2cTotal.value.value + cdnrTotal.value.value + expTotal.value.value;
});
</script>

<template>
    <div>
        <!-- KPI Block Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Taxable Value</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(grandTaxable) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total GST Liability</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(totalGst) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded p-4 bg-[#e2f0d9] border-[#c5e0b4] flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Gross Invoice Value</span>
                    <span class="text-lg font-black text-[#385723] mt-1 block">{{ formatCurrency(grandTotal) }}</span>
                </div>
                <BanknotesIcon class="w-5 h-5 text-[#385723]" />
            </div>
        </div>

        <!-- Section Navigation Tabs -->
        <div class="flex border-b border-slate-200 mb-6 bg-slate-50 rounded p-1">
            <button 
                @click="activeTab = 'b2b'"
                class="flex-1 py-2 px-3 text-xs font-bold transition-all rounded text-center flex items-center justify-center gap-1.5"
                :class="[activeTab === 'b2b' ? 'bg-white shadow-sm border border-slate-200 text-[#0064d2]' : 'text-slate-500 hover:text-slate-800']"
            >
                B2B Supplies
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 rounded text-slate-600 font-black">
                    {{ (reportData.b2b || []).length }}
                </span>
            </button>
            <button 
                @click="activeTab = 'b2c'"
                class="flex-1 py-2 px-3 text-xs font-bold transition-all rounded text-center flex items-center justify-center gap-1.5"
                :class="[activeTab === 'b2c' ? 'bg-white shadow-sm border border-slate-200 text-[#0064d2]' : 'text-slate-500 hover:text-slate-800']"
            >
                B2C Supplies
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 rounded text-slate-600 font-black">
                    {{ (reportData.b2c || []).length }}
                </span>
            </button>
            <button 
                @click="activeTab = 'cdnr'"
                class="flex-1 py-2 px-3 text-xs font-bold transition-all rounded text-center flex items-center justify-center gap-1.5"
                :class="[activeTab === 'cdnr' ? 'bg-white shadow-sm border border-slate-200 text-[#0064d2]' : 'text-slate-500 hover:text-slate-800']"
            >
                Credit / Debit Notes (CDNR)
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 rounded text-slate-600 font-black">
                    {{ (reportData.cdnr || []).length }}
                </span>
            </button>
            <button 
                @click="activeTab = 'exp'"
                class="flex-1 py-2 px-3 text-xs font-bold transition-all rounded text-center flex items-center justify-center gap-1.5"
                :class="[activeTab === 'exp' ? 'bg-white shadow-sm border border-slate-200 text-[#0064d2]' : 'text-slate-500 hover:text-slate-800']"
            >
                Exports (EXP)
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 rounded text-slate-600 font-black">
                    {{ (reportData.exp || []).length }}
                </span>
            </button>
        </div>

        <!-- B2B Section -->
        <div v-if="activeTab === 'b2b'" class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4">Customer GSTIN</th>
                        <th class="py-3 px-4">Customer Name</th>
                        <th class="py-3 px-4">Invoice No</th>
                        <th class="py-3 px-4 text-center">Date</th>
                        <th class="py-3 px-4 text-right">Taxable Value</th>
                        <th class="py-3 px-4 text-right">CGST</th>
                        <th class="py-3 px-4 text-right">SGST</th>
                        <th class="py-3 px-4 text-right">IGST</th>
                        <th class="py-3 px-4 text-right">Invoice Value</th>
                        <th class="py-3 px-4 text-center">POS</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-if="(reportData.b2b || []).length === 0">
                        <td colspan="10" class="py-8 text-center text-slate-400 bg-slate-50/50">
                            No B2B invoices found in this period.
                        </td>
                    </tr>
                    <tr v-for="(row, idx) in reportData.b2b" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 font-bold text-slate-800">{{ row.gstin }}</td>
                        <td class="py-3 px-4">{{ row.customer_name }}</td>
                        <td class="py-3 px-4 font-bold text-slate-900">{{ row.invoice_no }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.invoice_date }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_value) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.cgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.sgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.igst) }}</td>
                        <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/40">{{ formatCurrency(row.invoice_value) }}</td>
                        <td class="py-3 px-4 text-center">{{ row.place_of_supply }}</td>
                    </tr>
                    <tr v-if="(reportData.b2b || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                        <td colspan="4" class="py-3.5 px-4 uppercase text-center font-bold">Total B2B</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2bTotal.taxable) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2bTotal.cgst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2bTotal.sgst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2bTotal.igst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black bg-slate-100">{{ formatCurrency(b2bTotal.value) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- B2C Section -->
        <div v-if="activeTab === 'b2c'" class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4">Invoice No</th>
                        <th class="py-3 px-4 text-center">Date</th>
                        <th class="py-3 px-4 text-right">Taxable Value</th>
                        <th class="py-3 px-4 text-right">CGST</th>
                        <th class="py-3 px-4 text-right">SGST</th>
                        <th class="py-3 px-4 text-right">IGST</th>
                        <th class="py-3 px-4 text-right">Invoice Value</th>
                        <th class="py-3 px-4 text-center">POS</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-if="(reportData.b2c || []).length === 0">
                        <td colspan="8" class="py-8 text-center text-slate-400 bg-slate-50/50">
                            No B2C transactions found in this period.
                        </td>
                    </tr>
                    <tr v-for="(row, idx) in reportData.b2c" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 font-bold text-slate-900">{{ row.invoice_no }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.invoice_date }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_value) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.cgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.sgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.igst) }}</td>
                        <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/40">{{ formatCurrency(row.invoice_value) }}</td>
                        <td class="py-3 px-4 text-center">{{ row.place_of_supply }}</td>
                    </tr>
                    <tr v-if="(reportData.b2c || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                        <td colspan="2" class="py-3.5 px-4 uppercase text-center font-bold">Total B2C</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2cTotal.taxable) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2cTotal.cgst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2cTotal.sgst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(b2cTotal.igst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black bg-slate-100">{{ formatCurrency(b2cTotal.value) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- CDNR Section -->
        <div v-if="activeTab === 'cdnr'" class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4">Customer GSTIN</th>
                        <th class="py-3 px-4">Customer Name</th>
                        <th class="py-3 px-4">Note No</th>
                        <th class="py-3 px-4 text-center">Note Date</th>
                        <th class="py-3 px-4 text-center">Type</th>
                        <th class="py-3 px-4">Orig Invoice No</th>
                        <th class="py-3 px-4 text-right">Taxable Value</th>
                        <th class="py-3 px-4 text-right">CGST</th>
                        <th class="py-3 px-4 text-right">SGST</th>
                        <th class="py-3 px-4 text-right">IGST</th>
                        <th class="py-3 px-4 text-right">Note Value</th>
                        <th class="py-3 px-4 text-center">POS</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-if="(reportData.cdnr || []).length === 0">
                        <td colspan="12" class="py-8 text-center text-slate-400 bg-slate-50/50">
                            No credit/debit notes found in this period.
                        </td>
                    </tr>
                    <tr v-for="(row, idx) in reportData.cdnr" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 font-bold text-slate-800">{{ row.gstin }}</td>
                        <td class="py-3 px-4">{{ row.customer_name }}</td>
                        <td class="py-3 px-4 font-bold text-slate-900">{{ row.note_no }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.note_date }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold" :class="[row.note_type === 'C' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200']">
                                {{ row.note_type === 'C' ? 'Credit' : 'Debit' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-500">{{ row.original_inv_no }}</td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_value) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.cgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.sgst) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.igst) }}</td>
                        <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/40">{{ formatCurrency(row.note_value) }}</td>
                        <td class="py-3 px-4 text-center">{{ row.place_of_supply }}</td>
                    </tr>
                    <tr v-if="(reportData.cdnr || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                        <td colspan="6" class="py-3.5 px-4 uppercase text-center font-bold">Total CDNR</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(cdnrTotal.taxable) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(cdnrTotal.cgst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(cdnrTotal.sgst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(cdnrTotal.igst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black bg-slate-100">{{ formatCurrency(cdnrTotal.value) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- EXP Section -->
        <div v-if="activeTab === 'exp'" class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4">Invoice No</th>
                        <th class="py-3 px-4 text-center">Date</th>
                        <th class="py-3 px-4">Export Type</th>
                        <th class="py-3 px-4 text-right">Taxable Value</th>
                        <th class="py-3 px-4 text-right">IGST Paid</th>
                        <th class="py-3 px-4 text-right">Invoice Value</th>
                        <th class="py-3 px-4 text-center">POS</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-if="(reportData.exp || []).length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-400 bg-slate-50/50">
                            No export transactions found in this period.
                        </td>
                    </tr>
                    <tr v-for="(row, idx) in reportData.exp" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 font-bold text-slate-900">{{ row.invoice_no }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.invoice_date }}</td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#f2f7fc] text-[#0064d2] border border-[#d2e4f9]">
                                {{ row.export_type === 'WPAY' ? 'With Payment of Tax' : 'Without Payment of Tax' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_value) }}</td>
                        <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.igst) }}</td>
                        <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/40">{{ formatCurrency(row.invoice_value) }}</td>
                        <td class="py-3 px-4 text-center">{{ row.place_of_supply }}</td>
                    </tr>
                    <tr v-if="(reportData.exp || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                        <td colspan="3" class="py-3.5 px-4 uppercase text-center font-bold">Total EXP</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(expTotal.taxable) }}</td>
                        <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(expTotal.igst) }}</td>
                        <td class="py-3.5 px-4 text-right font-black bg-slate-100">{{ formatCurrency(expTotal.value) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
