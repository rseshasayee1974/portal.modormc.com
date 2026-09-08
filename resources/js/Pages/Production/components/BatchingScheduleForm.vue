<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import {
    TruckIcon,
    ArrowLeftIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    isEditing: {
        type: Boolean,
        default: false,
    },
    initialData: {
        type: Object,
        default: null,
    },
    dropdowns: {
        type: Object,
        default: () => ({
            sites: [],
            mixDesigns: [],
            vehicles: [],
            drivers: [],
            salesOrders: [],
            dispatches: [],
            pumpTypes: []
        }),
    },
    defaultScheduleDate: {
        type: String,
        default: () => new Date().toISOString().substring(0, 10),
    },
});

const emit = defineEmits(['saved', 'cancel']);

const saving = ref(false);

const form = ref({
    schedule_date: props.defaultScheduleDate,
    pour_reference: '',
    site_id: null,
    mix_design_id: null,
    qty_m3: 6.0,
    order_volume_m3: 30.0,
    vehicle_id: null,
    driver_id: null,
    pump_type: 'boom_pump',
    pump_vehicle_id: null,
    sales_order_id: null,
    dispatch_id: null,
    batching_time: '',
    dispatch_time: '',
    eta_site: '',
    unloading_start: '',
    unloading_end: '',
    status: 'scheduled',
    notes: '',
});

// Dropdown options
const siteOptions = computed(() => props.dropdowns.sites?.map(s => ({ label: s.name, value: s.id })) || []);
const mixOptions = computed(() => props.dropdowns.mixDesigns?.map(m => ({ label: `${m.design_name || m.name} (${m.design_code || m.code || '-'})`, value: m.id })) || []);
const vehicleOptions = computed(() => props.dropdowns.vehicles?.map(v => ({ label: `${v.registration} (${v.vehicle_model || v.capacity + ' m³' || 'TM'})`, value: v.id })) || []);
const driverOptions = computed(() => props.dropdowns.drivers?.map(d => ({ label: `${d.first_name} ${d.last_name || ''} (${d.mobile || d.employee_code || 'Staff'})`, value: d.id })) || []);
const pumpVehicleOptions = computed(() => [
    { label: 'Direct Pour / No Pump', value: null },
    ...(props.dropdowns.vehicles?.map(v => ({ label: `${v.registration} (${v.vehicle_model || 'Pump'})`, value: v.id })) || [])
]);

const dispatchOptions = computed(() => [
    { label: '-- Manual Scheduling (No Ticket Linked) --', value: null },
    ...(props.dropdowns.dispatches || []).map(d => ({
        label: d.label || `${d.full_number} (${d.site_name || 'Site'})`,
        value: d.id,
        raw: d
    }))
]);

const selectedDispatch = computed(() => {
    if (!form.value.dispatch_id) return null;
    return (props.dropdowns.dispatches || []).find(d => d.id === form.value.dispatch_id);
});

const statusOptions = [
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'Batching', value: 'batching' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'On Site', value: 'on_site' },
    { label: 'Pouring', value: 'pouring' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

const normalizePumpType = (raw) => {
    if (!raw) return 'boom_pump';
    const s = String(raw).trim().toLowerCase().replace(/\s+/g, '_');
    if (s.includes('boom')) return 'boom_pump';
    if (s.includes('line')) return 'line_pump';
    if (s.includes('stationary') || s.includes('static')) return 'stationary_pump';
    if (s.includes('crane') || s.includes('bucket')) return 'crane_bucket';
    if (s.includes('direct') || s.includes('chute')) return 'direct_pour';
    return s;
};

const onStatusChange = () => {
    const nowStr = new Date().toISOString().substring(0, 16);
    if (form.value.status === 'batching' && !form.value.batching_time) {
        form.value.batching_time = nowStr;
    } else if (form.value.status === 'in_transit') {
        if (!form.value.dispatch_time) form.value.dispatch_time = nowStr;
        if (!form.value.eta_site) {
            const eta = new Date(Date.now() + 35 * 60000);
            form.value.eta_site = eta.toISOString().substring(0, 16);
        }
    } else if (form.value.status === 'on_site') {
        if (!form.value.eta_site) form.value.eta_site = nowStr;
    } else if (form.value.status === 'pouring' && !form.value.unloading_start) {
        form.value.unloading_start = nowStr;
    } else if (form.value.status === 'completed' && !form.value.unloading_end) {
        form.value.unloading_end = nowStr;
    }
};

const onDispatchSelected = (dispatchId) => {
    if (!dispatchId) {
        form.value.dispatch_id = null;
        return;
    }
    const disp = (props.dropdowns.dispatches || []).find(d => d.id === dispatchId);
    if (!disp) return;

    form.value.dispatch_id = disp.id;
    if (disp.sales_order_id) form.value.sales_order_id = Number(disp.sales_order_id);
    if (disp.site_id) form.value.site_id = Number(disp.site_id);
    if (disp.mix_design_id) form.value.mix_design_id = Number(disp.mix_design_id);
    if (disp.vehicle_id) form.value.vehicle_id = Number(disp.vehicle_id);
    if (disp.driver_id) form.value.driver_id = Number(disp.driver_id);
    if (disp.pump_vehicle_id) {
        form.value.pump_vehicle_id = Number(disp.pump_vehicle_id);
        form.value.pump_type = 'boom_pump';
    } else if (disp.pump_type) {
        form.value.pump_type = normalizePumpType(disp.pump_type);
    }
    if (disp.qty_m3) form.value.qty_m3 = parseFloat(disp.qty_m3);
    if (disp.order_volume_m3) form.value.order_volume_m3 = parseFloat(disp.order_volume_m3);
    if (disp.pour_reference) form.value.pour_reference = disp.pour_reference;
    if (disp.dispatch_time) form.value.dispatch_time = disp.dispatch_time.substring(0, 16);
    if (disp.delivery_time) form.value.unloading_end = disp.delivery_time.substring(0, 16);

    const statusMap = {
        'Draft': 'scheduled',
        'Loading': 'batching',
        'In Transit': 'in_transit',
        'On Site': 'on_site',
        'Pouring': 'pouring',
        'Delivered': 'completed',
        'Cancelled': 'cancelled'
    };
    if (disp.dispatch_status && statusMap[disp.dispatch_status]) {
        form.value.status = statusMap[disp.dispatch_status];
    }

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: `Autofilled from #${disp.full_number}`,
        timer: 2000,
        showConfirmButton: false
    });
};

