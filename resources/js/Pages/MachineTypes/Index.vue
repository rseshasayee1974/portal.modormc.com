<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import {
    CpuChipIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, PlusIcon, TagIcon, XMarkIcon, CheckIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';

const page = usePage();

const props = defineProps<{
    machineTypes: any[];
}>();

const searchQuery = ref('');
const editingId = ref<number | null>(null);

const filteredTypes = computed(() => {
    if (!searchQuery.value) return props.machineTypes;
    const q = searchQuery.value.toLowerCase();
    return props.machineTypes.filter((t: any) =>
        t.name && t.name.toLowerCase().includes(q)
    );
});

const form = useForm({
    name: '',
});

const startEdit = (type: any) => {
    editingId.value = type.id;
    form.name = type.name;
    // Scroll to top to focus on form
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('machinetypes.update', editingId.value), {
            onSuccess: () => {
                cancelEdit();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Classification updated', showConfirmButton: false, timer: 3000 });
            }
        });
    } else {
        form.post(route('machinetypes.store'), {
            onSuccess: () => {
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Classification created', showConfirmButton: false, timer: 3000 });
            }
        });
    }
};

const deleteType = (id: number) => {
    Swal.fire({
        title: 'Delete Classification?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('machinetypes.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) cancelEdit();
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 3000 });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 3000 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Machine Classifications">
        <template #header><ModuleSubTopNav /></template>

        <div class=" ">
            <div class="max-w-7xl">

                <!-- ── Top Section: Breadcrumb & Title ── -->
                

                <!-- ── Create / Edit Form Card (Similar to Patron style) ── -->
                <div class="bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300" :class="editingId ? 'ring-2 ring-indigo-500 ring-offset-4 dark:ring-offset-slate-950' : ''">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                                <CpuChipIcon v-if="!editingId" class="w-6 h-6" />
                                <PencilSquareIcon v-else class="w-6 h-6" />
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                                    {{ editingId ? 'Modify Classification' : 'Add New Classification' }}
                                </h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                    Define fleet categories like Truck, Excavator, or Dumper
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitForm" class="flex flex-col md:flex-row items-start gap-4">
                            <div class="flex-1 w-full">
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <TagIcon class="h-4 w-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                                    </div>
                                    <BaseInput
                                        v-model="form.name"
                                        placeholder="Enter classification name (e.g. Tipper Truck, Crane)"
                                        class="!w-full !h-14 !pl-11 !pr-4 !bg-slate-50 dark:!bg-slate-800 !border-slate-100 dark:!border-slate-700 !rounded-2xl !text-sm !font-bold !text-slate-700 dark:!text-slate-200 focus:!ring-4 focus:!ring-indigo-100 dark:focus:!ring-indigo-900/20 focus:!bg-white dark:focus:!bg-slate-900 !transition-all"
                                        :class="{ '!border-red-400': form.errors.name }"
                                    />
                                </div>
                                <small v-if="form.errors.name" class="text-red-500 text-[10px] font-black uppercase mt-2 ml-2 tracking-widest flex items-center gap-1">
                                    <span>⚠</span> {{ form.errors.name }}
                                </small>
                            </div>

                            <div class="flex items-center gap-2 w-full md:w-auto mt-2 md:mt-0">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 md:flex-none flex items-center justify-center gap-3 h-14 px-8 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all duration-200 active:scale-95"
                                >
                                    <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3px]" />
                                    <span v-else class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    {{ editingId ? 'Update' : 'Register' }}
                                </button>
                                
                                <button
                                    v-if="editingId"
                                    @click="cancelEdit"
                                    type="button"
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-all active:scale-95"
                                    title="Cancel Edit"
                                >
                                    <XMarkIcon class="w-6 h-6 font-bold" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── DataTable Section ── -->
                <div class="bg-white dark:bg-slate-900 shadow-lg shadow-slate-200/40 dark:shadow-none rounded-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <DataTable
                        :value="filteredTypes"
                        stripedRows
                        paginator
                        :rows="10"
                        paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                        currentPageReportTemplate="{first}–{last} of {totalRecords}"
                        class="machinetypes-table"
                        row-hover
                    >
                        <template #header>
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-1.5 h-8 bg-indigo-500 rounded-full"></div>
                                    <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">Master List</h3>
                                </div>
                                
                                <div class="relative group w-full sm:w-72">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                                    </div>
                                    <BaseInput
                                        v-model="searchQuery"
                                        placeholder="Quick Search..."
                                        class="!w-full !pl-11 !pr-4 !bg-slate-50 dark:!bg-slate-800 !border-none !rounded-xl !text-xs !font-bold !text-slate-600 dark:!text-slate-300 focus:!ring-4 focus:!ring-indigo-50 dark:focus:!ring-indigo-900/10 transition-all"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- S.No -->
                        <Column header="#" style="width: 72px" align="center">
                            <template #body="slotProps">
                                <span class="text-[11px] font-black text-slate-300 dark:text-slate-700">
                                    {{ (slotProps.index + 1).toString().padStart(2, '0') }}
                                </span>
                            </template>
                        </Column>

                        <!-- Classification Name -->
                        <Column header="Classification Name" sortable field="name">
                            <template #body="slotProps">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-400">
                                        <CpuChipIcon class="w-4 h-4" />
                                    </div>
                                    <span class="font-bold text-slate-700 dark:text-slate-200">
                                        {{ slotProps.data.name }}
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <!-- Status -->
                        <Column header="Status" style="width: 140px">
                            <template #body="slotProps">
                                <div class="flex items-center gap-2">
                                    <span 
                                        class="h-1.5 w-1.5 rounded-full bg-emerald-500" 
                                    ></span>
                                    <span class="text-[9px] font-black uppercase tracking-widest text-emerald-500">
                                        Operational
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <!-- ID -->
                        <Column header="Reference ID" style="width: 120px" align="center">
                            <template #body="slotProps">
                                <span class="text-[10px] font-mono font-bold text-slate-400">#MT-{{ slotProps.data.id }}</span>
                            </template>
                        </Column>

                        <!-- Actions -->
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
                                        @click="deleteType(slotProps.data.id)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95"
                                        title="Remove"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>

                        <!-- Empty State -->
                        <template #empty>
                            <div class="py-20 flex flex-col items-center gap-4">
                                <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                    <CpuChipIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                </div>
                                <div class="text-center">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Data Available</p>
                                    <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Register a new classification record above.</p>
                                </div>
                            </div>
                        </template>
                    </DataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.machinetypes-table .p-datatable-thead > tr > th) {
    @apply !bg-slate-50/50 dark:!bg-slate-950/50 !text-slate-400 !font-black !text-[10px] !uppercase !tracking-[0.2em] !py-6 !border-b !border-slate-100 dark:!border-slate-800 !border-none;
}

