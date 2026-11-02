<script setup>
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleDateTime, entityToday, entityDaysAgo } from '@/Utils/entityDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { computed, onUnmounted, ref, defineAsyncComponent } from 'vue';
const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    BanknotesIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    PresentationChartLineIcon,
    CircleStackIcon,
    CurrencyRupeeIcon,
    ExclamationTriangleIcon,
    ReceiptPercentIcon,
    TruckIcon,
    WalletIcon,
    UserGroupIcon,
    InboxStackIcon,
    ShoppingCartIcon,
    CalendarDaysIcon,
    ChevronRightIcon
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
let refreshController = null;

const filterForm = ref({
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    patron_id: props.filters?.patron_id || null,
});

const selectedPreset = ref('30d');

const defaultMetrics = {
    sales_revenue: 0,
    sales_count: 0,
    purchase_spend: 0,
    purchase_count: 0,
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

const performanceTrajectory = ref(props.initialData?.performance_trajectory || {
    monthly: { labels: [], sales: [], purchases: [] },
    quarterly: { labels: [], sales: [], purchases: [] },
    annually: { labels: [], sales: [], purchases: [] },
});

const activeTimeTab = ref('Monthly');
const salesTrendChartType = ref('bar');

const queryParams = computed(() => ({
    start_date: normalizeDate(filterForm.value.start_date),
    end_date: normalizeDate(filterForm.value.end_date),
    patron_id: filterForm.value.patron_id || null,
}));

const plantLabel = computed(() => activePlant.value?.plant_name || 'Enterprise Command Center');

// Gross Margin calculation
const grossMargin = computed(() => {
    return (metrics.value.sales_revenue || 0) - (metrics.value.purchase_spend || 0);
});

const grossMarginPercent = computed(() => {
    const sales = metrics.value.sales_revenue || 0;
    if (sales <= 0) return 0;
    return Number(((grossMargin.value / sales) * 100).toFixed(1));
});

// Quick Date Presets
const applyPreset = (preset) => {
    selectedPreset.value = preset;
    const today = entityToday();
    if (preset === 'today') {
        filterForm.value.start_date = `${today} 00:00:00`;
        filterForm.value.end_date = `${today} 23:59:59`;
    } else if (preset === '7d') {
        filterForm.value.start_date = `${entityDaysAgo(7)} 00:00:00`;
        filterForm.value.end_date = `${today} 23:59:59`;
    } else if (preset === '30d') {
        filterForm.value.start_date = `${entityDaysAgo(30)} 00:00:00`;
        filterForm.value.end_date = `${today} 23:59:59`;
    } else if (preset === 'month') {
        const d = new Date();
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        filterForm.value.start_date = `${year}-${month}-01 00:00:00`;
        filterForm.value.end_date = `${today} 23:59:59`;
    }
    fetchDashboardData();
};

const onCustomDateChange = () => {
    selectedPreset.value = 'custom';
    fetchDashboardData();
};

// Sales vs Purchase Comparison Chart
const salesTrendSeries = computed(() => {
    const rawSales = financeTrend.value.series?.find(s => s.name === 'Sales')?.data || [];
    const rawPurchases = financeTrend.value.series?.find(s => s.name === 'Purchase')?.data || [];
    return [
        { name: 'Sales Revenue', data: rawSales },
        { name: 'Purchase Spend', data: rawPurchases }
    ];
});

const totalPeriodSales = computed(() => {
    const data = financeTrend.value.series?.find(s => s.name === 'Sales')?.data || [];
    return data.reduce((sum, v) => sum + (Number(v) || 0), 0);
});

const totalPeriodPurchases = computed(() => {
    const data = financeTrend.value.series?.find(s => s.name === 'Purchase')?.data || [];
    return data.reduce((sum, v) => sum + (Number(v) || 0), 0);
});

const salesTrendOptions = computed(() => ({
    chart: {
        type: salesTrendChartType.value,
        toolbar: { show: false },
        background: 'transparent',
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '45%',
            borderRadius: 4,
            borderRadiusApplication: 'end'
        },
    },
    stroke: {
        curve: 'smooth',
        width: salesTrendChartType.value === 'area' ? 3 : 0,
    },
    fill: salesTrendChartType.value === 'area' ? {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.35,
            opacityTo: 0.05,
            stops: [0, 90, 100]
        }
    } : {
        opacity: 0.95
    },
    dataLabels: { enabled: false },
    colors: ['#2563eb', '#f59e0b'],
    xaxis: {
        categories: financeTrend.value.labels || [],
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '11px',
                fontWeight: 600,
                fontFamily: 'Inter, sans-serif'
            }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: {
            formatter: (val) => compactCurrency(val),
            style: { colors: '#64748b', fontSize: '11px', fontFamily: 'Inter, sans-serif' }
        }
    },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4,
    },
    tooltip: {
        shared: true,
        intersect: false,
        theme: 'light',
        y: { formatter: (val) => formatCurrency(val) }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        fontSize: '12px',
        fontWeight: 600,
        labels: { colors: '#334155' },
        markers: { radius: 4, width: 10, height: 10, offsetX: -2 }
    }
}));

