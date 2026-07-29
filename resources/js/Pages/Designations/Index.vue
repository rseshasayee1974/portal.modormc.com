<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import Swal from 'sweetalert2';
import { PencilSquareIcon, TrashIcon, PlusIcon, IdentificationIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { usePermissions } from '@/Composables/usePermissions';

interface Designation {
    id: number;
    name: string;
    code: string | null;
    min_salary: number | string | null;
    max_salary: number | string | null;
}

const props = defineProps<{
    designations: Designation[];
}>();

const page = usePage();
const { isSassOwner } = usePermissions();
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    code: '',
    min_salary: null as number | null,
    max_salary: null as number | null,
});

const editDesignation = (desg: Designation) => {
    editingId.value = desg.id;
    form.name = desg.name;
    form.code = desg.code || '';
    form.min_salary = desg.min_salary ? Number(desg.min_salary) : null;
    form.max_salary = desg.max_salary ? Number(desg.max_salary) : null;
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('designations.update', editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post(route('designations.store'), {
            onSuccess: () => resetForm(),
        });
    }
};

const deleteDesignation = (id: number) => {
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
            router.delete(route('designations.destroy', id), {
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
    <AppLayout title="Designation Management">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-6">
                    
                    <!-- Form Container (3-column layout) -->
                    <BaseCard v-if="isSassOwner" class="text-sm">
                        <template #header>
                            <div class="flex items-center gap-2">
                                <IdentificationIcon class="w-5 h-5 text-indigo-500" />
                                <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                    {{ editingId ? 'Edit Designation' : 'Create Designation' }}
                                </span>
                            </div>
                        </template>

                        <form @submit.prevent="submitForm" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Designation Name <span class="text-red-500">*</span></label>
                                    <BaseInput v-model="form.name" placeholder="e.g. Mechanical Engineer" :class="{'p-invalid': form.errors.name}" />
                                    <small v-if="form.errors.name" class="p-error text-[10px]">{{ form.errors.name }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Designation Code</label>
                                    <BaseInput v-model="form.code" placeholder="e.g. MECH-ENG" />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Min Salary</label>
                                        <BaseInput type="number" step="0.01" v-model="form.min_salary" placeholder="Min" />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Max Salary</label>
                                        <BaseInput type="number" step="0.01" v-model="form.max_salary" placeholder="Max" />
                                    </div>
                                </div>
                            </div>

                            <BaseFormActions 
                                :loading="form.processing"
                                :label="editingId ? 'Update Designation' : 'Save Designation'"
                                :cancel-label="editingId ? 'Cancel' : 'Reset'"
                                :mode="editingId ? 'edit' : 'add'"
                                class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                @cancel="resetForm"
                            />
                        </form>
                    </BaseCard>

                    <!-- Designations List -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable 
                            :value="designations" 
                            dataKey="id"
                            stripedRows 
                            heading="Designations"
                            headingIcon="BriefcaseIcon"
                            showSearch showSerial
                            paginator
                            :rows="30" 
                            :totalRecords="designations.length"
                            class="p-datatable-sm"
                        >
                            <Column header="Designation Code">
                                <template #body="slotProps">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">
                                        {{ slotProps.data.code || '-' }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Designation Name">
                                <template #body="slotProps">
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.name }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Min Salary">
                                <template #body="slotProps">
                                    <span>{{ slotProps.data.min_salary ? Number(slotProps.data.min_salary).toLocaleString('en-IN', {style: 'currency', currency: 'INR'}) : '-' }}</span>
                                </template>
                            </Column>
                            <Column header="Max Salary">
                                <template #body="slotProps">
                                    <span>{{ slotProps.data.max_salary ? Number(slotProps.data.max_salary).toLocaleString('en-IN', {style: 'currency', currency: 'INR'}) : '-' }}</span>
                                </template>
                            </Column>
                            <Column v-if="isSassOwner" header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <BaseButton 
                                            icon="pi pi-pencil" 
                                            severity="info" 
                                            text 
                                            rounded 
                                            @click="editDesignation(slotProps.data)"
                                        />
                                        <BaseButton 
                                            icon="pi pi-trash" 
                                            severity="danger" 
                                            text 
                                            rounded 
                                            @click="deleteDesignation(slotProps.data.id)"
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
