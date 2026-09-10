<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency } from '@/Utils/formatters';
import { 
    MagnifyingGlassIcon,
    CheckCircleIcon,
    ClockIcon,
    UserGroupIcon,
    DocumentTextIcon,
    BanknotesIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: Object
});

// Search & Filter State
const searchQuery = ref('');
const statusFilter = ref('all_outstanding'); // 'all_outstanding', 'overdue_only', 'critical_90', 'all_customers'

// Reset on new report data
watch(() => props.reportData?.transactions, () => {
    currentPage.value = 1;
});

// 1. Consolidated Customers List & Filtering
const rawCustomers = computed(() => props.reportData?.transactions || []);

const filteredCustomers = computed(() => {
    let list = [...rawCustomers.value];

    // Status / Outstanding filter
    if (statusFilter.value === 'all_outstanding') {
        list = list.filter(c => (c.total_outstanding || 0) > 0);
    } else if (statusFilter.value === 'overdue_only') {
        list = list.filter(c => (c.aging_31_60 || 0) > 0 || (c.aging_61_90 || 0) > 0 || (c.aging_90_plus || 0) > 0);
    } else if (statusFilter.value === 'critical_90') {
        list = list.filter(c => (c.aging_90_plus || 0) > 0);
    }

    // Search query
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(c => 
            (c.customer_name && c.customer_name.toLowerCase().includes(q)) ||
            (c.customer_code && c.customer_code.toLowerCase().includes(q)) ||
            (c.gstin && c.gstin.toLowerCase().includes(q)) ||
            (c.phone && c.phone.toLowerCase().includes(q)) ||
            (c.contact_person && c.contact_person.toLowerCase().includes(q))
        );
    }

    // Sort customers by name ascending
    list.sort((a, b) => (a.customer_name || '').localeCompare(b.customer_name || ''));

    return list;
});

// Pagination for Customer Table
const perPage = ref(25);
const currentPage = ref(1);

const totalCount = computed(() => filteredCustomers.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedCustomers = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return filteredCustomers.value.slice(start, start + perPage.value);
});

const startIndex = computed(() => totalCount.value === 0 ? 0 : (sanitizedPage.value - 1) * perPage.value + 1);
const endIndex = computed(() => Math.min(sanitizedPage.value * perPage.value, totalCount.value));

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

// Summary metrics from reportData
const summary = computed(() => ({
    totalOutstanding: props.reportData?.total_outstanding_amount ?? 0,
    totalInvoiced: props.reportData?.total_invoiced_amount ?? 0,
    totalReceipt: props.reportData?.total_receipt_amount ?? (props.reportData?.total_paid_amount ?? 0),
    totalPayment: props.reportData?.total_payment_amount ?? 0,
    aging0to30: props.reportData?.aging_0_30 ?? 0,
    aging31to60: props.reportData?.aging_31_60 ?? 0,
    aging61to90: props.reportData?.aging_61_90 ?? 0,
    aging90Plus: props.reportData?.aging_90_plus ?? 0,
    totalCustomers: props.reportData?.total_customers ?? 0,
    customersWithBalance: props.reportData?.customers_with_balance ?? 0,
    totalReceiptsCount: props.reportData?.total_receipts_count ?? 0,
    totalPaymentsCount: props.reportData?.total_payments_count ?? 0,
}));

