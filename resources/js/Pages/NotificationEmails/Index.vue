<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { EnvelopeIcon } from '@heroicons/vue/24/outline';

// PrimeVue
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const toast = useToast();

interface Plant { id: number; name: string; }
interface NotificationEmail {
    id: number;
    plant_id: number;
    plant?: Plant;
    type: string;
    role_name: string;
    email: string;
    status: number;
}

const props = defineProps<{
    notificationEmails: NotificationEmail[];
    plants: Plant[];
    roles: string[];
    types: string[];
}>();

const editingId = ref<number | null>(null);
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const form = useForm({
    plant_id: null as number | null,
    type: '',
    role_name: '',
    email: '',
    status: 1,
});

const typeOptions = computed(() => props.types.map(t => ({ label: t, value: t })));
const roleOptions = computed(() => props.roles.map(r => ({ label: r, value: r })));

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (item: NotificationEmail) => {
    modalMode.value = 'edit';
    editingId.value = item.id;
    form.plant_id = item.plant_id;
    form.type = item.type;
    form.role_name = item.role_name;
    form.email = item.email;
    form.status = item.status;
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submit = () => {
    if (editingId.value) {
        form.put(route('notificationemails.update', editingId.value), {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Success', detail: 'Notification email updated', life: 2000 });
                closeModal();
            },
        });
    } else {
        form.post(route('notificationemails.store'), {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Success', detail: 'Notification email created', life: 2000 });
                closeModal();
            },
        });
    }
};

const deleteItem = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('notificationemails.destroy', id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Notification email removed', life: 2000 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Notification Emails">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <BaseDataTable 
                :value="props.notificationEmails" 
                showSerial
                :rows="30"
                showSearch
                :showAdvancedFilter="false"
                :rowsPerPageOptions="[30, 50, 100, 200]"
                v-model:filters="filters"
                :globalFilterFields="['type', 'role_name', 'email']"
                class="text-xs"
            >
                <template #header>
                    <div class="flex items-center justify-between p-2">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                                <EnvelopeIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <span class="text-xl font-semibold uppercase tracking-tight">Notification Emails</span>
                        </div>
                        <Button label="New Notification" icon="pi pi-plus" @click="openCreateModal" />
                    </div>
                </template>

                <Column field="type" header="Type" sortable></Column>
                <Column field="role_name" header="Role Name" sortable></Column>
                <Column field="email" header="Email Address" sortable></Column>
                <!-- <Column field="plant.name" header="Plant" sortable></Column> -->
                <Column field="status" header="Status" sortable>
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.status === 1 ? 'Active' : 'Inactive'" :severity="slotProps.data.status === 1 ? 'success' : 'danger'" rounded />
                    </template>
                </Column>
                <Column header="Actions" class="text-right" style="width: 120px">
                    <template #body="slotProps">
                        <div class="flex justify-end gap-2">
                            <Button icon="pi pi-pencil" text rounded @click="openEditModal(slotProps.data)" severity="info" />
                            <Button icon="pi pi-trash" text rounded @click="deleteItem(slotProps.data.id)" severity="danger" />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' NOTIFICATION EMAIL'" :style="{ width: '600px' }">
            <div class="grid grid-cols-2 gap-4 py-4">
                <!-- <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Plant</label>
                    <BaseSelect v-model="form.plant_id" :options="props.plants" optionLabel="name" optionValue="id" fluid showClear filter />
                    <small v-if="form.errors.plant_id" class="text-red-500">{{ form.errors.plant_id }}</small>
                </div> -->

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Notification Type</label>
                    <BaseSelect v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" fluid />
                    <small v-if="form.errors.type" class="text-red-500">{{ form.errors.type }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Role Name</label>
                    <BaseSelect v-model="form.role_name" :options="roleOptions" optionLabel="label" optionValue="value" fluid />
                    <small v-if="form.errors.role_name" class="text-red-500">{{ form.errors.role_name }}</small>
                </div>

                <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Email Address</label>
                    <BaseInput v-model="form.email" fluid type="email" />
                    <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Status</label>
                    <div class="flex items-center gap-2 mt-2">
                        <ToggleSwitch v-model="form.status" :trueValue="1" :falseValue="0" />
                        <span class="text-sm">{{ form.status === 1 ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <Button label="Cancel" text severity="secondary" @click="closeModal" />
                    <Button label="Save" :loading="form.processing" @click="submit" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
