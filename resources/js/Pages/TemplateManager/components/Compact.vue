<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    dummyData: any;
    settings?: any;
}>();

const pdfSettings = computed(() => props.settings?.pdf || props.dummyData?.settings?.pdf || {});
const labels = computed(() => pdfSettings.value?.labels || {});

const company = computed(() => props.dummyData?.company || {});
const billTo = computed(() => props.dummyData?.bill_to || {});
const shipTo = computed(() => props.dummyData?.ship_to || {});
const items = computed(() => props.dummyData?.items || []);
const totals = computed(() => props.dummyData?.totals || {});
const meta = computed(() => props.dummyData?.meta || {});
</script>

<template>
    <div class="design-wrap compact-mode">
        <div class="flex justify-between items-center border-b border-slate-200 pb-4 mb-6">
            <h1 class="text-xl font-black uppercase tracking-tight">
                {{ company.name || 'Company' }} <span class="text-slate-400 font-light ml-2">{{ labels.invoice_title || dummyData.doc_title || 'Document' }}</span>
            </h1>
            <div v-if="pdfSettings.invoice_number !== false" class="text-[10px] font-black text-slate-400">#{{ dummyData.doc_no }}</div>
        </div>

        <div class="flex gap-12 mb-8">
            <div class="flex-1">
                <div class="text-[8px] font-black text-slate-400 uppercase mb-1">To:</div>
                <div class="text-[10px] font-black uppercase text-slate-800">{{ dummyData.bill_to.name }}</div>
                <div class="text-[9px] text-slate-500 uppercase leading-none mt-1">{{ dummyData.bill_to.city }}</div>
            </div>
            <div class="text-right">
                <div class="text-[8px] font-black text-slate-400 uppercase mb-1">Date:</div>
                <div class="text-[10px] font-black text-slate-800">{{ dummyData.date }}</div>
            </div>
        </div>

        <table class="w-full text-[10px] compact-table">
            <thead>
                <tr class="bg-slate-50 border-y border-slate-100">
                    <th class="py-2 text-left font-black uppercase tracking-tighter">Items</th>
                    <th class="py-2 text-right font-black uppercase tracking-tighter">Qty</th>
                    <th class="py-2 text-right font-black uppercase tracking-tighter">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in dummyData.items" :key="item.description" class="border-b border-slate-50">
                    <td class="py-2 font-bold uppercase truncate max-w-[150px]">{{ item.description }}</td>
                    <td class="py-2 text-right">{{ item.qty }}</td>
                    <td class="py-2 text-right font-black italic">₹{{ item.amount }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 pt-4 border-t border-slate-900 border-dashed">
            <div class="flex justify-between items-center mb-1">
                <span class="text-[9px] font-bold text-slate-400 uppercase">SubTotal</span>
                <span class="text-[10px] font-bold">₹{{ dummyData.sub_total }}.00</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-[9px] font-black uppercase">Net Total</span>
                <span class="text-xs font-black">₹{{ dummyData.total }}.00</span>
            </div>
        </div>

        <div class="mt-8 text-center text-[8px] text-slate-400 font-black uppercase tracking-[0.3em]">Thank You • msrk</div>
    </div>
</template>

<style scoped>
.design-wrap { padding: 10mm !important; }
</style>