// Filtered Totals for Table Footer
const filteredTotals = computed(() => {
    const list = filteredCustomers.value;
    return {
        invoiced: list.reduce((acc, c) => acc + (c.total_invoiced || 0), 0),
        receipt: list.reduce((acc, c) => acc + (c.total_receipt || c.total_paid || 0), 0),
        payment: list.reduce((acc, c) => acc + (c.total_payment || 0), 0),
        outstanding: list.reduce((acc, c) => acc + (c.total_outstanding || 0), 0),
        aging0to30: list.reduce((acc, c) => acc + (c.aging_0_30 || 0), 0),
        aging31to60: list.reduce((acc, c) => acc + (c.aging_31_60 || 0), 0),
        aging61to90: list.reduce((acc, c) => acc + (c.aging_61_90 || 0), 0),
        aging90Plus: list.reduce((acc, c) => acc + (c.aging_90_plus || 0), 0),
    };
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. KPI Metric Dashboard -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Outstanding Card -->
            <div class="bg-gradient-to-br from-rose-50 via-white to-white p-4 rounded-xl border border-rose-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Total Outstanding</span>
                    <span class="p-1.5 bg-rose-100 text-rose-700 rounded-lg">
                        <BanknotesIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-rose-800 tracking-tight">
                        {{ formatCurrency(summary.totalOutstanding) }}
                    </div>
                    <p class="text-[11px] text-rose-600 font-medium mt-0.5">
                        {{ summary.customersWithBalance }} of {{ summary.totalCustomers }} customers with open balance
                    </p>
                </div>
            </div>

            <!-- Total Invoiced / Billed -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Invoiced</span>
                    <span class="p-1.5 bg-slate-100 text-slate-600 rounded-lg">
                        <DocumentTextIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-[#1d2d3e] tracking-tight">
                        {{ formatCurrency(summary.totalInvoiced) }}
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                        Gross sales billed
                    </p>
                </div>
            </div>

            <!-- Total Receipts Collected -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Total Receipts</span>
                    <span class="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg">
                        <CheckCircleIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-emerald-700 tracking-tight">
                        {{ formatCurrency(summary.totalReceipt) }}
                    </div>
                    <p class="text-[11px] text-emerald-600 font-medium mt-0.5">
                        Total collections received
                    </p>
                </div>
            </div>

            <!-- Total Payments / Refunds -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-700">Total Payments</span>
                    <span class="p-1.5 bg-indigo-100 text-indigo-700 rounded-lg">
                        <ClockIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-indigo-800 tracking-tight">
                        {{ formatCurrency(summary.totalPayment) }}
                    </div>
                    <p class="text-[11px] text-indigo-600 font-medium mt-0.5">
                        Customer refunds / disbursements
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Aging Distribution Bar Cards -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Accounts Receivable Aging Breakdown</h4>
                    <span class="text-[10px] text-slate-400 font-medium">Calculated from invoice due date</span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <!-- 0-30 Days -->
                <div class="bg-blue-50/60 border border-blue-200/80 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase text-blue-700 tracking-wider">0 - 30 Days</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold">Current</span>
                    </div>
                    <div class="text-base font-bold text-blue-900 mt-1">
                        {{ formatCurrency(summary.aging0to30) }}
                    </div>
                </div>

                <!-- 31-60 Days -->
                <div class="bg-amber-50/60 border border-amber-200/80 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase text-amber-700 tracking-wider">31 - 60 Days</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">Overdue</span>
                    </div>
                    <div class="text-base font-bold text-amber-900 mt-1">
                        {{ formatCurrency(summary.aging31to60) }}
                    </div>
                </div>

                <!-- 61-90 Days -->
                <div class="bg-orange-50/60 border border-orange-200/80 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase text-orange-700 tracking-wider">61 - 90 Days</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-orange-100 text-orange-800 font-semibold">Attention</span>
                    </div>
                    <div class="text-base font-bold text-orange-900 mt-1">
                        {{ formatCurrency(summary.aging61to90) }}
                    </div>
                </div>

                <!-- 90+ Days -->
                <div class="bg-rose-50/70 border border-rose-200 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase text-rose-700 tracking-wider">90+ Days</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-rose-200 text-rose-900 font-bold">Critical</span>
                    </div>
                    <div class="text-base font-bold text-rose-800 mt-1">
                        {{ formatCurrency(summary.aging90Plus) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Customer Outstanding Summary Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs">
            <!-- Header with Title & Filter Controls -->
            <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-blue-50 text-[#0064d2] rounded-lg">
                        <UserGroupIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Customer Outstanding Summary</h3>
                        <p class="text-[11px] text-slate-400 font-medium">
                            Showing {{ filteredCustomers.length }} customer account{{ filteredCustomers.length === 1 ? '' : 's' }}
                        </p>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Filter Dropdown -->
                    <select 
                        v-model="statusFilter"
                        class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-slate-50 font-medium focus:outline-none focus:ring-1 focus:ring-[#0064d2]"
                    >
                        <option value="all_outstanding">With Balance Only (> 0)</option>
                        <option value="overdue_only">Overdue Only (> 30 Days)</option>
                        <option value="critical_90">Critical Only (90+ Days)</option>
                        <option value="all_customers">All Customers</option>
                    </select>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-60">
                        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" />
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            @input="currentPage = 1"
                            placeholder="Search customer, code, phone..." 
                            class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0064d2] bg-white"
                        />
                    </div>
                </div>
            </div>

            <!-- Customer Summary Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1100px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="4%">#</th>
                            <th class="py-3 px-3" width="9%">Code</th>
                            <th class="py-3 px-3" width="22%">Customer Name</th>
                            <th class="py-3 px-3 text-right text-slate-700" width="10%">Total Invoiced</th>
                            <th class="py-3 px-3 text-right text-emerald-700" width="10%">Total Receipts</th>
                            <th class="py-3 px-3 text-right text-indigo-700" width="9%">Total Payments</th>
                            <th class="py-3 px-3 text-right text-rose-700" width="12%">Outstanding Balance</th>
                            <th class="py-3 px-3 text-right text-blue-700" width="6%">0-30d</th>
                            <th class="py-3 px-3 text-right text-amber-700" width="6%">31-60d</th>
                            <th class="py-3 px-3 text-right text-orange-700" width="6%">61-90d</th>
                            <th class="py-3 px-3 text-right text-rose-700" width="6%">90+d</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-slate-100">
                        <tr 
                            v-for="(c, cIdx) in paginatedCustomers" 
                            :key="c.customer_id"
                            class="hover:bg-slate-50/80 transition-colors"
                        >
                            <td class="py-3 px-3 text-center text-slate-400 font-bold">
                                {{ (sanitizedPage - 1) * perPage + cIdx + 1 }}
                            </td>
                            <td class="py-3 px-3 font-semibold text-slate-600 font-mono text-[11px]">
                                {{ c.customer_code }}
                            </td>
                            <td class="py-3 px-3 font-bold text-slate-800">
                                <div>{{ c.customer_name }}</div>
                                <div class="text-[10px] text-slate-400 font-normal flex items-center gap-2 mt-0.5">
                                    <span v-if="c.gstin && c.gstin !== '-'">GST: {{ c.gstin }}</span>
                                    <span v-if="c.phone && c.phone !== '-'">Ph: {{ c.phone }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-right font-medium text-slate-700">
                                {{ formatCurrency(c.total_invoiced) }}
                            </td>
                            <td class="py-3 px-3 text-right font-medium text-emerald-700">
                                {{ formatCurrency(c.total_receipt || c.total_paid) }}
                            </td>
                            <td class="py-3 px-3 text-right font-medium text-indigo-700">
                                {{ formatCurrency(c.total_payment || 0) }}
                            </td>
                            <td class="py-3 px-3 text-right font-bold" :class="c.total_outstanding > 0 ? 'text-rose-700 bg-rose-50/20' : 'text-slate-700'">
                                {{ formatCurrency(c.total_outstanding) }}
                            </td>
                            <td class="py-3 px-3 text-right font-semibold text-blue-800 text-[11px]">
                                {{ c.aging_0_30 > 0 ? formatCurrency(c.aging_0_30) : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-semibold text-amber-800 text-[11px]">
                                {{ c.aging_31_60 > 0 ? formatCurrency(c.aging_31_60) : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-semibold text-orange-800 text-[11px]">
                                {{ c.aging_61_90 > 0 ? formatCurrency(c.aging_61_90) : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-bold text-rose-700 text-[11px]">
                                {{ c.aging_90_plus > 0 ? formatCurrency(c.aging_90_plus) : '-' }}
                            </td>
                        </tr>

                        <tr v-if="!paginatedCustomers.length">
                            <td colspan="11" class="py-12 text-center text-slate-400">
                                <CheckCircleIcon class="w-8 h-8 mx-auto text-emerald-400 mb-2" />
                                No customer outstanding records found matching your filters.
                            </td>
                        </tr>
                    </tbody>

                    <!-- Table Footer: Column Totals -->
                    <tfoot v-if="filteredCustomers.length > 0" class="bg-slate-100 font-bold text-xs border-t-2 border-slate-300">
                        <tr>
                            <td colspan="3" class="py-3 px-3 text-slate-800 uppercase text-[11px]">
                                Summary Totals ({{ filteredCustomers.length }} Customers)
                            </td>
                            <td class="py-3 px-3 text-right text-slate-800">
                                {{ formatCurrency(filteredTotals.invoiced) }}
                            </td>
                            <td class="py-3 px-3 text-right text-emerald-700">
                                {{ formatCurrency(filteredTotals.receipt) }}
                            </td>
                            <td class="py-3 px-3 text-right text-indigo-700">
                                {{ formatCurrency(filteredTotals.payment) }}
                            </td>
                            <td class="py-3 px-3 text-right text-rose-800">
                                {{ formatCurrency(filteredTotals.outstanding) }}
                            </td>
                            <td class="py-3 px-3 text-right text-blue-900 text-[11px]">
                                {{ formatCurrency(filteredTotals.aging0to30) }}
                            </td>
                            <td class="py-3 px-3 text-right text-amber-900 text-[11px]">
                                {{ formatCurrency(filteredTotals.aging31to60) }}
                            </td>
                            <td class="py-3 px-3 text-right text-orange-900 text-[11px]">
                                {{ formatCurrency(filteredTotals.aging61to90) }}
                            </td>
                            <td class="py-3 px-3 text-right text-rose-800 text-[11px]">
                                {{ formatCurrency(filteredTotals.aging90Plus) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalCount > perPage || totalPages > 1" class="px-4 py-3 bg-slate-50 border-t border-slate-200 rounded-b flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-slate-500 font-medium">
                    <span>
                        Showing <strong class="text-slate-800">{{ startIndex }}</strong> to <strong class="text-slate-800">{{ endIndex }}</strong> of <strong class="text-slate-800">{{ totalCount }}</strong> customers
                    </span>
                    <span class="text-slate-300">|</span>
                    <label class="flex items-center gap-1.5">
                        <span>Per page:</span>
                        <select 
                            v-model="perPage" 
                            @change="currentPage = 1"
                            class="border border-slate-200 rounded px-1.5 py-0.5 text-xs bg-white text-slate-700 focus:outline-none"
                        >
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </label>
                </div>

                <div class="flex items-center gap-1">
                    <button 
                        type="button" 
                        @click="goToPage(1)" 
                        :disabled="sanitizedPage <= 1"
                        class="px-2 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                        title="First Page"
                    >
                        «
                    </button>
                    <button 
                        type="button" 
                        @click="goToPage(sanitizedPage - 1)" 
                        :disabled="sanitizedPage <= 1"
                        class="px-2.5 py-1 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer"
                    >
                        ‹ Prev
                    </button>

                    <template v-for="(p, idx) in visiblePages" :key="idx">
                        <span v-if="p === '...'" class="px-2 py-1 text-slate-400">...</span>
                        <button 
                            v-else 
                            type="button" 
                            @click="goToPage(p)"
                            :class="[
                                'px-2.5 py-1 rounded font-semibold text-[11px] cursor-pointer transition-colors',
                                sanitizedPage === p 
                                    ? 'bg-[#0064d2] text-white' 
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
                        »
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
