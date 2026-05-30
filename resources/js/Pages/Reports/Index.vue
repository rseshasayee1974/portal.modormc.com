<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import { 
    ChartBarIcon,
    DocumentTextIcon, 
    UserGroupIcon,
    ArrowPathIcon,
    DocumentArrowDownIcon,
    ArrowDownTrayIcon,
    TableCellsIcon,
    BanknotesIcon,
    CubeIcon,
    Cog6ToothIcon,
    TruckIcon,
    UsersIcon,
    InboxIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    ledgers: Array,
    patrons: Array,
    filters: Object,
});

const getModuleIcon = (id) => {
    switch (id) {
        case 'accounting': return BanknotesIcon;
        case 'inventory': return CubeIcon;
        case 'production': return Cog6ToothIcon;
        case 'machines': return TruckIcon;
        case 'payroll': return UsersIcon;
        default: return DocumentTextIcon;
    }
};

// SAP Fiori Module Config
const modules = [
    {
        id: 'accounting',
        name: 'Accounting & Finance',
        reports: [
            { id: 'ledger', name: 'General Ledger', description: 'Account balances, running ledgers and transaction history' },
            { id: 'patron', name: 'Patron Statement', description: 'Partner transactions, invoice status and balances' },
            { id: 'payment', name: 'Payment Log', description: 'Cash outflow logs, paid vouchers and reference numbers' },
            { id: 'receipt', name: 'Receipt Log', description: 'Cash inflow logs, receipt vouchers and reference numbers' },
        ]
    },
    {
        id: 'inventory',
        name: 'Inventory Management',
        reports: [
            { id: 'inventory_stock', name: 'Stock Status List', description: 'Product stock levels, opening balances and current status' },
            { id: 'inventory_inward', name: 'Purchase Inward Receipts', description: 'Inward histories, truck weights and purchase records' },
            { id: 'purchase', name: 'Purchase & Bills Summary', description: 'PO breakdown, product cost logs and vendor bills' },
            { id: 'purchase_register', name: 'Purchase Register Report', description: 'Itemized purchase bills with supplier GST, rate, and values' },
            { id: 'silo_stock_valuation', name: 'Silo Stock Valuation', description: 'FIFO / Weighted Average cost valuation for consumed aggregate stock' },
        ]
    },
    {
        id: 'production',
        name: 'Production & Dispatch',
        reports: [
            { id: 'sales', name: 'Sales & Dispatches', description: 'Invoice listings, dispatch volumes and concrete grades' },
            { id: 'sales_register', name: 'Sales Register Report', description: 'Itemized sales invoices with GST breakdown, rate, and taxable values' },
            { id: 'production_batch', name: 'Batch Production Sheet', description: 'Batch mix designs, target vs actual aggregate loads' },
        ]
    },
    {
        id: 'machines',
        name: 'Fleet & Machinery',
        reports: [
            { id: 'machines_list', name: 'Machine Fleet Inventory', description: 'Active fleet list, mixer capacities and vehicle specs' },
            { id: 'machine_summary', name: 'Machine Summary Report', description: 'Overview of fleet metrics: registration, trips, qty, weight, revenue, expenses, and document expiry warnings' },
            { id: 'vehicle_pl', name: 'Vehicle Wise Profit & Loss', description: 'Vehicle financial breakdown: revenue, trip costs, fuel/maintenance, total costs, net profit, and profit margin %' },
        ]
    },
    {
        id: 'payroll',
        name: 'Payroll & Personnel',
        reports: [
            { id: 'payroll_personnel', name: 'Personnel Directory', description: 'Employee master data, contact details and designations' },
        ]
    }
];

const selectedModuleId = ref('accounting');
const reportType = ref('ledger'); 
const selectedId = ref(null); 
const patronId = ref(null);
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

const loading = ref(false);
const reportData = ref(null);

const gstType = ref(null);
const paymentStatus = ref(null);
const currentPage = ref(1);

const valuationMethod = ref('FIFO');
const valuationMethodOptions = [
    { label: 'FIFO (First-In, First-Out)', value: 'FIFO' },
    { label: 'Weighted Average', value: 'AVERAGE' }
];
const expandedProductId = ref(null);
const toggleProductExpand = (id) => {
    expandedProductId.value = expandedProductId.value === id ? null : id;
};

const exportProgress = ref(null);
const pollingInterval = ref(null);

const gstTypeOptions = [
    { label: 'All GST Types', value: null },
    { label: 'Intra-State (CGST + SGST)', value: 'intra' },
    { label: 'Inter-State (IGST)', value: 'inter' }
];

const paymentStatusOptions = [
    { label: 'All Statuses', value: null },
    { label: 'Paid Only', value: 'paid' },
    { label: 'Unpaid Only', value: 'unpaid' },
    { label: 'Partially Paid', value: 'partial' }
];

const checkExportStatus = (key) => {
    pollingInterval.value = setInterval(async () => {
        try {
            const response = await axios.get(route('reports.export-status', { key }));
            const data = response.data;
            exportProgress.value = data;

            if (data.status === 'completed') {
                clearInterval(pollingInterval.value);
                pollingInterval.value = null;
                window.open(data.url, '_blank');
                exportProgress.value = null;
            } else if (data.status === 'failed') {
                clearInterval(pollingInterval.value);
                pollingInterval.value = null;
                alert('Export failed: ' + data.error);
                exportProgress.value = null;
            }
        } catch (error) {
            console.error('Error polling export status:', error);
        }
    }, 3000);
};

const activeModule = computed(() => {
    return modules.find(m => m.id === selectedModuleId.value);
});

const activeReport = computed(() => {
    return activeModule.value.reports.find(r => r.id === reportType.value);
});

// Watch module change to select first report automatically
watch(selectedModuleId, (newModuleId) => {
    const mod = modules.find(m => m.id === newModuleId);
    if (mod && mod.reports.length > 0) {
        reportType.value = mod.reports[0].id;
        reportData.value = null;
        selectedId.value = null;
        patronId.value = null;
        gstType.value = null;
        paymentStatus.value = null;
        currentPage.value = 1;
        if (pollingInterval.value) {
            clearInterval(pollingInterval.value);
            pollingInterval.value = null;
        }
        exportProgress.value = null;
    }
});

watch(reportType, () => {
    reportData.value = null;
    gstType.value = null;
    paymentStatus.value = null;
    currentPage.value = 1;
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
    exportProgress.value = null;
});

