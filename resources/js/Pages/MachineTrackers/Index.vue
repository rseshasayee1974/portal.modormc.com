<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

import {
    ClockIcon, PencilSquareIcon,
    TrashIcon, PlusIcon, TagIcon, XMarkIcon, CheckIcon, CalendarIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const page = usePage();

interface MachineTracker {
    id: number;
    plant_id: number;
    machine_id: number;
    operation_type: string | null;
    category: string | null;
    operator_id: number | null;
    opening: any;
    closing: any;
    odometer_start: number;
    odometer_end: number;
    hourmeter_start: number;
    hourmeter_end: number;
    eb_start: number;
    eb_close: number;
    opening_hsd: number;
    closing_hsd: number;
    notes: string | null;
    fuel: number;
    fuel_filled_on: any;
    last_fuel_filled_km: number;
    fuel_filled_km: number;
    pump_name: string | null;
    pump_reading: string | null;
    amount: number;
    shift: number;
    created: any;
    created_by: number;
    company_id: number;
    machine?: {
        id: number;
        registration: string;
    };
    operator?: {
        id: number;
        username: string;
    };
}

const props = defineProps<{
    trackers: MachineTracker[];
    machines: any[];
    operators: any[];
}>();

const editingId = ref<number | null>(null);

const expandedRows = ref<any[]>([]);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const machineOptions = computed(() => props.machines.map(m => ({ label: m.registration, value: m.id })));
const operatorOptions = computed(() => props.operators.map(u => ({ label: u.username, value: u.id })));

const shiftOptions = [
    { label: 'Day Shift', value: 1 },
    { label: 'Night Shift', value: 2 },
    { label: 'General Shift', value: 3 },
    { label: 'Not Specified', value: -1 }
];

const getInitialForm = () => ({
    machine_id: null as number | null,
    operation_type: '',
    category: '',
    operator_id: null as number | null,
    opening: null as any,
    closing: null as any,
    odometer_start: 0,
    odometer_end: 0,
    hourmeter_start: 0,
    hourmeter_end: 0,
    eb_start: 0,
    eb_close: 0,
    opening_hsd: 0,
    closing_hsd: 0,
    notes: '',
    fuel: 0,
    fuel_filled_on: null as any,
    last_fuel_filled_km: 0,
    fuel_filled_km: 0,
    pump_name: '',
    pump_reading: '',
    amount: 0,
    shift: -1
});

const form = useForm(getInitialForm());

const startEdit = (tracker: MachineTracker) => {
    editingId.value = tracker.id;
    form.machine_id = tracker.machine_id;
    form.operation_type = tracker.operation_type || '';
    form.category = tracker.category || '';
    form.operator_id = tracker.operator_id;
    form.opening = tracker.opening ? new Date(tracker.opening) : null;
    form.closing = tracker.closing ? new Date(tracker.closing) : null;
    form.odometer_start = Number(tracker.odometer_start);
    form.odometer_end = Number(tracker.odometer_end);
    form.hourmeter_start = Number(tracker.hourmeter_start);
    form.hourmeter_end = Number(tracker.hourmeter_end);
    form.eb_start = Number(tracker.eb_start);
    form.eb_close = Number(tracker.eb_close);
    form.opening_hsd = Number(tracker.opening_hsd);
    form.closing_hsd = Number(tracker.closing_hsd);
    form.notes = tracker.notes || '';
    form.fuel = Number(tracker.fuel);
    form.fuel_filled_on = tracker.fuel_filled_on ? new Date(tracker.fuel_filled_on) : null;
    form.last_fuel_filled_km = Number(tracker.last_fuel_filled_km);
    form.fuel_filled_km = Number(tracker.fuel_filled_km);
    form.pump_name = tracker.pump_name || '';
    form.pump_reading = tracker.pump_reading || '';
    form.amount = Number(tracker.amount);
    form.shift = tracker.shift;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    form.clearErrors();
    let hasError = false;

    if (!form.machine_id) {
        form.setError('machine_id', 'The machine / vehicle field is required.');
        hasError = true;
    }
    
    if (form.shift === null || form.shift === undefined || form.shift === '') {
        form.setError('shift', 'The shift field is required.');
        hasError = true;
    }

    if (form.eb_start === null || form.eb_start === undefined || form.eb_start === 0) {
        form.setError('eb_start', 'The eb start field is required.');
        hasError = true;
    }

    if (form.eb_close === null || form.eb_close === undefined || form.eb_close === 0) {
        form.setError('eb_close', 'The eb close field is required.');
        hasError = true;
    }

    if (form.last_fuel_filled_km === null || form.last_fuel_filled_km === undefined || form.last_fuel_filled_km === 0) {
        form.setError('last_fuel_filled_km', 'The last fuel filled km field is required.');
        hasError = true;
    }

    if (hasError) {
        // Scroll to top so user can see errors
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    if (editingId.value) {
        form.put(route('machine-trackers.update', editingId.value), {
            onSuccess: () => {
                cancelEdit();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tracker log updated', showConfirmButton: false, timer: 1500 });
            }
        });
    } else {
        form.post(route('machine-trackers.store'), {
            onSuccess: () => {
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tracker log saved', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteTracker = (id: number) => {
    Swal.fire({
        title: 'Delete Tracker Entry?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('machine-trackers.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) cancelEdit();
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Daily Machine Tracker">
        <template #header><ModuleSubTopNav /></template>

        <div class="my-5">
            <div class="max-w-7xl">

                <!-- ── Create / Edit Form Card ── -->
                <div class="bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300" :class="editingId ? 'ring-2 ring-indigo-500 ring-offset-4 dark:ring-offset-slate-950' : ''">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                                <ClockIcon v-if="!editingId" class="w-6 h-6" />
                                <PencilSquareIcon v-else class="w-6 h-6" />
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                                    {{ editingId ? 'Modify Daily Tracker Entry' : 'Log Daily Machine Sheet' }}
                                </h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                    Log shift details, runtime meters, energy logs, and fuel refills
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitForm" class="flex flex-col gap-5">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                <!-- Machine and Shift -->
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseSelect v-model="form.machine_id" :options="machineOptions" label="Machine / Vehicle" required optionLabel="label" optionValue="value" placeholder="Select Asset" :error="form.errors.machine_id" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseSelect v-model="form.shift" :options="shiftOptions" label="Shift" required optionLabel="label" optionValue="value" :error="form.errors.shift" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Operator</label>
                                    <BaseSelect v-model="form.operator_id" :options="operatorOptions" optionLabel="label" optionValue="value" placeholder="Select User" :error="form.errors.operator_id" />
                                </div>

                                <!-- Hours / Kms -->
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Odometer Start</label>
                                    <BaseInputNumber v-model="form.odometer_start" placeholder="0" :error="form.errors.odometer_start" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Odometer End</label>
                                    <BaseInputNumber v-model="form.odometer_end" placeholder="0" :error="form.errors.odometer_end" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Hourmeter Start</label>
                                    <BaseInputNumber v-model="form.hourmeter_start" placeholder="0" :error="form.errors.hourmeter_start" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Hourmeter End</label>
                                    <BaseInputNumber v-model="form.hourmeter_end" placeholder="0" :error="form.errors.hourmeter_end" />
                                </div>

                                <!-- EB Start / Close -->
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInputNumber v-model="form.eb_start" required label="EB Start" placeholder="0" :error="form.errors.eb_start" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInputNumber v-model="form.eb_close" required label="EB Close" placeholder="0" :error="form.errors.eb_close" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInputNumber v-model="form.opening_hsd" label="Opening HSD" placeholder="0" :error="form.errors.opening_hsd" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInputNumber v-model="form.closing_hsd" label="Closing HSD" placeholder="0" :error="form.errors.closing_hsd" />
                                </div>

                                <!-- Dates -->
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput type="datetime-local" v-model="form.opening" label="Opening DateTime" :error="form.errors.opening" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput type="datetime-local" v-model="form.closing" label="Closing DateTime" :error="form.errors.closing" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput v-model="form.operation_type" label="Concrete Type" placeholder="E.g. Transport, Excavation" :error="form.errors.operation_type" />
                                </div>

                                <!-- Fuel Details -->
                                <div class="col-span-12 mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                                    <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest mb-4">Fuel Refills & Consumption</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                        <div class="col-span-6 md:col-span-3 field-group">
                                            <BaseInputNumber v-model="form.fuel" label="Fuel Filled (Liters)" placeholder="0" :error="form.errors.fuel" />
                                        </div>
                                        <div class="col-span-6 md:col-span-3 field-group">
                                            <BaseInputNumber v-model="form.amount" label="Fuel Cost Amount" mode="currency" currency="INR" locale="en-IN" placeholder="₹0.00" :error="form.errors.amount" />
                                        </div>
                                        <div class="col-span-6 md:col-span-3 field-group">
                                            <BaseInputNumber v-model="form.last_fuel_filled_km" label="Last Fuel KM" required placeholder="0" :error="form.errors.last_fuel_filled_km" />
                                        </div>
                                        <div class="col-span-6 md:col-span-3 field-group">
                                            <BaseInputNumber v-model="form.fuel_filled_km" label="Fuel Filled KM" placeholder="0" :error="form.errors.fuel_filled_km" />
                                        </div>
                                        
                                        <div class="col-span-6 md:col-span-4 field-group">
                                            <BaseInput v-model="form.pump_name" label="Pump Name" placeholder="Reliance, HP Pump" :error="form.errors.pump_name" />
                                        </div>
                                        <div class="col-span-6 md:col-span-4 field-group">
                                            <BaseInput v-model="form.pump_reading" label="Pump Reading" placeholder="Receipt reading details" :error="form.errors.pump_reading" />
                                        </div>
                                        <div class="col-span-12 md:col-span-4 field-group">
                                            <BaseInput type="datetime-local" v-model="form.fuel_filled_on" label="Fuel Refilled On" :error="form.errors.fuel_filled_on" />
                                        </div>
                                    </div>
                                </div>

                                <div class="col-span-12 field-group">
                                    <BaseInput v-model="form.notes" label="Tracker Notes" placeholder="Log operational issues, site status, operator reports" :error="form.errors.notes" />
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 mt-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex items-center justify-center gap-3 h-12 px-8 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all duration-200 active:scale-95"
                                >
                                    <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3px]" />
                                    <span v-else class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    {{ editingId ? 'Update Tracker Log' : 'Save Tracker Log' }}
                                </button>
                                
                                <button
                                    v-if="editingId"
                                    @click="cancelEdit"
                                    type="button"
                                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-all active:scale-95"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── DataTable Section ── -->
                <div class="bg-white dark:bg-slate-900 shadow-lg shadow-slate-200/40 dark:shadow-none rounded-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <BaseDataTable
                        :value="trackers"
                        v-model:filters="filters"
                        :globalFilterFields="['machine.registration', 'operation_type', 'operator.username', 'notes', 'pump_name', 'category']"
                        showSearch
                        showSerial
                        heading="Tracker Ledger"
                        headingIcon="ClockIcon"
                        :rows="30"
                        v-model:expandedRows="expandedRows"
                        class="tracker-table"
                    >
                        <!-- Row Expansion Trigger -->
                        <Column expander style="width: 3rem" />
                        <!-- Machine -->
                        <Column header="Vehicle" sortable field="machine.registration">
                            <template #body="slotProps">
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-200">
                                    {{ slotProps.data.machine?.registration }}
                                </span>
                            </template>
                        </Column>

                        <!-- Shift -->
                        <Column header="Shift" sortable field="shift">
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-600">
                                    {{ shiftOptions.find(s => s.value === slotProps.data.shift)?.label || 'General' }}
                                </span>
                            </template>
                        </Column>

                        <!-- Operator -->
                        <Column header="Operator">
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-500">
                                    {{ slotProps.data.operator?.username || '—' }}
                                </span>
                            </template>
                        </Column>

                        <!-- Hours Log -->
                        <Column header="Odometer (Start/End)">
                            <template #body="slotProps">
                                <span class="text-xs font-mono font-medium text-slate-600">
                                    {{ Number(slotProps.data.odometer_start) }} – {{ Number(slotProps.data.odometer_end) }}
                                </span>
                            </template>
                        </Column>

                        <!-- Hourmeter Log -->
                        <Column header="Hourmeter (Start/End)">
                            <template #body="slotProps">
                                <span class="text-xs font-mono font-medium text-slate-600">
                                    {{ Number(slotProps.data.hourmeter_start) }} – {{ Number(slotProps.data.hourmeter_end) }}
                                </span>
                            </template>
                        </Column>

                        <!-- EB -->
                        <Column header="EB Start/Close">
                            <template #body="slotProps">
                                <span class="text-xs font-mono text-slate-500">
                                    {{ Number(slotProps.data.eb_start) }} – {{ Number(slotProps.data.eb_close) }}
                                </span>
                            </template>
                        </Column>

                        <!-- Fuel filled -->
                        <Column header="Fuel Filled">
                            <template #body="slotProps">
                                <div class="flex flex-col font-mono text-xs">
                                    <span class="text-emerald-600 font-bold">{{ Number(slotProps.data.fuel) }} L</span>
                                    <span class="text-[9px] text-slate-400">₹{{ Number(slotProps.data.amount).toLocaleString() }}</span>
                                </div>
                            </template>
                        </Column>

                        <!-- Controls -->
                        <Column header="Control" style="width: 120px" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end items-center gap-2">
                                    <button
                                        @click="startEdit(slotProps.data)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 hover:bg-indigo-100 transition-all active:scale-95"
                                        title="Modify"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="deleteTracker(slotProps.data.id)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95"
                                        title="Remove"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>

                        <!-- Row Expansion Template -->
                        <template #expansion="slotProps">
                            <div class="p-6 bg-slate-50/50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800 m-2">
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Detailed Log Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                    <div>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase">Concrete Type</p>
                                        <p class="text-sm font-semibold text-slate-700">{{ slotProps.data.operation_type || '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase">Opening Time</p>
                                        <p class="text-sm font-semibold text-slate-700">{{ slotProps.data.opening ? new Date(slotProps.data.opening).toLocaleString() : '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase">Closing Time</p>
                                        <p class="text-sm font-semibold text-slate-700">{{ slotProps.data.closing ? new Date(slotProps.data.closing).toLocaleString() : '—' }}</p>
                                    </div>
                                    <div class="md:col-span-4">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase">Notes / Remarks</p>
                                        <p class="text-sm font-medium text-slate-600">{{ slotProps.data.notes || 'No notes provided.' }}</p>
                                    </div>
                                    
                                    <div v-if="slotProps.data.pump_name || slotProps.data.pump_reading" class="md:col-span-4 border-t border-slate-200/60 pt-4 mt-2">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-2">Fuel Pump Details</p>
                                        <div class="flex gap-8">
                                            <div>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase">Pump Name</p>
                                                <p class="text-sm font-semibold text-slate-700">{{ slotProps.data.pump_name || '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase">Receipt/Reading</p>
                                                <p class="text-sm font-semibold text-slate-700">{{ slotProps.data.pump_reading || '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase">Refilled On</p>
                                                <p class="text-sm font-semibold text-slate-700">{{ slotProps.data.fuel_filled_on ? new Date(slotProps.data.fuel_filled_on).toLocaleString() : '—' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <template #empty>
                            <div class="py-20 flex flex-col items-center gap-4">
                                <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                    <ClockIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                </div>
                                <div class="text-center">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Tracker Logs</p>
                                    <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Submit a new machine log sheet above.</p>
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
