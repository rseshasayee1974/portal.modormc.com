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
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dropdown from '@/Components/Dropdown.vue';
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
    XCircleIcon,
    EllipsisVerticalIcon,
    ExclamationTriangleIcon,
    ListBulletIcon,
    DocumentTextIcon
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
    window.addEventListener('click', closeActionMenu);
    window.addEventListener('scroll', closeActionMenu, true);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    window.removeEventListener('click', closeActionMenu);
    window.removeEventListener('scroll', closeActionMenu, true);
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

const getRowClass = (data) => {
    if (!data) return '';
    if (data.status === 'delayed') return 'bg-orange-50/30 dark:bg-orange-950/20';
    if (data.status === 'cancelled') return 'opacity-60 bg-gray-50/40 dark:bg-gray-800/40';
    return '';
};

// Floating Action Menu Popover (Teleported to avoid overflow clipping)
const activeActionMenu = ref(null);

const openActionMenu = (event, item) => {
    event.stopPropagation();
    if (activeActionMenu.value?.id === item.id) {
        activeActionMenu.value = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    const menuHeight = 240;
    const spaceBelow = window.innerHeight - rect.bottom;
    const openUpwards = spaceBelow < menuHeight && rect.top > menuHeight;

    activeActionMenu.value = {
        item,
        id: item.id,
        top: openUpwards ? Math.max(10, rect.top - menuHeight) : rect.bottom + 4,
        right: Math.max(12, window.innerWidth - rect.right),
    };
};

const closeActionMenu = () => {
    activeActionMenu.value = null;
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
        inputValue: item.notes || '',
        inputPlaceholder: 'Enter delay notes...',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        confirmButtonText: 'Confirm Delay'
    });

    if (reason !== undefined) {
        await axios.patch(route('production.pump-deployments.update-status', item.id), {
            status: 'delayed',
            notes: reason,
        });
        fetchData();
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Marked as Delayed', timer: 2000, showConfirmButton: false });
    }
};

const markCancelled = async (item) => {
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'Cancel Pour Deployment?',
        text: `Are you sure you want to cancel pour "${item.pour_reference}"? This requires planner confirmation.`,
        input: 'text',
        inputLabel: 'Reason for cancellation (optional)',
        inputValue: item.notes || '',
        inputPlaceholder: 'Enter cancellation notes...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Cancel Pour'
    });

    if (!isConfirmed) return;

    await axios.patch(route('production.pump-deployments.update-status', item.id), {
        status: 'cancelled',
        notes: reason,
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
        if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(ts)) {
            const [h, m] = ts.split(':');
            const hour = parseInt(h, 10);
            const period = hour >= 12 ? 'PM' : 'AM';
            const formattedHour = hour % 12 || 12;
            return `${formattedHour}:${m} ${period}`;
        }
        const normalized = ts.replace('t', 'T');
        const d = new Date(normalized);
        if (isNaN(d.getTime())) return ts;
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
    } catch (e) {
        return ts;
    }
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'scheduled':
            return { label: 'Scheduled', severity: 'secondary' };
        case 'en_route':
            return { label: 'En Route', severity: 'info' };
        case 'setup':
            return { label: 'Setup', severity: 'warn' };
        case 'ready':
            return { label: 'Ready', severity: 'info' };
        case 'in_progress':
        case 'pumping':
            return { label: 'In Progress', severity: 'info' };
        case 'washout':
            return { label: 'Washout', severity: 'secondary' };
        case 'completed':
            return { label: 'Completed', severity: 'success' };
        case 'delayed':
            return { label: 'Delayed', severity: 'warn' };
        case 'breakdown':
            return { label: 'Breakdown', severity: 'danger' };
        case 'cancelled':
            return { label: 'Cancelled', severity: 'danger' };
        default:
            return { label: status, severity: 'secondary' };
    }
};
</script>

