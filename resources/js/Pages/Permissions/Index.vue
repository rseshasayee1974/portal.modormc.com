<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

// PrimeVue
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    permissions: any;
    filters: { search?: string };
}>();

const toast = useToast();
const searchQuery = ref(props.filters.search || '');
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const formErrors = ref<Record<string, string[]>>({});

const modalForm = ref({
    id: null as number | null,
    name: '',
    guard_name: 'web',
    processing: false,
});

const resetForm = () => {
    modalForm.value = { id: null, name: '', guard_name: 'web', processing: false };
    formErrors.value = {};
};

const openCreateModal = () => {
    modalMode.value = 'create';
    resetForm();
    showModal.value = true;
};

const openEditModal = (permission: any) => {
    modalMode.value = 'edit';
    resetForm();
    modalForm.value.id = permission.id;
    modalForm.value.name = permission.name;
    modalForm.value.guard_name = permission.guard_name || 'web';
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    formErrors.value = {};

    const payload = { ...modalForm.value };
    const targetUrl = modalMode.value === 'edit' ? `/permissions/${payload.id}` : '/permissions';
    const method = modalMode.value === 'edit' ? 'put' : 'post';

    try {
        await axios[method](targetUrl, payload);
        toast.add({ severity: 'success', summary: 'Success', detail: `Permission ${modalMode.value === 'edit' ? 'updated' : 'created'}` });
        closeModal();
        router.reload({ only: ['permissions'] });
    } catch (error: any) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors;
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'An unexpected error occurred' });
        }
    } finally {
        modalForm.value.processing = false;
    }
};

const confirmDelete = (id: number) => {
    Swal.fire({
        title: 'Delete Permission?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/permissions/${id}`, {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Permission removed' });
                }
            });
        }
    });
};

const onPage = (event: any) => {
    router.get('/permissions', { page: event.page + 1, search: searchQuery.value }, { preserveState: true });
};

const handleSearch = debounce(() => {
    router.get('/permissions', { search: searchQuery.value }, { preserveState: true, replace: true });
}, 300);
</script>

<template>
    <AppLayout title="Permissions Management">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                <BaseDataTable 
                    :value="permissions.data" 
                    stripedRows 
                    class="p-datatable-sm text-sm"
                    :lazy="true"
                    :paginator="true"
                    :totalRecords="permissions.total"
                    :rows="permissions.per_page"
                    @page="onPage($event)"
                >
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xl font-semibold uppercase tracking-tight">System Permissions</span>
                            <div class="flex items-center gap-2">
                                <BaseInput v-model="searchQuery" placeholder="Search keys..." class="p-inputtext-sm" @input="handleSearch" />
                                <BaseButton label="New Key" icon="pi pi-plus"  @click="openCreateModal" />
                            </div>
                        </div>
                    </template>
                    
                    <Column header="S.No" style="width: 70px">
                        <template #body="slotProps">
                            <span class="text-gray-400 font-bold">{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
                    <Column field="name" header="Access Key" sortable>
                        <template #body="slotProps">
                            <code class="bg-gray-100 text-pink-600 px-1.5 py-0.5 rounded text-[11px] font-mono select-all">{{ slotProps.data.name }}</code>
                        </template>
                    </Column>
                    <Column field="guard_name" header="Guard" style="width: 100px">
                        <template #body="slotProps">
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] uppercase font-bold">{{ slotProps.data.guard_name }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <BaseButton icon="pi pi-pencil" text rounded  severity="info" @click="openEditModal(slotProps.data)" />
                                <BaseButton icon="pi pi-trash" text rounded  severity="danger" @click="confirmDelete(slotProps.data.id)" />
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' PERMISSION'" :style="{ width: '450px' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Access Key</label>
                    <BaseInput v-model="modalForm.name" fluid placeholder="e.g. users.create" />
                    <small v-if="formErrors.name" class="text-red-500 text-xs">{{ formErrors.name[0] }}</small>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Guard Name</label>
                    <BaseInput v-model="modalForm.guard_name" fluid />
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <BaseButton label="Cancel" text severity="secondary" @click="closeModal" />
                    <BaseButton label="Save Permission" :loading="modalForm.processing" @click="submitModal" severity="primary" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

