<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { computed, onMounted, onUnmounted, ref, watch, defineAsyncComponent } from 'vue';
const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
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
const feedsLoading = ref(false);

const loading = computed(() => {
    return metricsLoading.value ||
           financeLoading.value ||
           dispatchLoading.value ||
           leaderboardLoading.value ||
           stockLoading.value ||
           activityLoading.value ||
           feedsLoading.value;
});

const errorMessage = ref('');
const lastUpdated = ref(props.initialData?.generated_at || '');
const activeFeed = ref('dispatches');
const pollIntervalMs = 30000;
let pollTimer = null;
let filterTimer = null;

const isDark = ref(false);

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

const moduleCards = ref(props.initialData?.module_cards || []);
const financeTrend = ref(props.initialData?.finance_trend || { labels: [], series: [] });
const dispatchStatus = ref(props.initialData?.dispatch_status || []);
const customerLeaderboard = ref(props.initialData?.customer_leaderboard || []);
const stockSnapshot = ref(props.initialData?.stock_snapshot || []);
const stockAlerts = ref(props.initialData?.stock_alerts || []);
const recentTransactions = ref(props.initialData?.recent_transactions || []);
const salesOrders = ref(props.initialData?.sales_orders || []);
const dispatches = ref(props.initialData?.dispatches || []);
const purchaseOrders = ref(props.initialData?.purchase_orders || []);

const feedTabs = [
    { key: 'sales_orders', label: 'Sales Orders' },
    { key: 'dispatches', label: 'Dispatches' },
];

const statusPalette = ['#0f766e', '#c2410c', '#2563eb', '#7c3aed', '#dc2626', '#475569'];

const queryParams = computed(() => ({
    start_date: normalizeDate(filterForm.value.start_date),
    end_date: normalizeDate(filterForm.value.end_date),
    patron_id: filterForm.value.patron_id || null,
}));

const plantLabel = computed(() => activePlant.value?.plant_name || 'Live ERP Workspace');

const summaryCards = computed(() => [
    {
        key: 'sales_revenue',
        title: 'Sales Revenue',
        value: metrics.value.sales_revenue,
        meta: `${formatNumber(metrics.value.dispatch_trips)} live trips`,
        icon: CurrencyRupeeIcon,
        tone: 'amber',
    },
    {
        key: 'purchase_spend',
        title: 'Purchase Spend',
        value: metrics.value.purchase_spend,
        meta: `${formatCurrency(metrics.value.payables)} payable`,
        icon: ClipboardDocumentListIcon,
        tone: 'sky',
    },
    {
        key: 'collections',
        title: 'Collections',
        value: metrics.value.collections,
        meta: `${formatCurrency(metrics.value.cash_delta)} net cash`,
        icon: BanknotesIcon,
        tone: 'emerald',
    },
    {
        key: 'receivables',
        title: 'Receivables',
        value: metrics.value.receivables,
        meta: `${formatCurrency(metrics.value.payables)} payables`,
        icon: WalletIcon,
        tone: 'violet',
    },
    {
        key: 'dispatch_quantity',
        title: 'Dispatch Volume',
        value: metrics.value.dispatch_quantity,
        meta: `${formatNumber(metrics.value.dispatch_trips)} trips`,
        icon: TruckIcon,
        tone: 'rose',
    },
    {
        key: 'stock_value',
        title: 'Stock Value',
        value: metrics.value.stock_value,
        meta: `${formatNumber(metrics.value.low_stock_count)} low stock items`,
        icon: CubeIcon,
        tone: 'slate',
    },
]);

