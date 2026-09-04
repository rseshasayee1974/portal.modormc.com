<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: Object
});

const perPage = ref(25);
const currentPage = ref(1);
const searchQuery = ref('');

const transactions = computed(() => props.reportData?.transactions || props.reportData?.items || []);

const filteredList = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return transactions.value;
    return transactions.value.filter(item => 
        (item.dispatch_no && item.dispatch_no.toLowerCase().includes(q)) ||
        (item.customer_name && item.customer_name.toLowerCase().includes(q)) ||
        (item.site_name && item.site_name.toLowerCase().includes(q)) ||
        (item.truck_no && item.truck_no.toLowerCase().includes(q)) ||
        (item.grade_name && item.grade_name.toLowerCase().includes(q)) ||
        (item.cancelled_notes && item.cancelled_notes.toLowerCase().includes(q)) ||
        (item.cancelled_by && item.cancelled_by.toLowerCase().includes(q))
    );
});

const totalRows = computed(() => filteredList.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalRows.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedList = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return filteredList.value.slice(start, start + perPage.value);
});

watch([transactions, searchQuery], () => {
    currentPage.value = 1;
});
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Overview summary banner -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">Cancelled Dispatches</span>
                <p class="text-2xl font-black text-rose-700 mt-1">{{ reportData.total_cancelled_dispatches ?? transactions.length }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Reversed Quantity</span>
                <p class="text-2xl font-black text-indigo-700 mt-1">{{ formatQuantity(reportData.total_quantity) }} m³</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Reversed Value</span>
                <p class="text-2xl font-black text-slate-800 mt-1">{{ formatCurrency(reportData.total_amount) }}</p>
            </div>
        </div>

        <!-- Cancelled Dispatch Table Card -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-rose-700 flex items-center gap-2">
                        <i class="pi pi-times-circle text-rose-600"></i>
                        Cancelled Dispatches & Batches Audit
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Comprehensive audit log of cancelled trips, reversed sales orders, credit notes, and notes.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search cancelled trips..."
                            class="text-xs rounded-lg border border-slate-300 pl-8 pr-3 py-1.5 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none w-56"
                        />
                        <i class="pi pi-search absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                    </div>
                    <span class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200">
                        {{ totalRows }} Records
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/70 text-slate-600 uppercase font-black tracking-wider text-[10px]">
                            <th class="py-3 px-4">Dispatch #</th>
                            <th class="py-3 px-4">Batch / SO</th>
                            <th class="py-3 px-4">Customer & Site</th>
                            <th class="py-3 px-4">Grade</th>
                            <th class="py-3 px-4">Truck / Driver</th>
                            <th class="py-3 px-4 text-right">Qty (m³)</th>
                            <th class="py-3 px-4 text-right">Amount (₹)</th>
                            <th class="py-3 px-4">Invoice / Credit Note</th>
                            <th class="py-3 px-4">Cancelled Info</th>
                            <th class="py-3 px-4 min-w-[280px]">Cancellation Reason / Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in paginatedList" :key="item.dispatch_id" class="hover:bg-rose-50/20 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-900 whitespace-nowrap">
                                <div>{{ item.dispatch_no }}</div>
                                <span class="inline-flex items-center gap-1 text-[9px] uppercase font-bold text-rose-700 bg-rose-100/70 px-1.5 py-0.5 rounded mt-0.5">
                                    <i class="pi pi-ban text-[8px]"></i> Cancelled
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-800">{{ item.batch_no }}</div>
                                <div class="text-[10px] text-slate-400">SO: {{ item.sales_order_no || '-' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-800">{{ item.customer_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ item.site_name }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-slate-700 font-medium">
                                {{ item.grade_name }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-800">{{ item.truck_no }}</div>
                                <div class="text-[10px] text-slate-500">{{ item.driver_name }}</div>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-indigo-700 whitespace-nowrap">
                                {{ formatQuantity(item.quantity) }}
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-800 whitespace-nowrap">
                                {{ formatCurrency(item.amount_total) }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-[11px]">
                                <div v-if="item.invoice_number !== '-'" class="text-slate-600">
                                    <span class="text-slate-400">Inv:</span> {{ item.invoice_number }}
                                </div>
                                <div v-if="item.credit_note_number !== '-'" class="text-indigo-600 font-bold">
                                    <span class="text-slate-400">CN:</span> {{ item.credit_note_number }}
                                </div>
                                <div v-if="item.invoice_number === '-' && item.credit_note_number === '-'" class="text-slate-400">
                                    Uninvoiced
                                </div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-[11px]">
                                <div class="text-slate-800 font-medium">{{ item.cancelled_at }}</div>
                                <div class="text-[10px] text-slate-400">By: {{ item.cancelled_by }}</div>
                            </td>
                            <td class="py-3 px-4 text-[11px] text-slate-600 italic bg-rose-50/30 rounded border-l-2 border-rose-400">
                                {{ item.cancelled_notes }}
                            </td>
                        </tr>
                        <tr v-if="paginatedList.length === 0">
                            <td colspan="10" class="py-8 text-center text-slate-400 text-sm">
                                <i class="pi pi-inbox text-3xl mb-2 text-slate-300 block"></i>
                                No cancelled dispatches found for the selected filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalRows > 0" class="px-5 py-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500 bg-slate-50/50">
                <div>
                    Showing <span class="font-bold text-slate-700">{{ (sanitizedPage - 1) * perPage + 1 }}</span>
                    to <span class="font-bold text-slate-700">{{ Math.min(sanitizedPage * perPage, totalRows) }}</span>
                    of <span class="font-bold text-slate-700">{{ totalRows }}</span> records
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        :disabled="sanitizedPage <= 1"
                        @click="currentPage--"
                        class="px-2.5 py-1 rounded border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold"
                    >
                        Previous
                    </button>
                    <span class="px-2 font-bold text-slate-700">
                        {{ sanitizedPage }} / {{ totalPages }}
                    </span>
                    <button
                        :disabled="sanitizedPage >= totalPages"
                        @click="currentPage++"
                        class="px-2.5 py-1 rounded border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
