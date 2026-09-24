<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';

const props = defineProps({
    reportData: Object
});

const perPage = ref(25);
const currentPage = ref(1);
const searchQuery = ref('');
const selectedType = ref('ALL');

const transactions = computed(() => props.reportData?.transactions || props.reportData?.items || []);

// Distinct entity types available
const entityTypes = computed(() => {
    const counts = props.reportData?.type_counts || {};
    const list = Object.keys(counts).map(type => ({
        type,
        count: counts[type]
    }));
    return list;
});

const filteredList = computed(() => {
    let list = transactions.value;

    if (selectedType.value !== 'ALL') {
        list = list.filter(item => item.entity_type === selectedType.value);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(item =>
            (item.reference_no && item.reference_no.toLowerCase().includes(q)) ||
            (item.customer_name && item.customer_name.toLowerCase().includes(q)) ||
            (item.entity_type && item.entity_type.toLowerCase().includes(q)) ||
            (item.deleted_by && item.deleted_by.toLowerCase().includes(q)) ||
            (item.notes && item.notes.toLowerCase().includes(q)) ||
            (item.deleted_at && item.deleted_at.toLowerCase().includes(q)) ||
            (item.original_date && item.original_date.toLowerCase().includes(q))
        );
    }

    return list;
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

const filteredTotalAmount = computed(() => {
    return filteredList.value.reduce((acc, row) => acc + (Number(row.amount) || 0), 0);
});

watch([transactions, searchQuery, selectedType], () => {
    currentPage.value = 1;
});

const getTypeBadgeClass = (type) => {
    switch (type?.toLowerCase().replace(/[- ]/g, '')) {
        case 'invoice':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'bill':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'payment':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'receipt':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'batch':
            return 'bg-cyan-50 text-cyan-700 border-cyan-200';
        case 'dispatch':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'expense':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'ewaybill':
            return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        case 'journalentry':
        case 'journal':
            return 'bg-violet-50 text-violet-700 border-violet-200';
        case 'discount':
            return 'bg-pink-50 text-pink-700 border-pink-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Overview summary banner -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">Total Deleted Records</span>
                <p class="text-md font-black text-rose-700 mt-1">{{ reportData.total_deleted ?? transactions.length }}
                </p>
                <span class="text-xs text-slate-400 mt-1 block">Across all audited modules</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Deleted Value</span>
                <p class="text-md font-black text-slate-800 mt-1">{{ formatCurrency(reportData.total_amount) }}</p>
                <span class="text-xs text-slate-400 mt-1 block">Cumulative value of deleted entries</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-500">Filtered Records</span>
                <p class="text-md font-black text-blue-700 mt-1">{{ totalRows }}</p>
                <span class="text-xs text-slate-400 mt-1 block">Matching current filters</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-500">Filtered Value</span>
                <p class="text-md font-black text-emerald-700 mt-1">{{ formatCurrency(filteredTotalAmount) }}</p>
                <span class="text-xs text-slate-400 mt-1 block">For selected type/search</span>
            </div>
        </div>

        <!-- Entity Type Quick Filter Pills -->
        <div class="flex flex-wrap items-center gap-2 bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 mr-2 flex items-center gap-1.5">
                <i class="pi pi-filter text-xs"></i>
                Type Filter:
            </span>
            <button @click="selectedType = 'ALL'" :class="[
                'text-xs font-semibold px-3 py-1 rounded-lg border transition-all',
                selectedType === 'ALL'
                    ? 'bg-rose-600 text-white border-rose-600 shadow-sm'
                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
            ]">
                All ({{ transactions.length }})
            </button>
            <button v-for="et in entityTypes" :key="et.type" @click="selectedType = et.type" :class="[
                'text-xs font-semibold px-3 py-1 rounded-lg border transition-all flex items-center gap-1.5',
                selectedType === et.type
                    ? 'bg-rose-600 text-white border-rose-600 shadow-sm'
                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
            ]">
                <span>{{ et.type }}</span>
                <span :class="[
                    'text-[10px] px-1.5 py-0.2 rounded-full font-bold',
                    selectedType === et.type ? 'bg-rose-700 text-white' : 'bg-slate-200 text-slate-700'
                ]">
                    {{ et.count }}
                </span>
            </button>
        </div>

        <!-- Deleted Report Table Card -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div
                class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-rose-700 flex items-center gap-2">
                        <i class="pi pi-trash text-rose-600"></i>
                        Deleted Records Audit Log
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Log of deleted invoices, bills, payments, receipts, batches, dispatches, expenses, e-way bills,
                        journals & discounts.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <input v-model="searchQuery" type="text" placeholder="Search ref #, customer, user..."
                            class="text-xs rounded-lg border border-slate-300 pl-8 pr-3 py-1.5 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none w-64" />
                        <!-- <i class="pi pi-search absolute left-2.5 top-2.5 text-slate-400 text-xs"></i> -->
                    </div>
                    <span
                        class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200">
                        {{ totalRows }} Records
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-100/70 text-slate-600 uppercase font-black tracking-wider text-[10px]">
                            <th class="py-3 px-4 w-12 text-center">#</th>
                            <th class="py-3 px-4 w-28">Entity Type</th>
                            <th class="py-3 px-4 w-36">Doc / Ref #</th>
                            <th class="py-3 px-4">Customer / Party</th>
                            <th class="py-3 px-4 w-28">Original Date</th>
                            <th class="py-3 px-4 w-36">Deleted At</th>
                            <th class="py-3 px-4 w-32">Deleted By</th>
                            <th class="py-3 px-4 w-32 text-right">Amount (₹) / Qty</th>
                            <th class="py-3 px-4">Details / Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in paginatedList" :key="row.id"
                            class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 text-center text-slate-400 font-mono">
                                {{ (sanitizedPage - 1) * perPage + idx + 1 }}
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    :class="['px-2 py-0.5 rounded-md text-[10px] font-bold border uppercase tracking-wider inline-block', getTypeBadgeClass(row.entity_type)]">
                                    {{ row.entity_type }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800 font-mono">
                                {{ row.reference_no }}
                            </td>
                            <td class="py-3 px-4">
                                <strong class="text-slate-800">{{ row.customer_name }}</strong>
                            </td>
                            <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                                {{ row.original_date }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-semibold text-rose-600">{{ row.deleted_at }}</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px] font-medium border border-slate-200">
                                    <i class="pi pi-user text-[10px] text-slate-400"></i>
                                    {{ row.deleted_by || 'System' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-slate-800 whitespace-nowrap">
                                {{ Number(row.amount).toLocaleString('en-IN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) }}
                            </td>
                            <td class="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate" :title="row.notes">
                                {{ row.notes || '-' }}
                            </td>
                        </tr>
                        <tr v-if="paginatedList.length === 0">
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <i class="pi pi-inbox text-3xl text-slate-300"></i>
                                    <p class="text-sm font-semibold">No deleted records found matching current criteria.
                                    </p>
                                    <p class="text-xs text-slate-400">Try adjusting the customer filter or date range.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="filteredList.length > 0"
                        class="bg-slate-50 font-bold border-t-2 border-slate-200 text-slate-700">
                        <tr>
                            <td colspan="7" class="py-3 px-4 text-right uppercase tracking-wider text-[11px]">
                                Filtered Total ({{ filteredList.length }} records):
                            </td>
                            <td class="py-3 px-4 text-right text-sm font-black text-rose-700">
                                {{ Number(filteredTotalAmount).toLocaleString('en-IN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                class="px-5 py-3 border-t border-slate-100 bg-slate-50/40 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span>Rows per page:</span>
                    <select v-model="perPage"
                        class="rounded border border-slate-300 text-xs py-1 px-2 bg-white focus:border-rose-500 outline-none">
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>
                        Showing {{ totalRows > 0 ? (sanitizedPage - 1) * perPage + 1 : 0 }} - {{ Math.min(sanitizedPage
                            * perPage, totalRows) }} of {{ totalRows }} records
                    </span>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="sanitizedPage <= 1"
                        class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-100">
                        Previous
                    </button>
                    <span class="px-3 py-1 font-bold text-slate-700">
                        Page {{ sanitizedPage }} of {{ totalPages }}
                    </span>
                    <button @click="currentPage = Math.min(totalPages, currentPage + 1)"
                        :disabled="sanitizedPage >= totalPages"
                        class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-100">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
