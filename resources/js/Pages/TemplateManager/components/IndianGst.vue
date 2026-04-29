<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps<{
    dummyData: any;
}>();
</script>

<template>
    <div class="design-wrap gst-mode">
        <div class="flex justify-between items-start mb-8 border-b-2 border-slate-200 pb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-900 border-l-8 border-slate-900 pl-4">msrk V4</h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase mt-2 pl-4">GSTIN: 33AAACX0000X1Z • PAN: AAAAA0000A</p>
                <p class="text-[9px] text-slate-500 mt-1 pl-4 max-w-sm uppercase leading-relaxed">Plot No 42, Sector 5, Industrial Estate, Tamil Nadu, India.</p>
            </div>
            <div class="text-right">
                <h2 class="text-4xl font-black text-indigo-600 uppercase tracking-tighter">GST Invoice</h2>
                <div class="mt-4 bg-indigo-50 p-4 rounded-lg inline-block border border-indigo-100">
                    <div class="flex justify-between gap-8">
                        <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest leading-none">Invoice NO</span>
                        <span class="text-xs font-black text-indigo-800 leading-none uppercase">{{ dummyData.doc_no }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="bg-slate-50 p-6 rounded-xl border border-slate-100">
                <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Billing Party</h4>
                <div class="text-sm font-black text-slate-800 uppercase">{{ dummyData.bill_to.name }}</div>
                <div class="text-[10px] text-slate-500 mt-2 leading-relaxed uppercase">
                    {{ dummyData.bill_to.address }}, {{ dummyData.bill_to.city }}<br>
                    State: {{ dummyData.bill_to.state }} (Code: 33)
                </div>
                <div class="mt-4 pt-4 border-t border-slate-200 text-[10px] font-black">GSTIN: 33BBBCX0000X1Z</div>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div v-for="l in ['Place of Supply', 'Reverse Charge', 'Transport Mode', 'Vehicle No']" :key="l" 
                         class="flex justify-between items-center text-[10px] font-bold border-b border-slate-50 pb-2">
                        <span class="text-slate-400 uppercase">{{ l }}</span>
                        <span class="text-slate-800 uppercase italic">{{ l === 'Place of Supply' ? dummyData.bill_to.state : 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <table class="w-full border border-slate-800 gst-table">
            <thead>
                <tr class="bg-slate-800 text-white border-b border-slate-800">
                    <th rowspan="2" class="p-2 border-r border-slate-700 text-[9px] font-black uppercase">HSN</th>
                    <th rowspan="2" class="p-2 border-r border-slate-700 text-left text-[9px] font-black uppercase">Description</th>
                    <th rowspan="2" class="p-2 border-r border-slate-700 text-[9px] font-black uppercase">Qty</th>
                    <th rowspan="2" class="p-2 border-r border-slate-700 text-[9px] font-black uppercase">Rate</th>
                    <th colspan="2" class="p-2 border-b border-slate-700 text-[9px] font-black uppercase text-center">CGST (9%)</th>
                    <th colspan="2" class="p-2 border-b border-slate-700 text-[9px] font-black uppercase text-center">SGST (9%)</th>
                    <th rowspan="2" class="p-2 text-[9px] font-black uppercase">Amount</th>
                </tr>
                <tr class="bg-slate-700 text-white font-mono text-[8px] uppercase">
                    <th class="p-1 border-r border-slate-600">Rate</th>
                    <th class="p-1 border-r border-slate-600">Amt</th>
                    <th class="p-1 border-r border-slate-600">Rate</th>
                    <th class="p-1 border-r border-slate-600 text-center">Amt</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in dummyData.items" :key="item.description" class="border-b border-slate-200">
                    <td class="p-3 border-r border-slate-200 text-center text-[10px] font-bold">382410</td>
                    <td class="p-3 border-r border-slate-200">
                        <div class="text-[10px] font-black uppercase">{{ item.description }}</div>
                    </td>
                    <td class="p-3 border-r border-slate-200 text-center text-[10px] font-black">{{ item.qty }}</td>
                    <td class="p-3 border-r border-slate-200 text-right text-[10px] font-bold">{{ item.rate }}</td>
                    <td class="p-3 border-r border-slate-200 text-center text-[9px] text-slate-400 italic">9%</td>
                    <td class="p-3 border-r border-slate-200 text-right text-[9px] font-bold">{{ (item.amount * 0.09).toFixed(2) }}</td>
                    <td class="p-3 border-r border-slate-200 text-center text-[9px] text-slate-400 italic">9%</td>
                    <td class="p-3 border-r border-slate-200 text-right text-[9px] font-bold">{{ (item.amount * 0.09).toFixed(2) }}</td>
                    <td class="p-3 text-right text-[10px] font-black">₹{{ (item.amount * 1.18).toFixed(2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 font-black">
                    <td colspan="4" class="p-4 text-right border-r border-slate-200 text-[10px] uppercase">Total Taxable Value</td>
                    <td colspan="4" class="p-2 border-r border-slate-200 text-right text-[10px]">₹{{ dummyData.sub_total }}</td>
                    <td class="p-2 text-right text-[11px]">₹{{ (dummyData.sub_total * 1.18).toFixed(2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-12 flex justify-between items-end">
            <div class="max-w-md">
                <div class="bg-slate-100 p-4 rounded-lg">
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Declaration</div>
                    <p class="text-[9px] text-slate-500 leading-relaxed uppercase">We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</p>
                </div>
            </div>
            <div class="text-right">
                <div class="mb-4 h-12 w-48 border-b-2 border-slate-200 ml-auto"></div>
                <div class="text-[10px] font-black uppercase">Authorized Signatory</div>
                <div class="text-[8px] text-slate-400 uppercase mt-1">For MSRK Construction V4</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gst-table { border-collapse: collapse; }
</style>