const financeChartSeries = computed(() => financeTrend.value.series || []);
const financeChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        background: 'transparent',
        animations: { easing: 'easeinout', speed: 450 },
        fontFamily: 'Inter, system-ui, sans-serif',
        offsetY: 6,
    },
    stroke: {
        curve: 'smooth',
        width: [3, 3, 3, 2],
        dashArray: [0, 0, 0, 6],
    },
    colors: ['#b45309', '#0284c7', '#059669', '#7c3aed'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.22,
            opacityTo: 0.02,
            stops: [0, 90, 100],
        },
    },
    dataLabels: { enabled: false },
    legend: {
        show: false,
        offsetX: 0,
        offsetY: 0,
        fontSize: '12px',
        itemMargin: { horizontal: 16, vertical: 0 },
        markers: {
            width: 8,
            height: 8,
            radius: 12,
            offsetX: -2,
            strokeWidth: 0,
        },
        labels: {
            colors: isDark.value? '#cbd5e1' : '#64748b',
            useSeriesColors: false
        },
    },
    grid: {
        borderColor: isDark.value? '#334155' : '#e2e8f0',
        strokeDashArray: 5,
        xaxis: { lines: { show: false } },
        padding: { top: 32, right: 12, bottom: 0, left: 12 }, // space for floating legend
    },
    xaxis: {
        categories: financeTrend.value.labels || [],
        labels: {
            style: {
                colors: isDark.value? '#64748b' : '#94a3b8',
                fontSize: '11px',
                fontWeight: 600,
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: [
        {
            show: true,
            seriesName: 'Sales',
            labels: {
                formatter: (value) => compactCurrency(value),
                style: {
                    colors: '#b45309',
                    fontSize: '11px',
                    fontWeight: 600,
                },
            },
        },
        {
            show: true,
            opposite: true,
            seriesName: 'Purchase',
            labels: {
                formatter: (value) => compactCurrency(value),
                style: {
                    colors: '#0284c7',
                    fontSize: '11px',
                    fontWeight: 600,
                },
            },
        },
        {
            show: false,
            seriesName: 'Sales',
        },
        {
            show: false,
            seriesName: 'Sales',
        },
    ],
    tooltip: {
        theme: isDark.value? 'dark' : 'light',
        y: {
            formatter: (value) => formatCurrency(value),
        },
    },
}));

const dispatchStatusSeries = computed(() => dispatchStatus.value.map((item) => item.value));
const dispatchStatusOptions = computed(() => ({
    chart: {
        type: 'donut',
        toolbar: { show: false },
        fontFamily: 'Inter, system-ui, sans-serif',
    },
    labels: dispatchStatus.value.map((item) => item.label),
    colors: statusPalette,
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: isDark.value? '#94a3b8' : '#64748b' },
    },
    dataLabels: {
        enabled: true,
        style: { fontSize: '11px', fontWeight: 700 },
    },
    stroke: {
        width: 0,
    },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Trips',
                        formatter: () => `${metrics.value.dispatch_trips || 0}`,
                        color: isDark.value? '#ffffff' : '#0f172a',
                    },
                    value: {
                        color: isDark.value? '#94a3b8' : '#64748b',
                    },
                },
            },
        },
    },
    tooltip: {
        theme: isDark.value? 'dark' : 'light',
    },
    theme: {
        mode: isDark.value? 'dark' : 'light',
    },
}));

const leaderboardSeries = computed(() => [{
    name: 'Revenue',
    data: customerLeaderboard.value.map((item) => item.revenue),
}]);

const leaderboardOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        background: 'transparent',
        fontFamily: 'Inter, system-ui, sans-serif',
    },
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 8,
            distributed: true,
            barHeight: '62%',
        },
    },
    colors: ['#0f766e', '#0ea5e9', '#f59e0b', '#7c3aed', '#ef4444', '#475569'],
    dataLabels: {
        enabled: false,
    },
    xaxis: {
        categories: customerLeaderboard.value.map((item) => item.customer),
        labels: {
            formatter: (value) => compactCurrency(value),
            style: { colors: isDark.value? '#64748b' : '#94a3b8', fontSize: '11px' },
        },
    },
    yaxis: {
        labels: {
            style: {
                colors: isDark.value? '#cbd5e1' : '#475569',
                fontSize: '11px',
                fontWeight: 750,
            },
        },
    },
    grid: {
        borderColor: isDark.value? '#334155' : '#e2e8f0',
        strokeDashArray: 5,
    },
    tooltip: {
        theme: isDark.value? 'dark' : 'light',
        y: { formatter: (value) => formatCurrency(value) },
    },
    theme: {
        mode: isDark.value? 'dark' : 'light',
    },
}));

const activeFeedRows = computed(() => {
    if (activeFeed.value === 'sales_orders') return salesOrders.value;
    if (activeFeed.value === 'purchase_orders') return purchaseOrders.value;
    return dispatches.value;
});

function applyDashboardPayload(data = {}) {
    errorMessage.value = '';
    metrics.value = {...defaultMetrics,...(data.metrics || {}) };
    moduleCards.value = data.module_cards || [];
    financeTrend.value = data.finance_trend || { labels: [], series: [] };
    dispatchStatus.value = data.dispatch_status || [];
    customerLeaderboard.value = data.customer_leaderboard || [];
    stockSnapshot.value = data.stock_snapshot || [];
    stockAlerts.value = data.stock_alerts || [];
    recentTransactions.value = data.recent_transactions || [];
    salesOrders.value = data.sales_orders || [];
    dispatches.value = data.dispatches || [];
    purchaseOrders.value = data.purchase_orders || [];
    lastUpdated.value = data.generated_at || new Date().toISOString();
}

