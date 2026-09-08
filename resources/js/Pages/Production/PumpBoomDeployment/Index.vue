<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import PumpDeploymentForm from './components/PumpDeploymentForm.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import {
    WrenchScrewdriverIcon,
    ArrowPathIcon,
    PlusIcon,
    CalendarIcon,
    MapPinIcon,
    FunnelIcon,
    PlayIcon,
    StopIcon,
    PencilSquareIcon,
    TrashIcon,
    UserIcon,
    TruckIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
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
const selectedDeployment = ref(null);

// 7 Operational Filters: Schedule date, Site, Pour location, Pump type, Pump number, Operator, Status
const filters = ref({
    schedule_date: props.initialDate || new Date().toISOString().substring(0, 10),
    site_id: 'all',
    pour_location: '',
    pump_type: props.initialFilters?.pump_type || 'all',
    pump_no: 'all',
    operator_id: 'all',
    status: props.initialFilters?.status || 'all',
});

const pourSearch = ref('');
const loading = ref(false);
const deployments = ref([]);
const metrics = ref({
    total_deployments: 0,
    total_planned_m3: 0,
    active_pumping_count: 0,
    setup_in_progress: 0,
    completed_deployments: 0,
    delayed_count: 0,
});

const dropdowns = ref({
    sites: [],
    mixDesigns: [],
    machines: [],
    operators: [],
    pumpTypes: []
});

// Dropdown option maps for filter dropdowns
const siteFilterOptions = computed(() => [
    { label: 'All Sites', value: 'all' },
    ...(props.dropdowns?.sites || dropdowns.value.sites || []).map(s => ({ label: s.name, value: s.id }))
]);

const machineFilterOptions = computed(() => [
    { label: 'All Machines', value: 'all' },
    ...(props.dropdowns?.machines || dropdowns.value.machines || []).map(m => ({ label: `${m.registration} (${m.vehicle_model || 'Rig'})`, value: m.registration }))
]);

const operatorFilterOptions = computed(() => [
    { label: 'All Operators', value: 'all' },
    ...(props.dropdowns?.operators || dropdowns.value.operators || []).map(o => ({ label: `${o.first_name} ${o.last_name || ''}`, value: o.id }))
]);

const pumpTypeFilterOptions = [
    { label: 'All Types', value: 'all' },
    { label: 'Boom Pump', value: 'boom_pump' },
    { label: 'Line Pump', value: 'line_pump' },
    { label: 'Stationary Pump', value: 'stationary_pump' },
    { label: 'Crane & Bucket', value: 'crane_bucket' },
    { label: 'Direct Chute', value: 'direct_pour' },
];

const statusFilterOptions = [
    { label: 'All Statuses', value: 'all' },
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'En Route', value: 'en_route' },
    { label: 'Setup', value: 'setup' },
    { label: 'Ready', value: 'ready' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Completed', value: 'completed' },
    { label: 'Delayed', value: 'delayed' },
    { label: 'Cancelled', value: 'cancelled' },
];

let pollTimer = null;
let searchDebounceTimer = null;

const fetchDropdowns = async () => {
    try {
        const res = await axios.get(route('production.pump-deployments.dropdowns'));
        dropdowns.value = res.data;
    } catch (err) {
        console.error('Failed to load dropdowns:', err);
    }
};

const fetchData = async () => {
    loading.value = true;
    try {
        const res = await axios.get(route('production.pump-deployments.data'), {
            params: {
                schedule_date: filters.value.schedule_date,
                site_id: filters.value.site_id,
                pour_location: filters.value.pour_location,
                pump_type: filters.value.pump_type,
                pump_no: filters.value.pump_no,
                operator_id: filters.value.operator_id,
                status: filters.value.status,
                pour_reference: pourSearch.value,
            }
        });
        deployments.value = res.data.deployments || [];
        metrics.value = res.data.metrics || metrics.value;
    } catch (err) {
        console.error('Error fetching pump deployments:', err);
    } finally {
        loading.value = false;
    }
};

