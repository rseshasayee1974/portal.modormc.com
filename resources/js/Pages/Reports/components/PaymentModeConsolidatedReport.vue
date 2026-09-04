<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: {
        type: Object,
        default: () => ({})
    }
});

// 1. Consolidated Summary Rows
const transactions = computed(() => props.reportData?.transactions || props.reportData?.items || []);

// 2. Batching / Trip Verification List
const tripPerPage = ref(30);
const tripCurrentPage = ref(1);
const tripSearch = ref('');
const selectedModeFilter = ref('');

watch(() => props.reportData, () => {
    tripCurrentPage.value = 1;
    selectedModeFilter.value = '';
    tripSearch.value = '';
});

const allTrips = computed(() => props.reportData?.batch_dispatches || []);

const modeOptions = computed(() => {
    const set = new Set();
    allTrips.value.forEach(t => {
        if (t.payment_mode) set.add(t.payment_mode);
    });
    return Array.from(set).sort();
});

const filteredTrips = computed(() => {
    let list = [...allTrips.value];
    if (selectedModeFilter.value) {
        list = list.filter(t => t.payment_mode === selectedModeFilter.value);
    }
    if (tripSearch.value.trim()) {
        const q = tripSearch.value.trim().toLowerCase();
        list = list.filter(t => 
            (t.payment_mode && t.payment_mode.toLowerCase().includes(q)) ||
            (t.customer_name && t.customer_name.toLowerCase().includes(q)) ||
            (t.docket_no && t.docket_no.toLowerCase().includes(q)) ||
            (t.dispatch_no && t.dispatch_no.toLowerCase().includes(q)) ||
            (t.truck_no && t.truck_no.toLowerCase().includes(q)) ||
            (t.site_name && t.site_name.toLowerCase().includes(q)) ||
            (t.concrete_grade && t.concrete_grade.toLowerCase().includes(q)) ||
            (t.mix_name && t.mix_name.toLowerCase().includes(q))
        );
    }
    list.sort((a, b) => {
        const timeA = a.dispatch_timestamp ? new Date(a.dispatch_timestamp).getTime() : (a.dispatch_id || 0);
        const timeB = b.dispatch_timestamp ? new Date(b.dispatch_timestamp).getTime() : (b.dispatch_id || 0);
        return timeA - timeB;
    });
    return list;
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
        delivered_qty: list.reduce((acc, t) => acc + (t.delivered_qty || t.quantity || 0), 0),
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

        <!-- Section 1: Payment Mode Consolidated Summary -->
        <div class="bg-white rounded border border-slate-200 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Payment Mode Consolidated Summary
                        </h3>
                        <span class="text-[10px] px-2.5 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded">
                            {{ transactions.length }} Modes
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        Consolidated by payment terms and settlement mode with batch size and delivered volume
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[750px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="5%">#</th>
                            <th class="py-3 px-3" width="40%">Payment Mode</th>
                            <th class="py-3 px-3 text-center" width="13%">Trips</th>
                            <!-- <th class="py-3 px-3 text-right" width="14%">Batch Size</th> -->
                            <th class="py-3 px-3 text-right" width="14%">Delivered Qty</th>
                            <th class="py-3 px-3 text-right" width="14%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(row, idx) in transactions" :key="idx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ row.payment_mode }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-bold">{{ row.trips_count }}</td>
                            <!-- <td class="py-2.5 px-3 text-right text-slate-700">{{ formatQuantity(row.batch_size) }}</td> -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                        </tr>
                        <tr v-if="!transactions.length">
                            <td colspan="6" class="py-8 text-center text-slate-400">No payment mode dispatch records found for selected period</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="2" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Payment Modes</td>
                            <td class="py-3 px-3 text-center text-[#1d2d3e] font-black">{{ reportData.total_trips }}</td>
                            <!-- <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_batch_size) }}</td> -->
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(reportData.total_quantity) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Payment Mode Batching / Trip Verification List -->
        <div v-if="allTrips.length" class="bg-white rounded border border-slate-200 shadow-sm mt-8">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Every Batching / Trip Verification List
                        </h3>
                        <span class="text-[10px] px-2.5 py-0.5 bg-blue-100 text-blue-800 font-bold rounded">
                            {{ tripTotalCount }} Trips
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        Itemized payment mode trip dispatch tickets for audit and settlement verification
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1000px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="4%">Trip #</th>
                            <th class="py-3 px-3" width="12%">Date & Time</th>
                            <th class="py-3 px-3" width="13%">Dispatch / DSP #</th>
                            <th class="py-3 px-3 text-center" width="12%">Payment Mode</th>
                            <th class="py-3 px-3" width="15%">Customer Name</th>
                            <th class="py-3 px-3" width="14%">Unload Site</th>
                            <th class="py-3 px-3 text-center" width="10%">Truck / Mixer</th>
                            <th class="py-3 px-3 text-center" width="12%">Grade / Mix</th>
                            <th class="py-3 px-3 text-right" width="6%">Deliv (m³)</th>
                            <th class="py-3 px-3 text-right" width="8%">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-for="(trip, tIdx) in paginatedTrips" :key="tIdx" class="hover:bg-slate-50 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ (sanitizedTripPage - 1) * tripPerPage + tIdx + 1 }}</td>
                            <td class="py-2.5 px-3 text-slate-600 font-mono text-[10px]">{{ trip.dispatch_time }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ trip.docket_no || trip.dispatch_no }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-emerald-700">{{ trip.payment_mode }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800 truncate max-w-[180px]" :title="trip.customer_name">{{ trip.customer_name }}</td>
                            <td class="py-2.5 px-3 text-slate-600 truncate max-w-[150px]" :title="trip.site_name">{{ trip.site_name }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-700">{{ trip.truck_no }}</td>
                            <td class="py-2.5 px-3 text-center font-bold text-indigo-600">{{ trip.concrete_grade || trip.mix_name }}</td>
                            <td class="py-2.5 px-3 text-right font-black text-slate-900">{{ formatQuantity(trip.delivered_qty || trip.quantity) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(trip.amount_total) }}</td>
                        </tr>
                        <tr v-if="!paginatedTrips.length">
                            <td colspan="10" class="py-8 text-center text-slate-400">No payment mode trips match the filter criteria</td>
                        </tr>
                        <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                            <td colspan="8" class="py-3 px-3 text-center text-[#1d2d3e] uppercase">Total Batch Dispatches ({{ tripTotalCount }} Trips)</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatQuantity(tripTotals.delivered_qty) }}</td>
                            <td class="py-3 px-3 text-right text-[#1d2d3e] font-black">{{ formatCurrency(tripTotals.amount_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Trip Pagination Bar -->
            <div v-if="tripTotalCount > tripPerPage || tripTotalPages > 1" class="px-4 py-3 bg-slate-50 border-t border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ tripStartIndex }}</strong> to <strong class="text-slate-800">{{ tripEndIndex }}</strong> of <strong class="text-slate-800">{{ tripTotalCount }}</strong> trips
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
