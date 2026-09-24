<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { CogIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

interface LeaveType {
    id: number;
    name: string;
    is_paid: boolean;
    max_days_per_year: number | null;
    carry_forward: boolean;
}

const props = defineProps<{
    leaveTypes: LeaveType[];
}>();

const page = usePage();
const { isSassOwner } = usePermissions();
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    is_paid: true,
    max_days_per_year: 12,
    carry_forward: false,
});

const editLeaveType = (type: LeaveType) => {
    editingId.value = type.id;
    form.name = type.name;
    form.is_paid = !!type.is_paid;
    form.max_days_per_year = type.max_days_per_year ?? 0;
    form.carry_forward = !!type.carry_forward;
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('leave-types.update', editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post(route('leave-types.store'), {
            onSuccess: () => resetForm(),
        });
    }
};

const deleteLeaveType = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the global leave category across all plants!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('leave-types.destroy', id), {
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
    <AppLayout title="Leave Category Master">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Form Card -->
                <BaseCard v-if="isSassOwner" class="text-sm">
                    <template #header>
                        <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                            {{ editingId ? 'Edit Global Leave Category' : 'Create Global Leave Category' }}
                        </span>
                    </template>

                    <form @submit.prevent="submitForm" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                    Leave Name <span class="text-red-500">*</span>
                                </label>
                                <BaseInput v-model="form.name" placeholder="e.g. Sick Leave" :class="{ 'p-invalid': form.errors.name }" />
                                <small v-if="form.errors.name" class="p-error text-[10px]">{{ form.errors.name }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                    Max Days per Year
                                </label>
                                <BaseInput type="number" v-model="form.max_days_per_year" />
                            </div>
                            <div class="md:col-span-3 flex flex-col md:flex-row gap-6 mt-2">
                                <!-- Paid Leave Card -->
                                <div @click="form.is_paid = !form.is_paid"
                                    class="flex-1 flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                    :class="form.is_paid
                                        ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                            Paid Leave Category
                                        </span>
                                        <span class="text-[10px] text-gray-400">
                                            Is time off under this category paid or unpaid?
                                        </span>
                                    </div>
                                    <ToggleSwitch v-model="form.is_paid" @click.stop />
                                </div>

                                <!-- Carry Forward Card -->
                                <div @click="form.carry_forward = !form.carry_forward"
                                    class="flex-1 flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                    :class="form.carry_forward
                                        ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                            Carry Forward Balance
                                        </span>
                                        <span class="text-[10px] text-gray-400">
                                            Can unused balance roll over to the next year?
                                        </span>
                                    </div>
                                    <ToggleSwitch v-model="form.carry_forward" @click.stop />
                                </div>
                            </div>
                        </div>

                        <BaseFormActions :loading="form.processing"
                            :label="editingId ? 'Update Category' : 'Save Category'"
                            :cancel-label="editingId ? 'Cancel' : 'Reset'"
                            :mode="editingId ? 'edit' : 'add'"
                            @cancel="resetForm" />
                    </form>
                </BaseCard>

                <!-- List Card -->
                <div class="bg-white dark:bg-slate-900 rounded-xl">
                    <BaseDataTable :value="leaveTypes" dataKey="id" stripedRows
                        heading="Global Leave Categories" headingIcon="CogIcon" showSearch showSerial paginator
                        :rows="30" :totalRecords="leaveTypes.length" class="p-datatable-sm">
                        <Column header="Category Name">
                            <template #body="slotProps">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ slotProps.data.name }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Max Days per Year">
                            <template #body="slotProps">
                                <span>{{ slotProps.data.max_days_per_year ?? 'Unlimited' }}</span>
                            </template>
                        </Column>
                        <Column header="Paid Leave">
                            <template #body="slotProps">
                                <Tag :severity="slotProps.data.is_paid ? 'success' : 'danger'"
                                    :value="slotProps.data.is_paid ? 'YES' : 'NO'" rounded />
                            </template>
                        </Column>
                        <Column header="Carry Forward">
                            <template #body="slotProps">
                                <Tag :severity="slotProps.data.carry_forward ? 'info' : 'secondary'"
                                    :value="slotProps.data.carry_forward ? 'YES' : 'NO'" rounded />
                            </template>
                        </Column>
                        <Column v-if="isSassOwner" header="Actions" alignFrozen="right" frozen>
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <BaseButton icon="pi pi-pencil" severity="info" text rounded
                                        @click="editLeaveType(slotProps.data)" />
                                    <BaseButton icon="pi pi-trash" severity="danger" text rounded
                                        @click="deleteLeaveType(slotProps.data.id)" />
                                </div>
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
