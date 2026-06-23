<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { ref, computed } from 'vue';
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
    XMarkIcon
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

const filterPlant = (options = {}) => {
    const params = { plant_id: selectedPlant.value };
    if (options.refresh) {
        params.refresh = true;
    }
    router.get(route('inventory.dashboard'), params, { preserveState: false });
};

// Formatting helpers
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0
    }).format(Number(val || 0));
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('en-IN').format(Number(val || 0));
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
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
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
    colors: ['#6366f1', '#10b981', '#f59e0b', '#38bdf8', '#ec4899'], // Modern Palette
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

const trendChartSeries = computed(() => [
    { name: 'Receipts (Inbounds)', data: props.monthlyTrend.receipts || [] },
    { name: 'Issues (Outbounds)', data: props.monthlyTrend.issues || [] }
]);
const trendChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        background: 'transparent'
    },
    theme: { mode: 'light' },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.25,
            opacityTo: 0.02,
            stops: [0, 90, 100]
        }
    },
    colors: ['#6366f1', '#f59e0b'],
    xaxis: {
        categories: props.monthlyTrend.labels || [],
        labels: { style: { colors: '#475569', fontSize: '10px' } }
    },
    yaxis: [
        {
            title: { text: 'Inbound Inflows', style: { color: '#6366f1', fontFamily: 'Inter, sans-serif' } },
            labels: { style: { colors: '#6366f1', fontSize: '10px' } }
        },
        {
            opposite: true,
            title: { text: 'Outbound Dispatches', style: { color: '#f59e0b', fontFamily: 'Inter, sans-serif' } },
            labels: { style: { colors: '#f59e0b', fontSize: '10px' } }
        }
    ],
    grid: {
        borderColor: 'rgba(0, 0, 0, 0.05)',
        strokeDashArray: 5
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        labels: { colors: '#475569' }
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
    colors: ['#6366f1', '#94a3b8', '#cbd5e1'],
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
        <div class="min-h-screen bg-[#f8fafc] text-slate-800 font-sans pb-16 selection:bg-indigo-500 selection:text-white">
            <div class="mx-auto max-w-[1700px] px-4 py-6 sm:px-6 lg:px-8">

                <!-- FUTURISTIC HEADER -->
                <header class="relative overflow-hidden rounded-[2.5rem] bg-white/70 border border-slate-200/80 p-6 mb-8 flex flex-col md:flex-row md:items-center md:justify-between shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl gap-6">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(99,102,241,0.05),transparent_35%)] pointer-events-none" />
                    
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-indigo-600">
                                <span class="size-2 rounded-full bg-indigo-500 animate-pulse"></span>
                                SaaS Command Suite
                            </span>
                            <span class="text-xs font-bold text-slate-500">Inventory Management</span>
                        </div>
                        <h1 class="text-3xl font-black tracking-tight text-slate-900">SaaS Inventory Command Center</h1>
                    </div>

                    <!-- Plant Filter Widget -->
                    <div class="relative z-10 flex items-center gap-3 bg-slate-50 border border-slate-200/80 p-1.5 rounded-2xl">
                        <select 
                            v-model="selectedPlant" 
                            class="text-xs font-black uppercase tracking-wider text-slate-700 border-0 bg-transparent rounded-xl px-4 py-2 focus:ring-0 focus:outline-none"
                            @change="filterPlant"
                        >
                            <option v-for="plant in plants" :key="plant.id" :value="plant.id" class="bg-white text-slate-800">{{ plant.name }}</option>
                        </select>
                        <button 
                            type="button" 
                            class="p-2 rounded-xl bg-white hover:bg-slate-100 transition border border-slate-200"
                            @click="filterPlant({ refresh: true })"
                        >
                            <ArrowPathIcon class="size-4 text-slate-600" />
                        </button>
                    </div>
                </header>

                <!-- GLASSMORPHIC KPI GRID -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Valuation Tile -->
                    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/70 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl transition hover:border-indigo-500/30">
                        <div class="flex items-center justify-between text-slate-505">
                            <span class="text-[10px] font-black uppercase tracking-wider">Total Stock Value</span>
                            <WalletIcon class="size-5 text-indigo-500" />
                        </div>
                        <div class="my-4">
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(localMetrics.total_valuation) }}</h2>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Capitalized asset</p>
                        </div>
                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-[10px] font-bold text-indigo-600">Live valuation</span>
                    </div>

                    <!-- SKU Tile -->
                    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/70 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl transition hover:border-indigo-500/30">
                        <div class="flex items-center justify-between text-slate-505">
                            <span class="text-[10px] font-black uppercase tracking-wider">Active SKU Catalog</span>
                            <CircleStackIcon class="size-5 text-indigo-500" />
                        </div>
                        <div class="my-4">
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ formatNumber(localMetrics.total_items) }}</h2>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Cataloged codes</p>
                        </div>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">Inwards active</span>
                    </div>

                    <!-- Inbound vs Outbound Flow Tile -->
                    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/70 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl transition hover:border-indigo-500/30">
                        <div class="flex items-center justify-between text-slate-505">
                            <span class="text-[10px] font-black uppercase tracking-wider">30d Movement Flow</span>
                            <ArrowTrendingUpIcon class="size-5 text-indigo-500" />
                        </div>
                        <div class="my-3 grid grid-cols-2 gap-2 border-t border-b border-slate-100 py-2.5">
                            <div>
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Inbound</span>
                                <h3 class="text-base font-black text-indigo-600 tracking-tight mt-0.5">{{ formatNumber(localMetrics.inbound_30d_volume) }}</h3>
                            </div>
                            <div class="border-l border-slate-100 pl-3">
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Outbound</span>
                                <h3 class="text-base font-black text-amber-600 tracking-tight mt-0.5">{{ formatNumber(localMetrics.outbound_30d_volume) }}</h3>
                            </div>
                        </div>
                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-[10px] font-bold text-indigo-600">Rolling 30-day units</span>
                    </div>

                    <!-- Alerts Tile -->
                    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/70 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl transition hover:border-indigo-500/30" :class="localMetrics.low_stock_count > 0 ? 'border-amber-500/30' : ''">
                        <div class="flex items-center justify-between text-slate-505">
                            <span class="text-[10px] font-black uppercase tracking-wider">Low Stock Warnings</span>
                            <ExclamationTriangleIcon class="size-5" :class="localMetrics.low_stock_count > 0 ? 'text-amber-500 animate-pulse' : 'text-slate-400'" />
                        </div>
                        <div class="my-4 flex items-baseline gap-2">
                            <h2 class="text-2xl font-black tracking-tight" :class="localMetrics.low_stock_count > 0 ? 'text-amber-600' : 'text-slate-900'">{{ formatNumber(localMetrics.low_stock_count) }}</h2>
                            <div v-if="localMetrics.low_stock_count > 0" class="flex h-2 w-2 relative"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span></div>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold" :class="localMetrics.low_stock_count > 0 ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500'">{{ localMetrics.low_stock_count > 0 ? 'Reorder needed' : 'Healthy inventory' }}</span>
                    </div>
                </section>

                <!-- HIGH IMPACT CHARTS -->
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Timeline Receipt vs Issue -->
                    <div class="bg-white/70 border border-slate-200/80 rounded-[2.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl lg:col-span-2">
                        <div class="mb-4">
                            <h3 class="text-lg font-black text-slate-900">Stock Turnover & Flow Trend</h3>
                            <p class="text-xs text-slate-500 mt-0.5 font-medium">Monthly timeline comparison of Receipts (Inwards) vs Consumption (Issues)</p>
                        </div>
                        <VueApexCharts type="area" height="260" :options="trendChartOptions" :series="trendChartSeries" />
                    </div>

                    <!-- Category Allocation Donut -->
                    <div class="bg-white/70 border border-slate-200/80 rounded-[2.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl">
                        <div class="mb-4">
                            <h3 class="text-lg font-black text-slate-900">Asset Valuation by Category</h3>
                            <p class="text-xs text-slate-500 mt-0.5 font-medium">Inventory value allocation distribution</p>
                        </div>
                        <VueApexCharts type="donut" height="250" :options="categoryChartOptions" :series="categoryChartSeries" />
                    </div>
                </section>

                <!-- ABC ANALYSIS & QUICK WARNS -->
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- ABC classification -->
                    <div class="bg-white/70 border border-slate-200/80 rounded-[2.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">ABC Category Class</h3>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">SKU sorting by value contribution</p>
                            </div>
                            <ScaleIcon class="size-5 text-slate-400" />
                        </div>
                        
                        <div class="flex justify-center my-2">
                            <VueApexCharts type="pie" height="230" :options="abcChartOptions" :series="abcChartSeries" />
                        </div>
                    </div>

                    <!-- Critical Warnings Table/Panel -->
                    <div class="bg-white/70 border border-slate-200/80 rounded-[2.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl lg:col-span-2">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">Critical Stock Warnings</h3>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Materials running below threshold levels</p>
                            </div>
                            <ExclamationTriangleIcon class="size-5 text-red-500 animate-pulse" />
                        </div>

                        <!-- Alerts list -->
                        <div class="space-y-3 max-h-[220px] overflow-y-auto pr-2 custom-scrollbar">
                            <div 
                                v-for="item in lowStockItems" 
                                :key="item.id" 
                                class="relative overflow-hidden rounded-2xl border border-red-200 bg-red-50/50 px-4 py-3 flex items-center justify-between shadow-[0_4px_20px_rgba(239,68,68,0.02)]"
                            >
                                <div>
                                    <h4 class="font-black text-slate-900 text-sm">{{ item.name }}</h4>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Stock Level: <span class="font-bold text-red-600">{{ formatNumber(item.quantity) }}</span> 
                                        / Safe Target: {{ formatNumber(item.alert_level) }}
                                    </p>
                                </div>
                                <span class="inline-flex rounded-full bg-red-100 border border-red-200 px-3 py-1 text-xs font-bold text-red-700">
                                    Reorder
                                </span>
                            </div>

                            <div v-if="!lowStockItems.length" class="py-16 text-center text-xs font-black uppercase tracking-wider text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                No critical warnings. Stock holds safe.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- THE LEDGER (DATA GRID) -->
                <section class="grid grid-cols-1 gap-8">
                    <div class="bg-white/70 border border-slate-200/80 rounded-[2.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.03)] backdrop-blur-xl">
                        
                        <!-- Search & Tabs -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-xl font-black text-slate-900">The Ledger</h3>
                                <p class="text-xs text-slate-500 mt-0.5 font-medium">Real-time database records. Double-click stock quantity cells to adjust balances.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Global Search -->
                                <div class="relative">
                                    <input 
                                        v-model="searchFilter"
                                        type="text" 
                                        placeholder="Instant lookup..."
                                        class="text-xs font-bold text-slate-700 placeholder-slate-400 bg-slate-50 border border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-0 rounded-xl pl-8 pr-4 py-2"
                                    />
                                    <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                                </div>

                                <!-- Category Dropdown -->
                                <div class="relative">
                                    <select 
                                        v-model="selectedCategory"
                                        class="text-xs font-black uppercase tracking-wider text-slate-700 border border-slate-200 bg-white rounded-xl px-4 py-2 focus:ring-0 focus:outline-none"
                                    >
                                        <option value="">All Categories</option>
                                        <option v-for="cat in categoryBreakdown" :key="cat.category" :value="cat.category">
                                            {{ cat.category }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Filter Tabs -->
                                <div class="inline-flex bg-slate-50 border border-slate-200/80 p-1 rounded-xl">
                                    <button 
                                        v-for="tab in ['All', 'Healthy', 'Low Stock', 'Out of Stock']"
                                        :key="tab"
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition"
                                        :class="activeStatusTab === tab ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                                        @click="activeStatusTab = tab"
                                    >
                                        {{ tab }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Data table layout -->
                        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white">
                            <table class="w-full text-left border-collapse text-xs select-none">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/55 text-[9px] font-black uppercase tracking-wider text-slate-500">
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
                                        class="hover:bg-slate-50/60 transition-colors"
                                    >
                                        <!-- Item ID -->
                                        <td class="px-6 py-4 font-mono text-[10px] text-indigo-600 font-bold">#{{ item.sku }}</td>
                                        
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
                                                    class="w-20 text-xs font-bold text-slate-800 bg-white border border-slate-200 focus:border-indigo-500 focus:ring-0 rounded px-1.5 py-0.5 text-right"
                                                    @keyup.enter="saveEdit(item)"
                                                    @keyup.esc="cancelEdit"
                                                />
                                                <button @click="saveEdit(item)" class="p-1 rounded bg-indigo-600 text-white hover:bg-indigo-500">
                                                    <CheckIcon class="size-3" />
                                                </button>
                                                <button @click="cancelEdit" class="p-1 rounded bg-slate-100 text-slate-500 hover:text-slate-800 border border-slate-200">
                                                    <XMarkIcon class="size-3" />
                                                </button>
                                            </div>
                                            <div 
                                                v-else 
                                                class="font-black text-slate-800 hover:text-indigo-600 cursor-pointer transition-colors"
                                                title="Double-click to adjust balance"
                                                @dblclick="startEdit(item)"
                                            >
                                                {{ formatNumber(item.quantity) }}
                                            </div>
                                        </td>
                                        
                                        <!-- UOM -->
                                        <td class="px-6 py-4 text-slate-600 font-medium">{{ item.uom }}</td>
                                        
                                        <!-- Last Sync -->
                                        <td class="px-6 py-4 text-slate-600 font-medium">{{ formatRelativeTime(item.last_updated) }}</td>
                                        
                                        <!-- Status Badge -->
                                        <td class="px-6 py-4">
                                            <span 
                                                v-if="item.status === 'Out of Stock'" 
                                                class="inline-flex items-center gap-1.5 rounded-full bg-red-50 border border-red-200/80 px-2.5 py-0.5 font-bold text-red-700 shadow-[0_0_15px_rgba(239,68,68,0.05)] animate-pulse"
                                            >
                                                <span class="size-1.5 rounded-full bg-red-500"></span>
                                                {{ item.status }}
                                            </span>
                                            <span 
                                                v-else-if="item.status === 'Low Stock'" 
                                                class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200/80 px-2.5 py-0.5 font-bold text-amber-700 shadow-[0_0_12px_rgba(245,158,11,0.05)]"
                                            >
                                                <span class="relative flex h-2 w-2">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                                </span>
                                                {{ item.status }}
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 font-bold text-emerald-700"
                                            >
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                {{ item.status }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="!filteredLedger.length">
                                        <td colspan="7" class="px-6 py-16 text-center text-slate-400 font-bold uppercase tracking-wider">
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
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}
</style>
