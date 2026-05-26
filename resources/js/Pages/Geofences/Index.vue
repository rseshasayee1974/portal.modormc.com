<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal2 from 'sweetalert2';

// Sub Components
import GeofenceForm from './components/GeofenceForm.vue';
import GeofenceEditForm from './components/GeofenceEditForm.vue';

// Base Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';

// Icons
import {
    PlusIcon,
    PencilSquareIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

interface Geofence {
    id: number;
    plant_id: number;
    name: string;
    description: string | null;
    shape: 'circle' | 'polygon';
    coordinates: any;
    is_active: boolean;
}

const props = defineProps<{
    geofences: Geofence[];
    shapes: { label: string; value: string }[];
}>();

const page = usePage();
const showCreateForm = ref(false);
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const createForm = useForm({
    name: '',
    description: '',
    shape: 'circle' as 'circle' | 'polygon',
    coordinates: null as any,
    is_active: true,
});

const editForm = useForm({
    name: '',
    description: '',
    shape: 'circle' as 'circle' | 'polygon',
    coordinates: null as any,
    is_active: true,
});

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
        const geofence = props.geofences.find(g => g.id === activeId);
        if (geofence) {
            editingId.value = geofence.id;
            editForm.name = geofence.name;
            editForm.description = geofence.description || '';
            editForm.shape = geofence.shape;
            editForm.coordinates = geofence.coordinates;
            editForm.is_active = geofence.is_active;
        }
    } else {
        editingId.value = null;
        editForm.reset();
        editForm.clearErrors();
    }
}, { deep: true });

const startEdit = (geofence: Geofence) => {
    if (expandedRows.value[geofence.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [geofence.id]: true };
    }
};

const resetEditForm = () => {
    expandedRows.value = {};
};

const submitGeofence = () => {
    if (!createForm.coordinates) {
        Swal2.fire({ icon: 'warning', title: 'Empty boundary', text: 'Please click on the map to define the geofence area first.' });
        return;
    }

    createForm.post(route('geofences.store'), {
        onSuccess: () => {
            toggleCreateForm();
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Geofence created successfully', showConfirmButton: false, timer: 1500 });
        }
    });
};

const submitEdit = () => {
    if (!editForm.coordinates) {
        Swal2.fire({ icon: 'warning', title: 'Empty boundary', text: 'Please click on the map to define the geofence area first.' });
        return;
    }

    if (editingId.value) {
        editForm.put(route('geofences.update', editingId.value), {
            onSuccess: () => {
                resetEditForm();
                Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Geofence updated successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteGeofence = (id: number) => {
    Swal2.fire({
        title: 'Delete Geofence?',
        text: "This geofence will be removed. Historical enter/exit records remain unchanged.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            createForm.delete(route('geofences.destroy', id), {
                onSuccess: () => {
                    Swal2.fire('Deleted!', 'Geofence has been removed.', 'success');
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
    <AppLayout title="Geofences">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-8">

                    <!-- Header -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight">Geofence Settings</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase mt-1">Define custom zones for vehicle alerts</p>
                        </div>
                        <BaseButton 
                            :label="showCreateForm ? 'Cancel Creation' : 'Create Geofence'" 
                            :icon="showCreateForm ? XMarkIcon : PlusIcon"
                            :severity="showCreateForm ? 'secondary' : 'primary'"
                            @click="toggleCreateForm"
                        />
                    </div>

                    <!-- Creation Form Component -->
                    <div v-if="showCreateForm" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 p-6 rounded-2xl shadow-sm">
                        <GeofenceForm 
                            :form="createForm" 
                            :shapes="shapes" 
                            :resetForm="toggleCreateForm" 
                            :submit="submitGeofence" 
                        />
                    </div>

                    <!-- Geofence list table (visible when not creating) -->
                    <div v-if="!showCreateForm" class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable
                            :value="geofences"
                            v-model:expandedRows="expandedRows"
                            dataKey="id"
                            stripedRows
                            heading="Geofence list"
                            headingIcon="MapIcon"
                            showSerial
                            paginator
                            :rows="10"
                            :totalRecords="geofences.length"
                            class="p-datatable-sm"
                        >
                            <!-- Name -->
                            <Column header="Geofence Name" sortable field="name">
                                <template #body="slotProps">
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        {{ slotProps.data.name }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Shape -->
                            <Column header="Shape" sortable field="shape">
                                <template #body="slotProps">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                          :class="[
                                              slotProps.data.shape === 'circle'
                                                  ? 'text-indigo-650 bg-indigo-50 border-indigo-100'
                                                  : 'text-emerald-650 bg-emerald-50 border-emerald-100'
                                          ]"
                                    >
                                        {{ slotProps.data.shape }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Description -->
                            <Column header="Description" field="description">
                                <template #body="slotProps">
                                    <span class="text-xs text-slate-500">
                                        {{ slotProps.data.description || '—' }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Status -->
                            <Column header="Status" sortable field="is_active">
                                <template #body="slotProps">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                          :class="[
                                              slotProps.data.is_active
                                                  ? 'text-emerald-650 bg-emerald-50 border-emerald-100'
                                                  : 'text-slate-500 bg-slate-50 border-slate-200'
                                          ]"
                                    >
                                        {{ slotProps.data.is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Actions -->
                            <Column header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="startEdit(slotProps.data)"
                                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-650 hover:bg-indigo-100 transition-all active:scale-95 cursor-pointer"
                                            title="Modify Geofence"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteGeofence(slotProps.data.id)"
                                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95 cursor-pointer"
                                            title="Delete Geofence"
                                        >
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                            </Column>

                            <!-- Row Expansion for Editing -->
                            <template #expansion="slotProps">
                                <div class="p-6 border rounded-xl bg-slate-50/50 dark:bg-slate-800/50">
                                    <GeofenceEditForm 
                                        :form="editForm" 
                                        :geofenceId="slotProps.data.id" 
                                        :shapes="shapes" 
                                        :resetForm="resetEditForm" 
                                        :submit="submitEdit" 
                                    />
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
