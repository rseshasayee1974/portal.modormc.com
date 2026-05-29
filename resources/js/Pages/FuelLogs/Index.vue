<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal2 from 'sweetalert2';

import FuelLogForm from './components/FuelLogForm.vue';
import FuelLogEditForm from './components/FuelLogEditForm.vue';

// Base Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';

import { SwatchIcon, PaperClipIcon, XMarkIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

const page = usePage();

interface Machine {
    id: number;
    registration: string;
}

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
}

interface FuelLog {
    id: number;
    plant_id: number;
    entity_id: number;
    machine_id: number;
    driver_id: number | null;
    log_date: string;
    quantity: number;
    rate_per_liter: number;
    total_amount: number;
    odometer_reading: number;
    hourmeter_reading: number | null;
    pump_name: string | null;
    bill_no: string | null;
    payment_method: string | null;
    attachment_path: string | null;
    notes: string | null;
    machine?: Machine;
    driver?: Personnel;
}

const props = defineProps<{
    fuelLogs: FuelLog[];
    machines: any[];
    drivers: any[];
    paymentMethods: any[];
}>();

// PrimeVue DataTable filter state
const filters = ref({
    global: { value: null as string | null, matchMode: 'contains' }
});

const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

// Upload receipt state
const attachmentFile = ref<File | null>(null);
const showReceiptModal = ref(false);
const activeReceiptUrl = ref('');

const machineOptions = computed(() => 
    props.machines.map(m => ({ label: m.registration, value: m.id }))
);

const driverOptions = computed(() => 
    props.drivers.map(d => ({ label: d.label, value: d.id }))
);

const paymentMethodOptions = computed(() => {
    return Array.isArray(props.paymentMethods) 
        ? props.paymentMethods.map(p => ({ label: p.name || p.label || p, value: p.id || p.value || p }))
        : [];
});

const getInitialForm = () => ({
    machine_id: null as number | null,
    driver_id: null as number | null,
    log_date: new Date(),
    quantity: 0,
    rate_per_liter: 0,
    odometer_reading: 0,
    hourmeter_reading: null as number | null,
    pump_name: '',
    bill_no: '',
    payment_method: 'Cash',
    delete_attachment: false,
    notes: ''
});

const createForm = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

// Auto calculated totals
const createCalculatedTotal = computed(() => {
    const total = (Number(createForm.quantity) || 0) * (Number(createForm.rate_per_liter) || 0);
    return total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});

const editCalculatedTotal = computed(() => {
    const total = (Number(editForm.quantity) || 0) * (Number(editForm.rate_per_liter) || 0);
    return total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});

const handleFileChange = (file: File) => {
    attachmentFile.value = file;
};

const clearFile = () => {
    attachmentFile.value = null;
};

// Watch expandedRows → auto-populate editForm when any row expands
watch(expandedRows, (newVal) => {
    const activeIds = Object.keys(newVal).filter(k => newVal[Number(k)]);
    if (activeIds.length > 0) {
        const activeId = Number(activeIds[0]);
        const log = props.fuelLogs.find(l => l.id === activeId);
        if (log) {
            editingId.value = log.id;
            editForm.machine_id = log.machine_id;
            editForm.driver_id = log.driver_id;
            editForm.log_date = log.log_date ? new Date(log.log_date) : new Date();
            editForm.quantity = Number(log.quantity);
            editForm.rate_per_liter = Number(log.rate_per_liter);
            editForm.odometer_reading = Number(log.odometer_reading);
            editForm.hourmeter_reading = log.hourmeter_reading ? Number(log.hourmeter_reading) : null;
            editForm.pump_name = log.pump_name || '';
            editForm.bill_no = log.bill_no || '';
            editForm.payment_method = log.payment_method || 'Cash';
            editForm.notes = log.notes || '';
            editForm.delete_attachment = false;
            clearFile();
        }
    } else {
        editingId.value = null;
        editForm.reset();
        editForm.clearErrors();
        clearFile();
    }
}, { deep: true });

const startEdit = (log: FuelLog) => {
    if (expandedRows.value[log.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [log.id]: true };
    }
};

const resetEditForm = () => {
    expandedRows.value = {};
};

