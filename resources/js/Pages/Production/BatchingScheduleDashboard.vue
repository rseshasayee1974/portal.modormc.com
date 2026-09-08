<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BatchingScheduleForm from './components/BatchingScheduleForm.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import {
    TruckIcon,
    ArrowPathIcon,
    PlusIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    MapPinIcon,
    BeakerIcon,
    DocumentTextIcon,
    FunnelIcon,
    CalendarIcon,
    WrenchScrewdriverIcon,
    PlayIcon,
    PaperAirplaneIcon,
    StopIcon,
    PencilSquareIcon,
    TrashIcon,
    ChevronRightIcon,
    ListBulletIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    plants: Array,
    activePlantId: Number,
    initialDate: String,
    initialFilters: Object,
});

// View management: 'list' | 'create' | 'edit'
const activeView = ref('list');
const selectedSchedule = ref(null);

const scheduleDate = ref(props.initialDate || new Date().toISOString().substring(0, 10));
const statusFilter = ref(props.initialFilters?.status || 'all');
const pourSearch = ref('');
const siteFilter = ref('all');

const loading = ref(false);
const schedules = ref([]);
const pours = ref([]);
const metrics = ref({
    total_scheduled_m3: 0,
    total_delivered_m3: 0,
    active_in_transit_tms: 0,
    active_pouring_tms: 0,
    hydration_warning_count: 0,
});

// Dropdowns data
const dropdowns = ref({
    sites: [],
    mixDesigns: [],
    vehicles: [],
    drivers: [],
    salesOrders: [],
    pumpTypes: []
});

const siteFilterOptions = computed(() => [
    { label: 'All Destination Sites', value: 'all' },
    ...(dropdowns.value.sites || []).map(s => ({ label: s.name, value: s.id }))
]);

