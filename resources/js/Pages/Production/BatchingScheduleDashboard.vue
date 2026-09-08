<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BatchingScheduleForm from './components/BatchingScheduleForm.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import {
    TruckIcon,
    ArrowPathIcon,
    PlusIcon,
    ClockIcon,
    MapPinIcon,
    FunnelIcon,
    CalendarIcon,
    WrenchScrewdriverIcon,
    PaperAirplaneIcon,
    PencilSquareIcon,
    TrashIcon,
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
    { label: 'All Sites', value: 'all' },
    ...(dropdowns.value.sites || []).map(s => ({ label: s.name, value: s.id }))
]);

const statusFilterOptions = [
    { label: 'All Statuses', value: 'all' },
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'Batching', value: 'batching' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'On Site', value: 'on_site' },
    { label: 'Pouring', value: 'pouring' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

let pollTimer = null;
let searchDebounceTimer = null;

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
        metrics.value = res.data.metrics || metrics.value;
    } catch (err) {
        console.error('Error fetching schedules:', err);
    } finally {
        loading.value = false;
    }
};

const onPourSearchInput = () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        fetchData();
    }, 400);
};

const resetFilters = () => {
    scheduleDate.value = new Date().toISOString().substring(0, 10);
    siteFilter.value = 'all';
    statusFilter.value = 'all';
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
        case 'batching':
            return { label: 'Batching', severity: 'warn' };
        case 'in_transit':
            return { label: 'In Transit', severity: 'info' };
        case 'on_site':
            return { label: 'On Site', severity: 'info' };
        case 'pouring':
            return { label: 'Pouring', severity: 'info' };
        case 'completed':
            return { label: 'Completed', severity: 'success' };
        case 'cancelled':
            return { label: 'Cancelled', severity: 'danger' };
        default:
            return { label: status, severity: 'secondary' };
    }
};

const getRowClass = (data) => {
    if (!data) return '';
    if (data.status === 'cancelled') return 'opacity-60 bg-gray-50/40 dark:bg-gray-800/40';
    return '';
};
</script>

