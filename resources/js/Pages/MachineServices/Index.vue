<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleDate, entityCalendarDate } from '@/Utils/entityDateTime';
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal2 from 'sweetalert2';

import {
    WrenchIcon, PencilSquareIcon, TrashIcon, CheckIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import MachineServiceEditForm from './MachineServiceEditForm.vue';

const page = usePage();

interface MachineService {
    id: number;
    plant_id: number;
    truck_id: number;
    service_type: number;
    last_service_km: number;
    next_service_km: number;
    current_running_km: number;
    service_hr_km: string | null;
    service_date: any;
    notes: string | null;
    status: number;
    machine?: {
        id: number;
        registration: string;
    };
}

const props = defineProps<{
    services: MachineService[];
    machines: any[];
}>();

const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const machineOptions = computed(() => props.machines.map(m => ({ label: m.registration, value: m.id })));

const serviceTypes = [
    { label: 'First Service', value: 1 },
    { label: 'General Service', value: 2 },
    { label: 'Major Overhaul', value: 3 },
    { label: 'Engine Tune-up', value: 4 },
    { label: 'Oil & Filter Change', value: 5 }
];

const serviceStatuses = [
    { label: 'Active', value: 1 },
    { label: 'Completed', value: 2 },
    { label: 'Pending Info', value: 3 }
];

const getInitialForm = () => ({
    truck_id: null as number | null,
    service_type: 2,
    last_service_km: 0,
    next_service_km: 0,
    current_running_km: 0,
    service_hr_km: '',
    service_date: null as any,
    notes: '',
    status: 1
});

const form = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

const startEdit = (service: MachineService) => {
    if (editingId.value === service.id) {
        cancelEdit();
        return;
    }

    editingId.value = service.id;
    editForm.truck_id = service.truck_id;
    editForm.service_type = service.service_type;
    editForm.last_service_km = Number(service.last_service_km);
    editForm.next_service_km = Number(service.next_service_km);
    editForm.current_running_km = Number(service.current_running_km);
    editForm.service_hr_km = service.service_hr_km || '';
    editForm.service_date = service.service_date ? entityCalendarDate(service.service_date) : null;
    editForm.notes = service.notes || '';
    editForm.status = service.status;
    expandedRows.value = { [service.id]: true };
};

const cancelEdit = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
};

const submitCreate = () => {
    form.post(route('machine-services.store'), {
        onSuccess: () => {
            form.reset();
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Service registered', showConfirmButton: false, timer: 1500 });
        }
    });
};

const submitEdit = () => {
    if (!editingId.value) return;
    editForm.put(route('machine-services.update', editingId.value), {
        onSuccess: () => {
            cancelEdit();
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Service updated', showConfirmButton: false, timer: 1500 });
        }
    });
};

