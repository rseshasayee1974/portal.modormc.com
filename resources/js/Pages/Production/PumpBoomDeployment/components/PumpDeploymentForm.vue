<script setup>
import { entityToday } from '@/Utils/entityDateTime';
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import {
    WrenchScrewdriverIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon
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
            machines: [],
            operators: [],
            pumpTypes: [],
            salesOrders: [],
            batches: []
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
    sales_order_id: null,
    batch_id: null,
    site_id: null,
    site_name: '',
    site_contact_number: '',
    pour_location: '',
    mix_design_id: null,
    grade: '',
    planned_qty_m3: 0,
    billing_name: '',
    pump_type: 'boom_pump',
    pump_vehicle_id: null,
    pump_no: '',
    boom_length_m: 0,
    operator_id: null,
    operator_name: '',
    driver_contact_number: '',
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

// Dropdown option maps
const salesOrderOptions = computed(() => {
    return (props.dropdowns?.salesOrders || []).map((so) => ({
        label: `${so.prefix}${so.order_no}`,
        value: so.id,
        raw: so,
    }));
});

const batchOptions = computed(() => {
    let batches = props.dropdowns?.batches || [];

    // Filter by selected sales order
    if (form.value.sales_order_id) {
        batches = batches.filter(b => b.sales_order_id == form.value.sales_order_id);
    }

    return batches.map((b) => ({
        label: `B-${b.batch_no}`,
        value: b.id,
        raw: b,
    }));
});

const siteOptions = computed(() => props.dropdowns.sites?.map(s => ({ label: s.name, value: s.id })) || []);
const mixOptions = computed(() => props.dropdowns.mixDesigns?.map(m => ({ label: `${m.name} `, value: m.id })) || []);
const machineOptions = computed(() => props.dropdowns.machines?.map(m => ({ label: `${m.registration} `, value: m.id })) || []);
const operatorOptions = computed(() => props.dropdowns.operators?.map(o => ({ label: `${o.first_name} ${o.last_name || ''}`, value: o.id })) || []);

const pumpTypeOptions = [
    { label: 'Boom Pump', value: 'boom_pump' },
    { label: 'Line Pump', value: 'line_pump' },
    { label: 'Stationary Pump', value: 'stationary_pump' },
    { label: 'Crane & Bucket', value: 'crane_bucket' },
    { label: 'Direct Chute', value: 'direct_pour' },
];

const statusOptions = [
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'En Route', value: 'en_route' },
    { label: 'Setup', value: 'setup' },
    { label: 'Ready', value: 'ready' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Line Washout', value: 'washout' },
    { label: 'Completed', value: 'completed' },
    { label: 'Delayed', value: 'delayed' },
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

const toIstDatetimeString = (utcString) => {
    if (!utcString) return '';
    const d = new Date(utcString);
    if (isNaN(d.getTime())) return '';
    const istTime = new Date(d.toLocaleString("en-US", { timeZone: "Asia/Kolkata" }));
    const y = istTime.getFullYear();
    const m = String(istTime.getMonth() + 1).padStart(2, '0');
    const day = String(istTime.getDate()).padStart(2, '0');
    const hh = String(istTime.getHours()).padStart(2, '0');
    const mm = String(istTime.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day}T${hh}:${mm}`;
};

const initForm = () => {
    if (props.isEditing && props.initialData) {
        const item = props.initialData;

        form.value = {
            schedule_date: item.schedule_date || props.defaultScheduleDate,
            sales_order_id: item.sales_order_id ? Number(item.sales_order_id) : null,
            batch_id: item.batch_id ? Number(item.batch_id) : null,
            site_id: item.site_id ? Number(item.site_id) : null,
            site_name: item.site_name || '',
            site_contact_number: item.site_contact_number || '',
            pour_location: item.pour_location || '',
            mix_design_id: item.mix_design_id ? Number(item.mix_design_id) : null,
            grade: item.grade || '',
            planned_qty_m3: parseFloat(item.planned_qty_m3) || 0,
            billing_name: item.billing_name || '',
            pump_type: normalizePumpType(item.pump_type),
            pump_vehicle_id: item.pump_vehicle_id ? Number(item.pump_vehicle_id) : null,
            pump_no: item.pump_no || '',
            boom_length_m: parseFloat(item.boom_length_m) || 0,
            operator_id: item.operator_id ? Number(item.operator_id) : null,
            operator_name: item.operator_name || '',
            driver_contact_number: item.driver_contact_number || '',
            pump_arrival_time: toIstDatetimeString(item.pump_arrival_time),
            setup_start_time: toIstDatetimeString(item.setup_start_time),
            setup_end_time: toIstDatetimeString(item.setup_end_time),
            pour_start_time: toIstDatetimeString(item.pour_start_time),
            planned_end_time: toIstDatetimeString(item.planned_end_time),
            actual_start_time: toIstDatetimeString(item.actual_start_time),
            actual_end_time: toIstDatetimeString(item.actual_end_time),
            notes: item.notes || '',
            status: item.status || 'scheduled',
        };
    } else {
        form.value = {
            schedule_date: props.defaultScheduleDate || entityToday(),
            sales_order_id: null,
            batch_id: null,
            site_id: props.dropdowns.sites?.[0]?.id ? Number(props.dropdowns.sites[0].id) : null,
            site_name: props.dropdowns.sites?.[0]?.name || '',
            site_contact_number: '',
            pour_location: '',
            mix_design_id: props.dropdowns.mixDesigns?.[0]?.id ? Number(props.dropdowns.mixDesigns[0].id) : null,
            grade: props.dropdowns.mixDesigns?.[0]?.name || '',
            planned_qty_m3: null,
            billing_name: '',
            pump_type: 'boom_pump',
            pump_vehicle_id: null,
            pump_no: '',
            boom_length_m: 0,
            operator_id: null,
            operator_name: '',
            driver_contact_number: '',
            pump_arrival_time: '',
            setup_start_time: '',
            setup_end_time: '',
            pour_start_time: '',
            planned_end_time: '',
            actual_start_time: '',
            actual_end_time: '',
            status: 'scheduled',
            notes: '',
        };
    }
};

watch(() => props.initialData, initForm, { immediate: true });

const onSalesOrderSelect = () => {
    const selected = props.dropdowns?.salesOrders?.find(so => so.id == form.value.sales_order_id);
    if (selected) {
        if (selected.site_id) {
            form.value.site_id = Number(selected.site_id);
            form.value.site_name = selected.site?.name || '';
            form.value.pour_location = selected.site?.site_address_1 || selected.site?.name || '';
            form.value.site_contact_number = selected.customer_mobile || '';
        }
        if (selected.mix_design_id) {
            form.value.mix_design_id = Number(selected.mix_design_id);
            form.value.grade = selected.mixDesign?.design_name || '';
        }
        form.value.planned_qty_m3 = parseFloat(selected.total_qty || 0);
        form.value.billing_name = selected.customer_name || '';
    }
};

const onBatchSelect = () => {
    const selected = props.dropdowns?.batches?.find(b => b.id == form.value.batch_id);
    if (selected) {
        if (selected.sales_order_id) {
            form.value.sales_order_id = Number(selected.sales_order_id);
            onSalesOrderSelect();
        }
        form.value.planned_qty_m3 = parseFloat(selected.planned_qty_m3 || 0);
        form.value.notes = `Batch: ${selected.batch_no}`;
    }
};

const onSiteSelect = () => {
    const s = props.dropdowns.sites?.find(item => item.id == form.value.site_id);
    if (s) form.value.site_name = s.name;
};

const onMixSelect = () => {
    const m = props.dropdowns.mixDesigns?.find(item => item.id == form.value.mix_design_id);
    if (m) form.value.grade = m.name;
};

const onPumpSelect = () => {
    const p = props.dropdowns.machines?.find(item => item.id == form.value.pump_vehicle_id);
    if (p) form.value.pump_no = p.registration;
};

const onOperatorSelect = () => {
    const o = props.dropdowns.operators?.find(item => item.id == form.value.operator_id);
    if (o) {
        form.value.operator_name = (o.first_name || '') + ' ' + (o.last_name || '');
        form.value.driver_contact_number = o.mobile || o.phone || '';
    }
};

const onActualStartInput = () => {
    if (form.value.actual_start_time) {
        if (!form.value.actual_end_time) {
            const startDate = new Date(form.value.actual_start_time);
            if (!isNaN(startDate.getTime())) {
                // Convert to Indian Standard Time (Chennai)
                const istTime = new Date(startDate.toLocaleString("en-US", { timeZone: "Asia/Kolkata" }));
                istTime.setHours(istTime.getHours() + 1);

                const y = istTime.getFullYear();
                const m = String(istTime.getMonth() + 1).padStart(2, '0');
                const d = String(istTime.getDate()).padStart(2, '0');
                const hh = String(istTime.getHours()).padStart(2, '0');
                const mm = String(istTime.getMinutes()).padStart(2, '0');
                form.value.actual_end_time = `${y}-${m}-${d}T${hh}:${mm}`;
            }
        }

        if (form.value.actual_end_time) {
            form.value.status = 'completed';
        } else if (!['delayed', 'cancelled'].includes(form.value.status)) {
            form.value.status = 'in_progress';
        }
    }
};

const onActualEndInput = () => {
    if (form.value.actual_end_time) {
        if (!['cancelled'].includes(form.value.status)) {
            form.value.status = 'completed';
        }
    }
};

const submitForm = async () => {
    if (!form.value.schedule_date || !form.value.sales_order_id) {
        Swal.fire('Required Field', 'Please provide Schedule Date and Sales Order.', 'warning');
        return;
    }

    if (!form.value.planned_qty_m3) {
        Swal.fire('Required Fields', 'Please specify Planned Volume.', 'warning');
        return;
    }

    if (!form.value.pump_type || (!form.value.pump_vehicle_id && !String(form.value.pump_no || '').trim())) {
        Swal.fire('Assigned Pump Required', 'Please assign a pump machine.', 'warning');
        return;
    }

    // if (form.value.pump_type === 'boom_pump') {
    //     const boomLen = parseFloat(form.value.boom_length_m);
    //     if (isNaN(boomLen) || boomLen <= 0) {
    //         Swal.fire('Boom Length Required', 'Please specify boom length in meters.', 'warning');
    //         return;
    //     }
    // }

    // Time Validation: setup_start_time <= setup_end_time
    if (form.value.setup_start_time && form.value.setup_end_time) {
        if (new Date(form.value.setup_start_time) > new Date(form.value.setup_end_time)) {
            Swal.fire({
                icon: 'error',
                title: 'Time Validation Error',
                text: 'Setup Start Time cannot be later than Setup End Time.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }
    }

    // Default actual_end_time to 1 hour after actual_start_time if empty
    if (form.value.actual_start_time && !form.value.actual_end_time) {
        const startDate = new Date(form.value.actual_start_time);
        startDate.setHours(startDate.getHours() + 1);

        const y = startDate.getFullYear();
        const m = String(startDate.getMonth() + 1).padStart(2, '0');
        const d = String(startDate.getDate()).padStart(2, '0');
        const hh = String(startDate.getHours()).padStart(2, '0');
        const mm = String(startDate.getMinutes()).padStart(2, '0');
        form.value.actual_end_time = `${y}-${m}-${d}T${hh}:${mm}`;

        // Also update status since we now have an end time
        if (!['cancelled'].includes(form.value.status)) {
            form.value.status = 'completed';
        }
    }

    // Time Validation: actual_start_time <= actual_end_time
    if (form.value.actual_start_time && form.value.actual_end_time) {
        if (new Date(form.value.actual_start_time) > new Date(form.value.actual_end_time)) {
            Swal.fire({
                icon: 'error',
                title: 'Time Validation Error',
                text: 'Actual Start Time cannot be later than Actual End Time.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }
    }

    saving.value = true;
    try {
        if (!form.value.status) {
            form.value.status = 'scheduled';
        }

        const payload = {
            ...form.value,
            pour_reference: form.value.pour_reference,
            pump_type: normalizePumpType(form.value.pump_type),
        };
        if (props.isEditing && props.initialData?.id) {
            await axios.put(route('production.pump-deployments.update', props.initialData.id), payload);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Schedule updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            await axios.post(route('production.pump-deployments.store'), payload);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Pump schedule created successfully',
                timer: 2000,
                showConfirmButton: false
            });
            initForm();
        }
        emit('saved');
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
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                    <WrenchScrewdriverIcon class="h-5 w-5" />
                </div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ isEditing ? `Edit Pump Deployment #${initialData?.id}` : 'New Pump Deployment' }}
                </h2>
            </div>
            <button v-if="isEditing" type="button" @click="emit('cancel')" class="text-xs font-semibold text-slate-500 transition-colors hover:text-rose-600 dark:text-slate-400">
                Cancel edit
            </button>
            <button v-else type="button" @click="initForm" class="flex items-center gap-1 text-xs font-semibold text-slate-500 transition-colors hover:text-indigo-600 dark:text-slate-400">
                <ArrowPathIcon class="h-3.5 w-3.5" /> Reset
            </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-6 p-5">

            <div v-if="form.actual_start_time && form.actual_end_time && new Date(form.actual_start_time) > new Date(form.actual_end_time)"
                class="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300">
                <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
                <span>Actual Start Time cannot be later than Actual End Time.</span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <BaseDatePicker v-model="form.schedule_date" label="Schedule Date" required />
                <BaseSelect v-model="form.sales_order_id" :options="salesOrderOptions" optionLabel="label" optionValue="value" label="Sales Order" placeholder="Select Sales Order" :filter="true" required @change="onSalesOrderSelect" />
                <BaseSelect v-model="form.batch_id" :options="batchOptions" optionLabel="label" optionValue="value" label="Batch" placeholder="Optional" :filter="true" @change="onBatchSelect" />
                <BaseSelect v-model="form.site_id" :options="siteOptions" optionLabel="label" optionValue="value" label="Destination Site" placeholder="Select Site" required @change="onSiteSelect" />
                <BaseInput v-model="form.pour_location" label="Pour Location" placeholder="e.g. Slab, Raft" />
                <BaseInput v-model="form.site_contact_number" label="Site Contact" placeholder="Contact number" />
                <BaseInput v-model="form.billing_name" label="Billing Name" placeholder="Billing name" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <BaseSelect v-model="form.mix_design_id" :options="mixOptions" optionLabel="label" optionValue="value" label="Mix Grade" placeholder="Select Grade" @change="onMixSelect" />
                <BaseInputNumber v-model="form.planned_qty_m3" :min="0.5" :step="0.5" :minFractionDigits="1" :maxFractionDigits="2" label="Volume (m³)" required />
                <BaseSelect v-model="form.pump_vehicle_id" :options="machineOptions" optionLabel="label" optionValue="value" label="Assigned Pump" placeholder="Select Pump" required @change="onPumpSelect" />
                <BaseSelect v-model="form.operator_id" :options="operatorOptions" optionLabel="label" optionValue="value" label="Operator" placeholder="Assign Operator" @change="onOperatorSelect" />
                <BaseInput v-model="form.driver_contact_number" label="Operator Contact" placeholder="Mobile number" />
                <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" label="Status" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <BaseDatePicker v-model="form.pump_arrival_time" :showTime="true" hourFormat="12" label="Site Arrival" />
                <BaseDatePicker v-model="form.setup_start_time" :showTime="true" hourFormat="12" label="Setup Start" />
                <BaseDatePicker v-model="form.setup_end_time" :showTime="true" hourFormat="12" label="Setup Ready" />
                <BaseDatePicker v-model="form.pour_start_time" :showTime="true" hourFormat="12" label="Pour Start" />
                <BaseDatePicker v-model="form.planned_end_time" :showTime="true" hourFormat="12" label="Planned Finish" />
                <BaseDatePicker v-model="form.actual_start_time" :showTime="true" hourFormat="12" label="Actual Start" @update:modelValue="onActualStartInput" />
                <BaseDatePicker v-model="form.actual_end_time" :showTime="true" hourFormat="12" label="Actual Finish" @update:modelValue="onActualEndInput" />
            </div>

            <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 dark:border-slate-800 sm:flex-row sm:items-end">
                <BaseInput v-model="form.notes" label="Notes" placeholder="Rigging, site access, or pour notes" class="min-w-0 flex-1" />
                <div class="flex shrink-0 justify-end gap-2">
                    <BaseButton v-if="isEditing" label="Cancel" severity="secondary" variant="outlined" @click="emit('cancel')" />
                    <BaseButton :label="isEditing ? 'Save Changes' : 'Create Deployment'" severity="primary" variant="filled" type="submit" :loading="saving" class="!border-transparent !bg-indigo-600 !text-white hover:!bg-indigo-700" />
                </div>
            </div>
        </form>
    </div>
</template>
