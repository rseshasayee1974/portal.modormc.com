<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal2 from 'sweetalert2';

import DriverForm from './components/DriverForm.vue';
import DriverEditForm from './components/DriverEditForm.vue';
import { IdentificationIcon } from '@heroicons/vue/24/outline';

// Base Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';

const page = usePage();

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
}

interface Driver {
    id: number;
    plant_id: number;
    entity_id: number;
    personnel_id: number;
    license_number: string;
    license_expiry_date: string | null;
    license_type: string | null;
    status: string;
    personnel?: Personnel;
}

const props = defineProps<{
    drivers: Driver[];
    availablePersonnel: Personnel[];
    licenseTypes: string[];
    statuses: string[];
    genders: string[];
}>();
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

// Watch expandedRows to automatically populate editForm when a row is expanded
watch(expandedRows, (newVal) => {
    const activeIds = Object.keys(newVal).filter(k => newVal[Number(k)]);
    if (activeIds.length > 0) {
        const activeId = Number(activeIds[0]);
        const driver = props.drivers.find(d => d.id === activeId);
        if (driver) {
            editingId.value = driver.id;
            editForm.is_promoting = false;
            editForm.personnel_id = driver.personnel_id;
            editForm.first_name = driver.personnel?.first_name || '';
            editForm.last_name = driver.personnel?.last_name || '';
            editForm.gender = null;
            editForm.date_of_birth = null;
            editForm.joining_date = null;
            
            editForm.license_number = driver.license_number;
            editForm.license_expiry_date = driver.license_expiry_date ? new Date(driver.license_expiry_date) : null;
            editForm.license_type = driver.license_type || 'HMV';
            editForm.status = driver.status;
        }
    } else {
        editingId.value = null;
        editForm.reset();
        editForm.clearErrors();
    }
}, { deep: true });

// PrimeVue DataTable Filter State
const filters = ref({
    global: { value: null as string | null, matchMode: 'contains' }
});

const personnelOptions = computed(() => 
    props.availablePersonnel.map(p => ({
        label: `${p.first_name} ${p.last_name || ''}`.trim(),
        value: p.id
    }))
);

const licenseTypeOptions = computed(() => 
    props.licenseTypes.map(t => ({ label: t, value: t }))
);

const statusOptions = computed(() => 
    props.statuses.map(s => ({ label: s.toUpperCase(), value: s }))
);

const genderOptions = computed(() => 
    props.genders.map(g => ({ label: g, value: g }))
);

const getInitialForm = () => ({
    is_promoting: false,
    personnel_id: null as number | null,
    
    // Personnel creation
    first_name: '',
    last_name: '',
    gender: null as string | null,
    date_of_birth: null as any,
    joining_date: null as any,

    // Driver details
    license_number: '',
    license_expiry_date: null as any,
    license_type: 'HMV',
    status: 'active'
});

const createForm = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

const startEdit = (driver: Driver) => {
    if (expandedRows.value[driver.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [driver.id]: true };
    }
};

const resetEditForm = () => {
    expandedRows.value = {};
};

const submitCreate = () => {
    createForm.post(route('drivers.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.clearErrors();
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Driver registered successfully', showConfirmButton: false, timer: 1500 });
        }
    });
};

