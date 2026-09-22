<script setup>
import { entityToday, entityDateTime } from '@/Utils/entityDateTime';
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
    ExclamationTriangleIcon,
    XMarkIcon,
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
            pumps: [],
            drivers: [],
            salesOrders: [],
            dispatches: [],
            pumpTypes: []
        }),
    },
    defaultScheduleDate: {
        type: String,
        default: () => entityToday(),
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
const vehicleOptions = computed(() => props.dropdowns.vehicles?.map(v => ({ label: v.registration, value: v.id })) || []);
const driverOptions = computed(() => props.dropdowns.drivers?.map(d => ({ label: `${d.first_name} ${d.last_name || ''} (${d.mobile || d.employee_code || 'Staff'})`, value: d.id })) || []);
const pumpVehicleOptions = computed(() => [
    { label: 'Direct Pour / No Pump', value: null },
    ...(props.dropdowns.pumps?.map(v => ({ label: v.registration, value: v.id })) || [])
]);
const dispatchOptions = computed(() => [
    { label: '-- Manual Scheduling (No Ticket Linked) --', value: null },
    ...(props.dropdowns.dispatches || []).map(d => ({
        label: d.batch_no,
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
    const nowStr = entityDateTime().replace(' ', 'T').substring(0, 16);
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
    if (disp.batch_time) form.value.batching_time = disp.batch_time.substring(0, 16);
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
            schedule_date: props.defaultScheduleDate || entityToday(),
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
    if (!form.value.schedule_date || !form.value.site_id || !form.value.mix_design_id) {
        Swal.fire('Required Fields', 'Please complete Schedule Date, Destination Site, and Mix Design.', 'warning');
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
    <div
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                    <TruckIcon class="h-5 w-5" />
                </div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ isEditing ? `Edit Batching Schedule #${initialData?.id}` : 'New Batching Schedule' }}
                </h2>
            </div>
            <button type="button" @click="emit('cancel')"
                class="text-xs font-semibold text-slate-500 transition-colors hover:text-rose-600 dark:text-slate-400">
                Cancel
            </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-6 p-5">
            <div v-if="form.batching_time && form.dispatch_time && new Date(form.batching_time) > new Date(form.dispatch_time)"
                class="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300">
                <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
                <span>Batching Time cannot be later than Dispatch Time.</span>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,24rem)_auto] md:items-end">
                <BaseSelect v-model="form.dispatch_id" :options="dispatchOptions" optionLabel="label"
                    optionValue="value" label="Dispatch / Batch Ticket" placeholder="Manual schedule"
                    @change="onDispatchSelected(form.dispatch_id)" />
                <div v-if="form.dispatch_id" class="pb-1">
                    <button v-if="form.dispatch_id" type="button" @click="clearDispatchSelection"
                        class="flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700">
                        <XMarkIcon class="h-3.5 w-3.5" /> Clear selection
                    </button>
                </div>
            </div>

            <div v-if="selectedDispatch"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-200">
                <span class="font-bold">#{{ selectedDispatch.full_number }}</span>
                <span>{{ selectedDispatch.site_name }}</span>
                <span>{{ selectedDispatch.mix_name }}</span>
                <span>{{ selectedDispatch.qty_m3 }} m³</span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <BaseDatePicker v-model="form.schedule_date" label="Schedule Date" required />
                <!-- <BaseInput v-model="form.pour_reference" label="Pour Reference (Auto-generated if empty)"
                    placeholder="e.g. SLAB-L3" /> -->
                <BaseSelect v-model="form.site_id" :options="siteOptions" optionLabel="label" optionValue="value"
                    label="Destination Site" placeholder="Select Site" required />
                <BaseSelect v-model="form.mix_design_id" :options="mixOptions" optionLabel="label" optionValue="value"
                    label="Mix Design" placeholder="Select Mix" required />
                <BaseInputNumber v-model="form.order_volume_m3" :min="0.5" :step="0.5" :minFractionDigits="1"
                    :maxFractionDigits="2" label="Order Volume (m³)" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <BaseSelect v-model="form.vehicle_id" :options="vehicleOptions" optionLabel="label" optionValue="value"
                    label="Transit Mixer" placeholder="Select Mixer" />
                <BaseSelect v-model="form.driver_id" :options="driverOptions" optionLabel="label" optionValue="value"
                    label="Driver" placeholder="Select Driver" />
                <BaseSelect v-model="form.pump_vehicle_id" :options="pumpVehicleOptions" optionLabel="label"
                    optionValue="value" label="Pump" placeholder="Direct Pour" />
                <BaseInputNumber v-model="form.qty_m3" :min="0.5" :step="0.5" :minFractionDigits="1"
                    :maxFractionDigits="2" label="Trip Volume (m³)" required />
                <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    label="Status" @change="onStatusChange" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <BaseDatePicker v-model="form.batching_time" :showTime="true" hourFormat="12" label="Batching Time" />
                <BaseDatePicker v-model="form.dispatch_time" :showTime="true" hourFormat="12" label="Dispatch Time" />
                <BaseDatePicker v-model="form.eta_site" :showTime="true" hourFormat="12" label="ETA at Site" />
                <BaseDatePicker v-model="form.unloading_start" :showTime="true" hourFormat="12"
                    label="Unloading Start" />
                <BaseDatePicker v-model="form.unloading_end" :showTime="true" hourFormat="12" label="Unloading End" />
            </div>

            <div
                class="flex flex-col gap-4 border-t border-slate-100 pt-5 dark:border-slate-800 sm:flex-row sm:items-end">
                <BaseInput v-model="form.notes" label="Notes" placeholder="Optional batching instructions"
                    class="min-w-0 flex-1" />
                <div class="flex shrink-0 justify-end gap-2">
                    <BaseButton label="Cancel" severity="secondary" variant="outlined" @click="emit('cancel')" />
                    <BaseButton :label="isEditing ? 'Save Changes' : 'Create Schedule'" severity="primary"
                        variant="filled" type="submit" :loading="saving"
                        class="!border-transparent !bg-indigo-600 !text-white hover:!bg-indigo-700" />
                </div>
            </div>
        </form>
    </div>
</template>