const fetchDashboardData = async ({ silent = false, refresh = false } = {}) => {
    errorMessage.value = '';

    const params = {...queryParams.value };
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
        metricsLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.metrics'), { params });
            metrics.value = data.metrics;
            moduleCards.value = data.module_cards;
        } catch (error) {
            handleError(error);
        } finally {
            metricsLoading.value = false;
        }
    };

    const loadFinanceTrend = async () => {
        financeLoading.value = true;
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
        dispatchLoading.value = true;
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
        leaderboardLoading.value = true;
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
        stockLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.stock'), { params });
            stockSnapshot.value = data.stock_snapshot;
            stockAlerts.value = data.stock_alerts;
        } catch (error) {
            handleError(error);
        } finally {
            stockLoading.value = false;
        }
    };

    const loadRecentActivity = async () => {
        activityLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.recent-activity'), { params });
            recentTransactions.value = data.recent_transactions;
        } catch (error) {
            handleError(error);
        } finally {
            activityLoading.value = false;
        }
    };

    const loadFeeds = async () => {
        feedsLoading.value = true;
        try {
            const { data } = await axios.get(route('dashboard.data.feeds'), { params });
            salesOrders.value = data.sales_orders;
            dispatches.value = data.dispatches;
            purchaseOrders.value = data.purchase_orders;
        } catch (error) {
            handleError(error);
        } finally {
            feedsLoading.value = false;
        }
    };

    await Promise.all([
        loadMetrics(),
        loadFinanceTrend(),
        loadDispatchStatus(),
        loadCustomerLeaderboard(),
        loadStock(),
        loadRecentActivity(),
        loadFeeds(),
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

function moduleCardValue(card) {
    if (!card) return '';
    if (['sales', 'purchase', 'accounting'].includes(card.key)) {
        return formatCurrency(card.value);
    }
    return formatNumber(card.value, card.key === 'dispatch'? 3 : 0);
}
function toneClasses(tone) {
    const tones = {
        amber: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30',
        sky: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/20 dark:text-sky-400 dark:border-sky-900/30',
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30',
        violet: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/20 dark:text-violet-400 dark:border-violet-900/30',
        rose: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30',
        slate: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-900/50 dark:text-slate-350 dark:border-slate-700',
    };

    return tones[tone] || tones.slate;
}
</script>

<template>
    <AppLayout title="ERP Live Dashboard">
        <div class="py-6 sm:py-8 space-y-6">
            <!-- Header and Filter controls -->
            <section class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-slate-200 dark:border-slate-700 shadow-sm sm:p-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 dark:border-emerald-900/30 bg-emerald-50 dark:bg-emerald-950/20 px-3 py-1 text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700 dark:text-emerald-400">
                                <span class="inline-block size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live ERP
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                <BuildingOffice2Icon class="size-3.5" />
                                {{ plantLabel }}
                            </span>
                        </div>

                        <div>
                            <h1 class="text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">SaaS ERP Command Center</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400 sm:text-base">
                                One live dashboard for sales, purchase, dispatch, accounting, stock, and operational movement.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-2 rounded-xl bg-slate-950 dark:bg-slate-900 px-3 py-2 text-white shadow-sm">
                                <ChartBarIcon class="size-4" />
                                {{ metrics.open_sales_orders }} open sales orders
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2">
                                <TruckIcon class="size-4 text-slate-400 dark:text-slate-500" />
                                {{ metrics.active_batches }} active batches
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2">
                                <ArrowPathIcon class="size-4 text-slate-400 dark:text-slate-500" />
                                Updated {{ formatDateTime(lastUpdated) }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 p-4 border border-slate-200 dark:border-slate-700 sm:grid-cols-2 xl:min-w-[520px]">
                        <BaseDatePicker v-model="filterForm.start_date" label="From" />
                        <BaseDatePicker v-model="filterForm.end_date" label="To" />
                        <BaseSelect
                            v-model="filterForm.patron_id"
                            :options="patrons"
                            optionLabel="legal_name"
                            optionValue="id"
                            label="Customer / Vendor"
                            placeholder="All patrons"
                            filter
                            showClear
                            class="sm:col-span-2"
                        />
                        <button
                            type="button"
                            class="sm:col-span-2 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 text-sm font-bold transition shadow-sm cursor-pointer"
                            @click="fetchDashboardData({ refresh: true })"
                        >
                            <ArrowPathIcon class="size-4" :class="loading ? 'animate-spin' : ''" />
                            Refresh live data
                        </button>
                    </div>
                </div>
            </section>

            <!-- Error message -->
            <section
                v-if="errorMessage"
                class="rounded-xl border border-rose-200 dark:border-rose-900/30 bg-rose-50 dark:bg-rose-950/20 px-4 py-3 text-sm font-medium text-rose-700 dark:text-rose-400"
            >
                {{ errorMessage }}
            </section>

            <!-- Summary cards -->
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <template v-if="metricsLoading">
                    <div v-for="i in 6" :key="'summary-sk-' + i" class="rounded-xl bg-white dark:bg-slate-800 p-5 border border-slate-200 dark:border-slate-700 shadow-sm animate-pulse h-[130px]">
                        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3 mb-4"></div>
                        <div class="h-8 bg-slate-250 dark:bg-slate-600 rounded w-1/2 mb-3"></div>
                        <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/4"></div>
                    </div>
                </template>
                <template v-else>
                    <article
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="rounded-xl bg-white dark:bg-slate-800 p-5 border border-slate-200 dark:border-slate-700 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ card.title }}</p>
                                <p class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                                    {{ ['dispatch_quantity'].includes(card.key) ? formatNumber(card.value, 3) : formatCurrency(card.value) }}
                                </p>
                                <p class="mt-2 text-sm font-medium text-slate-500 dark:text-slate-400">{{ card.meta }}</p>
                            </div>
                            <div :class="['flex size-12 items-center justify-center rounded-xl border shadow-sm', toneClasses(card.tone)]">
                                <component :is="card.icon" class="size-6" />
                            </div>
                        </div>
                    </article>
                </template>
            </section>

            <!-- Module cards -->
            <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
                <template v-if="metricsLoading">
                    <div v-for="i in 5" :key="'module-sk-' + i" class="rounded-xl bg-white dark:bg-slate-800 p-5 border border-slate-200 dark:border-slate-700 shadow-sm animate-pulse h-[110px]">
                        <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2 mb-3"></div>
                        <div class="h-6 bg-slate-250 dark:bg-slate-600 rounded w-3/4 mb-2"></div>
                        <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
                    </div>
                </template>
                <template v-else>
                    <article
                        v-for="card in moduleCards"
                        :key="card.key"
                        class="rounded-xl bg-white dark:bg-slate-800 p-5 border border-slate-200 dark:border-slate-700 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ card.title }}</p>
                                <p class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ moduleCardValue(card) }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ card.meta }}</p>
                            </div>
                            <div :class="['h-12 w-1 rounded-full shrink-0', toneClasses(card.accent)]"></div>
                        </div>
                    </article>
                </template>
            </section>

            <!-- Charts section -->
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.9fr),minmax(360px,1fr)]">
                <article class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">Finance Pulse</p>
                            <h3 class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white">Sales, purchase, collections, and dispatch trend</h3>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                            {{ financeTrend.labels.length }} points
                        </div>
                    </div>

                    <div class="mt-6">
                        <div v-if="financeLoading" class="animate-pulse h-[360px] bg-slate-50 dark:bg-slate-900/50 rounded-xl flex items-center justify-center text-slate-400 dark:text-slate-600 font-bold border border-slate-200 dark:border-slate-800">
                            Loading Chart...
                        </div>
                        <VueApexCharts
                            v-show="!financeLoading"
                            type="area"
                            height="360"
                            :options="financeChartOptions"
                            :series="financeChartSeries"
                        />
                    </div>
                </article>

                <div class="space-y-6">
                    <article class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">Dispatch Status</p>
                                <h3 class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white">Trip distribution</h3>
                            </div>
                            <TruckIcon class="size-5 text-slate-400 dark:text-slate-500 shrink-0" />
                        </div>

                        <div class="mt-5">
                            <div v-if="dispatchLoading" class="animate-pulse h-[300px] bg-slate-50 dark:bg-slate-900/50 rounded-xl flex items-center justify-center text-slate-400 dark:text-slate-600 font-bold border border-slate-200 dark:border-slate-800">
                                Loading distribution...
                            </div>
                            <VueApexCharts
                                v-show="!dispatchLoading"
                                type="donut"
                                height="300"
                                :options="dispatchStatusOptions"
                                :series="dispatchStatusSeries"
                            />
                        </div>
                    </article>
                </div>
            </section>

            <!-- Operations feed & Leaderboard -->
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.4fr),minmax(0,1fr)]">
                <article class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">Operations Feed</p>
                            <h3 class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white">Live Flow</h3>
                        </div>
                        <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-900 p-1 border border-slate-200 dark:border-slate-700">
                            <button
                                v-for="tab in feedTabs"
                                :key="tab.key"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold uppercase tracking-[0.18em] transition cursor-pointer"
                                :class="activeFeed === tab.key ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-white shadow-sm border border-slate-200/50 dark:border-slate-700/50' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                                @click="activeFeed = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
                        <div class="grid grid-cols-[1.2fr,1fr,0.8fr,0.8fr] bg-slate-50 dark:bg-slate-800/60 px-4 py-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <span>{{ activeFeed === 'dispatches' ? 'Ticket' : activeFeed === 'sales_orders' ? 'Order' : 'PO' }}</span>
                            <span>{{ activeFeed === 'purchase_orders' ? 'Vendor' : 'Customer' }}</span>
                            <span>{{ activeFeed === 'purchase_orders' ? 'Date' : 'Qty' }}</span>
                            <span>Status</span>
                        </div>

                        <div v-if="feedsLoading" class="animate-pulse p-4 space-y-4">
                            <div v-for="i in 5" :key="i" class="grid grid-cols-[1.2fr,1fr,0.8fr,0.8fr] gap-3">
                                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4"></div>
                                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
                                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
                                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
                            </div>
                        </div>
                        <div v-else-if="activeFeedRows.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div
                                v-for="row in activeFeedRows"
                                :key="`${activeFeed}-${row.id}`"
                                class="grid grid-cols-[1.2fr,1fr,0.8fr,0.8fr] items-center gap-3 px-4 py-4 text-sm"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-black text-slate-900 dark:text-white">
                                        {{ activeFeed === 'dispatches' ? row.ticket : activeFeed === 'sales_orders' ? row.number : row.number }}
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ activeFeed === 'dispatches' ? row.vehicle : activeFeed === 'sales_orders' ? row.grade : `Amount ${formatCurrency(row.amount)}` }}
                                    </p>
                                </div>
                                <p class="truncate font-medium text-slate-600 dark:text-slate-300">
                                    {{ activeFeed === 'purchase_orders' ? row.vendor : row.customer }}
                                </p>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">
                                    {{ activeFeed === 'purchase_orders' ? row.date : formatNumber(activeFeed === 'dispatches' ? row.qty : row.qty, 3) }}
                                </p>
                                <div>
                                    <span class="inline-flex rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                        {{ activeFeed === 'purchase_orders' ? (row.invoice_status ? 'Billed' : 'Open') : row.status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="px-6 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                            No records found for this feed.
                        </div>
                    </div>
                </article>

                <div class="space-y-6">
                    <article class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">Customer Leaderboard</p>
                                <h3 class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white">Top customers by dispatch revenue</h3>
                            </div>
                            <ArrowTrendingUpIcon class="size-5 text-slate-400 dark:text-slate-500 shrink-0" />
                        </div>

                        <div class="mt-5">
                            <div v-if="leaderboardLoading" class="animate-pulse h-[280px] bg-slate-50 dark:bg-slate-900/50 rounded-xl flex items-center justify-center text-slate-400 dark:text-slate-600 font-bold border border-slate-200 dark:border-slate-800">
                                Loading leaderboard...
                            </div>
                            <VueApexCharts
                                v-show="!leaderboardLoading"
                                type="bar"
                                height="280"
                                :options="leaderboardOptions"
                                :series="leaderboardSeries"
                            />
                        </div>
                    </article>

                        <!-- <article class="rounded-[32px] bg-[#f0f3f6] p-6 shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#d1d9e6] transition-all duration-300 hover:-translate-y-1 hover:shadow-[-10px_-10px_20px_#ffffff,10px_10px_20px_#cbd5e1]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Stock Pressure</p>
                                    <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Current stock and alert coverage</h3>
                                </div>
                                <CircleStackIcon class="size-5 text-slate-300" />
                            </div>

                            <div class="mt-5 space-y-4">
                                <div v-if="stockLoading" class="animate-pulse space-y-4">
                                    <div v-for="i in 3" :key="i" class="rounded-2xl border border-slate-200 bg-white p-4 h-[100px]">
                                        <div class="h-4 bg-slate-300 rounded w-1/2 mb-2"></div>
                                        <div class="h-3 bg-slate-200 rounded w-1/3"></div>
                                    </div>
                                </div>
                                <template v-else>
                                    <div
                                        v-for="item in stockSnapshot"
                                        :key="item.id"
                                        class="rounded-2xl border border-slate-200 bg-white p-4"
                                    >
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="truncate font-black text-slate-900">{{ item.name }}</p>
                                                <p class="mt-1 text-sm text-slate-500">
                                                    {{ formatNumber(item.quantity, 2) }} {{ item.unit }}
                                                    <span v-if="item.alert_level"> / alert {{ formatNumber(item.alert_level, 2) }}</span>
                                                </p>
                                            </div>
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                                                :class="item.is_critical ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                                            >
                                                {{ item.is_critical ? 'Critical' : 'Healthy' }}
                                            </span>
                                        </div>

                                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="item.is_critical ? 'bg-rose-500' : 'bg-emerald-500'"
                                                :style="{ width: `${Math.max(8, item.coverage)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </article> -->
                </div>
            </section>

                <!-- <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.3fr),minmax(0,0.7fr)]">
                    <article class="rounded-[32px] bg-[#f0f3f6] p-6 shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#d1d9e6] transition-all duration-300 hover:-translate-y-1 hover:shadow-[-10px_-10px_20px_#ffffff,10px_10px_20px_#cbd5e1]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Accounting Stream</p>
                                <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Recent journal movement</h3>
                            </div>
                            <BanknotesIcon class="size-5 text-slate-300" />
                        </div>

                        <div class="mt-6 grid gap-3">
                            <div v-if="activityLoading" class="animate-pulse space-y-3">
                                <div v-for="i in 4" :key="i" class="flex items-center gap-4 rounded-[24px] border border-slate-200 bg-white px-4 py-4 h-[75px]">
                                    <div class="size-11 rounded-2xl bg-slate-200"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="h-4 bg-slate-300 rounded w-1/2"></div>
                                        <div class="h-3 bg-slate-200 rounded w-1/3"></div>
                                    </div>
                                    <div class="w-16 h-6 bg-slate-300 rounded"></div>
                                </div>
                            </div>
                            <template v-else>
                                <div
                                    v-for="(trx, index) in recentTransactions"
                                    :key="`${trx.voucher_no}-${index}`"
                                    class="flex items-center gap-4 rounded-[24px] border border-slate-200 bg-white px-4 py-4"
                                >
                                    <div
                                        class="flex size-11 shrink-0 items-center justify-center rounded-2xl font-black"
                                        :class="trx.dr_cr === 'Dr' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                    >
                                        {{ trx.dr_cr }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-black text-slate-900">{{ trx.ledger }}</p>
                                        <p class="mt-1 truncate text-sm text-slate-500">{{ trx.partner }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-black text-slate-950">{{ formatCurrency(trx.amount) }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ trx.voucher_type }}</p>
                                    </div>
                                </div>

                                <div v-if="!recentTransactions.length" class="rounded-[24px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">
                                    No recent accounting activity in this range.
                                </div>
                            </template>
                        </div>
                    </article>

                    <article class="rounded-[32px] bg-[#f0f3f6] p-6 shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#d1d9e6] transition-all duration-300 hover:-translate-y-1 hover:shadow-[-10px_-10px_20px_#ffffff,10px_10px_20px_#cbd5e1]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Alerts</p>
                                <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Critical stock items</h3>
                            </div>
                            <ExclamationTriangleIcon class="size-5 text-slate-300" />
                        </div>

                        <div class="mt-5 space-y-3">
                            <div v-if="stockLoading" class="animate-pulse space-y-3">
                                <div v-for="i in 2" :key="i" class="rounded-2xl border border-rose-100 bg-rose-50/50 px-4 py-4 h-[60px]"></div>
                            </div>
                            <template v-else>
                                <div
                                    v-for="item in stockAlerts"
                                    :key="`alert-${item.id}`"
                                    class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4"
                                >
                                    <p class="font-black text-rose-900">{{ item.name }}</p>
                                    <p class="mt-1 text-sm text-rose-700">
                                        {{ formatNumber(item.quantity, 2) }} {{ item.unit }} remaining
                                    </p>
                                </div>

                                <div v-if="!stockAlerts.length" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-8 text-center text-sm font-semibold text-emerald-700">
                                    No stock alerts right now.
                                </div>
                            </template>
                        </div>
                    </article>
                </section> -->
            </div>
        <!-- </div> -->
    </AppLayout>
</template>