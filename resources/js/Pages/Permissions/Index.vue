<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { debounce } from 'lodash';
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

const form = useForm({
    id: null as number | null,
    name: '',
    guard_name: 'web',
});

const openCreateModal = () => {
    modalMode.value = 'create';
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (permission: any) => {
    modalMode.value = 'edit';
    form.reset();
    form.clearErrors();
    form.id = permission.id;
    form.name = permission.name;
    form.guard_name = permission.guard_name || 'web';
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submitModal = () => {
    form.name = form.name.toUpperCase();
    
    if (modalMode.value === 'edit') {
        form.put(`/settings/permissions/${form.id}`, {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Success', detail: 'Permission updated' });
                closeModal();
            }
        });
    } else {
        form.post('/settings/permissions', {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Success', detail: 'Permission created' });
                closeModal();
            }
        });
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
            router.delete(`/settings/permissions/${id}`, {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Permission removed' });
                }
            });
        }
    });
};

const onPage = (event: any) => {
    router.get('/settings/permissions', { page: event.page + 1, search: searchQuery.value }, { preserveState: true, preserveScroll: true });
};

const handleSearch = debounce(() => {
    router.get('/settings/permissions', { search: searchQuery.value }, { preserveState: true, replace: true, preserveScroll: true });
}, 300);

const firstRecord = computed(() => (props.permissions.current_page - 1) * props.permissions.per_page);
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
                    :first="firstRecord"
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
                            <span class="text-gray-400 font-bold">{{ slotProps.index + 1 + firstRecord }}</span>
                        </template>
                    </Column>
                    <Column field="name" header="Access Key" sortable>
                        <template #body="slotProps">
                            <code class="bg-gray-100 text-pink-600 px-1.5 py-0.5 rounded text-[11px] font-mono select-all uppercase">{{ slotProps.data.name }}</code>
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
                    <BaseInput v-model="form.name" fluid placeholder="e.g. USERS.CREATE" style="text-transform: uppercase;" />
                    <small v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</small>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Guard Name</label>
                    <BaseInput v-model="form.guard_name" fluid />
                    <small v-if="form.errors.guard_name" class="text-red-500 text-xs">{{ form.errors.guard_name }}</small>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <BaseButton label="Cancel" text severity="secondary" @click="closeModal" />
                    <BaseButton label="Save Permission" :loading="form.processing" @click="submitModal" severity="primary" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

