<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useVoucherTypeStore, VoucherType } from './useVoucherTypeStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    voucherTypes: VoucherType[];
    isSuperAdmin: boolean;
}>();

const store = useVoucherTypeStore();
const toast = useToast();

onMounted(() => {
    store.setVoucherTypes(props.voucherTypes);
});

const showModal = ref(false);
const modalMode = ref<'create' | 'edit' | 'view'>('create');
const editingId = ref<number | null>(null);

const modalForm = ref({
    journal_name: '',
    short_code: '',
    is_system_generated: false,
    prefix: '',
    voucher_group: 'Other',
    processing: false,
    errors: { journal_name: '', short_code: '', voucher_group: '' }
});

const voucherGroups = [
    { label: 'Sales', value: 'Sales' },
    { label: 'Purchase', value: 'Purchase' },
    { label: 'Payment', value: 'Payment' },
    { label: 'Receipt', value: 'Receipt' },
    { label: 'Debit Note', value: 'Debit Note' },
    { label: 'Credit Note', value: 'Credit Note' },
    { label: 'Expense', value: 'Expense' },
    { label: 'Other', value: 'Other' },
];

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value = { journal_name: '', short_code: '', is_system_generated: false, prefix: '', voucher_group: 'Other', processing: false, errors: { journal_name: '', short_code: '', voucher_group: '' } };
    showModal.value = true;
};

const openEditModal = (vt: VoucherType) => {
    modalMode.value = 'edit';
    editingId.value = vt.id;
    modalForm.value = {
        journal_name: vt.journal_name,
        short_code: vt.short_code,
        is_system_generated: !!vt.is_system_generated,
        prefix: vt.prefix || '',
        voucher_group: vt.voucher_group,
        processing: false,
        errors: { journal_name: '', short_code: '', voucher_group: '' }
    };
    showModal.value = true;
};

const openViewModal = (vt: VoucherType) => {
    openEditModal(vt);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors = { journal_name: '', short_code: '', voucher_group: '' };

    const payload = {
        journal_name: modalForm.value.journal_name,
        short_code: modalForm.value.short_code,
        is_system_generated: modalForm.value.is_system_generated,
        prefix: modalForm.value.prefix,
        voucher_group: modalForm.value.voucher_group,
    };

    try {
        if (modalMode.value === 'create') {
            const res = await axios.post(route('vouchertypes.store'), payload);
            store.addVoucherType(res.data.voucherType);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Voucher Type Created' });
        } else {
            const res = await axios.put(route('vouchertypes.update', editingId.value), payload);
            store.updateVoucherType(res.data.voucherType);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Voucher Type Updated' });
        }
        closeModal();
    } catch (err: any) {
        if (err.response?.data?.errors) {
            Object.assign(modalForm.value.errors, err.response.data.errors);
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save' });
        }
    } finally {
        modalForm.value.processing = false;
    }
};

const deleteVT = (id: number) => {
    Swal.fire({
        title: 'Delete Voucher Type?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await axios.delete(route('vouchertypes.destroy', id));
                store.removeVoucherType(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: res.data.message });
            } catch (err: any) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete' });
            }
        }
    });
};
</script>

<template>
    <AppLayout title="Voucher Types">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg text-sm">
                <DataTable :value="store.voucherTypes" stripedRows class="p-datatable-sm" :paginator="true" :rows="10">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-semibold uppercase tracking-tight">Journal Configuration</span>
                            <Button label="New Voucher Type" icon="pi pi-plus"  @click="openCreateModal" />
                        </div>
                    </template>
                    <Column field="voucher_group" header="Group" sortable>
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.voucher_group" severity="info"  />
                        </template>
                    </Column>
                    <Column field="journal_name" header="Journal Name" sortable></Column>
                    <Column field="short_code" header="Code" sortable></Column>
                    <Column field="prefix" header="Prefix" sortable></Column>
                    <Column field="is_system_generated" header="System" sortable class="text-center">
                        <template #body="slotProps">
                            <i v-if="slotProps.data.is_system_generated" class="pi pi-lock text-amber-500"></i>
                            <i v-else class="pi pi-check-circle text-green-500"></i>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <Button icon="pi pi-eye" text rounded  @click="openViewModal(slotProps.data)" severity="secondary" />
                                <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                                <Button v-if="!slotProps.data.is_system_generated" icon="pi pi-trash" text rounded  @click="deleteVT(slotProps.data.id)" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' VOUCHER TYPE'" :style="{ width: '600px' }">
            <div class="grid grid-cols-2 gap-4 py-4">
                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Journal Name</label>
                    <BaseInput v-model="modalForm.journal_name" :disabled="modalMode === 'view'" fluid autofocus />
                    <small v-if="modalForm.errors.journal_name" class="text-red-500">{{ modalForm.errors.journal_name }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Short Code</label>
                    <BaseInput v-model="modalForm.short_code" :disabled="modalMode === 'view' || (modalForm.is_system_generated && !props.isSuperAdmin)" fluid />
                    <small v-if="modalForm.errors.short_code" class="text-red-500">{{ modalForm.errors.short_code }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Voucher Group</label>
                    <BaseSelect v-model="modalForm.voucher_group" :options="voucherGroups" optionLabel="label" optionValue="value" :disabled="modalMode === 'view'" fluid />
                    <small v-if="modalForm.errors.voucher_group" class="text-red-500">{{ modalForm.errors.voucher_group }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Prefix</label>
                    <BaseInput v-model="modalForm.prefix" :disabled="modalMode === 'view'" fluid />
                </div>

                <div v-if="props.isSuperAdmin" class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">System Options</label>
                    <div class="flex items-center gap-2 mt-2">
                        <ToggleSwitch v-model="modalForm.is_system_generated" :disabled="modalMode !== 'create' && !props.isSuperAdmin" />
                        <span class="text-sm">Is System Generated?</span>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <Button :label="modalMode === 'view' ? 'Close' : 'Cancel'" text severity="secondary" @click="closeModal" />
                    <Button v-if="modalMode !== 'view'" label="Save" :loading="modalForm.processing" @click="submitModal" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-sm) {
    font-size: 0.8rem;
}
</style>

