<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePaymentMethodStore, PaymentMethod } from '@/Pages/PaymentMethods/usePaymentMethodStore';

// PrimeVue
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import InputSwitch from 'primevue/inputswitch';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { useToast } from 'primevue/usetoast';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const store = usePaymentMethodStore();
const toast = useToast();

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const props = defineProps<{
    paymentMethods: PaymentMethod[];
}>();

onMounted(() => {
    store.setPaymentMethods(props.paymentMethods);
});

const deletePaymentMethod = (id: number) => {
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
                await axios.delete(route('paymentmethods.destroy', id));
                store.removePaymentMethod(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Payment method removed', life: 1500 });
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
    name: '',
    description: '',
    is_active: true,
    processing: false,
    errors: { name: '', description: '' }
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value.name = '';
    modalForm.value.description = '';
    modalForm.value.is_active = true;
    modalForm.value.errors.name = '';
    modalForm.value.errors.description = '';
    showModal.value = true;
};

const openEditModal = (paymentMethod: PaymentMethod) => {
    modalMode.value = 'edit';
    editingId.value = paymentMethod.id;
    modalForm.value.name = paymentMethod.name;
    modalForm.value.description = paymentMethod.description || '';
    modalForm.value.is_active = Boolean(paymentMethod.is_active);
    modalForm.value.errors.name = '';
    modalForm.value.errors.description = '';
    showModal.value = true;
};

const openViewModal = (paymentMethod: PaymentMethod) => {
    openEditModal(paymentMethod);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors.name = '';
    modalForm.value.errors.description = '';

    try {
        const payload = {
            name: modalForm.value.name,
            description: modalForm.value.description,
            is_active: modalForm.value.is_active
        };

        if (modalMode.value === 'create') {
            const response = await axios.post(route('paymentmethods.store'), payload);
            store.addPaymentMethod(response.data.paymentMethod);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Payment method created', life: 1500 });
        } else {
            const response = await axios.put(route('paymentmethods.update', editingId.value), payload);
            store.updatePaymentMethod(response.data.paymentMethod);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Payment method updated', life: 1500 });
        }
        closeModal();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            if (errors.name) modalForm.value.errors.name = errors.name[0];
            if (errors.description) modalForm.value.errors.description = errors.description[0];
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'An error occurred', life: 1500 });
        }
    } finally {
        modalForm.value.processing = false;
    }
};
</script>

<template>
    <AppLayout title="Payment Methods">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <BaseDataTable
                :value="store.paymentMethods"
                v-model:filters="filters"
                :globalFilterFields="['name', 'description']"
                showSearch
                showSerial
                heading="Payment Methods"
                headingIcon="CreditCardIcon"
                :rows="30"
                class="text-sm"
            >
                <template #toolbar>
                    <Button label="New Payment Method" icon="pi pi-plus" @click="openCreateModal" />
                </template>
                <Column field="name" header="Name" sortable></Column>
                <Column field="description" header="Description" sortable></Column>
                <Column field="is_active" header="Status" sortable style="width: 120px">
                    <template #body="slotProps">
                        <i
                            class="pi text-xs mr-2"
                            :class="slotProps.data.is_active ? 'pi-check-circle text-emerald-500' : 'pi-times-circle text-slate-300'"
                        ></i>
                        <span class="text-[11px] font-black uppercase tracking-wider" :class="slotProps.data.is_active ? 'text-emerald-600' : 'text-slate-400'">
                            {{ slotProps.data.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </template>
                </Column>
                <Column header="Actions" class="text-right" style="width: 120px">
                    <template #body="slotProps">
                        <div class="flex justify-end gap-2">
                            <Button icon="pi pi-eye" text rounded @click="openViewModal(slotProps.data)" severity="secondary" />
                            <Button icon="pi pi-pencil" text rounded @click="openEditModal(slotProps.data)" severity="info" />
                            <Button icon="pi pi-trash" text rounded @click="deletePaymentMethod(slotProps.data.id)" severity="danger" />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' PAYMENT METHOD'" :style="{ width: '450px' }">
            <div class="flex flex-col gap-4 py-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Method Name</label>
                    <BaseInput v-model="modalForm.name" :disabled="modalMode === 'view'" fluid autofocus />
                    <small v-if="modalForm.errors.name" class="text-red-500">{{ modalForm.errors.name }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Description</label>
                    <Textarea v-model="modalForm.description" :disabled="modalMode === 'view'" rows="3" class="w-full p-2 border rounded-md dark:bg-slate-900 dark:border-slate-700" />
                    <small v-if="modalForm.errors.description" class="text-red-500">{{ modalForm.errors.description }}</small>
                </div>

                <div class="flex items-center justify-between border-t pt-4 dark:border-slate-700">
                    <span class="text-xs font-semibold uppercase text-gray-500">Active Status</span>
                    <InputSwitch v-model="modalForm.is_active" :disabled="modalMode === 'view'" />
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
