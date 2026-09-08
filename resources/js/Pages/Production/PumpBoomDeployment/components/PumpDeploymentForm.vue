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
    CalendarIcon,
    MapPinIcon,
    BeakerIcon,
    ClockIcon,
    UserIcon,
    DocumentTextIcon,
    ArrowLeftIcon,
    CheckIcon,
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
    { label: 'Boom Pump (Truck-Mounted Articulated)', value: 'boom_pump' },
    { label: 'Line Pump (Ground Pipeline)', value: 'line_pump' },
    { label: 'Stationary High-Rise Pump', value: 'stationary_pump' },
    { label: 'Crane & Bucket Pour', value: 'crane_bucket' },
    { label: 'Direct Chute Discharge', value: 'direct_pour' },
];

const statusOptions = [
    { label: 'Scheduled (Default for new records)', value: 'scheduled' },
    { label: 'En Route (Traveling to Site)', value: 'en_route' },
    { label: 'Setup (Rigging / Outriggers deployed)', value: 'setup' },
    { label: 'Ready (Primed with Slurry & Prepared)', value: 'ready' },
    { label: 'In Progress (Active Pumping)', value: 'in_progress' },
    { label: 'Line Washout (Hopper / Pipe Clean)', value: 'washout' },
    { label: 'Completed (Pour Finalized)', value: 'completed' },
    { label: 'Delayed (Site Access / Slump Delay)', value: 'delayed' },
    { label: 'Cancelled', value: 'cancelled' },
];

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
            pump_type: item.pump_type || 'boom_pump',
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

const estimatedDurationHours = computed(() => {
    const qty = parseFloat(form.value.planned_qty_m3);
    if (isNaN(qty) || qty <= 0) return 0;
    return (qty / 30).toFixed(1);
});

