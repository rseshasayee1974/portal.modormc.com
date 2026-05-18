<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, watch, computed } from 'vue';
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
    ArrowPathIcon,
    CpuChipIcon,
    SparklesIcon,
    ChartBarIcon,
    CubeTransparentIcon,
    BoltIcon,
    ArrowUpRightIcon,
    TruckIcon,
    CheckBadgeIcon,
    CurrencyDollarIcon,
    PresentationChartLineIcon,
    MapPinIcon,
    BeakerIcon,
    CalendarDaysIcon,
    VariableIcon,
    ShieldCheckIcon,
    WrenchScrewdriverIcon,
    ShieldExclamationIcon,
    ChatBubbleLeftRightIcon,
    Square3Stack3DIcon,
    UserGroupIcon,
    CpuChipIcon as CpuIcon,
    HandThumbUpIcon,
    TagIcon,
    UserCircleIcon
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
    outstanding: 0,
    gross_margin: 28.4,
    delivery_success: 94.2,
    fleet_active: 8,
    fleet_total: 12,
    avg_trip_time: '42m',
    demand_accuracy: 91.5,
    cash_flow_health: 'Stable',
    automation_level: '65%'
});

const activeTab = ref('overview');
const loading = ref(false);
const lastUpdated = ref(new Date().toLocaleTimeString());
let pollingInterval = null;

const filterForm = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    patron_id: props.filters.patron_id
});

// ─── AI Automation Recommendations ──────────────────────────────────────────
const automationStack = ref({
    dispatch: [
        { id: 1, order: 'WO-474', truck: 'MH-8292', reason: 'Closest to site (4km), Full fuel', confidence: 98 },
        { id: 2, order: 'WO-475', truck: 'MH-1021', reason: 'Recently cleaned, Driver shift just started', confidence: 92 }
    ],
    quality: [
        { id: 1, batch: 'B-1002', issue: 'Moisture Imbalance (+2.1%)', action: 'Auto-adjust water -4.5L', status: 'Corrected' },
        { id: 2, batch: 'B-1005', issue: 'Slump Deviation', action: 'Admixture recommendation: +0.5L', status: 'Pending' }
    ],
    procurement: [
        { id: 1, material: 'Cement', vendor: 'UltraTech', benefit: 'Best price (₹340/bag), delivery in 4h', rank: 1 },
        { id: 2, material: 'Fly Ash', vendor: 'NTPC Direct', benefit: 'Highest quality silica, 12% cheaper', rank: 1 }
    ],
    hr: [
        { id: 1, driver: 'Ramesh K.', metric: 'Safety Score', value: 98, status: 'Excellent' },
        { id: 2, driver: 'Suresh M.', metric: 'Idle Time', value: '42m/trip', status: 'Needs Review' }
    ]
});

const fetchDashboardData = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('dashboard.data'), { params: filterForm.value });
        metrics.value = { ...metrics.value, ...response.data.metrics };
        lastUpdated.value = new Date().toLocaleTimeString();
    } catch (error) { console.error("Dashboard error", error); }
    finally { loading.value = false; }
};

onMounted(() => {
    fetchDashboardData();
    pollingInterval = setInterval(fetchDashboardData, 30000);
});

import { onUnmounted } from 'vue';
onUnmounted(() => { if (pollingInterval) clearInterval(pollingInterval); });

</script>