const clearDispatchSelection = () => {
    form.value.dispatch_id = null;
};

const initForm = () => {
    if (props.isEditing && props.initialData) {
        const item = props.initialData;
        form.value = {
            schedule_date: item.schedule_date || props.defaultScheduleDate,
            pour_reference: item.pour_reference || '',
            site_id: item.site_id ? Number(item.site_id) : null,
            mix_design_id: item.mix_design_id ? Number(item.mix_design_id) : null,
            qty_m3: parseFloat(item.qty_m3) || 6.0,
            order_volume_m3: parseFloat(item.order_volume_m3) || 30.0,
            vehicle_id: item.vehicle_id ? Number(item.vehicle_id) : null,
            driver_id: item.driver_id ? Number(item.driver_id) : null,
            pump_type: normalizePumpType(item.pump_type),
            pump_vehicle_id: item.pump_vehicle_id ? Number(item.pump_vehicle_id) : null,
            sales_order_id: item.sales_order_id ? Number(item.sales_order_id) : null,
            dispatch_id: item.dispatch_id ? Number(item.dispatch_id) : null,
            batching_time: item.batching_time ? item.batching_time.substring(0, 16) : '',
            dispatch_time: item.dispatch_time ? item.dispatch_time.substring(0, 16) : '',
            eta_site: item.eta_site ? item.eta_site.substring(0, 16) : '',
            unloading_start: item.unloading_start ? item.unloading_start.substring(0, 16) : '',
            unloading_end: item.unloading_end ? item.unloading_end.substring(0, 16) : '',
            status: item.status || 'scheduled',
            notes: item.notes || '',
        };
    } else {
        form.value = {
            schedule_date: props.defaultScheduleDate || new Date().toISOString().substring(0, 10),
            pour_reference: '',
            site_id: props.dropdowns.sites?.[0]?.id ? Number(props.dropdowns.sites[0].id) : null,
            mix_design_id: props.dropdowns.mixDesigns?.[0]?.id ? Number(props.dropdowns.mixDesigns[0].id) : null,
            qty_m3: 6.0,
            order_volume_m3: 30.0,
            vehicle_id: null,
            driver_id: null,
            pump_type: 'boom_pump',
            pump_vehicle_id: null,
            sales_order_id: null,
            dispatch_id: null,
            batching_time: '',
            dispatch_time: '',
            eta_site: '',
            unloading_start: '',
            unloading_end: '',
            status: 'scheduled',
            notes: '',
        };
    }
};

watch(() => props.initialData, initForm, { immediate: true });

