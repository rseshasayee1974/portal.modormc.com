<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: Object
});

const emit = defineEmits(['view-statement', 'select-patron']);

const activeTab = ref('summary'); // 'summary' | 'trips'

// 1. Consolidated Customers Pagination
const perPage = ref(30);
const currentPage = ref(1);

watch(() => props.reportData?.transactions, () => {
    currentPage.value = 1;
    tripCurrentPage.value = 1;
    selectedCustomerFilter.value = '';
    tripSearch.value = '';
});

const allRows = computed(() => props.reportData?.transactions || []);
const totalCount = computed(() => allRows.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedRows = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return allRows.value.slice(start, start + perPage.value);
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

// 2. Batching / Trip Verification List
const tripPerPage = ref(30);
const tripCurrentPage = ref(1);
const tripSearch = ref('');
const selectedCustomerFilter = ref('');

const allTrips = computed(() => props.reportData?.batch_dispatches || []);

const customerOptions = computed(() => {
    const set = new Set();
    allTrips.value.forEach(t => {
        if (t.customer_name) set.add(t.customer_name);
    });
    return Array.from(set).sort();
});

const filteredTrips = computed(() => {
    let list = [...allTrips.value];
    if (selectedCustomerFilter.value) {
        list = list.filter(t => t.customer_name === selectedCustomerFilter.value);
    }
    if (tripSearch.value.trim()) {
        const q = tripSearch.value.trim().toLowerCase();
        list = list.filter(t => 
            (t.customer_name && t.customer_name.toLowerCase().includes(q)) ||
            (t.docket_no && t.docket_no.toLowerCase().includes(q)) ||
            (t.batch_no && t.batch_no.toLowerCase().includes(q)) ||
            (t.truck_no && t.truck_no.toLowerCase().includes(q)) ||
            (t.site_name && t.site_name.toLowerCase().includes(q)) ||
            (t.driver_name && t.driver_name.toLowerCase().includes(q)) ||
            (t.concrete_grade && t.concrete_grade.toLowerCase().includes(q)) ||
            (t.mix_name && t.mix_name.toLowerCase().includes(q))
        );
    }
    return list.sort((a, b) => {
        const timeA = a.dispatch_timestamp ?? (a.dispatch_time ? new Date(a.dispatch_time).getTime() : 0);
        const timeB = b.dispatch_timestamp ?? (b.dispatch_time ? new Date(b.dispatch_time).getTime() : 0);
        if (timeA !== timeB) return timeA - timeB;
        return (a.dispatch_id || 0) - (b.dispatch_id || 0);
    });
});

const tripTotalCount = computed(() => filteredTrips.value.length);
const tripTotalPages = computed(() => Math.max(1, Math.ceil(tripTotalCount.value / tripPerPage.value)));

const sanitizedTripPage = computed(() => {
    if (tripCurrentPage.value < 1) return 1;
    if (tripCurrentPage.value > tripTotalPages.value) return tripTotalPages.value;
    return tripCurrentPage.value;
});

const paginatedTrips = computed(() => {
    const start = (sanitizedTripPage.value - 1) * tripPerPage.value;
    return filteredTrips.value.slice(start, start + tripPerPage.value);
});

const tripStartIndex = computed(() => {
    if (tripTotalCount.value === 0) return 0;
    return (sanitizedTripPage.value - 1) * tripPerPage.value + 1;
});

const tripEndIndex = computed(() => {
    return Math.min(sanitizedTripPage.value * tripPerPage.value, tripTotalCount.value);
});

const goToTripPage = (p) => {
    if (p >= 1 && p <= tripTotalPages.value) {
        tripCurrentPage.value = p;
    }
};

const visibleTripPages = computed(() => {
    const total = tripTotalPages.value;
    const current = sanitizedTripPage.value;
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

const tripTotals = computed(() => {
    const list = filteredTrips.value;
    return {
        batch_size: list.reduce((acc, t) => acc + (t.batch_size || 0), 0),
        delivered_qty: list.reduce((acc, t) => acc + (t.delivered_qty || 0), 0),
        empty_weight: list.reduce((acc, t) => acc + (t.empty_weight || 0), 0),
        loaded_weight: list.reduce((acc, t) => acc + (t.loaded_weight || 0), 0),
        net_weight: list.reduce((acc, t) => acc + (t.net_weight || 0), 0),
        amount_total: list.reduce((acc, t) => acc + (t.amount_total || 0), 0),
    };
});
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Overview summary banner -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 2xl:grid-cols-7 gap-3">
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Total Trips</span>
                <p class="text-sm sm:text-base font-black text-slate-800 mt-1 truncate tracking-tight" :title="`${reportData.total_trips || 0}`">{{ reportData.total_trips || 0 }}</p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Batch Size</span>
                <p class="text-sm sm:text-base font-black text-indigo-700 mt-1 truncate tracking-tight" :title="`${formatQuantity(reportData.total_batch_size)} m³`">
                    {{ formatQuantity(reportData.total_batch_size) }} <span class="text-xs font-semibold text-indigo-400">m³</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Delivered Qty</span>
                <p class="text-sm sm:text-base font-black text-blue-700 mt-1 truncate tracking-tight" :title="`${formatQuantity(reportData.total_quantity)} m³`">
                    {{ formatQuantity(reportData.total_quantity) }} <span class="text-xs font-semibold text-blue-400">m³</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Empty Wt</span>
                <p class="text-sm sm:text-base font-bold text-slate-600 mt-1 truncate tracking-tight" :title="`${formatQuantity(reportData.total_truck_empty)} T`">
                    {{ formatQuantity(reportData.total_truck_empty) }} <span class="text-xs font-medium text-slate-400">T</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Loaded Wt</span>
                <p class="text-sm sm:text-base font-bold text-slate-600 mt-1 truncate tracking-tight" :title="`${formatQuantity(reportData.total_loaded_weight)} T`">
                    {{ formatQuantity(reportData.total_loaded_weight) }} <span class="text-xs font-medium text-slate-400">T</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Net Wt</span>
                <p class="text-sm sm:text-base font-black text-emerald-700 mt-1 truncate tracking-tight" :title="`${formatQuantity(reportData.total_net_weight)} T`">
                    {{ formatQuantity(reportData.total_net_weight) }} <span class="text-xs font-semibold text-emerald-400">T</span>
                </p>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm min-w-0 flex flex-col justify-between hover:border-slate-300 transition-colors">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">Total Revenue</span>
                <p class="text-sm sm:text-base font-black text-[#1d2d3e] mt-1 truncate tracking-tight" :title="formatCurrency(reportData.total_amount)">
                    {{ formatCurrency(reportData.total_amount) }}
                </p>
            </div>
        </div>
        <!-- Section Tabs: Customer Summary vs Trip Details -->
        <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-5 pt-3 rounded-t-xl shadow-2xs">
            <button 
                type="button" 
                @click="activeTab = 'summary'"
                :class="[
                    'pb-3 px-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2 cursor-pointer',
                    activeTab === 'summary' 
                        ? 'border-[#0064d2] text-[#0064d2]' 
                        : 'border-transparent text-slate-500 hover:text-slate-800'
                ]"
            >
                <span>Customer Consolidated Summary</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold" :class="activeTab === 'summary' ? 'bg-blue-100 text-[#0064d2]' : 'bg-slate-100 text-slate-500'">
                    {{ totalCount }} Customers
                </span>
            </button>
            <button 
                v-if="allTrips.length"
                type="button" 
                @click="activeTab = 'trips'"
                :class="[
                    'pb-3 px-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2 cursor-pointer',
                    activeTab === 'trips' 
                        ? 'border-[#0064d2] text-[#0064d2]' 
                        : 'border-transparent text-slate-500 hover:text-slate-800'
                ]"
            >
                <span>Batching / Trip Verification List</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold" :class="activeTab === 'trips' ? 'bg-blue-100 text-[#0064d2]' : 'bg-slate-100 text-slate-500'">
                    {{ tripTotalCount }} Trips
                </span>
            </button>
        </div>

        <!-- Section 1: Customer Consolidated Summary Table (Primary Tab) -->
        <div v-show="activeTab === 'summary'" class="bg-white rounded-b-xl border border-t-0 border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Customer Consolidated Summary</h3>
                    <p class="text-[10px] text-slate-400">Customer / party wise dispatches consolidated with trip count, batch size, and weights</p>
                </div>
                <span class="text-xs font-bold text-[#0064d2]">
                    Total {{ totalCount }} Customers ({{ reportData.total_trips || 0 }} Trips)
                </span>
            </div>

            <div class="overflow-x-auto max-h-[580px] overflow-y-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="sticky top-0 bg-[#f8fafc] z-10 text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 shadow-2xs">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="36%">Customer / Party Name</th>
                            <th class="py-3 px-3 text-center" width="8%">Trips</th>
                            <th class="py-3 px-3 text-right" width="14%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="18%">Total Amt</th>
                            <th class="py-3 px-3 text-center" width="20%">Statement</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in paginatedRows" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ (sanitizedPage - 1) * perPage + idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">
                                {{ row.party_name || row.customer_name || 'Unknown Customer' }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900 whitespace-nowrap">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55 whitespace-nowrap">{{ formatCurrency(row.amount_total) }}</td>
                            <td class="py-2.5 px-3 text-center">
                                <button 
                                    type="button" 
                                    @click="emit('view-statement', row.customer_id)"
                                    :disabled="!row.customer_id"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 hover:bg-blue-100 disabled:opacity-40 disabled:cursor-not-allowed text-[#0064d2] font-bold rounded text-[11px] border border-blue-200 hover:border-blue-300 transition-colors cursor-pointer shadow-2xs"
                                    title="View Patron Statement of Accounts"
                                >
                                    <DocumentTextIcon class="w-3.5 h-3.5" />
                                    <span>Statement</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!paginatedRows?.length">
                            <td colspan="6" class="py-8 text-center text-slate-400">No customer dispatches found for selected period</td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-[#f2f4f7] font-bold border-t-2 border-slate-300 text-xs shadow-2xs z-10">
                        <tr>
                            <td colspan="2" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Customer Volume</td>
                            <td class="py-3 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_trips }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatQuantity(reportData.total_quantity) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatCurrency(reportData.total_amount) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination Bar (30 rows per page) -->
            <div v-if="totalCount > perPage || totalPages > 1" class="px-4 py-3 bg-slate-50 border-t border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ startIndex }}</strong> to <strong class="text-slate-800">{{ endIndex }}</strong> of <strong class="text-slate-800">{{ totalCount }}</strong> customers
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

        <!-- Section 2: Batching / Trip Verification List (Secondary Tab) -->
        <div v-if="allTrips.length" v-show="activeTab === 'trips'" class="bg-white rounded-b-xl border border-t-0 border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Every Batching / Trip Verification List
                        </h3>
                        <span class="text-[10px] px-2 py-0.5 bg-blue-100 text-blue-800 font-bold rounded">
                            {{ tripTotalCount }} Trips
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        Itemized batch tickets and dispatches so customer can audit and verify each trip count
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[580px] overflow-y-auto">
                <table class="w-full text-left border-collapse min-w-[950px]">
                    <thead>
                        <tr class="sticky top-0 bg-[#f8fafc] z-10 text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 shadow-2xs">
                            <th class="py-3 px-3 text-center" width="4%">Trip #</th>
                            <th class="py-3 px-3" width="12%">Date & Time</th>
                            <th class="py-3 px-3" width="12%">Dispatch / DSP #</th>
                            <th class="py-3 px-3" width="15%">Customer Name</th>
                            <th class="py-3 px-3" width="13%">Unload Site</th>
                            <th class="py-3 px-3 text-center" width="9%">Mixer / Truck</th>
                            <th class="py-3 px-3 text-center" width="9%">Grade / Mix</th>
                            <th class="py-3 px-3 text-right" width="6%">Deliv (m³)</th>
                            <th class="py-3 px-3 text-right" width="5%">Empty (T)</th>
                            <th class="py-3 px-3 text-right" width="5%">Load (T)</th>
                            <th class="py-3 px-3 text-right" width="5%">Net (T)</th>
                            <th class="py-3 px-3 text-right" width="9%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(trip, tIdx) in paginatedTrips" :key="tIdx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ (sanitizedTripPage - 1) * tripPerPage + tIdx + 1 }}</td>
                            <td class="py-2.5 px-3 text-slate-600 font-mono text-[10px]">{{ trip.dispatch_time }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ trip.docket_no }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ trip.customer_name }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ trip.site_name }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ trip.truck_no }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-emerald-700">{{ trip.concrete_grade }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(trip.delivered_qty) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(trip.empty_weight) }}</td>
                            <td class="py-2.5 px-3 text-right text-slate-600">{{ formatQuantity(trip.loaded_weight) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-slate-800">{{ formatQuantity(trip.net_weight) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55 whitespace-nowrap">{{ formatCurrency(trip.amount_total) }}</td>
                        </tr>
                        <tr v-if="!paginatedTrips.length">
                            <td colspan="12" class="py-8 text-center text-slate-400">No batching trips match the search criteria</td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-[#f2f4f7] font-bold border-t-2 border-slate-300 text-xs shadow-2xs z-10">
                        <tr>
                            <td colspan="7" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Verified Batch Trips ({{ tripTotalCount }} Trips)</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatQuantity(tripTotals.delivered_qty) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatQuantity(tripTotals.empty_weight) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatQuantity(tripTotals.loaded_weight) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatQuantity(tripTotals.net_weight) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black whitespace-nowrap">{{ formatCurrency(tripTotals.amount_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Trip Pagination Bar (30 rows per page) -->
            <div v-if="tripTotalCount > tripPerPage || tripTotalPages > 1" class="px-4 py-3 bg-slate-50 border-t border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ tripStartIndex }}</strong> to <strong class="text-slate-800">{{ tripEndIndex }}</strong> of <strong class="text-slate-800">{{ tripTotalCount }}</strong> batch trips
                    </span>
                    <div class="flex items-center gap-1.5 ml-2">
                        <span class="text-[11px] text-slate-400">Rows:</span>
                        <select 
                            v-model.number="tripPerPage" 
                            @change="tripCurrentPage = 1"
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
                        @click="goToTripPage(1)" 
                        :disabled="sanitizedTripPage <= 1"
                        class="px-2 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="First Page"
                    >
                        « First
                    </button>
                    <button 
                        type="button" 
                        @click="goToTripPage(sanitizedTripPage - 1)" 
                        :disabled="sanitizedTripPage <= 1"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        ‹ Prev
                    </button>
                    
                    <template v-for="(p, pIdx) in visibleTripPages" :key="pIdx">
                        <span v-if="p === '...'" class="px-1 text-slate-400 font-semibold">...</span>
                        <button 
                            v-else 
                            type="button" 
                            @click="goToTripPage(p)"
                            :class="[
                                'px-2.5 py-1 rounded text-[11px] font-bold transition-all cursor-pointer',
                                sanitizedTripPage === p 
                                    ? 'bg-[#0064d2] text-white shadow-xs' 
                                    : 'border border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            {{ p }}
                        </button>
                    </template>

                    <button 
                        type="button" 
                        @click="goToTripPage(sanitizedTripPage + 1)" 
                        :disabled="sanitizedTripPage >= tripTotalPages"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        Next ›
                    </button>
                    <button 
                        type="button" 
                        @click="goToTripPage(tripTotalPages)" 
                        :disabled="sanitizedTripPage >= tripTotalPages"
                        class="px-2 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="Last Page"
                    >
                        Last »
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
