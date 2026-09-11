<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { 
    ArrowLeftIcon, 
    PrinterIcon, 
    MagnifyingGlassIcon,
    FunnelIcon,
    BuildingOfficeIcon,
    CalendarIcon,
    DocumentTextIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';

const page = usePage();

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    },
    showBackButton: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['back']);

// Local search and filter within the statement rows
const searchQuery = ref('');
const typeFilter = ref('ALL');

const patron = computed(() => props.reportData?.patron || {});
const plant = computed(() => props.reportData?.plant || {});
const accountSummary = computed(() => props.reportData?.account_summary || {});
const rawRows = computed(() => props.reportData?.ledger_transactions || props.reportData?.transactions || []);

// Active Plant Logo
const plantLogoUrl = computed(() => {
    if (plant.value?.logo_url) return plant.value.logo_url;
    if (plant.value?.logo_path) {
        const clean = String(plant.value.logo_path).replace(/^(public\/|storage\/|\/storage\/)/, '');
        return `/storage/${clean}`;
    }
    const activePlant = page.props.auth?.activePlant;
    if (activePlant?.plant_logo) return activePlant.plant_logo;
    return null;
});

// Address formatters
const patronAddress = computed(() => {
    const p = patron.value;
    if (p.addresses && p.addresses.length > 0) {
        const addr = p.addresses[0];
        const parts = [
            addr.address_line_1,
            addr.address_line_2,
            addr.city,
            addr.district,
            addr.state?.name || addr.state,
            addr.postal_code ? `- ${addr.postal_code}` : ''
        ].filter(Boolean);
        return parts.join(', ');
    }
    return '';
});

const plantAddress = computed(() => {
    const pl = plant.value;
    if (pl.addresses && pl.addresses.length > 0) {
        const addr = pl.addresses[0];
        const parts = [
            addr.address_line_1,
            addr.address_line_2,
            addr.city ? `${addr.city} (Po)` : '',
            addr.district ? `${addr.district} (Dt)` : '',
            addr.state?.name || addr.state,
            addr.postal_code ? `-${addr.postal_code}` : ''
        ].filter(Boolean);
        return parts.join(',\n');
    }
    return '';
});

const periodDisplay = computed(() => {
    if (props.reportData?.start_formatted && props.reportData?.end_formatted) {
        return `${props.reportData.start_formatted} to ${props.reportData.end_formatted}`;
    }
    if (props.reportData?.start && props.reportData?.end) {
        return `${props.reportData.start} to ${props.reportData.end}`;
    }
    return 'All Time';
});

// Filtered rows
const filteredRows = computed(() => {
    let rows = rawRows.value;

    if (typeFilter.value !== 'ALL') {
        rows = rows.filter(r => {
            if (r.is_opening) return true; // Always keep opening balance row
            const tx = (r.transactions || '').toLowerCase();
            if (typeFilter.value === 'INVOICE') return tx.includes('invoice') || tx.includes('bill');
            if (typeFilter.value === 'PAYMENT') return tx.includes('payment') || tx.includes('receipt');
            if (typeFilter.value === 'NOTE') return tx.includes('credit') || tx.includes('debit');
            return true;
        });
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        rows = rows.filter(r => 
            (r.transactions && r.transactions.toLowerCase().includes(q)) ||
            (r.details && r.details.toLowerCase().includes(q)) ||
            (r.type && r.type.toLowerCase().includes(q)) ||
            (r.date && r.date.toLowerCase().includes(q)) ||
            (r.invoice_bill_display && r.invoice_bill_display.toLowerCase().includes(q)) ||
            (r.receipt_payment_display && r.receipt_payment_display.toLowerCase().includes(q))
        );
    }

    return rows;
});

const printStatement = () => {
    window.print();
};