const submitForm = async () => {
    if (!form.value.schedule_date || !form.value.pour_reference?.trim() || !form.value.site_id || !form.value.mix_design_id) {
        Swal.fire('Required Fields', 'Please complete Schedule Date, Pour Reference, Destination Site, and Mix Design.', 'warning');
        return;
    }

    if (form.value.batching_time && form.value.dispatch_time && new Date(form.value.batching_time) > new Date(form.value.dispatch_time)) {
        Swal.fire('Time Validation Error', 'Batching Time cannot be later than Dispatch Time.', 'error');
        return;
    }

    if (form.value.dispatch_time && form.value.eta_site && new Date(form.value.dispatch_time) > new Date(form.value.eta_site)) {
        Swal.fire('Time Validation Error', 'Dispatch Time cannot be later than ETA Site.', 'error');
        return;
    }

    if (form.value.unloading_start && form.value.unloading_end && new Date(form.value.unloading_start) > new Date(form.value.unloading_end)) {
        Swal.fire('Time Validation Error', 'Unloading Start Time cannot be later than Unloading End Time.', 'error');
        return;
    }

    saving.value = true;
    try {
        const payload = {
            ...form.value,
            pump_type: normalizePumpType(form.value.pump_type),
        };
        if (props.isEditing && props.initialData?.id) {
            await axios.put(route('production.batching-schedules.update', props.initialData.id), payload);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Schedule updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            await axios.post(route('production.batching-schedules.store'), payload);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Schedule created successfully',
                timer: 2000,
                showConfirmButton: false
            });
        }
        emit('saved');
    } catch (err) {
        console.error('Error saving batch schedule:', err);
        const errMsg = err.response?.data?.errors
            ? Object.values(err.response.data.errors).flat().join('<br><br>')
            : (err.response?.data?.message || 'Failed to save schedule.');

        Swal.fire({
            icon: 'error',
            title: 'Validation Conflict',
            html: `<div class="text-left text-xs leading-relaxed">${errMsg}</div>`,
            confirmButtonColor: '#4f46e5'
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
        <!-- Compact Header -->
        <div class="px-5 py-3 bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                    <TruckIcon class="w-4 h-4" />
                </div>
                <div>
                    <h2 class="text-xs font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? `Edit Trip #${initialData?.id}` : 'New Batching Schedule' }}
                    </h2>
                </div>
            </div>

            <button
                type="button"
                @click="emit('cancel')"
                class="px-2.5 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1 transition-colors"
            >
                <ArrowLeftIcon class="w-3.5 h-3.5" />
                <span>Back</span>
            </button>
        </div>

        <form @submit.prevent="submitForm" class="p-4 space-y-3.5">
            
            <!-- Quick Validation Alerts -->
            <div v-if="form.batching_time && form.dispatch_time && new Date(form.batching_time) > new Date(form.dispatch_time)" 
                 class="p-2 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-300 text-xs font-medium flex items-center gap-1.5">
                <ExclamationTriangleIcon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                <span>Batching Time cannot be later than Dispatch Time.</span>
            </div>

            <!-- PRIMARY SELECTOR: Dispatch / Batch Ticket with Autofill -->
            <div class="p-3 bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-lg">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-1.5 text-indigo-950 dark:text-indigo-200 font-bold text-xs">
                        <SparklesIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        <span>Autofill from Dispatch / Batch Ticket</span>
                    </div>
                    <button
                        v-if="form.dispatch_id"
                        type="button"
                        @click="clearDispatchSelection"
                        class="text-[11px] text-rose-600 hover:text-rose-700 dark:text-rose-400 font-semibold flex items-center gap-1 cursor-pointer"
                    >
                        <XMarkIcon class="w-3 h-3" />
                        <span>Clear Ticket Link</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-2">
                    <BaseSelect
                        v-model="form.dispatch_id"
                        :options="dispatchOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select Dispatch / Batch Ticket..."
                        @change="onDispatchSelected(form.dispatch_id)"
                    />
                </div>

                <!-- Active Ticket Inline Tag -->
                <div v-if="selectedDispatch" class="mt-2 pt-2 border-t border-indigo-100 dark:border-indigo-900/60 flex flex-wrap items-center gap-2 text-[11px]">
                    <span class="px-2 py-0.5 rounded bg-indigo-600 text-white font-bold text-[10px]">
                        #{{ selectedDispatch.full_number }}
                    </span>
                    <span v-if="selectedDispatch.batch_no" class="px-2 py-0.5 rounded bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-800 font-medium text-indigo-700 dark:text-indigo-300">
                        Batch #{{ selectedDispatch.batch_no }}
                    </span>
                    <span class="text-gray-600 dark:text-gray-300 font-medium">
                        {{ selectedDispatch.site_name }} • {{ selectedDispatch.mix_name }} • {{ selectedDispatch.vehicle_reg || 'No TM' }}
                    </span>
                    <span class="ml-auto font-bold text-indigo-700 dark:text-indigo-300">
                        {{ selectedDispatch.qty_m3 }} m³ ({{ selectedDispatch.dispatch_status || 'Draft' }})
                    </span>
                </div>
            </div>

            <!-- SECTION 1: Job & Mix Specifications (What & Where) -->
            <div class="p-3.5 bg-slate-50/80 dark:bg-gray-900/60 border border-slate-200 dark:border-gray-700 rounded-xl space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider">
                        <span class="w-5 h-5 rounded-md bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black shadow-xs">1</span>
                        <span>Job & Mix Specifications</span>
                    </div>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Order, Site & Grade Details</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <BaseDatePicker
                            v-model="form.schedule_date"
                            label="Schedule Date"
                            required
                        />
                    </div>

                    <div>
                        <BaseInput
                            v-model="form.pour_reference"
                            type="text"
                            label="Pour Reference"
                            placeholder="e.g. SLAB-L3"
                            required
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.site_id"
                            :options="siteOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Destination Site"
                            placeholder="Select Site"
                            required
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.mix_design_id"
                            :options="mixOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Mix Design Grade"
                            placeholder="Select Grade"
                            required
                        />
                    </div>

                    <div>
                        <BaseInputNumber
                            v-model="form.order_volume_m3"
                            :min="0.5"
                            :step="0.5"
                            :minFractionDigits="1"
                            :maxFractionDigits="2"
                            label="Total Order (m³)"
                            placeholder="30.0"
                            required
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Fleet & Logistics Allocation (Who & How) -->
            <div class="p-3.5 bg-slate-50/80 dark:bg-gray-900/60 border border-slate-200 dark:border-gray-700 rounded-xl space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider">
                        <span class="w-5 h-5 rounded-md bg-blue-600 text-white flex items-center justify-center text-[10px] font-black shadow-xs">2</span>
                        <span>Fleet & Logistics Allocation</span>
                    </div>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Truck, Driver, Pump & Load</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <BaseSelect
                            v-model="form.vehicle_id"
                            :options="vehicleOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Transit Mixer"
                            placeholder="Select Mixer"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.driver_id"
                            :options="driverOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Driver"
                            placeholder="Select Driver"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.pump_vehicle_id"
                            :options="pumpVehicleOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Pump Machine"
                            placeholder="Direct / None"
                        />
                    </div>

                    <div>
                        <BaseInputNumber
                            v-model="form.qty_m3"
                            :min="0.5"
                            :step="0.5"
                            :minFractionDigits="1"
                            :maxFractionDigits="2"
                            label="Trip Volume (m³)"
                            placeholder="6.0"
                            required
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Trip Status"
                            @change="onStatusChange"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Execution Timelines (When) -->
            <div class="p-3.5 bg-slate-50/80 dark:bg-gray-900/60 border border-slate-200 dark:border-gray-700 rounded-xl space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider">
                        <span class="w-5 h-5 rounded-md bg-emerald-600 text-white flex items-center justify-center text-[10px] font-black shadow-xs">3</span>
                        <span>Execution Timelines</span>
                    </div>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Sequential Operations Tracking</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <BaseDatePicker
                            v-model="form.batching_time"
                            :showTime="true"
                            hourFormat="12"
                            label="1. Batching Time"
                            placeholder="Select Time"
                        />
                    </div>

                    <div>
                        <BaseDatePicker
                            v-model="form.dispatch_time"
                            :showTime="true"
                            hourFormat="12"
                            label="2. Dispatch Time"
                            placeholder="Select Time"
                        />
                    </div>

                    <div>
                        <BaseDatePicker
                            v-model="form.eta_site"
                            :showTime="true"
                            hourFormat="12"
                            label="3. ETA Site Arrival"
                            placeholder="Select Time"
                        />
                    </div>

                    <div>
                        <BaseDatePicker
                            v-model="form.unloading_start"
                            :showTime="true"
                            hourFormat="12"
                            label="4. Unload Start"
                            placeholder="Select Time"
                        />
                    </div>

                    <div>
                        <BaseDatePicker
                            v-model="form.unloading_end"
                            :showTime="true"
                            hourFormat="12"
                            label="5. Unload Finish"
                            placeholder="Select Time"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Notes & Bottom Actions -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="w-full sm:w-2/3">
                    <BaseInput
                    label="Remarks/Notes"
                        v-model="form.notes"
                        type="text"
                        placeholder="Optional remarks or batching instructions..."
                    />
                </div>

                <div class="flex items-center justify-end gap-2 shrink-0 ml-auto">
                    <BaseButton
                        label="Cancel"
                        severity="secondary"
                        variant="outlined"
                        @click="emit('cancel')"
                    />
                    <BaseButton
                        :label="isEditing ? 'Save Changes' : 'Create Schedule'"
                        severity="primary"
                        variant="filled"
                        type="submit"
                        :loading="saving"
                        class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-transparent font-semibold"
                    />
                </div>
            </div>
        </form>
    </div>
</template>

