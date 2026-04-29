<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useContactTypeStore } from '@/Pages/ContactTypes/useContactTypeStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { useToast } from 'primevue/usetoast';

const store = useContactTypeStore();
const toast = useToast();

interface ContactType {
    id: number;
    type: string;
}

const props = defineProps<{
    contactTypes: ContactType[];
}>();

onMounted(() => {
    store.setContactTypes(props.contactTypes);
});

const deleteContactType = (id: number) => {
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
                await axios.delete(route('contacttypes.destroy', id));
                store.removeContactType(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Contact type removed', life: 3000 });
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
    type: '',
    processing: false,
    errors: { type: '' }
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value.type = '';
    modalForm.value.errors.type = '';
    showModal.value = true;
};

const openEditModal = (contactType: ContactType) => {
    modalMode.value = 'edit';
    editingId.value = contactType.id;
    modalForm.value.type = contactType.type;
    modalForm.value.errors.type = '';
    showModal.value = true;
};

const openViewModal = (contactType: ContactType) => {
    openEditModal(contactType);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors.type = '';

    try {
        if (modalMode.value === 'create') {
            const response = await axios.post(route('contacttypes.store'), { type: modalForm.value.type });
            store.addContactType(response.data.contactType);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Contact type created' });
        } else {
            const response = await axios.put(route('contacttypes.update', editingId.value), { type: modalForm.value.type });
            store.updateContactType(response.data.contactType);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Contact type updated' });
        }
        closeModal();
    } catch (error: any) {
        if (error.response?.data?.errors?.type) {
            modalForm.value.errors.type = error.response.data.errors.type[0];
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'An error occurred' });
        }
    } finally {
        modalForm.value.processing = false;
    }
};
</script>

<template>
    <AppLayout title="Contact Types">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                <DataTable :value="store.contactTypes" stripedRows class="p-datatable-sm text-sm" :paginator="true" :rows="10">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-semibold uppercase tracking-tight">Personnel Roles</span>
                            <Button label="New Type" icon="pi pi-plus"  @click="openCreateModal" />
                        </div>
                    </template>
                    <Column field="type" header="Type Name" sortable></Column>
                    <Column header="Actions" class="text-right" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <Button icon="pi pi-eye" text rounded  @click="openViewModal(slotProps.data)" severity="secondary" />
                                <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                                <Button icon="pi pi-trash" text rounded  @click="deleteContactType(slotProps.data.id)" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' CONTACT TYPE'" :style="{ width: '450px' }">
            <div class="flex flex-col gap-2 py-4">
                <label class="text-xs font-semibold uppercase text-gray-500">Type Name</label>
                <BaseInput v-model="modalForm.type" :disabled="modalMode === 'view'" fluid autofocus />
                <small v-if="modalForm.errors.type" class="text-red-500">{{ modalForm.errors.type }}</small>
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

