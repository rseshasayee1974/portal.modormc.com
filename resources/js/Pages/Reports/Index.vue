<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import ReportScheduleModal from './components/ReportScheduleModal.vue';
import Dialog from 'primevue/dialog';

// Import child report components
import StandardLedgerReport from './components/StandardLedgerReport.vue';
import SalesRegisterReport from './components/SalesRegisterReport.vue';
import PurchaseRegisterReport from './components/PurchaseRegisterReport.vue';
import SiloStockValuationReport from './components/SiloStockValuationReport.vue';
import InventoryStockReport from './components/InventoryStockReport.vue';
import InventoryInwardReport from './components/InventoryInwardReport.vue';
import ProductionBatchReport from './components/ProductionBatchReport.vue';
import MachinesListReport from './components/MachinesListReport.vue';
import MachineSummaryReport from './components/MachineSummaryReport.vue';
import VehiclePLReport from './components/VehiclePLReport.vue';
import PayrollPersonnelReport from './components/PayrollPersonnelReport.vue';
import PurchaseReport from './components/PurchaseReport.vue';
import SalesReport from './components/SalesReport.vue';
import Gstr1Report from './components/Gstr1Report.vue';
import Gstr3bReport from './components/Gstr3bReport.vue';
import TdsCertificateReport from './components/TdsCertificateReport.vue';
import EsiPfChallanReport from './components/EsiPfChallanReport.vue';

import { 
    ChartBarIcon,
    DocumentTextIcon, 
    UserGroupIcon,
    ArrowPathIcon,
    ArrowDownTrayIcon,
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
        case 'compliance': return DocumentTextIcon;
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
    },
    {
        id: 'compliance',
        name: 'Taxation & Compliance',
        reports: [
            { id: 'gstr1', name: 'GSTR-1 Report', description: 'B2B, B2C, CDNR, and EXP invoice breakups for GST returns' },
            { id: 'gstr3b', name: 'GSTR-3B Return Summary', description: 'Table 3.1 outward supplies and Table 4 eligible ITC summary' },
            { id: 'tds_certificate', name: 'TDS Certificate Generation', description: 'TDS details and deduction summary for a given patron' },
            { id: 'esi_pf_challan', name: 'ESI/PF Challan Generation', description: 'Monthly Employee State Insurance and Provident Fund statutory challan calculations' }
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

const getReportComponent = (type) => {
    switch (type) {
        case 'sales_register': return SalesRegisterReport;
        case 'purchase_register': return PurchaseRegisterReport;
        case 'silo_stock_valuation': return SiloStockValuationReport;
        case 'inventory_stock': return InventoryStockReport;
        case 'inventory_inward': return InventoryInwardReport;
        case 'production_batch': return ProductionBatchReport;
        case 'machines_list': return MachinesListReport;
        case 'machine_summary': return MachineSummaryReport;
        case 'vehicle_pl': return VehiclePLReport;
        case 'payroll_personnel': return PayrollPersonnelReport;
        case 'purchase': return PurchaseReport;
        case 'sales': return SalesReport;
        case 'gstr1': return Gstr1Report;
        case 'gstr3b': return Gstr3bReport;
        case 'tds_certificate': return TdsCertificateReport;
        case 'esi_pf_challan': return EsiPfChallanReport;
        default: return StandardLedgerReport;
    }
};

const handlePageChange = (newPage) => {
    currentPage.value = newPage;
    generateReport();
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

const startQueuedExport = async (url) => {
    loading.value = true;
    try {
        const response = await axios.get(url);
        if (response.data && response.data.queued) {
            exportProgress.value = { status: 'queued', progress: 0 };
            checkExportStatus(response.data.status_key);
        } else {
            alert('Export failed to start.');
        }
    } catch (error) {
        console.error('Error initiating export:', error);
        alert('Export failed to start.');
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
    startQueuedExport(url);
};

const exportExcel = () => {
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
    startQueuedExport(url);
};

const schedules = ref([]);
const isScheduleModalOpen = ref(false);

const fetchSchedules = async () => {
    try {
        const response = await axios.get(route('reports.schedules.index'));
        schedules.value = response.data;
    } catch (err) {
        console.error('Failed to fetch schedules:', err);
    }
};

const deleteSchedule = async (id) => {
    const result = await Swal.fire({
        title: 'Cancel Schedule?',
        text: 'This report will no longer be sent automatically.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, keep it',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) return;

    try {
        await axios.delete(route('reports.schedules.destroy', { schedule: id }));
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Schedule cancelled successfully.',
            showConfirmButton: false,
            timer: 2000
        });
        fetchSchedules();
    } catch (err) {
        console.error('Failed to delete schedule:', err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to cancel schedule.'
        });
    }
};

const currentReportParams = computed(() => {
    return {
        id: selectedId.value,
        patron_id: patronId.value,
        gst_type: gstType.value,
        payment_status: paymentStatus.value,
        valuation_method: valuationMethod.value,
    };
});

const openScheduleModal = () => {
    isScheduleModalOpen.value = true;
};

onMounted(() => {
    fetchSchedules();
});

const showShareModal = ref(false);
const shareExpiry = ref('7');
const shareLink = ref('');
const isGeneratingLink = ref(false);

const openShareReport = () => {
    shareExpiry.value = '7';
    shareLink.value = '';
    showShareModal.value = true;
};

const generateShareLink = async () => {
    isGeneratingLink.value = true;
    try {
        const response = await axios.post(route('reports.share'), {
            document_type: 'report',
            expiry: shareExpiry.value,
            report_params: {
                type: reportType.value,
                id: selectedId.value,
                patron_id: patronId.value,
                start_date: startDate.value,
                end_date: endDate.value,
                valuation_method: valuationMethod.value,
                gst_type: gstType.value,
                payment_status: paymentStatus.value,
            }
        });
        
        if (response.data && response.data.url) {
            shareLink.value = response.data.url;
        } else {
            Swal.fire('Error', 'Failed to generate share link.', 'error');
        }
    } catch (error) {
        console.error('Error sharing report:', error);
        Swal.fire('Error', error.response?.data?.message || 'An error occurred while generating the link.', 'error');
    } finally {
        isGeneratingLink.value = false;
    }
};

const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(shareLink.value);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Share link copied to clipboard!',
            showConfirmButton: false,
            timer: 2000
        });
    } catch (err) {
        console.error('Failed to copy text: ', err);
    }
};

