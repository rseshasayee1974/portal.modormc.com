<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    BanknotesIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    CircleStackIcon,
    ClipboardDocumentListIcon,
    CubeIcon,
    CurrencyRupeeIcon,
    ExclamationTriangleIcon,
    ReceiptPercentIcon,
    TruckIcon,
    WalletIcon,
    UserGroupIcon,
    InboxStackIcon,
    SparklesIcon,
    ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    patrons: Array,
    filters: Object,
    initialData: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const activePlant = computed(() => page.props.active_plant || null);

const metricsLoading = ref(false);
const financeLoading = ref(false);
const dispatchLoading = ref(false);
const leaderboardLoading = ref(false);
const stockLoading = ref(false);
const activityLoading = ref(false);

const loading = computed(() => {
    return metricsLoading.value ||
           financeLoading.value ||
           dispatchLoading.value ||
           leaderboardLoading.value ||
           stockLoading.value ||
           activityLoading.value;
});

const errorMessage = ref('');
const lastUpdated = ref(props.initialData?.generated_at || '');
const pollIntervalMs = 30000;
let pollTimer = null;
let filterTimer = null;

const filterForm = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    patron_id: props.filters.patron_id,
});

const defaultMetrics = {
    sales_revenue: 0,
    purchase_spend: 0,
    dispatch_revenue: 0,
    dispatch_quantity: 0,
    dispatch_trips: 0,
    collections: 0,
    payments: 0,
    receivables: 0,
    payables: 0,
    cash_delta: 0,
    stock_value: 0,
    low_stock_count: 0,
    open_sales_orders: 0,
    active_batches: 0,
};

const metrics = ref({
    ...defaultMetrics,
    ...(props.initialData?.metrics || {}),
});

const financeTrend = ref(props.initialData?.finance_trend || { labels: [], series: [] });
const dispatchStatus = ref(props.initialData?.dispatch_status || []);
const customerLeaderboard = ref(props.initialData?.customer_leaderboard || []);
const stockSnapshot = ref(props.initialData?.stock_snapshot || []);
const recentTransactions = ref(props.initialData?.recent_transactions || []);

const activeTimeTab = ref('Monthly');

const queryParams = computed(() => ({
    start_date: normalizeDate(filterForm.value.start_date),
    end_date: normalizeDate(filterForm.value.end_date),
    patron_id: filterForm.value.patron_id || null,
}));

const plantLabel = computed(() => activePlant.value?.plant_name || 'Live ERP Command Center');

// Chart data mapping
const salesTrendSeries = computed(() => {
    const rawSales = financeTrend.value.series?.find(s => s.name === 'Sales')?.data || [];
    const rawPurchases = financeTrend.value.series?.find(s => s.name === 'Purchase')?.data || [];
    return [
        { name: 'Sales', data: rawSales },
        { name: 'Purchase', data: rawPurchases }
    ];
});

const salesTrendOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        background: 'transparent',
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '55%',
            borderRadius: 6,
        },
    },
    dataLabels: { enabled: false },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    colors: ['#f87171', '#f97316'], // Soft Peach & Solid Orange
    xaxis: {
        categories: financeTrend.value.labels || [],
        labels: { style: { colors: '#64748b', fontSize: '11px', fontWeight: 500 } }
    },
    yaxis: {
        labels: {
            formatter: (val) => compactCurrency(val),
            style: { colors: '#64748b', fontSize: '11px' }
        }
    },
    fill: { opacity: 1 },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4,
    },
    tooltip: {
        y: { formatter: (val) => formatCurrency(val) }
    },
    legend: {
        position: 'bottom',
        fontFamily: 'Inter',
        labels: { colors: '#64748b' }
    }
}));

// Overall Information - Customers Donut Ring Chart
const customerDonutSeries = computed(() => [65, 35]); // Mock ratio representing First Time (65%) vs Return (35%)
const customerDonutOptions = {
    chart: {
        type: 'donut',
    },
    labels: ['First Time', 'Returning'],
    colors: ['#10b981', '#f59e0b'],
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    legend: { show: false },
    plotOptions: {
        pie: {
            donut: {
                size: '78%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Retention',
                        formatter: () => '65%',
                        color: '#0f172a',
                        fontSize: '16px',
                        fontWeight: '700'
                    }
                }
            }
        }
    },
    tooltip: { enabled: true }
};

