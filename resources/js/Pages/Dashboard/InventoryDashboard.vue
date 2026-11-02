<script setup>
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, defineAsyncComponent } from 'vue';
const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowPathIcon,
    CircleStackIcon,
    ExclamationTriangleIcon,
    InboxArrowDownIcon,
    ShoppingCartIcon,
    WalletIcon,
    BuildingOfficeIcon,
    ListBulletIcon,
    ScaleIcon,
    ArrowTrendingUpIcon,
    MagnifyingGlassIcon,
    CheckIcon,
    XMarkIcon,
    ChevronDownIcon,
    ChartBarIcon,
    PresentationChartLineIcon,
    ArrowDownLeftIcon,
    ArrowUpRightIcon,
    ArrowsRightLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    plants: Array,
    activePlantId: Number,
    metrics: Object,
    categoryBreakdown: Array,
    recentInwards: Array,
    lowStockItems: Array,
    abcAnalysis: Array,
    monthlyTrend: Object,
    ledger: Array,
});

const selectedPlant = ref(props.activePlantId);
const localMetrics = ref({ ...props.metrics });
const ledgerItems = ref([...props.ledger]);

const activePlantName = computed(() => {
    const p = (props.plants || []).find(plant => plant.id === selectedPlant.value);
    return p ? p.name : 'All Plants';
});

const filterPlant = (options = {}) => {
    const params = { plant_id: selectedPlant.value };
    if (options.refresh) {
        params.refresh = true;
    }
    router.get(route('inventory.dashboard'), params, { preserveState: false });
};

// Formatting helpers
const formatCurrency = (val) => {
    return new Intl.NumberFormat(APP_LOCALE, {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0
    }).format(Number(val || 0));
};

const formatNumber = (val) => {
    return new Intl.NumberFormat(APP_LOCALE).format(Number(val || 0));
};

const formatRelativeTime = (isoString) => {
    if (!isoString) return 'Just now';
    const date = new Date(isoString);
    const diffMs = new Date() - date;
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    return entityLocaleDate(date, APP_LOCALE, { day: '2-digit', month: 'short' });
};

// State for filtering & search
const searchFilter = ref('');
const activeStatusTab = ref('All');
const selectedCategory = ref('');

// Inline editor state
const editingId = ref(null);
const editValue = ref(0);

const startEdit = (item) => {
    editingId.value = item.id;
    editValue.value = item.quantity;
};

const cancelEdit = () => {
    editingId.value = null;
};

const saveEdit = async (item) => {
    const newVal = parseFloat(editValue.value);
    if (isNaN(newVal) || newVal < 0) return;

    try {
        const response = await axios.patch(route('inventory.adjust', { id: item.id }), {
            quantity: newVal
        });

        if (response.data.success) {
            // Update local state reactive values dynamically
            const index = ledgerItems.value.findIndex(i => i.id === item.id);
            if (index !== -1) {
                ledgerItems.value[index].quantity = newVal;
                ledgerItems.value[index].last_updated = new Date().toISOString();
                
                // Recalculate status
                const threshold = ledgerItems.value[index].alert_threshold;
                if (threshold > 0 && newVal === 0) {
                    ledgerItems.value[index].status = 'Out of Stock';
                } else if (threshold > 0 && newVal <= threshold) {
                    ledgerItems.value[index].status = 'Low Stock';
                } else {
                    ledgerItems.value[index].status = 'Healthy';
                }
            }

            // Update top metric values instantly
            localMetrics.value.total_valuation = response.data.metrics.total_valuation;
            localMetrics.value.total_items = response.data.metrics.total_items;
            localMetrics.value.low_stock_count = response.data.metrics.low_stock_count;
            localMetrics.value.inbound_30d_volume = response.data.metrics.inbound_30d_volume;
            localMetrics.value.outbound_30d_volume = response.data.metrics.outbound_30d_volume;
        }
    } catch (err) {
        console.error("Failed to adjust stock balance", err);
    } finally {
        editingId.value = null;
    }
};

// Filtered Ledger computation
const filteredLedger = computed(() => {
    return ledgerItems.value.filter(item => {
        const matchesSearch = item.name.toLowerCase().includes(searchFilter.value.toLowerCase()) || 
                              item.sku.toLowerCase().includes(searchFilter.value.toLowerCase()) ||
                              item.category.toLowerCase().includes(searchFilter.value.toLowerCase());
        
        const matchesCategory = !selectedCategory.value || item.category === selectedCategory.value;
        const matchesStatus = activeStatusTab.value === 'All' || item.status === activeStatusTab.value;

        return matchesSearch && matchesCategory && matchesStatus;
    });
});

