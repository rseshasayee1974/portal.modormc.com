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
    CalendarIcon,
    TruckIcon,
    MapPinIcon,
    BeakerIcon,
    ClockIcon,
    UserIcon,
    DocumentTextIcon,
    ArrowLeftIcon,
    CheckIcon,
    WrenchScrewdriverIcon
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
    batching_time: '',
    eta_site: '',
    notes: '',
});

// Dropdown options
const siteOptions = computed(() => props.dropdowns.sites?.map(s => ({ label: s.name, value: s.id })) || []);
const mixOptions = computed(() => props.dropdowns.mixDesigns?.map(m => ({ label: `${m.name} (${m.code || '-'})`, value: m.id })) || []);
const vehicleOptions = computed(() => props.dropdowns.vehicles?.map(v => ({ label: `${v.registration} (${v.vehicle_model || v.capacity + ' m³' || 'TM'})`, value: v.id })) || []);
const driverOptions = computed(() => props.dropdowns.drivers?.map(d => ({ label: `${d.first_name} ${d.last_name || ''} (${d.phone || d.employee_code || 'Staff'})`, value: d.id })) || []);
const pumpVehicleOptions = computed(() => props.dropdowns.vehicles?.map(v => ({ label: `${v.registration} (${v.vehicle_model || 'Pump'})`, value: v.id })) || []);

const pumpTypeOptions = [
    { label: 'Boom Pump (Articulated Mobile Boom)', value: 'boom_pump' },
    { label: 'Line Pump (Ground Pipeline)', value: 'line_pump' },
    { label: 'Stationary High-Rise Pump', value: 'stationary_pump' },
    { label: 'Crane & Bucket Pour', value: 'crane_bucket' },
    { label: 'Direct Chute Discharge', value: 'direct_pour' },
];

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
            pump_type: item.pump_type || 'boom_pump',
            pump_vehicle_id: item.pump_vehicle_id ? Number(item.pump_vehicle_id) : null,
            sales_order_id: item.sales_order_id ? Number(item.sales_order_id) : null,
            batching_time: item.batching_time ? item.batching_time.substring(0, 16) : '',
            eta_site: item.eta_site ? item.eta_site.substring(0, 16) : '',
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
            batching_time: '',
            eta_site: '',
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

    saving.value = true;
    try {
        if (props.isEditing && props.initialData?.id) {
            await axios.put(route('production.batching-schedules.update', props.initialData.id), form.value);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Schedule updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            await axios.post(route('production.batching-schedules.store'), form.value);
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
            ? Object.values(err.response.data.errors).flat().join('<br>')
            : (err.response?.data?.message || 'Failed to save schedule.');

        Swal.fire({
            icon: 'error',
            title: 'Failed to Save',
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
        <!-- Form Header in Indigo Theme -->
        <div class="px-6 py-4 bg-indigo-50/50 dark:bg-gray-900/60 border-b border-indigo-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md">
                    <TruckIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? `Edit Concrete Batching Trip #${initialData?.id} — ${form.pour_reference}` : 'Schedule Concrete Batching & Transit Mixer Dispatch' }}
                    </h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        Plan batching production, load volume, fleet assignment, and delivery times
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
            
            <!-- SECTION 1: Pour & Destination -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <MapPinIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        1. Schedule Date & Destination Site
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
                            label="Pour Reference / Tag"
                            placeholder="e.g. SLAB-L3-POUR-A, RAFT-01"
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
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.mix_design_id"
                            :options="mixOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Mix Design / Concrete Grade"
                            placeholder="Select Grade"
                            required
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Production Volume Specifications -->
            <div class="space-y-3 bg-indigo-50/40 dark:bg-gray-900/40 p-4 rounded-xl border border-indigo-100 dark:border-gray-700">
                <div class="flex items-center gap-2 pb-1">
                    <BeakerIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        2. Volume Specifications
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <BaseInputNumber
                            v-model="form.qty_m3"
                            :min="0.5"
                            :step="0.5"
                            :minFractionDigits="1"
                            :maxFractionDigits="2"
                            label="Trip / Load Volume (m³)"
                            placeholder="6.0"
                            required
                        />
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 block">Quantity for this specific Transit Mixer batch trip.</span>
                    </div>

                    <div>
                        <BaseInputNumber
                            v-model="form.order_volume_m3"
                            :min="0.5"
                            :step="0.5"
                            :minFractionDigits="1"
                            :maxFractionDigits="2"
                            label="Total Order / Pour Volume (m³)"
                            placeholder="45.0"
                            required
                        />
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 block">Total quantity required for the full structural pour.</span>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Transit Mixer & Driver Logistics -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <TruckIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        3. Fleet & Placement Method
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <BaseSelect
                            v-model="form.vehicle_id"
                            :options="vehicleOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Assigned Transit Mixer (TM)"
                            placeholder="Assign Later"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.driver_id"
                            :options="driverOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Assigned TM Driver"
                            placeholder="Assign Later"
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.pump_type"
                            :options="pumpTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Placement / Pump Method"
                            required
                        />
                    </div>

                    <div>
                        <BaseSelect
                            v-model="form.pump_vehicle_id"
                            :options="pumpVehicleOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Deployed Pump Rig / Machine"
                            placeholder="None / External"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Timelines & Special Notes -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                    <ClockIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        4. Production Timelines & Pour Instructions
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <BaseDatePicker
                            v-model="form.batching_time"
                            :showTime="true"
                            hourFormat="12"
                            label="Target Batching Time"
                            placeholder="Select Batching Time"
                        />
                    </div>

                    <div>
                        <BaseDatePicker
                            v-model="form.eta_site"
                            :showTime="true"
                            hourFormat="12"
                            label="Estimated Site Arrival (ETA)"
                            placeholder="Select Estimated Arrival"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Slump, Admixture & Site Pour Instructions
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="e.g. Slump 120±25mm, add retarder dosage for 30km lead distance, 4th floor line pump..."
                        class="w-full text-xs bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-3 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    ></textarea>
                </div>
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
                    :label="isEditing ? 'Save Changes' : 'Confirm & Create Schedule'"
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