const submitForm = async () => {
    if (!form.value.schedule_date || !form.value.pour_reference?.trim()) {
        Swal.fire('Required Field', 'Every pour must have a schedule date and pour reference.', 'warning');
        return;
    }

    if (!form.value.pour_location?.trim() || !form.value.planned_qty_m3) {
        Swal.fire('Required Fields', 'Please specify Pour Location and Planned Pour Volume.', 'warning');
        return;
    }

    if (!form.value.pump_type || (!form.value.pump_vehicle_id && !form.value.pump_no?.trim())) {
        Swal.fire('Assigned Pump Required', 'Every scheduled pour must have a pump type and assigned pump machine.', 'warning');
        return;
    }

    if (form.value.pump_type === 'boom_pump') {
        const boomLen = parseFloat(form.value.boom_length_m);
        if (isNaN(boomLen) || boomLen <= 0) {
            Swal.fire('Boom Length Required', 'A boom pump must have a boom length in meters (e.g. 36m, 42m).', 'warning');
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
        if (props.isEditing && props.initialData?.id) {
            await axios.put(route('production.pump-deployments.update', props.initialData.id), form.value);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Pump deployment updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            await axios.post(route('production.pump-deployments.store'), form.value);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Pump deployment scheduled successfully',
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
        <!-- Form Header Bar in Indigo Theme -->
        <div class="px-6 py-4 bg-indigo-50/50 dark:bg-gray-900/60 border-b border-indigo-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md">
                    <WrenchScrewdriverIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? `Edit Deployment #${initialData?.id} — ${form.pour_reference}` : 'Schedule New Pour & Pump Deployment' }}
                    </h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        Assign concrete boom pumps, line pumps, operators, and milestone schedules
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="emit('cancel')"
                class="px-3.5 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm"
            >
                <ArrowLeftIcon class="w-3.5 h-3.5" />
                <span>Back to Schedule</span>
            </button>
        </div>

        <form @submit.prevent="submitForm" class="p-6 space-y-6">
            
            <!-- Real-Time Time Validation Warnings / Advisories -->
            <div v-if="form.setup_start_time && form.setup_end_time && new Date(form.setup_start_time) > new Date(form.setup_end_time)" 
                 class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-300 font-bold flex items-center gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 shrink-0" />
                <span>Validation Error: Setup Start Time cannot be later than Setup End Time.</span>
            </div>

            <div v-if="form.actual_start_time && form.actual_end_time && new Date(form.actual_start_time) > new Date(form.actual_end_time)" 
                 class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-300 font-bold flex items-center gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 shrink-0" />
                <span>Validation Error: Actual Start Time cannot be later than Actual End Time.</span>
            </div>

            <div v-if="form.pump_arrival_time && form.setup_start_time && new Date(form.pump_arrival_time) > new Date(form.setup_start_time)" 
                 class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-300 font-medium flex items-center gap-2">
                <InformationCircleIcon class="w-4 h-4 text-amber-600 shrink-0" />
                <span>Operational Advisory: Pump arrival on site is usually prior to setup start.</span>
            </div>

            <!-- SECTION 1: Pour & Location Details -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <MapPinIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        1. Pour Identification & Destination Location
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
                            label="Pour Reference / Code"
                            placeholder="e.g. POUR-2026-0908-01"
                            required
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.site_id"
                            :options="siteOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Delivery Destination Site"
                            placeholder="Select Site"
                            required
                            @change="onSiteSelect"
                        />
                    </div>

                    <div>
                        <BaseInput
                            v-model="form.pour_location"
                            type="text"
                            label="Pour Location / Element"
                            placeholder="e.g. Raft Grid A-D, 3rd Floor"
                            required
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Concrete Recipe & Quantity -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <BeakerIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        2. Concrete Mix & Pour Volume
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <BaseSelect
                            v-model="form.mix_design_id"
                            :options="mixOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Concrete Grade / Recipe"
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
                            label="Planned Pour Volume (m³)"
                            placeholder="45.0"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                            Estimated Pumping Duration
                        </label>
                        <div class="px-3 py-2 bg-indigo-50/40 dark:bg-gray-900/50 border border-indigo-100 dark:border-gray-700 rounded-lg text-xs font-semibold text-indigo-900 dark:text-indigo-300 flex items-center justify-between h-[38px]">
                            <span>Approx. {{ estimatedDurationHours }} hrs</span>
                            <span class="text-[10px] text-gray-400">@ 30 m³/h baseline</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Pump Rig & Reach -->
            <div class="space-y-3 bg-indigo-50/50 dark:bg-indigo-950/20 p-4 rounded-xl border border-indigo-100 dark:border-indigo-900/40">
                <div class="flex items-center gap-2 pb-1">
                    <WrenchScrewdriverIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-indigo-900 dark:text-indigo-200 uppercase tracking-wider">
                        3. Pump Machine & Reach Configuration
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                            label="Assigned Pump Machine / Rig"
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
                            :label="form.pump_type === 'boom_pump' ? 'Boom Vertical/Horizontal Reach (m) *' : 'Pipeline Line Length (m)'"
                            :placeholder="form.pump_type === 'boom_pump' ? 'e.g. 36 (Required)' : 'e.g. 100'"
                            :required="form.pump_type === 'boom_pump'"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Operator & Current Status -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <UserIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        4. Pump Operator & Deployment Status
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <BaseSelect
                            v-model="form.operator_id"
                            :options="operatorOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Pump Operator / Crew Lead"
                            placeholder="Assign Operator Later"
                            @change="onOperatorSelect"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Deployment Status"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 5: Operational Milestone Timelines -->
            <div class="space-y-3 bg-gray-50 dark:bg-gray-900/40 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 pb-1">
                    <ClockIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        5. Operational Milestones & Placement Timelines
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <BaseDatePicker
                            v-model="form.pump_arrival_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Pump Arrival at Site"
                            placeholder="Select Arrival Time"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.setup_start_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Setup / Rigging Start"
                            placeholder="Select Setup Start"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.setup_end_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Setup Finish & Ready"
                            placeholder="Select Ready Time"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <BaseDatePicker
                            v-model="form.pour_start_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Target Pour Start Time"
                            placeholder="Select Pour Start"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.planned_end_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Planned Completion Time"
                            placeholder="Select Target Finish"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <div class="p-3 bg-indigo-50/60 dark:bg-indigo-950/30 rounded-lg border border-indigo-200 dark:border-indigo-900">
                        <label class="block text-[10px] font-bold text-indigo-900 dark:text-indigo-300 uppercase mb-1">
                            Actual Pour Start <span class="text-[9px] font-normal text-indigo-600 dark:text-indigo-400">(Sets status to In Progress)</span>
                        </label>
                        <BaseDatePicker
                            v-model="form.actual_start_time"
                            :showTime="true"
                            hourFormat="12"
                            placeholder="Select Actual Start"
                            @update:modelValue="onActualStartInput"
                        />
                    </div>

                    <div class="p-3 bg-emerald-50/60 dark:bg-emerald-950/30 rounded-lg border border-emerald-200 dark:border-emerald-900">
                        <label class="block text-[10px] font-bold text-emerald-900 dark:text-emerald-300 uppercase mb-1">
                            Actual Pour Finish <span class="text-[9px] font-normal text-emerald-600 dark:text-emerald-400">(Sets status to Completed)</span>
                        </label>
                        <BaseDatePicker
                            v-model="form.actual_end_time"
                            :showTime="true"
                            hourFormat="12"
                            placeholder="Select Actual Finish"
                            @update:modelValue="onActualEndInput"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 6: Site Access & Outrigger Notes -->
            <div class="space-y-2">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <DocumentTextIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        6. Site Rigging, Overhead Clearance & Priming Notes
                    </span>
                </div>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    placeholder="e.g. 8m outrigger footprint clear, overhead high-tension wire 15m away, priming with 2 bags cement slurry..."
                    class="w-full text-xs bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-3 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                ></textarea>
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                <BaseButton
                    label="Cancel"
                    severity="secondary"
                    variant="outlined"
                    @click="emit('cancel')"
                />
                <BaseButton
                    :label="isEditing ? 'Save Deployment Changes' : 'Confirm & Schedule Deployment'"
                    severity="primary"
                    variant="filled"
                    type="submit"
                    :loading="saving"
                    class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-transparent"
                />
            </div>
        </form>
    </div>
</template>
