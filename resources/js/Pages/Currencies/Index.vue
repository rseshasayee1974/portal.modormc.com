<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useCurrencyStore } from '@/Pages/Currencies/useCurrencyStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { useToast } from 'primevue/usetoast';

const store = useCurrencyStore();
const toast = useToast();

interface Currency {
    id: number;
    currency_name: string;
    currency_code: string;
}

const props = defineProps<{
    currencies: Currency[];
}>();

onMounted(() => {
    store.setCurrencies(props.currencies);
});

const deleteCurrency = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await axios.delete(route('currencies.destroy', id));
                store.removeCurrency(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Currency removed successfully', life: 3000 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete currency', life: 3000 });
            }
        }
    });
};

const showModal = ref(false);
const modalMode = ref<'create' | 'edit' | 'view'>('create');
const editingId = ref<number | null>(null);

const modalForm = ref({
    currency_name: '',
    currency_code: '',
    processing: false,
    errors: {} as any
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value = { currency_name: '', currency_code: '', processing: false, errors: {} };
    showModal.value = true;
};

const openEditModal = (currency: Currency) => {
    modalMode.value = 'edit';
    editingId.value = currency.id;
    modalForm.value = { currency_name: currency.currency_name, currency_code: currency.currency_code, processing: false, errors: {} };
    showModal.value = true;
};

const openViewModal = (currency: Currency) => {
    openEditModal(currency);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors = {};

    const payload = {
        currency_name: modalForm.value.currency_name,
        currency_code: modalForm.value.currency_code
    };

    try {
        if (modalMode.value === 'create') {
            const response = await axios.post(route('currencies.store'), payload);
            store.addCurrency(response.data.currency);
            toast.add({ severity: 'success', summary: 'Success', detail: response.data.message, life: 3000 });
        } else {
            const response = await axios.put(route('currencies.update', editingId.value), payload);
            store.updateCurrency(response.data.currency);
            toast.add({ severity: 'success', summary: 'Success', detail: response.data.message, life: 3000 });
        }
        closeModal();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            modalForm.value.errors = error.response.data.errors;
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'An error occurred', life: 3000 });
        }
    } finally {
        modalForm.value.processing = false;
    }
};
</script>

<template>
    <AppLayout title="Currencies">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                <DataTable :value="store.currencies" stripedRows class="p-datatable-sm text-sm" :paginator="true" :rows="10">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-semibold uppercase tracking-tight">Currencies</span>
                            <Button label="New Currency" icon="pi pi-plus"  @click="openCreateModal" />
                        </div>
                    </template>
                    <Column field="currency_code" header="Code" sortable style="width: 100px"></Column>
                    <Column field="currency_name" header="Name" sortable></Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <Button icon="pi pi-eye" text rounded  @click="openViewModal(slotProps.data)" severity="secondary" />
                                <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                                <Button icon="pi pi-trash" text rounded  @click="deleteCurrency(slotProps.data.id)" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' CURRENCY'" :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Currency Name</label>
                    <BaseInput v-model="modalForm.currency_name" :disabled="modalMode === 'view'" fluid class="p-inputtext-sm" />
                    <small v-if="modalForm.errors.currency_name" class="text-red-500">{{ modalForm.errors.currency_name[0] }}</small>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Currency Code</label>
                    <BaseInput v-model="modalForm.currency_code" :disabled="modalMode === 'view'" fluid class="p-inputtext-sm" />
                    <small v-if="modalForm.errors.currency_code" class="text-red-500">{{ modalForm.errors.currency_code[0] }}</small>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <Button :label="modalMode === 'view' ? 'Close' : 'Cancel'" text severity="secondary" @click="closeModal" />
                    <Button v-if="modalMode !== 'view'" label="Save" :loading="modalForm.processing" @click="submitModal" severity="primary" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

