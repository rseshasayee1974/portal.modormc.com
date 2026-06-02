<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePaymentStatusStore } from '@/Pages/PaymentStatuses/usePaymentStatusStore';

// PrimeVue
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { useToast } from 'primevue/usetoast';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const store = usePaymentStatusStore();
const toast = useToast();

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

interface PaymentStatus {
    id: number;
    status_name: string;
}

const props = defineProps<{
    paymentStatuses: PaymentStatus[];
}>();

onMounted(() => {
    store.setPaymentStatuses(props.paymentStatuses);
});

const deletePaymentStatus = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                await axios.delete(route('paymentstatuses.destroy', id));
                store.removePaymentStatus(id);
                toast.add({ severity: 'success', summary: 'Success', detail: 'Payment Status deleted', life: 1500 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 1500 });
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
    errors: {} as any
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value = { status_name: '', processing: false, errors: {} };
    showModal.value = true;
};

const openEditModal = (paymentStatus: PaymentStatus) => {
    modalMode.value = 'edit';
    editingId.value = paymentStatus.id;
    modalForm.value = {
        status_name: paymentStatus.status_name,
        processing: false,
        errors: {}
    };
    showModal.value = true;
};

const openViewModal = (paymentStatus: PaymentStatus) => {
    openEditModal(paymentStatus);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors = {};

    const payload = {
        status_name: modalForm.value.status_name,
    };

    try {
        if (modalMode.value === 'create') {
            const response = await axios.post(route('paymentstatuses.store'), payload);
            store.addPaymentStatus(response.data.paymentStatus);
            toast.add({ severity: 'success', summary: 'Success', detail: response.data.message, life: 1500 });
        } else {
            const response = await axios.put(route('paymentstatuses.update', editingId.value), payload);
            store.updatePaymentStatus(response.data.paymentStatus);
            toast.add({ severity: 'success', summary: 'Success', detail: response.data.message, life: 1500 });
        }
        closeModal();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            modalForm.value.errors = error.response.data.errors;
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'An error occurred', life: 1500 });
        }
    } finally {
        modalForm.value.processing = false;
    }
};
</script>

<template>
    <AppLayout title="Payment Statuses">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <BaseDataTable
                :value="store.paymentStatuses"
                v-model:filters="filters"
                :globalFilterFields="['status_name']"
                showSearch
                showSerial
                heading="Payment Statuses"
                headingIcon="CreditCardIcon"
                :rows="30"
                class="text-sm"
            >
                <template #toolbar>
                    <Button label="New Status" icon="pi pi-plus" @click="openCreateModal" />
                </template>
                <Column field="status_name" header="Status Name" sortable></Column>
                <Column header="Actions" class="text-right" style="width: 120px">
                    <template #body="slotProps">
                        <div class="flex justify-end gap-2">
                            <Button icon="pi pi-eye" text rounded @click="openViewModal(slotProps.data)" severity="secondary" />
                            <Button icon="pi pi-pencil" text rounded @click="openEditModal(slotProps.data)" severity="info" />
                            <Button icon="pi pi-trash" text rounded @click="deletePaymentStatus(slotProps.data.id)" severity="danger" />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' PAYMENT STATUS'" :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Status Name</label>
                    <BaseInput v-model="modalForm.status_name" :disabled="modalMode === 'view'" placeholder="e.g. Paid, Partial, Failed" fluid />
                    <small v-if="modalForm.errors.status_name" class="text-red-500">{{ modalForm.errors.status_name[0] }}</small>
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