// Real Dispatch Status Fulfillment Donut
const dispatchDonutSeries = computed(() => {
    return (dispatchStatus.value || []).map(d => Number(d.value) || 0);
});

const dispatchDonutLabels = computed(() => {
    return (dispatchStatus.value || []).map(d => d.label);
});

const hasDispatchData = computed(() => {
    return dispatchDonutSeries.value.some(v => v > 0);
});

const dispatchDonutOptions = computed(() => ({
    chart: {
        type: 'donut',
        background: 'transparent'
    },
    labels: dispatchDonutLabels.value,
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'],
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    legend: {
        position: 'bottom',
        fontSize: '11px',
        fontWeight: 600,
        labels: { colors: '#475569' }
    },
    plotOptions: {
        pie: {
            donut: {
                size: '75%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total Trips',
                        formatter: () => formatNumber(metrics.value.dispatch_trips || dispatchDonutSeries.value.reduce((a, b) => a + b, 0)),
                        color: '#0f172a',
                        fontSize: '18px',
                        fontWeight: '800'
                    }
                }
            }
        }
    },
    tooltip: {
        y: {
            formatter: (val) => `${formatNumber(val)} trips`
        }
    }
}));

// Top Customer Leaderboard Revenue Share
const maxCustomerRevenue = computed(() => {
    if (!customerLeaderboard.value?.length) return 1;
    return Math.max(...customerLeaderboard.value.map(c => Number(c.revenue) || 0), 1);
});

// Target Progress Gauge
const monthlyTarget = computed(() => 2500000); // 25 Lakhs reference benchmark

const targetPercent = computed(() => {
    const target = monthlyTarget.value;
    const currentSales = metrics.value.sales_revenue || 0;
    if (target <= 0) return 0;
    const pct = (currentSales / target) * 100;
    return Number(pct.toFixed(1));
});

const monthlyTargetSeries = computed(() => [Math.min(100, Math.max(0, targetPercent.value))]);
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
                strokeWidth: '95%',
                margin: 5,
            },
            dataLabels: {
                name: { show: false },
                value: {
                    offsetY: -4,
                    fontSize: '28px',
                    fontWeight: '800',
                    color: '#0f172a',
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
                { offset: 0, color: '#2563eb', opacity: 1 },
                { offset: 100, color: '#10b981', opacity: 1 }
            ]
        }
    },
    colors: ['#2563eb'],
    labels: ['Target Achievement']
}));

// Multi-period Performance Trajectory (Monthly / Quarterly / Annually)
const activeTrajectoryData = computed(() => {
    const tabKey = (activeTimeTab.value || 'Monthly').toLowerCase();
    const traj = performanceTrajectory.value?.[tabKey];
    if (traj && traj.labels && traj.labels.length > 0) {
        return traj;
    }
    // Fallback if not loaded
    const sales = financeTrend.value?.series?.find(s => s.name === 'Sales')?.data || [];
    const purchases = financeTrend.value?.series?.find(s => s.name === 'Purchase')?.data || [];
    return {
        labels: financeTrend.value?.labels || [],
        sales,
        purchases,
    };
});

