<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import Swal from 'sweetalert2';
import { PencilSquareIcon, TrashIcon, PlusIcon, FolderOpenIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

interface Department {
    id: number;
    name: string;
    code: string | null;
}

const props = defineProps<{
    departments: Department[];
}>();

const page = usePage();
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    code: '',
});

const editDepartment = (dept: Department) => {
    editingId.value = dept.id;
    form.name = dept.name;
    form.code = dept.code || '';
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('departments.update', editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post(route('departments.store'), {
            onSuccess: () => resetForm(),
        });
    }
};

const deleteDepartment = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
    router.delete(route('departments.destroy', id), {
                preserveScroll: true,
                preserveState: true
            });
        }
    });
};

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                icon: 'success',
                title: flash.success
            });
        }
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <AppLayout title="Department Management">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-6">
                    
                    <!-- Form Container (col-span-3 standard grid layout) -->
                    <BaseCard class="text-sm">
                        <template #header>
                            <div class="flex items-center gap-2">
                                <FolderOpenIcon class="w-5 h-5 text-indigo-500" />
                                <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                    {{ editingId ? 'Edit Department' : 'Create Department' }}
                                </span>
                            </div>
                        </template>

                        <form @submit.prevent="submitForm" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Department Name <span class="text-red-500">*</span></label>
                                    <BaseInput v-model="form.name" placeholder="e.g. Quality Control" :class="{'p-invalid': form.errors.name}" />
                                    <small v-if="form.errors.name" class="p-error text-[10px]">{{ form.errors.name }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Department Code</label>
                                    <BaseInput v-model="form.code" placeholder="e.g. QC-DEPT" />
                                </div>
                            </div>

                            <BaseFormActions 
                                :loading="form.processing"
                                :label="editingId ? 'Update Department' : 'Save Department'"
                                :cancel-label="editingId ? 'Cancel' : 'Reset'"
                                :mode="editingId ? 'edit' : 'add'"
                                class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                @cancel="resetForm"
                            />
                        </form>
                    </BaseCard>

                    <!-- Departments List -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable 
                            :value="departments" 
                            dataKey="id"
                            stripedRows 
                            heading="Departments"
                            headingIcon="FolderIcon"
                            showSearch showSerial
                            paginator
                            :rows="30" 
                            :totalRecords="departments.length"
                            class="p-datatable-sm"
                        >
                            <Column header="Department Code">
                                <template #body="slotProps">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">
                                        {{ slotProps.data.code || '-' }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Department Name">
                                <template #body="slotProps">
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.name }}
                                    </span>
                                </template>
                            </Column>

                            <Column header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <BaseButton 
                                            icon="pi pi-pencil" 
                                            severity="info" 
                                            text 
                                            rounded 
                                            @click="editDepartment(slotProps.data)"
                                        />
                                        <BaseButton 
                                            icon="pi pi-trash" 
                                            severity="danger" 
                                            text 
                                            rounded 
                                            @click="deleteDepartment(slotProps.data.id)"
                                        />
                                    </div>
                                </template>
                            </Column>
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
