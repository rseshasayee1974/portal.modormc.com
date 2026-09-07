<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
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
    ChevronRightIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    plants: Array,
    activePlantId: Number,
    initialDate: String,
    initialFilters: Object,
});

const scheduleDate = ref(props.initialDate || new Date().toISOString().substring(0, 10));
const statusFilter = ref(props.initialFilters?.status || 'all');
const pourSearch = ref('');
const siteFilter = ref('');

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

// Modal state
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const saving = ref(false);

const form = ref({
    schedule_date: scheduleDate.value,
    pour_reference: '',
    site_id: '',
    mix_design_id: '',
    qty_m3: 6.0,
    order_volume_m3: 30.0,
    vehicle_id: '',
    driver_id: '',
    pump_type: 'boom_pump',
    pump_vehicle_id: '',
    sales_order_id: '',
    batching_time: '',
    eta_site: '',
    notes: '',
});

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
                site_id: siteFilter.value,
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
    pollTimer = setInterval(fetchData, 30000); // 30 sec auto-refresh for live plant dispatch
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

// Filtered schedules
const filteredSchedules = computed(() => {
    return schedules.value.filter(s => {
        if (statusFilter.value !== 'all' && s.status !== statusFilter.value) return false;
        if (pourSearch.value && !s.pour_reference?.toLowerCase().includes(pourSearch.value.toLowerCase())) return false;
        if (siteFilter.value && s.site_id != siteFilter.value) return false;
        return true;
    });
});

// Modal operations
const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        schedule_date: scheduleDate.value,
        pour_reference: '',
        site_id: dropdowns.value.sites[0]?.id || '',
        mix_design_id: dropdowns.value.mixDesigns[0]?.id || '',
        qty_m3: 6.0,
        order_volume_m3: 30.0,
        vehicle_id: '',
        driver_id: '',
        pump_type: 'boom_pump',
        pump_vehicle_id: '',
        sales_order_id: '',
        batching_time: '',
        eta_site: '',
        notes: '',
    };
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true;
    editingId.value = item.id;
    form.value = {
        schedule_date: item.schedule_date,
        pour_reference: item.pour_reference,
        site_id: item.site_id,
        mix_design_id: item.mix_design_id,
        qty_m3: item.qty_m3,
        order_volume_m3: item.order_volume_m3,
        vehicle_id: item.vehicle_id || '',
        driver_id: item.driver_id || '',
        pump_type: item.pump_type || 'boom_pump',
        pump_vehicle_id: item.pump_vehicle_id || '',
        sales_order_id: item.sales_order_id || '',
        batching_time: item.batching_time ? item.batching_time.substring(0, 16) : '',
        eta_site: item.eta_site ? item.eta_site.substring(0, 16) : '',
        notes: item.notes || '',
        status: item.status,
    };
    isModalOpen.value = true;
};

const saveSchedule = async () => {
    if (!form.value.pour_reference || !form.value.site_id || !form.value.mix_design_id || !form.value.qty_m3) {
        Swal.fire('Required Fields', 'Please complete Pour Reference, Site, Mix Design, and Trip Volume.', 'warning');
        return;
    }

    saving.value = true;
    try {
        if (isEditing.value) {
            await axios.put(route('production.batching-schedules.update', editingId.value), form.value);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Schedule updated successfully', timer: 2000, showConfirmButton: false });
        } else {
            await axios.post(route('production.batching-schedules.store'), form.value);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Batch schedule created', timer: 2000, showConfirmButton: false });
        }
        isModalOpen.value = false;
        fetchData();
    } catch (err) {
        console.error('Error saving schedule:', err);
        Swal.fire('Error', err.response?.data?.message || 'Failed to save schedule slot.', 'error');
    } finally {
        saving.value = false;
    }
};

