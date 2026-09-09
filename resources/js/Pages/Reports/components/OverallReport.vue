<script setup>
import { ref, computed } from 'vue';
import { formatCurrency, formatQuantity } from '@/Utils/formatters';
import { 
    BanknotesIcon,
    TruckIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    CubeIcon,
    ClipboardDocumentListIcon,
    CurrencyRupeeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ShieldCheckIcon,
    MagnifyingGlassIcon,
    WrenchScrewdriverIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: Object
});

const activeSection = ref('operations'); // 'operations', 'invoicing', 'cash_flow', 'pipeline', 'inward'

// Quick Search
const dispatchSearch = ref('');
const invoiceSearch = ref('');
const voucherSearch = ref('');

// Summary metrics shortcuts
const summary = computed(() => props.reportData?.executive_summary || {});
const dispatches = computed(() => props.reportData?.dispatches || { list: [] });
const production = computed(() => props.reportData?.production || { list: [], grade_wise: [] });
const pumpOps = computed(() => props.reportData?.pump_operations || { list: [] });
const invoicing = computed(() => props.reportData?.invoicing || { list: [], purchase_bills_list: [] });
const cashFlow = computed(() => props.reportData?.cash_flow || { receipts_list: [], payments_list: [] });
const taxes = computed(() => props.reportData?.taxes || {});
const pipeline = computed(() => props.reportData?.sales_pipeline || { quotations_list: [], sales_orders_list: [] });
const procurement = computed(() => props.reportData?.procurement || { list: [] });

// Filtered Lists
const filteredDispatches = computed(() => {
    let list = dispatches.value.list || [];
    if (dispatchSearch.value.trim()) {
        const q = dispatchSearch.value.trim().toLowerCase();
        list = list.filter(d => 
            (d.docket_no && d.docket_no.toLowerCase().includes(q)) ||
            (d.customer_name && d.customer_name.toLowerCase().includes(q)) ||
            (d.site_name && d.site_name.toLowerCase().includes(q)) ||
            (d.truck_no && d.truck_no.toLowerCase().includes(q)) ||
            (d.driver_name && d.driver_name.toLowerCase().includes(q))
        );
    }
    return list;
});

const filteredInvoices = computed(() => {
    let list = invoicing.value.list || [];
    if (invoiceSearch.value.trim()) {
        const q = invoiceSearch.value.trim().toLowerCase();
        list = list.filter(inv => 
            (inv.invoice_no && inv.invoice_no.toLowerCase().includes(q)) ||
            (inv.customer_name && inv.customer_name.toLowerCase().includes(q)) ||
            (inv.gstin && inv.gstin.toLowerCase().includes(q))
        );
    }
    return list;
});

const filteredReceipts = computed(() => {
    let list = cashFlow.value.receipts_list || [];
    if (voucherSearch.value.trim()) {
        const q = voucherSearch.value.trim().toLowerCase();
        list = list.filter(r => 
            (r.voucher_no && r.voucher_no.toLowerCase().includes(q)) ||
            (r.customer && r.customer.toLowerCase().includes(q)) ||
            (r.account && r.account.toLowerCase().includes(q))
        );
    }
    return list;
});

