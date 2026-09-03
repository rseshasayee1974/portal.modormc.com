<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: Object
});

// 1. Every Batching / Trip Verification List
const perPage = ref(30);
const currentPage = ref(1);
const selectedTruckFilter = ref('');
const searchQuery = ref('');

// 2. Fleet Consolidated Summary
const groupPerPage = ref(30);
const groupCurrentPage = ref(1);

watch(() => props.reportData, () => {
    currentPage.value = 1;
    groupCurrentPage.value = 1;
    selectedTruckFilter.value = '';
    searchQuery.value = '';
});

const allTrips = computed(() => props.reportData?.truck_trips || props.reportData?.transactions || []);

const truckOptions = computed(() => {
    const set = new Set();
    allTrips.value.forEach(t => {
        if (t.truck_no) set.add(t.truck_no);
    });
    return Array.from(set).sort();
});

const filteredTrips = computed(() => {
    let list = allTrips.value;
    if (selectedTruckFilter.value) {
        list = list.filter(t => t.truck_no === selectedTruckFilter.value);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(t => 
            (t.truck_no && t.truck_no.toLowerCase().includes(q)) ||
            (t.docket_no && t.docket_no.toLowerCase().includes(q)) ||
            (t.batch_no && t.batch_no.toLowerCase().includes(q)) ||
            (t.customer_name && t.customer_name.toLowerCase().includes(q)) ||
            (t.site_name && t.site_name.toLowerCase().includes(q)) ||
            (t.driver_name && t.driver_name.toLowerCase().includes(q)) ||
            (t.concrete_grade && t.concrete_grade.toLowerCase().includes(q))
        );
    }
    return list;
});

const totalCount = computed(() => filteredTrips.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedTrips = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return filteredTrips.value.slice(start, start + perPage.value);
});

const startIndex = computed(() => {
    if (totalCount.value === 0) return 0;
    return (sanitizedPage.value - 1) * perPage.value + 1;
});

const endIndex = computed(() => {
    return Math.min(sanitizedPage.value * perPage.value, totalCount.value);
});

const goToPage = (p) => {
    if (p >= 1 && p <= totalPages.value) {
        currentPage.value = p;
    }
};

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = sanitizedPage.value;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [1];
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) pages.push(i);
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});

const filteredTotals = computed(() => {
    const list = filteredTrips.value;
    return {
        batch_size: list.reduce((acc, t) => acc + (t.batch_size || 0), 0),
        delivered_qty: list.reduce((acc, t) => acc + (t.delivered_qty || 0), 0),
        empty_weight: list.reduce((acc, t) => acc + (t.empty_weight || 0), 0),
        loaded_weight: list.reduce((acc, t) => acc + (t.loaded_weight || 0), 0),
        net_weight: list.reduce((acc, t) => acc + (t.net_weight || 0), 0),
        amount_untaxed: list.reduce((acc, t) => acc + (t.amount_untaxed || 0), 0),
        amount_tax: list.reduce((acc, t) => acc + (t.amount_tax || 0), 0),
        amount_total: list.reduce((acc, t) => acc + (t.amount_total || 0), 0),
    };
});

// Fleet Consolidated Groups
const allGroups = computed(() => props.reportData?.truck_groups || []);
const groupTotalCount = computed(() => allGroups.value.length);
const groupTotalPages = computed(() => Math.max(1, Math.ceil(groupTotalCount.value / groupPerPage.value)));

const sanitizedGroupPage = computed(() => {
    if (groupCurrentPage.value < 1) return 1;
    if (groupCurrentPage.value > groupTotalPages.value) return groupTotalPages.value;
    return groupCurrentPage.value;
});

const paginatedGroups = computed(() => {
    const start = (sanitizedGroupPage.value - 1) * groupPerPage.value;
    return allGroups.value.slice(start, start + groupPerPage.value);
});

const groupStartIndex = computed(() => {
    if (groupTotalCount.value === 0) return 0;
    return (sanitizedGroupPage.value - 1) * groupPerPage.value + 1;
});

const groupEndIndex = computed(() => {
    return Math.min(sanitizedGroupPage.value * groupPerPage.value, groupTotalCount.value);
});

const goToGroupPage = (p) => {
    if (p >= 1 && p <= groupTotalPages.value) {
        groupCurrentPage.value = p;
    }
};

const visibleGroupPages = computed(() => {
    const total = groupTotalPages.value;
    const current = sanitizedGroupPage.value;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [1];
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) pages.push(i);
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});

