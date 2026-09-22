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
    ArrowLeftIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    deployment: {
        type: Object,
        required: true,
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
});

const emit = defineEmits(['saved', 'cancel']);

const saving = ref(false);

const form = ref({
    schedule_date: '',
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
    if (!props.deployment) return;
    const item = props.deployment;

    form.value = {
        schedule_date: item.schedule_date || entityToday(),
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
};

watch(() => props.deployment, initForm, { immediate: true });

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
        const payload = {
            ...form.value,
            pour_reference: form.value.sales_order_id,
            pump_type: normalizePumpType(form.value.pump_type),
        };
        
        await axios.put(route('production.pump-deployments.update', props.deployment.id), payload);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Schedule updated successfully',
            timer: 2000,
            showConfirmButton: false
        });
        
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
    <div
        class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
        <!-- Compact Header -->
        <div
            class="px-4 py-2.5 bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-md bg-indigo-600 text-white flex items-center justify-center">
                    <WrenchScrewdriverIcon class="w-3.5 h-3.5 text-white" />
                </div>
                <h2 class="text-xs font-bold text-gray-900 dark:text-gray-100">
                    Edit Deployment #{{ deployment?.id }}
                </h2>
            </div>

            <button type="button" @click="emit('cancel')"
                class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md text-[11px] font-medium flex items-center gap-1 transition-colors">
                <ArrowLeftIcon class="w-3 h-3" />
                <span>Cancel Edit</span>
            </button>
        </div>

        <form @submit.prevent="submitForm" class="p-3 space-y-3">

            <div v-if="form.actual_start_time && form.actual_end_time && new Date(form.actual_start_time) > new Date(form.actual_end_time)"
                class="p-2 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-300 text-xs font-medium flex items-center gap-1.5">
                <ExclamationTriangleIcon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                <span>Actual Start Time cannot be later than Actual End Time.</span>
            </div>

            <!-- SECTION 1: Job & Location Details -->
            <div
                class="p-3.5 bg-slate-50/80 dark:bg-gray-900/60 border border-slate-200 dark:border-gray-700 rounded-xl space-y-2.5">
                <div class="flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider">
                        <span
                            class="w-5 h-5 rounded-md bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black shadow-xs">1</span>
                        <span>Job & Location Details</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <BaseDatePicker v-model="form.schedule_date" label="Schedule Date" required />
                    </div>

                    <div>
                        <BaseSelect v-model="form.sales_order_id" :options="salesOrderOptions" optionLabel="label"
                            optionValue="value" label="Sales Order" placeholder="Select Sales Order" :filter="true"
                            required @change="onSalesOrderSelect" />
                    </div>

                    <div>
                        <BaseSelect v-model="form.batch_id" :options="batchOptions" optionLabel="label"
                            optionValue="value" label="Batch (Optional)" placeholder="Select Batch" :filter="true"
                            @change="onBatchSelect" />
                    </div>

                    <div>
                        <BaseSelect v-model="form.site_id" :options="siteOptions" optionLabel="label"
                            optionValue="value" label="Destination Site" placeholder="Select Site" required
                            @change="onSiteSelect" />
                    </div>

                    <div>
                        <BaseInput v-model="form.pour_location" type="text" label="Pour Location"
                            placeholder="e.g. Slab, Raft" />
                    </div>

                    <div>
                        <BaseInput v-model="form.site_contact_number" type="text" label="Site Contact No"
                            placeholder="Contact No" />
                    </div>

                    <div>
                        <BaseInput v-model="form.billing_name" type="text" label="Billing Name"
                            placeholder="Billing Name" />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Equipment & Operations -->
            <div
                class="p-3.5 bg-slate-50/80 dark:bg-gray-900/60 border border-slate-200 dark:border-gray-700 rounded-xl space-y-2.5">
                <div class="flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider">
                        <span
                            class="w-5 h-5 rounded-md bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black shadow-xs">2</span>
                        <span>Equipment & Operations</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <BaseSelect v-model="form.mix_design_id" :options="mixOptions" optionLabel="label"
                            optionValue="value" label="Mix Grade" placeholder="Select Grade" @change="onMixSelect" />
                    </div>

                    <div>
                        <BaseInputNumber v-model="form.planned_qty_m3" :min="0.5" :step="0.5" :minFractionDigits="1"
                            :maxFractionDigits="2" label="Volume (m³)" placeholder="0" required />
                    </div>

                    <div>
                        <BaseSelect v-model="form.pump_vehicle_id" :options="machineOptions" optionLabel="label"
                            optionValue="value" label="Assigned Pump" placeholder="Select Pump" required
                            @change="onPumpSelect" />
                    </div>

                    <div>
                        <BaseSelect v-model="form.operator_id" :options="operatorOptions" optionLabel="label"
                            optionValue="value" label="Operator" placeholder="Assign Operator"
                            @change="onOperatorSelect" />
                    </div>

                    <div>
                        <BaseInput v-model="form.driver_contact_number" type="text" label="Driver Contact No"
                            placeholder="Mobile No" />
                    </div>

                    <div>
                        <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label"
                            optionValue="value" label="Status" />
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Timelines -->
            <div
                class="p-3.5 bg-slate-50/80 dark:bg-gray-900/60 border border-slate-200 dark:border-gray-700 rounded-xl space-y-2.5">
                <div class="flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider">
                        <span
                            class="w-5 h-5 rounded-md bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black shadow-xs">3</span>
                        <span>Timelines</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <BaseDatePicker v-model="form.actual_start_time" :showTime="true" hourFormat="12"
                            label="Act. Pour Start" placeholder="Select Time" @update:modelValue="onActualStartInput" />
                    </div>
                    <div>
                        <BaseDatePicker v-model="form.actual_end_time" :showTime="true" hourFormat="12"
                            label="Act. Pour Finish" placeholder="Select Time" @update:modelValue="onActualEndInput" />
                    </div>
                </div>
            </div>

            <!-- Notes & Bottom Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                <div class="w-full sm:grow">
                    <BaseInput label="Remarks / Notes" v-model="form.notes" type="text"
                        placeholder="Add rigging, site access, or pour notes..." />
                </div>

                <div class="flex items-center justify-end gap-2 shrink-0 sm:self-end">
                    <BaseButton label="Cancel Edit" severity="secondary" variant="outlined"
                        @click="emit('cancel')" />
                    <BaseButton label="Save Changes" severity="primary"
                        variant="filled" type="submit" :loading="saving"
                        class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-transparent font-semibold text-xs shadow-xs" />
                </div>
            </div>
        </form>
    </div>
</template>