const getBadgeStyle = (txType) => {
    const tx = (txType || '').toLowerCase();
    if (tx.includes('opening')) {
        return 'bg-purple-100 text-purple-800 border-purple-200';
    }
    if (tx.includes('invoice')) {
        return 'bg-blue-100 text-blue-800 border-blue-200';
    }
    if (tx.includes('bill')) {
        return 'bg-amber-100 text-amber-800 border-amber-200';
    }
    if (tx.includes('receipt') || (tx.includes('payment') && tx.includes('received'))) {
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    }
    if (tx.includes('payment') && tx.includes('made')) {
        return 'bg-indigo-100 text-indigo-800 border-indigo-200';
    }
    if (tx.includes('credit')) {
        return 'bg-cyan-100 text-cyan-800 border-cyan-200';
    }
    if (tx.includes('debit')) {
        return 'bg-rose-100 text-rose-800 border-rose-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
};
</script>

<template>
    <div class="space-y-4 patron-statement-container">
        <!-- 1. Top Action Toolbar (Hidden during print) -->
        <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-white rounded-xl border border-slate-200 shadow-xs print:hidden">
            <div class="flex items-center gap-2">
                <button 
                    v-if="showBackButton"
                    type="button"
                    @click="emit('back')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    <ArrowLeftIcon class="w-3.5 h-3.5" />
                    <span>Back to All Customers</span>
                </button>

                <div class="h-4 w-px bg-slate-200 mx-1 hidden sm:block"></div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-800">
                        {{ patron.legal_name || 'Customer' }}
                    </span>
                    <span v-if="patron.patron_code || patron.code" class="text-[10px] px-1.5 py-0.5 rounded font-mono font-semibold bg-slate-100 text-slate-600">
                        {{ patron.patron_code || patron.code }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Transaction Type Filter -->
                <div class="flex items-center bg-slate-100 rounded-lg p-0.5 text-xs font-medium text-slate-600">
                    <button 
                        type="button" 
                        @click="typeFilter = 'ALL'"
                        :class="['px-2.5 py-1 rounded-md text-[11px] font-bold transition-all cursor-pointer', typeFilter === 'ALL' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900']"
                    >
                        All
                    </button>
                    <button 
                        type="button" 
                        @click="typeFilter = 'INVOICE'"
                        :class="['px-2.5 py-1 rounded-md text-[11px] font-bold transition-all cursor-pointer', typeFilter === 'INVOICE' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900']"
                    >
                        Invoices
                    </button>
                    <button 
                        type="button" 
                        @click="typeFilter = 'PAYMENT'"
                        :class="['px-2.5 py-1 rounded-md text-[11px] font-bold transition-all cursor-pointer', typeFilter === 'PAYMENT' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900']"
                    >
                        Payments
                    </button>
                    <button 
                        type="button" 
                        @click="typeFilter = 'NOTE'"
                        :class="['px-2.5 py-1 rounded-md text-[11px] font-bold transition-all cursor-pointer', typeFilter === 'NOTE' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900']"
                    >
                        Credit/Debit
                    </button>
                </div>

                <!-- Quick Filter Search -->
                <div class="relative w-48 sm:w-56">
                    <MagnifyingGlassIcon class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Search date, truck, ref..."
                        class="w-full pl-8 pr-2.5 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0064d2]"
                    />
                </div>

                <!-- Print Button -->
                <button 
                    type="button" 
                    @click="printStatement" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-xs cursor-pointer"
                    title="Print Statement"
                >
                    <PrinterIcon class="w-3.5 h-3.5 text-slate-600" />
                    <span>Print</span>
                </button>
            </div>
        </div>

        <!-- 2. Statement Document Sheet (Aesthetic Pixel-Accurate Layout matching PDF) -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 sm:p-8 font-sans print:p-0 print:border-none print:shadow-none">
            <!-- Header Row: Logo & Plant Address -->
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 pb-4 border-b border-slate-100">
                <!-- Left: Active Plant Logo Only -->
                <div class="flex items-center">
                    <img 
                        v-if="plantLogoUrl"
                        :src="plantLogoUrl" 
                        :alt="plant.legal_name || plant.name || 'Plant Logo'" 
                        class="h-12 max-h-14 w-auto max-w-[220px] object-contain"
                    />
                    <div v-else class="text-xl font-black tracking-tight text-slate-900">
                        {{ plant.legal_name || plant.name || 'DEMO LOGIN' }}
                    </div>
                </div>

                <!-- Right: Plant Legal & Address -->
                <div class="text-right text-[11px] text-slate-600 leading-tight">
                    <div class="font-bold text-slate-500 uppercase text-[9px] tracking-wider mb-0.5">Address:</div>
                    <div class="font-bold text-slate-900 text-xs">{{ plant.legal_name || plant.name || 'DEMO LOGIN' }}</div>
                    <div v-if="plantAddress" class="whitespace-pre-line text-slate-600 mt-0.5">{{ plantAddress }}</div>
                    <div v-else class="text-slate-500 mt-0.5">
                        3/150, Akkiyampatti (Po), Sendamangalam (Tk),<br />
                        Namakkal (Dt), Tamil Nadu -637409
                    </div>
                    <div class="mt-1 text-slate-700">
                        <span class="font-semibold">GSTIN/UIN #:</span> {{ plant.gstin || '-' }}
                    </div>
                    <div v-if="plant.udyam_number || plant.msme_number" class="text-slate-700">
                        <span class="font-semibold">MSME - UDYAM:</span> {{ plant.udyam_number || plant.msme_number }}
                    </div>
                </div>
            </div>

            <!-- Title & Statement Period -->
            <div class="flex flex-col sm:flex-row justify-between items-end gap-2 mt-4 pb-2">
                <div></div>
                <div class="text-right">
                    <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                        Patron Statement of Accounts
                    </h2>
                    <div class="text-xs text-slate-500 font-medium mt-0.5">
                        {{ periodDisplay }}
                    </div>
                </div>
            </div>

            <!-- Two Column Layout: To (Patron info) & Account Summary Box -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start mt-3">
                <!-- Left: Customer Information (To:) -->
                <div class="md:col-span-6 space-y-1 text-xs">
                    <div class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">To:</div>
                    <div class="text-base font-black text-slate-900 tracking-tight">
                        {{ patron.legal_name || patron.trade_name || 'Customer' }}
                    </div>
                    <div v-if="patron.trade_name && patron.trade_name !== patron.legal_name" class="text-xs font-semibold text-slate-600">
                        {{ patron.trade_name }}
                    </div>
                    <div v-if="patronAddress" class="text-slate-600 leading-snug text-[11px] pt-0.5">
                        {{ patronAddress }}
                    </div>
                    <div class="pt-1.5 space-y-0.5 text-[11px] text-slate-700">
                        <div>
                            <span class="font-bold text-slate-800">GSTIN/UIN # :</span>
                            <span class="ml-1 font-mono">{{ patron.gst_number || patron.gstin || '-' }}</span>
                        </div>
                        <div v-if="patron.pan_number || patron.pan_no">
                            <span class="font-bold text-slate-800">PAN # :</span>
                            <span class="ml-1 font-mono">{{ patron.pan_number || patron.pan_no }}</span>
                        </div>
                        <div>
                            <span class="font-bold text-slate-800">Contact # :</span>
                            <span class="ml-1 font-mono">{{ reportData.phone || patron.phone || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Account Summary Box (Matches uploaded PDF exactly) -->
                <div class="md:col-span-6 flex justify-start md:justify-end">
                    <div class="w-full sm:max-w-sm rounded-lg border border-slate-300 overflow-hidden shadow-2xs">
                        <div class="bg-slate-100 px-3 py-1.5 font-bold text-xs text-slate-800 border-b border-slate-300">
                            Account Summary
                        </div>
                        <table class="w-full text-xs">
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 font-medium">Opening Balance</td>
                                    <td class="py-1 px-3 text-right font-bold text-slate-900 font-mono">
                                        {{ accountSummary.opening_balance_display || 'Cr₹ 0.00' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Invoiced(Tax)</td>
                                    <td class="py-1 px-3 text-right font-mono">
                                        {{ accountSummary.invoiced_tax_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Invoiced(Non-Tax)</td>
                                    <td class="py-1 px-3 text-right font-mono">
                                        {{ accountSummary.invoiced_nontax_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="bg-slate-50/70 font-semibold">
                                    <td class="py-1 px-3 text-slate-800">Total Invoiced Amount</td>
                                    <td class="py-1 px-3 text-right font-bold text-slate-900 font-mono">
                                        {{ accountSummary.total_invoiced_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Sales Discount</td>
                                    <td class="py-1 px-3 text-right font-mono">
                                        {{ accountSummary.sales_discount_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Purchased</td>
                                    <td class="py-1 px-3 text-right font-mono">
                                        {{ accountSummary.purchased_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Amount Received</td>
                                    <td class="py-1 px-3 text-right font-bold text-emerald-700 font-mono">
                                        {{ accountSummary.amount_received_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Amount Paid</td>
                                    <td class="py-1 px-3 text-right font-mono">
                                        {{ accountSummary.amount_paid_display || '0' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-1 px-3 text-slate-600">Credits</td>
                                    <td class="py-1 px-3 text-right font-mono">
                                        {{ accountSummary.credits_display || '0' }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-100 font-bold border-t border-slate-300">
                                    <td class="py-1.5 px-3 text-slate-900 text-xs">Balance Due</td>
                                    <td class="py-1.5 px-3 text-right text-xs font-mono font-black" :class="accountSummary.balance_due_type === 'Dr' ? 'text-rose-700' : 'text-slate-900'">
                                        {{ accountSummary.balance_due_display || 'Cr ₹ 0.00' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Balance Due Banner Strip -->
            <div class="mt-6 flex items-center justify-between px-4 py-2 bg-slate-100 border-y border-slate-200">
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Balance Due</span>
                <span class="text-sm sm:text-base font-black font-mono" :class="accountSummary.balance_due_type === 'Dr' ? 'text-rose-700' : 'text-slate-900'">
                    {{ accountSummary.balance_due_display || reportData.balance_due_display || 'Cr ₹ 0.00' }}
                </span>
            </div>

            <!-- 3. Ledger Transactions Table (9-Column Table matching reference PDF) -->
            <div class="mt-4 overflow-x-auto max-h-[600px] overflow-y-auto border border-slate-200 rounded-lg">
                <table class="w-full text-left border-collapse min-w-[850px] text-xs">
                    <thead>
                        <tr class="sticky top-0 bg-[#1e293b] text-white text-[10px] font-bold uppercase tracking-wider z-10 shadow-xs">
                            <th class="py-2.5 px-1 text-center" width="4%">S/No</th>
                            <th class="py-2.5 px-1" width="5%">Date</th>
                            <th class="py-2.5 px-1" width="12%">Transactions</th>
                            <th class="py-2.5 px-1" width="25%">Details</th>
                            <th class="py-2.5 px-1 text-center" width="13%">Type</th>
                            <th class="py-2.5 px-1 text-right" width="13%">Invoice/(Bill)</th>
                            <th class="py-2.5 px-1 text-right" width="11%">(Receipt)/ Payment</th>
                            <th class="py-2.5 px-1 text-right" width="4%">Discount</th>
                            <th class="py-2.5 px-1 text-right" width="15%">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-[11px]">
                        <tr 
                            v-for="row in filteredRows" 
                            :key="row.s_no"
                            :class="[
                                'hover:bg-slate-50/80 transition-colors',
                                row.is_opening ? 'bg-slate-50/90 font-bold' : ''
                            ]"
                        >
                            <!-- S/No -->
                            <td class="py-2 px-1 text-center font-bold text-slate-500">
                                {{ row.s_no }}
                            </td>

                            <!-- Date -->
                            <td class="py-2 px-1 whitespace-nowrap text-slate-700 font-medium">
                                {{ row.date }}
                            </td>

                            <!-- Transactions -->
                            <td class="py-2 px-1 font-bold">
                                <span 
                                    v-if="row.is_opening"
                                    class="text-slate-800 font-extrabold italic"
                                >
                                    {{ row.transactions }}
                                </span>
                                <span 
                                    v-else 
                                    class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold border"
                                    :class="getBadgeStyle(row.transactions)"
                                >
                                    {{ row.transactions }}
                                </span>
                            </td>

                            <!-- Details (multiline text) -->
                            <td class="py-2 px-1 whitespace-pre-line text-slate-700 text-[10.5px] leading-relaxed">
                                {{ row.details }}
                            </td>

                            <!-- Type (Invoice Number & Prefix) -->
                            <td class="py-2 px-1 text-center font-mono text-[11px] font-semibold text-slate-700 whitespace-nowrap">
                                <span v-if="row.type && row.type !== '-'" class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ row.type }}
                                </span>
                                <span v-else class="text-slate-400">-</span>
                            </td>

                            <!-- Invoice / Bill -->
                            <td class="py-2 px-1 text-right font-mono font-medium text-slate-800 whitespace-nowrap">
                                {{ row.invoice_bill_display }}
                            </td>

                            <!-- (Receipt) / Payment -->
                            <td class="py-2 px-1 text-right font-mono font-medium text-slate-800 whitespace-nowrap">
                                {{ row.receipt_payment_display }}
                            </td>

                            <!-- Discount -->
                            <td class="py-2 px-1 text-right font-mono text-slate-500 whitespace-nowrap">
                                {{ row.discount_display }}
                            </td>

                            <!-- Running Balance -->
                            <td class="py-2 px-1 text-right font-mono font-bold whitespace-nowrap" :class="row.balance_type === 'Dr' ? 'text-rose-700' : 'text-slate-900'">
                                {{ row.balance_display }}
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!filteredRows.length">
                            <td colspan="9" class="py-8 text-center text-slate-400">
                                No transactions found matching your filters.
                            </td>
                        </tr>
                    </tbody>

                    <!-- Bottom Balance Due Footer Row -->
                    <tfoot class="border-t-2 border-slate-300 bg-slate-100 text-xs font-bold">
                        <tr>
                            <td colspan="5" class="py-2 px-1 text-right uppercase text-[10px] tracking-wider text-slate-700">
                                Balance Due
                            </td>
                            <td colspan="4" class="py-2 px-1 text-right font-black font-mono text-sm whitespace-nowrap" :class="accountSummary.balance_due_type === 'Dr' ? 'text-rose-700' : 'text-slate-900'">
                                {{ accountSummary.balance_due_display || reportData.balance_due_display || 'Cr ₹ 0.00' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Copyright & Watermark Footer -->
            <div class="mt-8 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-[10px] text-slate-400">
                <div>
                    {{ plant.legal_name || plant.name || 'DEMO LOGIN' }} - Copyright {{ new Date().getFullYear() }}
                </div>
                <div>
                    Report Generated: {{ reportData.generated_at || 'Now' }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        size: A4 portrait !important;
        margin: 10mm 8mm 10mm 8mm !important;
    }

    html, body {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .patron-statement-container {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .overflow-x-auto, .overflow-y-auto {
        max-height: none !important;
        overflow: visible !important;
    }
}
</style>
