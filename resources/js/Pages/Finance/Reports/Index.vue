<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
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
    TableCellsIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    ledgers: Array,
    patrons: Array,
    filters: Object,
});

const reportType = ref('ledger'); // 'ledger', 'patron', 'purchase', 'sales', 'payment', 'receipt'
const selectedId = ref(null); // ledger_id
const patronId = ref(null);
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

const loading = ref(false);
const reportData = ref(null);

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
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

</script>

<template>
    <AppLayout title="Accounting Reports">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Report Type Selector & Filters -->
                <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden mb-8 no-print transition-all hover:shadow-2xl hover:shadow-slate-200/50">
                    <div class="bg-slate-900 p-8 text-white">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="p-3 bg-indigo-500 rounded-2xl shadow-lg">
                                <ChartBarIcon class="h-8 w-8 text-white" />
                            </div>
                            <div>
                                <h1 class="text-2xl font-black tracking-tight">Accounting Reports</h1>
                                <p class="text-indigo-300 font-medium">Generate ledger and patron statements</p>
                            </div>
                        </div>

                        <div class="mb-8">
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-indigo-400 mb-4">Select Report Category</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 bg-slate-800/50 p-2 rounded-2xl border border-slate-700/50">
                                <button 
                                    @click="reportType = 'ledger'; reportData = null"
                                    class="py-3 px-4 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                                    :class="reportType === 'ledger' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                                >
                                    <DocumentTextIcon class="h-4 w-4" />
                                    Ledger
                                </button>
                                <button 
                                    @click="reportType = 'patron'; reportData = null"
                                    class="py-3 px-4 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                                    :class="reportType === 'patron' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                                >
                                    <UserGroupIcon class="h-4 w-4" />
                                    Patron
                                </button>
                                <button 
                                    @click="reportType = 'sales'; reportData = null"
                                    class="py-3 px-4 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                                    :class="reportType === 'sales' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                                >
                                    <TableCellsIcon class="h-4 w-4" />
                                    Sales
                                </button>
                                <button 
                                    @click="reportType = 'purchase'; reportData = null"
                                    class="py-3 px-4 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                                    :class="reportType === 'purchase' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                                >
                                    <ArrowDownTrayIcon class="h-4 w-4" />
                                    Purchase
                                </button>
                                <button 
                                    @click="reportType = 'payment'; reportData = null"
                                    class="py-3 px-4 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                                    :class="reportType === 'payment' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                                >
                                    <ArrowPathIcon class="h-4 w-4" />
                                    Payment
                                </button>
                                <button 
                                    @click="reportType = 'receipt'; reportData = null"
                                    class="py-3 px-4 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                                    :class="reportType === 'receipt' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                                >
                                    <ArrowPathIcon class="h-4 w-4" />
                                    Receipt
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-end bg-slate-800/30 p-6 rounded-2xl border border-slate-700/30">
                            <!-- Target Dropdown (Ledger) -->
                            <div v-if="reportType === 'ledger'" class="lg:col-span-1">
                                <BaseSelect 
                                    v-model="selectedId"
                                    :options="ledgers"
                                    optionLabel="title"
                                    optionValue="id"
                                    label="Account Ledger"
                                    placeholder="Choose account..."
                                    filter
                                    dark
                                />
                            </div>

                            <!-- Patron Dropdown -->
                            <div v-if="reportType !== 'ledger'" class="lg:col-span-1">
                                <BaseSelect 
                                    v-model="patronId"
                                    :options="patrons"
                                    optionLabel="legal_name"
                                    optionValue="id"
                                    :filterFields="['legal_name', 'email', 'phone', 'contact_person']"
                                    label="Select Patron"
                                    placeholder="All Patrons"
                                    filter
                                    showClear
                                    dark
                                />
                            </div>

                            <!-- Spacer if needed for alignment -->
                            <div v-if="reportType === 'ledger'" class="hidden lg:block"></div>

                            <!-- Date Range -->
                            <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                                <BaseDatePicker v-model="startDate" label="From" dark fluid />
                                <BaseDatePicker v-model="endDate" label="To" dark fluid />
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end gap-3 border-t border-slate-800 pt-6">
                            <BaseButton variant="outlined" severity="secondary" @click="exportExcel">
                                <TableCellsIcon class="h-4 w-4 mr-2" />
                                Export Excel
                            </BaseButton>
                            <BaseButton variant="outlined" severity="primary" @click="exportPdf">
                                <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
                                Download PDF
                            </BaseButton>
                            <BaseButton variant="filled" severity="primary" @click="generateReport" :loading="loading">
                                <ArrowPathIcon class="h-4 w-4 mr-2" />
                                Generate Report
                            </BaseButton>
                        </div>
                    </div>
                </div>

                <!-- Report Display Section -->
                <div v-if="reportData" class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-500">
                    
                    <!-- View Header -->
                    <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-8 bg-indigo-600 rounded-full"></div>
                            <h2 class="text-lg font-black text-slate-800">Statement Preview</h2>
                        </div>
                        <BaseButton variant="outlined" severity="primary" size="sm" @click="exportPdf">
                            <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
                            Download PDF
                        </BaseButton>
                    </div>

                    <div class="p-8 overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 border-b-2 border-slate-100">
                                    <th class="pb-6 px-4">Date</th>
                                    <th class="pb-6 px-4">Particulars</th>
                                    <th class="pb-6 px-4">Reference</th>
                                    <th class="pb-6 px-4 text-right">Amount</th>
                                    <th class="pb-6 px-4 text-center">Type</th>
                                    <th class="pb-6 px-4 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium">
                                <!-- Opening -->
                                <tr class="bg-indigo-50/30 border-b border-indigo-100/50">
                                    <td class="py-6 px-4 text-slate-400 italic">{{ startDate }}</td>
                                    <td class="py-6 px-4 font-black text-indigo-900 uppercase tracking-tighter">Opening Balance</td>
                                    <td class="py-6 px-4">---</td>
                                    <td class="py-6 px-4 text-right font-bold">{{ formatCurrency(Math.abs(reportData.opening_balance)) }}</td>
                                    <td class="py-6 px-4 text-center">
                                        <span class="px-2 py-1 rounded text-[10px] font-black" :class="reportData.opening_balance >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                                            {{ reportData.opening_balance >= 0 ? 'DR' : 'CR' }}
                                        </span>
                                    </td>
                                    <td class="py-6 px-4 text-right font-black" :class="reportData.opening_balance >= 0 ? 'text-indigo-600' : 'text-rose-600'">
                                        {{ formatCurrency(reportData.opening_balance) }}
                                    </td>
                                </tr>

                                <!-- Lines -->
                                <tr v-for="(trx, idx) in transactionsWithBalance" :key="idx" class="border-b border-slate-50 hover:bg-slate-50/50 transition-all">
                                    <td class="py-5 px-4 text-slate-500 whitespace-nowrap">{{ trx.date }}</td>
                                    <td class="py-5 px-4">
                                        <div class="font-bold text-slate-800 leading-tight">{{ trx.narration }}</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-black tracking-widest uppercase">{{ trx.voucher_type }}</span>
                                        </div>
                                    </td>
                                    <td class="py-5 px-4">
                                        <div class="text-indigo-600 font-black tracking-tighter">{{ trx.voucher_no }}</div>
                                    </td>
                                    <td class="py-5 px-4 text-right text-slate-900 font-bold">
                                        {{ formatCurrency(trx.amount) }}
                                    </td>
                                    <td class="py-5 px-4 text-center">
                                        <span class="px-2 py-1 rounded text-[10px] font-black" :class="trx.type === 'Dr' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                                            {{ trx.type.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="py-5 px-4 text-right font-black text-slate-800 bg-slate-50/30">
                                        {{ formatCurrency(Math.abs(trx.running_balance)) }}
                                        <span class="text-[9px] ml-1 uppercase text-slate-400">{{ trx.running_balance >= 0 ? 'Dr' : 'Cr' }}</span>
                                    </td>
                                </tr>

                                <!-- Closing -->
                                <tr class="bg-slate-900 text-white shadow-2xl">
                                    <td colspan="3" class="py-8 px-6 text-right font-black uppercase tracking-[0.3em] text-[11px] text-slate-400">Net Closing Balance</td>
                                    <td colspan="3" class="py-8 px-8 text-right font-black text-2xl tracking-tighter">
                                        {{ formatCurrency(transactionsWithBalance.length > 0 ? Math.abs(transactionsWithBalance[transactionsWithBalance.length - 1].running_balance) : Math.abs(reportData.opening_balance)) }}
                                        <span class="text-xs ml-3 uppercase opacity-40 font-medium">
                                            {{ (transactionsWithBalance.length > 0 ? transactionsWithBalance[transactionsWithBalance.length - 1].running_balance : reportData.opening_balance) >= 0 ? 'Debit' : 'Credit' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="bg-white rounded-3xl p-32 text-center border-4 border-dashed border-slate-100 flex flex-col items-center">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-8 animate-pulse">
                        <ChartBarIcon class="h-12 w-12 text-slate-200" />
                    </div>
                    <h3 class="text-2xl font-black text-slate-800">Ready to Generate</h3>
                    <p class="text-slate-400 max-w-sm mx-auto mt-4 text-lg">Select a report type and target account above to begin your financial analysis.</p>
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
    .py-12 {
        padding: 0 !important;
    }
}
</style>
