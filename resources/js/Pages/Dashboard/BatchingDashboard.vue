<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, defineAsyncComponent } from 'vue';
const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));
import axios from 'axios';
import {
    ArrowPathIcon,
    CpuChipIcon,
    ExclamationTriangleIcon,
    ScaleIcon,
    TruckIcon,
    MagnifyingGlassIcon,
    CheckIcon,
    XMarkIcon,
    BeakerIcon,
    DocumentDuplicateIcon,
    CreditCardIcon,
    TicketIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    plants: Array,
    activePlantId: Number,
});

const selectedPlant = ref(props.activePlantId);
const loading = ref(true);
const dashboardData = ref({
    kpis: {
        total_volume: 0,
        active_batches: 0,
        deviation_rate: 0,
        avg_batch_size: 0,
    },
    charts: {
        trend: { labels: [], volumes: [], counts: [] },
        distribution: []
    },
    ledger: [],
    recipes: []
});

const searchFilter = ref('');
const activeStatusTab = ref('All');
const selectedGrade = ref('');

const fetchDashboardData = async ({ refresh = false } = {}) => {
    loading.value = true;
    try {
        const params = { plant_id: selectedPlant.value };
        if (refresh) {
            params.refresh = true;
        }
        const response = await axios.get(route('production.dashboard.data'), { params });
        if (response.data.success) {
            dashboardData.value = response.data;
        }
    } catch (err) {
        console.error("Failed to load production statistics", err);
    } finally {
        loading.value = false;
    }
};

const filterPlant = () => {
    fetchDashboardData();
};

onMounted(() => {
    fetchDashboardData();
});

// Formatting helpers
const formatVolume = (val) => {
    return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 1 }).format(Number(val || 0)) + ' m³';
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('en-IN').format(Number(val || 0));
};

const formatTime = (isoString) => {
    if (!isoString) return 'Pending';
    const date = new Date(isoString);
    return date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) + ' | ' + date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
};

// Filtered Ledger computation
const filteredLedger = computed(() => {
    return (dashboardData.value.ledger || []).filter(item => {
        const matchesSearch = 
            item.batch_no.toString().toLowerCase().includes(searchFilter.value.toLowerCase()) || 
            item.customer.toLowerCase().includes(searchFilter.value.toLowerCase()) ||
            item.site.toLowerCase().includes(searchFilter.value.toLowerCase()) ||
            item.truck.toLowerCase().includes(searchFilter.value.toLowerCase());
        
        const matchesGrade = !selectedGrade.value || item.mix_design === selectedGrade.value;
        
        let matchesStatus = true;
        if (activeStatusTab.value === 'Active') {
            matchesStatus = ['Planned', 'Loading', 'Dispatched'].includes(item.status);
        } else if (activeStatusTab.value !== 'All') {
            matchesStatus = item.status === activeStatusTab.value;
        }

        return matchesSearch && matchesGrade && matchesStatus;
    });
});

// Computed values for metrics
const totalCompletedLoads = computed(() => {
    return (dashboardData.value.charts?.trend?.counts || []).reduce((a, b) => a + b, 0);
});

const activeTrucksCount = computed(() => {
    const trucks = (dashboardData.value.ledger || [])
        .filter(item => ['Planned', 'Loading', 'Dispatched'].includes(item.status))
        .map(item => item.truck);
    return new Set(trucks).size;
});

const topGrades = computed(() => {
    const sorted = [...(dashboardData.value.charts?.distribution || [])].sort((a, b) => b.value - a.value);
    return {
        first: sorted[0] || { grade: 'N/A', value: 0 },
        second: sorted[1] || { grade: 'N/A', value: 0 }
    };
});

const totalBatchesCount = computed(() => {
    return dashboardData.value.ledger?.length || 0;
});
const activeMixesCount = computed(() => {
    return dashboardData.value.charts?.distribution?.length || 0;
});
const activeRecipesCount = computed(() => {
    return dashboardData.value.recipes?.length || 0;
});

// Production Flow Trend (Area Chart Multi-Axis)
const trendChartSeries = computed(() => [
    { name: 'Production Volume (m³)', type: 'area', data: dashboardData.value.charts.trend.volumes || [] },
    { name: 'Batch Load Count', type: 'line', data: dashboardData.value.charts.trend.counts || [] }
]);

const trendChartOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        background: 'transparent',
        fontFamily: 'Inter, sans-serif'
    },
    stroke: {
        curve: 'smooth',
        width: [3, 4]
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: [0.3, 0],
            opacityTo: [0.02, 0],
            stops: [0, 90, 100]
        }
    },
    colors: ['#f05252', '#3f83f8'], // Red/Orange vs. Blue/Indigo (matching sales vs purchase warm tone structure)
    xaxis: {
        categories: dashboardData.value.charts.trend.labels || [],
        labels: { style: { colors: '#9ca3af', fontSize: '10px' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: [
        {
            title: { text: 'Concrete Volume (m³)', style: { color: '#f05252', fontFamily: 'Inter, sans-serif', fontWeight: 600 } },
            labels: { style: { colors: '#f05252', fontSize: '10px' } }
        },
        {
            opposite: true,
            title: { text: 'Batch Loads Count', style: { color: '#3f83f8', fontFamily: 'Inter, sans-serif', fontWeight: 600 } },
            labels: { style: { colors: '#3f83f8', fontSize: '10px' } }
        }
    ],
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 5
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        labels: { colors: '#4b5563' }
    },
    theme: { mode: 'light' }
}));

// Donut Chart - Concrete Grades Distribution
const donutChartSeries = computed(() => (dashboardData.value.charts.distribution || []).map(c => c.value));
const donutChartOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'Inter, sans-serif',
        events: {
            dataPointSelection: (event, chartContext, config) => {
                const itemIndex = config.dataPointIndex;
                const gradeName = dashboardData.value.charts.distribution[itemIndex]?.grade;
                if (gradeName) {
                    if (selectedGrade.value === gradeName) {
                        selectedGrade.value = '';
                    } else {
                        selectedGrade.value = gradeName;
                    }
                }
            }
        }
    },
    labels: (dashboardData.value.charts.distribution || []).map(c => c.grade),
    colors: ['#0e9f6e', '#3f83f8', '#e3a008', '#f05252', '#6366f1'], // Red/Orange, green, blue, yellow matching reference palettes
    stroke: { width: 0 },
    theme: { mode: 'light' },
    legend: {
        show: false // Custom legend on the right side of card
    },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: {
            donut: {
                size: '75%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total Volume',
                        formatter: () => formatVolume(donutChartSeries.value.reduce((a, b) => a + b, 0)),
                        fontSize: '12px',
                        fontWeight: 600,
                        color: '#4b5563'
                    }
                }
            }
        }
    }
}));
</script>

