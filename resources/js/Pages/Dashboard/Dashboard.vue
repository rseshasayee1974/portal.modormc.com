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

const loading = ref(false);
const errorMessage = ref('');
const lastUpdated = ref(props.initialData?.generated_at || '');
const activeFeed = ref('dispatches');
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
    open_work_orders: 0,
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
const workOrders = ref(props.initialData?.work_orders || []);
const dispatches = ref(props.initialData?.dispatches || []);
const purchaseOrders = ref(props.initialData?.purchase_orders || []);

const feedTabs = [
    { key: 'dispatches', label: 'Dispatches' },
    { key: 'work_orders', label: 'Work Orders' },
    { key: 'purchase_orders', label: 'Purchases' },
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
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '12px',
        labels: { colors: '#64748b' },
    },
    grid: {
        borderColor: '#e2e8f0',
        strokeDashArray: 5,
        xaxis: { lines: { show: false } },
    },
    xaxis: {
        categories: financeTrend.value.labels || [],
        labels: {
            style: {
                colors: '#94a3b8',
                fontSize: '11px',
                fontWeight: 600,
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            formatter: (value) => compactCurrency(value),
            style: {
                colors: '#94a3b8',
                fontSize: '11px',
                fontWeight: 600,
            },
        },
    },
    tooltip: {
        theme: 'light',
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
    },
    labels: dispatchStatus.value.map((item) => item.label),
    colors: statusPalette,
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: '#64748b' },
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
                    },
                },
            },
        },
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
            style: { colors: '#94a3b8', fontSize: '11px' },
        },
    },
    yaxis: {
        labels: {
            style: {
                colors: '#475569',
                fontSize: '11px',
                fontWeight: 700,
            },
        },
    },
    grid: {
        borderColor: '#e2e8f0',
        strokeDashArray: 5,
    },
    tooltip: {
        y: { formatter: (value) => formatCurrency(value) },
    },
}));

const activeFeedRows = computed(() => {
    if (activeFeed.value === 'work_orders') return workOrders.value;
    if (activeFeed.value === 'purchase_orders') return purchaseOrders.value;
    return dispatches.value;
});

function applyDashboardPayload(data = {}) {
    errorMessage.value = '';
    metrics.value = { ...defaultMetrics, ...(data.metrics || {}) };
    moduleCards.value = data.module_cards || [];
    financeTrend.value = data.finance_trend || { labels: [], series: [] };
    dispatchStatus.value = data.dispatch_status || [];
    customerLeaderboard.value = data.customer_leaderboard || [];
    stockSnapshot.value = data.stock_snapshot || [];
    stockAlerts.value = data.stock_alerts || [];
    recentTransactions.value = data.recent_transactions || [];
    workOrders.value = data.work_orders || [];
    dispatches.value = data.dispatches || [];
    purchaseOrders.value = data.purchase_orders || [];
    lastUpdated.value = data.generated_at || new Date().toISOString();
}

const fetchDashboardData = async ({ silent = false } = {}) => {
    if (!silent) loading.value = true;

    try {
        const { data } = await axios.get(route('dashboard.data'), {
            params: queryParams.value,
        });

        applyDashboardPayload(data);
    } catch (error) {
        if (error?.response?.status === 401 || error?.response?.status === 419) {
            // Session expired — stop polling and redirect to login
            if (pollTimer) clearInterval(pollTimer);
            window.location.href = '/login';
            return;
        }
        console.error('Failed to fetch dashboard data', error);
        errorMessage.value = 'Unable to refresh live dashboard data right now.';
    } finally {
        loading.value = false;
    }
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
    return formatNumber(card.value, card.key === 'dispatch' ? 3 : 0);
}

function toneClasses(tone) {
    const tones = {
        amber: 'bg-amber-50 text-amber-700 border-amber-100',
        sky: 'bg-sky-50 text-sky-700 border-sky-100',
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-100',
        violet: 'bg-violet-50 text-violet-700 border-violet-100',
        rose: 'bg-rose-50 text-rose-700 border-rose-100',
        slate: 'bg-slate-100 text-slate-700 border-slate-200',
    };

    return tones[tone] || tones.slate;
}
</script>