const onLocationSearchInput = () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        fetchData();
    }, 400);
};

const resetFilters = () => {
    filters.value = {
        schedule_date: new Date().toISOString().substring(0, 10),
        site_id: 'all',
        pour_location: '',
        pump_type: 'all',
        pump_no: 'all',
        operator_id: 'all',
        status: 'all',
    };
    pourSearch.value = '';
    fetchData();
};

onMounted(() => {
    fetchDropdowns();
    fetchData();
    pollTimer = setInterval(fetchData, 30000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});

// Real-time client-side filter computation
const filteredDeployments = computed(() => {
    return deployments.value.filter(d => {
        if (filters.value.schedule_date && filters.value.schedule_date !== 'all' && d.schedule_date !== filters.value.schedule_date) return false;
        if (filters.value.site_id !== 'all' && d.site_id != filters.value.site_id) return false;
        if (filters.value.pour_location && !d.pour_location?.toLowerCase().includes(filters.value.pour_location.toLowerCase())) return false;
        if (filters.value.pump_type !== 'all' && d.pump_type !== filters.value.pump_type) return false;
        if (filters.value.pump_no !== 'all') {
            const matchesId = d.pump_vehicle_id == filters.value.pump_no;
            const matchesNo = d.pump_no?.toLowerCase().includes(filters.value.pump_no.toLowerCase());
            if (!matchesId && !matchesNo) return false;
        }
        if (filters.value.operator_id !== 'all' && d.operator_id != filters.value.operator_id) return false;
        if (filters.value.status !== 'all') {
            if (filters.value.status === 'in_progress') {
                if (!['in_progress', 'pumping'].includes(d.status)) return false;
            } else if (d.status !== filters.value.status) {
                return false;
            }
        }
        if (pourSearch.value && !d.pour_reference?.toLowerCase().includes(pourSearch.value.toLowerCase()) && !d.pour_location?.toLowerCase().includes(pourSearch.value.toLowerCase())) return false;
        return true;
    });
});

// View Navigation Actions (No Modals)
const openCreateForm = () => {
    selectedDeployment.value = null;
    activeView.value = 'create';
};

const openEditForm = (item) => {
    selectedDeployment.value = item;
    activeView.value = 'edit';
};

const handleFormSaved = () => {
    activeView.value = 'list';
    selectedDeployment.value = null;
    fetchData();
};

const handleFormCancel = () => {
    activeView.value = 'list';
    selectedDeployment.value = null;
};

// 1-Click Operational Status Transition
const transitionStatus = async (item, nextStatus) => {
    try {
        await axios.patch(route('production.pump-deployments.update-status', item.id), {
            status: nextStatus
        });
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Status updated to ${nextStatus.replace('_', ' ').toUpperCase()}`,
            showConfirmButton: false,
            timer: 2000
        });
        fetchData();
    } catch (err) {
        console.error('Failed to update status:', err);
        Swal.fire('Error', 'Failed to change pump status.', 'error');
    }
};

// Explicit Delayed & Cancelled actions
const markDelayed = async (item) => {
    const { value: reason } = await Swal.fire({
        title: 'Mark Pour as Delayed',
        input: 'text',
        inputLabel: 'Reason for delay (e.g. site access, weather, slump delay)',
        inputPlaceholder: 'Enter delay notes...',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        confirmButtonText: 'Confirm Delay'
    });

    if (reason !== undefined) {
        await axios.patch(route('production.pump-deployments.update-status', item.id), {
            status: 'delayed'
        });
        fetchData();
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Marked as Delayed', timer: 2000, showConfirmButton: false });
    }
};

const markCancelled = async (item) => {
    const result = await Swal.fire({
        title: 'Cancel Pour Deployment?',
        text: `Are you sure you want to cancel pour "${item.pour_reference}"? This requires planner confirmation.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Cancel Pour'
    });

    if (!result.isConfirmed) return;

    await axios.patch(route('production.pump-deployments.update-status', item.id), {
        status: 'cancelled'
    });
    fetchData();
    Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Pour deployment cancelled', timer: 2000, showConfirmButton: false });
};