const deleteService = (id: number) => {
    Swal2.fire({
        title: 'Delete Service Entry?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('machine-services.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) cancelEdit();
                    Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Fleet Service Logging">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="my-5">
            <div class="max-w-7xl">

                <!-- ── Create Form Card ── -->
                <div class="bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300"
                    >
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-8">
                            <div
                                class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                                <WrenchIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <h2
                                    class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                                    Register Service Log
                                </h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                    Define service schedules, vehicle run tracking, and notes
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitCreate" class="flex flex-col gap-5">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Machine / Vehicle *</label>
                                    <BaseSelect v-model="form.truck_id" :options="machineOptions" optionLabel="label"
                                        optionValue="value" placeholder="Select Asset" :error="form.errors.truck_id" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Service Type *</label>
                                    <BaseSelect v-model="form.service_type" :options="serviceTypes" optionLabel="label"
                                        optionValue="value" :error="form.errors.service_type" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Service Date</label>
                                    <DatePicker v-model="form.service_date" dateFormat="yy-mm-dd"
                                        class="!w-full h-10" />
                                </div>

                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Last Service Km *</label>
                                    <BaseInputNumber v-model="form.last_service_km" placeholder="0"
                                        :error="form.errors.last_service_km" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Next Service Km *</label>
                                    <BaseInputNumber v-model="form.next_service_km" placeholder="0"
                                        :error="form.errors.next_service_km" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Current Running Km *</label>
                                    <BaseInputNumber v-model="form.current_running_km" placeholder="0"
                                        :error="form.errors.current_running_km" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInput v-model="form.service_hr_km" label="Service Hr/Km"
                                        placeholder="Hr/Km value" />
                                </div>

                                <div class="col-span-12 md:col-span-9 field-group">
                                    <BaseInput v-model="form.notes" label="Service Notes"
                                        placeholder="Record maintenance description" />
                                </div>
                                <div class="col-span-12 md:col-span-3 field-group">
                                    <label class="field-label">Status</label>
                                    <BaseSelect v-model="form.status" :options="serviceStatuses" optionLabel="label"
                                        optionValue="value" />
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 mt-4">
                                <button type="submit" :disabled="form.processing"
                                    class="flex items-center justify-center gap-3 h-12 px-8 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all duration-200 active:scale-95">
                                    <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3px]" />
                                    <span v-else
                                        class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    Register Service Log
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── DataTable Section ── -->
                <div
                    class="bg-white dark:bg-slate-900 shadow-lg shadow-slate-200/40 dark:shadow-none rounded-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <BaseDataTable :value="services" v-model:filters="filters" v-model:expandedRows="expandedRows"
                        :globalFilterFields="['machine.registration', 'notes']" showSearch showSerial
                        heading="Service History Log" headingIcon="WrenchIcon" :rows="30" :expandOnRowClick="false">

                        <Column expander class="!hidden" style="width: 0; padding: 0; display: none !important;" />

                        <!-- Machine -->
                        <Column header="Vehicle" sortable field="machine.registration">
                            <template #body="slotProps">
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-200">
                                    {{ slotProps.data.machine?.registration }}
                                </span>
                            </template>
                        </Column>

                        <!-- Service Type -->
                        <Column header="Service Detail">
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    {{serviceTypes.find(t => t.value === slotProps.data.service_type)?.label || 'Other'
                                    }}
                                </span>
                            </template>
                        </Column>

                        <!-- Running Kms -->
                        <Column header="Last / Next Km">
                            <template #body="slotProps">
                                <div class="flex flex-col text-xs font-mono font-medium">
                                    <span class="text-slate-600 dark:text-slate-300">Last: {{
                                        Number(slotProps.data.last_service_km).toLocaleString(APP_LOCALE) }} Km</span>
                                    <span class="text-indigo-600 font-bold">Next: {{
                                        Number(slotProps.data.next_service_km).toLocaleString(APP_LOCALE) }} Km</span>
                                </div>
                            </template>
                        </Column>

                        <!-- Current running Kms -->
                        <Column header="Current Running Km">
                            <template #body="slotProps">
                                <span class="text-xs font-mono text-slate-600 dark:text-slate-300">
                                    {{ Number(slotProps.data.current_running_km).toLocaleString(APP_LOCALE) }} Km
                                </span>
                            </template>
                        </Column>

                        <!-- Date -->
                        <Column header="Service Date" sortable field="service_date">
                            <template #body="slotProps">
                                <span class="text-xs font-mono text-slate-500">
                                    {{ slotProps.data.service_date ? entityLocaleDate(slotProps.data.service_date,) :
                                        'N/A' }}
                                </span>
                            </template>
                        </Column>

                        <!-- Notes -->
                        <Column header="Notes">
                            <template #body="slotProps">
                                <span class="text-xs text-slate-400">
                                    {{ slotProps.data.notes || '—' }}
                                </span>
                            </template>
                        </Column>

                        <!-- Status -->
                        <Column header="Status" sortable field="status">
                            <template #body="slotProps">
                                <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-emerald-50 text-emerald-600': slotProps.data.status === 2,
                                        'bg-indigo-50 text-indigo-600': slotProps.data.status === 1,
                                        'bg-slate-100 text-slate-500': slotProps.data.status === 3
                                    }">
                                    {{serviceStatuses.find(s => s.value === slotProps.data.status)?.label}}
                                </span>
                            </template>
                        </Column>

                        <!-- Controls -->
                        <Column header="Control" style="width: 120px" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end items-center gap-2">
                                    <button @click="startEdit(slotProps.data)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 hover:bg-indigo-100 transition-all active:scale-95"
                                        title="Modify">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="deleteService(slotProps.data.id)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95"
                                        title="Remove">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <MachineServiceEditForm
                                v-if="editingId === slotProps.data.id"
                                :form="editForm"
                                :editingId="slotProps.data.id"
                                :machineOptions="machineOptions"
                                :serviceTypes="serviceTypes"
                                :serviceStatuses="serviceStatuses"
                                @submit="submitEdit"
                                @cancel="cancelEdit"
                            />
                        </template>

                        <!-- Empty State -->
                        <template #empty>
                            <div class="py-20 flex flex-col items-center gap-4">
                                <div
                                    class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                    <WrenchIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                </div>
                                <div class="text-center">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No
                                        Service Records</p>
                                    <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Register
                                        a new machine service log above.</p>
                                </div>
                            </div>
                        </template>
                    </BaseDataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datepicker-input) {
    @apply h-10 text-sm font-bold border-slate-200 rounded-md !bg-white;
}
</style>
