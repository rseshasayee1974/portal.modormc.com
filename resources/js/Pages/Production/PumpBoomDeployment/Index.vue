<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
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
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon,
    UserIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    plants: Array,
    activePlantId: Number,
    initialDate: String,
    initialFilters: Object,
});

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

const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const saving = ref(false);

const form = ref({
    schedule_date: filters.value.schedule_date,
    pour_reference: '',
    site_id: '',
    site_name: '',
    pour_location: '',
    mix_design_id: '',
    grade: '',
    planned_qty_m3: 45.0,
    pump_type: 'boom_pump',
    pump_vehicle_id: '',
    pump_no: '',
    boom_length_m: 36.0,
    operator_id: '',
    operator_name: '',
    pump_arrival_time: '',
    setup_start_time: '',
    setup_end_time: '',
    pour_start_time: '',
    planned_end_time: '',
    actual_start_time: '',
    actual_end_time: '',
    status: 'scheduled',
    notes: '',
});

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

const onSiteSelect = () => {
    const s = dropdowns.value.sites.find(item => item.id == form.value.site_id);
    if (s) form.value.site_name = s.name;
};

const onMixSelect = () => {
    const m = dropdowns.value.mixDesigns.find(item => item.id == form.value.mix_design_id);
    if (m) form.value.grade = m.name;
};

const onPumpSelect = () => {
    const p = dropdowns.value.machines.find(item => item.id == form.value.pump_vehicle_id);
    if (p) form.value.pump_no = p.registration;
};

const onOperatorSelect = () => {
    const o = dropdowns.value.operators.find(item => item.id == form.value.operator_id);
    if (o) form.value.operator_name = o.first_name + ' ' + (o.last_name || '');
};

// Automated Status Transitions on Time Entry
const onActualStartInput = () => {
    if (form.value.actual_start_time) {
        if (form.value.actual_end_time) {
            form.value.status = 'completed';
        } else if (!['delayed', 'cancelled'].includes(form.value.status)) {
            // Recording an actual start changes status to In Progress
            form.value.status = 'in_progress';
        }
    }
};

const onActualEndInput = () => {
    if (form.value.actual_end_time) {
        if (!['cancelled'].includes(form.value.status)) {
            // Recording an actual end changes status to Completed
            form.value.status = 'completed';
        }
    }
};

// Modal Openers
const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        schedule_date: filters.value.schedule_date || new Date().toISOString().substring(0, 10),
        pour_reference: '',
        site_id: dropdowns.value.sites[0]?.id || '',
        site_name: dropdowns.value.sites[0]?.name || '',
        pour_location: '',
        mix_design_id: dropdowns.value.mixDesigns[0]?.id || '',
        grade: dropdowns.value.mixDesigns[0]?.name || '',
        planned_qty_m3: 45.0,
        pump_type: 'boom_pump',
        pump_vehicle_id: '',
        pump_no: '',
        boom_length_m: 36.0,
        operator_id: '',
        operator_name: '',
        pump_arrival_time: '',
        setup_start_time: '',
        setup_end_time: '',
        pour_start_time: '',
        planned_end_time: '',
        actual_start_time: '',
        actual_end_time: '',
        status: 'scheduled', // New records default to Scheduled
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
        site_id: item.site_id || '',
        site_name: item.site_name || '',
        pour_location: item.pour_location || '',
        mix_design_id: item.mix_design_id || '',
        grade: item.grade || '',
        planned_qty_m3: item.planned_qty_m3,
        pump_type: item.pump_type || 'boom_pump',
        pump_vehicle_id: item.pump_vehicle_id || '',
        pump_no: item.pump_no || '',
        boom_length_m: item.boom_length_m || 36.0,
        operator_id: item.operator_id || '',
        operator_name: item.operator_name || '',
        pump_arrival_time: item.pump_arrival_time ? item.pump_arrival_time.substring(0, 16) : '',
        setup_start_time: item.setup_start_time ? item.setup_start_time.substring(0, 16) : '',
        setup_end_time: item.setup_end_time ? item.setup_end_time.substring(0, 16) : '',
        pour_start_time: item.pour_start_time ? item.pour_start_time.substring(0, 16) : '',
        planned_end_time: item.planned_end_time ? item.planned_end_time.substring(0, 16) : '',
        actual_start_time: item.actual_start_time ? item.actual_start_time.substring(0, 16) : '',
        actual_end_time: item.actual_end_time ? item.actual_end_time.substring(0, 16) : '',
        notes: item.notes || '',
        status: item.status,
    };
    isModalOpen.value = true;
};