const deleteDeployment = async (item) => {
    const result = await Swal.fire({
        title: 'Delete Deployment Schedule?',
        text: `Delete pump allocation for pour "${item.pour_reference}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    });

    if (!result.isConfirmed) return;

    try {
        await axios.delete(route('production.pump-deployments.destroy', item.id));
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted successfully', timer: 2000, showConfirmButton: false });
        fetchData();
    } catch (err) {
        Swal.fire('Error', 'Failed to delete deployment.', 'error');
    }
};

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
        case 'en_route':
            return { label: 'En Route', bg: 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-300 dark:border-sky-800' };
        case 'setup':
            return { label: 'Setup / Rigging', bg: 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800' };
        case 'ready':
            return { label: 'Ready & Primed', bg: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800' };
        case 'in_progress':
        case 'pumping':
            return { label: 'In Progress', bg: 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300 border-blue-300 dark:border-blue-800 font-bold' };
        case 'washout':
            return { label: 'Line Washout', bg: 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-800' };
        case 'completed':
            return { label: 'Completed', bg: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800 font-bold' };
        case 'delayed':
            return { label: 'Delayed', bg: 'bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-300 dark:border-orange-800 font-bold' };
        case 'breakdown':
            return { label: 'Breakdown', bg: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800 font-bold' };
        case 'cancelled':
            return { label: 'Cancelled', bg: 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-300 dark:border-gray-700' };
        default:
            return { label: status, bg: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700' };
    }
};
</script>

<template>
    <AppLayout title="Pump & Boom Deployments">
        <div class="py-2 px-4">
            <ModuleSubTopNav />

            <div class="max-w-7xl mx-auto mt-4 space-y-4">
                
                <!-- Main Header Card in Indigo Theme -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md">
                            <WrenchScrewdriverIcon class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Placement & Production Logistics
                                </span>
                                <span class="text-[10px] bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded font-mono font-semibold border border-indigo-100 dark:border-indigo-900">
                                    Plant Active
                                </span>
                            </div>
                            <h1 class="text-base font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                                Concrete Pour Schedule & Pump Deployments
                            </h1>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link 
                            :href="route('production.batching-schedules.index')" 
                            class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm"
                        >
                            <CalendarIcon class="w-4 h-4 text-indigo-600" />
                            <span>Batching Schedules</span>
                        </Link>

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
                            <span>Deploy Pump / Boom</span>
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

                <!-- VIEW 1: CREATE / EDIT FORM (COMPLETELY REPLACING MODAL) -->
                <div v-if="activeView !== 'list'">
                    <PumpDeploymentForm
                        :isEditing="activeView === 'edit'"
                        :initialData="selectedDeployment"
                        :dropdowns="dropdowns"
                        :defaultScheduleDate="filters.schedule_date"
                        @saved="handleFormSaved"
                        @cancel="handleFormCancel"
                    />
                </div>

                <!-- VIEW 2: LIST DASHBOARD WITH KPI METRICS & FILTERS -->
                <div v-else class="space-y-4">

                    <!-- 1. Operational KPI Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500 tracking-wider block">Total Deployments</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-gray-900 dark:text-gray-100">{{ metrics.total_deployments }}</span>
                                <span class="text-xs font-bold text-gray-400">Rigs</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider block">Planned Volume</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-indigo-600 dark:text-indigo-400">{{ metrics.total_planned_m3 }}</span>
                                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">m³</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-blue-600 dark:text-blue-400 tracking-wider block">Active Pumping</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-blue-600 dark:text-blue-400">{{ metrics.active_pumping_count }}</span>
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400">Pumps</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400 tracking-wider block">Setup & Rigging</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-amber-600 dark:text-amber-400">{{ metrics.setup_in_progress }}</span>
                                <span class="text-xs font-bold text-amber-600 dark:text-amber-400">Rigs</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400 tracking-wider block">Completed Pours</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ metrics.completed_deployments }}</span>
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Pours</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <span class="text-[10px] font-bold uppercase text-orange-600 dark:text-orange-400 tracking-wider block">Delayed Pours</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="text-xl font-black text-orange-600 dark:text-orange-400">{{ metrics.delayed_count }}</span>
                                <span class="text-xs font-bold text-orange-600 dark:text-orange-400">Alerts</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Main Filter & Schedule Table Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden text-xs">
                        
                        <!-- 7 Operational Filter Bar -->
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <FunnelIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                                        Operational Filters
                                    </span>
                                </div>
                                <button 
                                    @click="resetFilters" 
                                    class="text-[11px] font-bold text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition-colors flex items-center gap-1"
                                >
                                    <ArrowPathIcon class="w-3.5 h-3.5" />
                                    <span>Reset Filters</span>
                                </button>
                            </div>

                            <!-- Filter Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2.5">
                                
                                <!-- 1. Date -->
                                <div>
                                    <BaseInput
                                        v-model="filters.schedule_date"
                                        type="date"
                                        label="Schedule Date"
                                        @update:modelValue="fetchData"
                                    />
                                </div>

                                <!-- 2. Site -->
                                <div>
                                    <BaseSelect
                                        v-model="filters.site_id"
                                        :options="siteFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Site"
                                        @change="fetchData"
                                    />
                                </div>

                                <!-- 3. Pour Location -->
                                <div>
                                    <BaseInput
                                        v-model="filters.pour_location"
                                        type="text"
                                        label="Location"
                                        placeholder="Raft, Slab..."
                                        @update:modelValue="onLocationSearchInput"
                                    />
                                </div>

                                <!-- 4. Pump Type -->
                                <div>
                                    <BaseSelect
                                        v-model="filters.pump_type"
                                        :options="pumpTypeFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Pump Type"
                                        @change="fetchData"
                                    />
                                </div>

                                <!-- 5. Pump Rig Number -->
                                <div>
                                    <BaseSelect
                                        v-model="filters.pump_no"
                                        :options="machineFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Pump Machine"
                                        @change="fetchData"
                                    />
                                </div>

                                <!-- 6. Operator -->
                                <div>
                                    <BaseSelect
                                        v-model="filters.operator_id"
                                        :options="operatorFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Operator"
                                        @change="fetchData"
                                    />
                                </div>

                                <!-- 7. Status -->
                                <div>
                                    <BaseSelect
                                        v-model="filters.status"
                                        :options="statusFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Status"
                                        @change="fetchData"
                                    />
                                </div>

                            </div>

                            <!-- Quick Status Filter Pills in Indigo Theme -->
                            <div class="flex items-center gap-1.5 pt-1 overflow-x-auto whitespace-nowrap">
                                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase mr-1">Status Pills:</span>
                                <button 
                                    v-for="st in [
                                        { id: 'all', label: 'All' },
                                        { id: 'scheduled', label: 'Scheduled' },
                                        { id: 'in_progress', label: 'In Progress' },
                                        { id: 'setup', label: 'Setup' },
                                        { id: 'ready', label: 'Ready' },
                                        { id: 'completed', label: 'Completed' },
                                        { id: 'delayed', label: 'Delayed' },
                                        { id: 'cancelled', label: 'Cancelled' }
                                    ]"
                                    :key="st.id"
                                    @click="filters.status = st.id; fetchData()"
                                    class="px-2.5 py-1 rounded-full text-[11px] font-bold transition-all border"
                                    :class="filters.status === st.id ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border-gray-200 dark:border-gray-600'"
                                >
                                    {{ st.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-100/70 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-3 px-3 text-center w-12">#</th>
                                        <th class="py-3 px-3">Pour Reference</th>
                                        <th class="py-3 px-3">Destination & Site</th>
                                        <th class="py-3 px-3">Timelines</th>
                                        <th class="py-3 px-3 text-right">Volume</th>
                                        <th class="py-3 px-3">Pump Rig & Reach</th>
                                        <th class="py-3 px-3">Operator</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                        <th class="py-3 px-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 font-medium">
                                    <tr v-if="filteredDeployments.length === 0">
                                        <td colspan="9" class="py-12 text-center text-gray-400 italic">
                                            No pour deployments matching the selected filters. Click "Deploy Pump / Boom" to create one.
                                        </td>
                                    </tr>

                                    <tr 
                                        v-for="item in filteredDeployments" 
                                        :key="item.id"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors"
                                        :class="{
                                            'bg-orange-50/30 dark:bg-orange-950/20': item.status === 'delayed',
                                            'opacity-60 bg-gray-50/40 dark:bg-gray-800/40': item.status === 'cancelled'
                                        }"
                                    >
                                        <!-- ID -->
                                        <td class="py-3 px-3 text-center font-bold text-gray-400">
                                            #{{ item.id }}
                                        </td>

                                        <!-- Pour -->
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-gray-900 dark:text-gray-100">
                                                {{ item.pour_reference }}
                                            </div>
                                            <div class="mt-0.5">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-1.5 py-0.5 rounded text-[10px] border border-indigo-100 dark:border-indigo-900">
                                                    {{ item.grade || item.mix_design?.name || 'Standard Mix' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Site & Location -->
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1">
                                                <MapPinIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                <span>{{ item.site_name || item.site?.name || 'Unspecified Site' }}</span>
                                            </div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mt-0.5 pl-4.5">
                                                {{ item.pour_location }}
                                            </div>
                                        </td>

                                        <!-- Timelines -->
                                        <td class="py-3 px-3 text-[11px]">
                                            <div class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1">
                                                <CalendarIcon class="w-3.5 h-3.5 text-gray-400" />
                                                <span>{{ item.schedule_date }}</span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                                <div>Target: <strong class="text-gray-700 dark:text-gray-300">{{ formatTime(item.pour_start_time) }}</strong></div>
                                                <div>Plan End: <strong class="text-gray-700 dark:text-gray-300">{{ formatTime(item.planned_end_time) }}</strong></div>
                                                <div>Act Start: <strong class="text-blue-600 dark:text-blue-400 font-bold">{{ formatTime(item.actual_start_time) }}</strong></div>
                                                <div>Act End: <strong class="text-emerald-600 dark:text-emerald-400 font-bold">{{ formatTime(item.actual_end_time) }}</strong></div>
                                            </div>
                                        </td>

                                        <!-- Volume -->
                                        <td class="py-3 px-3 text-right">
                                            <div class="font-black text-indigo-600 dark:text-indigo-400 text-sm">
                                                {{ item.planned_qty_m3 }} <span class="text-[10px] text-gray-400 font-normal">m³</span>
                                            </div>
                                            <div v-if="item.pumping_rate_m3_per_hour" class="text-[10px] text-teal-600 dark:text-teal-400 font-semibold">
                                                ~{{ item.pumping_rate_m3_per_hour }} m³/h
                                            </div>
                                        </td>

                                        <!-- Pump Rig -->
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-1.5">
                                                <span 
                                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border"
                                                    :class="item.pump_type === 'boom_pump' ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' : 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'"
                                                >
                                                    {{ item.pump_type === 'boom_pump' ? 'Boom' : (item.pump_type === 'stationary_pump' ? 'Stationary' : (item.pump_type === 'line_pump' ? 'Line' : item.pump_type)) }}
                                                </span>
                                                <strong class="text-gray-800 dark:text-gray-200">{{ item.pump_no || item.pump_machine?.registration || 'TBD' }}</strong>
                                            </div>
                                            <div v-if="item.boom_length_m" class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                Reach: <strong class="text-gray-700 dark:text-gray-300">{{ item.boom_length_m }}m</strong>
                                            </div>
                                        </td>

                                        <!-- Operator -->
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1">
                                                <UserIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                <span>{{ item.operator_name || (item.operator ? item.operator.first_name + ' ' + (item.operator.last_name || '') : 'Unassigned') }}</span>
                                            </div>
                                            <div v-if="item.operator?.phone" class="text-[10px] text-gray-400 pl-4.5">
                                                {{ item.operator.phone }}
                                            </div>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="py-3 px-3 text-center">
                                            <span 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold border inline-block"
                                                :class="getStatusBadge(item.status).bg"
                                            >
                                                {{ getStatusBadge(item.status).label }}
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-3 px-3 text-right">
                                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                                
                                                <!-- Action: Advance to Setup -->
                                                <button 
                                                    v-if="item.status === 'scheduled'" 
                                                    @click="transitionStatus(item, 'setup')"
                                                    class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Start Rigging & Setup"
                                                >
                                                    Setup
                                                </button>

                                                <!-- Action: Advance to Ready -->
                                                <button 
                                                    v-else-if="item.status === 'setup'" 
                                                    @click="transitionStatus(item, 'ready')"
                                                    class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded text-[10px] transition-colors shadow-sm"
                                                    title="Mark Setup Finished & Ready"
                                                >
                                                    Ready
                                                </button>

                                                <!-- Action: Start Pour -->
                                                <button 
                                                    v-else-if="['ready', 'scheduled'].includes(item.status)" 
                                                    @click="transitionStatus(item, 'in_progress')"
                                                    class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded text-[10px] transition-colors flex items-center gap-1 shadow-sm"
                                                    title="Record Actual Start (In Progress)"
                                                >
                                                    <PlayIcon class="w-3 h-3" />
                                                    <span>Start</span>
                                                </button>

                                                <!-- Action: Complete -->
                                                <button 
                                                    v-else-if="['in_progress', 'pumping'].includes(item.status)" 
                                                    @click="transitionStatus(item, 'completed')"
                                                    class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded text-[10px] transition-colors flex items-center gap-1 shadow-sm"
                                                    title="Record Actual End (Completed)"
                                                >
                                                    <StopIcon class="w-3 h-3" />
                                                    <span>Finish</span>
                                                </button>

                                                <!-- Delay -->
                                                <button 
                                                    v-if="!['completed', 'cancelled', 'delayed'].includes(item.status)"
                                                    @click="markDelayed(item)"
                                                    class="px-2 py-1 bg-orange-100 hover:bg-orange-200 dark:bg-orange-950/60 dark:hover:bg-orange-900 text-orange-800 dark:text-orange-300 font-bold rounded text-[10px] transition-colors"
                                                    title="Explicitly Mark as Delayed"
                                                >
                                                    Delay
                                                </button>

                                                <!-- Cancel -->
                                                <button 
                                                    v-if="!['completed', 'cancelled'].includes(item.status)"
                                                    @click="markCancelled(item)"
                                                    class="px-2 py-1 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900 text-rose-700 dark:text-rose-300 font-bold rounded text-[10px] transition-colors"
                                                    title="Explicitly Cancel Pour"
                                                >
                                                    Cancel
                                                </button>

                                                <!-- Edit (Switch to Form View without Modal) -->
                                                <button 
                                                    @click="openEditForm(item)"
                                                    class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 rounded transition-colors"
                                                    title="Edit Schedule"
                                                >
                                                    <PencilSquareIcon class="w-4 h-4" />
                                                </button>

                                                <!-- Delete -->
                                                <button 
                                                    @click="deleteDeployment(item)"
                                                    class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-rose-600 dark:text-rose-400 rounded transition-colors"
                                                    title="Delete Schedule"
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
