<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps<{
    dummyData: any;
}>();
</script>

<template>
    <div class="design-wrap spreadsheet-mode">
        <div class="border-4 border-slate-900 p-8 mb-8">
            <div class="flex justify-between items-center">
                <h1 class="text-4xl font-black uppercase tracking-tighter">Inventory Disbursement</h1>
                <div class="bg-slate-900 text-white px-6 py-2 text-sm font-black uppercase">Ref: {{ dummyData.doc_no }}</div>
            </div>
        </div>

        <div class="grid grid-cols-4 border border-slate-200 mb-8">
            <div v-for="(val, label) in { 'Date': dummyData.date, 'Entity': dummyData.bill_to.name, 'Warehouse': 'Central DEP-4', 'Status': 'DISPATCHED' }" :key="label" 
                 class="border-r border-slate-200 last:border-0 p-4">
                <div class="text-[9px] font-black text-slate-400 uppercase mb-1">{{ label }}</div>
                <div class="text-[11px] font-bold text-slate-800 uppercase truncate">{{ val }}</div>
            </div>
        </div>

        <table class="w-full border border-slate-200 spreadsheet-table">
            <thead class="bg-slate-100">
                <tr>
                    <th class="border border-slate-200 p-3 text-left text-[10px] font-black uppercase">Line</th>
                    <th class="border border-slate-200 p-3 text-left text-[10px] font-black uppercase">SKU / Description</th>
                    <th class="border border-slate-200 p-3 text-right text-[10px] font-black uppercase">Quantity</th>
                    <th class="border border-slate-200 p-3 text-right text-[10px] font-black uppercase">Unit Price</th>
                    <th class="border border-slate-200 p-3 text-right text-[10px] font-black uppercase">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, i) in dummyData.items" :key="i">
                    <td class="border border-slate-200 p-3 text-[10px] font-bold text-slate-400">{{ String(i+1).padStart(2, '0') }}</td>
                    <td class="border border-slate-200 p-3 text-[11px] font-bold uppercase">{{ item.description }}</td>
                    <td class="border border-slate-200 p-3 text-right text-[11px] font-mono">{{ item.qty }}.00</td>
                    <td class="border border-slate-200 p-3 text-right text-[11px] font-mono">{{ item.rate }}.00</td>
                    <td class="border border-slate-200 p-3 text-right text-[11px] font-black bg-slate-50">₹{{ item.amount }}.00</td>
                </tr>
                <tr v-for="i in 10 - dummyData.items.length" :key="'blank-'+i">
                    <td class="border border-slate-200 p-3 h-10"></td>
                    <td class="border border-slate-200 p-3 h-10"></td>
                    <td class="border border-slate-200 p-3 h-10"></td>
                    <td class="border border-slate-200 p-3 h-10"></td>
                    <td class="border border-slate-200 p-3 h-10 bg-slate-50"></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="border border-slate-200 p-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Computer Generated Inventory Document</td>
                    <td class="border border-slate-200 p-3 text-right text-[10px] font-black uppercase bg-slate-100">Grand Total</td>
                    <td class="border border-slate-200 p-3 text-right text-sm font-black bg-slate-100">₹{{ dummyData.total }}.00</td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>

<style scoped>
.spreadsheet-table { border-collapse: collapse; }
</style>
