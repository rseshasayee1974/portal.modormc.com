<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import VueApexCharts from 'vue3-apexcharts';
import { 
    BanknotesIcon, 
    ShoppingCartIcon, 
    ArrowTrendingUpIcon, 
    ArrowTrendingDownIcon,
    WalletIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    patrons: Array,
    filters: Object
});

const metrics = ref({
    sales_orders: 0,
    purchase_orders: 0,
    invoiced: 0,
    payments_received: 0,
    payments_paid: 0,
    outstanding: 0
});

const recentActivity = ref([]);
const stockAlerts = ref([]);
const workOrders = ref([]);
const batches = ref([]);
const dispatches = ref([]);
const loading = ref(false);

const filterForm = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    patron_id: props.filters.patron_id
});

const fetchDashboardData = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('dashboard.data'), {
            params: filterForm.value
        });
        metrics.value = response.data.metrics;
        recentActivity.value = response.data.recent_transactions;
        stockAlerts.value = response.data.stock_alerts;
        workOrders.value = response.data.work_orders;
        batches.value = response.data.batches;
        dispatches.value = response.data.dispatches;
        updateCharts();
    } catch (error) {
        console.error("Failed to fetch dashboard data", error);
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0
    }).format(Math.abs(val));
};

// Chart Configurations
const chartOptions = ref({
    chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    plotOptions: { bar: { borderRadius: 10, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    colors: ['#4f46e5', '#f59e0b', '#10b981'],
    xaxis: { categories: ['Sales Orders', 'Purchase Orders', 'Invoiced'], labels: { style: { colors: '#94a3b8' } } },
    yaxis: { labels: { style: { colors: '#94a3b8' } } },
    grid: { borderColor: '#f1f5f9' },
    theme: { mode: 'light' }
});

const series = ref([{
    name: 'Value',
    data: [0, 0, 0]
}]);

const updateCharts = () => {
    series.value = [{
        name: 'Amount',
        data: [metrics.value.sales_orders, metrics.value.purchase_orders, metrics.value.invoiced]
    }];
};

onMounted(() => {
    fetchDashboardData();
});

watch(filterForm, () => {
    fetchDashboardData();
}, { deep: true });

</script>

<template>
    <AppLayout title="Main Dashboard">
        <div class="min-h-screen bg-[#f8fafc] p-6 lg:p-10">
            <div class=" ">
                
                <!-- Header & Filters -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-10">
                    <div>
                        <h1 class="text-lg font-black text-slate-900 tracking-tight">Main Dashboard</h1>
                        <p class="text-slate-500 font-medium mt-1">Real-time business performance analytics</p>
                    </div>

                    <div class="bg-white p-2 rounded-lg shadow-sm border border-slate-200 flex flex-wrap items-center gap-3">
                        <BaseDatePicker v-model="filterForm.start_date" label="From" class="w-40" size="small" />
                        <BaseDatePicker v-model="filterForm.end_date" label="To" class="w-40" size="small" />
                        <BaseSelect 
                            v-model="filterForm.patron_id" 
                            :options="patrons" 
                            optionLabel="legal_name" 
                            optionValue="id" 
                            label="Customer/Vendor"
                            placeholder="All Patrons"
                            class="w-64"
                            size="small"
                        />
                        <BaseButton variant="filled" severity="primary" @click="fetchDashboardData" :loading="loading" class="rounded-2xl">
                            <ArrowPathIcon class="h-4 w-4" />
                        </BaseButton>
                    </div>
                </div>

                <!-- KPI Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <!-- Sales Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-110 transition-transform">
                            <ArrowTrendingUpIcon class="w-12 h-12 text-indigo-600" />
                        </div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 bg-indigo-50 rounded-xl text-indigo-600">
                                <ShoppingCartIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Sales Orders</span>
                        </div>
                        <div class="text-xl font-black text-slate-900">
                            {{ formatCurrency(metrics.sales_orders) }}
                        </div>
                    </div>

                    <!-- Purchase Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-110 transition-transform">
                            <ArrowTrendingDownIcon class="w-12 h-12 text-amber-500" />
                        </div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 bg-amber-50 rounded-xl text-amber-500">
                                <ShoppingCartIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Purchase Orders</span>
                        </div>
                        <div class="text-xl font-black text-slate-900">
                            {{ formatCurrency(metrics.purchase_orders) }}
                        </div>
                    </div>

                    <!-- Invoiced Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-110 transition-transform">
                            <BanknotesIcon class="w-12 h-12 text-emerald-500" />
                        </div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 bg-emerald-50 rounded-xl text-emerald-600">
                                <BanknotesIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Total Invoiced</span>
                        </div>
                        <div class="text-xl font-black text-slate-900">
                            {{ formatCurrency(metrics.invoiced) }}
                        </div>
                    </div>

                    <!-- Outstanding Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group" :class="metrics.outstanding < 0 ? 'bg-rose-50/30' : 'bg-emerald-50/30'">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 rounded-xl" :class="metrics.outstanding < 0 ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
                                <WalletIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Net Outstanding</span>
                        </div>
                        <div class="text-xl font-black" :class="metrics.outstanding < 0 ? 'text-rose-600' : 'text-emerald-600'">
                            {{ formatCurrency(metrics.outstanding) }}
                            <span class="text-xs uppercase ml-1 opacity-60">{{ metrics.outstanding >= 0 ? 'Receivable' : 'Payable' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Charts & Alerts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
                    <!-- Chart Column -->
                    <div class="lg:col-span-2 bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-slate-900">Operational Overview</h3>
                        </div>
                        <VueApexCharts width="100%" height="400" :options="chartOptions" :series="series" />
                    </div>

                    <!-- Stock Alerts Column -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-slate-900">Stock Alerts</h3>
                            <ExclamationTriangleIcon class="w-5 h-5 text-rose-500" />
                        </div>

                        <div class="space-y-4 overflow-y-auto max-h-[400px] pr-2 custom-scrollbar">
                            <div v-for="alert in stockAlerts" :key="alert.id" class="p-4 rounded-lg bg-rose-50 border border-rose-100 group">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-black text-rose-900">{{ alert.title }}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 bg-rose-200 text-rose-700 rounded-full uppercase tracking-tighter">Low Stock</span>
                                </div>
                                <div class="flex items-end justify-between">
                                    <div>
                                        <div class="text-[10px] font-bold text-rose-400 uppercase tracking-widest">Current Stock</div>
                                        <div class="text-lg font-black text-rose-700">{{ alert.current_stock }} <span class="text-xs font-bold">{{ alert.unit }}</span></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] font-bold text-rose-400 uppercase tracking-widest">Alert Level</div>
                                        <div class="text-sm font-black text-rose-900">{{ alert.alert_level }} {{ alert.unit }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="stockAlerts.length === 0" class="flex flex-col items-center justify-center py-20 opacity-20">
                                <div class="p-4 bg-emerald-50 rounded-full mb-4">
                                    <ArrowPathIcon class="w-8 h-8 text-emerald-600" />
                                </div>
                                <span class="font-black uppercase tracking-widest text-xs text-center">All Material Levels<br>Within Limits</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Lists Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
                    <!-- Work Orders -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-slate-900 tracking-tight">Recent Work Orders</h3>
                            <BaseButton variant="text" size="small" class="text-[10px] uppercase font-black tracking-widest">View All</BaseButton>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm border-separate border-spacing-y-2">
                                <thead>
                                    <tr class="text-slate-400 uppercase text-[10px] font-black tracking-widest">
                                        <th class="px-4 py-2">WO Number</th>
                                        <th class="px-4 py-2">Customer</th>
                                        <th class="px-4 py-2">Grade</th>
                                        <th class="px-4 py-2 text-right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="wo in workOrders" :key="wo.id" class="bg-slate-50/50 hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-4 rounded-l-lg font-black text-indigo-600">{{ wo.number }}</td>
                                        <td class="px-4 py-4 font-bold text-slate-800">{{ wo.customer }}</td>
                                        <td class="px-4 py-4 text-slate-500">{{ wo.grade }}</td>
                                        <td class="px-4 py-4 rounded-r-lg text-right font-black text-slate-900">{{ wo.qty }} m³</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Batches -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-slate-900 tracking-tight">Recent Batches</h3>
                            <BaseButton variant="text" size="small" class="text-[10px] uppercase font-black tracking-widest">View Production</BaseButton>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm border-separate border-spacing-y-2">
                                <thead>
                                    <tr class="text-slate-400 uppercase text-[10px] font-black tracking-widest">
                                        <th class="px-4 py-2">Batch #</th>
                                        <th class="px-4 py-2">Work Order</th>
                                        <th class="px-4 py-2">Size</th>
                                        <th class="px-4 py-2 text-right">Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="b in batches" :key="b.id" class="bg-slate-50/50 hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-4 rounded-l-lg font-black text-amber-600">{{ b.no }}</td>
                                        <td class="px-4 py-4 font-bold text-slate-800">{{ b.wo }}</td>
                                        <td class="px-4 py-4 text-slate-500">{{ b.size }} m³</td>
                                        <td class="px-4 py-4 rounded-r-lg text-right font-black text-slate-900">{{ b.time }}</td>
                                    </tr>
                                    <tr v-if="batches.length === 0">
                                        <td colspan="4" class="py-10 text-center opacity-20 font-black uppercase text-xs">No Recent Batches</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Active Dispatches (Full Width) -->
                <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 mb-10">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-black text-slate-900 tracking-tight">Active Dispatches</h3>
                        <BaseButton variant="text" size="small" class="text-[10px] uppercase font-black tracking-widest">Live GPS Feed</BaseButton>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-separate border-spacing-y-2">
                            <thead>
                                <tr class="text-slate-400 uppercase text-[10px] font-black tracking-widest">
                                    <th class="px-4 py-2">Ticket</th>
                                    <th class="px-4 py-2">Vehicle</th>
                                    <th class="px-4 py-2">Customer</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2 text-right">Load Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in dispatches" :key="d.id" class="bg-slate-50/50 hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-4 rounded-l-lg font-black text-emerald-600">{{ d.ticket }}</td>
                                    <td class="px-4 py-4 font-bold text-slate-800">{{ d.vehicle }}</td>
                                    <td class="px-4 py-4 text-slate-500">{{ d.customer }}</td>
                                    <td class="px-4 py-4">
                                        <span :class="d.status === 'Billed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" class="px-2 py-1 rounded text-[10px] font-black uppercase">{{ d.status }}</span>
                                    </td>
                                    <td class="px-4 py-4 rounded-r-lg text-right font-black text-slate-900">{{ d.qty }} m³</td>
                                </tr>
                                <tr v-if="dispatches.length === 0">
                                    <td colspan="5" class="py-10 text-center opacity-20 font-black uppercase text-xs">No Active Dispatches</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Final Row: Charts & Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Chart Column (Previously handled above, but ensure no duplication) -->
                    <!-- Recent Activity Column -->
                    <div class="lg:col-span-3 bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-slate-900">Financial Activity</h3>
                            <ClockIcon class="w-5 h-5 text-slate-300" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-6 overflow-y-auto max-h-[400px] pr-2 custom-scrollbar">
                                <div v-for="(act, idx) in recentActivity" :key="idx" class="flex items-center gap-4 group">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0" :class="act.dr_cr === 'Dr' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                                        <span class="font-black text-xs">{{ act.dr_cr }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-black text-slate-800 truncate">{{ act.particulars }}</div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ act.type }} • {{ act.date }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-black text-slate-900">{{ formatCurrency(act.amount) }}</div>
                                    </div>
                                </div>
                                <div v-if="recentActivity.length === 0" class="flex flex-col items-center justify-center py-20 opacity-20">
                                    <ExclamationTriangleIcon class="w-12 h-12 mb-2" />
                                    <span class="font-black uppercase tracking-widest text-xs">No Recent Data</span>
                                </div>
                            </div>
                            
                            <!-- Production Summary Placeholder or second chart -->
                            <div class="bg-slate-50 p-6 rounded-2xl border border-dashed border-slate-200 flex items-center justify-center">
                                <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">Production Analytics Summary</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
</style>