<template>
    <AppLayout title="Batching & Dispatch Schedules">
        <div class="py-2 px-2 sm:px-4 w-full">
            <ModuleSubTopNav />

            <div class="w-full mt-3 space-y-3">
                
                <!-- Main Header Card in Indigo Theme -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                            <TruckIcon class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Logistics & Batching Operations
                                </span>
                                <span class="text-[9px] bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 px-1.5 py-0.2 rounded font-mono font-semibold border border-indigo-100 dark:border-indigo-900">
                                    Plant Active
                                </span>
                            </div>
                            <h1 class="text-sm sm:text-base font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                                Concrete Batching Schedules & Dispatches
                            </h1>
                        </div>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link 
                            :href="route('production.pump-deployments.index')" 
                            class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
                        >
                            <WrenchScrewdriverIcon class="w-3.5 h-3.5 text-indigo-600" />
                            <span>Pump Deployments</span>
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
                            <span>Schedule Trip</span>
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
                <div v-else class="space-y-3">
                    
                    <!-- 1. KPI Metric Summary Bar -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-gray-400 dark:text-gray-500 tracking-wider block">Total Scheduled</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-gray-900 dark:text-gray-100">{{ metrics.total_scheduled_m3 }}</span>
                                <span class="text-[10px] font-semibold text-gray-400">m³</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-emerald-600 dark:text-emerald-400 tracking-wider block">Delivered Volume</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ metrics.total_delivered_m3 }}</span>
                                <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">m³</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-sky-600 dark:text-sky-400 tracking-wider block">In Transit</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-sky-600 dark:text-sky-400">{{ metrics.active_in_transit_tms }}</span>
                                <span class="text-[10px] font-semibold text-sky-600 dark:text-sky-400">Trucks</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs">
                            <span class="text-[9px] font-bold uppercase text-teal-600 dark:text-teal-400 tracking-wider block">Actively Pouring</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black text-teal-600 dark:text-teal-400">{{ metrics.active_pouring_tms }}</span>
                                <span class="text-[10px] font-semibold text-teal-600 dark:text-teal-400">Trucks</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 shadow-xs col-span-2 sm:col-span-1">
                            <span class="text-[9px] font-bold uppercase text-amber-600 dark:text-amber-400 tracking-wider block">Hydration Alert</span>
                            <div class="mt-0.5 flex items-baseline justify-between">
                                <span class="text-lg font-black" :class="metrics.hydration_warning_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100'">
                                    {{ metrics.hydration_warning_count }}
                                </span>
                                <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400">>90 Mins</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Granular Batching Trips Schedule Table -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs overflow-hidden text-xs">
                        
                        <!-- Operational Filter Bar -->
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

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <!-- Date Filter -->
                                <div>
                                    <BaseInput 
                                        type="date" 
                                        v-model="scheduleDate" 
                                        label="Date"
                                        @update:modelValue="fetchData"
                                    />
                                </div>
                                    
                                <!-- Site Filter -->
                                <div>
                                    <BaseSelect 
                                        v-model="siteFilter" 
                                        :options="siteFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Site"
                                        @change="fetchData"
                                    />
                                </div>

                                <!-- Status Filter -->
                                <div>
                                    <BaseSelect 
                                        v-model="statusFilter" 
                                        :options="statusFilterOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        label="Status"
                                        @change="fetchData"
                                    />
                                </div>

                                <!-- Text search -->
                                <div>
                                    <BaseInput 
                                        type="text" 
                                        v-model="pourSearch" 
                                        label="Search"
                                        placeholder="Pour Ref, Mix..." 
                                        @update:modelValue="onPourSearchInput"
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
                                        { id: 'batching', label: 'Batching' },
                                        { id: 'in_transit', label: 'In Transit' },
                                        { id: 'on_site', label: 'On Site' },
                                        { id: 'pouring', label: 'Pouring' },
                                        { id: 'completed', label: 'Completed' },
                                        { id: 'cancelled', label: 'Cancelled' }
                                    ]"
                                    :key="st.id"
                                    @click="statusFilter = st.id; fetchData()"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold transition-all border"
                                    :class="statusFilter === st.id ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border-gray-200 dark:border-gray-600'"
                                >
                                    {{ st.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Data Table using BaseDataTable -->
                        <div class="w-full">
                            <BaseDataTable
                                :value="filteredSchedules"
                                :loading="loading"
                                dataKey="id"
                                :paginator="true"
                                :rows="20"
                                :rowsPerPageOptions="[10, 20, 50, 100]"
                                :showSerial="true"
                                :rowClass="getRowClass"
                                class="text-xs"
                            >
                                <Column field="pour_reference" header="Trip / Pour Ref" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100 text-xs">
                                            {{ data.pour_reference }}
                                        </div>
                                        <div v-if="data.dispatch?.dispatch_no || data.dispatch_no" class="mt-0.5">
                                            <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900 font-mono">
                                                Dispatch: #{{ data.dispatch?.dispatch_no || data.dispatch_no }}
                                            </span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="site.name" header="Destination Site" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200 text-xs flex items-center gap-1">
                                            <MapPinIcon class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                            <span class="truncate max-w-[150px]">{{ data.site?.name || 'Unassigned Site' }}</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="mix_design.name" header="Recipe & Grade" :sortable="true">
                                    <template #body="{ data }">
                                        <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900">
                                            {{ data.mix_design?.name || 'Standard Mix' }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="qty_m3" header="Volume" :sortable="true" align="right" headerClass="text-right">
                                    <template #body="{ data }">
                                        <span class="font-bold text-gray-900 dark:text-gray-100 text-xs">
                                            {{ Number(data.qty_m3 || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 }) }}
                                        </span>
                                        <span class="text-[9px] font-normal text-gray-500"> m³</span>
                                    </template>
                                </Column>

                                <Column field="vehicle.registration" header="Transit Mixer & Driver" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-1">
                                            <TruckIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                            <span class="font-semibold text-gray-800 dark:text-gray-200 text-xs whitespace-nowrap">
                                                {{ data.vehicle?.registration || 'Fleet TBD' }}
                                            </span>
                                        </div>
                                        <div v-if="data.driver" class="text-[10px] text-gray-400 pl-4.5">
                                            {{ data.driver.first_name }} {{ data.driver.last_name || '' }}
                                        </div>
                                    </template>
                                </Column>

                                <Column field="batching_time" header="Batch & ETA" :sortable="true">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-1 text-[11px] text-gray-700 dark:text-gray-300 font-medium">
                                            <ClockIcon class="w-3 h-3 text-gray-400 shrink-0" />
                                            <span>Batch: {{ formatTime(data.batching_time) }}</span>
                                        </div>
                                        <div v-if="data.eta_site" class="text-[10px] text-gray-500 dark:text-gray-400 pl-4">
                                            ETA: {{ formatTime(data.eta_site) }}
                                        </div>
                                    </template>
                                </Column>

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

                                <Column header="Actions" align="right" headerClass="text-right" style="width: 100px">
                                    <template #body="{ data }">
                                        <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                            <!-- Dispatch Ticket Generator -->
                                            <button 
                                                v-if="!data.dispatch_id && ['in_transit', 'on_site', 'pouring', 'completed'].includes(data.status)"
                                                @click="createDispatch(data)"
                                                class="p-1 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded transition-colors"
                                                title="Generate Dispatch Ticket"
                                            >
                                                <PaperAirplaneIcon class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Edit -->
                                            <button 
                                                @click="openEditForm(data)"
                                                class="p-1 text-gray-500 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                                title="Edit Schedule"
                                            >
                                                <PencilSquareIcon class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Delete -->
                                            <button 
                                                @click="deleteSchedule(data)"
                                                class="p-1 text-gray-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded transition-colors"
                                                title="Delete Schedule"
                                            >
                                                <TrashIcon class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-10 flex flex-col items-center justify-center text-gray-400">
                                        <TruckIcon class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" />
                                        <span class="font-medium text-xs">No batching schedules matching the selected filters. Click "Schedule Trip" to add one.</span>
                                    </div>
                                </template>
                            </BaseDataTable>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AppLayout>
</template>