const filteredPayments = computed(() => {
    let list = cashFlow.value.payments_list || [];
    if (voucherSearch.value.trim()) {
        const q = voucherSearch.value.trim().toLowerCase();
        list = list.filter(p => 
            (p.voucher_no && p.voucher_no.toLowerCase().includes(q)) ||
            (p.beneficiary && p.beneficiary.toLowerCase().includes(q)) ||
            (p.account && p.account.toLowerCase().includes(q))
        );
    }
    return list;
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Executive Flash Scorecard (Hero Metric Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Gross Billed Revenue -->
            <div class="bg-gradient-to-br from-blue-50/80 via-white to-white p-4 rounded-xl border border-blue-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Gross Billed Sales</span>
                    <span class="p-1.5 bg-blue-100 text-blue-700 rounded-lg">
                        <DocumentTextIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-[#1d2d3e] tracking-tight">
                        {{ formatCurrency(summary.sales_revenue) }}
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium mt-1">
                        <span>{{ invoicing.sales_invoices_count || 0 }} Invoices issued</span>
                        <span v-if="summary.credit_sales_balance > 0" class="text-rose-600 font-bold">
                            Due: {{ formatCurrency(summary.credit_sales_balance) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Total Collections / Receipts -->
            <div class="bg-gradient-to-br from-emerald-50/80 via-white to-white p-4 rounded-xl border border-emerald-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Total Collections</span>
                    <span class="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg">
                        <ArrowTrendingUpIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-emerald-800 tracking-tight">
                        {{ formatCurrency(summary.total_receipts_collected) }}
                    </div>
                    <div class="flex items-center gap-3 text-[10.5px] font-semibold text-emerald-600 mt-1">
                        <span>Cash: {{ formatCurrency(summary.cash_receipts) }}</span>
                        <span>•</span>
                        <span>Bank: {{ formatCurrency(summary.bank_receipts) }}</span>
                    </div>
                </div>
            </div>

            <!-- Total Payments / Outflows -->
            <div class="bg-gradient-to-br from-amber-50/80 via-white to-white p-4 rounded-xl border border-amber-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Total Payments</span>
                    <span class="p-1.5 bg-amber-100 text-amber-800 rounded-lg">
                        <ArrowTrendingDownIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black text-amber-900 tracking-tight">
                        {{ formatCurrency(summary.total_payments_made) }}
                    </div>
                    <div class="flex items-center gap-3 text-[10.5px] font-semibold text-amber-700 mt-1">
                        <span>Cash: {{ formatCurrency(summary.cash_payments) }}</span>
                        <span>•</span>
                        <span>Bank: {{ formatCurrency(summary.bank_payments) }}</span>
                    </div>
                </div>
            </div>

            <!-- Net Daily Cash Flow -->
            <div :class="[
                'p-4 rounded-xl border shadow-xs flex flex-col justify-between',
                summary.net_cash_flow >= 0 ? 'bg-emerald-50/40 border-emerald-200' : 'bg-rose-50/40 border-rose-200'
            ]">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider" :class="summary.net_cash_flow >= 0 ? 'text-emerald-800' : 'text-rose-800'">
                        Net Daily Cash Flow
                    </span>
                    <span class="p-1.5 rounded-lg" :class="summary.net_cash_flow >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                        <BanknotesIcon class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-xl sm:text-2xl font-black tracking-tight" :class="summary.net_cash_flow >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                        {{ formatCurrency(summary.net_cash_flow) }}
                    </div>
                    <p class="text-[11px] font-medium mt-1 text-slate-500">
                        Total Receipts minus Outflows
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Operational Flash Scorecard (Volume, Fleet, Pump & Compliance) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Dispatched Qty -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Dispatched Qty</span>
                <div class="text-lg font-black text-indigo-700 mt-1">
                    {{ formatQuantity(summary.total_dispatched_volume) }} <span class="text-xs font-semibold text-slate-500">m³</span>
                </div>
                <span class="text-[10px] text-slate-500 font-semibold">{{ summary.total_dispatches_count }} trips</span>
            </div>

            <!-- Batches Produced -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Batch Produced</span>
                <div class="text-lg font-black text-slate-800 mt-1">
                    {{ formatQuantity(summary.total_batches_produced) }} <span class="text-xs font-semibold text-slate-500">m³</span>
                </div>
                <span class="text-[10px] text-slate-500 font-semibold">{{ summary.total_batches_count }} batches</span>
            </div>

            <!-- Pump Volume -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Pump Scheduled</span>
                <div class="text-lg font-black text-cyan-700 mt-1">
                    {{ formatQuantity(summary.total_pump_volume) }} <span class="text-xs font-semibold text-slate-500">m³</span>
                </div>
                <span class="text-[10px] text-slate-500 font-semibold">{{ summary.pump_deployments_count }} deployments</span>
            </div>

            <!-- Pump Charges -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Pump Charges</span>
                <div class="text-base font-black text-slate-800 mt-1 truncate" :title="formatCurrency(summary.pump_charges_billed)">
                    {{ formatCurrency(summary.pump_charges_billed) }}
                </div>
                <span class="text-[10px] text-slate-500 font-semibold">Billed on dispatches</span>
            </div>

            <!-- Hire / Shipping Charges -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Hire / Freight</span>
                <div class="text-base font-black text-slate-800 mt-1 truncate" :title="formatCurrency(summary.hire_charges_billed)">
                    {{ formatCurrency(summary.hire_charges_billed) }}
                </div>
                <span class="text-[10px] text-slate-500 font-semibold">Transport / hire charge</span>
            </div>

            <!-- Net GST Liability -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Net GST Payable</span>
                <div class="text-base font-black text-slate-800 mt-1 truncate" :title="formatCurrency(summary.net_tax_liability)">
                    {{ formatCurrency(summary.net_tax_liability) }}
                </div>
                <span class="text-[10px] text-slate-500 font-semibold">Output - Input GST</span>
            </div>
        </div>

        <!-- 3. Domain Deep-Dive Container with Interactive Navigation -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <!-- Section Tab Bar -->
            <div class="px-4 py-3 bg-slate-50/70 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button 
                        type="button" 
                        @click="activeSection = 'operations'"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                            activeSection === 'operations' ? 'bg-[#0064d2] text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                        ]"
                    >
                        <TruckIcon class="w-3.5 h-3.5" />
                        Production & Dispatches ({{ dispatches.dispatches_count }})
                    </button>

                    <button 
                        type="button" 
                        @click="activeSection = 'invoicing'"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                            activeSection === 'invoicing' ? 'bg-[#0064d2] text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                        ]"
                    >
                        <DocumentTextIcon class="w-3.5 h-3.5" />
                        Invoices & Billing ({{ invoicing.sales_invoices_count }})
                    </button>

                    <button 
                        type="button" 
                        @click="activeSection = 'cash_flow'"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                            activeSection === 'cash_flow' ? 'bg-[#0064d2] text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                        ]"
                    >
                        <CurrencyRupeeIcon class="w-3.5 h-3.5" />
                        Collections & Day Book
                    </button>

                    <button 
                        type="button" 
                        @click="activeSection = 'pipeline'"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                            activeSection === 'pipeline' ? 'bg-[#0064d2] text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                        ]"
                    >
                        <ClipboardDocumentListIcon class="w-3.5 h-3.5" />
                        Quotations & Orders ({{ pipeline.sales_orders_count }})
                    </button>

                    <button 
                        type="button" 
                        @click="activeSection = 'inward'"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                            activeSection === 'inward' ? 'bg-[#0064d2] text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                        ]"
                    >
                        <CubeIcon class="w-3.5 h-3.5" />
                        Inwards ({{ procurement.inwards_count }})
                    </button>
                </div>

                <!-- Compliance Pills -->
                <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-600">
                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                        <ShieldCheckIcon class="w-3.5 h-3.5" />
                        {{ summary.einvoices_count || 0 }} E-Invoices
                    </span>
                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 flex items-center gap-1">
                        <TruckIcon class="w-3.5 h-3.5" />
                        {{ summary.ewaybills_count || 0 }} E-Way Bills
                    </span>
                </div>
            </div>

            <!-- 4. SECTION 1: OPERATIONS & DISPATCHES -->
            <div v-if="activeSection === 'operations'" class="p-4 space-y-6">
                <!-- Search & Filters -->
                <div class="flex items-center justify-between gap-3">
                    <div class="relative w-72">
                        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" />
                        <input 
                            type="text" 
                            v-model="dispatchSearch" 
                            placeholder="Search docket, customer, mixer, site..." 
                            class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0064d2]"
                        />
                    </div>

                    <div class="text-xs font-bold text-slate-700">
                        Total Volume: <span class="text-[#0064d2]">{{ formatQuantity(dispatches.delivered_qty) }} m³</span> | 
                        Trips: <span class="text-slate-900">{{ dispatches.dispatches_count }}</span> | 
                        Fleet: <span class="text-slate-900">{{ dispatches.trucks_count }} Mixers</span>
                    </div>
                </div>

                <!-- Dispatches Table -->
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-bold uppercase text-slate-600 border-b border-slate-200">
                                <th class="py-2.5 px-3 text-center" width="4%">#</th>
                                <th class="py-2.5 px-3" width="13%">Docket / DSP #</th>
                                <th class="py-2.5 px-3" width="20%">Customer Name</th>
                                <th class="py-2.5 px-3" width="15%">Unload Site</th>
                                <th class="py-2.5 px-3 text-center" width="10%">Mixer</th>
                                <th class="py-2.5 px-3" width="12%">Driver</th>
                                <th class="py-2.5 px-3 text-right" width="8%">Qty (m³)</th>
                                <th class="py-2.5 px-3 text-right" width="8%">Pump Chg</th>
                                <th class="py-2.5 px-3 text-right" width="10%">Total (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <tr v-for="d in filteredDispatches" :key="d.id" class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-2 px-3 text-center text-slate-400 font-bold">{{ d.index }}</td>
                                <td class="py-2 px-3 font-bold text-[#0064d2] font-mono">{{ d.docket_no }}</td>
                                <td class="py-2 px-3 font-bold text-slate-900">{{ d.customer_name }}</td>
                                <td class="py-2 px-3 text-slate-600 text-[11px]">{{ d.site_name }}</td>
                                <td class="py-2 px-3 text-center font-bold text-indigo-700">{{ d.truck_no }}</td>
                                <td class="py-2 px-3 text-slate-600 text-[11px]">{{ d.driver_name }}</td>
                                <td class="py-2 px-3 text-right font-bold text-slate-900">{{ formatQuantity(d.delivered_qty) }}</td>
                                <td class="py-2 px-3 text-right text-slate-600 text-[11px]">{{ d.pump_charges > 0 ? formatCurrency(d.pump_charges) : '-' }}</td>
                                <td class="py-2 px-3 text-right font-bold text-slate-900">{{ formatCurrency(d.total_amount) }}</td>
                            </tr>
                            <tr v-if="!filteredDispatches.length">
                                <td colspan="9" class="py-8 text-center text-slate-400">No dispatches match the search criteria.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pump Deployments Grid -->
                <div v-if="pumpOps.list && pumpOps.list.length > 0" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <WrenchScrewdriverIcon class="w-4 h-4 text-cyan-600" />
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Concrete Pump & Boom Deployments ({{ pumpOps.list.length }})
                        </h4>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div v-for="p in pumpOps.list" :key="p.id" class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs">
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-cyan-800">{{ p.pump_no }}</span>
                                <span class="px-2 py-0.5 rounded text-[9px] bg-cyan-100 text-cyan-800 uppercase font-bold">{{ p.status }}</span>
                            </div>
                            <div class="mt-1 font-semibold text-slate-800">{{ p.customer_name }}</div>
                            <div class="text-[11px] text-slate-500">{{ p.site_name }} • {{ p.grade }}</div>
                            <div class="mt-2 text-[11px] flex items-center justify-between font-bold text-slate-700 pt-1 border-t border-slate-200">
                                <span>Planned: {{ formatQuantity(p.planned_qty_m3) }} m³</span>
                                <span>Boom: {{ p.boom_length_m }}m</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. SECTION 2: INVOICING & BILLING -->
            <div v-if="activeSection === 'invoicing'" class="p-4 space-y-6">
                <!-- Search & Filters -->
                <div class="flex items-center justify-between gap-3">
                    <div class="relative w-72">
                        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" />
                        <input 
                            type="text" 
                            v-model="invoiceSearch" 
                            placeholder="Search invoice no, customer, GSTIN..." 
                            class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0064d2]"
                        />
                    </div>
                    <div class="text-xs font-bold text-slate-700">
                        Total Sales Billed: <span class="text-emerald-700">{{ formatCurrency(invoicing.sales_total_billed) }}</span> | 
                        Output GST: <span class="text-slate-900">{{ formatCurrency(invoicing.sales_tax_amount) }}</span>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-bold uppercase text-slate-600 border-b border-slate-200">
                                <th class="py-2.5 px-3 text-center" width="4%">#</th>
                                <th class="py-2.5 px-3" width="14%">Invoice No</th>
                                <th class="py-2.5 px-3" width="22%">Customer Name</th>
                                <th class="py-2.5 px-3 text-right" width="10%">Subtotal</th>
                                <th class="py-2.5 px-3 text-right" width="10%">Tax Amount</th>
                                <th class="py-2.5 px-3 text-right" width="10%">Hire/Shipping</th>
                                <th class="py-2.5 px-3 text-right" width="12%">Total (₹)</th>
                                <th class="py-2.5 px-3 text-center" width="10%">Compliance</th>
                                <th class="py-2.5 px-3 text-center" width="8%">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <tr v-for="inv in filteredInvoices" :key="inv.id" class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-2 px-3 text-center text-slate-400 font-bold">{{ inv.index }}</td>
                                <td class="py-2 px-3 font-bold text-[#0064d2] font-mono">{{ inv.invoice_no }}</td>
                                <td class="py-2 px-3 font-bold text-slate-900">
                                    <div>{{ inv.customer_name }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ inv.gstin }}</div>
                                </td>
                                <td class="py-2 px-3 text-right">{{ formatCurrency(inv.subtotal) }}</td>
                                <td class="py-2 px-3 text-right">{{ formatCurrency(inv.tax_amount) }}</td>
                                <td class="py-2 px-3 text-right">{{ inv.shipping_charges > 0 ? formatCurrency(inv.shipping_charges) : '-' }}</td>
                                <td class="py-2 px-3 text-right font-black text-slate-900">{{ formatCurrency(inv.total_amount) }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span v-if="inv.einvoice_irn !== 'Pending'" class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[9px] font-bold border border-emerald-200">
                                        IRN Active
                                    </span>
                                    <span v-else class="text-slate-400 text-[10px]">-</span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full text-[9px] font-bold uppercase',
                                        inv.balance_amount <= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                                    ]">
                                        {{ inv.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!filteredInvoices.length">
                                <td colspan="9" class="py-8 text-center text-slate-400">No invoices match the search criteria.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 6. SECTION 3: CASH FLOW & DAY BOOK -->
            <div v-if="activeSection === 'cash_flow'" class="p-4 space-y-6">
                <!-- Tax & Cash Flow Highlights -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Output vs Input GST -->
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-500 block">GST Reconciliation</span>
                        <div class="flex items-center justify-between text-slate-700">
                            <span>Output GST (Sales):</span>
                            <span class="font-bold">{{ formatCurrency(taxes.total_output_tax) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-700">
                            <span>Input GST (Purchase ITC):</span>
                            <span class="font-bold text-emerald-700">{{ formatCurrency(taxes.total_input_tax) }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200 font-bold text-slate-900">
                            <span>Net Tax Liability:</span>
                            <span :class="taxes.net_tax_liability >= 0 ? 'text-slate-900' : 'text-emerald-700'">
                                {{ formatCurrency(taxes.net_tax_liability) }}
                            </span>
                        </div>
                    </div>

                    <!-- Collections Split -->
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1">
                        <span class="text-[10px] font-bold uppercase text-emerald-700 block">Receipts Mode Breakdown</span>
                        <div class="flex items-center justify-between text-slate-700">
                            <span>Cash Receipts:</span>
                            <span class="font-bold">{{ formatCurrency(cashFlow.cash_receipts) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-700">
                            <span>Bank / Online / Cheque:</span>
                            <span class="font-bold">{{ formatCurrency(cashFlow.bank_receipts) }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200 font-bold text-emerald-800">
                            <span>Total Receipts:</span>
                            <span>{{ formatCurrency(cashFlow.total_receipts) }}</span>
                        </div>
                    </div>

                    <!-- Outflow Split -->
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1">
                        <span class="text-[10px] font-bold uppercase text-amber-800 block">Payments Mode Breakdown</span>
                        <div class="flex items-center justify-between text-slate-700">
                            <span>Cash Payments:</span>
                            <span class="font-bold">{{ formatCurrency(cashFlow.cash_payments) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-700">
                            <span>Bank / Online Transfers:</span>
                            <span class="font-bold">{{ formatCurrency(cashFlow.bank_payments) }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200 font-bold text-amber-900">
                            <span>Total Payments:</span>
                            <span>{{ formatCurrency(cashFlow.total_payments) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Receipts & Payments Tables (Side-by-side) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Receipts Table -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                            <CheckCircleIcon class="w-4 h-4" />
                            Collections & Receipts ({{ filteredReceipts.length }})
                        </h4>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-emerald-50/50 text-[10px] uppercase font-bold text-emerald-900 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Voucher #</th>
                                        <th class="py-2 px-3">Customer</th>
                                        <th class="py-2 px-3">Mode</th>
                                        <th class="py-2 px-3 text-right">Amount (₹)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="r in filteredReceipts" :key="r.id" class="hover:bg-slate-50">
                                        <td class="py-2 px-3 font-bold font-mono text-slate-800">{{ r.voucher_no }}</td>
                                        <td class="py-2 px-3 font-semibold text-slate-800">{{ r.customer }}</td>
                                        <td class="py-2 px-3 text-slate-600 text-[11px]">{{ r.mode }}</td>
                                        <td class="py-2 px-3 text-right font-black text-emerald-700">{{ formatCurrency(r.amount) }}</td>
                                    </tr>
                                    <tr v-if="!filteredReceipts.length">
                                        <td colspan="4" class="py-6 text-center text-slate-400">No receipts logged today.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payments Table -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                            <ArrowTrendingDownIcon class="w-4 h-4" />
                            Payments & Expenses ({{ filteredPayments.length }})
                        </h4>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-amber-50/50 text-[10px] uppercase font-bold text-amber-900 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Voucher #</th>
                                        <th class="py-2 px-3">Beneficiary</th>
                                        <th class="py-2 px-3">Mode</th>
                                        <th class="py-2 px-3 text-right">Amount (₹)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="p in filteredPayments" :key="p.id" class="hover:bg-slate-50">
                                        <td class="py-2 px-3 font-bold font-mono text-slate-800">{{ p.voucher_no }}</td>
                                        <td class="py-2 px-3 font-semibold text-slate-800">{{ p.beneficiary }}</td>
                                        <td class="py-2 px-3 text-slate-600 text-[11px]">{{ p.mode }}</td>
                                        <td class="py-2 px-3 text-right font-black text-amber-800">{{ formatCurrency(p.amount) }}</td>
                                    </tr>
                                    <tr v-if="!filteredPayments.length">
                                        <td colspan="4" class="py-6 text-center text-slate-400">No payments logged today.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. SECTION 4: SALES PIPELINE -->
            <div v-if="activeSection === 'pipeline'" class="p-4 space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Quotations -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Quotations Issued ({{ pipeline.quotations_count }})
                        </h4>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-600 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Quote #</th>
                                        <th class="py-2 px-3">Customer</th>
                                        <th class="py-2 px-3 text-right">Total Amount</th>
                                        <th class="py-2 px-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="q in pipeline.quotations_list" :key="q.id">
                                        <td class="py-2 px-3 font-bold font-mono text-[#0064d2]">{{ q.quote_no }}</td>
                                        <td class="py-2 px-3 font-semibold text-slate-800">{{ q.customer }}</td>
                                        <td class="py-2 px-3 text-right font-bold">{{ formatCurrency(q.total_amount) }}</td>
                                        <td class="py-2 px-3 text-center text-[10px]">{{ q.status }}</td>
                                    </tr>
                                    <tr v-if="!pipeline.quotations_list.length">
                                        <td colspan="4" class="py-6 text-center text-slate-400">No quotations logged for this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sales Orders -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            Sales Orders Confirmed ({{ pipeline.sales_orders_count }})
                        </h4>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-600 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Order #</th>
                                        <th class="py-2 px-3">Customer</th>
                                        <th class="py-2 px-3 text-right">Total Amount</th>
                                        <th class="py-2 px-3 text-center">Pump Required</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="so in pipeline.sales_orders_list" :key="so.id">
                                        <td class="py-2 px-3 font-bold font-mono text-emerald-700">{{ so.order_no }}</td>
                                        <td class="py-2 px-3 font-semibold text-slate-800">{{ so.customer }}</td>
                                        <td class="py-2 px-3 text-right font-bold">{{ formatCurrency(so.total_amount) }}</td>
                                        <td class="py-2 px-3 text-center">
                                            <span v-if="so.pump_required" class="px-1.5 py-0.5 rounded bg-cyan-100 text-cyan-800 font-bold text-[9px]">Yes</span>
                                            <span v-else class="text-slate-400 text-[10px]">No</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!pipeline.sales_orders_list.length">
                                        <td colspan="4" class="py-6 text-center text-slate-400">No sales orders logged for this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. SECTION 5: INWARDS -->
            <div v-if="activeSection === 'inward'" class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                        Raw Material Inwards & Receipts ({{ procurement.inwards_count }})
                    </h4>
                    <span class="text-xs font-bold text-slate-700">
                        Total Weight: <strong class="text-indigo-700">{{ procurement.total_weight }} T</strong>
                    </span>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-600 border-b border-slate-200">
                            <tr>
                                <th class="py-2 px-3 text-center" width="5%">#</th>
                                <th class="py-2 px-3" width="15%">Truck No</th>
                                <th class="py-2 px-3" width="30%">Vendor / Supplier</th>
                                <th class="py-2 px-3" width="25%">Material</th>
                                <th class="py-2 px-3 text-right" width="15%">Net Wt (T)</th>
                                <th class="py-2 px-3 text-center" width="10%">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="inw in procurement.list" :key="inw.id">
                                <td class="py-2 px-3 text-center text-slate-400 font-bold">{{ inw.index }}</td>
                                <td class="py-2 px-3 font-bold text-indigo-700">{{ inw.truck_no }}</td>
                                <td class="py-2 px-3 font-semibold text-slate-800">{{ inw.vendor }}</td>
                                <td class="py-2 px-3 text-slate-600">{{ inw.material }}</td>
                                <td class="py-2 px-3 text-right font-black text-slate-900">{{ formatQuantity(inw.net_weight) }}</td>
                                <td class="py-2 px-3 text-center text-slate-500">{{ inw.date }}</td>
                            </tr>
                            <tr v-if="!procurement.list.length">
                                <td colspan="6" class="py-8 text-center text-slate-400">No goods inward records found for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