const groupTotals = computed(() => {
    const list = allGroups.value;
    return {
        trips_count: list.reduce((acc, g) => acc + (g.trips_count || 0), 0),
        total_batch: list.reduce((acc, g) => acc + (g.total_batch || 0), 0),
        total_qty: list.reduce((acc, g) => acc + (g.total_qty || 0), 0),
        total_amount: list.reduce((acc, g) => acc + (g.total_amount || 0), 0),
    };
});
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Overview summary banner -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 2xl:grid-cols-8 gap-3">
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Total Trips</span>
                <p class="text-sm sm:text-base font-black text-slate-800 mt-1 truncate tracking-tight" :title="`${filteredTrips.length}`">{{ filteredTrips.length }}</p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Batch Size</span>
                <p class="text-sm sm:text-base font-black text-indigo-700 mt-1 truncate tracking-tight" :title="`${formatQuantity(filteredTotals.batch_size)} m³`">
                    {{ formatQuantity(filteredTotals.batch_size) }} <span class="text-xs font-semibold text-indigo-400">m³</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Delivered Qty</span>
                <p class="text-sm sm:text-base font-black text-blue-700 mt-1 truncate tracking-tight" :title="`${formatQuantity(filteredTotals.delivered_qty)} m³`">
                    {{ formatQuantity(filteredTotals.delivered_qty) }} <span class="text-xs font-semibold text-blue-400">m³</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Empty Wt</span>
                <p class="text-sm sm:text-base font-bold text-slate-600 mt-1 truncate tracking-tight" :title="`${formatQuantity(filteredTotals.empty_weight)} T`">
                    {{ formatQuantity(filteredTotals.empty_weight) }} <span class="text-xs font-medium text-slate-400">T</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Loaded Wt</span>
                <p class="text-sm sm:text-base font-bold text-slate-600 mt-1 truncate tracking-tight" :title="`${formatQuantity(filteredTotals.loaded_weight)} T`">
                    {{ formatQuantity(filteredTotals.loaded_weight) }} <span class="text-xs font-medium text-slate-400">T</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Net Wt</span>
                <p class="text-sm sm:text-base font-black text-emerald-700 mt-1 truncate tracking-tight" :title="`${formatQuantity(filteredTotals.net_weight)} T`">
                    {{ formatQuantity(filteredTotals.net_weight) }} <span class="text-xs font-semibold text-emerald-400">T</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Taxable Amt</span>
                <p class="text-sm sm:text-base font-bold text-slate-700 mt-1 truncate tracking-tight" :title="formatCurrency(filteredTotals.amount_untaxed)">
                    {{ formatCurrency(filteredTotals.amount_untaxed) }}
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Total Revenue</span>
                <p class="text-sm sm:text-base font-black text-[#1d2d3e] mt-1 truncate tracking-tight" :title="formatCurrency(filteredTotals.amount_total)">
                    {{ formatCurrency(filteredTotals.amount_total) }}
                </p>
            </div>
        </div>

        <!-- Section 1: Truck Wise Trip Report Table -->
        <div class="bg-white rounded border border-slate-200 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Every Batching / Trip Verification List
                        </h3>
                        <span class="text-[10px] px-2.5 py-0.5 bg-blue-100 text-blue-800 font-bold rounded">
                            {{ totalCount }} Trips
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        Itemized truck trip tickets with delivered qty, empty weight, loaded weight, net weight, and tax breakdown
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1200px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="4%">Trip #</th>
                            <th class="py-3 px-3 text-center" width="9%">Truck / Mixer</th>
                            <th class="py-3 px-3" width="11%">Date & Time</th>
                            <th class="py-3 px-3" width="11%">Dispatch / DSP #</th>
                            <th class="py-3 px-3" width="14%">Customer Name</th>
                            <th class="py-3 px-3" width="12%">Unload Site</th>
                            <th class="py-3 px-3 text-center" width="9%">Grade / Mix</th>
                            <th class="py-3 px-3 text-right" width="6%">Deliv Qty</th>
                            <th class="py-3 px-3 text-right" width="5%">Empty Wt</th>
                            <th class="py-3 px-3 text-right" width="5%">Loaded Wt</th>
                            <th class="py-3 px-3 text-right" width="5%">Net Wt</th>
                            <th class="py-3 px-3 text-right" width="6%">Taxable Amt</th>
                            <th class="py-3 px-3 text-right" width="5%">Tax Amt</th>
                            <th class="py-3 px-3 text-right" width="8%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(trip, idx) in paginatedTrips" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ (sanitizedPage - 1) * perPage + idx + 1 }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ trip.truck_no }}</td>
                            <td class="py-2.5 px-3 text-slate-600 font-mono text-[10px]">{{ trip.dispatch_time }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ trip.docket_no }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800 truncate max-w-[180px]" :title="trip.customer_name">{{ trip.customer_name }}</td>
                            <td class="py-2.5 px-3 text-slate-600 truncate max-w-[150px]" :title="trip.site_name">{{ trip.site_name }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-emerald-700">{{ trip.concrete_grade }}</td>
                            <td class="py-2.5 px-3 text-right font-black text-slate-900">{{ formatQuantity(trip.delivered_qty) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(trip.empty_weight) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(trip.loaded_weight) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-800">{{ formatQuantity(trip.net_weight) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(trip.amount_untaxed) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(trip.amount_tax) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(trip.amount_total) }}</td>
                        </tr>
                        <tr v-if="!paginatedTrips.length">
                            <td colspan="14" class="py-8 text-center text-slate-400">No truck trips match the selected filter</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="7" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Fleet Volume ({{ totalCount }} Trips)</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(filteredTotals.delivered_qty) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(filteredTotals.empty_weight) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(filteredTotals.loaded_weight) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(filteredTotals.net_weight) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(filteredTotals.amount_untaxed) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(filteredTotals.amount_tax) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(filteredTotals.amount_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar (30 rows per page) -->
            <div v-if="totalCount > perPage || totalPages > 1" class="px-4 py-3 bg-slate-50 border-t border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ startIndex }}</strong> to <strong class="text-slate-800">{{ endIndex }}</strong> of <strong class="text-slate-800">{{ totalCount }}</strong> trips
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
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="Last Page"
                    >
                        Last »
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 2: Fleet Consolidated Summary Table -->
        <div v-if="allGroups.length" class="bg-white rounded border border-slate-200 shadow-sm mt-8">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Fleet Consolidated Summary
                        </h3>
                        <span class="text-[10px] px-2.5 py-0.5 bg-indigo-100 text-indigo-800 font-bold rounded">
                            {{ groupTotalCount }} Trucks
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        Consolidated vehicle fleet summary with total trips, batch capacity, delivered volume, and revenue
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3 text-center" width="25%">Truck / Mixer Reg</th>
                            <th class="py-3 px-3 text-center" width="15%">Trips</th>
                            <th class="py-3 px-3 text-right" width="18%">Batch Size (m³)</th>
                            <th class="py-3 px-3 text-right" width="18%">Delivered Qty (m³)</th>
                            <th class="py-3 px-3 text-right" width="19%">Total Amt (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(tg, idx) in paginatedGroups" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ (sanitizedGroupPage - 1) * groupPerPage + idx + 1 }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ tg.truck_no }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-slate-800">{{ tg.trips_count }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(tg.total_batch) }}</td>
                            <td class="py-2.5 px-3 text-right font-black text-slate-900">{{ formatQuantity(tg.total_qty) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(tg.total_amount) }}</td>
                        </tr>
                        <tr v-if="!paginatedGroups.length">
                            <td colspan="6" class="py-8 text-center text-slate-400">No fleet summary available</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="2" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Grand Fleet Total ({{ groupTotalCount }} Trucks)</td>
                            <td class="py-3 px-3 text-center text-[#1d2d3e] font-black">{{ groupTotals.trips_count }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(groupTotals.total_batch) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(groupTotals.total_qty) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(groupTotals.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Group Pagination Bar -->
            <div v-if="groupTotalCount > groupPerPage || groupTotalPages > 1" class="px-4 py-3 bg-slate-50 border-t border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ groupStartIndex }}</strong> to <strong class="text-slate-800">{{ groupEndIndex }}</strong> of <strong class="text-slate-800">{{ groupTotalCount }}</strong> trucks
                    </span>
                    <div class="flex items-center gap-1.5 ml-2">
                        <span class="text-[11px] text-slate-400">Rows:</span>
                        <select 
                            v-model.number="groupPerPage" 
                            @change="groupCurrentPage = 1"
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
                        @click="goToGroupPage(1)" 
                        :disabled="sanitizedGroupPage <= 1"
                        class="px-2 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="First Page"
                    >
                        « First
                    </button>
                    <button 
                        type="button" 
                        @click="goToGroupPage(sanitizedGroupPage - 1)" 
                        :disabled="sanitizedGroupPage <= 1"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        ‹ Prev
                    </button>
                    
                    <template v-for="(p, pIdx) in visibleGroupPages" :key="pIdx">
                        <span v-if="p === '...'" class="px-1 text-slate-400 font-semibold">...</span>
                        <button 
                            v-else 
                            type="button" 
                            @click="goToGroupPage(p)"
                            :class="[
                                'px-2.5 py-1 rounded text-[11px] font-bold transition-all cursor-pointer',
                                sanitizedGroupPage === p 
                                    ? 'bg-[#0064d2] text-white shadow-xs' 
                                    : 'border border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            {{ p }}
                        </button>
                    </template>

                    <button 
                        type="button" 
                        @click="goToGroupPage(sanitizedGroupPage + 1)" 
                        :disabled="sanitizedGroupPage >= groupTotalPages"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        Next ›
                    </button>
                    <button 
                        type="button" 
                        @click="goToGroupPage(groupTotalPages)" 
                        :disabled="sanitizedGroupPage >= groupTotalPages"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="Last Page"
                    >
                        Last »
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