// Monthly Target Semicircle progress
const targetPercent = computed(() => {
    const monthlyTarget = 1500000; // Target: 15 Lakhs
    const currentSales = metrics.value.sales_revenue || 0;
    const pct = Math.min(100, Math.max(0, (currentSales / monthlyTarget) * 100));
    return Number(pct.toFixed(2));
});

const monthlyTargetSeries = computed(() => [targetPercent.value]);
const monthlyTargetOptions = computed(() => ({
    chart: {
        type: 'radialBar',
        sparkline: { enabled: true }
    },
    plotOptions: {
        radialBar: {
            startAngle: -90,
            endAngle: 90,
            track: {
                background: '#f1f5f9',
                strokeWidth: '97%',
                margin: 5,
                dropShadow: {
                    enabled: false
                }
            },
            dataLabels: {
                name: { show: false },
                value: {
                    offsetY: -2,
                    fontSize: '30px',
                    fontWeight: '800',
                    color: '#1e1b4b',
                    formatter: (val) => `${val}%`
                }
            }
        }
    },
    fill: {
        type: 'gradient',
        gradient: {
            shade: 'light',
            shadeIntensity: 0.4,
            inverseColors: false,
            opacityFrom: 1,
            opacityTo: 1,
            stops: [0, 50, 100],
            colorStops: [
                { offset: 0, color: '#6366f1', opacity: 1 },
                { offset: 100, color: '#10b981', opacity: 1 }
            ]
        }
    },
    colors: ['#6366f1'],
    labels: ['Monthly Target Progress']
}));

// Monthly Sales bar chart (vertical bars)
const monthlySalesSeries = computed(() => {
    const data = financeTrend.value.series?.find(s => s.name === 'Sales')?.data || [];
    return [{ name: 'Monthly Sales', data }];
});
const monthlySalesOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false }
    },
    colors: ['#4f46e5'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '40%',
        }
    },
    dataLabels: { enabled: false },
    xaxis: {
        categories: financeTrend.value.labels || [],
        labels: { style: { colors: '#64748b', fontSize: '10px' } }
    },
    yaxis: { show: false },
    grid: { show: false }
}));

// Statistics area-line chart
const statsSeries = computed(() => {
    // Return custom timeframes. For demo, we multiply values slightly based on Monthly, Quarterly, Annually select.
    const multiplier = activeTimeTab.value === 'Quarterly' ? 3 : activeTimeTab.value === 'Annually' ? 12 : 1;
    
    const rawSales = (financeTrend.value.series?.find(s => s.name === 'Sales')?.data || []).map(v => v * multiplier);
    const rawPurchases = (financeTrend.value.series?.find(s => s.name === 'Purchase')?.data || []).map(v => v * multiplier);
    
    return [
        { name: 'Target KPI', data: rawSales },
        { name: 'Actual Performance', data: rawPurchases }
    ];
});

const statsOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false }
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.35,
            opacityTo: 0.05,
            stops: [0, 90, 100]
        }
    },
    colors: ['#4f46e5', '#38bdf8'],
    dataLabels: { enabled: false },
    xaxis: {
        categories: financeTrend.value.labels || [],
        labels: { style: { colors: '#94a3b8', fontSize: '10px' } }
    },
    yaxis: {
        labels: {
            formatter: (val) => compactCurrency(val),
            style: { colors: '#94a3b8' }
        }
    },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        labels: { colors: '#64748b' }
    }
}));

function applyDashboardPayload(data = {}) {
    errorMessage.value = '';
    metrics.value = { ...defaultMetrics, ...(data.metrics || {}) };
    financeTrend.value = data.finance_trend || { labels: [], series: [] };
    dispatchStatus.value = data.dispatch_status || [];
    customerLeaderboard.value = data.customer_leaderboard || [];
    stockSnapshot.value = data.stock_snapshot || [];
    recentTransactions.value = data.recent_transactions || [];
    lastUpdated.value = data.generated_at || new Date().toISOString();
}