const shareWhatsApp = () => {
    const text = encodeURIComponent(`Here is the link to view the report: ${shareLink.value}`);
    window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
};

const shareEmail = () => {
    const reportName = activeReport.value?.name || 'Report';
    const subject = encodeURIComponent(`${reportName} - Shared Link`);
    const body = encodeURIComponent(`Dear Customer,\n\nPlease find the secure link to view the report online:\n\n${shareLink.value}\n\nThank you.`);
    window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
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
                                <div v-if="['patron', 'sales', 'purchase', 'payment', 'receipt', 'sales_register', 'purchase_register', 'tds_certificate'].includes(reportType)" class="lg:col-span-1">
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
                            <div class="mt-6 pt-4 border-t border-slate-100 flex justify-end items-center">
                                <!-- <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Smart Filter floorplan</span> -->
                                <div class="flex gap-2">
                                    <button 
                                        @click="openScheduleModal"
                                        class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded transition-all flex items-center gap-1.5"
                                    >
                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                        </svg>
                                        Schedule Report
                                    </button>
                                    <button 
                                        @click="exportExcel"
                                        class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-bold rounded transition-all flex items-center gap-1.5"
                                    >
                                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.85 21H18.75C19.99 21 21 19.99 21 18.75V5.25C21 4.01 19.99 3 18.75 3H11.85V21Z" fill="#107C41" />
                                            <rect x="13.5" y="5.5" width="2.5" height="2.5" rx="0.5" fill="#ffffff" fill-opacity="0.3" />
                                            <rect x="17" y="5.5" width="2.5" height="2.5" rx="0.5" fill="#ffffff" fill-opacity="0.3" />
                                            <rect x="13.5" y="9.5" width="2.5" height="2.5" rx="0.5" fill="#ffffff" fill-opacity="0.3" />
                                            <rect x="17" y="9.5" width="2.5" height="2.5" rx="0.5" fill="#ffffff" fill-opacity="0.3" />
                                            <rect x="13.5" y="13.5" width="2.5" height="2.5" rx="0.5" fill="#ffffff" fill-opacity="0.3" />
                                            <rect x="17" y="13.5" width="2.5" height="2.5" rx="0.5" fill="#ffffff" fill-opacity="0.3" />
                                            <path d="M12.15 21H5.25C4.01 21 3 19.99 3 18.75V5.25C3 4.01 4.01 3 5.25 3H12.15V21Z" fill="#0E6836" />
                                            <path d="M5.5 7.5L9.5 16.5M9.5 7.5L5.5 16.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Export Excel
                                    </button>
                                    <button 
                                        @click="exportPdf"
                                        class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold rounded transition-all flex items-center gap-1.5"
                                    >
                                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#E21A1A" />
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.5C12.5 6.5 13 8.5 12 10.5C11 8.5 11.5 6.5 12 6.5ZM9.5 14C8 14.5 6 15 7.5 16C9 17 10.5 15 9.5 14ZM14.5 14C16 15 17.5 16 16.5 16.8C15.5 17.6 13.5 15.5 14.5 14Z" fill="white" />
                                            <path d="M12.2 11.2C12.8 11.8 14 12.8 13.8 13.5C13.6 14.2 11 15 10.2 13.8C9.4 12.6 11.6 10.6 12.2 11.2Z" fill="white" fill-opacity="0.8" />
                                        </svg>
                                        Export PDF
                                    </button>
                                    <button 
                                        @click="openShareReport"
                                        class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded transition-all flex items-center gap-1.5"
                                    >
                                        <!-- Share Icon -->
                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 10.742l-1.996-1.002a2.003 2.003 0 110-3.048l1.996-1.002a2.003 2.003 0 113.417 1.41l-1.996 1.002a2.003 2.003 0 010 3.048l1.996 1.002a2.003 2.003 0 11-3.417-1.41z" />
                                        </svg>
                                        Share Report
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

                    <!-- Active Schedules Panel -->
                    <div class="bg-white rounded border border-slate-200 shadow-sm mb-6 no-print">
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <div>
                                <h3 class="text-xs font-bold text-[#1d2d3e] uppercase tracking-wider">Automated Schedules for this Plant</h3>
                            </div>
                            <span class="text-[9px] bg-indigo-50 border border-indigo-200 rounded text-indigo-600 px-2 py-0.5 font-bold uppercase tracking-widest">
                                {{ schedules.length }} ACTIVE
                            </span>
                        </div>
                        <div class="p-5">
                            <div v-if="schedules.length === 0" class="text-center py-6 text-xs text-slate-400">
                                No recurring schedules configured for this plant. Click <strong>Schedule Report</strong> to create one.
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 bg-slate-50/50">
                                            <th class="px-4 py-2 font-bold text-slate-500 uppercase">Report Type</th>
                                            <th class="px-4 py-2 font-bold text-slate-500 uppercase">Frequency</th>
                                            <th class="px-4 py-2 font-bold text-slate-500 uppercase">Time</th>
                                            <th class="px-4 py-2 font-bold text-slate-500 uppercase">Recipients</th>
                                            <th class="px-4 py-2 font-bold text-slate-500 uppercase">Last Run</th>
                                            <th class="px-4 py-2 font-bold text-slate-500 uppercase text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="sch in schedules" :key="sch.id" class="hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-3 font-semibold text-slate-700 capitalize">
                                                {{ sch.report_type.replace('_', ' ') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-[10px] font-bold uppercase text-slate-600">
                                                    {{ sch.frequency }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-medium text-slate-600">{{ sch.schedule_time }}</td>
                                            <td class="px-4 py-3 text-slate-500 max-w-xs truncate" :title="sch.email_recipients">
                                                {{ sch.email_recipients }}
                                            </td>
                                            <td class="px-4 py-3 text-slate-400">
                                                {{ sch.last_run_at ? new Date(sch.last_run_at).toLocaleString('en-IN') : 'Never' }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button @click="deleteSchedule(sch.id)" class="text-rose-600 hover:text-rose-800 font-bold hover:underline">
                                                    Cancel Schedule
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
                                    class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold rounded transition-all flex items-center gap-1"
                                >
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#E21A1A" />
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.5C12.5 6.5 13 8.5 12 10.5C11 8.5 11.5 6.5 12 6.5ZM9.5 14C8 14.5 6 15 7.5 16C9 17 10.5 15 9.5 14ZM14.5 14C16 15 17.5 16 16.5 16.8C15.5 17.6 13.5 15.5 14.5 14Z" fill="white" />
                                        <path d="M12.2 11.2C12.8 11.8 14 12.8 13.8 13.5C13.6 14.2 11 15 10.2 13.8C9.4 12.6 11.6 10.6 12.2 11.2Z" fill="white" fill-opacity="0.8" />
                                    </svg>
                                    Save PDF
                                </button>
                            </div>
                        </div>

                        <div class="p-5">
                            <component
                                :is="getReportComponent(reportType)"
                                :report-data="reportData"
                                :current-page="currentPage"
                                :start-date="startDate"
                                :valuation-method="valuationMethod"
                                @page-change="handlePageChange"
                            />
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

        <!-- Report Scheduling Modal -->
        <ReportScheduleModal 
            :is-open="isScheduleModalOpen"
            :report-type="reportType"
            :report-params="currentReportParams"
            @close="isScheduleModalOpen = false"
            @saved="fetchSchedules"
        />

        <!-- Premium Share Report Dialog -->
        <Dialog v-model:visible="showShareModal" modal header="Share Report" :style="{ width: '450px' }" class="premium-dialog">
            <div class="p-2">
                <p class="text-xs text-slate-500 mb-4">
                    Generate a secure, read-only link to share this report with your customer.
                </p>

                <!-- Expiry Options -->
                <div class="mb-5">
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2">Link Expiry</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button 
                            v-for="opt in [
                                { label: '1 Day', value: '1' },
                                { label: '7 Days', value: '7' },
                                { label: '30 Days', value: '30' },
                                { label: 'Never', value: '0' }
                            ]" 
                            :key="opt.value"
                            type="button"
                            @click="shareExpiry = opt.value"
                            class="px-2 py-2 text-xs font-semibold rounded-lg border text-center transition-all"
                            :class="[
                                shareExpiry === opt.value
                                ? 'bg-indigo-50 border-indigo-500 text-indigo-700'
                                : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <!-- Action Button or Generated Link Display -->
                <div v-if="!shareLink" class="mt-6 flex justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" text severity="secondary" @click="showShareModal = false" class="!text-xs font-bold uppercase" />
                    <Button 
                        label="Generate Link" 
                        icon="pi pi-link" 
                        severity="primary" 
                        @click="generateShareLink" 
                        :loading="isGeneratingLink"
                        class="!text-xs font-bold uppercase bg-indigo-600 hover:bg-indigo-700 text-white border-0" 
                    />
                </div>

                <div v-else class="mt-6 space-y-4 animate-in fade-in duration-200">
                    <!-- Link Textbox -->
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Secure Share Link</label>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                readonly 
                                :value="shareLink" 
                                class="flex-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-600 dark:text-slate-300 font-mono focus:outline-none"
                            />
                            <button 
                                @click="copyShareLink"
                                class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold flex items-center gap-1 transition-all"
                            >
                                <i class="pi pi-copy"></i>
                                Copy
                            </button>
                        </div>
                    </div>

                    <!-- Social Share Action Buttons -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                        <button 
                            @click="shareWhatsApp"
                            class="flex-1 py-2 px-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-sm"
                        >
                            <i class="pi pi-whatsapp text-sm"></i>
                            WhatsApp
                        </button>
                        <button 
                            @click="shareEmail"
                            class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-sm"
                        >
                            <i class="pi pi-envelope text-sm"></i>
                            Email
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>
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
