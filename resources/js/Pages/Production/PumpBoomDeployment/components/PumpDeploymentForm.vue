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
    WrenchScrewdriverIcon,
    MapPinIcon,
    ClockIcon,
    ArrowLeftIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon
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
    site_name: '',
    pour_location: '',
    mix_design_id: null,
    grade: '',
    planned_qty_m3: 45.0,
    pump_type: 'boom_pump',
    pump_vehicle_id: null,
    pump_no: '',
    boom_length_m: 36.0,
    operator_id: null,
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

// Dropdown option maps
const siteOptions = computed(() => props.dropdowns.sites?.map(s => ({ label: s.name, value: s.id })) || []);
const mixOptions = computed(() => props.dropdowns.mixDesigns?.map(m => ({ label: `${m.name} (${m.code || '-'})`, value: m.id })) || []);
const machineOptions = computed(() => props.dropdowns.machines?.map(m => ({ label: `${m.registration} (${m.vehicle_model || 'Rig'})`, value: m.id })) || []);
const operatorOptions = computed(() => props.dropdowns.operators?.map(o => ({ label: `${o.first_name} ${o.last_name || ''} (${o.phone || o.employee_code || 'Staff'})`, value: o.id })) || []);

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

const initForm = () => {
    if (props.isEditing && props.initialData) {
        const item = props.initialData;
        form.value = {
            schedule_date: item.schedule_date || props.defaultScheduleDate,
            pour_reference: item.pour_reference || '',
            site_id: item.site_id ? Number(item.site_id) : null,
            site_name: item.site_name || '',
            pour_location: item.pour_location || '',
            mix_design_id: item.mix_design_id ? Number(item.mix_design_id) : null,
            grade: item.grade || '',
            planned_qty_m3: parseFloat(item.planned_qty_m3) || 45.0,
            pump_type: normalizePumpType(item.pump_type),
            pump_vehicle_id: item.pump_vehicle_id ? Number(item.pump_vehicle_id) : null,
            pump_no: item.pump_no || '',
            boom_length_m: parseFloat(item.boom_length_m) || 36.0,
            operator_id: item.operator_id ? Number(item.operator_id) : null,
            operator_name: item.operator_name || '',
            pump_arrival_time: item.pump_arrival_time ? item.pump_arrival_time.substring(0, 16) : '',
            setup_start_time: item.setup_start_time ? item.setup_start_time.substring(0, 16) : '',
            setup_end_time: item.setup_end_time ? item.setup_end_time.substring(0, 16) : '',
            pour_start_time: item.pour_start_time ? item.pour_start_time.substring(0, 16) : '',
            planned_end_time: item.planned_end_time ? item.planned_end_time.substring(0, 16) : '',
            actual_start_time: item.actual_start_time ? item.actual_start_time.substring(0, 16) : '',
            actual_end_time: item.actual_end_time ? item.actual_end_time.substring(0, 16) : '',
            notes: item.notes || '',
            status: item.status || 'scheduled',
        };
    } else {
        form.value = {
            schedule_date: props.defaultScheduleDate || new Date().toISOString().substring(0, 10),
            pour_reference: '',
            site_id: props.dropdowns.sites?.[0]?.id ? Number(props.dropdowns.sites[0].id) : null,
            site_name: props.dropdowns.sites?.[0]?.name || '',
            pour_location: '',
            mix_design_id: props.dropdowns.mixDesigns?.[0]?.id ? Number(props.dropdowns.mixDesigns[0].id) : null,
            grade: props.dropdowns.mixDesigns?.[0]?.name || '',
            planned_qty_m3: 45.0,
            pump_type: 'boom_pump',
            pump_vehicle_id: null,
            pump_no: '',
            boom_length_m: 36.0,
            operator_id: null,
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
        };
    }
};

watch(() => props.initialData, initForm, { immediate: true });

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
    if (o) form.value.operator_name = (o.first_name || '') + ' ' + (o.last_name || '');
};