const fetchDashboardData = async ({ silent = false, refresh = false } = {}) => {
    errorMessage.value = '';

    const params = { ...queryParams.value };
    if (refresh) {
        params.refresh = true;
    }

    const handleError = (error) => {
        if (error?.response?.status === 401 || error?.response?.status === 419) {
            if (pollTimer) clearInterval(pollTimer);
            window.location.href = '/login';
        }
        console.error('Failed to fetch dashboard component data', error);
        errorMessage.value = 'Unable to refresh some live dashboard data right now.';
    };

    const loadMetrics = async () => {
        if (!silent) metricsLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.metrics'), { params });
            metrics.value = data.metrics;
        } catch (error) {
            handleError(error);
        } finally {
            metricsLoading.value = false;
        }
    };

    const loadFinanceTrend = async () => {
        if (!silent) financeLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.finance-trend'), { params });
            financeTrend.value = data.finance_trend;
        } catch (error) {
            handleError(error);
        } finally {
            financeLoading.value = false;
        }
    };

    const loadDispatchStatus = async () => {
        if (!silent) dispatchLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.dispatch-status'), { params });
            dispatchStatus.value = data.dispatch_status;
        } catch (error) {
            handleError(error);
        } finally {
            dispatchLoading.value = false;
        }
    };

    const loadCustomerLeaderboard = async () => {
        if (!silent) leaderboardLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.customer-leaderboard'), { params });
            customerLeaderboard.value = data.customer_leaderboard;
        } catch (error) {
            handleError(error);
        } finally {
            leaderboardLoading.value = false;
        }
    };

    const loadStock = async () => {
        if (!silent) stockLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.stock'), { params });
            stockSnapshot.value = data.stock_snapshot;
        } catch (error) {
            handleError(error);
        } finally {
            stockLoading.value = false;
        }
    };

    const loadRecentActivity = async () => {
        if (!silent) activityLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.recent-activity'), { params });
            recentTransactions.value = data.recent_transactions;
        } catch (error) {
            handleError(error);
        } finally {
            activityLoading.value = false;
        }
    };

    await Promise.all([
        loadMetrics(),
        loadFinanceTrend(),
        loadDispatchStatus(),
        loadCustomerLeaderboard(),
        loadStock(),
        loadRecentActivity(),
    ]);
    lastUpdated.value = new Date().toISOString();
};

watch(
    () => [filterForm.value.start_date, filterForm.value.end_date, filterForm.value.patron_id],
    () => {
        if (filterTimer) clearTimeout(filterTimer);
        filterTimer = setTimeout(() => fetchDashboardData(), 250);
    }
);

onMounted(() => {
    fetchDashboardData({ silent: true });
    pollTimer = setInterval(() => fetchDashboardData({ silent: true }), pollIntervalMs);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (filterTimer) clearTimeout(filterTimer);
});