const submitEdit = () => {
    if (editingId.value) {
        editForm.put(route('drivers.update', editingId.value), {
            onSuccess: () => {
                resetEditForm();
                Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Driver updated successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteDriver = (id: number) => {
    Swal2.fire({
        title: 'Delete Driver Record?',
        text: "This will soft-delete both the driver credentials and their employee record.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            createForm.delete(route('drivers.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) resetEditForm();
                    Swal2.fire('Deleted!', 'Driver record has been deleted.', 'success');
                }
            });
        }
    });
};

// Calculate Days to Expiry for visual indicators
const getExpiryStatus = (expiryDateStr: string | null) => {
    if (!expiryDateStr) return { text: 'N/A', severity: 'info', class: 'bg-slate-100 text-slate-500' };
    const expiry = new Date(expiryDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const diffTime = expiry.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 0) {
        return { text: 'Expired', class: 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 font-bold' };
    } else if (diffDays <= 30) {
        return { text: `Expiring soon (${diffDays}d)`, class: 'bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 font-bold' };
    } else {
        return { text: `${diffDays} days left`, class: 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 font-medium' };
    }
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Driver Management">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-8">

                    <!-- Creation Form Container -->
                    <div id="top-form-container">
                        <DriverForm 
                            :form="createForm"
                            :personnelOptions="personnelOptions"
                            :genderOptions="genderOptions"
                            :licenseTypeOptions="licenseTypeOptions"
                            :statusOptions="statusOptions"
                            :resetForm="() => createForm.reset()"
                            :submit="submitCreate"
                        />
                    </div>

                    <!-- ── DataTable Section ── -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable
                            :value="drivers"
                            v-model:expandedRows="expandedRows"
                            v-model:filters="filters"
                            dataKey="id"
                            stripedRows
                            heading="Driver Registry"
                            headingIcon="UserGroupIcon"
                            showSearch
                            showSerial
                            paginator
                            :rows="10"
                            :totalRecords="drivers.length"
                            class="p-datatable-sm"
                            showExport
                            exportFilename="drivers-registry-report"
                            :globalFilterFields="['personnel.first_name', 'personnel.last_name', 'license_number', 'license_type']"
                        >
                            <template #toolbar>
                                <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ drivers.length }} total drivers</span>
                                </div>
                            </template>

                            <!-- Driver Name -->
                            <Column header="Driver Name" sortable field="personnel.first_name">
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                            {{ slotProps.data.personnel?.first_name }} {{ slotProps.data.personnel?.last_name || '' }}
                                        </span>
                                        <span class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider mt-0.5">
                                            Emp ID: #{{ slotProps.data.personnel_id }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- License Number -->
                            <Column header="License Number" sortable field="license_number">
                                <template #body="slotProps">
                                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-200">
                                        {{ slotProps.data.license_number }}
                                    </span>
                                </template>
                            </Column>

                            <!-- License Type -->
                            <Column header="License Type" sortable field="license_type">
                                <template #body="slotProps">
                                    <span class="text-xs font-semibold text-slate-650 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 px-2.5 py-1 rounded-lg">
                                        {{ slotProps.data.license_type || '—' }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Expiry Date -->
                            <Column header="License Expiry" sortable field="license_expiry_date">
                                <template #body="slotProps">
                                    <span class="text-xs font-mono text-slate-500">
                                        {{ slotProps.data.license_expiry_date ? new Date(slotProps.data.license_expiry_date).toLocaleDateString() : 'N/A' }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Expiry Alert -->
                            <Column header="Validity">
                                <template #body="slotProps">
                                    <span 
                                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full border border-transparent"
                                        :class="getExpiryStatus(slotProps.data.license_expiry_date).class"
                                    >
                                        {{ getExpiryStatus(slotProps.data.license_expiry_date).text }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Status -->
                            <Column header="Status" sortable field="status">
                                <template #body="slotProps">
                                    <span 
                                        class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                                        :class="{
                                            'bg-emerald-50 text-emerald-600': slotProps.data.status === 'active',
                                            'bg-rose-50 text-rose-600': slotProps.data.status === 'suspended',
                                            'bg-slate-100 text-slate-500': slotProps.data.status === 'inactive'
                                        }"
                                    >
                                        {{ slotProps.data.status }}
                                    </span>
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
                                            @click="deleteDriver(slotProps.data.id)"
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
                                    <DriverEditForm 
                                        :form="editForm"
                                        :driverId="slotProps.data.id"
                                        :genderOptions="genderOptions"
                                        :licenseTypeOptions="licenseTypeOptions"
                                        :statusOptions="statusOptions"
                                        :resetForm="resetEditForm"
                                        :submit="submitEdit"
                                    />
                                </div>
                            </template>

                            <!-- Empty State -->
                            <template #empty>
                                <div class="py-20 flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                        <IdentificationIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Driver Records</p>
                                        <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Register a new driver above.</p>
                                    </div>
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