<template>
    <AppLayout title="ERP Live Dashboard">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.12),_transparent_30%),linear-gradient(180deg,#fffdf8_0%,#f8fafc_45%,#f8fafc_100%)] pb-12">
            <div class="mx-auto max-w-[1700px] px-4 py-6 sm:px-6 lg:px-8">
                <section class="rounded-[32px] border border-white/80 bg-white/80 p-6 shadow-[0_30px_80px_-40px_rgba(15,23,42,0.4)] backdrop-blur-xl sm:p-8">
                    <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">
                                    <span class="inline-block size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Live ERP
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
                                    <BuildingOffice2Icon class="size-3.5" />
                                    {{ plantLabel }}
                                </span>
                            </div>

                            <div>
                                <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">SaaS ERP Command Center</h1>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">
                                    One live dashboard for sales, purchase, dispatch, accounting, stock, and operational movement.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                <span class="inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-3 py-2 text-white shadow-lg shadow-slate-200">
                                    <ChartBarIcon class="size-4" />
                                    {{ metrics.open_work_orders }} open work orders
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                    <TruckIcon class="size-4 text-slate-400" />
                                    {{ metrics.active_batches }} active batches
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                    <ArrowPathIcon class="size-4 text-slate-400" />
                                    Updated {{ formatDateTime(lastUpdated) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 rounded-[28px] border border-slate-200 bg-white/90 p-4 sm:grid-cols-2 xl:min-w-[520px]">
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
                                class="sm:col-span-2 inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
                                @click="fetchDashboardData()"
                            >
                                <ArrowPathIcon class="size-4" :class="loading ? 'animate-spin' : ''" />
                                Refresh live data
                            </button>
                        </div>
                    </div>
                </section>

                <section
                    v-if="errorMessage"
                    class="mt-6 rounded-[24px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                >
                    {{ errorMessage }}
                </section>

                <section class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="rounded-[28px] border border-white/80 bg-white/85 p-5 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)] backdrop-blur-xl"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">{{ card.title }}</p>
                                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                                    {{ ['dispatch_quantity'].includes(card.key) ? formatNumber(card.value, 3) : formatCurrency(card.value) }}
                                </p>
                                <p class="mt-2 text-sm font-medium text-slate-500">{{ card.meta }}</p>
                            </div>
                            <div :class="['flex size-12 items-center justify-center rounded-2xl border', toneClasses(card.tone)]">
                                <component :is="card.icon" class="size-6" />
                            </div>
                        </div>
                    </article>
                </section>

                <section class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-5">
                    <article
                        v-for="card in moduleCards"
                        :key="card.key"
                        class="rounded-[26px] border border-white/80 bg-white/85 p-5 shadow-[0_22px_50px_-42px_rgba(15,23,42,0.4)]"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">{{ card.title }}</p>
                                <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ moduleCardValue(card) }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ card.meta }}</p>
                            </div>
                            <div :class="['h-12 w-1 rounded-full', toneClasses(card.accent)]"></div>
                        </div>
                    </article>
                </section>

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.9fr),minmax(360px,1fr)]">
                    <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Finance Pulse</p>
                                <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Sales, purchase, collections, and dispatch trend</h3>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">
                                {{ financeTrend.labels.length }} points
                            </div>
                        </div>

                        <div class="mt-6">
                            <VueApexCharts
                                type="area"
                                height="360"
                                :options="financeChartOptions"
                                :series="financeChartSeries"
                            />
                        </div>
                    </article>

                    <div class="space-y-6">
                        <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Dispatch Status</p>
                                    <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Trip distribution</h3>
                                </div>
                                <TruckIcon class="size-5 text-slate-300" />
                            </div>

                            <div class="mt-5">
                                <VueApexCharts
                                    type="donut"
                                    height="300"
                                    :options="dispatchStatusOptions"
                                    :series="dispatchStatusSeries"
                                />
                            </div>
                        </article>

                        <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Accounting Pulse</p>
                                    <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Cash and balances</h3>
                                </div>
                                <ReceiptPercentIcon class="size-5 text-slate-300" />
                            </div>

                            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl bg-emerald-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Collections</p>
                                    <p class="mt-2 text-lg font-black text-emerald-900">{{ formatCurrency(metrics.collections) }}</p>
                                </div>
                                <div class="rounded-2xl bg-rose-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-rose-600">Payables</p>
                                    <p class="mt-2 text-lg font-black text-rose-900">{{ formatCurrency(metrics.payables) }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-950 p-4 text-white">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300">Cash Delta</p>
                                    <p class="mt-2 text-lg font-black">{{ formatCurrency(metrics.cash_delta) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.4fr),minmax(0,1fr)]">
                    <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Operations Feed</p>
                                <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Live flow across dispatch, production, and purchase</h3>
                            </div>
                            <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1">
                                <button
                                    v-for="tab in feedTabs"
                                    :key="tab.key"
                                    type="button"
                                    class="rounded-2xl px-3 py-2 text-xs font-bold uppercase tracking-[0.18em] transition"
                                    :class="activeFeed === tab.key ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                                    @click="activeFeed = tab.key"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 overflow-hidden rounded-[24px] border border-slate-200">
                            <div class="grid grid-cols-[1.2fr,1fr,0.8fr,0.8fr] bg-slate-50 px-4 py-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                                <span>{{ activeFeed === 'dispatches' ? 'Ticket' : activeFeed === 'work_orders' ? 'Order' : 'PO' }}</span>
                                <span>{{ activeFeed === 'purchase_orders' ? 'Vendor' : 'Customer' }}</span>
                                <span>{{ activeFeed === 'purchase_orders' ? 'Date' : 'Qty' }}</span>
                                <span>Status</span>
                            </div>

                            <div v-if="activeFeedRows.length" class="divide-y divide-slate-100">
                                <div
                                    v-for="row in activeFeedRows"
                                    :key="`${activeFeed}-${row.id}`"
                                    class="grid grid-cols-[1.2fr,1fr,0.8fr,0.8fr] items-center gap-3 px-4 py-4 text-sm"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-black text-slate-900">
                                            {{ activeFeed === 'dispatches' ? row.ticket : activeFeed === 'work_orders' ? row.number : row.number }}
                                        </p>
                                        <p class="mt-1 truncate text-xs text-slate-500">
                                            {{ activeFeed === 'dispatches' ? row.vehicle : activeFeed === 'work_orders' ? row.grade : `Amount ${formatCurrency(row.amount)}` }}
                                        </p>
                                    </div>
                                    <p class="truncate font-medium text-slate-600">
                                        {{ activeFeed === 'purchase_orders' ? row.vendor : row.customer }}
                                    </p>
                                    <p class="font-semibold text-slate-700">
                                        {{ activeFeed === 'purchase_orders' ? row.date : formatNumber(activeFeed === 'dispatches' ? row.qty : row.qty, 3) }}
                                    </p>
                                    <div>
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-600">
                                            {{ activeFeed === 'purchase_orders' ? (row.invoice_status ? 'Billed' : 'Open') : row.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="px-6 py-16 text-center text-sm text-slate-500">
                                No records found for this feed.
                            </div>
                        </div>
                    </article>

                    <div class="space-y-6">
                        <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Customer Leaderboard</p>
                                    <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Top customers by dispatch revenue</h3>
                                </div>
                                <ArrowTrendingUpIcon class="size-5 text-slate-300" />
                            </div>

                            <div class="mt-5">
                                <VueApexCharts
                                    type="bar"
                                    height="280"
                                    :options="leaderboardOptions"
                                    :series="leaderboardSeries"
                                />
                            </div>
                        </article>

                        <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Stock Pressure</p>
                                    <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Current stock and alert coverage</h3>
                                </div>
                                <CircleStackIcon class="size-5 text-slate-300" />
                            </div>

                            <div class="mt-5 space-y-4">
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
                            </div>
                        </article>
                    </div>
                </section>

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.3fr),minmax(0,0.7fr)]">
                    <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Accounting Stream</p>
                                <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Recent journal movement</h3>
                            </div>
                            <BanknotesIcon class="size-5 text-slate-300" />
                        </div>

                        <div class="mt-6 grid gap-3">
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
                        </div>
                    </article>

                    <article class="rounded-[32px] border border-white/80 bg-white/85 p-6 shadow-[0_24px_60px_-42px_rgba(15,23,42,0.4)]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Alerts</p>
                                <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Critical stock items</h3>
                            </div>
                            <ExclamationTriangleIcon class="size-5 text-slate-300" />
                        </div>

                        <div class="mt-5 space-y-3">
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
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