const generateReport = async () => {
    if (!selectedId.value && reportType.value === 'ledger') return;
    
    loading.value = true;
    try {
        let url = route('reports.generate');
        let params = {
            type: reportType.value,
            id: selectedId.value,
            patron_id: patronId.value,
            start_date: startDate.value,
            end_date: endDate.value,
            valuation_method: valuationMethod.value,
            export: 'view'
        };

        if (reportType.value === 'sales_register') {
            url = route('reports.sales-register');
            params = {
                from_date: startDate.value,
                to_date: endDate.value,
                customer_id: patronId.value,
                gst_type: gstType.value,
                payment_status: paymentStatus.value,
                page: currentPage.value
            };
        } else if (reportType.value === 'purchase_register') {
            url = route('reports.purchase-register');
            params = {
                from_date: startDate.value,
                to_date: endDate.value,
                supplier_id: patronId.value,
                gst_type: gstType.value,
                page: currentPage.value
            };
        } else if (reportType.value === 'machine_summary') {
            url = route('reports.machine-summary');
            params = {
                from_date: startDate.value,
                to_date: endDate.value,
                page: currentPage.value
            };
        } else if (reportType.value === 'vehicle_pl') {
            url = route('reports.vehicle-pl');
            params = {
                from_date: startDate.value,
                to_date: endDate.value,
                page: currentPage.value
            };
        }

        const response = await axios.get(url, { params });
        reportData.value = response.data;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const exportPdf = () => {
    let url = route('reports.generate', {
        type: reportType.value,
        id: selectedId.value,
        patron_id: patronId.value,
        start_date: startDate.value,
        end_date: endDate.value,
        valuation_method: valuationMethod.value,
        export: 'pdf'
    });

    if (reportType.value === 'sales_register') {
        url = route('reports.sales-register', {
            from_date: startDate.value,
            to_date: endDate.value,
            customer_id: patronId.value,
            gst_type: gstType.value,
            payment_status: paymentStatus.value,
            export: 'pdf'
        });
    } else if (reportType.value === 'purchase_register') {
        url = route('reports.purchase-register', {
            from_date: startDate.value,
            to_date: endDate.value,
            supplier_id: patronId.value,
            gst_type: gstType.value,
            export: 'pdf'
        });
    } else if (reportType.value === 'machine_summary') {
        url = route('reports.machine-summary', {
            from_date: startDate.value,
            to_date: endDate.value,
            export: 'pdf'
        });
    } else if (reportType.value === 'vehicle_pl') {
        url = route('reports.vehicle-pl', {
            from_date: startDate.value,
            to_date: endDate.value,
            export: 'pdf'
        });
    }
    window.open(url, '_blank');
};

const exportExcel = async () => {
    let url = route('reports.generate', {
        type: reportType.value,
        id: selectedId.value,
        patron_id: patronId.value,
        start_date: startDate.value,
        end_date: endDate.value,
        valuation_method: valuationMethod.value,
        export: 'excel'
    });

    if (reportType.value === 'sales_register') {
        url = route('reports.sales-register', {
            from_date: startDate.value,
            to_date: endDate.value,
            customer_id: patronId.value,
            gst_type: gstType.value,
            payment_status: paymentStatus.value,
            export: 'excel'
        });
    } else if (reportType.value === 'purchase_register') {
        url = route('reports.purchase-register', {
            from_date: startDate.value,
            to_date: endDate.value,
            supplier_id: patronId.value,
            gst_type: gstType.value,
            export: 'excel'
        });
    } else if (reportType.value === 'machine_summary') {
        url = route('reports.machine-summary', {
            from_date: startDate.value,
            to_date: endDate.value,
            export: 'excel'
        });
    } else if (reportType.value === 'vehicle_pl') {
        url = route('reports.vehicle-pl', {
            from_date: startDate.value,
            to_date: endDate.value,
            export: 'excel'
        });
    }

    if (['sales_register', 'purchase_register', 'machine_summary', 'vehicle_pl'].includes(reportType.value)) {
        loading.value = true;
        try {
            const response = await axios.get(url);
            if (response.data && response.data.queued) {
                exportProgress.value = { status: 'queued', progress: 0 };
                checkExportStatus(response.data.status_key);
            } else {
                window.open(url + (url.indexOf('?') !== -1 ? '&direct=1' : '?direct=1'), '_blank');
            }
        } catch (error) {
            console.error('Error initiating Excel export:', error);
            alert('Export failed to start.');
        } finally {
            loading.value = false;
        }
    } else {
        window.open(url, '_blank');
    }
};

const transactionsWithBalance = computed(() => {
    if (!reportData.value) return [];
    let balance = reportData.value.opening_balance;
    return reportData.value.transactions.map(trx => {
        balance += (trx.debit - trx.credit);
        return { ...trx, running_balance: balance };
    });
});

const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

const sumTotalTaxesKey = (key) => {
    if (!reportData.value || !reportData.value.data) return 0;
    return reportData.value.data.reduce((sum, row) => sum + (row.taxes?.[key] || 0), 0);
};
</script>

<template>
    <AppLayout title="Operational Reports">
        <!-- SAP Fiori Quartz Light Shell Frame -->
        <div class="bg-[#f2f4f7] min-h-screen text-[#1d2d3e] font-sans antialiased">
            
            <!-- SAP Fiori Shell Header -->
            <div class="bg-[#1d2d3e] text-white px-6 py-3.5 shadow flex items-center justify-between border-b border-[#2d3e50]">
                <div class="flex items-center gap-4">
                    <span class="text-xs uppercase font-semibold text-slate-300 tracking-wider">SAP Fiori Launchpad</span>
                    <span class="text-xs text-slate-400">|</span>
                    <h1 class="text-sm font-bold tracking-tight text-white uppercase">Operational Report Floorplan</h1>
                </div>
                <div class="text-[11px] text-slate-300 font-semibold bg-[#2a3c50] px-3 py-1 rounded">
                    Active Plant Scoped
                </div>
            </div>

            <!-- SAP Fiori Tab Navigation Bar (Module selection - horizontally scrollable on mobile) -->
            <div class="bg-white border-b border-slate-200 px-4 lg:px-6 flex overflow-x-auto whitespace-nowrap scrollbar-none gap-1">
                <button 
                    v-for="mod in modules" 
                    :key="mod.id"
                    @click="selectedModuleId = mod.id"
                    class="px-5 py-3.5 text-xs font-bold transition-all border-b-2 -mb-px flex items-center gap-2 shrink-0"
                    :class="[
                        selectedModuleId === mod.id 
                        ? 'border-[#0064d2] text-[#0064d2]' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-200'
                    ]"
                >
                    <!-- Crystal-clear dynamic Heroicons component -->
                    <component :is="getModuleIcon(mod.id)" class="w-3.5 h-3.5 stroke-[2.5]" />
                    {{ mod.name }}
                </button>
            </div>

            <!-- SAP Split-Screen Master-Detail Layout -->
            <div class="flex flex-col lg:flex-row min-h-[calc(100vh-100px)]">
                
                <!-- Master Pane (Left Column: Report selector - Hidden on Mobile/Tablet) -->
                <div class="hidden lg:block w-72 bg-white border-r border-slate-200 shrink-0">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                        <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Reports Catalog</span>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        <div 
                            v-for="rep in activeModule.reports" 
                            :key="rep.id"
                            @click="reportType = rep.id"
                            class="px-5 py-4 cursor-pointer hover:bg-slate-50 transition-all border-l-4"
                            :class="[
                                reportType === rep.id 
                                ? 'border-[#0064d2] bg-[#f2f7fc] text-[#0064d2]' 
                                : 'border-transparent text-slate-700'
                            ]"
                        >
                            <h4 class="text-xs font-bold leading-snug">{{ rep.name }}</h4>
                            <p class="text-[10px] text-slate-400 mt-1 leading-relaxed line-clamp-2">{{ rep.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Detail Pane (Right Column: Filters & Table - Responsive padding) -->
                <div class="flex-1 p-4 sm:p-6 overflow-y-auto">
                    
                    <!-- Mobile Report Selector (only visible below lg breakpoint) -->
                    <div class="block lg:hidden mb-5 bg-white p-4 rounded border border-slate-200 shadow-sm">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Select Report Category</span>
                        <select 
                            v-model="reportType"
                            class="w-full text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded p-2 focus:outline-none focus:ring-1 focus:ring-[#0064d2]"
                        >
                            <option v-for="rep in activeModule.reports" :key="rep.id" :value="rep.id">
                                {{ rep.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Background Progress Alert -->
                    <div v-if="exportProgress" class="mb-6 bg-[#f2f7fc] border border-[#0064d2] p-4 rounded flex items-center gap-4">
                        <span class="w-5 h-5 border-2 border-[#0064d2] border-t-transparent rounded-full animate-spin shrink-0"></span>
                        <div class="flex-1">
                            <h4 class="text-xs font-bold text-[#0064d2] uppercase">Export Generation in Progress</h4>
                            <p class="text-[10px] text-slate-500 mt-0.5">Please wait while the server builds your document. This won't block other tasks.</p>
                            <div v-if="exportProgress.progress" class="w-full bg-slate-200 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-[#0064d2] h-full transition-all duration-300" :style="{ width: exportProgress.progress + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- SAP Fiori Smart Filter Bar -->
                    <div class="bg-white rounded border border-slate-200 shadow-sm mb-6">
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ activeModule.name }}</span>
                                <h3 class="text-sm font-bold text-[#1d2d3e] mt-0.5">{{ activeReport.name }}</h3>
                            </div>
                            <span v-if="['machines_list', 'payroll_personnel'].includes(reportType)" class="text-[10px] px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-slate-500 font-semibold">
                                Live Database Scoped
                            </span>
                        </div>
                        
                        <div class="p-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 items-end">
                                <!-- Target Dropdown (Ledger) -->
                                <div v-if="reportType === 'ledger'" class="lg:col-span-1">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Account Ledger</span>
                                    <BaseSelect 
                                        v-model="selectedId"
                                        :options="ledgers"
                                        optionLabel="title"
                                        optionValue="id"
                                        placeholder="Choose Account..."
                                        filter
                                    />
                                </div>

                                <!-- Patron Dropdown -->
                                <div v-if="['patron', 'sales', 'purchase', 'payment', 'receipt', 'sales_register', 'purchase_register'].includes(reportType)" class="lg:col-span-1">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">
                                        {{ reportType === 'sales_register' ? 'Select Customer' : (reportType === 'purchase_register' ? 'Select Supplier' : 'Select Partner') }}
                                    </span>
                                    <BaseSelect 
                                        v-model="patronId"
                                        :options="patrons"
                                        optionLabel="legal_name"
                                        optionValue="id"
                                        :filterFields="['legal_name', 'email', 'phone', 'contact_person']"
                                        placeholder="All Partners"
                                        filter
                                        showClear
                                    />
                                </div>

                                <!-- GST Type (Sales & Purchase Register) -->
                                <div v-if="['sales_register', 'purchase_register'].includes(reportType)" class="lg:col-span-1">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">GST Type</span>
                                    <BaseSelect 
                                        v-model="gstType"
                                        :options="gstTypeOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="All GST Types"
                                        showClear
                                    />
                                </div>

                                <!-- Payment Status (Sales Register only) -->
                                <div v-if="reportType === 'sales_register'" class="lg:col-span-1">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Payment Status</span>
                                    <BaseSelect 
                                        v-model="paymentStatus"
                                        :options="paymentStatusOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="All Statuses"
                                        showClear
                                    />
                                </div>

                                <!-- Valuation Method (Silo Stock Valuation only) -->
                                <div v-if="reportType === 'silo_stock_valuation'" class="lg:col-span-1">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Valuation Method</span>
                                    <BaseSelect 
                                        v-model="valuationMethod"
                                        :options="valuationMethodOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Valuation Method"
                                    />
                                </div>

                                <!-- Date Range -->
                                <div v-if="!['machines_list', 'payroll_personnel'].includes(reportType)" class="lg:col-span-2 grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-[11px] font-bold text-slate-500 block mb-1">From Date</span>
                                        <BaseDatePicker v-model="startDate" fluid />
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-bold text-slate-500 block mb-1">To Date</span>
                                        <BaseDatePicker v-model="endDate" fluid />
                                    </div>
                                </div>
                            </div>

                            <!-- SAP Fiori Action Bar -->
                            <div class="mt-6 pt-4 border-t border-slate-100 flex justify-between items-center">
                                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Smart Filter floorplan</span>
                                <div class="flex gap-2">
                                    <button 
                                        @click="exportExcel"
                                        class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-xs font-bold text-slate-700 rounded transition-all flex items-center gap-1.5"
                                    >
                                        <TableCellsIcon class="w-3.5 h-3.5" />
                                        Export Excel
                                    </button>
                                    <button 
                                        @click="exportPdf"
                                        class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-xs font-bold text-slate-700 rounded transition-all flex items-center gap-1.5"
                                    >
                                        <DocumentArrowDownIcon class="w-3.5 h-3.5" />
                                        Export PDF
                                    </button>
                                    <button 
                                        @click="generateReport"
                                        :disabled="loading"
                                        class="px-5 py-2 bg-[#0064d2] hover:bg-[#0057b8] text-white text-xs font-bold rounded transition-all flex items-center gap-1.5 shadow-sm"
                                    >
                                        <span v-if="loading" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                        <ArrowPathIcon v-else class="w-3.5 h-3.5" />
                                        Execute Query
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SAP Fiori Responsive Table Grid -->
                    <div v-if="reportData" class="bg-white rounded border border-slate-200 shadow-sm overflow-hidden animate-in fade-in duration-200">
                        
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <span class="text-xs font-bold text-[#1d2d3e] uppercase tracking-wider">Statement Result Grid</span>
                            <div class="flex items-center gap-2">
                                <span class="lg:hidden text-[9px] bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Swipe ➡️</span>
                                <button 
                                    @click="exportPdf"
                                    class="px-3 py-1.5 border border-slate-200 hover:bg-slate-50 text-[10px] font-bold text-slate-700 rounded transition-all flex items-center gap-1"
                                >
                                    <DocumentArrowDownIcon class="w-3 h-3" />
                                    Save PDF
                                </button>
                            </div>
                        </div>

                        <div class="p-5">
                            <!-- KPI Block Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                                <!-- Sales Register KPI Block -->
                                <div v-if="reportType === 'sales_register'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Taxable Sales</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.taxable) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'sales_register'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total GST Collected</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.gst) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'sales_register'" class="border border-slate-200 rounded p-4 bg-[#e2f0d9] border-[#c5e0b4] flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Grand Sales Total</span>
                                        <span class="text-lg font-black text-[#385723] mt-1 block">{{ formatCurrency(reportData.totals?.grand_total) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-[#385723]" />
                                </div>

                                <!-- Purchase Register KPI Block -->
                                <div v-if="reportType === 'purchase_register'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Taxable Purchases</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.taxable) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'purchase_register'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total GST Paid</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.gst) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'purchase_register'" class="border border-slate-200 rounded p-4 bg-[#fce4d6] border-[#f8cbad] flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-[#c65911] uppercase tracking-wider block">Grand Purchase Total</span>
                                        <span class="text-lg font-black text-[#c65911] mt-1 block">{{ formatCurrency(reportData.totals?.grand_total) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-[#c65911]" />
                                </div>

                                <div v-if="reportType === 'inventory_stock'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Consolidated Stock</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_quantity }}</span>
                                    </div>
                                    <CubeIcon class="w-5 h-5 text-slate-400" />
                                </div>

                                <div v-if="reportType === 'inventory_inward'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Inward Quantity</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_quantity }}</span>
                                    </div>
                                    <InboxIcon class="w-5 h-5 text-slate-400" />
                                </div>

                                <div v-if="reportType === 'production_batch'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Batched Volume</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_batch_size }} m³</span>
                                    </div>
                                    <Cog6ToothIcon class="w-5 h-5 text-slate-400" />
                                </div>

                                <!-- Silo Stock Valuation KPI Block -->
                                <div v-if="reportType === 'silo_stock_valuation'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Opening Valuation</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_opening_value_formatted }}</span>
                                    </div>
                                    <CubeIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'silo_stock_valuation'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total COGS Consumed</span>
                                        <span class="text-lg font-black text-red-650 mt-1 block">{{ reportData.total_consumed_value_formatted }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-red-400" />
                                </div>
                                <div v-if="reportType === 'silo_stock_valuation'" class="border border-slate-200 rounded p-4 bg-[#e2f0d9] border-[#c5e0b4] flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-[#385723] uppercase tracking-wider block">Closing Stock Valuation</span>
                                        <span class="text-lg font-black text-[#385723] mt-1 block">{{ reportData.total_ending_value_formatted }}</span>
                                    </div>
                                    <CubeIcon class="w-5 h-5 text-[#385723]" />
                                </div>

                                <div v-if="reportType === 'machines_list'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Active Fleet</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.transactions.length }} Vehicles</span>
                                    </div>
                                    <TruckIcon class="w-5 h-5 text-slate-400" />
                                </div>

                                <!-- Machine Summary KPI Block -->
                                <div v-if="reportType === 'machine_summary'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Trips</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.totals?.trips_count }}</span>
                                    </div>
                                    <TruckIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'machine_summary'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Revenue</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.total_revenue) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'machine_summary'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total General Expenses</span>
                                        <span class="text-lg font-black text-red-650 mt-1 block">{{ formatCurrency(reportData.totals?.general_expenses) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-red-400" />
                                </div>

                                <!-- Vehicle PL KPI Block -->
                                <div v-if="reportType === 'vehicle_pl'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Fleet Revenue</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.totals?.trip_revenue) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'vehicle_pl'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Fleet Total Expenses</span>
                                        <span class="text-lg font-black text-slate-700 mt-1 block">{{ formatCurrency(reportData.totals?.total_cost) }}</span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                                </div>
                                <div v-if="reportType === 'vehicle_pl'" class="border border-slate-200 rounded p-4 flex justify-between items-center"
                                     :class="(reportData.totals?.net_profit || 0) >= 0 ? 'bg-[#e2f0d9] border-[#c5e0b4]' : 'bg-red-50 border-red-200'"
                                >
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-wider block"
                                              :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-[#385723]' : 'text-red-700'"
                                        >
                                            Net Profit / Loss
                                        </span>
                                        <span class="text-lg font-black mt-1 block"
                                              :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-[#385723]' : 'text-red-700'"
                                        >
                                            {{ formatCurrency(reportData.totals?.net_profit) }}
                                        </span>
                                    </div>
                                    <BanknotesIcon class="w-5 h-5" :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-[#385723]' : 'text-red-500'" />
                                </div>

                                <div v-if="reportType === 'payroll_personnel'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Plant Headcount</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.transactions.length }} Employees</span>
                                    </div>
                                    <UsersIcon class="w-5 h-5 text-slate-400" />
                                </div>
                            </div>

                            <!-- Dynamic View: Sales Register Report -->
                            <div v-if="reportType === 'sales_register'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[1200px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="3%">#</th>
                                                <th class="py-3 px-4" width="8%">Invoice No</th>
                                                <th class="py-3 px-4 text-center" width="8%">Date</th>
                                                <th class="py-3 px-4" width="14%">Customer Name</th>
                                                <th class="py-3 px-4 text-center" width="9%">GSTIN</th>
                                                <th class="py-3 px-4" width="10%">Product</th>
                                                <th class="py-3 px-4 text-right" width="5%">Qty</th>
                                                <th class="py-3 px-4 text-right" width="6%">Rate</th>
                                                <th class="py-3 px-4 text-right" width="8%">Taxable Amt</th>
                                                <th class="py-3 px-4 text-right" width="6%">CGST</th>
                                                <th class="py-3 px-4 text-right" width="6%">SGST</th>
                                                <th class="py-3 px-4 text-right" width="6%">IGST</th>
                                                <!-- Dynamic tax rate columns -->
                                                <th v-for="col in reportData.tax_columns" :key="col.key" class="py-3 px-4 text-right text-slate-650" width="6%">
                                                    {{ col.label }}
                                                </th>
                                                <th class="py-3 px-4 text-right" width="8%">Net Amt</th>
                                                <th class="py-3 px-4 text-center" width="8%">Payment</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">
                                                     {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                                                </td>
                                                <td class="py-3 px-4 font-bold text-slate-800">{{ row.invoice_no }}</td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.invoice_date }}</td>
                                                <td class="py-3 px-4">{{ row.customer_name }}</td>
                                                <td class="py-3 px-4 text-center text-slate-650">{{ row.gst_number || 'N/A' }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ row.product_name }}</td>
                                                <td class="py-3 px-4 text-right">{{ row.qty.toFixed(2) }}</td>
                                                <td class="py-3 px-4 text-right">{{ formatCurrency(row.rate) }}</td>
                                                <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_amount) }}</td>
                                                <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.cgst) }}</td>
                                                <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.sgst) }}</td>
                                                <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.igst) }}</td>
                                                <!-- Dynamic tax rate columns values -->
                                                <td v-for="col in reportData.tax_columns" :key="col.key" class="py-3 px-4 text-right text-slate-500">
                                                    {{ formatCurrency(row.taxes?.[col.key] || 0) }}
                                                </td>
                                                <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.net_amount) }}</td>
                                                <td class="py-3 px-4 text-center">
                                                     <span class="px-2 py-0.5 rounded text-[9px] font-bold" 
                                                         :class="[
                                                              row.payment_status === 'Paid' ? 'bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]' : 
                                                              (row.payment_status === 'Partial' ? 'bg-[#fce4d6] text-[#c65911] border border-[#f8cbad]' : 'bg-red-50 text-red-700 border border-red-200')
                                                          ]"
                                                     >
                                                          {{ row.payment_status }}
                                                     </span>
                                                </td>
                                            </tr>
                                            <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                <td colspan="6" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Sales</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.totals?.qty.toFixed(2) }}</td>
                                                <td></td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.taxable) }}</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.cgst) }}</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.sgst) }}</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.igst) }}</td>
                                                <!-- Dynamic tax rate columns totals -->
                                                <td v-for="col in reportData.tax_columns" :key="col.key" class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">
                                                    {{ formatCurrency(sumTotalTaxesKey(col.key)) }}
                                                </td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-100">{{ formatCurrency(reportData.totals?.grand_total) }}</td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination Controls -->
                                <div v-if="reportData.pagination && reportData.pagination.last_page > 1" class="mt-4 flex justify-between items-center bg-slate-50 p-3 rounded border border-slate-200">
                                    <span class="text-xs text-slate-500 font-semibold">
                                         Showing page {{ reportData.pagination.current_page }} of {{ reportData.pagination.last_page }} (Total {{ reportData.pagination.total }} entries)
                                    </span>
                                    <div class="flex gap-2">
                                         <button 
                                             @click="currentPage--; generateReport();"
                                             :disabled="currentPage <= 1"
                                             class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                         >
                                             Previous
                                         </button>
                                         <button 
                                             @click="currentPage++; generateReport();"
                                             :disabled="currentPage >= reportData.pagination.last_page"
                                             class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                         >
                                             Next
                                         </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic View: Purchase Register Report -->
                            <div v-else-if="reportType === 'purchase_register'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[1200px]">
                                        <thead>
                                             <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                 <th class="py-3 px-4 text-center" width="3%">#</th>
                                                 <th class="py-3 px-4" width="8%">Bill No</th>
                                                 <th class="py-3 px-4 text-center" width="8%">Bill Date</th>
                                                 <th class="py-3 px-4" width="14%">Supplier Name</th>
                                                 <th class="py-3 px-4 text-center" width="9%">GSTIN</th>
                                                 <th class="py-3 px-4" width="10%">Product</th>
                                                 <th class="py-3 px-4 text-right" width="5%">Qty</th>
                                                 <th class="py-3 px-4 text-right" width="6%">Rate</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Taxable Amt</th>
                                                 <th class="py-3 px-4 text-right" width="6%">CGST</th>
                                                 <th class="py-3 px-4 text-right" width="6%">SGST</th>
                                                 <th class="py-3 px-4 text-right" width="6%">IGST</th>
                                                 <!-- Dynamic tax rate columns -->
                                                 <th v-for="col in reportData.tax_columns" :key="col.key" class="py-3 px-4 text-right text-slate-650" width="6%">
                                                     {{ col.label }}
                                                 </th>
                                                 <th class="py-3 px-4 text-right" width="8%">Net Amt</th>
                                             </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                             <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                 <td class="py-3 px-4 text-center text-slate-400">
                                                     {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                                                 </td>
                                                 <td class="py-3 px-4 font-bold text-slate-800">{{ row.bill_no }}</td>
                                                 <td class="py-3 px-4 text-center text-slate-500">{{ row.bill_date }}</td>
                                                 <td class="py-3 px-4">{{ row.supplier_name }}</td>
                                                 <td class="py-3 px-4 text-center text-slate-650">{{ row.gst_number || 'N/A' }}</td>
                                                 <td class="py-3 px-4 text-slate-600">{{ row.product_name }}</td>
                                                 <td class="py-3 px-4 text-right">{{ row.qty.toFixed(2) }}</td>
                                                 <td class="py-3 px-4 text-right">{{ formatCurrency(row.purchase_rate) }}</td>
                                                 <td class="py-3 px-4 text-right">{{ formatCurrency(row.taxable_amount) }}</td>
                                                 <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.cgst) }}</td>
                                                 <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.sgst) }}</td>
                                                 <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.igst) }}</td>
                                                 <!-- Dynamic tax rate columns values -->
                                                 <td v-for="col in reportData.tax_columns" :key="col.key" class="py-3 px-4 text-right text-slate-500">
                                                     {{ formatCurrency(row.taxes?.[col.key] || 0) }}
                                                 </td>
                                                 <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.net_amount) }}</td>
                                             </tr>
                                             <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                 <td colspan="6" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Purchases</td>
                                                 <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.totals?.qty.toFixed(2) }}</td>
                                                 <td></td>
                                                 <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.taxable) }}</td>
                                                 <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.cgst) }}</td>
                                                 <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.sgst) }}</td>
                                                 <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.igst) }}</td>
                                                 <!-- Dynamic tax rate columns totals -->
                                                 <td v-for="col in reportData.tax_columns" :key="col.key" class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">
                                                     {{ formatCurrency(sumTotalTaxesKey(col.key)) }}
                                                 </td>
                                                 <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-100">{{ formatCurrency(reportData.totals?.grand_total) }}</td>
                                             </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination Controls -->
                                <div v-if="reportData.pagination && reportData.pagination.last_page > 1" class="mt-4 flex justify-between items-center bg-slate-50 p-3 rounded border border-slate-200">
                                     <span class="text-xs text-slate-500 font-semibold">
                                         Showing page {{ reportData.pagination.current_page }} of {{ reportData.pagination.last_page }} (Total {{ reportData.pagination.total }} entries)
                                     </span>
                                     <div class="flex gap-2">
                                         <button 
                                             @click="currentPage--; generateReport();"
                                             :disabled="currentPage <= 1"
                                             class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                         >
                                             Previous
                                         </button>
                                         <button 
                                             @click="currentPage++; generateReport();"
                                             :disabled="currentPage >= reportData.pagination.last_page"
                                             class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                         >
                                             Next
                                         </button>
                                     </div>
                                </div>
                            </div>

                            <!-- Dynamic View: Silo Stock Valuation Report -->
                            <div v-else-if="reportType === 'silo_stock_valuation'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[1200px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                 <th class="py-3 px-4 text-center" width="3%">#</th>
                                                 <th class="py-3 px-4" width="15%">Product Name</th>
                                                 <th class="py-3 px-4" width="10%">Category</th>
                                                 <th class="py-3 px-4 text-center" width="5%">UOM</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Opening Qty</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Opening Value</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Inward Qty</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Inward Value</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Consumed Qty</th>
                                                 <th class="py-3 px-4 text-right" width="8%">COGS Value</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Ending Qty</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Ending Value</th>
                                                 <th class="py-3 px-4 text-right" width="8%">Unit Cost</th>
                                                 <th class="py-3 px-4 text-center" width="5%">Trace</th>
                                             </tr>
                                         </thead>
                                         <tbody class="text-[11px] font-semibold text-slate-700">
                                             <template v-for="(row, idx) in reportData.products" :key="row.product_id">
                                                 <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                     <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                     <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                                                     <td class="py-3 px-4 text-slate-600">{{ row.category }}</td>
                                                     <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                                                     <td class="py-3 px-4 text-right">{{ row.opening_qty.toLocaleString() }}</td>
                                                     <td class="py-3 px-4 text-right text-slate-500">{{ row.opening_value_formatted }}</td>
                                                     <td class="py-3 px-4 text-right">{{ row.inward_qty.toLocaleString() }}</td>
                                                     <td class="py-3 px-4 text-right text-slate-500">{{ row.inward_value_formatted }}</td>
                                                     <td class="py-3 px-4 text-right text-red-650">{{ row.consumed_qty.toLocaleString() }}</td>
                                                     <td class="py-3 px-4 text-right text-red-650">{{ row.consumed_value_formatted }}</td>
                                                     <td class="py-3 px-4 text-right font-black text-slate-800">{{ row.ending_qty.toLocaleString() }}</td>
                                                     <td class="py-3 px-4 text-right font-black text-[#0064d2]">{{ row.ending_value_formatted }}</td>
                                                     <td class="py-3 px-4 text-right font-bold text-slate-600">{{ row.avg_unit_cost_formatted }}</td>
                                                     <td class="py-3 px-4 text-center">
                                                         <button 
                                                             @click="toggleProductExpand(row.product_id)"
                                                             class="px-2 py-1 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded text-[9px] font-bold text-slate-600 transition-all uppercase"
                                                         >
                                                             {{ expandedProductId === row.product_id ? 'Hide' : 'Trace' }}
                                                         </button>
                                                     </td>
                                                 </tr>
                                                 <tr v-if="expandedProductId === row.product_id" class="bg-slate-50/70">
                                                     <td colspan="14" class="p-4 border-b border-slate-200">
                                                         <div class="bg-white rounded border border-slate-200 p-3 shadow-inner">
                                                             <h5 class="text-[10px] font-black uppercase text-slate-400 tracking-wider mb-2.5">
                                                                 Chronological Audit Trail Ledger for {{ row.product_name }} ({{ valuationMethod }})
                                                             </h5>
                                                             <div v-if="row.detailed_events && row.detailed_events.length > 0" class="overflow-x-auto">
                                                                     <table class="w-full text-left border-collapse text-[10px]">
                                                                         <thead>
                                                                             <tr class="font-bold text-slate-500 border-b border-slate-100 uppercase bg-slate-50/50">
                                                                                 <th class="py-2 px-3">Date</th>
                                                                                 <th class="py-2 px-3">Doc No</th>
                                                                                 <th class="py-2 px-3">Transaction</th>
                                                                                 <th class="py-2 px-3">Reference / Source</th>
                                                                                 <th class="py-2 px-3 text-right">Inward Qty</th>
                                                                                 <th class="py-2 px-3 text-right">Consumed Qty</th>
                                                                                 <th class="py-2 px-3 text-right">Unit Rate</th>
                                                                                 <th class="py-2 px-3 text-right">Total Value</th>
                                                                                 <th class="py-2 px-3 text-right">Running Stock Qty</th>
                                                                                 <th class="py-2 px-3 text-right">Running Stock Value</th>
                                                                             </tr>
                                                                         </thead>
                                                                         <tbody class="font-medium text-slate-600">
                                                                             <tr v-for="(evt, eIdx) in row.detailed_events" :key="eIdx" class="border-b border-slate-100 hover:bg-slate-50">
                                                                                 <td class="py-2 px-3 text-slate-400">{{ evt.date }}</td>
                                                                                 <td class="py-2 px-3 font-mono font-bold">{{ evt.doc_no }}</td>
                                                                                 <td class="py-2 px-3">
                                                                                     <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider"
                                                                                           :class="evt.type === 'inward' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100'"
                                                                                     >
                                                                                         {{ evt.type }}
                                                                                     </span>
                                                                                 </td>
                                                                                 <td class="py-2 px-3 italic">{{ evt.ref }}</td>
                                                                                 <td class="py-2 px-3 text-right font-semibold text-slate-800">{{ evt.type === 'inward' ? evt.qty.toLocaleString() : '-' }}</td>
                                                                                 <td class="py-2 px-3 text-right font-semibold text-red-600">{{ evt.type === 'consumption' ? evt.qty.toLocaleString() : '-' }}</td>
                                                                                 <td class="py-2 px-3 text-right">{{ formatCurrency(evt.price) }}</td>
                                                                                 <td class="py-2 px-3 text-right font-semibold" :class="evt.type === 'consumption' ? 'text-red-650' : 'text-emerald-750'">{{ formatCurrency(evt.value) }}</td>
                                                                                 <td class="py-2 px-3 text-right font-bold text-slate-800 bg-slate-50/20">{{ evt.running_qty.toLocaleString() }}</td>
                                                                                 <td class="py-2 px-3 text-right font-bold text-slate-800 bg-slate-50/20">{{ formatCurrency(evt.running_val) }}</td>
                                                                             </tr>
                                                                         </tbody>
                                                                     </table>
                                                             </div>
                                                             <div v-else class="text-center py-4 text-slate-400 text-xs italic">
                                                                 No transactions to trace in this date range.
                                                             </div>
                                                         </div>
                                                     </td>
                                                 </tr>
                                             </template>
                                         </tbody>
                                     </table>
                                 </div>
                             </div>

                            <!-- Dynamic View: Inventory Stock Levels -->
                            <div v-else-if="reportType === 'inventory_stock'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[800px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="5%">#</th>
                                                <th class="py-3 px-4 text-center" width="15%">Date</th>
                                                <th class="py-3 px-4" width="40%">Product Name</th>
                                                <th class="py-3 px-4 text-center" width="10%">UOM</th>
                                                <th class="py-3 px-4 text-right" width="15%">Opening Qty</th>
                                                <th class="py-3 px-4 text-right" width="15%">Current Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                                                <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                                                <td class="py-3 px-4 text-right text-slate-600">{{ row.opening_qty }}</td>
                                                <td class="py-3 px-4 text-right font-black text-[#1d2d3e] bg-slate-50/55">{{ row.quantity }}</td>
                                            </tr>
                                            <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                <td colspan="5" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Current Stock</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Dynamic View: Purchase Inwards -->
                            <div v-else-if="reportType === 'inventory_inward'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[800px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="5%">#</th>
                                                <th class="py-3 px-4 text-center" width="12%">Received Date</th>
                                                <th class="py-3 px-4 text-center" width="12%">Inward No</th>
                                                <th class="py-3 px-4 text-center" width="12%">PO No</th>
                                                <th class="py-3 px-4" width="20%">Supplier / Vendor</th>
                                                <th class="py-3 px-4" width="15%">Product</th>
                                                <th class="py-3 px-4 text-right" width="12%">Quantity</th>
                                                <th class="py-3 px-4 text-center" width="12%">Truck No</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                                                <td class="py-3 px-4 text-center font-bold text-slate-750">{{ row.inward_no }}</td>
                                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.po_number }}</td>
                                                <td class="py-3 px-4 text-slate-800">{{ row.vendor_name }}</td>
                                                <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                                                <td class="py-3 px-4 text-right font-black text-[#1d2d3e] bg-slate-50/55">{{ row.quantity }} <span class="text-[10px] text-slate-400">{{ row.uom }}</span></td>
                                                <td class="py-3 px-4 text-center text-slate-600">{{ row.truck_no }}</td>
                                            </tr>
                                            <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                <td colspan="6" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Goods Inward</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Dynamic View: Production Batch Consumption -->
                            <div v-else-if="reportType === 'production_batch'">
                                <!-- Consolidated Material Consumption Summary -->
                                <div class="mb-6 border border-slate-200 rounded" v-if="reportData.material_summary && reportData.material_summary.length > 0">
                                    <div class="px-4 py-2.5 bg-[#f2f4f7] border-b border-slate-200 font-bold text-[10px] text-slate-600 uppercase">
                                        Consolidated Raw Aggregate Consumption
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-200">
                                        <div v-for="(mRow, idx) in reportData.material_summary" :key="idx" class="p-4">
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">{{ mRow.material_name }}</span>
                                            <div class="flex justify-between items-end mt-2">
                                                <div>
                                                    <span class="text-[9px] text-slate-400 block font-semibold">Actual</span>
                                                    <span class="text-sm font-black text-[#1d2d3e] block">{{ mRow.actual_qty.toFixed(1) }} kg</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-[9px] text-slate-400 block font-semibold">Target</span>
                                                    <span class="text-xs font-bold text-slate-500 block">{{ mRow.target_qty.toFixed(1) }} kg</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[800px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="5%">#</th>
                                                <th class="py-3 px-4 text-center" width="12%">Start Date</th>
                                                <th class="py-3 px-4 text-center" width="12%">Batch No</th>
                                                <th class="py-3 px-4 text-center" width="12%">Work Order</th>
                                                <th class="py-3 px-4" width="25%">Mix Design</th>
                                                <th class="py-3 px-4 text-right" width="12%">Batch Size</th>
                                                <th class="py-3 px-4" width="12%">Operator</th>
                                                <th class="py-3 px-4 text-center" width="10%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                                                <td class="py-3 px-4 text-center font-bold text-slate-700">{{ row.batch_no }}</td>
                                                <td class="py-3 px-4 text-center font-bold text-[#0064d2]">{{ row.work_order }}</td>
                                                <td class="py-3 px-4 text-slate-800">{{ row.mix_design }}</td>
                                                <td class="py-3 px-4 text-right font-black text-[#1d2d3e] bg-slate-50/55">{{ row.batch_size }} m³</td>
                                                <td class="py-3 px-4 text-slate-600">{{ row.operator }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                                                        {{ row.status }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                <td colspan="5" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Batched Volume</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_batch_size }} m³</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Dynamic View: Fleet Machines -->
                            <div v-else-if="reportType === 'machines_list'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[800px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="5%">#</th>
                                                <th class="py-3 px-4 text-center" width="20%">Registration</th>
                                                <th class="py-3 px-4" width="25%">Vehicle Model</th>
                                                <th class="py-3 px-4 text-center" width="15%">Vehicle Type</th>
                                                <th class="py-3 px-4 text-center" width="10%">Make Year</th>
                                                <th class="py-3 px-4 text-right" width="10%">Capacity</th>
                                                <th class="py-3 px-4" width="15%">Owner</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.registration }}</td>
                                                <td class="py-3 px-4 text-slate-800">{{ row.vehicle_model }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                                        {{ row.vehicle_type }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.make_year }}</td>
                                                <td class="py-3 px-4 text-right font-bold text-slate-700">{{ row.capacity }}</td>
                                                <td class="py-3 px-4 text-slate-600 italic">{{ row.owner }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Dynamic View: Machine Summary Report -->
                            <div v-else-if="reportType === 'machine_summary'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[1000px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="3%">#</th>
                                                <th class="py-3 px-4 text-center" width="10%">Registration</th>
                                                <th class="py-3 px-4" width="12%">Model</th>
                                                <th class="py-3 px-4 text-center" width="10%">Type</th>
                                                <th class="py-3 px-4 text-center" width="8%">Make Year</th>
                                                <th class="py-3 px-4 text-right" width="8%">Capacity</th>
                                                <th class="py-3 px-4" width="12%">Owner</th>
                                                <th class="py-3 px-4 text-center" width="6%">Trips</th>
                                                <th class="py-3 px-4 text-right" width="8%">Qty</th>
                                                <th class="py-3 px-4 text-right" width="10%">Revenue</th>
                                                <th class="py-3 px-4 text-right" width="10%">Expenses</th>
                                                <th class="py-3 px-4" width="15%">Alerts</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">
                                                    {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                                                </td>
                                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.registration }}</td>
                                                <td class="py-3 px-4 text-slate-800">{{ row.vehicle_model }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                                        {{ row.vehicle_type }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.make_year }}</td>
                                                <td class="py-3 px-4 text-right text-slate-700">{{ row.capacity }}</td>
                                                <td class="py-3 px-4 text-slate-650">{{ row.owner }}</td>
                                                <td class="py-3 px-4 text-center">{{ row.trips_count }}</td>
                                                <td class="py-3 px-4 text-right">
                                                    {{ row.total_qty.toFixed(2) }}
                                                    <div class="text-[9px] text-slate-400 font-normal">{{ row.total_weight_tons.toFixed(2) }} Tons</div>
                                                </td>
                                                <td class="py-3 px-4 text-right">{{ formatCurrency(row.total_revenue) }}</td>
                                                <td class="py-3 px-4 text-right">{{ formatCurrency(row.general_expenses) }}</td>
                                                <td class="py-3 px-4">
                                                    <div v-for="(alert, aIdx) in row.alerts" :key="aIdx" 
                                                         class="text-[9px] font-bold leading-tight"
                                                         :class="alert.status === 'expired' ? 'text-red-600' : 'text-amber-600'"
                                                    >
                                                        ⚠️ {{ alert.message }}
                                                    </div>
                                                    <span v-if="!row.alerts || row.alerts.length === 0" class="text-green-600 text-[9px] font-bold">✓ Active</span>
                                                </td>
                                            </tr>
                                            <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                <td colspan="7" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Fleet Summary</td>
                                                <td class="py-3.5 px-4 text-center text-[#1d2d3e] font-black">{{ reportData.totals?.trips_count }}</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">
                                                    {{ reportData.totals?.total_qty.toFixed(2) }}
                                                    <div class="text-[9px] text-slate-500 font-semibold">{{ reportData.totals?.total_weight_tons.toFixed(2) }} Tons</div>
                                                </td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-50/50">{{ formatCurrency(reportData.totals?.total_revenue) }}</td>
                                                <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-50/50">{{ formatCurrency(reportData.totals?.general_expenses) }}</td>
                                                <td></td>
                                              </tr>
                                          </tbody>
                                      </table>
                                  </div>

                                  <!-- Pagination Controls -->
                                  <div v-if="reportData.pagination && reportData.pagination.last_page > 1" class="mt-4 flex justify-between items-center bg-slate-50 p-3 rounded border border-slate-200">
                                      <span class="text-xs text-slate-500 font-semibold">
                                           Showing page {{ reportData.pagination.current_page }} of {{ reportData.pagination.last_page }} (Total {{ reportData.pagination.total }} entries)
                                      </span>
                                      <div class="flex gap-2">
                                           <button 
                                               @click="currentPage--; generateReport();"
                                               :disabled="currentPage <= 1"
                                               class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                           >
                                               Previous
                                           </button>
                                           <button 
                                               @click="currentPage++; generateReport();"
                                               :disabled="currentPage >= reportData.pagination.last_page"
                                               class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                           >
                                               Next
                                           </button>
                                      </div>
                                  </div>
                              </div>

                              <!-- Dynamic View: Vehicle Wise Profit & Loss Report -->
                              <div v-else-if="reportType === 'vehicle_pl'">
                                  <div class="overflow-x-auto border border-slate-200 rounded">
                                      <table class="w-full text-left border-collapse min-w-[1000px]">
                                          <thead>
                                              <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                  <th class="py-3 px-4 text-center" width="3%">#</th>
                                                  <th class="py-3 px-4 text-center" width="10%">Registration</th>
                                                  <th class="py-3 px-4" width="12%">Model</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Trip Revenue</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Trip Cost</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Fuel Expense</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Maintenance</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Other Expense</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Total Cost</th>
                                                  <th class="py-3 px-4 text-right" width="11%">Net Profit</th>
                                                  <th class="py-3 px-4 text-right" width="8%">Margin %</th>
                                              </tr>
                                          </thead>
                                          <tbody class="text-[11px] font-semibold text-slate-700">
                                              <tr v-for="(row, idx) in reportData.data" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                  <td class="py-3 px-4 text-center text-slate-400">
                                                      {{ (currentPage - 1) * (reportData.pagination?.per_page || 100) + idx + 1 }}
                                                  </td>
                                                  <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.registration }}</td>
                                                  <td class="py-3 px-4 text-slate-800">{{ row.vehicle_model }}</td>
                                                  <td class="py-3 px-4 text-right">{{ formatCurrency(row.trip_revenue) }}</td>
                                                  <td class="py-3 px-4 text-right">{{ formatCurrency(row.trip_cost) }}</td>
                                                  <td class="py-3 px-4 text-right">{{ formatCurrency(row.fuel_expenses) }}</td>
                                                  <td class="py-3 px-4 text-right">{{ formatCurrency(row.maintenance_expenses) }}</td>
                                                  <td class="py-3 px-4 text-right">{{ formatCurrency(row.other_expenses) }}</td>
                                                  <td class="py-3 px-4 text-right font-bold text-slate-800 bg-slate-50/20">{{ formatCurrency(row.total_cost) }}</td>
                                                  <td class="py-3 px-4 text-right font-bold" 
                                                      :class="row.net_profit >= 0 ? 'text-green-600' : 'text-red-600'"
                                                  >
                                                      {{ formatCurrency(row.net_profit) }}
                                                  </td>
                                                  <td class="py-3 px-4 text-right font-bold" 
                                                      :class="row.margin_pct >= 0 ? 'text-green-600 font-extrabold' : 'text-red-600 font-extrabold'"
                                                  >
                                                      {{ row.margin_pct.toFixed(2) }}%
                                                  </td>
                                              </tr>
                                              <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                  <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total P&L</td>
                                                  <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.trip_revenue) }}</td>
                                                  <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.trip_cost) }}</td>
                                                  <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.fuel_expenses) }}</td>
                                                  <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.maintenance_expenses) }}</td>
                                                  <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.totals?.other_expenses) }}</td>
                                                  <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black bg-slate-100">{{ formatCurrency(reportData.totals?.total_cost) }}</td>
                                                  <td class="py-3.5 px-4 text-right font-black" 
                                                      :class="(reportData.totals?.net_profit || 0) >= 0 ? 'text-green-600 bg-slate-100' : 'text-red-600 bg-slate-100'"
                                                  >
                                                      {{ formatCurrency(reportData.totals?.net_profit) }}
                                                  </td>
                                                  <td class="py-3.5 px-4 text-right font-black" 
                                                      :class="(reportData.totals?.margin_pct || 0) >= 0 ? 'text-green-600 bg-slate-100' : 'text-red-600 bg-slate-100'"
                                                  >
                                                      {{ (reportData.totals?.margin_pct || 0).toFixed(2) }}%
                                                  </td>
                                              </tr>
                                          </tbody>
                                      </table>
                                  </div>

                                  <!-- Pagination Controls -->
                                  <div v-if="reportData.pagination && reportData.pagination.last_page > 1" class="mt-4 flex justify-between items-center bg-slate-50 p-3 rounded border border-slate-200">
                                      <span class="text-xs text-slate-500 font-semibold">
                                           Showing page {{ reportData.pagination.current_page }} of {{ reportData.pagination.last_page }} (Total {{ reportData.pagination.total }} entries)
                                      </span>
                                      <div class="flex gap-2">
                                           <button 
                                               @click="currentPage--; generateReport();"
                                               :disabled="currentPage <= 1"
                                               class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                           >
                                               Previous
                                           </button>
                                           <button 
                                               @click="currentPage++; generateReport();"
                                               :disabled="currentPage >= reportData.pagination.last_page"
                                               class="px-3 py-1.5 border border-slate-200 hover:bg-slate-150 text-xs font-bold text-slate-700 rounded disabled:opacity-50"
                                           >
                                               Next
                                           </button>
                                      </div>
                                  </div>
                              </div>

                            <!-- Dynamic View: Personnel Directory -->
                            <div v-else-if="reportType === 'payroll_personnel'">
                                <div class="overflow-x-auto border border-slate-200 rounded">
                                    <table class="w-full text-left border-collapse min-w-[800px]">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                <th class="py-3 px-4 text-center" width="5%">#</th>
                                                <th class="py-3 px-4" width="25%">Employee Name</th>
                                                <th class="py-3 px-4" width="20%">Role / Employee Type</th>
                                                <th class="py-3 px-4 text-center" width="15%">Joining Date</th>
                                                <th class="py-3 px-4 text-center" width="10%">Status</th>
                                                <th class="py-3 px-4" width="15%">Email</th>
                                                <th class="py-3 px-4 text-center" width="10%">Phone</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[11px] font-semibold text-slate-700">
                                            <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                <td class="py-3 px-4 font-bold text-slate-800">{{ row.name }}</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                                        {{ row.employee_type }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center text-slate-500">{{ row.joining_date }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                                                        {{ row.status }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-slate-600 text-xs">{{ row.email }}</td>
                                                <td class="py-3 px-4 text-center text-slate-600 text-xs">{{ row.phone }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Purchase Report Preview -->
                            <div v-else-if="reportType === 'purchase'">
                                <div class="mb-6">
                                    <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                                        Purchase Order Summary Breakdown
                                    </div>
                                    <div class="overflow-x-auto border border-slate-200 rounded-b">
                                        <table class="w-full text-left border-collapse min-w-[800px]">
                                            <thead>
                                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                    <th class="py-3 px-4 text-center" width="5%">#</th>
                                                    <th class="py-3 px-4 text-center" width="15%">Date</th>
                                                    <th class="py-3 px-4 text-center" width="20%">PO Number</th>
                                                    <th class="py-3 px-4" width="24%">Supplier / Vendor</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-[11px] font-semibold text-slate-700">
                                                <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                    <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                    <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                                                    <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.po_number }}</td>
                                                    <td class="py-3 px-4">{{ row.vendor_name }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                                                </tr>
                                                <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                    <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Purchase</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_untaxed) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_tax) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                                        Material wise Summary
                                    </div>
                                    <div class="overflow-x-auto border border-slate-200 rounded-b">
                                        <table class="w-full text-left border-collapse min-w-[800px]">
                                            <thead>
                                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                    <th class="py-3 px-4 text-center" width="5%">#</th>
                                                    <th class="py-3 px-4" width="40%">Product Name</th>
                                                    <th class="py-3 px-4 text-center" width="10%">UOM</th>
                                                    <th class="py-3 px-4 text-right" width="10%">Quantity</th>
                                                    <th class="py-3 px-4 text-right" width="11%">Avg Rate</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-[11px] font-semibold text-slate-700">
                                                <tr v-for="(row, idx) in reportData.product_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                    <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                    <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                                                    <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600 font-bold">{{ row.quantity }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                                                </tr>
                                                <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                    <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Goods breakdown</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                                                    <td></td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_untaxed) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_tax) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales Report Preview -->
                            <div v-else-if="reportType === 'sales'">
                                <div class="mb-6">
                                    <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                                        Sales Invoice wise Breakdown
                                    </div>
                                    <div class="overflow-x-auto border border-slate-200 rounded-b">
                                        <table class="w-full text-left border-collapse min-w-[800px]">
                                            <thead>
                                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                    <th class="py-3 px-4 text-center" width="5%">#</th>
                                                    <th class="py-3 px-4 text-center" width="15%">Date</th>
                                                    <th class="py-3 px-4 text-center" width="20%">Invoice Number</th>
                                                    <th class="py-3 px-4" width="24%">Customer / Party</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-[11px] font-semibold text-slate-700">
                                                <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                    <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                    <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                                                    <td class="py-3 px-4 text-center font-bold text-slate-850">{{ row.invoice_number }}</td>
                                                    <td class="py-3 px-4">{{ row.customer_name }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                                                </tr>
                                                <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                    <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Sales</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_untaxed) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_tax) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-6 mb-6">
                                    <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                                        Sales Product wise Consolidated Summary
                                    </div>
                                    <div class="overflow-x-auto border border-slate-200 rounded-b">
                                        <table class="w-full text-left border-collapse min-w-[800px]">
                                            <thead>
                                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                    <th class="py-3 px-4 text-center" width="5%">#</th>
                                                    <th class="py-3 px-4" width="40%">Product Name</th>
                                                    <th class="py-3 px-4 text-center" width="10%">UOM</th>
                                                    <th class="py-3 px-4 text-right" width="10%">Quantity</th>
                                                    <th class="py-3 px-4 text-right" width="11%">Avg Rate</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Taxable Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Tax Amt</th>
                                                    <th class="py-3 px-4 text-right" width="12%">Total Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-[11px] font-semibold text-slate-700">
                                                <tr v-for="(row, idx) in reportData.product_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                    <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                    <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                                                    <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600 font-bold">{{ row.quantity }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                                                </tr>
                                                <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                    <td colspan="3" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total summary</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                                                    <td></td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_untaxed) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_tax) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_product_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-6 mb-6">
                                    <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                                        Concrete Grade / Mix design wise Dispatch
                                    </div>
                                    <div class="overflow-x-auto border border-slate-200 rounded-b">
                                        <table class="w-full text-left border-collapse min-w-[800px]">
                                            <thead>
                                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                    <th class="py-3 px-4 text-center" width="5%">#</th>
                                                    <th class="py-3 px-4" width="30%">Mix Design Name</th>
                                                    <th class="py-3 px-4 text-center" width="15%">Concrete Grade</th>
                                                    <th class="py-3 px-4 text-center" width="8%">UOM</th>
                                                    <th class="py-3 px-4 text-right" width="10%">Quantity</th>
                                                    <th class="py-3 px-4 text-right" width="10%">Avg Rate</th>
                                                    <th class="py-3 px-4 text-right" width="11%">Taxable Amt</th>
                                                    <th class="py-3 px-4 text-right" width="11%">Tax Amt</th>
                                                    <th class="py-3 px-4 text-right" width="11%">Total Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-[11px] font-semibold text-slate-700">
                                                <tr v-for="(row, idx) in reportData.mix_design_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                    <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                    <td class="py-3 px-4 font-bold text-slate-800">{{ row.mix_name }}</td>
                                                    <td class="py-3 px-4 text-center font-bold text-slate-650">{{ row.concrete_grade }}</td>
                                                    <td class="py-3 px-4 text-center text-slate-500">{{ row.uom }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-slate-900">{{ row.quantity }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.avg_rate) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                                                </tr>
                                                <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                    <td colspan="4" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total grade dispatches</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_dispatch_quantity }}</td>
                                                    <td></td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_dispatch_untaxed) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_dispatch_tax) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_dispatch_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-t border-b-0 font-bold text-[10px] text-slate-600 uppercase">
                                        Party wise Dispatch Summary
                                    </div>
                                    <div class="overflow-x-auto border border-slate-200 rounded-b">
                                        <table class="w-full text-left border-collapse min-w-[800px]">
                                            <thead>
                                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                                    <th class="py-3 px-4 text-center" width="5%">#</th>
                                                    <th class="py-3 px-4" width="40%">Customer / Party Name</th>
                                                    <th class="py-3 px-4 text-right" width="15%">Delivered Qty</th>
                                                    <th class="py-3 px-4 text-right" width="15%">Taxable Amt</th>
                                                    <th class="py-3 px-4 text-right" width="15%">Tax Amt</th>
                                                    <th class="py-3 px-4 text-right" width="15%">Total Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-[11px] font-semibold text-slate-700">
                                                <tr v-for="(row, idx) in reportData.party_summary" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                                    <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                                                    <td class="py-3 px-4 font-bold text-slate-800">{{ row.party_name }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-slate-900">{{ row.quantity }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_untaxed) }}</td>
                                                    <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.amount_tax) }}</td>
                                                    <td class="py-3 px-4 text-right font-bold text-[#1d2d3e] bg-slate-50/55">{{ formatCurrency(row.amount_total) }}</td>
                                                </tr>
                                                <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                                                    <td colspan="2" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total party volume</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_party_quantity }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_untaxed) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_tax) }}</td>
                                                    <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ formatCurrency(reportData.total_party_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Standard Transaction Ledger / Patron Preview -->
                            <div v-else class="overflow-x-auto border border-slate-200 rounded">
                                <table class="w-full text-left border-collapse min-w-[800px]">
                                    <thead>
                                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                                            <th class="py-3 px-4">Date</th>
                                            <th class="py-3 px-4">Particulars</th>
                                            <th class="py-3 px-4">Reference</th>
                                            <th class="py-3 px-4 text-right">Amount</th>
                                            <th class="py-3 px-4 text-center">Type</th>
                                            <th class="py-3 px-4 text-right">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-[11px] font-semibold text-slate-700">
                                        <!-- Opening -->
                                        <tr class="bg-slate-50 border-b border-slate-200">
                                            <td class="py-3 px-4 text-slate-400 italic">{{ startDate }}</td>
                                            <td class="py-3 px-4 font-bold text-[#1d2d3e] uppercase">Opening Balance</td>
                                            <td class="py-3 px-4">---</td>
                                            <td class="py-3 px-4 text-right font-bold">{{ formatCurrency(Math.abs(reportData.opening_balance)) }}</td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                                                    {{ reportData.opening_balance >= 0 ? 'DR' : 'CR' }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right font-bold text-[#1d2d3e]">
                                                {{ formatCurrency(reportData.opening_balance) }}
                                            </td>
                                        </tr>

                                        <!-- Lines -->
                                        <tr v-for="(trx, idx) in transactionsWithBalance" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                                            <td class="py-3 px-4 text-slate-500 whitespace-nowrap">{{ trx.date }}</td>
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-800 leading-tight">{{ trx.narration }}</div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">{{ trx.voucher_type }}</span>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="text-slate-800 font-bold tracking-tighter">{{ trx.voucher_no }}</div>
                                            </td>
                                            <td class="py-3 px-4 text-right text-slate-900 font-bold">
                                                {{ formatCurrency(trx.amount) }}
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="trx.type === 'Dr' ? 'bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]' : 'bg-[#fce4d6] text-[#c65911] border border-[#f8cbad]'">
                                                    {{ trx.type.toUpperCase() }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right font-black text-slate-800 bg-slate-50/55">
                                                {{ formatCurrency(Math.abs(trx.running_balance)) }}
                                                <span class="text-[9px] ml-1 uppercase text-slate-400">{{ trx.running_balance >= 0 ? 'Dr' : 'Cr' }}</span>
                                            </td>
                                        </tr>

                                        <!-- Closing -->
                                        <tr class="bg-[#1d2d3e] text-white">
                                            <td colspan="3" class="py-4 px-6 text-right font-bold uppercase text-[10px] tracking-wider text-slate-300">Net Closing Balance</td>
                                            <td colspan="3" class="py-4 px-8 text-right font-black text-lg tracking-tight">
                                                {{ formatCurrency(transactionsWithBalance.length > 0 ? Math.abs(transactionsWithBalance[transactionsWithBalance.length - 1].running_balance) : Math.abs(reportData.opening_balance)) }}
                                                <span class="text-xs ml-2 uppercase opacity-60 font-semibold">
                                                    {{ (transactionsWithBalance.length > 0 ? transactionsWithBalance[transactionsWithBalance.length - 1].running_balance : reportData.opening_balance) >= 0 ? 'Debit' : 'Credit' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="bg-white rounded border border-slate-200 py-20 text-center flex flex-col items-center shadow-sm">
                        <div class="w-14 h-14 bg-slate-50 border border-slate-200 rounded flex items-center justify-center mb-5 text-slate-400">
                            <ChartBarIcon class="h-7 w-7" />
                        </div>
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Execute Query Statement</h3>
                        <p class="text-slate-400 max-w-xs mx-auto mt-2 text-[11px] leading-relaxed">Choose a report from the master list catalog pane, set smart filter query scopes, and click Execute to load database rows.</p>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white !important;
        margin: 0;
        padding: 0;
    }
    .max-w-7xl {
        max-width: 100% !important;
    }
}
</style>