const onActualStartInput = () => {
    if (form.value.actual_start_time) {
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
    if (!form.value.schedule_date || !form.value.pour_reference?.trim()) {
        Swal.fire('Required Field', 'Please provide Schedule Date and Pour Reference.', 'warning');
        return;
    }

    if (!form.value.pour_location?.trim() || !form.value.planned_qty_m3) {
        Swal.fire('Required Fields', 'Please specify Pour Location and Planned Volume.', 'warning');
        return;
    }

    if (!form.value.pump_type || (!form.value.pump_vehicle_id && !form.value.pump_no?.trim())) {
        Swal.fire('Assigned Pump Required', 'Please assign a pump machine.', 'warning');
        return;
    }

    if (form.value.pump_type === 'boom_pump') {
        const boomLen = parseFloat(form.value.boom_length_m);
        if (isNaN(boomLen) || boomLen <= 0) {
            Swal.fire('Boom Length Required', 'Please specify boom length in meters.', 'warning');
            return;
        }
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
            pump_type: normalizePumpType(form.value.pump_type),
        };
        if (props.isEditing && props.initialData?.id) {
            await axios.put(route('production.pump-deployments.update', props.initialData.id), payload);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Deployment updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            await axios.post(route('production.pump-deployments.store'), payload);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Deployment scheduled successfully',
                timer: 2000,
                showConfirmButton: false
            });
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
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
        <!-- Header -->
        <div class="px-6 py-4 bg-gray-50/80 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-sm">
                    <WrenchScrewdriverIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? `Edit Deployment #${initialData?.id}` : 'New Pump Deployment' }}
                    </h2>
                    <p v-if="form.pour_reference" class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                        {{ form.pour_reference }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="emit('cancel')"
                class="px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm"
            >
                <ArrowLeftIcon class="w-3.5 h-3.5" />
                <span>Back</span>
            </button>
        </div>

        <form @submit.prevent="submitForm" class="p-6 space-y-5">
            
            <!-- Real-Time Time Validation Warnings / Advisories -->
            <div v-if="form.setup_start_time && form.setup_end_time && new Date(form.setup_start_time) > new Date(form.setup_end_time)" 
                 class="p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-300 text-xs font-medium flex items-center gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 shrink-0" />
                <span>Setup Start Time cannot be later than Setup End Time.</span>
            </div>

            <div v-if="form.actual_start_time && form.actual_end_time && new Date(form.actual_start_time) > new Date(form.actual_end_time)" 
                 class="p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-300 text-xs font-medium flex items-center gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 shrink-0" />
                <span>Actual Start Time cannot be later than Actual End Time.</span>
            </div>

            <div v-if="form.pump_arrival_time && form.setup_start_time && new Date(form.pump_arrival_time) > new Date(form.setup_start_time)" 
                 class="p-2 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-300 text-[11px] font-medium flex items-center gap-2">
                <InformationCircleIcon class="w-4 h-4 text-amber-600 shrink-0" />
                <span>Pump arrival on site is usually prior to setup start.</span>
            </div>

            <!-- SECTION 1: Pour & Location Details -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <MapPinIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        Pour & Location
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                            placeholder="e.g. SLAB-L3, RAFT-01"
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
                            @change="onSiteSelect"
                        />
                    </div>

                    <div>
                        <BaseInput
                            v-model="form.pour_location"
                            type="text"
                            label="Pour Location"
                            placeholder="e.g. Raft Grid A-D, 3rd Floor"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div>
                        <BaseSelect
                            v-model="form.mix_design_id"
                            :options="mixOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Mix Design"
                            placeholder="Select Grade"
                            @change="onMixSelect"
                        />
                    </div>

                    <div>
                        <BaseInputNumber
                            v-model="form.planned_qty_m3"
                            :min="0.5"
                            :step="0.5"
                            :minFractionDigits="1"
                            :maxFractionDigits="2"
                            label="Planned Volume (m³)"
                            placeholder="45.0"
                            required
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Pump Rig & Operator -->
            <div class="space-y-3 pt-1">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <WrenchScrewdriverIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        Pump Rig & Operator
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div>
                        <BaseSelect
                            v-model="form.pump_type"
                            :options="pumpTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Pump Type"
                            required
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.pump_vehicle_id"
                            :options="machineOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Assigned Pump"
                            placeholder="Select Pump Rig"
                            required
                            @change="onPumpSelect"
                        />
                    </div>

                    <div>
                        <BaseInputNumber
                            v-model="form.boom_length_m"
                            :min="1"
                            :step="1"
                            :minFractionDigits="0"
                            :maxFractionDigits="1"
                            :label="form.pump_type === 'boom_pump' ? 'Boom Length (m)' : 'Line Length (m)'"
                            :placeholder="form.pump_type === 'boom_pump' ? '36' : '100'"
                            :required="form.pump_type === 'boom_pump'"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.operator_id"
                            :options="operatorOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Operator"
                            placeholder="Assign Operator"
                            @change="onOperatorSelect"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Status"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Timelines -->
            <div class="space-y-3 pt-1">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <ClockIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        Timelines & Execution
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <BaseDatePicker
                            v-model="form.pump_arrival_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Arrival Time"
                            placeholder="Select Time"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.setup_start_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Setup Start"
                            placeholder="Select Time"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.setup_end_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Setup End"
                            placeholder="Select Time"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <BaseDatePicker
                            v-model="form.pour_start_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Target Pour Start"
                            placeholder="Select Time"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.planned_end_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Planned Finish"
                            placeholder="Select Time"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <BaseDatePicker
                            v-model="form.actual_start_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Actual Start"
                            placeholder="Select Time"
                            @update:modelValue="onActualStartInput"
                        />
                    </div>

                    <div>
                        <BaseDatePicker
                            v-model="form.actual_end_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Actual End"
                            placeholder="Select Time"
                            @update:modelValue="onActualEndInput"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Notes -->
            <div class="pt-1">
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                    Notes
                </label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    placeholder="Add any rigging, site access, or pour notes..."
                    class="w-full text-xs bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2.5 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                ></textarea>
            </div>

            <!-- Form Actions -->
            <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                <BaseButton
                    label="Cancel"
                    severity="secondary"
                    variant="outlined"
                    @click="emit('cancel')"
                />
                <BaseButton
                    :label="isEditing ? 'Save Changes' : 'Create Deployment'"
                    severity="primary"
                    variant="filled"
                    type="submit"
                    :loading="saving"
                    class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-transparent font-semibold"
                />
            </div>
        </form>
    </div>
</template>