const transitionStatus = async (item, nextStatus) => {
    try {
        const res = await axios.patch(route('production.batching-schedules.update-status', item.id), {
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
        confirmButtonColor: '#0284c7',
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
        confirmButtonColor: '#dc2626',
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
            return { label: 'Scheduled', bg: 'bg-slate-100 text-slate-700 border-slate-300' };
        case 'batching':
            return { label: 'Batching', bg: 'bg-amber-50 text-amber-700 border-amber-300 animate-pulse' };
        case 'in_transit':
            return { label: 'In Transit', bg: 'bg-sky-50 text-sky-700 border-sky-300' };
        case 'on_site':
            return { label: 'On Site', bg: 'bg-indigo-50 text-indigo-700 border-indigo-300' };
        case 'pouring':
            return { label: 'Pouring / Pumping', bg: 'bg-teal-50 text-teal-800 border-teal-300' };
        case 'completed':
            return { label: 'Completed', bg: 'bg-emerald-50 text-emerald-800 border-emerald-300' };
        case 'cancelled':
            return { label: 'Cancelled', bg: 'bg-rose-50 text-rose-700 border-rose-300' };
        default:
            return { label: status, bg: 'bg-slate-100 text-slate-700 border-slate-300' };
    }
};
</script>

<template>
    <AppLayout title="Concrete Batching & Dispatch Scheduling">
        <!-- SAP Fiori Quartz Light Shell Frame -->
        <div class="bg-[#f4f6f9] min-h-screen text-[#1d2d3e] font-sans antialiased pb-12">
            
            <!-- Shell Header Bar -->
            <div class="bg-[#1d2d3e] text-white px-6 py-4 shadow flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#2d3e50]">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded bg-[#0064d2] flex items-center justify-center text-white font-bold text-xs shadow-sm">
                        RMC
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] uppercase font-bold text-slate-300 tracking-wider">Logistics & Production Floorplan</span>
                            <span class="text-[10px] bg-[#2a3c50] text-sky-300 px-2 py-0.5 rounded font-mono font-semibold">Plant Scoped</span>
                        </div>
                        <h1 class="text-base font-bold tracking-tight text-white mt-0.5">Concrete Batching & Dispatch Scheduling</h1>
                    </div>
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-2">
                    <button 
                        @click="fetchData" 
                        :disabled="loading"
                        class="p-2 bg-[#2a3c50] hover:bg-[#374c63] text-white rounded text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        title="Refresh Live Data"
                    >
                        <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                        <span class="hidden sm:inline">Sync</span>
                    </button>

                    <button 
                        @click="openCreateModal"
                        class="px-3.5 py-2 bg-[#0064d2] hover:bg-[#0052b3] text-white rounded text-xs font-bold flex items-center gap-1.5 shadow-sm transition-colors"
                    >
                        <PlusIcon class="w-4 h-4 stroke-[2.5]" />
                        <span>Schedule Trip / Pour</span>
                    </button>
                </div>
            </div>

            <!-- Main Container -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6">

                <!-- 1. KPI Metric Summary Bar -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Total Scheduled -->
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider block">Total Scheduled</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-slate-800">{{ metrics.total_scheduled_m3 }}</span>
                            <span class="text-xs font-bold text-slate-400">m³</span>
                        </div>
                        <div class="mt-2 text-[10px] text-slate-500 flex items-center gap-1">
                            <CalendarIcon class="w-3.5 h-3.5 text-slate-400" />
                            <span>{{ scheduleDate }}</span>
                        </div>
                    </div>

                    <!-- Total Delivered -->
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-emerald-600 tracking-wider block">Discharged / Delivered</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-emerald-700">{{ metrics.total_delivered_m3 }}</span>
                            <span class="text-xs font-bold text-emerald-600">m³</span>
                        </div>
                        <div class="mt-2 text-[10px] text-emerald-600 font-medium">
                            {{ metrics.total_scheduled_m3 > 0 ? Math.round((metrics.total_delivered_m3 / metrics.total_scheduled_m3) * 100) : 0 }}% completed
                        </div>
                    </div>

                    <!-- Active In-Transit TMs -->
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-sky-600 tracking-wider block">TMs in Transit</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-sky-700">{{ metrics.active_in_transit_tms }}</span>
                            <span class="text-xs font-bold text-sky-600">Trucks</span>
                        </div>
                        <div class="mt-2 text-[10px] text-slate-500 flex items-center gap-1">
                            <TruckIcon class="w-3.5 h-3.5 text-sky-500" />
                            <span>En-route to sites</span>
                        </div>
                    </div>

                    <!-- Active Pouring -->
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-teal-600 tracking-wider block">Actively Pouring</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-teal-700">{{ metrics.active_pouring_tms }}</span>
                            <span class="text-xs font-bold text-teal-600">Trucks</span>
                        </div>
                        <div class="mt-2 text-[10px] text-slate-500 flex items-center gap-1">
                            <WrenchScrewdriverIcon class="w-3.5 h-3.5 text-teal-500" />
                            <span>Pumping into hoppers</span>
                        </div>
                    </div>

                    <!-- Hydration Alerts -->
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm col-span-2 lg:col-span-1">
                        <span class="text-[10px] font-bold uppercase text-amber-600 tracking-wider block">Hydration Age Alert</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-2xl font-black" :class="metrics.hydration_warning_count > 0 ? 'text-amber-600' : 'text-slate-800'">
                                {{ metrics.hydration_warning_count }}
                            </span>
                            <span class="text-xs font-bold text-slate-400">>90 Mins</span>
                        </div>
                        <div class="mt-2 text-[10px] text-slate-500 flex items-center gap-1">
                            <ClockIcon class="w-3.5 h-3.5 text-amber-500" />
                            <span>Slump & setting limit check</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Active Pour Tracking Section (Workflow Card Grid) -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold text-[#1d2d3e] uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-[#0064d2]"></span>
                            Live Pour Progress & Pump Deployments
                        </h3>
                        <span class="text-[11px] text-slate-400 font-semibold">{{ pours.length }} Active Pours</span>
                    </div>

                    <div v-if="pours.length === 0" class="bg-white rounded-lg border border-slate-200 p-8 text-center shadow-sm">
                        <p class="text-xs text-slate-500 font-medium">No concrete pour schedules recorded for this date.</p>
                        <button @click="openCreateModal" class="mt-3 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-bold transition-colors">
                            + Create First Pour Schedule
                        </button>
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div 
                            v-for="pour in pours" 
                            :key="pour.pour_reference"
                            class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm hover:border-[#0064d2]/50 transition-all flex flex-col justify-between"
                        >
                            <div>
                                <!-- Pour Header -->
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider">Pour Reference</span>
                                        <h4 class="text-sm font-black text-slate-800 mt-0.5">{{ pour.pour_reference }}</h4>
                                    </div>
                                    <!-- Pump Badge -->
                                    <span 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border flex items-center gap-1"
                                        :class="pour.pump_type === 'boom_pump' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                    >
                                        {{ pour.pump_type === 'boom_pump' ? 'Boom Pump' : 'Line Pump' }}
                                    </span>
                                </div>

                                <!-- Site & Mix Design -->
                                <div class="mt-3 space-y-1 text-xs text-slate-600">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <MapPinIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span class="font-medium text-slate-700">{{ pour.site_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <BeakerIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span class="font-semibold text-[#0064d2]">{{ pour.mix_design_name }}</span>
                                    </div>
                                    <div v-if="pour.pump_vehicle && pour.pump_vehicle !== 'None Assigned'" class="text-[10px] text-slate-500 pl-5">
                                        Pump Reg: <strong class="text-slate-700">{{ pour.pump_vehicle }}</strong>
                                    </div>
                                </div>

                                <!-- Volume Progress Bar -->
                                <div class="mt-4 pt-3 border-t border-slate-100">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-700">{{ pour.delivered_volume_m3 }} / {{ pour.order_volume_m3 }} m³</span>
                                        <span class="font-bold text-[#0064d2]">{{ pour.progress_percent }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                        <div 
                                            class="bg-[#0064d2] h-full transition-all duration-500 rounded-full" 
                                            :style="{ width: pour.progress_percent + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Status & Balance -->
                            <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                <span class="text-slate-500">
                                    Remaining: <strong class="text-rose-600 font-bold">{{ pour.remaining_volume_m3 }} m³</strong>
                                </span>
                                <span class="text-slate-500">
                                    Trips: <strong>{{ pour.completed_trips }}</strong>/{{ pour.total_trips }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Trip Timeline & Dispatch Gantt Grid -->
                <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    
                    <!-- Filter Toolbar -->
                    <div class="p-4 border-b border-slate-200 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Date Selector -->
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-500 uppercase">Date:</span>
                                <input 
                                    type="date" 
                                    v-model="scheduleDate" 
                                    @change="fetchData"
                                    class="text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded px-2.5 py-1.5 focus:ring-1 focus:ring-[#0064d2]"
                                />
                            </div>

                            <!-- Pour Reference Search -->
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="pourSearch" 
                                    placeholder="Search Pour Ref..." 
                                    class="text-xs text-slate-700 bg-white border border-slate-300 rounded pl-2.5 pr-2 py-1.5 w-44 focus:ring-1 focus:ring-[#0064d2]"
                                />
                            </div>

                            <!-- Site Filter -->
                            <select 
                                v-model="siteFilter" 
                                @change="fetchData"
                                class="text-xs text-slate-700 bg-white border border-slate-300 rounded px-2.5 py-1.5 focus:ring-1 focus:ring-[#0064d2]"
                            >
                                <option value="">All Sites</option>
                                <option v-for="site in dropdowns.sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                            </select>
                        </div>

                        <!-- Status Pill Filters -->
                        <div class="flex items-center gap-1 overflow-x-auto whitespace-nowrap">
                            <button 
                                v-for="st in [
                                    { id: 'all', label: 'All' },
                                    { id: 'scheduled', label: 'Scheduled' },
                                    { id: 'batching', label: 'Batching' },
                                    { id: 'in_transit', label: 'In Transit' },
                                    { id: 'on_site', label: 'On Site' },
                                    { id: 'pouring', label: 'Pouring' },
                                    { id: 'completed', label: 'Completed' }
                                ]"
                                :key="st.id"
                                @click="statusFilter = st.id; fetchData()"
                                class="px-2.5 py-1 rounded text-[11px] font-bold transition-all"
                                :class="statusFilter === st.id ? 'bg-[#0064d2] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                            >
                                {{ st.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Dispatch Trips Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3 px-3.5 text-center">Trip #</th>
                                    <th class="py-3 px-3.5">Pour Ref / Site</th>
                                    <th class="py-3 px-3.5">Mix Grade</th>
                                    <th class="py-3 px-3.5 text-right">Volume (m³)</th>
                                    <th class="py-3 px-3.5">Transit Mixer & Driver</th>
                                    <th class="py-3 px-3.5">Pump Setup</th>
                                    <th class="py-3 px-3.5">Timeline Milestones</th>
                                    <th class="py-3 px-3.5 text-center">Hydration</th>
                                    <th class="py-3 px-3.5 text-center">Status</th>
                                    <th class="py-3 px-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr v-if="filteredSchedules.length === 0">
                                    <td colspan="10" class="py-12 text-center text-slate-400 italic">
                                        No batching schedules match your current filters. Click "Schedule Trip / Pour" to add one.
                                    </td>
                                </tr>
                                
                                <tr 
                                    v-for="item in filteredSchedules" 
                                    :key="item.id"
                                    class="hover:bg-slate-50/80 transition-colors"
                                >
                                    <!-- Trip # -->
                                    <td class="py-3.5 px-3.5 text-center font-bold text-slate-700">
                                        #{{ item.id }}
                                        <div v-if="item.dispatch" class="text-[9px] text-[#0064d2] font-semibold mt-0.5">
                                            {{ item.dispatch.dispatch_no }}
                                        </div>
                                    </td>

                                    <!-- Pour Ref & Site -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="font-bold text-slate-800">{{ item.pour_reference }}</div>
                                        <div class="text-[10px] text-slate-500 truncate max-w-[160px]" :title="item.site?.name">
                                            {{ item.site?.name || 'Unspecified Site' }}
                                        </div>
                                    </td>

                                    <!-- Mix Grade -->
                                    <td class="py-3.5 px-3.5">
                                        <span class="font-bold text-slate-800">{{ item.mix_design?.name || '-' }}</span>
                                        <div class="text-[10px] text-slate-400">{{ item.mix_design?.code || '' }}</div>
                                    </td>

                                    <!-- Volume -->
                                    <td class="py-3.5 px-3.5 text-right">
                                        <span class="font-black text-slate-800 text-sm">{{ item.qty_m3 }}</span>
                                        <div class="text-[10px] text-slate-400">Bal: {{ item.remaining_volume_m3 }} m³</div>
                                    </td>

                                    <!-- TM & Driver -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="font-bold text-slate-700 flex items-center gap-1">
                                            <TruckIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                            {{ item.vehicle?.registration || 'TM Unassigned' }}
                                        </div>
                                        <div class="text-[10px] text-slate-500">
                                            {{ item.driver ? item.driver.first_name + ' ' + (item.driver.last_name || '') : 'Driver Pending' }}
                                        </div>
                                    </td>

                                    <!-- Pump Deployment -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="text-[11px] font-semibold capitalize" :class="item.pump_type === 'boom_pump' ? 'text-indigo-700' : 'text-amber-800'">
                                            {{ item.pump_type.replace('_', ' ') }}
                                        </div>
                                        <div v-if="item.pump_vehicle" class="text-[10px] text-slate-500">
                                            {{ item.pump_vehicle.registration }}
                                        </div>
                                    </td>

                                    <!-- Timeline Milestones -->
                                    <td class="py-3.5 px-3.5 text-[10px] text-slate-600">
                                        <div class="grid grid-cols-2 gap-x-2 gap-y-0.5">
                                            <div>Batch: <strong class="text-slate-800">{{ formatTime(item.batching_time) }}</strong></div>
                                            <div>Disp: <strong class="text-slate-800">{{ formatTime(item.dispatch_time) }}</strong></div>
                                            <div>ETA: <strong class="text-slate-800">{{ formatTime(item.eta_site) }}</strong></div>
                                            <div>Pour: <strong class="text-slate-800">{{ formatTime(item.unloading_start) }}</strong></div>
                                        </div>
                                    </td>

                                    <!-- Hydration Age -->
                                    <td class="py-3.5 px-3.5 text-center">
                                        <div v-if="item.hydration_age_minutes !== null">
                                            <span 
                                                class="px-2 py-0.5 rounded font-bold text-[10px] border"
                                                :class="{
                                                    'bg-emerald-50 text-emerald-700 border-emerald-200': item.hydration_status === 'normal' || item.hydration_status === 'discharged',
                                                    'bg-amber-50 text-amber-700 border-amber-300 animate-pulse': item.hydration_status === 'warning',
                                                    'bg-rose-50 text-rose-700 border-rose-300 font-black animate-bounce': item.hydration_status === 'critical'
                                                }"
                                            >
                                                {{ item.hydration_age_minutes }}m
                                            </span>
                                        </div>
                                        <span v-else class="text-slate-300 text-[10px]">-</span>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3.5 px-3.5 text-center">
                                        <span 
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block"
                                            :class="getStatusBadge(item.status).bg"
                                        >
                                            {{ getStatusBadge(item.status).label }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <!-- Fast Next Status Button -->
                                            <button 
                                                v-if="item.status === 'scheduled'" 
                                                @click="transitionStatus(item, 'batching')"
                                                class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded transition-colors"
                                                title="Start Batching"
                                            >
                                                <PlayIcon class="w-3.5 h-3.5" />
                                            </button>

                                            <button 
                                                v-else-if="item.status === 'batching'" 
                                                @click="createDispatch(item)"
                                                class="px-2 py-1 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded text-[10px] transition-colors flex items-center gap-1"
                                                title="Dispatch TM & Generate Ticket"
                                            >
                                                <PaperAirplaneIcon class="w-3 h-3" />
                                                <span>Dispatch</span>
                                            </button>

                                            <button 
                                                v-else-if="item.status === 'in_transit'" 
                                                @click="transitionStatus(item, 'on_site')"
                                                class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded text-[10px] transition-colors"
                                                title="Mark TM Arrived at Site"
                                            >
                                                On Site
                                            </button>

                                            <button 
                                                v-else-if="item.status === 'on_site'" 
                                                @click="transitionStatus(item, 'pouring')"
                                                class="px-2 py-1 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded text-[10px] transition-colors"
                                                title="Start Unloading into Pump Hopper"
                                            >
                                                Start Pour
                                            </button>

                                            <button 
                                                v-else-if="item.status === 'pouring'" 
                                                @click="transitionStatus(item, 'completed')"
                                                class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded text-[10px] transition-colors"
                                                title="Complete Discharge & Washup"
                                            >
                                                Done
                                            </button>

                                            <!-- Edit Modal Button -->
                                            <button 
                                                @click="openEditModal(item)"
                                                class="p-1.5 hover:bg-slate-100 text-slate-500 rounded transition-colors"
                                                title="Edit Slot"
                                            >
                                                <PencilSquareIcon class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Delete Button -->
                                            <button 
                                                @click="deleteSchedule(item)"
                                                class="p-1.5 hover:bg-rose-50 text-rose-500 rounded transition-colors"
                                                title="Delete Slot"
                                            >
                                                <TrashIcon class="w-3.5 h-3.5" />
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

        <!-- 4. Create / Edit Schedule Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-[#1d2d3e] text-white flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider">
                            {{ isEditing ? 'Edit Concrete Batching Trip #' + editingId : 'Schedule Concrete Batching & Dispatch' }}
                        </h3>
                        <p class="text-[11px] text-slate-300 mt-0.5">Plan production volume, transit mixer logistics, and site pump setup.</p>
                    </div>
                    <button @click="isModalOpen = false" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <!-- Modal Body Form -->
                <div class="p-6 space-y-4 text-xs">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Schedule Date -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Schedule Date *</label>
                            <input type="date" v-model="form.schedule_date" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-[#0064d2]" required />
                        </div>

                        <!-- Pour Reference -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Pour Reference / Structure Tag *</label>
                            <input type="text" v-model="form.pour_reference" placeholder="e.g. SLAB-L3-POUR-A, RAFT-01" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-[#0064d2]" required />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Site Destination -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Delivery Destination Site *</label>
                            <select v-model="form.site_id" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-[#0064d2]" required>
                                <option value="">Select Delivery Site</option>
                                <option v-for="site in dropdowns.sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                            </select>
                        </div>

                        <!-- Mix Design -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Mix Design / Concrete Grade *</label>
                            <select v-model="form.mix_design_id" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-[#0064d2]" required>
                                <option value="">Select Mix Design</option>
                                <option v-for="mix in dropdowns.mixDesigns" :key="mix.id" :value="mix.id">{{ mix.name }} ({{ mix.code || '-' }})</option>
                            </select>
                        </div>
                    </div>

                    <!-- Volume Specifications -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Trip / Load Volume (m³) *</label>
                            <input type="number" step="0.5" v-model="form.qty_m3" placeholder="e.g. 6.0" class="w-full bg-white border border-slate-300 rounded p-2 text-xs font-bold text-[#0064d2]" required />
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Quantity for this specific Transit Mixer load.</span>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Total Order / Pour Volume (m³) *</label>
                            <input type="number" step="0.5" v-model="form.order_volume_m3" placeholder="e.g. 45.0" class="w-full bg-white border border-slate-300 rounded p-2 text-xs font-bold text-slate-800" required />
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Total volume required for the full pour element.</span>
                        </div>
                    </div>

                    <!-- Logistics & Fleet Assignment -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Transit Mixer -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Assigned Transit Mixer (TM)</label>
                            <select v-model="form.vehicle_id" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs">
                                <option value="">Assign Later / Available Fleet</option>
                                <option v-for="veh in dropdowns.vehicles" :key="veh.id" :value="veh.id">
                                    {{ veh.registration }} ({{ veh.vehicle_model || veh.capacity + ' m³' || 'TM' }})
                                </option>
                            </select>
                        </div>

                        <!-- Driver -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Assigned TM Driver</label>
                            <select v-model="form.driver_id" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs">
                                <option value="">Assign Later</option>
                                <option v-for="drv in dropdowns.drivers" :key="drv.id" :value="drv.id">
                                    {{ drv.first_name }} {{ drv.last_name || '' }} ({{ drv.phone || drv.employee_code }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Pump / Placement Deployment -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-indigo-50/50 p-3 rounded-lg border border-indigo-100">
                        <div>
                            <label class="block font-bold text-indigo-900 mb-1">Placement / Pump Method *</label>
                            <select v-model="form.pump_type" class="w-full bg-white border border-indigo-200 rounded p-2 text-xs font-semibold text-indigo-900">
                                <option value="boom_pump">Boom Pump (Mobile Articulated Boom)</option>
                                <option value="line_pump">Line Pump (Ground / Stationary Pipeline)</option>
                                <option value="crane_bucket">Crane & Bucket Pour</option>
                                <option value="direct_pour">Direct Chute Discharge</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-indigo-900 mb-1">Deployed Pump Vehicle / Rig</label>
                            <select v-model="form.pump_vehicle_id" class="w-full bg-white border border-indigo-200 rounded p-2 text-xs text-indigo-900">
                                <option value="">None / External Pump</option>
                                <option v-for="veh in dropdowns.vehicles" :key="veh.id" :value="veh.id">
                                    {{ veh.registration }} ({{ veh.vehicle_model || 'Pump' }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Target Batching Time</label>
                            <input type="datetime-local" v-model="form.batching_time" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Estimated Arrival at Site (ETA)</label>
                            <input type="datetime-local" v-model="form.eta_site" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs" />
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Special Slump / Site Pour Instructions</label>
                        <textarea v-model="form.notes" rows="2" placeholder="e.g. Slump 120±25mm, add retarder dosage for 30km lead distance, 4th floor line pump..." class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs"></textarea>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                    <button @click="isModalOpen = false" class="px-4 py-2 border border-slate-300 text-slate-700 rounded text-xs font-bold hover:bg-slate-100 transition-colors">
                        Cancel
                    </button>
                    <button @click="saveSchedule" :disabled="saving" class="px-5 py-2 bg-[#0064d2] hover:bg-[#0052b3] text-white rounded text-xs font-bold shadow-sm transition-colors flex items-center gap-1.5">
                        <span v-if="saving" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span>{{ isEditing ? 'Save Changes' : 'Confirm Schedule' }}</span>
                    </button>
                </div>

            </div>
        </div>

    </AppLayout>
</template>