// Chart Specs
const categoryChartSeries = computed(() => props.categoryBreakdown.map(c => c.value));
const categoryChartOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'Inter, sans-serif',
        events: {
            dataPointSelection: (event, chartContext, config) => {
                const categoryIndex = config.dataPointIndex;
                const categoryName = props.categoryBreakdown[categoryIndex]?.category;
                if (categoryName) {
                    if (selectedCategory.value === categoryName) {
                        selectedCategory.value = '';
                    } else {
                        selectedCategory.value = categoryName;
                    }
                }
            }
        }
    },
    labels: props.categoryBreakdown.map(c => c.category),
    colors: ['#27187e', '#758bfd', '#ff8600', '#aeb8fe', '#38bdf8'],
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    theme: { mode: 'light' },
    legend: {
        position: 'bottom',
        fontSize: '11px',
        labels: { colors: '#475569' }
    },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Assets Value',
                        formatter: () => formatCurrency(categoryChartSeries.value.reduce((a, b) => a + b, 0)),
                        fontSize: '13px',
                        color: '#475569'
                    }
                }
            }
        }
    }
}));

// Stock Turnover & Flow Trend controls & computations
const trendChartType = ref('area');
const trendSeriesFilter = ref('all');

const totalTrendReceipts = computed(() => {
    const list = props.monthlyTrend?.receipts || [];
    return list.reduce((sum, val) => sum + (Number(val) || 0), 0);
});

const totalTrendIssues = computed(() => {
    const list = props.monthlyTrend?.issues || [];
    return list.reduce((sum, val) => sum + (Number(val) || 0), 0);
});

const netTrendFlow = computed(() => {
    return totalTrendReceipts.value - totalTrendIssues.value;
});

const hasTrendData = computed(() => {
    return (props.monthlyTrend?.labels?.length > 0) && (totalTrendReceipts.value > 0 || totalTrendIssues.value > 0);
});

const filteredTrendSeries = computed(() => {
    const receiptsSeries = { name: 'Receipts (Inbound)', data: props.monthlyTrend?.receipts || [] };
    const issuesSeries = { name: 'Dispatches (Outbound)', data: props.monthlyTrend?.issues || [] };

    if (trendSeriesFilter.value === 'inbound') return [receiptsSeries];
    if (trendSeriesFilter.value === 'outbound') return [issuesSeries];
    return [receiptsSeries, issuesSeries];
});

const filteredTrendColors = computed(() => {
    if (trendSeriesFilter.value === 'inbound') return ['#758bfd'];
    if (trendSeriesFilter.value === 'outbound') return ['#ff8600'];
    return ['#758bfd', '#ff8600'];
});

const trendChartOptions = computed(() => ({
    chart: {
        type: trendChartType.value,
        toolbar: {
            show: true,
            tools: {
                download: true,
                selection: false,
                zoom: false,
                zoomin: false,
                zoomout: false,
                pan: false,
                reset: false
            }
        },
        background: 'transparent',
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 400
        }
    },
    theme: { mode: 'light' },
    stroke: {
        curve: 'smooth',
        width: trendChartType.value === 'area' ? 3 : 0
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '40%',
            borderRadius: 4,
            borderRadiusApplication: 'end'
        }
    },
    fill: trendChartType.value === 'area' ? {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.35,
            opacityTo: 0.04,
            stops: [0, 90, 100]
        }
    } : {
        opacity: 0.95
    },
    colors: filteredTrendColors.value,
    xaxis: {
        categories: props.monthlyTrend?.labels || [],
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
        title: {
            text: 'Quantity Units',
            style: {
                color: '#64748b',
                fontFamily: 'Inter, sans-serif',
                fontSize: '11px',
                fontWeight: 600
            }
        },
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '10px',
                fontFamily: 'Inter, sans-serif'
            },
            formatter: (val) => formatNumber(val)
        }
    },
    markers: {
        size: trendChartType.value === 'area' ? 4 : 0,
        strokeWidth: 2,
        strokeColors: '#ffffff',
        hover: { size: 6 }
    },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true } },
        padding: { top: 0, right: 10, bottom: 0, left: 10 }
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
        x: { show: true },
        y: {
            formatter: (val) => `${formatNumber(val)} units`
        }
    }
}));

