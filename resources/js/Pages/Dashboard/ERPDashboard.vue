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
    <AppLayout title="ERP Live Dashboard">
        <div class="min-h-screen bg-[#f8fafc] p-6 lg:p-10">
            <div class=" ">
                
                <!-- Header & Filters -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-10">
                    <div>
                        <h1 class="text-xl font-black text-slate-900 tracking-tight">ERP Live Dashboard</h1>
                        <p class="text-slate-500 font-medium mt-1">Real-time business performance analytics</p>
                    </div>

                    <div class="bg-white p-2 rounded-md shadow-sm border border-slate-200 flex flex-wrap items-center gap-3">
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
                            <ArrowTrendingUpIcon class="w-24 h-24 text-indigo-600" />
                        </div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 bg-indigo-50 rounded-md text-indigo-600">
                                <ShoppingCartIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Sales Orders</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 tracking-tighter">
                            {{ formatCurrency(metrics.sales_orders) }}
                        </div>
                    </div>

                    <!-- Purchase Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-110 transition-transform">
                            <ArrowTrendingDownIcon class="w-24 h-24 text-amber-500" />
                        </div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 bg-amber-50 rounded-md text-amber-500">
                                <ShoppingCartIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Purchase Orders</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 tracking-tighter">
                            {{ formatCurrency(metrics.purchase_orders) }}
                        </div>
                    </div>

                    <!-- Invoiced Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-110 transition-transform">
                            <BanknotesIcon class="w-24 h-24 text-emerald-500" />
                        </div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 bg-emerald-50 rounded-md text-emerald-600">
                                <BanknotesIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Total Invoiced</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 tracking-tighter">
                            {{ formatCurrency(metrics.invoiced) }}
                        </div>
                    </div>

                    <!-- Outstanding Card -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 relative overflow-hidden group" :class="metrics.outstanding < 0 ? 'bg-rose-50/30' : 'bg-emerald-50/30'">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="p-3 rounded-2xl" :class="metrics.outstanding < 0 ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
                                <WalletIcon class="w-6 h-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Net Outstanding</span>
                        </div>
                        <div class="text-3xl font-black tracking-tighter" :class="metrics.outstanding < 0 ? 'text-rose-600' : 'text-emerald-600'">
                            {{ formatCurrency(metrics.outstanding) }}
                            <span class="text-xs uppercase ml-1 opacity-60">{{ metrics.outstanding >= 0 ? 'Receivable' : 'Payable' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Charts & Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Chart Column -->
                    <div class="lg:col-span-2 bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-slate-900">Operational Overview</h3>
                            <div class="flex gap-2">
                                <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-indigo-500 rounded-full"></div> <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Metrics</span></div>
                            </div>
                        </div>
                        <VueApexCharts width="100%" height="400" :options="chartOptions" :series="series" />
                    </div>

                    <!-- Recent Activity Column -->
                    <div class="bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-slate-900">Live Activity</h3>
                            <ClockIcon class="w-5 h-5 text-slate-300" />
                        </div>

                        <div class="space-y-6 overflow-y-auto max-h-[400px] pr-2 custom-scrollbar">
                            <div v-for="(act, idx) in recentActivity" :key="idx" class="flex items-center gap-4 group">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" :class="act.dr_cr === 'Dr' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
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

                        <BaseButton variant="text" severity="primary" class="mt-auto pt-6 text-xs font-black uppercase tracking-widest justify-center">
                            View All Transactions
                        </BaseButton>
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