<template>
    <AppLayout title="Production & Mixing Command Center">
        <div class="min-h-screen bg-[#f8fafc] text-slate-800 font-sans pb-16 relative selection:bg-[#f05252] selection:text-white">
            <div class="mx-auto max-w-[1700px] px-4 py-6 sm:px-6 lg:px-8">

                <!-- HEADER -->
                <header class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-6">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Dashboard</h1>
                        <p class="text-sm text-slate-500 mt-1">Concrete batching and mixing command center</p>
                    </div>

                    <!-- Plant Switcher widget -->
                    <div class="flex items-center gap-3 bg-white border border-slate-200 p-2 rounded-xl shadow-sm">
                        <select 
                            v-model="selectedPlant" 
                            class="text-xs font-semibold uppercase tracking-wider text-slate-700 border-0 bg-transparent rounded-lg px-4 py-2 focus:ring-0 focus:outline-none"
                            @change="filterPlant"
                        >
                            <option v-for="plant in plants" :key="plant.id" :value="plant.id" class="bg-white text-slate-800">{{ plant.name }}</option>
                        </select>
                        <button 
                            type="button" 
                            class="p-2 rounded-lg bg-white hover:bg-slate-100 transition border border-slate-200"
                            @click="fetchDashboardData({ refresh: true })"
                            :disabled="loading"
                        >
                            <ArrowPathIcon class="size-4 text-slate-600" :class="loading ? 'animate-spin' : ''" />
                        </button>
                    </div>
                </header>

                <!-- SKELETON LOADER -->
                <div v-if="loading" class="space-y-8 animate-pulse">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="i in 4" :key="i" class="h-28 bg-slate-200/50 rounded-xl"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div v-for="i in 3" :key="i" class="h-32 bg-slate-200/50 rounded-xl"></div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="h-80 bg-slate-200/50 rounded-xl lg:col-span-2"></div>
                        <div class="h-80 bg-slate-200/50 rounded-xl"></div>
                    </div>
                </div>

                <!-- MAIN CONTROLLER LAYOUT -->
                <div v-else>
                    <!-- KPI GRID -->
                    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Volume Produced Tile (Green theme) -->
                        <div class="flex items-center p-6 bg-[#f0fdf4] border border-[#def7ec] rounded-xl shadow-sm hover:shadow transition">
                            <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-[#0e9f6e] text-white shadow-sm">
                                <ScaleIcon class="size-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Volume Produced</div>
                                <div class="text-2xl font-bold text-slate-900 mt-1">{{ formatVolume(dashboardData.kpis.total_volume) }}</div>
                                <div class="text-xs font-semibold text-[#0e9f6e] mt-1">Dispatched shift output</div>
                            </div>
                        </div>

                        <!-- Active Batches Tile (Blue theme) -->
                        <div class="flex items-center p-6 bg-[#f0f9ff] border border-[#e1f5fe] rounded-xl shadow-sm hover:shadow transition">
                            <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-[#3f83f8] text-white shadow-sm">
                                <CpuChipIcon class="size-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Active Batch Runs</div>
                                <div class="text-2xl font-bold text-slate-900 mt-1">{{ dashboardData.kpis.active_batches }}</div>
                                <div class="text-xs font-semibold text-[#3f83f8] mt-1">Current mixing queue</div>
                            </div>
                        </div>

                        <!-- Deviation Warning Tile (Red/Pink theme) -->
                        <div class="flex items-center p-6 bg-[#fdf2f2] border border-[#fde8e8] rounded-xl shadow-sm hover:shadow transition">
                            <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-[#f05252] text-white shadow-sm">
                                <ExclamationTriangleIcon class="size-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Recipe Deviation Alert</div>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <div class="text-2xl font-bold text-slate-900">{{ dashboardData.kpis.deviation_rate }}%</div>
                                    <div v-if="dashboardData.kpis.deviation_rate > 0" class="flex h-2 w-2 relative">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                    </div>
                                </div>
                                <div class="text-xs font-semibold text-[#f05252] mt-1">{{ dashboardData.kpis.deviation_rate > 5 ? 'Exceeds 5% tolerance' : 'Within Tolerance' }}</div>
                            </div>
                        </div>

                        <!-- Avg Batch Size Tile (Yellow theme) -->
                        <div class="flex items-center p-6 bg-[#fdfaeb] border border-[#fdf0cd] rounded-xl shadow-sm hover:shadow transition">
                            <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-[#e3a008] text-white shadow-sm">
                                <TruckIcon class="size-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Avg Transit Size</div>
                                <div class="text-2xl font-bold text-slate-900 mt-1">{{ formatVolume(dashboardData.kpis.avg_batch_size) }}</div>
                                <div class="text-xs font-semibold text-[#e3a008] mt-1">Average load volume</div>
                            </div>
                        </div>
                    </section>

                    <!-- SECOND ROW STAT CARDS -->
                    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Completed Loads Card -->
                        <div class="bg-white border border-slate-200 rounded-xl p-6 relative shadow-sm hover:border-slate-300 transition flex justify-between items-start">
                            <div>
                                <div class="text-2xl font-bold text-slate-900">{{ formatNumber(totalCompletedLoads) }}</div>
                                <div class="text-xs font-semibold text-slate-500 mt-1">Completed Batch Loads</div>
                                <div class="text-[10px] font-bold text-emerald-600 mt-3 flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    Dispatched shift tickets
                                </div>
                            </div>
                            <DocumentDuplicateIcon class="size-7 text-[#e3a008]/80" />
                            <a href="#ledger" class="absolute bottom-6 right-6 text-xs font-bold text-[#e3a008] hover:underline">View</a>
                        </div>

                        <!-- Adherence Card -->
                        <div class="bg-white border border-slate-200 rounded-xl p-6 relative shadow-sm hover:border-slate-300 transition flex justify-between items-start">
                            <div>
                                <div class="text-2xl font-bold text-slate-900">{{ formatNumber(100 - dashboardData.kpis.deviation_rate) }}%</div>
                                <div class="text-xs font-semibold text-slate-500 mt-1">Recipe Adherence Rate</div>
                                <div class="text-[10px] font-bold text-slate-400 mt-3">Within material tolerance limits</div>
                            </div>
                            <BeakerIcon class="size-7 text-red-500/80" />
                            <a href="#recipes" class="absolute bottom-6 right-6 text-xs font-bold text-red-500 hover:underline">View</a>
                        </div>

                        <!-- Fleet Transits Card -->
                        <div class="bg-white border border-slate-200 rounded-xl p-6 relative shadow-sm hover:border-slate-300 transition flex justify-between items-start">
                            <div>
                                <div class="text-2xl font-bold text-slate-900">{{ activeTrucksCount }}</div>
                                <div class="text-xs font-semibold text-slate-500 mt-1">Active Trucks Transiting</div>
                                <div class="text-[10px] font-bold text-slate-400 mt-3">Currently dispatched concrete mixers</div>
                            </div>
                            <TruckIcon class="size-7 text-[#3f83f8]/80" />
                            <a href="#ledger" class="absolute bottom-6 right-6 text-xs font-bold text-[#3f83f8] hover:underline">View</a>
                        </div>
                    </section>

                    <!-- INTERACTIVE CHARTS -->
                    <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                        <!-- Daily Production trend -->
                        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm lg:col-span-2">
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Production Trends</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 font-medium">Daily concrete volume produced vs daily load count</p>
                                </div>
                                <select class="text-xs font-semibold text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 focus:outline-none">
                                    <option>This Year</option>
                                    <option>Last 6 Months</option>
                                    <option>This Month</option>
                                </select>
                            </div>
                            <div v-if="dashboardData.charts.trend.volumes.length === 0" class="h-[260px] flex items-center justify-center border border-dashed border-slate-200 rounded-xl text-xs font-bold text-slate-400 uppercase tracking-wider">
                                No production data logged over past 30 days.
                            </div>
                            <VueApexCharts v-else type="line" height="260" :options="trendChartOptions" :series="trendChartSeries" />
                        </div>

                        <!-- Concrete Grade Distribution Donut inside "Overall Information" card -->
                        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="mb-4 flex items-center justify-between">
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">Overall Information</h3>
                                        <p class="text-xs text-slate-500 mt-0.5 font-medium">Concrete Mix Allocation</p>
                                    </div>
                                    <select class="text-xs font-semibold text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 focus:outline-none">
                                        <option>Last 6 Months</option>
                                        <option>This Month</option>
                                    </select>
                                </div>

                                <div v-if="dashboardData.charts.distribution.length === 0" class="h-[200px] flex items-center justify-center border border-dashed border-slate-200 rounded-xl text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    No grades produced.
                                </div>
                                <div v-else class="flex items-center gap-4 mt-6">
                                    <div class="flex-1 max-w-[150px]">
                                        <VueApexCharts type="donut" height="150" :options="donutChartOptions" :series="donutChartSeries" />
                                    </div>
                                    <!-- Sidebar breakdown -->
                                    <div class="flex-1 space-y-3">
                                        <div class="border-l-4 border-[#0e9f6e] pl-3 py-0.5">
                                            <div class="text-[10px] font-bold text-slate-400 uppercase">Primary Grade</div>
                                            <div class="text-sm font-bold text-slate-800 truncate mt-0.5" :title="topGrades.first.grade">{{ topGrades.first.grade }}</div>
                                            <div class="text-xs font-semibold text-slate-500 mt-0.5">{{ formatVolume(topGrades.first.value) }}</div>
                                        </div>
                                        <div class="border-l-4 border-[#3f83f8] pl-3 py-0.5">
                                            <div class="text-[10px] font-bold text-slate-400 uppercase">Secondary Grade</div>
                                            <div class="text-sm font-bold text-slate-800 truncate mt-0.5" :title="topGrades.second.grade">{{ topGrades.second.grade }}</div>
                                            <div class="text-xs font-semibold text-slate-500 mt-0.5">{{ formatVolume(topGrades.second.value) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom 3-column stats with dividers -->
                            <div class="border-t border-slate-100 mt-6 pt-4 grid grid-cols-3 text-center">
                                <div>
                                    <div class="text-xl font-bold text-slate-900">{{ totalBatchesCount }}</div>
                                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">Batches</div>
                                </div>
                                <div class="border-l border-slate-100">
                                    <div class="text-xl font-bold text-slate-900">{{ activeMixesCount }}</div>
                                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">Grades</div>
                                </div>
                                <div class="border-l border-slate-100">
                                    <div class="text-xl font-bold text-slate-900">{{ activeRecipesCount }}</div>
                                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">Recipes</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- WORKSPACE DATA GRID: TABLES -->
                    <section id="ledger" class="grid grid-cols-1 gap-8 mb-8">
                        <!-- PRODUCTION LEDGER TABLE -->
                        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Active Production Ledger</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 font-medium">Real-time concrete batching tickets, PLC recipe deviation limits, and ERP sync status.</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <!-- Global lookup -->
                                    <div class="relative">
                                        <input 
                                            v-model="searchFilter"
                                            type="text" 
                                            placeholder="Batch, Customer, Truck..."
                                            class="text-xs font-semibold text-slate-700 placeholder-slate-400 bg-slate-50 border border-slate-200 focus:border-[#f05252] focus:bg-white focus:ring-0 rounded-xl pl-8 pr-4 py-2"
                                        />
                                        <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                                    </div>

                                    <!-- Grade Filter Dropdown -->
                                    <div class="relative">
                                        <select 
                                            v-model="selectedGrade"
                                            class="text-xs font-semibold text-slate-700 border border-slate-200 bg-white rounded-xl px-4 py-2 focus:ring-0 focus:outline-none"
                                        >
                                            <option value="">All Mixes</option>
                                            <option v-for="item in dashboardData.charts.distribution" :key="item.grade" :value="item.grade">
                                                {{ item.grade }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Filter tabs -->
                                    <div class="inline-flex bg-slate-50 border border-slate-200 p-1 rounded-xl">
                                        <button 
                                            v-for="tab in ['All', 'Active', 'Completed', 'Cancelled']"
                                            :key="tab"
                                            type="button"
                                            class="rounded-lg px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider transition"
                                            :class="activeStatusTab === tab ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                            @click="activeStatusTab = tab"
                                        >
                                            {{ tab }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Data Table -->
                            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                                <table class="w-full text-left border-collapse text-xs select-none">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                            <th class="px-6 py-4">Batch ID</th>
                                            <th class="px-6 py-4">Customer</th>
                                            <th class="px-6 py-4">Site</th>
                                            <th class="px-6 py-4">Mix Design</th>
                                            <th class="px-6 py-4 text-right">Volume</th>
                                            <th class="px-6 py-4">Truck / Driver</th>
                                            <th class="px-6 py-4">Status</th>
                                            <th class="px-6 py-4">Tolerance</th>
                                            <th class="px-6 py-4">Sync</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-600">
                                        <tr 
                                            v-for="item in filteredLedger" 
                                            :key="item.id" 
                                            class="hover:bg-slate-50/60 transition-colors"
                                        >
                                            <td class="px-6 py-4 font-mono text-[10px] text-[#3f83f8] font-bold">#{{ item.batch_no }}</td>
                                            <td class="px-6 py-4 font-semibold text-slate-900 truncate max-w-[150px]" :title="item.customer">{{ item.customer }}</td>
                                            <td class="px-6 py-4 font-medium text-slate-500 truncate max-w-[120px]" :title="item.site">{{ item.site }}</td>
                                            <td class="px-6 py-4 font-medium text-slate-800">
                                                <div class="flex flex-col">
                                                    <span>{{ item.mix_design }}</span>
                                                    <span class="text-[9px] text-slate-400 font-mono">{{ item.mix_code }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatVolume(item.batch_size) }}</td>
                                            <td class="px-6 py-4 text-slate-500 font-medium">
                                                <div class="flex flex-col">
                                                    <span>{{ item.truck }}</span>
                                                    <span class="text-[9px] text-slate-400">{{ item.driver }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span 
                                                    v-if="item.status === 'Completed'" 
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-[#edfbf4] border border-[#cff5e1] px-2.5 py-0.5 text-[10px] font-bold text-[#0e9f6e]"
                                                >
                                                    <span class="size-1.5 rounded-full bg-[#0e9f6e]"></span>
                                                    Completed
                                                </span>
                                                <span 
                                                    v-else-if="item.status === 'Loading'" 
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-[#edf8fc] border border-[#d0eefa] px-2.5 py-0.5 text-[10px] font-bold text-[#3f83f8] animate-pulse"
                                                >
                                                    <span class="size-1.5 rounded-full bg-[#3f83f8] animate-ping"></span>
                                                    Loading
                                                </span>
                                                <span 
                                                    v-else-if="item.status === 'Dispatched'" 
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-[#f5f3ff] border border-[#e0e7ff] px-2.5 py-0.5 text-[10px] font-bold text-[#6366f1]"
                                                >
                                                    <span class="size-1.5 rounded-full bg-[#6366f1]"></span>
                                                    Dispatched
                                                </span>
                                                <span 
                                                    v-else-if="item.status === 'Cancelled'" 
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-[#fdf2f2] border border-[#fcdcd6] px-2.5 py-0.5 text-[10px] font-bold text-[#f05252]"
                                                >
                                                    <span class="size-1.5 rounded-full bg-[#f05252]"></span>
                                                    Cancelled
                                                </span>
                                                <span 
                                                    v-else 
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-slate-50 border border-slate-200 px-2.5 py-0.5 text-[10px] font-bold text-slate-600"
                                                >
                                                    Planned
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span 
                                                    v-if="item.has_deviation" 
                                                    class="inline-flex items-center gap-1 rounded bg-amber-50 border border-amber-200/80 px-2 py-0.5 text-[10px] font-bold text-amber-700"
                                                >
                                                    <span class="relative flex h-1.5 w-1.5">
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-amber-500"></span>
                                                    </span>
                                                    Deviation > 5%
                                                </span>
                                                <span v-else class="text-slate-400 text-[10px] font-medium">Within Limit</span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded" :class="item.sync_status === 'success' ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 bg-slate-100'">
                                                    {{ item.sync_status }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredLedger.length === 0">
                                            <td colspan="9" class="px-6 py-16 text-center text-slate-400 font-bold uppercase tracking-wider">
                                                No active batch logs match filters.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- MIX DESIGN INGREDIENTS RECIPES CATALOG -->
                        <div id="recipes" class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Mix Recipes & Proportions Catalog</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 font-medium">Standard recipe ingredient quantities per cubic meter configured for the plant.</p>
                                </div>
                                <BeakerIcon class="size-6 text-slate-400" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                <div 
                                    v-for="recipe in dashboardData.recipes" 
                                    :key="recipe.id" 
                                    class="bg-slate-50/50 border border-slate-200/60 rounded-xl p-5 hover:border-[#f05252]/30 transition shadow-sm"
                                >
                                    <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-3">
                                        <h4 class="font-bold text-slate-800 text-sm truncate max-w-[160px]">{{ recipe.design_name }}</h4>
                                        <span class="font-mono text-[9px] bg-slate-100 border border-slate-200 px-2.5 py-0.5 rounded text-[#3f83f8] uppercase">{{ recipe.design_code }}</span>
                                    </div>
                                    <div class="space-y-2 text-slate-600 font-medium">
                                        <div v-for="mat in recipe.materials" :key="mat.name" class="flex justify-between text-[11px]">
                                            <span class="text-slate-500 truncate max-w-[120px]">{{ mat.name }}</span>
                                            <span class="font-mono font-bold text-slate-800">{{ formatNumber(mat.qty) }} {{ mat.uom }}</span>
                                        </div>
                                        <div v-if="recipe.materials.length === 0" class="py-4 text-center text-[10px] text-slate-400 uppercase tracking-widest">
                                            No ingredients listed.
                                        </div>
                                    </div>
                                </div>
                                <div v-if="dashboardData.recipes.length === 0" class="col-span-full py-16 text-center text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                    No mix recipe configurations found.
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles */
</style>
