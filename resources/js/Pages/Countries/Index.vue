<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useCountryStore } from '@/Pages/Countries/useCountryStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

const store = useCountryStore();
const toast = useToast();

interface Country {
    id: number;
    country_name: string;
    country_code: string;
    is_active: number | boolean;
}

const props = defineProps<{
    countries: Country[];
}>();

onMounted(() => {
    store.setCountries(props.countries);
});

const deleteCountry = (id: number) => {
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
                const response = await axios.delete(route('countries.destroy', id));
                store.removeCountry(id);
                toast.add({ severity: 'success', summary: 'Success', detail: 'Country deleted', life: 3000 });
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
    country_name: '',
    country_code: '',
    is_active: true as any,
    processing: false,
    errors: {} as any
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value = { country_name: '', country_code: '', is_active: true, processing: false, errors: {} };
    showModal.value = true;
};

const openEditModal = (country: Country) => {
    modalMode.value = 'edit';
    editingId.value = country.id;
    modalForm.value = {
        country_name: country.country_name,
        country_code: country.country_code,
        is_active: !!country.is_active,
        processing: false,
        errors: {}
    };
    showModal.value = true;
};

const openViewModal = (country: Country) => {
    openEditModal(country);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors = {};

    const payload = {
        country_name: modalForm.value.country_name,
        country_code: modalForm.value.country_code,
        is_active: modalForm.value.is_active ? 1 : 0
    };

    try {
        if (modalMode.value === 'create') {
            const response = await axios.post(route('countries.store'), payload);
            store.addCountry(response.data.country);
            toast.add({ severity: 'success', summary: 'Success', detail: response.data.message, life: 3000 });
        } else {
            const response = await axios.put(route('countries.update', editingId.value), payload);
            store.updateCountry(response.data.country);
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
    <AppLayout title="Countries">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                <DataTable :value="store.countries" stripedRows class="p-datatable-sm text-sm" :paginator="true" :rows="10">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-semibold uppercase tracking-tight">Countries</span>
                            <Button label="New Country" icon="pi pi-plus"  @click="openCreateModal" />
                        </div>
                    </template>
                    <Column field="country_code" header="Code" sortable style="width: 100px"></Column>
                    <Column field="country_name" header="Name" sortable></Column>
                    <Column header="Active" style="width: 100px">
                        <template #body="slotProps">
                            <span :class="slotProps.data.is_active ? 'text-green-600 font-bold' : 'text-red-500 font-bold'">
                                {{ slotProps.data.is_active ? 'YES' : 'NO' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <Button icon="pi pi-eye" text rounded  @click="openViewModal(slotProps.data)" severity="secondary" />
                                <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                                <Button icon="pi pi-trash" text rounded  @click="deleteCountry(slotProps.data.id)" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' COUNTRY'" :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Country Name</label>
                    <BaseInput v-model="modalForm.country_name" :disabled="modalMode === 'view'" fluid />
                    <small v-if="modalForm.errors.country_name" class="text-red-500">{{ modalForm.errors.country_name[0] }}</small>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Country Code</label>
                    <BaseInput v-model="modalForm.country_code" :disabled="modalMode === 'view'" fluid />
                    <small v-if="modalForm.errors.country_code" class="text-red-500">{{ modalForm.errors.country_code[0] }}</small>
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="modalForm.is_active" :disabled="modalMode === 'view'" />
                    <label class="text-sm font-semibold">Active Status</label>
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