const statsSeries = computed(() => [
    { name: 'Revenue Trajectory', data: activeTrajectoryData.value.sales || [] },
    { name: 'Expenditure Profile', data: activeTrajectoryData.value.purchases || [] }
]);

const trajectorySalesTotal = computed(() => {
    return (activeTrajectoryData.value.sales || []).reduce((a, b) => a + (Number(b) || 0), 0);
});

const trajectoryPurchasesTotal = computed(() => {
    return (activeTrajectoryData.value.purchases || []).reduce((a, b) => a + (Number(b) || 0), 0);
});

const trajectoryNetMargin = computed(() => {
    return trajectorySalesTotal.value - trajectoryPurchasesTotal.value;
});

const statsOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 350
        }
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
            opacityTo: 0.04,
            stops: [0, 90, 100]
        }
    },
    colors: ['#2563eb', '#0891b2'],
    dataLabels: { enabled: false },
    xaxis: {
        categories: activeTrajectoryData.value.labels || [],
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '11px',
                fontWeight: 600,
                fontFamily: 'Inter, sans-serif'
            }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: {
            formatter: (val) => compactCurrency(val),
            style: { colors: '#64748b', fontSize: '11px', fontFamily: 'Inter, sans-serif' }
        }
    },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        fontSize: '12px',
        fontWeight: 600,
        labels: { colors: '#334155' },
        markers: { radius: 4, width: 10, height: 10, offsetX: -2 }
    },
    tooltip: {
        shared: true,
        intersect: false,
        theme: 'light',
        y: { formatter: (val) => formatCurrency(val) }
    }
}));

function applyDashboardPayload(data = {}) {
    errorMessage.value = '';
    metrics.value = { ...defaultMetrics, ...(data.metrics || {}) };
    financeTrend.value = data.finance_trend || { labels: [], series: [] };
    if (data.performance_trajectory) {
        performanceTrajectory.value = data.performance_trajectory;
    }
    dispatchStatus.value = data.dispatch_status || [];
    customerLeaderboard.value = data.customer_leaderboard || [];
    stockSnapshot.value = data.stock_snapshot || [];
    recentTransactions.value = data.recent_transactions || [];
    lastUpdated.value = data.generated_at || '';
}

const fetchDashboardData = async () => {
    if (loading.value || !activePlant.value) return;
    errorMessage.value = '';
    const params = { ...queryParams.value, refresh: true };
    const requestedFilters = JSON.stringify(queryParams.value);
    const controller = new AbortController();
    refreshController = controller;
    const sectionLoading = [metricsLoading, financeLoading, dispatchLoading, leaderboardLoading, stockLoading, activityLoading];
    sectionLoading.forEach((state) => { state.value = true; });

    try {
        const { data } = await axios.get(route('dashboard.data'), {
            params,
            signal: controller.signal,
            timeout: 120000,
        });
        if (controller.signal.aborted) return;
        if (requestedFilters !== JSON.stringify(queryParams.value)) return;
        applyDashboardPayload(data);
    } catch (error) {
        if (axios.isCancel(error)) return;
        if (error?.response?.status === 401 || error?.response?.status === 419) {
            window.location.href = '/login';
            return;
        }
        errorMessage.value = error?.response?.status === 422
            ? 'Check the selected date range, then click Refresh data again.'
            : 'Unable to refresh dashboard data. Please try again.';
    } finally {
        sectionLoading.forEach((state) => { state.value = false; });
        if (refreshController === controller) refreshController = null;
    }
};

onUnmounted(() => {
    refreshController?.abort();
});