<template>
    <AppLayout title="AI Automation Center">
        <div class="min-h-screen bg-[#fcfdfe] pb-20">
            
            <!-- Dynamic AI Aura -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-indigo-50/20 blur-[150px] rounded-full"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-50/10 blur-[150px] rounded-full"></div>
            </div>

            <div class="relative max-w-[1700px] mx-auto pt-8">
                
                <!-- ── AI Header & Tab System ──────────────────────────────── -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-10 px-6 mb-12">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] shadow-xl shadow-indigo-200">
                                <SparklesIcon class="w-3.5 h-3.5 text-indigo-400" /> AI Automation Center 6.0
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase border border-emerald-100">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></div> Live Sync: {{ lastUpdated }}
                            </span>
                        </div>
                        <h1 class="text-5xl font-black text-slate-900 leading-none mb-4 italic tracking-tighter">Semi-Autonomous Operations</h1>
                        <p class="text-slate-400 font-medium text-xl max-w-2xl leading-relaxed italic">AI is currently managing <span class="text-indigo-600 font-black">{{ metrics.automation_level }}</span> of your plant's repetitive decision-making.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 p-2 bg-white/40 backdrop-blur-3xl border border-white/60 rounded-[0.5rem] shadow-2xl shadow-indigo-100/30">
                        <button v-for="tab in ['overview', 'automation', 'intelligence', 'analytics']" :key="tab"
                            @click="activeTab = tab"
                            :class="activeTab === tab ? 'bg-slate-900 text-white shadow-xl scale-105' : 'text-slate-500 hover:bg-slate-50'"
                            class="px-8 py-3 rounded-[0.5rem] text-[10px] font-black uppercase tracking-[0.1em] transition-all duration-300">
                            {{ tab }}
                        </button>
                    </div>
                </div>

                <!-- ── AI Automation Pillars ───────────────────────────────── -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 px-6 mb-12">
                    
                    <!-- 1. Dispatch: Auto Truck Assignment -->
                    <div class="bg-white p-8 rounded-[0.5rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
                        <div class="flex items-center justify-between mb-8">
                            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-xl"><TruckIcon class="w-6 h-6" /></div>
                            <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest italic">Smart Dispatch</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 mb-6">Auto Truck Assignment</h3>
                        <div class="space-y-4 mb-8">
                            <div v-for="assign in automationStack.dispatch" :key="assign.id" class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] font-black text-slate-800">{{ assign.order }}</span>
                                    <span class="text-[10px] font-black text-emerald-600">{{ assign.confidence }}% Match</span>
                                </div>
                                <div class="text-[10px] text-slate-500 font-medium italic">Recommended: {{ assign.truck }}</div>
                                <div class="text-[9px] text-slate-400 mt-1 uppercase tracking-tighter">{{ assign.reason }}</div>
                            </div>
                        </div>
                        <BaseButton variant="filled" class="w-full !bg-slate-900 !rounded-lg !py-4 !text-[10px] !font-black !uppercase">Execute All Assignments</BaseButton>
                    </div>

                    <!-- 2. QA/QC: Anomaly Detection -->
                    <div class="bg-white p-8 rounded-[0.5rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
                        <div class="flex items-center justify-between mb-8">
                            <div class="p-3 bg-rose-50 text-rose-600 rounded-xl"><BeakerIcon class="w-6 h-6" /></div>
                            <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest italic">Quality Guard</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 mb-6">Quality Anomaly Detection</h3>
                        <div class="space-y-4 mb-8">
                            <div v-for="q in automationStack.quality" :key="q.id" class="p-4 rounded-xl border border-rose-100" :class="q.status === 'Corrected' ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50'">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] font-black text-slate-800">{{ q.batch }}</span>
                                    <span class="text-[9px] font-black px-2 py-0.5 rounded" :class="q.status === 'Corrected' ? 'bg-emerald-200 text-emerald-800' : 'bg-rose-200 text-rose-800'">{{ q.status }}</span>
                                </div>
                                <div class="text-[10px] text-slate-600 font-medium">{{ q.issue }}</div>
                                <div class="text-[9px] text-slate-400 mt-1 uppercase italic">AI Action: {{ q.action }}</div>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase text-center">Batch consistency monitored in real-time</p>
                    </div>

                    <!-- 3. Procurement: Vendor Recommendation -->
                    <div class="bg-white p-8 rounded-[0.5rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
                        <div class="flex items-center justify-between mb-8">
                            <div class="p-3 bg-amber-50 text-amber-600 rounded-xl"><TagIcon class="w-6 h-6" /></div>
                            <span class="text-[10px] font-black text-amber-400 uppercase tracking-widest italic">Smart Sourcing</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 mb-6">Vendor Recommendations</h3>
                        <div class="space-y-4 mb-8">
                            <div v-for="p in automationStack.procurement" :key="p.id" class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] font-black text-slate-800">{{ p.material }}</span>
                                    <div class="flex items-center gap-1">
                                        <HandThumbUpIcon class="w-3 h-3 text-amber-500" />
                                        <span class="text-[10px] font-black text-amber-600">Rank #{{ p.rank }}</span>
                                    </div>
                                </div>
                                <div class="text-[10px] font-black text-indigo-600 italic">Recommended: {{ p.vendor }}</div>
                                <div class="text-[9px] text-slate-400 mt-1 uppercase tracking-tighter">{{ p.benefit }}</div>
                            </div>
                        </div>
                        <BaseButton variant="outlined" class="w-full !rounded-lg !py-4 !text-[10px] !font-black !uppercase !text-slate-400">Generate Purchase Orders</BaseButton>
                    </div>

                    <!-- 4. HR: Driver behavior analytics -->
                    <div class="bg-white p-8 rounded-[0.5rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
                        <div class="flex items-center justify-between mb-8">
                            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl"><UserCircleIcon class="w-6 h-6" /></div>
                            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest italic">Fleet HR</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 mb-6">Driver Behavior Analytics</h3>
                        <div class="space-y-4 mb-8">
                            <div v-for="h in automationStack.hr" :key="h.id" class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] font-black text-slate-800">{{ h.driver }}</span>
                                    <span class="text-[10px] font-black" :class="h.status === 'Excellent' ? 'text-emerald-600' : 'text-rose-600'">{{ h.status }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-slate-500 font-medium">{{ h.metric }}</span>
                                    <span class="text-[11px] font-black text-slate-900">{{ h.value }}{{ h.metric.includes('Score') ? '%' : '' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-emerald-600 rounded-xl text-white text-center">
                            <div class="text-[9px] font-black uppercase mb-1">Fleet Performance</div>
                            <div class="text-2xl font-black">94.8%</div>
                        </div>
                    </div>

                </div>

                <!-- ── AI Autonomous Pulse (Bottom Action Strip) ───────────── -->
                <div class="px-6 mb-12">
                    <div class="bg-slate-900 p-10 rounded-[0.5rem] shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-10">
                        <div class="absolute inset-0 bg-gradient-to-r from-indigo-900/40 to-emerald-900/20"></div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-4">
                                <CpuIcon class="w-8 h-8 text-indigo-400" />
                                <h2 class="text-3xl font-black text-white italic tracking-tighter uppercase">AI Master Controller</h2>
                            </div>
                            <p class="text-indigo-100 font-medium text-lg max-w-xl italic">Switch to full semi-autonomous mode to let AI handle all routine dispatches and stock reorders.</p>
                        </div>
                        <div class="relative z-10 flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                            <BaseButton variant="filled" class="!bg-emerald-500 !border-none !rounded-lg !py-6 !px-12 !text-[12px] !font-black !uppercase !tracking-widest shadow-xl shadow-emerald-900/50">
                                Enable Autonomous Ops
                            </BaseButton>
                            <BaseButton variant="outlined" class="!border-white/20 !text-white !rounded-lg !py-6 !px-12 !text-[12px] !font-black !uppercase !tracking-widest hover:!bg-white/10">
                                System Audit Trail
                            </BaseButton>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>
