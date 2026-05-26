<script setup lang="ts">
usePage;
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal2 from 'sweetalert2';

// Base Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

import {
    CpuChipIcon,
    PlusIcon,
    PencilSquareIcon,
    XMarkIcon,
    SignalIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

interface Machine {
    id: number;
    registration: string;
    vehicle_model: string;
}

interface GpsDevice {
    id: number;
    plant_id: number;
    machine_id: number | null;
    imei: string;
    device_model: string;
    sim_number: string | null;
    phone_number: string | null;
    is_active: boolean;
    last_activity: string | null;
    notes: string | null;
    machine?: Machine;
}

const props = defineProps<{
    devices: GpsDevice[];
    availableMachines: Machine[];
    deviceModels: string[];
    statuses: { label: string; value: number }[];
}>();

const page = usePage();
const showCreateForm = ref(false);
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

// Available machines options mapping
const machineOptions = computed(() => {
    return props.availableMachines.map(m => ({
        label: `${m.registration} (${m.vehicle_model})`,
        value: m.id
    }));
});

// Edit machine options (need to append the currently assigned machine for the device being edited)
const getEditMachineOptions = (currentMachineId: number | null, currentMachine?: Machine) => {
    const list = [...props.availableMachines];
    if (currentMachineId && currentMachine && !list.some(m => m.id === currentMachineId)) {
        list.push(currentMachine);
    }
    return list.map(m => ({
        label: `${m.registration} (${m.vehicle_model})`,
        value: m.id
    }));
};

const getInitialForm = () => ({
    imei: '',
    device_model: 'TK103',
    sim_number: '',
    phone_number: '',
    machine_id: null as number | null,
    is_active: true,
    notes: ''
});

const createForm = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

const toggleCreateForm = () => {
    showCreateForm.value = !showCreateForm.value;
    createForm.reset();
    createForm.clearErrors();
};

// Watch expandedRows → auto-populate editForm
watch(expandedRows, (newVal) => {
    const activeIds = Object.keys(newVal).filter(k => newVal[Number(k)]);
    if (activeIds.length > 0) {
        const activeId = Number(activeIds[0]);
        const device = props.devices.find(d => d.id === activeId);
        if (device) {
            editingId.value = device.id;
            editForm.imei = device.imei;
            editForm.device_model = device.device_model;
            editForm.sim_number = device.sim_number || '';
            editForm.phone_number = device.phone_number || '';
            editForm.machine_id = device.machine_id;
            editForm.is_active = device.is_active;
            editForm.notes = device.notes || '';
        }
    } else {
        editingId.value = null;
        editForm.reset();
        editForm.clearErrors();
    }
}, { deep: true });

const startEdit = (device: GpsDevice) => {
    if (expandedRows.value[device.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [device.id]: true };
    }
};

const resetEditForm = () => {
    expandedRows.value = {};
};

const submitCreate = () => {
    createForm.post(route('gps-devices.store'), {
        onSuccess: () => {
            toggleCreateForm();
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'GPS device registered successfully', showConfirmButton: false, timer: 1500 });
        }
    });
};

const submitEdit = () => {
    if (editingId.value) {
        editForm.put(route('gps-devices.update', editingId.value), {
            onSuccess: () => {
                resetEditForm();
                Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'GPS device updated successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteDevice = (id: number) => {
    Swal2.fire({
        title: 'De-register GPS Device?',
        text: "This will remove the tracking hardware mapping for this vehicle.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Remove',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            createForm.delete(route('gps-devices.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) resetEditForm();
                    Swal2.fire('Removed!', 'Device has been de-registered.', 'success');
                }
            });
        }
    });
};