function normalizeDate(value) {
    if (!value) return null;
    if (value instanceof Date) {
        const pad = (part) => String(part).padStart(2, '0');
        return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())} ${pad(value.getHours())}:${pad(value.getMinutes())}:${pad(value.getSeconds())}`;
    }
    return value;
}

function formatCurrency(value) {
    return new Intl.NumberFormat(APP_LOCALE, {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function compactCurrency(value) {
    return new Intl.NumberFormat(APP_LOCALE, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(Number(value || 0));
}

function formatNumber(value, digits = 0) {
    return new Intl.NumberFormat(APP_LOCALE, {
        maximumFractionDigits: digits,
        minimumFractionDigits: digits,
    }).format(Number(value || 0));
}

function formatDateTime(value) {
    if (!value) return 'Not loaded';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return entityLocaleDateTime(value, APP_LOCALE, {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout title="SaaS ERP Analytics Center">
        <div class="min-h-screen bg-[#f1f2f6] text-slate-800 font-sans pb-16 selection:bg-blue-600 selection:text-white">
            <div class="mx-auto max-w-[1700px] px-4 py-6 sm:px-6 lg:px-8">
                
                <!-- EXECUTIVE COMMAND HEADER & TOOLBAR -->
                <header class="rounded-md bg-white border border-slate-200/90 p-5 mb-6 shadow-xs flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-blue-700 border border-blue-200">
                                <span class="size-2 rounded-full bg-blue-600 animate-pulse"></span>
                                ERP Intelligence & Analytics Hub
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-[#f1f2f6] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 border border-slate-200">
                                <BuildingOffice2Icon class="size-3.5 text-blue-600" />
                                <span class="truncate max-w-[220px]">{{ plantLabel }}</span>
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Executive Analytics Command Center</h1>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5 font-medium">Consolidated financial health, sales vs. procurement trends, delivery fulfillment, and customer intelligence</p>
                    </div>

                    <!-- Filter Toolbar -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Date Presets -->
                        <div class="inline-flex bg-[#f1f2f6] border border-slate-200 p-1 rounded-md gap-1">
                            <button
                                v-for="preset in [
                                    { key: 'today', label: 'Today' },
                                    { key: '7d', label: '7D' },
                                    { key: '30d', label: '30D' },
                                    { key: 'month', label: 'Month' }
                                ]"
                                :key="preset.key"
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-bold transition"
                                :class="selectedPreset === preset.key ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
                                @click="applyPreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>

                        <!-- Custom Date Pickers -->
                        <div class="flex items-center gap-2 bg-[#f1f2f6] border border-slate-200 p-1 rounded-md">
                            <BaseDatePicker 
                                v-model="filterForm.start_date" 
                                placeholder="Start Date" 
                                class="w-36 text-xs" 
                                size="small" 
                                @change="onCustomDateChange"
                            />
                            <span class="text-xs font-bold text-slate-400">-</span>
                            <BaseDatePicker 
                                v-model="filterForm.end_date" 
                                placeholder="End Date" 
                                class="w-36 text-xs" 
                                size="small" 
                                @change="onCustomDateChange"
                            />
                        </div>

                        <!-- Patron Dropdown -->
                        <BaseSelect
                            v-model="filterForm.patron_id"
                            :options="patrons"
                            optionLabel="legal_name"
                            optionValue="id"
                            placeholder="All Patrons"
                            filter
                            showClear
                            class="w-44 text-xs"
                            size="small"
                            @change="fetchDashboardData"
                        />

                        <!-- Refresh Action -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-xs disabled:opacity-50"
                            :disabled="loading || !activePlant"
                            @click="fetchDashboardData"
                            title="Refresh Analytics Data"
                        >
                            <ArrowPathIcon class="size-3.5" :class="loading ? 'animate-spin' : ''" />
                            <span>{{ loading ? 'Updating...' : 'Sync' }}</span>
                        </button>
                    </div>
                </header>

                <!-- Status alert / timestamp banner -->
                <div class="flex items-center justify-between text-xs text-slate-500 font-medium mb-6 px-1">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full" :class="loading ? 'bg-amber-500 animate-ping' : 'bg-emerald-500'"></span>
                        <span>{{ loading ? 'Synchronizing live metrics...' : (lastUpdated ? `Live data snapshot: ${formatDateTime(lastUpdated)}` : 'Click Sync to query live database metrics') }}</span>
                    </div>
                    <span v-if="activePlant" class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">
                        Plant Node: {{ activePlant.plant_name }}
                    </span>
                </div>

                <div v-if="errorMessage" class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    {{ errorMessage }}
                </div>

                <!-- TIER 1: CORE EXECUTIVE FINANCIAL HEALTH TILES (4 COLUMNS) -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <!-- Total Sales Tile -->
                    <div class="rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-blue-300">
                        <div class="flex items-center justify-between text-slate-500 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-wider">Invoiced Sales</span>
                            <div class="p-2 rounded-md bg-blue-50 text-blue-600 border border-blue-100">
                                <ArrowTrendingUpIcon class="size-4" />
                            </div>
                        </div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.sales_revenue) }}</h2>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Realized Billing</span>
                            <span class="inline-flex rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100">
                                {{ metrics.open_sales_orders }} Orders Active
                            </span>
                        </div>
                    </div>

                    <!-- Total Purchases Tile -->
                    <div class="rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-cyan-300">
                        <div class="flex items-center justify-between text-slate-500 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-wider">Procurement Spend</span>
                            <div class="p-2 rounded-md bg-cyan-50 text-cyan-600 border border-cyan-100">
                                <ShoppingCartIcon class="size-4" />
                            </div>
                        </div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.purchase_spend) }}</h2>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Raw Material Spend</span>
                            <span class="inline-flex rounded-md bg-cyan-50 px-2 py-0.5 text-[10px] font-bold text-cyan-700 border border-cyan-100">
                                Stock: {{ compactCurrency(metrics.stock_value) }}
                            </span>
                        </div>
                    </div>

                    <!-- Gross Operating Margin Tile -->
                    <div class="rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-emerald-300">
                        <div class="flex items-center justify-between text-slate-500 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-wider">Gross Operating Margin</span>
                            <div class="p-2 rounded-md bg-emerald-50 text-emerald-600 border border-emerald-100">
                                <CurrencyRupeeIcon class="size-4" />
                            </div>
                        </div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(grossMargin) }}</h2>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sales vs. Purchase</span>
                            <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">
                                {{ grossMarginPercent }}% Margin
                            </span>
                        </div>
                    </div>

                    <!-- Outstanding Receivables Tile -->
                    <div class="rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-amber-300">
                        <div class="flex items-center justify-between text-slate-500 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-wider">Accounts Receivable</span>
                            <div class="p-2 rounded-md bg-amber-50 text-amber-600 border border-amber-100">
                                <WalletIcon class="size-4" />
                            </div>
                        </div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(metrics.receivables) }}</h2>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Unsettled Dues</span>
                            <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-100">
                                Payables: {{ compactCurrency(metrics.payables) }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- TIER 2: OPERATIONAL THROUGHPUT & LIQUIDITY STRIP (4 COLUMNS) -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Delivered Volume -->
                    <div class="flex items-center gap-3.5 p-4 rounded-md border border-slate-200/90 bg-white shadow-xs">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-indigo-50 text-indigo-600 border border-indigo-100">
                            <TruckIcon class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Dispatch Throughput</span>
                            <h3 class="text-lg font-black text-slate-900 tracking-tight leading-tight">
                                {{ formatNumber(metrics.dispatch_quantity, 1) }} <span class="text-xs font-semibold text-slate-500">m³</span>
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">{{ metrics.dispatch_trips }} completed transit loads</p>
                        </div>
                    </div>

                    <!-- Collections Inward -->
                    <div class="flex items-center gap-3.5 p-4 rounded-md border border-slate-200/90 bg-white shadow-xs">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 border border-emerald-100">
                            <ReceiptPercentIcon class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Collections Realized</span>
                            <h3 class="text-lg font-black text-slate-900 tracking-tight leading-tight">
                                {{ formatCurrency(metrics.collections) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">Customer bank/cash inwards</p>
                        </div>
                    </div>

                    <!-- Disbursements Outward -->
                    <div class="flex items-center gap-3.5 p-4 rounded-md border border-slate-200/90 bg-white shadow-xs">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-rose-50 text-rose-600 border border-rose-100">
                            <BanknotesIcon class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Disbursements Paid</span>
                            <h3 class="text-lg font-black text-slate-900 tracking-tight leading-tight">
                                {{ formatCurrency(metrics.payments) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">Operational & vendor vouchers</p>
                        </div>
                    </div>

                    <!-- Net Cash Delta -->
                    <div class="flex items-center gap-3.5 p-4 rounded-md border border-slate-200/90 bg-white shadow-xs">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md" :class="metrics.cash_delta >= 0 ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100'">
                            <component :is="metrics.cash_delta >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Net Liquidity Delta</span>
                            <h3 class="text-lg font-black tracking-tight leading-tight" :class="metrics.cash_delta >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                {{ metrics.cash_delta > 0 ? '+' : '' }}{{ formatCurrency(metrics.cash_delta) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">Receipts vs Payments delta</p>
                        </div>
                    </div>
                </section>

                <!-- TIER 3: HIGH IMPACT VISUAL ANALYTICS (2/3 + 1/3 GRID) -->
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Sales vs Purchase Timeline Analyzer (2/3 width) -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs lg:col-span-2 flex flex-col justify-between">
                        <!-- Chart Header -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4 pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-extrabold text-slate-900">Sales vs. Purchase Comparison</h3>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200">
                                        Timeline Breakdown
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Comparative evaluation of customer billing inflows against vendor purchases</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Chart View Switcher -->
                                <div class="inline-flex bg-[#f1f2f6] border border-slate-200 p-0.5 rounded-md">
                                    <button 
                                        type="button" 
                                        @click="salesTrendChartType = 'bar'" 
                                        title="Grouped Columns"
                                        class="p-1.5 rounded-md transition"
                                        :class="salesTrendChartType === 'bar' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                    >
                                        <ChartBarIcon class="size-4" />
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="salesTrendChartType = 'area'" 
                                        title="Smooth Area Trajectory"
                                        class="p-1.5 rounded-md transition"
                                        :class="salesTrendChartType === 'area' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                    >
                                        <PresentationChartLineIcon class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mini stat pills -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                            <div class="p-2.5 rounded-md bg-blue-50/60 border border-blue-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 block">Total Period Sales</span>
                                <span class="text-sm font-black text-blue-900">{{ formatCurrency(totalPeriodSales) }}</span>
                            </div>
                            <div class="p-2.5 rounded-md bg-amber-50/60 border border-amber-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 block">Total Period Purchases</span>
                                <span class="text-sm font-black text-amber-900">{{ formatCurrency(totalPeriodPurchases) }}</span>
                            </div>
                            <div class="p-2.5 rounded-md bg-emerald-50/60 border border-emerald-100 col-span-2 sm:col-span-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">Period Operating Margin</span>
                                <span class="text-sm font-black text-emerald-900">{{ formatCurrency(totalPeriodSales - totalPeriodPurchases) }}</span>
                            </div>
                        </div>

                        <!-- Chart Canvas -->
                        <div v-if="financeLoading" class="animate-pulse h-[300px] bg-slate-50 border border-slate-100 rounded-md flex items-center justify-center text-slate-400 font-bold text-xs">
                            Updating finance trends...
                        </div>
                        <VueApexCharts 
                            v-else 
                            :key="salesTrendChartType" 
                            :type="salesTrendChartType" 
                            height="300" 
                            :options="salesTrendOptions" 
                            :series="salesTrendSeries" 
                        />
                    </div>

                    <!-- Dispatch Status Fulfillment Donut (1/3 width) -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-lg font-extrabold text-slate-900">Dispatch Fulfillment</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 font-medium">Real-time delivery status split</p>
                                </div>
                                <TruckIcon class="size-5 text-blue-600" />
                            </div>

                            <div v-if="dispatchLoading" class="animate-pulse h-[260px] bg-slate-50 border border-slate-100 rounded-md flex items-center justify-center text-slate-400 font-bold text-xs">
                                Updating dispatch statuses...
                            </div>
                            <div v-else-if="!hasDispatchData" class="py-16 text-center text-xs font-semibold text-slate-400 bg-[#f1f2f6]/40 rounded-md border border-dashed border-slate-200">
                                <TruckIcon class="size-8 text-slate-300 mx-auto mb-2" />
                                No dispatch transit records for this period.
                            </div>
                            <div v-else class="flex justify-center my-3">
                                <VueApexCharts type="donut" height="260" :options="dispatchDonutOptions" :series="dispatchDonutSeries" />
                            </div>
                        </div>

                        <!-- Footer Trip Stats -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600">
                            <span>Total Batch Loads:</span>
                            <span class="text-slate-900 font-black">{{ formatNumber(metrics.dispatch_trips) }} trips</span>
                        </div>
                    </div>
                </section>

                <!-- TIER 4: CUSTOMER INTELLIGENCE & PERFORMANCE BENCHMARK (2 COLUMNS) -->
                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Customer Accounts Leaderboard -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                                <div>
                                    <h3 class="text-lg font-extrabold text-slate-900">Top Customer Accounts</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 font-medium">Ranked by delivered volume and billed revenue</p>
                                </div>
                                <UserGroupIcon class="size-5 text-blue-600" />
                            </div>

                            <div v-if="leaderboardLoading" class="space-y-3 py-4">
                                <div v-for="i in 4" :key="i" class="h-12 bg-slate-100 rounded-md animate-pulse"></div>
                            </div>
                            <div v-else-if="!customerLeaderboard.length" class="py-16 text-center text-xs font-semibold text-slate-400 bg-[#f1f2f6]/40 rounded-md border border-dashed border-slate-200">
                                No customer dispatch activity recorded in this period.
                            </div>
                            <div v-else class="space-y-3.5 max-h-[320px] overflow-y-auto pr-1">
                                <div 
                                    v-for="(cust, index) in customerLeaderboard.slice(0, 6)" 
                                    :key="cust.customer || cust.customer_name || index"
                                    class="p-3 rounded-md border border-slate-200/80 bg-white hover:bg-slate-50/60 transition"
                                >
                                    <div class="flex items-center justify-between mb-1.5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span 
                                                class="size-5 rounded-md flex items-center justify-center text-[10px] font-black shrink-0"
                                                :class="index === 0 ? 'bg-amber-100 text-amber-800' : index === 1 ? 'bg-slate-200 text-slate-700' : 'bg-slate-100 text-slate-600'"
                                            >
                                                #{{ index + 1 }}
                                            </span>
                                            <h4 class="text-xs font-bold text-slate-900 truncate" :title="cust.customer || cust.customer_name">
                                                {{ cust.customer || cust.customer_name || 'Walk-in Customer' }}
                                            </h4>
                                        </div>
                                        <span class="text-xs font-black text-slate-900 shrink-0">{{ formatCurrency(cust.revenue) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-500 font-medium mb-1.5">
                                        <span>{{ formatNumber(cust.quantity, 1) }} m³ delivered</span>
                                        <span>{{ cust.trips }} trips</span>
                                    </div>
                                    <!-- Volume share progress bar -->
                                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                        <div 
                                            class="bg-blue-600 h-full rounded-full transition-all duration-500"
                                            :style="{ width: `${Math.min(100, Math.max(5, (cust.revenue / maxCustomerRevenue) * 100))}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500">
                            <span>Customer Concentration</span>
                            <span class="text-blue-600">{{ customerLeaderboard.length }} Active Clients</span>
                        </div>
                    </div>

                    <!-- Target Benchmark & Capacity Run-rate -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                                <div>
                                    <h3 class="text-lg font-extrabold text-slate-900">Period Target Benchmark</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 font-medium">Progress against standard revenue target</p>
                                </div>
                                <span class="text-xs font-bold text-slate-400">Target: {{ compactCurrency(monthlyTarget) }}</span>
                            </div>

                            <!-- Radial Bar Gauge -->
                            <div class="flex flex-col items-center justify-center my-2">
                                <VueApexCharts type="radialBar" height="230" :options="monthlyTargetOptions" :series="monthlyTargetSeries" class="w-full" />
                                <span 
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-bold mt-1 border"
                                    :class="targetPercent >= 80 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200'"
                                >
                                    {{ targetPercent }}% Target Completion
                                </span>
                            </div>

                            <p class="text-center text-xs text-slate-500 leading-relaxed font-medium px-4">
                                Generated <span class="font-bold text-slate-900">{{ formatCurrency(metrics.sales_revenue) }}</span> in realized billing against reference quota of {{ compactCurrency(monthlyTarget) }}.
                            </p>
                        </div>

                        <!-- Benchmark Summary Grid -->
                        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 gap-2 text-center">
                            <div class="p-2 rounded-md bg-[#f1f2f6]">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Benchmark</p>
                                <p class="text-xs font-black text-slate-800 mt-0.5">{{ compactCurrency(monthlyTarget) }}</p>
                            </div>
                            <div class="p-2 rounded-md bg-[#f1f2f6]">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Batches In Run</p>
                                <p class="text-xs font-black text-blue-600 mt-0.5">{{ metrics.active_batches }}</p>
                            </div>
                            <div class="p-2 rounded-md bg-[#f1f2f6]">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Low Raw Stock</p>
                                <p class="text-xs font-black" :class="metrics.low_stock_count > 0 ? 'text-amber-600' : 'text-slate-800'">
                                    {{ metrics.low_stock_count }} items
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TIER 5: MULTI-PERIOD PERFORMANCE TRAJECTORY (FULL WIDTH) -->
                <section class="grid grid-cols-1 gap-6">
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4 pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-extrabold text-slate-900">Performance Trajectory & Forecast Horizon</h3>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200">
                                        {{ activeTimeTab }} Aggregation
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Rolling multi-period revenue vs expenditure modeling</p>
                            </div>
                            
                            <!-- Annually / Monthly / Quarterly tabs -->
                            <div class="inline-flex bg-[#f1f2f6] border border-slate-200 p-1 rounded-md gap-1">
                                <button 
                                    v-for="tab in ['Monthly', 'Quarterly', 'Annually']"
                                    :key="tab"
                                    type="button" 
                                    class="rounded-md px-3.5 py-1.5 text-xs font-bold transition"
                                    :class="activeTimeTab === tab ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
                                    @click="activeTimeTab = tab"
                                >
                                    {{ tab }}
                                </button>
                            </div>
                        </div>

                        <!-- Horizon Quick Summary Pills -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div class="p-2.5 rounded-md bg-blue-50/60 border border-blue-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 block">{{ activeTimeTab }} Revenue</span>
                                <span class="text-sm font-black text-blue-900">{{ formatCurrency(trajectorySalesTotal) }}</span>
                            </div>
                            <div class="p-2.5 rounded-md bg-cyan-50/60 border border-cyan-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-cyan-700 block">{{ activeTimeTab }} Procurement</span>
                                <span class="text-sm font-black text-cyan-900">{{ formatCurrency(trajectoryPurchasesTotal) }}</span>
                            </div>
                            <div class="p-2.5 rounded-md border" :class="trajectoryNetMargin >= 0 ? 'bg-emerald-50/60 border-emerald-100' : 'bg-rose-50/60 border-rose-100'">
                                <span class="text-[10px] font-bold uppercase tracking-wider block" :class="trajectoryNetMargin >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ activeTimeTab }} Operating Margin
                                </span>
                                <span class="text-sm font-black" :class="trajectoryNetMargin >= 0 ? 'text-emerald-900' : 'text-rose-900'">
                                    {{ trajectoryNetMargin > 0 ? '+' : '' }}{{ formatCurrency(trajectoryNetMargin) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="financeLoading" class="animate-pulse h-[280px] bg-slate-50 border border-slate-100 rounded-md flex items-center justify-center text-slate-400 font-bold text-xs">
                            Updating trajectory forecast...
                        </div>
                        <VueApexCharts 
                            v-else 
                            :key="'trajectory-' + activeTimeTab" 
                            type="area" 
                            height="280" 
                            :options="statsOptions" 
                            :series="statsSeries" 
                        />
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>
