<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

// Pagination setup (30 rows per page)
const perPage = ref(30);
const currentPage = ref(1);

watch(() => props.reportData?.transactions, () => {
    currentPage.value = 1;
});

const transactions = computed(() => props.reportData?.transactions || []);
const totalTransactions = computed(() => transactions.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalTransactions.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedTransactions = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return transactions.value.slice(start, start + perPage.value);
});

const startIndex = computed(() => {
    if (totalTransactions.value === 0) return 0;
    return (sanitizedPage.value - 1) * perPage.value + 1;
});

const endIndex = computed(() => {
    return Math.min(sanitizedPage.value * perPage.value, totalTransactions.value);
});

const goToPage = (p) => {
    if (p >= 1 && p <= totalPages.value) {
        currentPage.value = p;
    }
};

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = sanitizedPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const pages = [];
    pages.push(1);
    if (current > 3) {
        pages.push('...');
    }
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    if (current < total - 2) {
        pages.push('...');
    }
    pages.push(total);
    return pages;
});
</script>

<template>
    <div>
        <!-- Sales Dispatch & Invoice wise Breakdown -->
        <div class="mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>Sales Dispatch & Invoice wise Breakdown</span>
                <span class="text-slate-400 font-normal normal-case">
                    Total {{ totalTransactions }} Dispatches (30 rows per page)
                </span>
            </div>
            <div class="overflow-x-auto border border-slate-200 border-b-0">
                <table class="w-full text-left border-collapse min-w-[1000px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="3%">#</th>
                            <th class="py-3 px-3 text-center" width="8%">Date</th>
                            <th class="py-3 px-3 text-center" width="10%">Dispatch / Batch</th>
                            <th class="py-3 px-3 text-center" width="10%">Invoice Details</th>
                            <th class="py-3 px-3 text-center" width="10%">E-Invoice Reference</th>
                            <th class="py-3 px-3" width="15%">Customer / Site</th>
                            <th class="py-3 px-3 text-right" width="6%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="6%">Empty Wt</th>
                            <th class="py-3 px-3 text-right" width="6%">Loaded Wt</th>
                            <th class="py-3 px-3 text-right" width="6%">Net Wt</th>
                            <th class="py-3 px-3 text-right" width="7%">Taxable Amt</th>
                            <th class="py-3 px-3 text-right" width="6%">Tax Amt</th>
                            <th class="py-3 px-3 text-right" width="7%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in paginatedTransactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-3 text-center text-slate-400">{{ (sanitizedPage - 1) * perPage + idx + 1 }}</td>
                            <td class="py-3 px-3 text-center text-slate-500 font-medium">
                                <span>{{ row.date }}</span>
                                <span v-if="row.time" class="text-[10px] text-slate-400 block font-normal">{{ row.time }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="font-bold text-slate-800">{{ row.dispatch_no ?? '-' }}</span>
                                <span class="text-[10px] text-slate-400 block font-normal">{{ row.batch_no ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <template v-if="row.invoice_number && row.invoice_number !== '-'">
                                    <span class="font-bold text-slate-800">{{ row.invoice_number }}</span>
                                    <span v-if="row.invoice_date && row.invoice_date !== '-'" class="text-[10px] text-slate-400 block font-normal">{{ row.invoice_date }}</span>
                                </template>
                                <span v-else class="px-2 py-0.5 text-[10px] font-medium rounded bg-amber-50 text-amber-700 border border-amber-200/60 inline-block">
                                    Unbilled
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <template v-if="row.einv_ackno">
                                    <div class="flex flex-col items-center gap-0.5" :title="`Ack Date: ${row.einv_ack_date || '-'}\nIRN: ${row.einv_irn || '-'}`">
                                        <span class="font-mono text-[10px] text-sky-700 font-bold tracking-tight">{{ row.einv_ackno }}</span>
                                        <span v-if="row.einv_status" class="px-1.5 py-0.2 text-[9px] rounded font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                            {{ row.einv_status }}
                                        </span>
                                    </div>
                                </template>
                                <span v-else class="text-slate-300 font-normal">-</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-medium text-slate-800">{{ row.customer_name }}</span>
                                <span v-if="row.site_name && row.site_name !== 'N/A'" class="text-[10px] text-slate-400 block font-normal">{{ row.site_name }}</span>
                            </td>
                            <td class="py-3 px-3 text-right font-bold text-slate-800">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-3 px-3 text-right text-slate-600">{{ formatQuantity(row.truck_empty ?? row.empty_weight) }}</td>
                            <td class="py-3 px-3 text-right text-slate-600">{{ formatQuantity(row.loaded_weight ?? row.truck_loaded) }}</td>
                            <td class="py-3 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(row.netweight ?? row.net_weight) }}</td>
                            <td class="py-3 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-3 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                            <td class="py-3 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!paginatedTransactions?.length">
                            <td colspan="13" class="py-4 text-center text-slate-400">No dispatches found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="6" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Sales</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_quantity) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_truck_empty) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_loaded_weight) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_net_weight) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_untaxed) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_tax) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar (30 rows per page) -->
            <div v-if="totalTransactions > perPage || totalPages > 1" class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ startIndex }}</strong> to <strong class="text-slate-800">{{ endIndex }}</strong> of <strong class="text-slate-800">{{ totalTransactions }}</strong> dispatches
                    </span>
                    <div class="flex items-center gap-1.5 ml-2">
                        <span class="text-[11px] text-slate-400">Rows:</span>
                        <select 
                            v-model.number="perPage" 
                            @change="currentPage = 1"
                            class="border border-slate-200 rounded px-2 py-0.5 text-xs text-slate-700 bg-white font-semibold focus:outline-none focus:ring-1 focus:ring-[#0064d2]"
                        >
                            <option :value="15">15</option>
                            <option :value="30">30</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button 
                        type="button" 
                        @click="goToPage(1)" 
                        :disabled="sanitizedPage <= 1"
                        class="px-2 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="First Page"
                    >
                        « First
                    </button>
                    <button 
                        type="button" 
                        @click="goToPage(sanitizedPage - 1)" 
                        :disabled="sanitizedPage <= 1"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        ‹ Prev
                    </button>
                    
                    <template v-for="(p, pIdx) in visiblePages" :key="pIdx">
                        <span v-if="p === '...'" class="px-1 text-slate-400 font-semibold">...</span>
                        <button 
                            v-else 
                            type="button" 
                            @click="goToPage(p)"
                            :class="[
                                'px-2.5 py-1 rounded text-[11px] font-bold transition-all cursor-pointer',
                                sanitizedPage === p 
                                    ? 'bg-[#0064d2] text-white shadow-xs' 
                                    : 'border border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            {{ p }}
                        </button>
                    </template>

                    <button 
                        type="button" 
                        @click="goToPage(sanitizedPage + 1)" 
                        :disabled="sanitizedPage >= totalPages"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        Next ›
                    </button>
                    <button 
                        type="button" 
                        @click="goToPage(totalPages)" 
                        :disabled="sanitizedPage >= totalPages"
                        class="px-2 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="Last Page"
                    >
                        Last »
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. Product Consolidated Report (Mix Design & Concrete Grade wise) -->
        <div class="mt-6 mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>Product Consolidated Report (Mix Design & Concrete Grade wise)</span>
                <span class="text-slate-400 font-normal normal-case">Consolidated by Mix Design & Concrete Grade with Batch Size and Weights</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse ">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="18%">Mix Design Name</th>
                            <th class="py-3 px-3 text-center" width="10%">Grade</th>
                            <th class="py-3 px-3 text-center" width="6%">UOM</th>
                            <th class="py-3 px-3 text-center" width="6%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="7%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="7%">Delivered Qty</th>
                            <!-- <th class="py-3 px-3 text-right" width="7%">Empty Wt</th>
                            <th class="py-3 px-3 text-right" width="7%">Loaded Wt</th>
                            <th class="py-3 px-3 text-right" width="7%">Net Wt</th>
                            <th class="py-3 px-3 text-right" width="7%">Avg Rate</th>
                            <th class="py-3 px-3 text-right" width="8%">Taxable Amt</th>
                            <th class="py-3 px-3 text-right" width="7%">Tax Amt</th> -->
                            <th class="py-3 px-3 text-right" width="9%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.product_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.mix_name }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ row.concrete_grade }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-500 font-medium">{{ row.uom }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(row.truck_empty) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(row.loaded_weight) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(row.netweight) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.product_summary?.length">
                            <td colspan="14" class="py-4 text-center text-slate-400">No product items found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Product Summary</td>
                            <td class="py-3.5 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.product_summary?.reduce((acc, r) => acc + (r.trips_count || 0), 0) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_product_batch_size) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_product_quantity || reportData.total_quantity) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_product_truck_empty) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_product_loaded_weight) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_product_net_weight) }}</td>
                            <td></td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_untaxed) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_tax) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Customer Consolidated Report (Customer / Party wise) -->
        <div class="mt-6 mb-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>Customer Consolidated Report (Customer / Party wise)</span>
                <span class="text-slate-400 font-normal normal-case">Consolidated by Customer with Batch Size and Weights</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="26%">Customer / Party Name</th>
                            <th class="py-3 px-3 text-center" width="7%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="9%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="10%">Delivered Qty</th>
                            <!-- <th class="py-3 px-3 text-right" width="9%">Empty Wt</th>
                            <th class="py-3 px-3 text-right" width="9%">Loaded Wt</th>
                            <th class="py-3 px-3 text-right" width="9%">Net Wt</th> -->
                            <!-- <th class="py-3 px-3 text-right" width="11%">Taxable Amt</th>
                            <th class="py-3 px-3 text-right" width="10%">Tax Amt</th> -->
                            <th class="py-3 px-3 text-right" width="11%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.party_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.party_name }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(row.truck_empty) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(row.loaded_weight) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(row.netweight) }}</td> -->
                            <!-- <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.party_summary?.length">
                            <td colspan="11" class="py-4 text-center text-slate-400">No customer records found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="2" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Customer Volume</td>
                            <td class="py-3.5 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.party_summary?.reduce((acc, r) => acc + (r.trips_count || 0), 0) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_party_batch_size) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_party_quantity) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_party_truck_empty) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_party_loaded_weight) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_party_net_weight) }}</td> -->
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_untaxed) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_tax) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Truck Consolidated Report (Vehicle wise) -->
        <div class="mt-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>Truck Consolidated Report (Vehicle / Fleet wise)</span>
                <span class="text-slate-400 font-normal normal-case">Consolidated by Transit Mixer / Truck with Batch Size and Weights</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[950px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="26%">Truck / Vehicle Registration</th>
                            <th class="py-3 px-3 text-center" width="7%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="9%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="10%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="9%">Empty Wt</th>
                            <th class="py-3 px-3 text-right" width="9%">Loaded Wt</th>
                            <th class="py-3 px-3 text-right" width="9%">Net Wt</th>
                            <!-- <th class="py-3 px-3 text-right" width="11%">Taxable Amt</th>
                            <th class="py-3 px-3 text-right" width="10%">Tax Amt</th> -->
                            <th class="py-3 px-3 text-right" width="11%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.truck_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.truck_no }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(row.truck_empty) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(row.loaded_weight) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(row.netweight) }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.truck_summary?.length">
                            <td colspan="11" class="py-4 text-center text-slate-400">No truck dispatch records found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="2" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Fleet Volume</td>
                            <td class="py-3.5 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_truck_trips || reportData.truck_summary?.reduce((acc, r) => acc + (r.trips_count || 0), 0) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_truck_batch_size) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_truck_quantity) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_truck_empty) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_truck_loaded_weight) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_truck_net_weight) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_truck_untaxed) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_truck_tax) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_truck_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. Unload Site Consolidated Report (Site wise) -->
        <div class="mt-6">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>5. Unload Site Consolidated Report (Site wise)</span>
                <span class="text-slate-400 font-normal normal-case">Consolidated by delivery/unloading destination site</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[750px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3" width="28%">Unload Site Name</th>
                            <th class="py-3 px-3" width="23%">Customer / Party</th>
                            <th class="py-3 px-3 text-center" width="10%">Trips</th>
                            <th class="py-3 px-3 text-right" width="11%">Batch Size</th>
                            <th class="py-3 px-3 text-right" width="11%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="12%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.site_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.site_name }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ row.customer_name || '-' }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.site_summary?.length">
                            <td colspan="7" class="py-4 text-center text-slate-400">No site dispatch records found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Site Volume</td>
                            <td class="py-3.5 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_site_trips || reportData.site_summary?.reduce((acc, r) => acc + (r.trips_count || 0), 0) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_site_batch_size) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_site_quantity) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_site_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6. Payment Mode Consolidated Report -->
        <div class="mt-6 mb-4">
            <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase flex items-center justify-between">
                <span>6. Payment Mode Consolidated Report</span>
                <span class="text-slate-400 font-normal normal-case">Consolidated by settlement and payment terms</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-b">
                <table class="w-full text-left border-collapse min-w-[750px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3" width="40%">Payment Mode</th>
                            <th class="py-3 px-3 text-center" width="13%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="14%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="14%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="14%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-for="(row, idx) in reportData.payment_mode_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.payment_mode }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!reportData.payment_mode_summary?.length">
                            <td colspan="6" class="py-4 text-center text-slate-400">No payment mode records found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="2" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Payment Modes</td>
                            <td class="py-3.5 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_payment_mode_trips || reportData.payment_mode_summary?.reduce((acc, r) => acc + (r.trips_count || 0), 0) }}</td>
                            <!-- <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_payment_mode_batch_size) }}</td> -->
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_payment_mode_quantity) }}</td>
                            <td class="py-3.5 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_payment_mode_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
