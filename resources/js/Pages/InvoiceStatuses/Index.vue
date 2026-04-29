<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useInvoiceStatusStore } from '@/Pages/InvoiceStatuses/useInvoiceStatusStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { useToast } from 'primevue/usetoast';

const store = useInvoiceStatusStore();
const toast = useToast();

interface InvoiceStatus {
    id: number;
    status_name: string;
}

const props = defineProps<{
    invoiceStatuses: InvoiceStatus[];
}>();

onMounted(() => {
    store.setInvoiceStatuses(props.invoiceStatuses);
});

const deleteInvoiceStatus = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                await axios.delete(route('invoicestatuses.destroy', id));
                store.removeInvoiceStatus(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Invoice status removed', life: 3000 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 3000 });
            }
        }
    });
};

const showModal = ref(false);
const modalMode = ref<'create' | 'edit' | 'view'>('create');
const editingId = ref<number | null>(null);

const modalForm = ref({
    status_name: '',
    processing: false,
    errors: { status_name: '' }
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value.status_name = '';
    modalForm.value.errors.status_name = '';
    showModal.value = true;
};

const openEditModal = (invoiceStatus: InvoiceStatus) => {
    modalMode.value = 'edit';
    editingId.value = invoiceStatus.id;
    modalForm.value.status_name = invoiceStatus.status_name;
    modalForm.value.errors.status_name = '';
    showModal.value = true;
};

const openViewModal = (invoiceStatus: InvoiceStatus) => {
    openEditModal(invoiceStatus);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors.status_name = '';

    try {
        const payload = { status_name: modalForm.value.status_name };
        if (modalMode.value === 'create') {
            const response = await axios.post(route('invoicestatuses.store'), payload);
            store.addInvoiceStatus(response.data.invoiceStatus);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice status created' });
        } else {
            const response = await axios.put(route('invoicestatuses.update', editingId.value), payload);
            store.updateInvoiceStatus(response.data.invoiceStatus);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice status updated' });
        }
        closeModal();
    } catch (error: any) {
        if (error.response?.data?.errors?.status_name) {
            modalForm.value.errors.status_name = error.response.data.errors.status_name[0];
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'An error occurred' });
        }
    } finally {
        modalForm.value.processing = false;
    }
};
</script>

<template>
    <AppLayout title="Invoice Statuses">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                <DataTable :value="store.invoiceStatuses" stripedRows class="p-datatable-sm text-sm" :paginator="true" :rows="10">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-semibold uppercase tracking-tight">Billing Lifecycle States</span>
                            <Button label="New Status" icon="pi pi-plus"  @click="openCreateModal" />
                        </div>
                    </template>
                    <Column field="status_name" header="Status Name" sortable></Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <Button icon="pi pi-eye" text rounded  @click="openViewModal(slotProps.data)" severity="secondary" />
                                <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                                <Button icon="pi pi-trash" text rounded  @click="deleteInvoiceStatus(slotProps.data.id)" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' INVOICE STATUS'" :style="{ width: '450px' }">
            <div class="flex flex-col gap-2 py-4">
                <label class="text-xs font-semibold uppercase text-gray-500">Status Name</label>
                <BaseInput v-model="modalForm.status_name" :disabled="modalMode === 'view'" fluid autofocus />
                <small v-if="modalForm.errors.status_name" class="text-red-500">{{ modalForm.errors.status_name }}</small>
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