const statusFilterOptions = [
    { label: 'All Trip Statuses', value: 'all' },
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'Batching', value: 'batching' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'On Site', value: 'on_site' },
    { label: 'Pouring', value: 'pouring' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

// Auto-refresh interval
let pollTimer = null;

const fetchDropdowns = async () => {
    try {
        const res = await axios.get(route('production.batching-schedules.dropdowns'));
        dropdowns.value = res.data;
    } catch (err) {
        console.error('Failed to load dropdowns:', err);
    }
};

const fetchData = async () => {
    loading.value = true;
    try {
        const res = await axios.get(route('production.batching-schedules.data'), {
            params: {
                schedule_date: scheduleDate.value,
                status: statusFilter.value,
                pour_reference: pourSearch.value,
                site_id: siteFilter.value === 'all' ? null : siteFilter.value,
            }
        });
        schedules.value = res.data.schedules || [];
        pours.value = res.data.pours || [];
        metrics.value = res.data.metrics || metrics.value;
    } catch (err) {
        console.error('Error fetching schedules:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchDropdowns();
    fetchData();
    pollTimer = setInterval(fetchData, 30000); // 30s live telemetry poll
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

// Real-time client-side filter computation
const filteredSchedules = computed(() => {
    return schedules.value.filter(s => {
        if (statusFilter.value !== 'all' && s.status !== statusFilter.value) return false;
        if (siteFilter.value !== 'all' && s.site_id != siteFilter.value) return false;
        if (pourSearch.value && !s.pour_reference?.toLowerCase().includes(pourSearch.value.toLowerCase())) return false;
        return true;
    });
});

// Open dedicated Form Component (No Modals)
const openCreateForm = () => {
    selectedSchedule.value = null;
    activeView.value = 'create';
};

const openEditForm = (item) => {
    selectedSchedule.value = item;
    activeView.value = 'edit';
};

const handleFormSaved = () => {
    activeView.value = 'list';
    selectedSchedule.value = null;
    fetchData();
};

const handleFormCancel = () => {
    activeView.value = 'list';
    selectedSchedule.value = null;
};

const transitionStatus = async (item, nextStatus) => {
    try {
        await axios.patch(route('production.batching-schedules.update-status', item.id), {
            status: nextStatus
        });
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Trip #${item.id} moved to ${nextStatus.replace('_', ' ').toUpperCase()}`,
            showConfirmButton: false,
            timer: 2000
        });
        fetchData();
    } catch (err) {
        console.error('Failed to update status:', err);
        Swal.fire('Error', 'Failed to transition status.', 'error');
    }
};

const createDispatch = async (item) => {
    const result = await Swal.fire({
        title: 'Generate Dispatch Ticket?',
        text: `Generate official Dispatch delivery ticket for TM ${item.vehicle?.registration || 'Trip #' + item.id}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Yes, Generate Dispatch'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(route('production.batching-schedules.create-dispatch', item.id));
        Swal.fire('Dispatch Created', `Dispatch ticket #${res.data.dispatch_no} generated successfully.`, 'success');
        fetchData();
    } catch (err) {
        console.error('Error creating dispatch:', err);
        Swal.fire('Error', err.response?.data?.message || 'Failed to create dispatch.', 'error');
    }
};

const deleteSchedule = async (item) => {
    const result = await Swal.fire({
        title: 'Delete Schedule Slot?',
        text: `Delete trip #${item.id} for pour "${item.pour_reference}"? Remaining balances will be recalculated.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    });

    if (!result.isConfirmed) return;

    try {
        await axios.delete(route('production.batching-schedules.destroy', item.id));
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Schedule deleted', timer: 2000, showConfirmButton: false });
        fetchData();
    } catch (err) {
        Swal.fire('Error', 'Failed to delete schedule slot.', 'error');
    }
};

// Formatting helpers
const formatTime = (ts) => {
    if (!ts) return '-';
    try {
        const d = new Date(ts);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
    } catch (e) {
        return ts;
    }
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'scheduled':
            return { label: 'Scheduled', bg: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700' };
        case 'batching':
            return { label: 'Batching', bg: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800 animate-pulse' };
        case 'in_transit':
            return { label: 'In Transit', bg: 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-300 dark:border-sky-800' };
        case 'on_site':
            return { label: 'On Site', bg: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800' };
        case 'pouring':
            return { label: 'Pouring / Pumping', bg: 'bg-teal-50 dark:bg-teal-950/40 text-teal-800 dark:text-teal-300 border-teal-300 dark:border-teal-800' };
        case 'completed':
            return { label: 'Completed', bg: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800' };
        case 'cancelled':
            return { label: 'Cancelled', bg: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800' };
        default:
            return { label: status, bg: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700' };
    }
};

const getPumpTypeBadge = (type) => {
    switch (type) {
        case 'boom_pump':
            return { label: 'Boom Pump', bg: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' };
        case 'line_pump':
            return { label: 'Line Pump', bg: 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800' };
        case 'stationary_pump':
            return { label: 'Stationary Pump', bg: 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800' };
        case 'crane_bucket':
            return { label: 'Crane & Bucket', bg: 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800' };
        case 'direct_pour':
            return { label: 'Direct Pour', bg: 'bg-teal-50 dark:bg-teal-950/40 text-teal-800 dark:text-teal-300 border-teal-200 dark:border-teal-800' };
        default:
            return { label: type ? type.replace(/_/g, ' ') : 'Boom Pump', bg: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700' };
    }
};
</script>

<template>
    <AppLayout title="Batching & Dispatch Schedules">
        <div class="py-2 px-4">
            <ModuleSubTopNav />

            <div class="max-w-7xl mx-auto mt-4 space-y-4">
                
                <!-- Main Header Card in Indigo Theme -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md">
                            <TruckIcon class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Logistics & Production Floorplan
                                </span>
                                <span class="text-[10px] bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded font-mono font-semibold border border-indigo-100 dark:border-indigo-900">
                                    Plant Active
                                </span>
                            </div>
                            <h1 class="text-base font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                                Concrete Batching & Dispatch Schedules
                            </h1>
                        </div>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <button 
                            @click="fetchData" 
                            :disabled="loading"
                            class="p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center transition-colors shadow-sm"
                            title="Refresh Live Data"
                        >
                            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                        </button>

                        <button 
                            v-if="activeView === 'list'"
                            @click="openCreateForm"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow transition-colors"
                        >
                            <PlusIcon class="w-4 h-4 stroke-[2.5]" />
                            <span>Schedule Trip / Pour</span>
                        </button>

                        <button 
                            v-else
                            @click="activeView = 'list'"
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow transition-colors"
                        >
                            <ListBulletIcon class="w-4 h-4" />
                            <span>View All Schedules</span>
                        </button>
                    </div>
                </div>

                <!-- VIEW 1: DEDICATED FORM COMPONENT (NO MODAL) -->
                <div v-if="activeView !== 'list'">
                    <BatchingScheduleForm
                        :isEditing="activeView === 'edit'"
                        :initialData="selectedSchedule"
                        :dropdowns="dropdowns"
                        :defaultScheduleDate="scheduleDate"
                        @saved="handleFormSaved"
                        @cancel="handleFormCancel"
                    />
                </div>

                <!-- VIEW 2: LIST DASHBOARD -->
                <div v-else class="space-y-4">
                    
                    <!-- 1. KPI Metric Summary Bar -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500 tracking-wider block">Total Scheduled</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-gray-900 dark:text-gray-100">{{ metrics.total_scheduled_m3 }}</span>
                                <span class="text-xs font-bold text-gray-400">m³</span>
                            </div>
                            <div class="mt-1.5 text-[10px] text-gray-500 flex items-center gap-1">
                                <CalendarIcon class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ scheduleDate }}</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400 tracking-wider block">Delivered</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ metrics.total_delivered_m3 }}</span>
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">m³</span>
                            </div>
                            <div class="mt-1.5 text-[10px] text-emerald-600 font-medium">
                                {{ metrics.total_scheduled_m3 > 0 ? Math.round((metrics.total_delivered_m3 / metrics.total_scheduled_m3) * 100) : 0 }}% completed
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-sky-600 dark:text-sky-400 tracking-wider block">TMs in Transit</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-sky-600 dark:text-sky-400">{{ metrics.active_in_transit_tms }}</span>
                                <span class="text-xs font-bold text-sky-600">Trucks</span>
                            </div>
                            <div class="mt-1.5 text-[10px] text-gray-500 flex items-center gap-1">
                                <TruckIcon class="w-3.5 h-3.5 text-sky-500" />
                                <span>En-route to sites</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-teal-600 dark:text-teal-400 tracking-wider block">Actively Pouring</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-teal-600 dark:text-teal-400">{{ metrics.active_pouring_tms }}</span>
                                <span class="text-xs font-bold text-teal-600">Trucks</span>
                            </div>
                            <div class="mt-1.5 text-[10px] text-gray-500 flex items-center gap-1">
                                <WrenchScrewdriverIcon class="w-3.5 h-3.5 text-teal-500" />
                                <span>Pumping into hoppers</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm col-span-2 lg:col-span-1">
                            <span class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400 tracking-wider block">Hydration Alert</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black" :class="metrics.hydration_warning_count > 0 ? 'text-amber-600' : 'text-gray-800 dark:text-gray-200'">
                                    {{ metrics.hydration_warning_count }}
                                </span>
                                <span class="text-xs font-bold text-gray-400">>90 Mins</span>
                            </div>
                            <div class="mt-1.5 text-[10px] text-gray-500 flex items-center gap-1">
                                <ClockIcon class="w-3.5 h-3.5 text-amber-500" />
                                <span>Slump limit check</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Active Pour Tracking Section -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                                Live Pour Progress & Pump Deployments
                            </h3>
                            <span class="text-[11px] text-gray-400 font-semibold">{{ pours.length }} Active Pours</span>
                        </div>

                        <div v-if="pours.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center shadow-sm">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">No concrete pour schedules recorded for this date.</p>
                            <button @click="openCreateForm" class="mt-3 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-bold transition-colors">
                                + Create First Pour Schedule
                            </button>
                        </div>

                        <!-- <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div 
                                v-for="pour in pours" 
                                :key="pour.pour_reference"
                                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm hover:border-indigo-600/50 transition-all flex flex-col justify-between"
                            >
                                <div>
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span class="text-[9px] uppercase font-bold text-gray-400 dark:text-gray-500 tracking-wider">Pour Reference</span>
                                            <h4 class="text-sm font-black text-gray-800 dark:text-gray-200 mt-0.5">{{ pour.pour_reference }}</h4>
                                        </div>
                                        <span 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border flex items-center gap-1"
                                            :class="getPumpTypeBadge(pour.pump_type).bg"
                                        >
                                            <WrenchScrewdriverIcon class="w-3 h-3" />
                                            {{ getPumpTypeBadge(pour.pump_type).label }}
                                        </span>
                                    </div>

                                    <div class="mt-3 space-y-1.5 text-xs">
                                        <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                                            <MapPinIcon class="w-4 h-4 text-gray-400 shrink-0" />
                                            <span class="font-bold text-gray-800 dark:text-gray-200 truncate">{{ pour.site_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                                            <BeakerIcon class="w-4 h-4 text-indigo-600 shrink-0" />
                                            <span>{{ pour.mix_design_name }} ({{ pour.mix_design_code }})</span>
                                        </div>
                                    </div>

                                    <div class="mt-4 bg-gray-50 dark:bg-gray-900/40 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700">
                                        <div class="flex justify-between text-[11px] font-bold mb-1">
                                            <span class="text-gray-500">Progress</span>
                                            <span class="text-indigo-600 dark:text-indigo-400">{{ pour.delivered_qty }} / {{ pour.total_planned_qty }} m³</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                            <div 
                                                class="bg-indigo-600 h-2 rounded-full transition-all duration-500" 
                                                :style="{ width: `${pour.progress_percentage}%` }"
                                            ></div>
                                        </div>
                                        <div class="flex justify-between text-[9px] text-gray-400 mt-1 font-semibold">
                                            <span>{{ pour.progress_percentage }}% complete</span>
                                            <span>{{ pour.total_planned_qty - pour.delivered_qty }} m³ remaining</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-[11px]">
                                    <span class="text-gray-400">Total Trips: <strong class="text-gray-700 dark:text-gray-300">{{ pour.total_trips }}</strong></span>
                                    <button 
                                        @click="pourSearch = pour.pour_reference; fetchData()"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold flex items-center gap-0.5"
                                    >
                                        <span>Filter Trips</span>
                                        <ChevronRightIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div> -->
                    </div>

                    <!-- 3. Granular Batching Trips Schedule Table -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden text-xs">
                        
                        <!-- Filter Bar -->
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 flex flex-col md:flex-row md:items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <FunnelIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                                    Trip Dispatch Filters
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5">
                                <!-- Date Filter -->
                                <BaseInput 
                                    type="date" 
                                    v-model="scheduleDate" 
                                    label="Schedule Date"
                                    @update:modelValue="fetchData"
                                />

                                <!-- Site Filter -->
                                <BaseSelect 
                                    v-model="siteFilter" 
                                    :options="siteFilterOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    label="Destination Site"
                                    @change="fetchData"
                                />

                                <!-- Status Filter -->
                                <BaseSelect 
                                    v-model="statusFilter" 
                                    :options="statusFilterOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    label="Status"
                                    @change="fetchData"
                                />

                                <!-- Text search -->
                                <BaseInput 
                                    type="text" 
                                    v-model="pourSearch" 
                                    label="Search"
                                    placeholder="Search Pour Ref..." 
                                    @update:modelValue="fetchData"
                                />
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-100/70 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-3 px-3 text-center w-12">#</th>
                                        <th class="py-3 px-3">Trip / Pour Reference</th>
                                        <th class="py-3 px-3">Destination Site</th>
                                        <th class="py-3 px-3">Recipe & Grade</th>
                                        <th class="py-3 px-3 text-right">Volume</th>
                                        <th class="py-3 px-3">Transit Mixer & Driver</th>
                                        <th class="py-3 px-3">Batch & ETA</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                        <th class="py-3 px-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 font-medium">
                                    <tr v-if="filteredSchedules.length === 0">
                                        <td colspan="9" class="py-12 text-center text-gray-400 italic">
                                            No batching schedule slots found. Click "Schedule Trip / Pour" to add one.
                                        </td>
                                    </tr>

                                    <tr 
                                        v-for="item in filteredSchedules" 
                                        :key="item.id"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors"
                                    >
                                        <td class="py-3 px-3 text-center font-bold text-gray-400">
                                            #{{ item.id }}
                                        </td>

                                        <td class="py-3 px-3">
                                            <div class="font-bold text-gray-900 dark:text-gray-100">
                                                {{ item.pour_reference }}
                                            </div>
                                            <div v-if="item.dispatch?.dispatch_no || item.dispatch_no" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">
                                                Dispatch: #{{ item.dispatch?.dispatch_no || item.dispatch_no }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-3">
                                            <div class="font-bold text-gray-800 dark:text-gray-200">
                                                {{ item.site?.name || 'Unassigned Site' }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-3">
                                            <span class="font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-1.5 py-0.5 rounded text-[10px] border border-indigo-100 dark:border-indigo-900">
                                                {{ item.mix_design?.name || 'Standard Mix' }}
                                            </span>
                                        </td>

                                        <td class="py-3 px-3 text-right">
                                            <strong class="text-sm font-black text-gray-900 dark:text-gray-100">{{ item.qty_m3 }}</strong>
                                            <span class="text-[10px] text-gray-400 font-normal"> m³</span>
                                        </td>

                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-1.5">
                                                <TruckIcon class="w-3.5 h-3.5 text-gray-400" />
                                                <strong class="text-gray-800 dark:text-gray-200">{{ item.vehicle?.registration || 'Fleet TBD' }}</strong>
                                            </div>
                                            <div v-if="item.driver" class="text-[10px] text-gray-400 pl-5">
                                                {{ item.driver.first_name }} {{ item.driver.last_name || '' }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-3 text-[11px]">
                                            <div class="flex items-center gap-1 text-gray-700 dark:text-gray-300">
                                                <ClockIcon class="w-3.5 h-3.5 text-gray-400" />
                                                <span>Batch: {{ formatTime(item.batching_time) }}</span>
                                            </div>
                                            <div class="text-[10px] text-gray-400 pl-4.5">
                                                ETA: {{ formatTime(item.eta_site) }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-3 text-center">
                                            <span 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold border inline-block"
                                                :class="getStatusBadge(item.status).bg"
                                            >
                                                {{ getStatusBadge(item.status).label }}
                                            </span>
                                        </td>

                                        <td class="py-3 px-3 text-right">
                                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                                
                                                <!-- Action: Start Batching -->
                                                <button 
                                                    v-if="item.status === 'scheduled'" 
                                                    @click="transitionStatus(item, 'batching')"
                                                    class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Start Batching Plant Loading"
                                                >
                                                    Batch
                                                </button>

                                                <!-- Action: In Transit -->
                                                <button 
                                                    v-else-if="item.status === 'batching'" 
                                                    @click="transitionStatus(item, 'in_transit')"
                                                    class="px-2 py-1 bg-sky-500 hover:bg-sky-600 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Dispatched Out Plant Gate"
                                                >
                                                    Transit
                                                </button>

                                                <!-- Action: On Site -->
                                                <button 
                                                    v-else-if="item.status === 'in_transit'" 
                                                    @click="transitionStatus(item, 'on_site')"
                                                    class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Arrived at Site Gate"
                                                >
                                                    On Site
                                                </button>

                                                <!-- Action: Start Pouring -->
                                                <button 
                                                    v-else-if="item.status === 'on_site'" 
                                                    @click="transitionStatus(item, 'pouring')"
                                                    class="px-2 py-1 bg-teal-500 hover:bg-teal-600 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Start Discharge / Pumping"
                                                >
                                                    Pour
                                                </button>

                                                <!-- Action: Complete -->
                                                <button 
                                                    v-else-if="item.status === 'pouring'" 
                                                    @click="transitionStatus(item, 'completed')"
                                                    class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Complete Pour & Return"
                                                >
                                                    Complete
                                                </button>

                                                <!-- Create Official Dispatch Ticket -->
                                                <button 
                                                    v-if="!item.dispatch_id && ['in_transit', 'on_site', 'pouring', 'completed'].includes(item.status)"
                                                    @click="createDispatch(item)"
                                                    class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold rounded text-[10px] transition-colors border border-indigo-200 dark:border-indigo-800 flex items-center gap-1"
                                                    title="Generate Delivery Dispatch Ticket"
                                                >
                                                    <PaperAirplaneIcon class="w-3 h-3" />
                                                    <span>Ticket</span>
                                                </button>

                                                <!-- Edit (Switch to Form View without Modal) -->
                                                <button 
                                                    @click="openEditForm(item)"
                                                    class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 rounded transition-colors"
                                                    title="Edit Schedule Slot"
                                                >
                                                    <PencilSquareIcon class="w-4 h-4" />
                                                </button>

                                                <!-- Delete -->
                                                <button 
                                                    @click="deleteSchedule(item)"
                                                    class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-rose-600 dark:text-rose-400 rounded transition-colors"
                                                    title="Delete Schedule Slot"
                                                >
                                                    <TrashIcon class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AppLayout>
</template>
