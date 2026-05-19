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
        ]
    },
    {
        id: 'production',
        name: 'Production & Dispatch',
        reports: [
            { id: 'sales', name: 'Sales & Dispatches', description: 'Invoice listings, dispatch volumes and concrete grades' },
            { id: 'production_batch', name: 'Batch Production Sheet', description: 'Batch mix designs, target vs actual aggregate loads' },
        ]
    },
    {
        id: 'machines',
        name: 'Fleet & Machinery',
        reports: [
            { id: 'machines_list', name: 'Machine Fleet Inventory', description: 'Active fleet list, mixer capacities and vehicle specs' },
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
    }
});

watch(reportType, () => {
    reportData.value = null;
});

const generateReport = async () => {
    if (!selectedId.value && reportType.value === 'ledger') return;
    
    loading.value = true;
    try {
        const response = await axios.get(route('reports.generate'), {
            params: {
                type: reportType.value,
                id: selectedId.value,
                patron_id: patronId.value,
                start_date: startDate.value,
                end_date: endDate.value,
                export: 'view'
            }
        });
        reportData.value = response.data;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const exportPdf = () => {
    const url = route('reports.generate', {
        type: reportType.value,
        id: selectedId.value,
        patron_id: patronId.value,
        start_date: startDate.value,
        end_date: endDate.value,
        export: 'pdf'
    });
    window.open(url, '_blank');
};

const exportExcel = () => {
    const url = route('reports.generate', {
        type: reportType.value,
        id: selectedId.value,
        patron_id: patronId.value,
        start_date: startDate.value,
        end_date: endDate.value,
        export: 'excel'
    });
    window.open(url, '_blank');
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
                                <div v-if="['patron', 'sales', 'purchase', 'payment', 'receipt'].includes(reportType)" class="lg:col-span-1">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Select Partner</span>
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

                                <div v-if="reportType === 'machines_list'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Active Fleet</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.transactions.length }} Vehicles</span>
                                    </div>
                                    <TruckIcon class="w-5 h-5 text-slate-400" />
                                </div>

                                <div v-if="reportType === 'payroll_personnel'" class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Plant Headcount</span>
                                        <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.transactions.length }} Employees</span>
                                    </div>
                                    <UsersIcon class="w-5 h-5 text-slate-400" />
                                </div>
                            </div>

                            <!-- Dynamic View: Inventory Stock Levels -->
                            <div v-if="reportType === 'inventory_stock'">
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