<template>
    <AppLayout title="Pump & Boom Deployments">
        <div class="py-2 px-2 sm:px-4 w-full">
            <ModuleSubTopNav />

            <div class="w-full mt-3 space-y-3">
                
                <!-- Main Header Card in Indigo Theme -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                            <WrenchScrewdriverIcon class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Placement & Production Logistics
                                </span>
                                <span class="text-[9px] bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 px-1.5 py-0.2 rounded font-mono font-semibold border border-indigo-100 dark:border-indigo-900">
                                    Plant Active
                                </span>
                            </div>
                            <h1 class="text-sm sm:text-base font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                                Concrete Pour Schedule & Pump Deployments
                            </h1>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link 
                            :href="route('production.batching-schedules.index')" 
                            class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
                        >
                            <CalendarIcon class="w-3.5 h-3.5 text-indigo-600" />
                            <span>Batching Schedules</span>
                        </Link>

                        <button 
                            @click="fetchData" 
                            :disabled="loading"
                            class="p-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center transition-colors shadow-xs"
                            title="Refresh Live Data"
                        >
                            <ArrowPathIcon class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" />
                        </button>

                        <button 
                            v-if="activeView === 'list'"
                            @click="openCreateForm"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-xs transition-colors"
                        >
                            <PlusIcon class="w-3.5 h-3.5 stroke-[2.5]" />
                            <span>Deploy Pump / Boom</span>
                        </button>

                        <button 
                            v-else
                            @click="activeView = 'list'"
                            class="px-3.5 py-1.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-xs transition-colors"
                        >
                            <ListBulletIcon class="w-3.5 h-3.5" />
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
                <div v-else class="space-y-3">

                    <!-- 1. Operational KPI Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-gray-400 dark:text-gray-500 tracking-wider block">Total Deployments</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-gray-900 dark:text-gray-100">{{ metrics.total_deployments }}</span>
                                <span class="text-[10px] font-semibold text-gray-400">Rigs</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider block">Planned Volume</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-indigo-600 dark:text-indigo-400">{{ metrics.total_planned_m3 }}</span>
                                <span class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400">m³</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-blue-600 dark:text-blue-400 tracking-wider block">Active Pumping</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-blue-600 dark:text-blue-400">{{ metrics.active_pumping_count }}</span>
                                <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-400">Pumps</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-amber-600 dark:text-amber-400 tracking-wider block">Setup & Rigging</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-amber-600 dark:text-amber-400">{{ metrics.setup_in_progress }}</span>
                                <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400">Rigs</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-emerald-600 dark:text-emerald-400 tracking-wider block">Completed Pours</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ metrics.completed_deployments }}</span>
                                <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Pours</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-orange-600 dark:text-orange-400 tracking-wider block">Delayed Pours</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-orange-600 dark:text-orange-400">{{ metrics.delayed_count }}</span>
                                <span class="text-[10px] font-semibold text-orange-600 dark:text-orange-400">Alerts</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Main Filter & Schedule Table Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs overflow-hidden text-xs">
                        
                        <!-- 7 Operational Filter Bar -->
                        <div class="p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <FunnelIcon class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                                        Operational Filters
                                    </span>
                                </div>
                                <button 
                                    @click="resetFilters" 
                                    class="text-[10px] font-bold text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition-colors flex items-center gap-1"
                                >
                                    <ArrowPathIcon class="w-3 h-3" />
                                    <span>Reset Filters</span>
                                </button>
                            </div>

                            <!-- Filter Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-2">
                                
                                <!-- 1. Date -->
                                <div>
                                    <BaseInput
                                        v-model="filters.schedule_date"
                                        type="date"
                                        label="Date"
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
                                        label="Machine"
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
                            <div class="flex items-center gap-1.5 pt-0.5 overflow-x-auto whitespace-nowrap">
                                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase mr-1">Status:</span>
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
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold transition-all border"
                                    :class="filters.status === st.id ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border-gray-200 dark:border-gray-600'"
                                >
                                    {{ st.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Data Table using BaseDataTable -->
                        <div class="w-full">
                            <BaseDataTable
                                :value="filteredDeployments"
                                :loading="loading"
                                dataKey="id"
                                :paginator="true"
                                :rows="20"
                                :rowsPerPageOptions="[10, 20, 50, 100]"
                                :showSerial="true"
                                :rowClass="getRowClass"
                                class="text-xs"
                            >
                                <!-- Pour Reference & Mix -->
                                <Column field="pour_reference" header="Pour Reference" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100 text-xs">
                                            {{ data.pour_reference }}
                                        </div>
                                        <div class="mt-0.5">
                                            <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900">
                                                {{ data.grade || data.mix_design?.name || 'Standard Mix' }}
                                            </span>
                                        </div>
                                    </template>
                                </Column>

                                <!-- Destination & Site -->
                                <Column field="site_name" header="Destination & Site" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200 text-xs flex items-center gap-1">
                                            <MapPinIcon class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                            <span class="truncate max-w-[140px]" :title="data.site_name || data.site?.name">{{ data.site_name || data.site?.name || 'Unspecified Site' }}</span>
                                        </div>
                                        <div v-if="data.pour_location" class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 pl-4.5 truncate max-w-[140px]" :title="data.pour_location">
                                            {{ data.pour_location }}
                                        </div>
                                    </template>
                                </Column>

                                <!-- Timelines -->
                                <Column field="schedule_date" header="Timelines" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-1 text-[11px] text-gray-700 dark:text-gray-300 font-medium">
                                            <CalendarIcon class="w-3 h-3 text-gray-400 shrink-0" />
                                            <span>{{ data.schedule_date }}</span>
                                        </div>
                                        <div class="text-[10px] text-gray-600 dark:text-gray-400 mt-0.5 flex items-center gap-1 whitespace-nowrap">
                                            <span class="text-gray-400">Target:</span>
                                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatTime(data.pour_start_time) }} - {{ formatTime(data.planned_end_time) }}</span>
                                        </div>
                                        <div v-if="data.actual_start_time || data.actual_end_time" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5 flex items-center gap-1 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                            <span>Act: {{ formatTime(data.actual_start_time) }} <template v-if="data.actual_end_time">- {{ formatTime(data.actual_end_time) }}</template></span>
                                        </div>
                                    </template>
                                </Column>

                                <!-- Volume -->
                                <Column field="planned_qty_m3" header="Volume" :sortable="true" align="right" headerClass="text-right">
                                    <template #body="{ data }">
                                        <div class="font-bold text-gray-900 dark:text-gray-100 text-xs">
                                            {{ Number(data.planned_qty_m3 || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }} <span class="text-[9px] font-normal text-gray-500">m³</span>
                                        </div>
                                        <div v-if="data.pumping_rate_m3_per_hour" class="text-[9px] text-teal-600 dark:text-teal-400 font-medium mt-0.5">
                                            ~{{ Number(data.pumping_rate_m3_per_hour).toFixed(1) }} m³/h
                                        </div>
                                    </template>
                                </Column>

                                <!-- Pump Rig & Reach -->
                                <Column field="pump_no" header="Pump Rig & Reach" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-1">
                                            <span 
                                                class="px-1 py-0.2 rounded text-[8px] font-bold uppercase tracking-wider border shrink-0"
                                                :class="data.pump_type === 'boom_pump' ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' : 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'"
                                            >
                                                {{ data.pump_type === 'boom_pump' ? 'Boom' : (data.pump_type === 'stationary_pump' ? 'Stationary' : (data.pump_type === 'line_pump' ? 'Line' : data.pump_type)) }}
                                            </span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200 text-xs whitespace-nowrap">
                                                {{ data.pump_no || data.pump_machine?.registration || 'TBD' }}
                                            </span>
                                        </div>
                                        <div v-if="data.boom_length_m" class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                            Reach: <span class="font-medium text-gray-700 dark:text-gray-300">{{ Number(data.boom_length_m).toFixed(0) }}m</span>
                                        </div>
                                    </template>
                                </Column>

                                <!-- Operator -->
                                <Column field="operator_name" header="Operator" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-1 whitespace-nowrap">
                                            <UserIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                            <span class="font-medium text-gray-800 dark:text-gray-200 text-xs truncate max-w-[110px]" :title="data.operator_name || (data.operator ? data.operator.first_name + ' ' + (data.operator.last_name || '') : 'Unassigned')">
                                                {{ data.operator_name || (data.operator ? data.operator.first_name + ' ' + (data.operator.last_name || '') : 'Unassigned') }}
                                            </span>
                                        </div>
                                        <div v-if="data.operator?.phone" class="text-[9px] text-gray-400 pl-4.5">
                                            {{ data.operator.phone }}
                                        </div>
                                    </template>
                                </Column>

                                <!-- Status Badge -->
                                <Column field="status" header="Status" :sortable="true" align="center" headerClass="text-center">
                                    <template #body="{ data }">
                                        <div class="flex items-center justify-center">
                                            <Tag 
                                                :value="getStatusBadge(data.status).label" 
                                                :severity="getStatusBadge(data.status).severity" 
                                                rounded 
                                                class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5"
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <!-- Actions -->
                                <Column header="Actions" align="right" headerClass="text-right" style="width: 80px">
                                    <template #body="{ data }">
                                        <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                            
                                            <!-- Quick Primary Progression Action Button (Temporarily commented)
                                            <button 
                                                v-if="data.status === 'scheduled'" 
                                                @click="transitionStatus(data, 'setup')"
                                                class="px-2 py-0.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded text-[11px] transition-colors shadow-xs"
                                                title="Start Rigging & Setup"
                                            >
                                                Setup
                                            </button>

                                            <button 
                                                v-else-if="data.status === 'setup'" 
                                                @click="transitionStatus(data, 'ready')"
                                                class="px-2 py-0.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded text-[11px] transition-colors shadow-xs"
                                                title="Mark Setup Finished & Ready"
                                            >
                                                Ready
                                            </button>

                                            <button 
                                                v-else-if="['ready', 'en_route'].includes(data.status)" 
                                                @click="transitionStatus(data, 'in_progress')"
                                                class="px-2 py-0.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded text-[11px] transition-colors flex items-center gap-1 shadow-xs"
                                                title="Record Actual Start (In Progress)"
                                            >
                                                <PlayIcon class="w-2.5 h-2.5" />
                                                <span>Start</span>
                                            </button>

                                            <button 
                                                v-else-if="['in_progress', 'pumping'].includes(data.status)" 
                                                @click="transitionStatus(data, 'completed')"
                                                class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded text-[11px] transition-colors flex items-center gap-1 shadow-xs"
                                                title="Record Actual End (Completed)"
                                            >
                                                <StopIcon class="w-2.5 h-2.5" />
                                                <span>Finish</span>
                                            </button>
                                            -->

                                            <!-- Edit Schedule -->
                                            <button 
                                                @click="openEditForm(data)"
                                                class="p-1 text-gray-500 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                                title="Edit Schedule"
                                            >
                                                <PencilSquareIcon class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Popover Trigger Button -->
                                            <button 
                                                type="button"
                                                @click="(e) => openActionMenu(e, data)"
                                                class="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                                :class="{ 'bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-indigo-400': activeActionMenu?.id === data.id }"
                                                title="More Options"
                                            >
                                                <EllipsisVerticalIcon class="w-4 h-4" />
                                            </button>

                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-10 flex flex-col items-center justify-center text-gray-400">
                                        <WrenchScrewdriverIcon class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" />
                                        <span class="font-medium text-xs">No pour deployments matching the selected filters. Click "Deploy Pump / Boom" to create one.</span>
                                    </div>
                                </template>
                            </BaseDataTable>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- Teleported Action Popover (Never clipped by container overflow) -->
        <Teleport to="body">
            <div 
                v-if="activeActionMenu" 
                class="fixed inset-0 z-[9998]" 
                @click="closeActionMenu"
            />
            <div 
                v-if="activeActionMenu" 
                class="fixed z-[9999] w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xl rounded-xl py-1 text-xs divide-y divide-gray-100 dark:divide-gray-700 transition-all"
                :style="{ top: `${activeActionMenu.top}px`, right: `${activeActionMenu.right}px` }"
                @click.stop
            >
                <!-- Status Actions -->
                <div class="py-1">
                    <div class="px-3 py-1 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                        Status Actions
                    </div>

                    <button
                        v-if="activeActionMenu.item.status === 'scheduled'"
                        @click="transitionStatus(activeActionMenu.item, 'setup'); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/40 flex items-center gap-2 transition-colors"
                    >
                        <WrenchScrewdriverIcon class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <span>Move to Setup / Rigging</span>
                    </button>

                    <button
                        v-if="activeActionMenu.item.status === 'setup'"
                        @click="transitionStatus(activeActionMenu.item, 'ready'); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 flex items-center gap-2 transition-colors"
                    >
                        <CheckCircleIcon class="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                        <span>Mark Ready & Primed</span>
                    </button>

                    <button
                        v-if="['ready', 'scheduled', 'en_route'].includes(activeActionMenu.item.status)"
                        @click="transitionStatus(activeActionMenu.item, 'in_progress'); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-950/40 flex items-center gap-2 transition-colors"
                    >
                        <PlayIcon class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                        <span>Start Pour (In Progress)</span>
                    </button>

                    <button
                        v-if="['in_progress', 'pumping'].includes(activeActionMenu.item.status)"
                        @click="transitionStatus(activeActionMenu.item, 'completed'); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 flex items-center gap-2 transition-colors"
                    >
                        <StopIcon class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>Complete & Finish Pour</span>
                    </button>

                    <button
                        v-if="!['completed', 'cancelled', 'delayed'].includes(activeActionMenu.item.status)"
                        @click="markDelayed(activeActionMenu.item); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-orange-700 dark:text-orange-300 hover:bg-orange-50 dark:hover:bg-orange-950/40 flex items-center gap-2 transition-colors"
                    >
                        <ClockIcon class="w-3.5 h-3.5 text-orange-500 shrink-0" />
                        <span>Mark as Delayed...</span>
                    </button>

                    <button
                        v-if="!['completed', 'cancelled'].includes(activeActionMenu.item.status)"
                        @click="markCancelled(activeActionMenu.item); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2 transition-colors"
                    >
                        <XCircleIcon class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                        <span>Cancel Pour...</span>
                    </button>
                </div>

                <!-- Management Options -->
                <div class="py-1">
                    <!-- <button
                        @click="openEditForm(activeActionMenu.item); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors"
                    >
                        <PencilSquareIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                        <span>Edit Deployment</span>
                    </button> -->

                    <button
                        @click="deleteDeployment(activeActionMenu.item); closeActionMenu()"
                        class="w-full text-left px-3 py-1.5 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 flex items-center gap-2 transition-colors"
                    >
                        <TrashIcon class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                        <span>Delete Deployment</span>
                    </button>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
