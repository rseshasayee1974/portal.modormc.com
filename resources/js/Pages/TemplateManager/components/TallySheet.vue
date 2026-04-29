<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps<{
    dummyData: any;
}>();
</script>

<template>
    <div class="design-wrap tally-view">
        <div class="flex justify-between items-start mb-16">
            <div>
                <h3 class="text-2xl font-black text-slate-900">msrk</h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase mt-1">Tamil Nadu, India</p>
                <p class="text-[10px] text-slate-400 font-bold lowercase">billing@modomines.com</p>
            </div>
            <div class="text-right">
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight border-b-4 border-slate-800 pb-2">Account Statement</h1>
                <p class="text-xs font-bold text-slate-400 mt-2 uppercase tracking-widest">{{ dummyData.date }} Cycle</p>
            </div>
        </div>

        <div class="flex justify-between mb-12">
            <div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Customer:</div>
                <h4 class="text-lg font-black text-slate-800 uppercase">{{ dummyData.bill_to.name }}</h4>
                <p class="text-xs text-slate-500 max-w-xs mt-2 uppercase">{{ dummyData.bill_to.address }}, {{ dummyData.bill_to.city }}</p>
            </div>
            <div class="w-80 bg-slate-900 text-white rounded-xl p-6 shadow-xl">
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6 border-b border-white/10 pb-2">Tally Summary</h5>
                <div class="space-y-4">
                    <div class="flex justify-between text-xs font-bold">
                        <span class="text-slate-400">Opening</span>
                        <span>₹94.83</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold">
                        <span class="text-slate-400">Accrued</span>
                        <span>₹{{ dummyData.total }}.00</span>
                    </div>
                    <div class="h-px bg-white/10"></div>
                    <div class="flex justify-between text-sm font-black uppercase text-indigo-400">
                        <span>Balance Due</span>
                        <span>₹{{ (dummyData.total + 94).toFixed(2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <table class="w-full ledger-table">
            <thead class="bg-slate-100">
                <tr>
                    <th class="px-4 py-4 text-left text-[10px] font-black uppercase text-slate-600">Entry Date</th>
                    <th class="px-4 py-4 text-left text-[10px] font-black uppercase text-slate-600">Transaction ID</th>
                    <th class="px-4 py-4 text-left text-[10px] font-black uppercase text-slate-600">Description</th>
                    <th class="px-4 py-4 text-right text-[10px] font-black uppercase text-slate-600">Debit (₹)</th>
                    <th class="px-4 py-4 text-right text-[10px] font-black uppercase text-slate-600">Credit (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, i) in dummyData.items" :key="i" class="border-b border-slate-100">
                    <td class="px-4 py-6 text-xs font-bold text-slate-400">{{ dummyData.date }}</td>
                    <td class="px-4 py-6 text-xs font-black text-slate-800 uppercase">TXN-{{ 1000+i }}</td>
                    <td class="px-4 py-6 text-[10px] text-slate-600 uppercase font-bold">{{ item.description }}</td>
                    <td class="px-4 py-6 text-right text-xs font-black">₹{{ item.amount }}.00</td>
                    <td class="px-4 py-6 text-right text-xs font-bold text-slate-300">-</td>
                </tr>
                <tr class="bg-indigo-50/30">
                    <td class="px-4 py-6 text-xs font-bold text-slate-400">{{ dummyData.date }}</td>
                    <td class="px-4 py-6 text-xs font-black text-indigo-600 uppercase">PMT-4492</td>
                    <td class="px-4 py-6 text-[10px] text-slate-600 uppercase font-black tracking-tighter">Razorpay Settlement • Ref: {{ dummyData.doc_no }}</td>
                    <td class="px-4 py-6 text-right text-xs font-bold text-slate-300">-</td>
                    <td class="px-4 py-6 text-right text-xs font-black text-indigo-600">₹{{ dummyData.total }}.00</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.ledger-table th { @apply border-b-2 border-slate-900; }
</style>