// Form Save with strict Time Validations
const saveDeployment = async () => {
    // Rule 1: Every pour must have a schedule date and pour reference.
    if (!form.value.schedule_date || !form.value.pour_reference?.trim()) {
        Swal.fire('Required Field', 'Every pour must have a schedule date and pour reference.', 'warning');
        return;
    }

    if (!form.value.pour_location?.trim() || !form.value.planned_qty_m3) {
        Swal.fire('Required Fields', 'Please specify Pour Location and Planned Pour Volume.', 'warning');
        return;
    }

    // Rule 2: Every scheduled pour must have a pump type and assigned pump.
    if (!form.value.pump_type || (!form.value.pump_vehicle_id && !form.value.pump_no?.trim())) {
        Swal.fire('Assigned Pump Required', 'Every scheduled pour must have a pump type and assigned pump.', 'warning');
        return;
    }

    // Rule 3 & 4: Boom pump must have boom length. Stationary pump does not require boom length.
    if (form.value.pump_type === 'boom_pump') {
        const boomLen = parseFloat(form.value.boom_length_m);
        if (isNaN(boomLen) || boomLen <= 0) {
            Swal.fire('Boom Length Required', 'A boom pump must have a boom length (in meters).', 'warning');
            return;
        }
    }

    // Time Validation 1: setup_start_time cannot be later than setup_end_time.
    if (form.value.setup_start_time && form.value.setup_end_time) {
        if (new Date(form.value.setup_start_time) > new Date(form.value.setup_end_time)) {
            Swal.fire({
                icon: 'error',
                title: 'Time Validation Error',
                text: 'setup_start_time cannot be later than setup_end_time.',
                confirmButtonColor: '#dc2626'
            });
            return;
        }
    }

    // Time Validation 2: actual_start_time cannot be later than actual_end_time.
    if (form.value.actual_start_time && form.value.actual_end_time) {
        if (new Date(form.value.actual_start_time) > new Date(form.value.actual_end_time)) {
            Swal.fire({
                icon: 'error',
                title: 'Time Validation Error',
                text: 'actual_start_time cannot be later than actual_end_time.',
                confirmButtonColor: '#dc2626'
            });
            return;
        }
    }

    // Note: actual_end_time may be later than planned_end_time; this should not block completion.

    saving.value = true;
    try {
        if (isEditing.value) {
            await axios.put(route('production.pump-deployments.update', editingId.value), form.value);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pump deployment updated', timer: 2000, showConfirmButton: false });
        } else {
            await axios.post(route('production.pump-deployments.store'), form.value);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pump deployment scheduled', timer: 2000, showConfirmButton: false });
        }
        isModalOpen.value = false;
        fetchData();
    } catch (err) {
        console.error('Error saving deployment:', err);
        const errMsg = err.response?.data?.errors
            ? Object.values(err.response.data.errors).flat().join('<br><br>')
            : (err.response?.data?.message || 'Failed to save pump deployment.');

        Swal.fire({
            icon: 'error',
            title: 'Validation / Overlap Conflict',
            html: `<div class="text-left text-xs leading-relaxed">${errMsg}</div>`,
            confirmButtonColor: '#4f46e5'
        });
    } finally {
        saving.value = false;
    }
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
            title: `Status changed to ${nextStatus.replace('_', ' ').toUpperCase()}`,
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
        inputLabel: 'Reason for delay (e.g. site access, weather, slump issue)',
        inputPlaceholder: 'Enter delay notes...',
        showCancelButton: true,
        confirmButtonColor: '#ea580c',
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
        text: `Are you sure you want to cancel pour "${item.pour_reference}"? This requires explicit planner confirmation.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
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
        confirmButtonColor: '#dc2626',
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
            return { label: 'Scheduled', bg: 'bg-slate-100 text-slate-700 border-slate-300 font-semibold' };
        case 'en_route':
            return { label: 'En Route', bg: 'bg-sky-50 text-sky-700 border-sky-300 font-semibold' };
        case 'setup':
            return { label: 'Setup / Rigging', bg: 'bg-amber-50 text-amber-800 border-amber-300 font-semibold' };
        case 'ready':
            return { label: 'Ready & Primed', bg: 'bg-indigo-50 text-indigo-700 border-indigo-300 font-semibold' };
        case 'in_progress':
        case 'pumping':
            return { label: 'In Progress', bg: 'bg-blue-50 text-blue-800 border-blue-300 font-bold' };
        case 'washout':
            return { label: 'Line Washout', bg: 'bg-purple-50 text-purple-700 border-purple-300' };
        case 'completed':
            return { label: 'Completed', bg: 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold' };
        case 'delayed':
            return { label: 'Delayed', bg: 'bg-orange-50 text-orange-700 border-orange-300 font-bold' };
        case 'breakdown':
            return { label: 'Breakdown', bg: 'bg-rose-50 text-rose-700 border-rose-300 font-bold' };
        case 'cancelled':
            return { label: 'Cancelled', bg: 'bg-slate-100 text-slate-500 border-slate-300' };
        default:
            return { label: status, bg: 'bg-slate-100 text-slate-700 border-slate-300' };
    }
};
</script>

<template>
    <AppLayout title="Pump & Boom Deployment Operations">
        <!-- SAP Fiori Quartz Light Shell Frame -->
        <div class="bg-[#f4f6f9] min-h-screen text-[#1d2d3e] font-sans antialiased pb-12">
            
            <!-- Shell Header Bar -->
            <div class="bg-[#1d2d3e] text-white px-6 py-4 shadow flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#2d3e50]">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                        <WrenchScrewdriverIcon class="w-4 h-4 text-white" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] uppercase font-bold text-slate-300 tracking-wider">Concrete Placement Logistics</span>
                            <span class="text-[10px] bg-[#2a3c50] text-sky-300 px-2 py-0.5 rounded font-mono font-semibold">Plant Scoped</span>
                        </div>
                        <h1 class="text-base font-bold tracking-tight text-white mt-0.5">Pour Schedule & Pump Deployment</h1>
                    </div>
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-2">
                    <Link 
                        :href="route('production.batching-schedules.index')" 
                        class="px-3 py-2 bg-[#2a3c50] hover:bg-[#374c63] text-slate-200 rounded text-xs font-semibold flex items-center gap-1.5 transition-colors"
                    >
                        <CalendarIcon class="w-4 h-4 text-sky-400" />
                        <span>Batching Schedules</span>
                    </Link>

                    <button 
                        @click="fetchData" 
                        :disabled="loading"
                        class="p-2 bg-[#2a3c50] hover:bg-[#374c63] text-white rounded text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        title="Refresh Live Data"
                    >
                        <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                    </button>

                    <button 
                        @click="openCreateModal"
                        class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold flex items-center gap-1.5 shadow-sm transition-colors"
                    >
                        <PlusIcon class="w-4 h-4 stroke-[2.5]" />
                        <span>Deploy Pump / Boom</span>
                    </button>
                </div>
            </div>

            <div class="px-6 pt-5 space-y-5">

                <!-- 1. Operational KPI Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-6 gap-3.5">
                    <div class="bg-white rounded-lg p-3.5 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider block">Total Deployments</span>
                        <div class="mt-1.5 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-slate-800">{{ metrics.total_deployments }}</span>
                            <span class="text-xs font-bold text-slate-400">Rigs</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg p-3.5 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-indigo-600 tracking-wider block">Planned Placement</span>
                        <div class="mt-1.5 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-indigo-700">{{ metrics.total_planned_m3 }}</span>
                            <span class="text-xs font-bold text-indigo-600">m³</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg p-3.5 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-blue-600 tracking-wider block">In Progress</span>
                        <div class="mt-1.5 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-blue-700">{{ metrics.active_pumping_count }}</span>
                            <span class="text-xs font-bold text-blue-600">Active</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg p-3.5 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-amber-600 tracking-wider block">Setup & Priming</span>
                        <div class="mt-1.5 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-amber-700">{{ metrics.setup_in_progress }}</span>
                            <span class="text-xs font-bold text-amber-600">Pumps</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg p-3.5 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-emerald-600 tracking-wider block">Completed Pours</span>
                        <div class="mt-1.5 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-emerald-700">{{ metrics.completed_deployments }}</span>
                            <span class="text-xs font-bold text-emerald-600">Pours</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg p-3.5 border border-slate-200 shadow-sm">
                        <span class="text-[10px] font-bold uppercase text-orange-600 tracking-wider block">Delayed Pours</span>
                        <div class="mt-1.5 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-orange-700">{{ metrics.delayed_count }}</span>
                            <span class="text-xs font-bold text-orange-600">Alerts</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Main Screen Pour Schedule Table with 7 Filters -->
                <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    
                    <!-- 7 Operational Filter Bar -->
                    <div class="p-4 border-b border-slate-200 bg-slate-50/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <FunnelIcon class="w-4 h-4 text-indigo-600" />
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Schedule View Filters</span>
                            </div>
                            <button 
                                @click="resetFilters" 
                                class="text-[11px] font-bold text-slate-500 hover:text-indigo-600 transition-colors flex items-center gap-1"
                            >
                                <ArrowPathIcon class="w-3.5 h-3.5" />
                                <span>Reset All Filters</span>
                            </button>
                        </div>

                        <!-- Filter Grid: 7 filters strictly covered -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2.5">
                            
                            <!-- 1. Schedule Date Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Schedule Date</label>
                                <input 
                                    type="date" 
                                    v-model="filters.schedule_date" 
                                    @change="fetchData"
                                    class="w-full text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600"
                                />
                            </div>

                            <!-- 2. Site Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Site</label>
                                <select 
                                    v-model="filters.site_id" 
                                    @change="fetchData"
                                    class="w-full text-xs text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600"
                                >
                                    <option value="all">All Sites</option>
                                    <option v-for="site in dropdowns.sites" :key="site.id" :value="site.id">
                                        {{ site.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- 3. Pour Location Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pour Location</label>
                                <input 
                                    type="text" 
                                    v-model="filters.pour_location" 
                                    @input="onLocationSearchInput"
                                    placeholder="e.g. Raft, Slab, Column..." 
                                    class="w-full text-xs text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600"
                                />
                            </div>

                            <!-- 4. Pump Type Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pump Type</label>
                                <select 
                                    v-model="filters.pump_type" 
                                    @change="fetchData"
                                    class="w-full text-xs text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600"
                                >
                                    <option value="all">All Pump Types</option>
                                    <option value="boom_pump">Boom Pump</option>
                                    <option value="line_pump">Line Pump</option>
                                    <option value="stationary_pump">Stationary Pump</option>
                                    <option value="crane_bucket">Crane & Bucket</option>
                                    <option value="direct_pour">Direct Chute</option>
                                </select>
                            </div>

                            <!-- 5. Pump Number Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pump Number</label>
                                <select 
                                    v-model="filters.pump_no" 
                                    @change="fetchData"
                                    class="w-full text-xs text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600"
                                >
                                    <option value="all">All Pumps</option>
                                    <option v-for="m in dropdowns.machines" :key="m.id" :value="m.registration">
                                        {{ m.registration }} ({{ m.vehicle_model || 'Rig' }})
                                    </option>
                                </select>
                            </div>

                            <!-- 6. Operator Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Operator</label>
                                <select 
                                    v-model="filters.operator_id" 
                                    @change="fetchData"
                                    class="w-full text-xs text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600"
                                >
                                    <option value="all">All Operators</option>
                                    <option v-for="op in dropdowns.operators" :key="op.id" :value="op.id">
                                        {{ op.first_name }} {{ op.last_name || '' }}
                                    </option>
                                </select>
                            </div>

                            <!-- 7. Status Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status</label>
                                <select 
                                    v-model="filters.status" 
                                    @change="fetchData"
                                    class="w-full text-xs text-slate-700 bg-white border border-slate-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-600 font-semibold"
                                >
                                    <option value="all">All Statuses</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="setup">Setup</option>
                                    <option value="ready">Ready</option>
                                    <option value="completed">Completed</option>
                                    <option value="delayed">Delayed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                        </div>

                        <!-- Status Quick-Filter Pills -->
                        <div class="flex items-center gap-1.5 pt-1 overflow-x-auto whitespace-nowrap">
                            <span class="text-[10px] font-bold text-slate-400 uppercase mr-1">Quick Status:</span>
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
                                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold transition-all border"
                                :class="filters.status === st.id ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border-slate-200'"
                            >
                                {{ st.label }}
                            </button>
                        </div>
                    </div>

                    <!-- PRIMARY OPERATIONAL VIEW TABLE -->
                    <!-- Sequence: What pour → Where → When → Quantity → Which pump → Which operator → Current status -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3 px-3.5 text-center w-12">#</th>
                                    <th class="py-3 px-3.5">1. What Pour</th>
                                    <th class="py-3 px-3.5">2. Where (Site & Location)</th>
                                    <th class="py-3 px-3.5">3. When (Date & Timelines)</th>
                                    <th class="py-3 px-3.5 text-right">4. Quantity</th>
                                    <th class="py-3 px-3.5">5. Which Pump</th>
                                    <th class="py-3 px-3.5">6. Which Operator</th>
                                    <th class="py-3 px-3.5 text-center">7. Current Status</th>
                                    <th class="py-3 px-3.5 text-right">Operational Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr v-if="filteredDeployments.length === 0">
                                    <td colspan="9" class="py-14 text-center text-slate-400 italic">
                                        No pour deployments matching the selected filters. Click "Deploy Pump / Boom" to schedule one.
                                    </td>
                                </tr>

                                <tr 
                                    v-for="item in filteredDeployments" 
                                    :key="item.id"
                                    class="hover:bg-slate-50/90 transition-colors"
                                    :class="{
                                        'bg-orange-50/30': item.status === 'delayed',
                                        'opacity-60 bg-slate-50/50': item.status === 'cancelled'
                                    }"
                                >
                                    <!-- ID -->
                                    <td class="py-3.5 px-3.5 text-center font-bold text-slate-500">
                                        #{{ item.id }}
                                    </td>

                                    <!-- 1. WHAT POUR -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                            <span>{{ item.pour_reference }}</span>
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1 text-[11px]">
                                            <span class="font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">
                                                {{ item.grade || item.mix_design?.name || 'Standard Mix' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- 2. WHERE (Site & Location) -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="font-bold text-slate-800 flex items-center gap-1">
                                            <MapPinIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                            <span>{{ item.site_name || item.site?.name || 'Unspecified Site' }}</span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 font-semibold mt-0.5 pl-4.5">
                                            {{ item.pour_location }}
                                        </div>
                                    </td>

                                    <!-- 3. WHEN (Date & Timelines) -->
                                    <td class="py-3.5 px-3.5 text-[11px]">
                                        <div class="font-bold text-slate-800 flex items-center gap-1">
                                            <CalendarIcon class="w-3.5 h-3.5 text-slate-400" />
                                            <span>{{ item.schedule_date }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 mt-1 text-[10px] text-slate-500">
                                            <div>Target: <strong class="text-slate-700">{{ formatTime(item.pour_start_time) }}</strong></div>
                                            <div>Plan End: <strong class="text-slate-700">{{ formatTime(item.planned_end_time) }}</strong></div>
                                            <div>Act Start: <strong class="text-blue-700 font-bold">{{ formatTime(item.actual_start_time) }}</strong></div>
                                            <div>Act End: <strong class="text-emerald-700 font-bold">{{ formatTime(item.actual_end_time) }}</strong></div>
                                        </div>
                                    </td>

                                    <!-- 4. QUANTITY -->
                                    <td class="py-3.5 px-3.5 text-right">
                                        <div class="font-black text-indigo-700 text-sm">
                                            {{ item.planned_qty_m3 }} <span class="text-[10px] text-slate-400 font-normal">m³</span>
                                        </div>
                                        <div v-if="item.pumping_rate_m3_per_hour" class="text-[10px] text-teal-700 font-semibold">
                                            ~{{ item.pumping_rate_m3_per_hour }} m³/h
                                        </div>
                                    </td>

                                    <!-- 5. WHICH PUMP -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="flex items-center gap-1.5">
                                            <span 
                                                class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border"
                                                :class="item.pump_type === 'boom_pump' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : (item.pump_type === 'stationary_pump' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-amber-50 text-amber-800 border-amber-200')"
                                            >
                                                {{ item.pump_type === 'boom_pump' ? 'Boom' : (item.pump_type === 'stationary_pump' ? 'Stationary' : (item.pump_type === 'line_pump' ? 'Line' : item.pump_type)) }}
                                            </span>
                                            <strong class="text-slate-800">{{ item.pump_no || item.pump_machine?.registration || 'TBD' }}</strong>
                                        </div>
                                        <div v-if="item.boom_length_m" class="text-[10px] text-slate-500 mt-0.5">
                                            Reach / Line: <strong class="text-slate-700">{{ item.boom_length_m }}m</strong>
                                        </div>
                                    </td>

                                    <!-- 6. WHICH OPERATOR -->
                                    <td class="py-3.5 px-3.5">
                                        <div class="font-bold text-slate-700 flex items-center gap-1">
                                            <UserIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                            <span>{{ item.operator_name || (item.operator ? item.operator.first_name + ' ' + (item.operator.last_name || '') : 'Unassigned') }}</span>
                                        </div>
                                        <div v-if="item.operator?.phone" class="text-[10px] text-slate-400 pl-4.5">
                                            {{ item.operator.phone }}
                                        </div>
                                    </td>

                                    <!-- 7. CURRENT STATUS -->
                                    <td class="py-3.5 px-3.5 text-center">
                                        <span 
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold border inline-block"
                                            :class="getStatusBadge(item.status).bg"
                                        >
                                            {{ getStatusBadge(item.status).label }}
                                        </span>
                                    </td>

                                    <!-- OPERATIONAL ACTIONS -->
                                    <td class="py-3.5 px-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            
                                            <!-- Action: Advance to Setup -->
                                            <button 
                                                v-if="item.status === 'scheduled'" 
                                                @click="transitionStatus(item, 'setup')"
                                                class="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded text-[10px] transition-colors"
                                                title="Start Rigging & Setup"
                                            >
                                                Start Setup
                                            </button>

                                            <!-- Action: Advance to Ready -->
                                            <button 
                                                v-else-if="item.status === 'setup'" 
                                                @click="transitionStatus(item, 'ready')"
                                                class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded text-[10px] transition-colors"
                                                title="Mark Setup Finished & Ready"
                                            >
                                                Mark Ready
                                            </button>

                                            <!-- Action: Start Pouring -> Status becomes In Progress -->
                                            <button 
                                                v-else-if="['ready', 'scheduled'].includes(item.status)" 
                                                @click="transitionStatus(item, 'in_progress')"
                                                class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded text-[10px] transition-colors flex items-center gap-1"
                                                title="Record Actual Start (Changes status to In Progress)"
                                            >
                                                <PlayIcon class="w-3 h-3" />
                                                <span>Start Pour</span>
                                            </button>

                                            <!-- Action: Complete Pour -> Status becomes Completed -->
                                            <button 
                                                v-else-if="['in_progress', 'pumping'].includes(item.status)" 
                                                @click="transitionStatus(item, 'completed')"
                                                class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded text-[10px] transition-colors flex items-center gap-1"
                                                title="Record Actual End (Changes status to Completed)"
                                            >
                                                <StopIcon class="w-3 h-3" />
                                                <span>Complete</span>
                                            </button>

                                            <!-- Explicit Delay Action -->
                                            <button 
                                                v-if="!['completed', 'cancelled', 'delayed'].includes(item.status)"
                                                @click="markDelayed(item)"
                                                class="px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-800 font-bold rounded text-[10px] transition-colors"
                                                title="Explicitly Mark as Delayed"
                                            >
                                                Delay
                                            </button>

                                            <!-- Explicit Cancel Action -->
                                            <button 
                                                v-if="!['completed', 'cancelled'].includes(item.status)"
                                                @click="markCancelled(item)"
                                                class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded text-[10px] transition-colors"
                                                title="Explicitly Cancel Pour"
                                            >
                                                Cancel
                                            </button>

                                            <!-- Edit -->
                                            <button 
                                                @click="openEditModal(item)"
                                                class="p-1 hover:bg-slate-200 text-slate-600 rounded transition-colors"
                                                title="Edit Deployment Schedule"
                                            >
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </button>

                                            <!-- Delete -->
                                            <button 
                                                @click="deleteDeployment(item)"
                                                class="p-1 hover:bg-rose-100 text-rose-600 rounded transition-colors"
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

            <!-- MODAL: Create / Edit Pump & Boom Deployment -->
            <div 
                v-if="isModalOpen" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto"
            >
                <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full border border-slate-200 my-8 overflow-hidden text-xs">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-[#1d2d3e] text-white flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-300 tracking-wider">
                                {{ isEditing ? 'Edit Pour Deployment' : 'New Pour Schedule & Pump Deployment' }}
                            </span>
                            <h2 class="text-base font-bold text-white mt-0.5">
                                {{ isEditing ? `Deployment #${editingId} - ${form.pour_reference}` : 'Schedule Pour & Assign Pump' }}
                            </h2>
                        </div>
                        <button @click="isModalOpen = false" class="text-slate-400 hover:text-white p-1">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Modal Body Form -->
                    <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">

                        <!-- Real-Time Time Validation Warnings / Advisories -->
                        <div v-if="form.setup_start_time && form.setup_end_time && new Date(form.setup_start_time) > new Date(form.setup_end_time)" 
                             class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 font-bold flex items-center gap-2">
                            <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 shrink-0" />
                            <span>Time Validation Error: setup_start_time cannot be later than setup_end_time.</span>
                        </div>

                        <div v-if="form.actual_start_time && form.actual_end_time && new Date(form.actual_start_time) > new Date(form.actual_end_time)" 
                             class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 font-bold flex items-center gap-2">
                            <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 shrink-0" />
                            <span>Time Validation Error: actual_start_time cannot be later than actual_end_time.</span>
                        </div>

                        <div v-if="form.pump_arrival_time && form.setup_start_time && new Date(form.pump_arrival_time) > new Date(form.setup_start_time)" 
                             class="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 font-medium flex items-center gap-2">
                            <InformationCircleIcon class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Operational Advisory: Pump arrival should normally be before setup start.</span>
                        </div>

                        <div v-if="form.setup_end_time && form.actual_start_time && new Date(form.setup_end_time) > new Date(form.actual_start_time)" 
                             class="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 font-medium flex items-center gap-2">
                            <InformationCircleIcon class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Operational Advisory: Setup should normally finish before actual pour start.</span>
                        </div>

                        <!-- Row 1: Schedule Date & Pour Reference -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Schedule Date *</label>
                                <input type="date" v-model="form.schedule_date" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs font-semibold focus:ring-1 focus:ring-indigo-600" required />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Pour Reference / Order Ref *</label>
                                <input type="text" v-model="form.pour_reference" placeholder="e.g. POUR-2026-0908-01" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs font-semibold focus:ring-1 focus:ring-indigo-600" required />
                            </div>
                        </div>

                        <!-- Row 2: Destination Site & Pour Location -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Delivery Destination Site *</label>
                                <select v-model="form.site_id" @change="onSiteSelect" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-indigo-600">
                                    <option value="">Select Destination Site</option>
                                    <option v-for="site in dropdowns.sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Pour Location / Structural Element *</label>
                                <input type="text" v-model="form.pour_location" placeholder="e.g. Raft Foundation Grid A-D, 3rd Floor Deck" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-indigo-600" required />
                            </div>
                        </div>

                        <!-- Row 3: Mix Design & Quantity -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Concrete Grade / Recipe</label>
                                <select v-model="form.mix_design_id" @change="onMixSelect" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs focus:ring-1 focus:ring-indigo-600">
                                    <option value="">Select Grade</option>
                                    <option v-for="mix in dropdowns.mixDesigns" :key="mix.id" :value="mix.id">{{ mix.name }} ({{ mix.code || '-' }})</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Planned Pour Volume (m³) *</label>
                                <input type="number" step="0.5" v-model="form.planned_qty_m3" placeholder="e.g. 45.0" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs font-bold text-indigo-700" required />
                            </div>
                        </div>

                        <!-- Row 4: Pump Rig, Boom Reach & Type -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-indigo-50/60 p-3.5 rounded-lg border border-indigo-100">
                            <div>
                                <label class="block font-bold text-indigo-900 mb-1">Pump Type *</label>
                                <select v-model="form.pump_type" class="w-full bg-white border border-indigo-200 rounded p-2 text-xs font-semibold text-indigo-900">
                                    <option value="boom_pump">Boom Pump (Articulated)</option>
                                    <option value="line_pump">Line Pump (Ground Pipeline)</option>
                                    <option value="stationary_pump">Stationary High-Rise Pump</option>
                                    <option value="crane_bucket">Crane & Bucket Pour</option>
                                    <option value="direct_pour">Direct Chute Discharge</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-indigo-900 mb-1">Assigned Pump Rig *</label>
                                <select v-model="form.pump_vehicle_id" @change="onPumpSelect" class="w-full bg-white border border-indigo-200 rounded p-2 text-xs font-semibold" required>
                                    <option value="">-- Select Assigned Pump Machine --</option>
                                    <option v-for="m in dropdowns.machines" :key="m.id" :value="m.id">
                                        {{ m.registration }} ({{ m.vehicle_model || 'Pump' }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-indigo-900 mb-1">
                                    {{ form.pump_type === 'boom_pump' ? 'Boom Length (m) *' : (form.pump_type === 'stationary_pump' ? 'Stationary Line (m) (Optional)' : 'Pipeline Length (m) (Optional)') }}
                                </label>
                                <input 
                                    type="number" 
                                    step="1" 
                                    v-model="form.boom_length_m" 
                                    :placeholder="form.pump_type === 'boom_pump' ? 'e.g. 36.0 (Required)' : 'e.g. 100.0 (Optional)'" 
                                    :required="form.pump_type === 'boom_pump'"
                                    class="w-full bg-white border border-indigo-200 rounded p-2 text-xs font-bold text-indigo-900" 
                                />
                            </div>
                        </div>

                        <!-- Row 5: Operator & Current Status -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Pump Operator / Crew</label>
                                <select v-model="form.operator_id" @change="onOperatorSelect" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs">
                                    <option value="">Assign Later</option>
                                    <option v-for="o in dropdowns.operators" :key="o.id" :value="o.id">
                                        {{ o.first_name }} {{ o.last_name || '' }} ({{ o.phone || o.employee_code }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Deployment Status</label>
                                <select v-model="form.status" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs font-bold text-slate-800">
                                    <option value="scheduled">Scheduled (Default for new records)</option>
                                    <option value="in_progress">In Progress (Recording Actual Start)</option>
                                    <option value="setup">Setup (Rigging / Outriggers)</option>
                                    <option value="ready">Ready (Primed & Prepared)</option>
                                    <option value="completed">Completed (Recording Actual End)</option>
                                    <option value="delayed">Delayed (Requires Explicit Change)</option>
                                    <option value="cancelled">Cancelled (Requires Explicit Change)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 6: Detailed Operational Timelines Section -->
                        <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">
                                Operational Milestone Timelines
                            </span>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Pump Arrival at Site</label>
                                    <input type="datetime-local" v-model="form.pump_arrival_time" class="w-full bg-white border border-slate-300 rounded p-1.5 text-xs" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Setup Start Time</label>
                                    <input type="datetime-local" v-model="form.setup_start_time" class="w-full bg-white border border-slate-300 rounded p-1.5 text-xs" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Setup End Time</label>
                                    <input type="datetime-local" v-model="form.setup_end_time" class="w-full bg-white border border-slate-300 rounded p-1.5 text-xs" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Target Pour Start Time</label>
                                    <input type="datetime-local" v-model="form.pour_start_time" class="w-full bg-white border border-slate-300 rounded p-1.5 text-xs" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Planned End Time</label>
                                    <input type="datetime-local" v-model="form.planned_end_time" class="w-full bg-white border border-slate-300 rounded p-1.5 text-xs" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-blue-50/50 p-2.5 rounded border border-blue-100">
                                <div>
                                    <label class="block text-[10px] font-bold text-blue-900 mb-1">
                                        Actual Start Time <span class="text-[9px] font-normal text-blue-600">(Sets status to In Progress)</span>
                                    </label>
                                    <input 
                                        type="datetime-local" 
                                        v-model="form.actual_start_time" 
                                        @change="onActualStartInput"
                                        class="w-full bg-white border border-blue-200 rounded p-1.5 text-xs font-semibold text-blue-900" 
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-emerald-900 mb-1">
                                        Actual End Time <span class="text-[9px] font-normal text-emerald-600">(Sets status to Completed)</span>
                                    </label>
                                    <input 
                                        type="datetime-local" 
                                        v-model="form.actual_end_time" 
                                        @change="onActualEndInput"
                                        class="w-full bg-white border border-emerald-200 rounded p-1.5 text-xs font-semibold text-emerald-900" 
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Site Access, Rigging & Overhead Wire Notes</label>
                            <textarea v-model="form.notes" rows="2" placeholder="e.g. 8m outrigger footprint clear, overhead high-tension wire 15m away, priming with 2 bags cement slurry..." class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs"></textarea>
                        </div>

                    </div>

                    <!-- Modal Actions -->
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                        <button @click="isModalOpen = false" class="px-4 py-2 border border-slate-300 text-slate-700 rounded text-xs font-bold hover:bg-slate-100 transition-colors">
                            Cancel
                        </button>
                        <button @click="saveDeployment" :disabled="saving" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold shadow-sm transition-colors flex items-center gap-1.5">
                            <span v-if="saving" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ isEditing ? 'Save Changes' : 'Confirm Deployment' }}</span>
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </AppLayout>
</template>