const submitCreate = () => {
    const formData = new FormData();
    formData.append('machine_id', String(createForm.machine_id || ''));
    formData.append('driver_id', String(createForm.driver_id || ''));
    formData.append('log_date', createForm.log_date ? createForm.log_date.toISOString() : '');
    formData.append('quantity', String(createForm.quantity || 0));
    formData.append('rate_per_liter', String(createForm.rate_per_liter || 0));
    formData.append('odometer_reading', String(createForm.odometer_reading || 0));
    if (createForm.hourmeter_reading !== null) {
        formData.append('hourmeter_reading', String(createForm.hourmeter_reading));
    }
    formData.append('pump_name', createForm.pump_name || '');
    formData.append('bill_no', createForm.bill_no || '');
    formData.append('payment_method', createForm.payment_method || '');
    formData.append('notes', createForm.notes || '');
    
    if (attachmentFile.value) {
        formData.append('attachment', attachmentFile.value);
    }

    createForm.transform(() => formData as any).post(route('fuel-logs.store'), {
        forceFormData: true,
        onSuccess: () => {
            createForm.reset();
            clearFile();
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Refuel logged successfully', showConfirmButton: false, timer: 1500 });
        }
    });
};

const submitEdit = () => {
    if (editingId.value) {
        const formData = new FormData();
        formData.append('machine_id', String(editForm.machine_id || ''));
        formData.append('driver_id', String(editForm.driver_id || ''));
        formData.append('log_date', editForm.log_date ? editForm.log_date.toISOString() : '');
        formData.append('quantity', String(editForm.quantity || 0));
        formData.append('rate_per_liter', String(editForm.rate_per_liter || 0));
        formData.append('odometer_reading', String(editForm.odometer_reading || 0));
        if (editForm.hourmeter_reading !== null) {
            formData.append('hourmeter_reading', String(editForm.hourmeter_reading));
        }
        formData.append('pump_name', editForm.pump_name || '');
        formData.append('bill_no', editForm.bill_no || '');
        formData.append('payment_method', editForm.payment_method || '');
        formData.append('notes', editForm.notes || '');
        formData.append('delete_attachment', editForm.delete_attachment ? '1' : '0');
        formData.append('_method', 'PUT');

        if (attachmentFile.value) {
            formData.append('attachment', attachmentFile.value);
        }

        editForm.transform(() => formData as any).post(route('fuel-logs.update', editingId.value), {
            forceFormData: true,
            onSuccess: () => {
                resetEditForm();
                Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Refuel log updated successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteLog = (id: number) => {
    Swal2.fire({
        title: 'Delete Refueling Log?',
        text: "This action will soft-delete the transaction. Odometer differences may recalculate.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            createForm.delete(route('fuel-logs.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) resetEditForm();
                    Swal2.fire('Deleted!', 'Refuel transaction has been deleted.', 'success');
                }
            });
        }
    });
};

const openReceipt = (path: string) => {
    activeReceiptUrl.value = `/storage/${path}`;
    showReceiptModal.value = true;
};

// Calculate mileage / odometer difference since last refueling log for same machine
const calculateOdoDiff = (currentLog: FuelLog) => {
    const machineLogs = props.fuelLogs
        .filter(l => l.machine_id === currentLog.machine_id && new Date(l.log_date).getTime() < new Date(currentLog.log_date).getTime())
        .sort((a, b) => new Date(b.log_date).getTime() - new Date(a.log_date).getTime());
        
    if (machineLogs.length > 0) {
        const prevOdo = Number(machineLogs[0].odometer_reading);
        const currOdo = Number(currentLog.odometer_reading);
        const diff = currOdo - prevOdo;
        return diff > 0 ? `+${diff.toLocaleString()} km` : `${diff.toLocaleString()} km`;
    }
    return 'First log';
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Fuel Logs">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-8">

                    <!-- Creation Form Container -->
                    <div id="top-form-container">
                        <FuelLogForm 
                            :form="createForm"
                            :machineOptions="machineOptions"
                            :driverOptions="driverOptions"
                            :paymentMethodOptions="paymentMethodOptions"
                            :calculatedTotal="createCalculatedTotal"
                            :attachmentFile="attachmentFile"
                            :resetForm="() => createForm.reset()"
                            :submit="submitCreate"
                            @file-change="handleFileChange"
                            @clear-file="clearFile"
                        />
                    </div>

                    <!-- ── DataTable Section ── -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable
                            :value="fuelLogs"
                            v-model:expandedRows="expandedRows"
                            v-model:filters="filters"
                            dataKey="id"
                            stripedRows
                            heading="Refueling History"
                            headingIcon="SwatchIcon"
                            showSearch
                            showSerial
                            paginator
                            :rows="30"
                            :totalRecords="fuelLogs.length"
                            class="p-datatable-sm"
                            showExport
                            exportFilename="fuel-logs-report"
                            :globalFilterFields="['machine.registration', 'driver.first_name', 'driver.last_name', 'pump_name', 'bill_no', 'payment_method']"
                        >
                            <template #toolbar>
                                <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ fuelLogs.length }} refuels</span>
                                </div>
                            </template>

                            <!-- Log Date -->
                            <Column header="Date & Time" sortable field="log_date">
                                <template #body="slotProps">
                                    <span class="text-xs font-mono text-slate-650 dark:text-slate-350">
                                        {{ new Date(slotProps.data.log_date).toLocaleString([], {year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute:'2-digit'}) }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Vehicle -->
                            <Column header="Vehicle" sortable field="machine.registration">
                                <template #body="slotProps">
                                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-200">
                                        {{ slotProps.data.machine?.registration }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Driver -->
                            <Column header="Driver" sortable field="driver.first_name">
                                <template #body="slotProps">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                        {{ slotProps.data.driver ? `${slotProps.data.driver.first_name} ${slotProps.data.driver.last_name || ''}` : '—' }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Quantity -->
                            <Column header="Fuel Filled">
                                <template #body="slotProps">
                                    <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-100">
                                        {{ Number(slotProps.data.quantity).toLocaleString() }} L
                                    </span>
                                </template>
                            </Column>

                            <!-- Amount details -->
                            <Column header="Rate & Total">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-xs font-mono">
                                        <span class="text-slate-500">Rate: ₹{{ Number(slotProps.data.rate_per_liter).toFixed(2) }}/L</span>
                                        <span class="text-indigo-650 font-bold">Total: ₹{{ Number(slotProps.data.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Odometer details -->
                            <Column header="Odometer">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-xs font-mono">
                                        <span class="text-slate-700 dark:text-slate-200">{{ Number(slotProps.data.odometer_reading).toLocaleString() }} Km</span>
                                        <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">
                                            {{ calculateOdoDiff(slotProps.data) }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Pump / Station & Bill No -->
                            <Column header="Station & Bill">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-xs font-semibold">
                                        <span class="text-slate-650 dark:text-slate-300 truncate max-w-[120px]">{{ slotProps.data.pump_name || '—' }}</span>
                                        <span class="text-[9px] font-mono text-slate-400">Bill: {{ slotProps.data.bill_no || 'N/A' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Payment & Receipt -->
                            <Column header="Receipt & Pay">
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                            {{ slotProps.data.payment_method }}
                                        </span>
                                        <button
                                            v-if="slotProps.data.attachment_path"
                                            @click="openReceipt(slotProps.data.attachment_path)"
                                            class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-650 hover:bg-indigo-100 transition-all cursor-pointer"
                                            title="View Receipt Image"
                                        >
                                            <PaperClipIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </template>
                            </Column>

                            <!-- Actions -->
                            <Column header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="startEdit(slotProps.data)"
                                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-650 hover:bg-indigo-100 transition-all active:scale-95"
                                            title="Modify"
                                        >
                                            <i class="pi pi-pencil text-xs"></i>
                                        </button>
                                        <button
                                            @click="deleteLog(slotProps.data.id)"
                                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95"
                                            title="Remove"
                                        >
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                            </Column>

                            <!-- Row Expansion for Editing -->
                            <template #expansion="slotProps">
                                <div class="p-6 border rounded-xl bg-slate-50/50 dark:bg-slate-800/50">
                                    <FuelLogEditForm 
                                        :form="editForm"
                                        :logId="slotProps.data.id"
                                        :machineOptions="machineOptions"
                                        :driverOptions="driverOptions"
                                        :paymentMethodOptions="paymentMethodOptions"
                                        :calculatedTotal="editCalculatedTotal"
                                        :attachmentFile="attachmentFile"
                                        :resetForm="resetEditForm"
                                        :submit="submitEdit"
                                        @file-change="handleFileChange"
                                        @clear-file="clearFile"
                                    />
                                </div>
                            </template>

                            <!-- Empty State -->
                            <template #empty>
                                <div class="py-20 flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                        <SwatchIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Refueling Records</p>
                                        <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Register a new refueling log above.</p>
                                    </div>
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Receipt Modal ── -->
        <div v-if="showReceiptModal" @click="showReceiptModal = false" class="fixed inset-0 z-[100] bg-slate-900/85 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.stop class="bg-white dark:bg-slate-900 rounded-3xl overflow-hidden max-w-xl w-full border border-slate-100 dark:border-slate-800 shadow-2xl relative">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-750 dark:text-slate-300">Fuel Receipt Attachment</h3>
                    <button @click="showReceiptModal = false" class="text-slate-400 hover:text-slate-600">
                        <XMarkIcon class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 flex justify-center items-center bg-slate-100/50 dark:bg-slate-950/20 max-h-[60vh] overflow-y-auto">
                    <img :src="activeReceiptUrl" alt="Fuel Receipt" class="max-w-full h-auto object-contain rounded-xl shadow-lg border border-slate-200/50" />
                </div>
                <div class="p-4 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <a :href="activeReceiptUrl" target="_blank" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-650 text-white hover:bg-indigo-700 text-xs font-bold transition-all shadow-lg shadow-indigo-100 dark:shadow-none">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                        Open in New Tab
                    </a>
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