:deep(.machinetypes-table .p-datatable-tbody > tr) {
    @apply !transition-all !duration-300;
}

:deep(.machinetypes-table .p-datatable-tbody > tr:hover) {
    @apply !bg-indigo-50/20 dark:!bg-indigo-900/10;
}

:deep(.machinetypes-table .p-datatable-tbody > tr > td) {
    @apply !py-5 !border-b !border-slate-50 dark:!border-slate-800/50 !bg-transparent;
}

:deep(.machinetypes-table .p-paginator) {
    @apply !bg-transparent !border-t !border-slate-100 dark:!border-slate-800 !py-6;
}

:deep(.machinetypes-table .p-paginator-current) {
    @apply !text-[11px] !font-black !text-slate-300 !uppercase !tracking-widest;
}

:deep(.machinetypes-table .p-paginator-element) {
    @apply !text-slate-400 !rounded-2xl !transition-all !w-11 !text-xs !font-black;
}

:deep(.machinetypes-table .p-paginator-element:hover) {
    @apply !bg-indigo-50/50 !text-indigo-600;
}

:deep(.machinetypes-table .p-paginator-element.p-highlight) {
    @apply !bg-indigo-600 !text-white !shadow-xl !shadow-indigo-200 dark:!shadow-none;
}

:deep(.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
    @apply !bg-slate-50/40 dark:!bg-slate-800/20;
}
</style>