const formatLastSeen = (timestamp: string | null) => {
    if (!timestamp) return 'Never connected';
    const date = new Date(timestamp);
    const diffMs = new Date().getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 2) return 'Online';
    if (diffMins < 60) return `${diffMins}m ago`;
    
    const diffHrs = Math.floor(diffMins / 60);
    if (diffHrs < 24) return `${diffHrs}h ago`;

    return date.toLocaleDateString();
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="GPS Devices">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-8">
                    
                    <!-- Title Bar -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight">GPS Device Management</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase mt-1">Register and assign GPS tracking hardware to fleet vehicles</p>
                        </div>
                        <BaseButton 
                            :label="showCreateForm ? 'Cancel Registration' : 'Register New GPS'" 
                            :icon="showCreateForm ? XMarkIcon : PlusIcon"
                            :severity="showCreateForm ? 'secondary' : 'primary'"
                            @click="toggleCreateForm"
                        />
                    </div>

                    <!-- Registration Form -->
                    <div v-if="showCreateForm" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 p-6 rounded-2xl shadow-sm transition-all duration-300">
                        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <CpuChipIcon class="w-5 h-5 text-indigo-500" />
                            <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">Register GPS Tracker</span>
                        </div>

                        <form @submit.prevent="submitCreate" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput v-model="createForm.imei" label="Device IMEI / Unique ID *" placeholder="Enter 15-digit IMEI" :error="createForm.errors.imei" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Device Model *</label>
                                    <BaseSelect v-model="createForm.device_model" :options="deviceModels.map(m => ({ label: m, value: m }))" optionLabel="label" optionValue="value" placeholder="Select Model" :error="createForm.errors.device_model" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Assign to Vehicle</label>
                                    <BaseSelect v-model="createForm.machine_id" :options="machineOptions" optionLabel="label" optionValue="value" placeholder="Select Vehicle (Optional)" :error="createForm.errors.machine_id" filter />
                                </div>

                                <div class="col-span-6 md:col-span-4 field-group">
                                    <BaseInput v-model="createForm.sim_number" label="SIM Card Serial No" placeholder="Enter ICCID" :error="createForm.errors.sim_number" />
                                </div>
                                <div class="col-span-6 md:col-span-4 field-group">
                                    <BaseInput v-model="createForm.phone_number" label="SIM Phone Number" placeholder="Enter SIM Mobile No" :error="createForm.errors.phone_number" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Status *</label>
                                    <BaseSelect v-model="createForm.is_active" :options="statuses" optionLabel="label" optionValue="value" :error="createForm.errors.is_active" />
                                </div>

                                <div class="col-span-12 field-group">
                                    <BaseInput v-model="createForm.notes" label="Notes" placeholder="Additional details, mounting locations, wiring instructions..." :error="createForm.errors.notes" />
                                </div>
                            </div>

                            <BaseFormActions 
                                :loading="createForm.processing"
                                save-label="Register GPS"
                                mode="create"
                                class="pt-6 border-t border-gray-100 dark:border-gray-800"
                                @cancel="toggleCreateForm"
                                @submit="submitCreate"
                            />
                        </form>
                    </div>

                    <!-- Datatable -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable
                            :value="devices"
                            v-model:expandedRows="expandedRows"
                            dataKey="id"
                            stripedRows
                            heading="Registered Hardware"
                            headingIcon="CpuChipIcon"
                            showSerial
                            paginator
                            :rows="10"
                            :totalRecords="devices.length"
                            class="p-datatable-sm"
                        >
                            <!-- IMEI -->
                            <Column header="IMEI / ID" sortable field="imei">
                                <template #body="slotProps">
                                    <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                                        {{ slotProps.data.imei }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Model -->
                            <Column header="Hardware Model" sortable field="device_model">
                                <template #body="slotProps">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                                        {{ slotProps.data.device_model }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Assigned Vehicle -->
                            <Column header="Assigned Vehicle" sortable field="machine.registration">
                                <template #body="slotProps">
                                    <span v-if="slotProps.data.machine" class="text-xs font-mono font-bold text-indigo-650 bg-indigo-50/50 dark:bg-indigo-950/20 px-2.5 py-1 rounded-lg border border-indigo-100/50 dark:border-indigo-900/30">
                                        {{ slotProps.data.machine.registration }}
                                    </span>
                                    <span v-else class="text-[10px] font-bold text-rose-500 uppercase tracking-widest bg-rose-50/50 dark:bg-rose-950/10 px-2.5 py-1 rounded-lg border border-rose-100/50 dark:border-rose-900/20 inline-flex items-center gap-1">
                                        <ExclamationTriangleIcon class="w-3.5 h-3.5 stroke-[2px]" />
                                        Unassigned
                                    </span>
                                </template>
                            </Column>

                            <!-- Connectivity / Heartbeat -->
                            <Column header="GPS Connection">
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full" 
                                             :class="[
                                                 slotProps.data.is_active && formatLastSeen(slotProps.data.last_activity) === 'Online'
                                                     ? 'bg-emerald-500 animate-pulse'
                                                     : 'bg-slate-400'
                                             ]"
                                        ></div>
                                        <span class="text-xs font-semibold text-slate-650 dark:text-slate-350">
                                            {{ slotProps.data.is_active ? formatLastSeen(slotProps.data.last_activity) : 'Disabled' }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- SIM Phone -->
                            <Column header="SIM Contact">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-xs">
                                        <span class="font-mono text-slate-700 dark:text-slate-350">{{ slotProps.data.phone_number || '—' }}</span>
                                        <span class="text-[9px] font-mono text-slate-400">{{ slotProps.data.sim_number || '—' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Actions -->
                            <Column header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="startEdit(slotProps.data)"
                                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-650 hover:bg-indigo-100 transition-all active:scale-95 cursor-pointer"
                                            title="Edit Device"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteDevice(slotProps.data.id)"
                                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95 cursor-pointer"
                                            title="Delete Device"
                                        >
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                            </Column>

                            <!-- Row Expansion for Editing -->
                            <template #expansion="slotProps">
                                <div class="p-6 border rounded-xl bg-slate-50/50 dark:bg-slate-800/50">
                                    <div class="flex items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                                        <div class="flex items-center gap-2">
                                            <PencilSquareIcon class="w-5 h-5 text-indigo-500" />
                                            <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">
                                                Edit Hardware Profile: <span class="text-indigo-650 dark:text-indigo-400 font-mono">{{ slotProps.data.imei }}</span>
                                            </span>
                                        </div>
                                        <BaseButton label="Cancel" text severity="secondary" @click="resetEditForm" size="small" />
                                    </div>

                                    <form @submit.prevent="submitEdit" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                            <div class="col-span-12 md:col-span-4 field-group">
                                                <BaseInput v-model="editForm.imei" label="Device IMEI / Unique ID *" placeholder="Enter 15-digit IMEI" :error="editForm.errors.imei" />
                                            </div>
                                            <div class="col-span-12 md:col-span-4 field-group">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Device Model *</label>
                                                <BaseSelect v-model="editForm.device_model" :options="deviceModels.map(m => ({ label: m, value: m }))" optionLabel="label" optionValue="value" placeholder="Select Model" :error="editForm.errors.device_model" />
                                            </div>
                                            <div class="col-span-12 md:col-span-4 field-group">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Assign to Vehicle</label>
                                                <BaseSelect v-model="editForm.machine_id" :options="getEditMachineOptions(slotProps.data.machine_id, slotProps.data.machine)" optionLabel="label" optionValue="value" placeholder="Select Vehicle (Optional)" :error="editForm.errors.machine_id" filter />
                                            </div>

                                            <div class="col-span-6 md:col-span-4 field-group">
                                                <BaseInput v-model="editForm.sim_number" label="SIM Card Serial No" placeholder="Enter ICCID" :error="editForm.errors.sim_number" />
                                            </div>
                                            <div class="col-span-6 md:col-span-4 field-group">
                                                <BaseInput v-model="editForm.phone_number" label="SIM Phone Number" placeholder="Enter SIM Mobile No" :error="editForm.errors.phone_number" />
                                            </div>
                                            <div class="col-span-12 md:col-span-4 field-group">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Status *</label>
                                                <BaseSelect v-model="editForm.is_active" :options="statuses" optionLabel="label" optionValue="value" :error="editForm.errors.is_active" />
                                            </div>

                                            <div class="col-span-12 field-group">
                                                <BaseInput v-model="editForm.notes" label="Notes" placeholder="Additional details..." :error="editForm.errors.notes" />
                                            </div>
                                        </div>

                                        <BaseFormActions 
                                            :loading="editForm.processing"
                                            update-label="Update Details"
                                            mode="update"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-800"
                                            @cancel="resetEditForm"
                                            @submit="submitEdit"
                                        />
                                    </form>
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold uppercase text-[10px] tracking-wider py-4;
}
</style>
