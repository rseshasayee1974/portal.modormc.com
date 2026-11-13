<!--
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { usePermissions } from '@/Composables/usePermissions';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';

type PermissionRow = { id: number; name: string; module: string; action: string; description: string | null; is_system: boolean };
const props = defineProps<{ permissions: any; filters: { search?: string } }>();
const { can } = usePermissions();
const toast = useToast();
const searchQuery = ref(props.filters.search || '');
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const form = useForm({ id: null as number | null, name: '', description: '' });
const firstRecord = computed(() => (props.permissions.current_page - 1) * props.permissions.per_page);

function openCreateModal() {
    modalMode.value = 'create';
    form.reset();
    form.clearErrors();
    showModal.value = true;
}
function openEditModal(permission: PermissionRow) {
    modalMode.value = 'edit';
    form.reset();
    form.clearErrors();
    form.id = permission.id;
    form.name = permission.name;
    form.description = permission.description || '';
    showModal.value = true;
}
function closeModal() { showModal.value = false; }
function submitModal() {
    form.name = form.name.toUpperCase().trim();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Saved', detail: `Mobile permission ${modalMode.value === 'edit' ? 'updated' : 'created'}.` });
            closeModal();
            form.reset();
        },
    };
    if (modalMode.value === 'edit') form.put(`/settings/mobile-permissions/${form.id}`, options);
    else form.post('/settings/mobile-permissions', options);
}
function confirmDelete(permission: PermissionRow) {
    Swal.fire({
        title: 'Remove mobile permission?',
        text: `${permission.name} will no longer be available for mobile user grants.`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'Remove',
    }).then(result => {
        if (result.isConfirmed) router.delete(`/settings/mobile-permissions/${permission.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.add({ severity: 'success', summary: 'Removed', detail: `${permission.name} removed.` }),
        });
    });
}
function onPage(event: any) {
    handleSearch.cancel();
    router.get('/settings/mobile-permissions', { page: event.page + 1, search: searchQuery.value }, { only: ['permissions', 'filters'], preserveState: true, preserveScroll: true });
}
const handleSearch = debounce(() => {
    router.get('/settings/mobile-permissions', { search: searchQuery.value }, { only: ['permissions', 'filters'], preserveState: true, replace: true, preserveScroll: true });
}, 300);
onUnmounted(() => handleSearch.cancel());
</script>

<template>
    <AppLayout title="Mobile Permission Catalog">
        <template #header><ModuleSubTopNav /></template>
        <div class="p-6">
            <section class="card overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <BaseDataTable
                    :value="permissions.data" stripedRows class="p-datatable-sm text-sm" :lazy="true" :paginator="true"
                    :totalRecords="permissions.total" :rows="permissions.per_page" :first="firstRecord" @page="onPage($event)">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h1 class="text-lg font-semibold uppercase tracking-tight">Mobile Permission Catalog</h1>
                                <p class="mt-1 text-xs text-gray-500">Define the module actions available to mobile users.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <BaseInput v-model="searchQuery" placeholder="Search permission keys..." class="p-inputtext-sm" @update:modelValue="handleSearch" />
                                <BaseButton v-if="can('mobile_permission.create')" label="New Permission" icon="pi pi-plus" variant="filled" @click="openCreateModal" />
                            </div>
                        </div>
                    </template>
                    <Column header="S.No" style="width: 70px">
                        <template #body="slotProps"><span class="font-bold text-gray-400">{{ slotProps.index + 1 + firstRecord }}</span></template>
                    </Column>
                    <Column field="module" header="Module" sortable />
                    <Column field="action" header="Action" sortable />
                    <Column field="name" header="Permission Key">
                        <template #body="slotProps"><code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] uppercase text-pink-600 dark:bg-gray-700">{{ slotProps.data.name }}</code></template>
                    </Column>
                    <Column field="is_system" header="Source" style="width: 110px">
                        <template #body="slotProps">
                            <span :class="slotProps.data.is_system
                                ? 'rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                : 'rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                                {{ slotProps.data.is_system ? 'System' : 'Custom' }}
                            </span>
                        </template>
                    </Column>
                    <Column field="description" header="Description">
                        <template #body="slotProps"><span class="text-xs text-gray-500">{{ slotProps.data.description || '—' }}</span></template>
                    </Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1">
                                <BaseButton v-if="can('mobile_permission.update')" icon="pi pi-pencil" variant="text" severity="info" rounded @click="openEditModal(slotProps.data)" />
                                <BaseButton v-if="can('mobile_permission.delete')" icon="pi pi-trash" variant="text" severity="danger" rounded @click="confirmDelete(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty><div class="p-6 text-center text-sm text-gray-500">No mobile permissions found.</div></template>
                </BaseDataTable>
            </section>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode === 'edit' ? 'EDIT MOBILE PERMISSION' : 'NEW MOBILE PERMISSION'" :style="{ width: 'min(92vw, 480px)' }">
            <form class="flex flex-col gap-4 py-2" @submit.prevent="submitModal">
                <BaseInput v-model="form.name" label="Permission Key" required maxlength="150" placeholder="ATTENDANCE.PDF" hint="Use MODULE.ACTION, for example PAYSLIP.VIEW." :error="form.errors.name" inputClass="uppercase font-mono" />
                <BaseInput v-model="form.description" label="Description" maxlength="255" placeholder="View attendance PDF" :error="form.errors.description" />
                <div class="mt-2 flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                    <BaseButton label="Cancel" variant="text" severity="secondary" @click="closeModal" />
                    <BaseButton :label="modalMode === 'edit' ? 'Save Changes' : 'Create Permission'" icon="pi pi-check" variant="filled" :loading="form.processing" type="submit" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