function normalizeDate(value) {
    if (!value) return null;
    if (value instanceof Date) return value.toISOString().slice(0, 10);
    return value;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function compactCurrency(value) {
    return new Intl.NumberFormat('en-IN', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(Number(value || 0));
}

function formatNumber(value, digits = 0) {
    return new Intl.NumberFormat('en-IN', {
        maximumFractionDigits: digits,
        minimumFractionDigits: digits,
    }).format(Number(value || 0));
}

function formatDateTime(value) {
    if (!value) return 'Just now';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout title="SaaS ERP Analytics Center">
        <div class="min-h-screen bg-[#f8fafc] pb-12 text-slate-900 font-sans">
            <div class="mx-auto max-w-[1700px] px-4 py-6 sm:px-6 lg:px-8">
                
                <!-- Main Header & Controls -->
                <header class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between mb-8">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-emerald-700">
                                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                World-Class SaaS ERP
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-500/10 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-indigo-700">
                                <BuildingOffice2Icon class="size-3.5" />
                                {{ plantLabel }}
                            </span>
                        </div>
                        <h1 class="text-3xl font-black tracking-tight text-slate-900">Analytics Command Center</h1>
                        <p class="mt-1 text-slate-500 text-sm font-medium">Real-time performance metrics, target benchmarks, and visual sales trends</p>
                    </div>

                    <!-- Date & Patron Filters -->
                    <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200/80 flex flex-wrap items-center gap-3">
                        <BaseDatePicker v-model="filterForm.start_date" label="From" class="w-36" size="small" />
                        <BaseDatePicker v-model="filterForm.end_date" label="To" class="w-36" size="small" />
                        <BaseSelect
                            v-model="filterForm.patron_id"
                            :options="patrons"
                            optionLabel="legal_name"
                            optionValue="id"
                            label="Patron Filter"
                            placeholder="All patrons"
                            filter
                            showClear
                            class="w-56"
                            size="small"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center justify-center p-2.5 rounded-xl bg-slate-950 text-white hover:bg-slate-800 transition shadow-md"
                            @click="fetchDashboardData({ refresh: true })"
                        >
                            <ArrowPathIcon class="size-4" :class="loading ? 'animate-spin' : ''" />
                        </button>
                    </div>
                </header>

                <div v-if="errorMessage" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    {{ errorMessage }}
                </div>

                <!-- SECTION 1: Top Soft-Colored KPI Cards (Screenshot 1 top row) -->
                <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <template v-if="metricsLoading">
                        <!-- Total Sales Skeleton -->
                        <div class="bg-[#fdf2f2] border border-red-100 rounded-3xl p-6 animate-pulse h-[142px] flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <div class="h-4 bg-red-200 rounded w-1/3"></div>
                                <div class="size-10 bg-white/60 rounded-xl shadow-sm"></div>
                            </div>
                            <div class="h-8 bg-red-200 rounded w-1/2 mt-4"></div>
                            <div class="h-3 bg-red-200/80 rounded w-1/4 mt-2"></div>
                        </div>

                        <!-- Total Purchase Skeleton -->
                        <div class="bg-[#edfbf4] border border-emerald-100 rounded-3xl p-6 animate-pulse h-[142px] flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <div class="h-4 bg-emerald-200 rounded w-1/3"></div>
                                <div class="size-10 bg-white/60 rounded-xl shadow-sm"></div>
                            </div>
                            <div class="h-8 bg-emerald-200 rounded w-1/2 mt-4"></div>
                            <div class="h-3 bg-emerald-200/80 rounded w-1/4 mt-2"></div>
                        </div>

                        <!-- Total Expenses Skeleton -->
                        <div class="bg-[#f0f9ff] border border-sky-100 rounded-3xl p-6 animate-pulse h-[142px] flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <div class="h-4 bg-sky-200 rounded w-1/3"></div>
                                <div class="size-10 bg-white/60 rounded-xl shadow-sm"></div>
                            </div>
                            <div class="h-8 bg-sky-200 rounded w-1/2 mt-4"></div>
                            <div class="h-3 bg-sky-200/80 rounded w-1/4 mt-2"></div>
                        </div>

                        <!-- Invoice Due Skeleton -->
                        <div class="bg-[#fefce8] border border-amber-100 rounded-3xl p-6 animate-pulse h-[142px] flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <div class="h-4 bg-amber-200 rounded w-1/3"></div>
                                <div class="size-10 bg-white/60 rounded-xl shadow-sm"></div>
                            </div>
                            <div class="h-8 bg-amber-200 rounded w-1/2 mt-4"></div>
                            <div class="h-3 bg-amber-200/80 rounded w-1/4 mt-2"></div>
                        </div>
                    </template>
                    <template v-else>
                        <!-- Total Sales -->
                        <div class="bg-[#fdf2f2] border border-red-100 rounded-3xl p-6 transition duration-300 hover:shadow-md">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-bold text-red-700 uppercase tracking-wider">Total Sales</span>
                                <div class="p-2.5 bg-white rounded-xl text-red-500 shadow-sm">
                                    <ArrowTrendingUpIcon class="size-5" />
                                </div>
                            </div>
                            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.sales_revenue) }}</h2>
                            <p class="mt-2 text-xs font-semibold text-red-600">+6% since last month</p>
                        </div>

                        <!-- Total Purchase -->
                        <div class="bg-[#edfbf4] border border-emerald-100 rounded-3xl p-6 transition duration-300 hover:shadow-md">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-bold text-emerald-700 uppercase tracking-wider">Total Purchase</span>
                                <div class="p-2.5 bg-white rounded-xl text-emerald-500 shadow-sm">
                                    <ArrowTrendingDownIcon class="size-5" />
                                </div>
                            </div>
                            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.purchase_spend) }}</h2>
                            <p class="mt-2 text-xs font-semibold text-emerald-600">+22% since last month</p>
                        </div>

                        <!-- Total Expenses -->
                        <div class="bg-[#f0f9ff] border border-sky-100 rounded-3xl p-6 transition duration-300 hover:shadow-md">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-bold text-sky-700 uppercase tracking-wider">Total Expenses</span>
                                <div class="p-2.5 bg-white rounded-xl text-sky-500 shadow-sm">
                                    <BanknotesIcon class="size-5" />
                                </div>
                            </div>
                            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.payments) }}</h2>
                            <p class="mt-2 text-xs font-semibold text-sky-600">+10% since last month</p>
                        </div>

                        <!-- Invoice Due -->
                        <div class="bg-[#fefce8] border border-amber-100 rounded-3xl p-6 transition duration-300 hover:shadow-md">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-bold text-amber-700 uppercase tracking-wider">Invoice Due</span>
                                <div class="p-2.5 bg-white rounded-xl text-amber-500 shadow-sm">
                                    <WalletIcon class="size-5" />
                                </div>
                            </div>
                            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.receivables) }}</h2>
                            <p class="mt-2 text-xs font-semibold text-amber-600">+36% since last month</p>
                        </div>
                    </template>
                </section>

                <!-- SECTION 2: Secondary Performance Cards (Screenshot 1 middle row) -->
                <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <template v-if="metricsLoading">
                        <div v-for="i in 3" :key="'sec-sk-' + i" class="bg-white border border-slate-200/80 rounded-3xl p-6 flex flex-col justify-between shadow-sm h-[178px] animate-pulse">
                            <div>
                                <div class="flex justify-between items-start mb-3">
                                    <div class="space-y-3 w-1/2">
                                        <div class="h-8 bg-slate-200 rounded w-full"></div>
                                        <div class="h-4 bg-slate-200 rounded w-2/3"></div>
                                    </div>
                                    <div class="size-8 bg-slate-100 rounded"></div>
                                </div>
                                <div class="h-5 bg-slate-100 rounded w-1/3 mt-4"></div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <!-- Total Profit -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 flex flex-col justify-between shadow-sm relative overflow-hidden group hover:border-indigo-200 transition">
                            <div>
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="text-3xl font-black text-slate-950 tracking-tight">{{ formatCurrency(metrics.sales_revenue - metrics.purchase_spend) }}</h3>
                                        <p class="text-sm font-bold text-slate-400 mt-1 uppercase tracking-wider">Total Profit</p>
                                    </div>
                                    <ClipboardDocumentListIcon class="size-8 text-indigo-100 group-hover:text-indigo-200 transition-colors" />
                                </div>
                                <span class="inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-700">+35% vs Last Month</span>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <a href="#" class="text-xs font-bold text-red-500 hover:text-red-600 inline-flex items-center gap-1">
                                    View <ArrowTopRightOnSquareIcon class="size-3.5" />
                                </a>
                            </div>
                        </div>

                        <!-- Total Payment Returns -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 flex flex-col justify-between shadow-sm relative overflow-hidden group hover:border-indigo-200 transition">
                            <div>
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="text-3xl font-black text-slate-950 tracking-tight">{{ formatCurrency((metrics.sales_revenue * 0.04)) }}</h3>
                                        <p class="text-sm font-bold text-slate-400 mt-1 uppercase tracking-wider">Total Payment Returns</p>
                                    </div>
                                    <ReceiptPercentIcon class="size-8 text-indigo-100 group-hover:text-indigo-200 transition-colors" />
                                </div>
                                <span class="inline-flex rounded-full bg-rose-500/10 px-2.5 py-1 text-[11px] font-bold text-rose-700">-20% vs Last Month</span>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <a href="#" class="text-xs font-bold text-red-500 hover:text-red-600 inline-flex items-center gap-1">
                                    View <ArrowTopRightOnSquareIcon class="size-3.5" />
                                </a>
                            </div>
                        </div>

                        <!-- Total Cash Expenses -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 flex flex-col justify-between shadow-sm relative overflow-hidden group hover:border-indigo-200 transition">
                            <div>
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="text-3xl font-black text-slate-950 tracking-tight">{{ formatCurrency(metrics.payments) }}</h3>
                                        <p class="text-sm font-bold text-slate-400 mt-1 uppercase tracking-wider">Total Cash Paid</p>
                                    </div>
                                    <WalletIcon class="size-8 text-indigo-100 group-hover:text-indigo-200 transition-colors" />
                                </div>
                                <span class="inline-flex rounded-full bg-rose-500/10 px-2.5 py-1 text-[11px] font-bold text-rose-700">-12% vs Last Month</span>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <a href="#" class="text-xs font-bold text-red-500 hover:text-red-600 inline-flex items-center gap-1">
                                    View <ArrowTopRightOnSquareIcon class="size-3.5" />
                                </a>
                            </div>
                        </div>
                    </template>
                </section>

                <!-- SECTION 3: Layout Split & Target Gauges (Screenshot 2 elements) -->
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Active Customers KPI Card -->
                    <div v-if="leaderboardLoading" class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm flex items-center justify-between h-[122px] animate-pulse">
                        <div class="space-y-3 w-1/2">
                            <div class="h-3 bg-slate-200 rounded w-1/3"></div>
                            <div class="h-7 bg-slate-200 rounded w-3/4"></div>
                        </div>
                        <div class="size-14 bg-indigo-50/50 rounded-2xl"></div>
                    </div>
                    <div v-else class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Customers</p>
                            <h3 class="text-3xl font-black text-slate-950">3,782</h3>
                            <span class="inline-flex items-center gap-1 mt-3 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                +11.01%
                            </span>
                        </div>
                        <div class="p-4 bg-indigo-50 text-indigo-600 rounded-2xl">
                            <UserGroupIcon class="size-7" />
                        </div>
                    </div>

                    <!-- Total Orders KPI Card -->
                    <div v-if="metricsLoading" class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm flex items-center justify-between h-[122px] animate-pulse">
                        <div class="space-y-3 w-1/2">
                            <div class="h-3 bg-slate-200 rounded w-1/3"></div>
                            <div class="h-7 bg-slate-200 rounded w-3/4"></div>
                        </div>
                        <div class="size-14 bg-indigo-50/50 rounded-2xl"></div>
                    </div>
                    <div v-else class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Active Sales Orders</p>
                            <h3 class="text-3xl font-black text-slate-950">{{ metrics.open_sales_orders }}</h3>
                            <span class="inline-flex items-center gap-1 mt-3 rounded-full bg-rose-500/10 px-2 py-0.5 text-xs font-bold text-rose-700">
                                -9.05%
                            </span>
                        </div>
                        <div class="p-4 bg-indigo-50 text-indigo-600 rounded-2xl">
                            <InboxStackIcon class="size-7" />
                        </div>
                    </div>

                    <!-- Monthly Target Semicircle progress gauge (Screenshot 2 right side) -->
                    <div class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm lg:row-span-2 flex flex-col justify-between">
                        <div v-if="metricsLoading" class="animate-pulse space-y-6 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="h-5 bg-slate-200 rounded w-1/3"></div>
                                    <div class="h-4 bg-slate-100 rounded w-1/4"></div>
                                </div>
                                
                                <!-- Gauge chart container -->
                                <div class="flex flex-col items-center justify-center my-4">
                                    <div class="size-36 rounded-full border-[12px] border-slate-100 border-t-indigo-200 flex items-center justify-center">
                                        <div class="h-6 bg-slate-200 rounded w-12"></div>
                                    </div>
                                    <div class="h-4 bg-slate-100 rounded w-16 mt-4"></div>
                                </div>

                                <div class="h-4 bg-slate-200 rounded w-3/4 mx-auto mt-4"></div>
                            </div>

                            <!-- Target values list -->
                            <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-3 gap-2 text-center">
                                <div v-for="i in 3" :key="i" class="space-y-2">
                                    <div class="h-3 bg-slate-100 rounded w-2/3 mx-auto"></div>
                                    <div class="h-4 bg-slate-200 rounded w-1/2 mx-auto"></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-black text-slate-900">Monthly Target</h3>
                                    <span class="text-xs font-bold text-slate-400">Target setting</span>
                                </div>
                                
                                <!-- Gauge chart container -->
                                <div class="flex flex-col items-center justify-center my-4">
                                    <VueApexCharts type="radialBar" height="230" :options="monthlyTargetOptions" :series="monthlyTargetSeries" class="w-full" />
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-bold text-emerald-700 mt-2">
                                        +10%
                                    </span>
                                </div>

                                <p class="text-center text-sm text-slate-600 leading-relaxed font-medium px-4 mt-2">
                                    You earned <span class="font-bold text-slate-900">{{ formatCurrency(metrics.sales_revenue) }}</span> during this period. Keep up your good work!
                                </p>
                            </div>

                            <!-- Target values list -->
                            <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Target</p>
                                    <p class="text-sm font-black text-slate-900 mt-1 flex items-center justify-center gap-0.5 text-red-500">
                                        1.5M <span class="text-xs">↓</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Revenue</p>
                                    <p class="text-sm font-black text-slate-900 mt-1 flex items-center justify-center gap-0.5 text-emerald-500">
                                        {{ compactCurrency(metrics.sales_revenue) }} <span class="text-xs">↑</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Payments</p>
                                    <p class="text-sm font-black text-slate-900 mt-1 flex items-center justify-center gap-0.5 text-emerald-500">
                                        {{ compactCurrency(metrics.payments) }} <span class="text-xs">↑</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Sales Bar Chart (Screenshot 2 middle) -->
                    <div class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm lg:col-span-2">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">Monthly Sales</h3>
                                <p class="text-xs text-slate-400 mt-0.5 font-medium">Breakdown across periods</p>
                            </div>
                        </div>
                        <div v-if="financeLoading" class="animate-pulse h-[180px] bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center text-slate-400 font-bold">
                            Loading Sales Chart...
                        </div>
                        <VueApexCharts v-show="!financeLoading" type="bar" height="180" :options="monthlySalesOptions" :series="monthlySalesSeries" />
                    </div>
                </section>

                <!-- SECTION 4: Sales vs Purchase and Overall Info (Screenshot 1 bottom row) -->
                <section class="grid grid-cols-1 lg:grid-cols-5 gap-8 mb-8">
                    <!-- Sales vs Purchase Bar Chart -->
                    <div class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm lg:col-span-3">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">Sales vs Purchase</h3>
                                <p class="text-xs text-slate-400 mt-0.5 font-medium">Comparison analytics</p>
                            </div>
                            <div class="relative">
                                <select class="text-xs font-bold text-slate-600 bg-slate-100 border-0 rounded-full px-4 py-1.5 focus:ring-0 focus:outline-none">
                                    <option>This Year</option>
                                    <option>Last Year</option>
                                </select>
                            </div>
                        </div>
                        
                        <div v-if="financeLoading" class="animate-pulse h-[340px] bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center text-slate-400 font-bold">
                            Loading Trends...
                        </div>
                        <VueApexCharts v-show="!financeLoading" type="bar" height="340" :options="salesTrendOptions" :series="salesTrendSeries" />
                    </div>

                    <!-- Overall Information Card -->
                    <div class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm lg:col-span-2 flex flex-col justify-between">
                        <div v-if="leaderboardLoading" class="animate-pulse space-y-6 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-6">
                                    <div class="h-5 bg-slate-200 rounded w-1/3"></div>
                                    <div class="h-7 bg-slate-100 rounded-full w-24"></div>
                                </div>

                                <p class="h-4 bg-slate-200 rounded w-1/2 mb-4"></p>

                                <!-- Donut overview section -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center mb-6">
                                    <div class="flex justify-center">
                                        <div class="size-32 rounded-full border-[10px] border-slate-100 flex items-center justify-center"></div>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <div class="h-3 bg-slate-100 rounded w-1/2 mb-2"></div>
                                            <div class="h-6 bg-slate-200 rounded w-1/3"></div>
                                        </div>
                                        <div>
                                            <div class="h-3 bg-slate-100 rounded w-1/2 mb-2"></div>
                                            <div class="h-6 bg-slate-200 rounded w-1/3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer mini counters -->
                            <div class="border-t border-slate-100 pt-5 grid grid-cols-3 gap-2 text-center">
                                <div v-for="i in 3" :key="i" class="space-y-2">
                                    <div class="h-5 bg-slate-200 rounded w-1/2 mx-auto"></div>
                                    <div class="h-3 bg-slate-100 rounded w-2/3 mx-auto"></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-black text-slate-900">Overall Information</h3>
                                    <div class="relative">
                                        <select class="text-xs font-bold text-slate-600 bg-slate-100 border-0 rounded-full px-4 py-1.5 focus:ring-0 focus:outline-none">
                                            <option>Last 6 Months</option>
                                            <option>This Month</option>
                                        </select>
                                    </div>
                                </div>

                                <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Customers Overview</p>

                                <!-- Donut overview section -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center mb-6">
                                    <div class="flex justify-center">
                                        <VueApexCharts type="donut" height="150" :options="customerDonutOptions" :series="customerDonutSeries" />
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">First Time</span>
                                            </div>
                                            <p class="text-2xl font-black text-slate-900">5.5K</p>
                                            <span class="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 mt-1">+25%</span>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span class="size-2 rounded-full bg-amber-500"></span>
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Return</span>
                                            </div>
                                            <p class="text-2xl font-black text-slate-900">3.5K</p>
                                            <span class="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 mt-1">+21%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer mini counters -->
                            <div class="border-t border-slate-100 pt-5 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <h4 class="text-xl font-black text-slate-900">6,987</h4>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1">Suppliers</p>
                                </div>
                                <div>
                                    <h4 class="text-xl font-black text-slate-900">4,896</h4>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1">Customers</p>
                                </div>
                                <div>
                                    <h4 class="text-xl font-black text-slate-900">487</h4>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1">Orders</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SECTION 5: Double Line Performance Analytics (Screenshot 2 bottom) -->
                <section class="grid grid-cols-1 gap-8">
                    <div class="bg-white border border-slate-200/80 rounded-[2rem] p-6 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-8">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">Statistics</h3>
                                <p class="text-xs text-slate-400 mt-0.5 font-medium">Target vs actual benchmarks</p>
                            </div>
                            
                            <!-- Annually / Monthly / Quarterly tabs -->
                            <div class="inline-flex bg-slate-100 p-1 rounded-xl">
                                <button 
                                    type="button" 
                                    class="rounded-lg px-4 py-1.5 text-xs font-bold transition"
                                    :class="activeTimeTab === 'Monthly' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                    @click="activeTimeTab = 'Monthly'"
                                >
                                    Monthly
                                </button>
                                <button 
                                    type="button" 
                                    class="rounded-lg px-4 py-1.5 text-xs font-bold transition"
                                    :class="activeTimeTab === 'Quarterly' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                    @click="activeTimeTab = 'Quarterly'"
                                >
                                    Quarterly
                                </button>
                                <button 
                                    type="button" 
                                    class="rounded-lg px-4 py-1.5 text-xs font-bold transition"
                                    :class="activeTimeTab === 'Annually' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                    @click="activeTimeTab = 'Annually'"
                                >
                                    Annually
                                </button>
                            </div>
                        </div>

                        <div v-if="financeLoading" class="animate-pulse h-[300px] bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center text-slate-400 font-bold">
                            Loading Statistics Chart...
                        </div>
                        <VueApexCharts v-show="!financeLoading" type="area" height="300" :options="statsOptions" :series="statsSeries" />
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes auth-panel {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