const abcChartSeries = computed(() => props.abcAnalysis.map(a => a.value));
const abcChartOptions = computed(() => ({
    chart: {
        type: 'pie',
        fontFamily: 'Inter, sans-serif'
    },
    theme: { mode: 'light' },
    labels: props.abcAnalysis.map(a => a.class),
    colors: ['#27187e', '#758bfd', '#aeb8fe'],
    legend: {
        position: 'bottom',
        fontSize: '11px',
        labels: { colors: '#475569' }
    },
    stroke: { width: 0 },
    dataLabels: {
        enabled: true,
        formatter: (val) => `${val.toFixed(1)}%`
    }
}));
</script>

<template>
    <AppLayout title="SaaS ERP Inventory Command Center">
        <div class="min-h-screen bg-[#f1f2f6] text-slate-800 font-sans pb-16 selection:bg-[#758bfd] selection:text-white inventory-dashboard">
            <div class="mx-auto max-w-[1700px] px-4 py-6 sm:px-6 lg:px-8">

                <!-- HEADER -->
                <header class="rounded-md bg-white border border-slate-200/90 p-5 mb-6 flex flex-col md:flex-row md:items-center md:justify-between shadow-xs gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-[#27187e]/10 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-[#27187e] border border-[#27187e]/20">
                                <span class="size-2 rounded-full bg-[#758bfd] animate-pulse"></span>
                                Inventory Command Suite
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-[#f1f2f6] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 border border-slate-200">
                                <BuildingOfficeIcon class="size-3.5 text-[#27187e]" />
                                <span class="truncate max-w-[220px]">{{ activePlantName }}</span>
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#27187e]">Inventory Command Center</h1>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5 font-medium">Real-time stock valuation, consumption flows, and SKU balance ledger</p>
                    </div>

                    <!-- Plant Filter Widget -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex items-center">
                            <BuildingOfficeIcon class="absolute left-3 size-4 text-[#758bfd] pointer-events-none z-10" />
                            <select 
                                v-model="selectedPlant" 
                                class="appearance-none text-xs font-bold uppercase tracking-wider text-slate-700 bg-white border border-slate-200 rounded-md pl-9 pr-9 py-2.5 shadow-xs focus:border-[#758bfd] focus:ring-1 focus:ring-[#758bfd] focus:outline-none transition min-w-[200px] max-w-[280px] truncate cursor-pointer hover:border-[#aeb8fe]"
                                @change="filterPlant"
                            >
                                <option v-for="plant in plants" :key="plant.id" :value="plant.id" class="bg-white text-slate-800 normal-case font-medium">
                                    {{ plant.name }}
                                </option>
                            </select>
                            <ChevronDownIcon class="absolute right-3 size-4 text-slate-400 pointer-events-none z-10" />
                        </div>
                        <button 
                            type="button" 
                            class="p-2.5 rounded-md bg-white hover:bg-[#f1f2f6] active:bg-slate-200 transition border border-slate-200 shadow-xs text-[#27187e] hover:text-[#27187e] disabled:opacity-50 shrink-0"
                            @click="filterPlant({ refresh: true })"
                            title="Refresh Inventory Data"
                        >
                            <ArrowPathIcon class="size-4" />
                        </button>
                    </div>
                </header>

                <!-- KPI METRICS GRID -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Valuation Tile -->
                    <div class="relative overflow-hidden rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-[#aeb8fe]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span class="text-[10px] font-black uppercase tracking-wider">Total Stock Value</span>
                            <WalletIcon class="size-5 text-[#27187e]" />
                        </div>
                        <div class="my-4">
                            <h2 class="text-2xl font-black text-[#27187e] tracking-tight">{{ formatCurrency(localMetrics.total_valuation) }}</h2>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Capitalized asset</p>
                        </div>
                        <span class="inline-flex rounded-md bg-[#27187e]/10 px-2.5 py-0.5 text-[10px] font-bold text-[#27187e] border border-[#27187e]/20">Live valuation</span>
                    </div>

                    <!-- SKU Tile -->
                    <div class="relative overflow-hidden rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-[#aeb8fe]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span class="text-[10px] font-black uppercase tracking-wider">Active SKU Catalog</span>
                            <CircleStackIcon class="size-5 text-[#758bfd]" />
                        </div>
                        <div class="my-4">
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatNumber(localMetrics.total_items) }}</h2>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Cataloged codes</p>
                        </div>
                        <span class="inline-flex rounded-md bg-[#f1f2f6] px-2.5 py-0.5 text-[10px] font-bold text-slate-700 border border-slate-200">Inwards active</span>
                    </div>

                    <!-- Inbound vs Outbound Flow Tile -->
                    <div class="relative overflow-hidden rounded-md border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-[#aeb8fe]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span class="text-[10px] font-black uppercase tracking-wider">30d Movement Flow</span>
                            <ArrowTrendingUpIcon class="size-5 text-[#758bfd]" />
                        </div>
                        <div class="my-3 grid grid-cols-2 gap-2 border-t border-b border-slate-100 py-2.5">
                            <div>
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Inbound</span>
                                <h3 class="text-base font-black text-[#758bfd] tracking-tight mt-0.5">{{ formatNumber(localMetrics.inbound_30d_volume) }}</h3>
                            </div>
                            <div class="border-l border-slate-100 pl-3">
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Outbound</span>
                                <h3 class="text-base font-black text-[#ff8600] tracking-tight mt-0.5">{{ formatNumber(localMetrics.outbound_30d_volume) }}</h3>
                            </div>
                        </div>
                        <span class="inline-flex rounded-md bg-[#758bfd]/10 px-2.5 py-0.5 text-[10px] font-bold text-[#27187e] border border-[#758bfd]/20">Rolling 30-day units</span>
                    </div>

                    <!-- Alerts Tile -->
                    <div class="relative overflow-hidden rounded-md border bg-white p-5 shadow-xs transition hover:border-[#aeb8fe]" :class="localMetrics.low_stock_count > 0 ? 'border-[#ff8600]/40' : 'border-slate-200/90'">
                        <div class="flex items-center justify-between text-slate-500">
                            <span class="text-[10px] font-black uppercase tracking-wider">Low Stock Warnings</span>
                            <ExclamationTriangleIcon class="size-5" :class="localMetrics.low_stock_count > 0 ? 'text-[#ff8600] animate-pulse' : 'text-slate-400'" />
                        </div>
                        <div class="my-4 flex items-baseline gap-2">
                            <h2 class="text-2xl font-black tracking-tight" :class="localMetrics.low_stock_count > 0 ? 'text-[#ff8600]' : 'text-slate-900'">{{ formatNumber(localMetrics.low_stock_count) }}</h2>
                            <div v-if="localMetrics.low_stock_count > 0" class="flex h-2 w-2 relative"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#ff8600] opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-[#ff8600]"></span></div>
                        </div>
                        <span class="inline-flex rounded-md px-2.5 py-0.5 text-[10px] font-bold" :class="localMetrics.low_stock_count > 0 ? 'bg-[#ff8600]/10 text-[#ff8600] border border-[#ff8600]/30' : 'bg-[#f1f2f6] text-slate-600 border border-slate-200'">{{ localMetrics.low_stock_count > 0 ? 'Reorder needed' : 'Healthy inventory' }}</span>
                    </div>
                </section>

                <!-- HIGH IMPACT CHARTS -->
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Stock Turnover & Flow Trend (2/3 width) -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs lg:col-span-2 flex flex-col justify-between">
                        <!-- Chart Header & Interactive Controls -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4 pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-black text-[#27187e]">Stock Turnover & Flow Trend</h3>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-[#27187e]/10 text-[#27187e] border border-[#27187e]/20">
                                        6M Rolling
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Monthly comparison of Receipts (Inwards) vs Consumption & Dispatches (Issues)</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Series Filter (Both / Inbound / Outbound) -->
                                <div class="inline-flex bg-[#f1f2f6] border border-slate-200 p-0.5 rounded-md text-[10px] font-bold">
                                    <button 
                                        type="button" 
                                        @click="trendSeriesFilter = 'all'" 
                                        class="px-2.5 py-1 rounded-md transition"
                                        :class="trendSeriesFilter === 'all' ? 'bg-[#27187e] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                    >
                                        Both
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="trendSeriesFilter = 'inbound'" 
                                        class="px-2.5 py-1 rounded-md transition"
                                        :class="trendSeriesFilter === 'inbound' ? 'bg-[#758bfd] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                    >
                                        Inbound
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="trendSeriesFilter = 'outbound'" 
                                        class="px-2.5 py-1 rounded-md transition"
                                        :class="trendSeriesFilter === 'outbound' ? 'bg-[#ff8600] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                    >
                                        Outbound
                                    </button>
                                </div>

                                <!-- Chart Type View Toggle (Area vs Bar) -->
                                <div class="inline-flex bg-[#f1f2f6] border border-slate-200 p-0.5 rounded-md">
                                    <button 
                                        type="button" 
                                        @click="trendChartType = 'area'" 
                                        title="Smooth Area Curve"
                                        class="p-1.5 rounded-md transition"
                                        :class="trendChartType === 'area' ? 'bg-white text-[#27187e] shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                    >
                                        <PresentationChartLineIcon class="size-4" />
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="trendChartType = 'bar'" 
                                        title="Grouped Side-by-Side Bar Columns"
                                        class="p-1.5 rounded-md transition"
                                        :class="trendChartType === 'bar' ? 'bg-white text-[#27187e] shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                    >
                                        <ChartBarIcon class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 6-Month Summary Stat Strip -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <!-- Inflow Stat -->
                            <div class="flex items-center gap-3 p-3 bg-[#f1f2f6]/70 rounded-md border border-slate-200/70">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#758bfd]/15 text-[#758bfd]">
                                    <ArrowDownLeftIcon class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Total Inflow (6M)</span>
                                    <div class="text-base font-black text-slate-900 tracking-tight leading-tight">
                                        {{ formatNumber(totalTrendReceipts) }} <span class="text-[10px] font-semibold text-slate-500">units</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Outflow Stat -->
                            <div class="flex items-center gap-3 p-3 bg-[#f1f2f6]/70 rounded-md border border-slate-200/70">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#ff8600]/15 text-[#ff8600]">
                                    <ArrowUpRightIcon class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Total Outflow (6M)</span>
                                    <div class="text-base font-black text-slate-900 tracking-tight leading-tight">
                                        {{ formatNumber(totalTrendIssues) }} <span class="text-[10px] font-semibold text-slate-500">units</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Net Movement Stat -->
                            <div class="flex items-center gap-3 p-3 bg-[#f1f2f6]/70 rounded-md border border-slate-200/70">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#27187e]/10 text-[#27187e]">
                                    <ArrowsRightLeftIcon class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Net Flow Delta</span>
                                    <div class="text-base font-black tracking-tight leading-tight" :class="netTrendFlow >= 0 ? 'text-[#27187e]' : 'text-[#ff8600]'">
                                        {{ netTrendFlow > 0 ? '+' : '' }}{{ formatNumber(netTrendFlow) }} <span class="text-[10px] font-semibold text-slate-500">units</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Canvas or Empty State -->
                        <div class="w-full">
                            <div v-if="!hasTrendData" class="py-20 text-center text-xs font-semibold text-slate-400 bg-[#f1f2f6]/40 rounded-md border border-dashed border-slate-200">
                                <InboxArrowDownIcon class="size-8 text-slate-300 mx-auto mb-2" />
                                No movement flow recorded for {{ activePlantName }} over the last 6 months.
                            </div>
                            <VueApexCharts 
                                v-else 
                                :key="trendChartType + '-' + trendSeriesFilter" 
                                :type="trendChartType" 
                                height="290" 
                                :options="trendChartOptions" 
                                :series="filteredTrendSeries" 
                            />
                        </div>
                    </div>

                    <!-- Category Allocation Donut (1/3 width) -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div class="mb-4">
                            <h3 class="text-lg font-black text-[#27187e]">Asset Valuation by Category</h3>
                            <p class="text-xs text-slate-500 mt-0.5 font-medium">Inventory value allocation distribution</p>
                        </div>
                        <div class="flex-1 flex items-center justify-center">
                            <VueApexCharts type="donut" height="280" :options="categoryChartOptions" :series="categoryChartSeries" />
                        </div>
                    </div>
                </section>

                <!-- ABC ANALYSIS & QUICK WARNS -->
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- ABC classification -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-6 shadow-xs">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-black text-[#27187e]">ABC Category Class</h3>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">SKU sorting by value contribution</p>
                            </div>
                            <ScaleIcon class="size-5 text-[#758bfd]" />
                        </div>
                        
                        <div class="flex justify-center my-2">
                            <VueApexCharts type="pie" height="230" :options="abcChartOptions" :series="abcChartSeries" />
                        </div>
                    </div>

                    <!-- Critical Warnings Table/Panel -->
                    <div class="bg-white border border-slate-200/90 rounded-md p-6 shadow-xs lg:col-span-2">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-black text-[#27187e]">Critical Stock Warnings</h3>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Materials running below threshold levels</p>
                            </div>
                            <ExclamationTriangleIcon class="size-5 text-[#ff8600] animate-pulse" />
                        </div>

                        <!-- Alerts list -->
                        <div class="space-y-3 max-h-[220px] overflow-y-auto pr-2 custom-scrollbar">
                            <div 
                                v-for="item in lowStockItems" 
                                :key="item.id" 
                                class="relative overflow-hidden rounded-md border border-[#ff8600]/30 bg-[#ff8600]/5 px-4 py-3 flex items-center justify-between shadow-xs transition hover:border-[#ff8600]/50"
                            >
                                <div>
                                    <h4 class="font-black text-slate-900 text-sm">{{ item.name }}</h4>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Stock Level: <span class="font-bold text-[#ff8600]">{{ formatNumber(item.quantity) }}</span> 
                                        / Safe Target: {{ formatNumber(item.alert_level) }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center rounded-md bg-[#ff8600] px-3 py-1 text-xs font-bold text-white shadow-xs hover:bg-[#ff8600]/90 transition cursor-pointer">
                                    Reorder
                                </span>
                            </div>

                            <div v-if="!lowStockItems.length" class="py-16 text-center text-xs font-black uppercase tracking-wider text-slate-400 bg-[#f1f2f6] rounded-md border border-dashed border-slate-200">
                                No critical warnings. Stock holds safe.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- THE LEDGER (DATA GRID) -->
                <section class="grid grid-cols-1 gap-8">
                    <div class="bg-white border border-slate-200/90 rounded-md p-5 sm:p-6 shadow-xs">
                        
                        <!-- Search & Tabs -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-xl font-black text-[#27187e]">The Ledger</h3>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Real-time database records. Double-click stock quantity cells to adjust balances.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Global Search -->
                                <div class="relative">
                                    <input 
                                        v-model="searchFilter"
                                        type="text" 
                                        placeholder="Instant lookup..."
                                        class="text-xs font-bold text-slate-700 placeholder-slate-400 bg-[#f1f2f6]/50 border border-slate-200 focus:border-[#758bfd] focus:bg-white focus:ring-1 focus:ring-[#758bfd] focus:outline-none rounded-md pl-8 pr-4 py-2 transition"
                                    />
                                    <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-[#758bfd]" />
                                </div>

                                <!-- Category Dropdown -->
                                <div class="relative">
                                    <select 
                                        v-model="selectedCategory"
                                        class="text-xs font-black uppercase tracking-wider text-slate-700 border border-slate-200 bg-white rounded-md px-4 py-2 focus:border-[#758bfd] focus:ring-1 focus:ring-[#758bfd] focus:outline-none transition cursor-pointer hover:border-[#aeb8fe]"
                                    >
                                        <option value="">All Categories</option>
                                        <option v-for="cat in categoryBreakdown" :key="cat.category" :value="cat.category">
                                             {{ cat.category }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Filter Tabs -->
                                <div class="inline-flex bg-[#f1f2f6] border border-slate-200 p-1 rounded-md gap-1">
                                    <button 
                                        v-for="tab in ['All', 'Healthy', 'Low Stock', 'Out of Stock']"
                                        :key="tab"
                                        type="button"
                                        class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition"
                                        :class="activeStatusTab === tab ? 'bg-[#27187e] text-white shadow-xs' : 'text-slate-600 hover:text-[#27187e] hover:bg-white/60'"
                                        @click="activeStatusTab = tab"
                                    >
                                        {{ tab }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Data table layout -->
                        <div class="overflow-x-auto rounded-md border border-slate-200 bg-white">
                            <table class="w-full text-left border-collapse text-xs select-none">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-[#f1f2f6] text-[10px] font-black uppercase tracking-wider text-[#27187e]">
                                        <th class="px-6 py-4">Item ID</th>
                                        <th class="px-6 py-4">Item Name</th>
                                        <th class="px-6 py-4">Category</th>
                                        <th class="px-6 py-4 text-right">Current Balance</th>
                                        <th class="px-6 py-4">UOM</th>
                                        <th class="px-6 py-4">Last Sync</th>
                                        <th class="px-6 py-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr 
                                        v-for="item in filteredLedger" 
                                        :key="item.id" 
                                        class="hover:bg-[#f1f2f6]/50 transition-colors"
                                    >
                                        <!-- Item ID -->
                                        <td class="px-6 py-4 font-mono text-[11px] text-[#27187e] font-bold">#{{ item.sku }}</td>
                                        
                                        <!-- Item Name -->
                                        <td class="px-6 py-4 font-semibold text-slate-800">{{ item.name }}</td>
                                        
                                        <!-- Category -->
                                        <td class="px-6 py-4 font-medium text-slate-600">{{ item.category }}</td>
                                        
                                        <!-- Current Balance (Inline Editor) -->
                                        <td class="px-6 py-4 text-right">
                                            <div v-if="editingId === item.id" class="inline-flex items-center gap-1.5 justify-end">
                                                <input 
                                                    v-model="editValue"
                                                    type="number" 
                                                    step="0.01"
                                                    class="w-20 text-xs font-bold text-slate-800 bg-white border border-slate-300 focus:border-[#758bfd] focus:ring-1 focus:ring-[#758bfd] rounded-md px-2 py-1 text-right focus:outline-none"
                                                    @keyup.enter="saveEdit(item)"
                                                    @keyup.esc="cancelEdit"
                                                />
                                                <button @click="saveEdit(item)" class="p-1.5 rounded-md bg-[#27187e] text-white hover:bg-[#27187e]/90 transition">
                                                    <CheckIcon class="size-3" />
                                                </button>
                                                <button @click="cancelEdit" class="p-1.5 rounded-md bg-[#f1f2f6] text-slate-600 hover:text-slate-800 border border-slate-200 transition">
                                                    <XMarkIcon class="size-3" />
                                                </button>
                                            </div>
                                            <div 
                                                v-else 
                                                class="font-black text-slate-900 hover:text-[#758bfd] cursor-pointer transition-colors"
                                                title="Double-click to adjust balance"
                                                @dblclick="startEdit(item)"
                                            >
                                                {{ formatNumber(item.quantity) }}
                                            </div>
                                        </td>
                                        
                                        <!-- UOM -->
                                        <td class="px-6 py-4 text-slate-600 font-medium">{{ item.uom }}</td>
                                        
                                        <!-- Last Sync -->
                                        <td class="px-6 py-4 text-slate-500 font-medium">{{ formatRelativeTime(item.last_updated) }}</td>
                                        
                                        <!-- Status Badge -->
                                        <td class="px-6 py-4">
                                            <span 
                                                v-if="item.status === 'Out of Stock'" 
                                                class="inline-flex items-center gap-1.5 rounded-md bg-[#ff8600]/10 border border-[#ff8600]/30 px-2.5 py-0.5 text-[10px] font-bold text-[#ff8600] animate-pulse"
                                            >
                                                <span class="size-1.5 rounded-full bg-[#ff8600]"></span>
                                                {{ item.status }}
                                            </span>
                                            <span 
                                                v-else-if="item.status === 'Low Stock'" 
                                                class="inline-flex items-center gap-1.5 rounded-md bg-[#ff8600]/10 border border-[#ff8600]/30 px-2.5 py-0.5 text-[10px] font-bold text-[#ff8600]"
                                            >
                                                <span class="relative flex h-2 w-2">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#ff8600] opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-[#ff8600]"></span>
                                                </span>
                                                {{ item.status }}
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center gap-1.5 rounded-md bg-[#758bfd]/10 border border-[#758bfd]/30 px-2.5 py-0.5 text-[10px] font-bold text-[#27187e]"
                                            >
                                                <span class="size-1.5 rounded-full bg-[#758bfd]"></span>
                                                {{ item.status }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="!filteredLedger.length">
                                        <td colspan="7" class="px-6 py-16 text-center text-slate-400 font-bold uppercase tracking-wider bg-[#f1f2f6]/30">
                                            No inventory records match filters.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:root, .inventory-dashboard {
    --indigo-ink: #27187e;
    --cornflower-blue: #758bfd;
    --periwinkle: #aeb8fe;
    --platinum: #f1f2f6;
    --princeton-orange: #ff8600;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #aeb8fe;
    border-radius: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #758bfd;
}
</style>
