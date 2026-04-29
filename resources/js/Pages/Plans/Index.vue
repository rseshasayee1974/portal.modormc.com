<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePlanStore } from '@/Pages/Plans/usePlanStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

const store = usePlanStore();
const toast = useToast();

interface Plan {
    id: number;
    plan_type: string;
    price_monthly: number;
    monthly_plan_description: string | null;
    price_yearly: number;
    yearly_plan_description: string | null;
    max_users: number;
    features_json: string[] | null;
    is_active: number | boolean;
}

const props = defineProps<{
    plans: Plan[];
}>();

onMounted(() => {
    store.setPlans(props.plans);
});

const deletePlan = (id: number) => {
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
                await axios.delete(route('plans.destroy', id));
                store.removePlan(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Plan removed', life: 3000 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete plan', life: 3000 });
            }
        }
    });
};

const showModal = ref(false);
const modalMode = ref<'create' | 'edit' | 'view'>('create');
const editingId = ref<number | null>(null);

const modalForm = ref({
    plan_type: '',
    price_monthly: 0,
    monthly_plan_description: '',
    price_yearly: 0,
    yearly_plan_description: '',
    max_users: 1,
    features_json: [] as string[],
    is_active: true as any,
    processing: false,
    errors: {} as any
});

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value = { plan_type: '', price_monthly: 0, monthly_plan_description: '', price_yearly: 0, yearly_plan_description: '', max_users: 1, features_json: [], is_active: true, processing: false, errors: {} };
    showModal.value = true;
};

const openEditModal = (plan: Plan) => {
    modalMode.value = 'edit';
    editingId.value = plan.id;
    modalForm.value = {
        plan_type: plan.plan_type,
        price_monthly: Number(plan.price_monthly),
        monthly_plan_description: plan.monthly_plan_description || '',
        price_yearly: Number(plan.price_yearly),
        yearly_plan_description: plan.yearly_plan_description || '',
        max_users: Number(plan.max_users),
        features_json: plan.features_json || [],
        is_active: !!plan.is_active,
        processing: false,
        errors: {}
    };
    showModal.value = true;
};

const openViewModal = (plan: Plan) => {
    openEditModal(plan);
    modalMode.value = 'view';
};

const closeModal = () => { showModal.value = false; };

const submitModal = async () => {
    modalForm.value.processing = true;
    modalForm.value.errors = {};

    const payload = { ...modalForm.value, is_active: modalForm.value.is_active ? 1 : 0 };

    try {
        if (modalMode.value === 'create') {
            const response = await axios.post(route('plans.store'), payload);
            store.addPlan(response.data.plan);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Plan created', life: 3000 });
        } else {
            const response = await axios.put(route('plans.update', editingId.value), payload);
            store.updatePlan(response.data.plan);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Plan updated', life: 3000 });
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

const newFeature = ref('');
const addFeature = () => {
    if (newFeature.value.trim()) {
        modalForm.value.features_json.push(newFeature.value.trim());
        newFeature.value = '';
    }
};
const removeFeature = (index: number) => {
    modalForm.value.features_json.splice(index, 1);
};
</script>

<template>
    <AppLayout title="Plans">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                <DataTable :value="store.plans" stripedRows class="p-datatable-sm text-sm" :paginator="true" :rows="10">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-semibold uppercase tracking-tight">Subscription Plans</span>
                            <Button label="New Plan" icon="pi pi-plus"  @click="openCreateModal" />
                        </div>
                    </template>
                    <Column header="S.No" style="width: 70px">
                        <template #body="slotProps">
                            <span class="text-gray-400 font-bold">{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
                    <Column field="plan_type" header="Plan" sortable></Column>
                    <Column field="price_monthly" header="Monthly ($)" sortable></Column>
                    <Column field="price_yearly" header="Yearly ($)" sortable></Column>
                    <Column field="max_users" header="Max Users" sortable></Column>
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
                                <Button icon="pi pi-trash" text rounded  @click="deletePlan(slotProps.data.id)" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' PLAN'" :style="{ width: '600px' }">
            <div class="grid grid-cols-2 gap-4 py-2">
                <div class="col-span-2 flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Plan Type</label>
                    <BaseInput v-model="modalForm.plan_type" :disabled="modalMode === 'view'" fluid />
                    <small v-if="modalForm.errors.plan_type" class="text-red-500">{{ modalForm.errors.plan_type[0] }}</small>
                </div>
                
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Monthly Price</label>
                    <BaseInputNumber v-model="modalForm.price_monthly" mode="currency" currency="USD" locale="en-US" :disabled="modalMode === 'view'" fluid />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Monthly Desc</label>
                    <BaseInput v-model="modalForm.monthly_plan_description" :disabled="modalMode === 'view'" fluid />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Yearly Price</label>
                    <BaseInputNumber v-model="modalForm.price_yearly" mode="currency" currency="USD" locale="en-US" :disabled="modalMode === 'view'" fluid />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Yearly Desc</label>
                    <BaseInput v-model="modalForm.yearly_plan_description" :disabled="modalMode === 'view'" fluid />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase text-gray-500">Max Users</label>
                    <BaseInputNumber v-model="modalForm.max_users" :min="1" :disabled="modalMode === 'view'" fluid />
                </div>
                <div class="flex items-center gap-2 pt-5">
                    <ToggleSwitch v-model="modalForm.is_active" :disabled="modalMode === 'view'" />
                    <label class="text-sm font-semibold">Active</label>
                </div>

                <div class="col-span-2 flex flex-col gap-2 mt-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Features</label>
                    <div class="flex gap-2">
                        <BaseInput v-model="newFeature" placeholder="Add feature..." class="flex-1" :disabled="modalMode === 'view'" @keyup.enter="addFeature" />
                        <Button icon="pi pi-plus" text @click="addFeature" :disabled="modalMode === 'view'" />
                    </div>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <div v-for="(feat, idx) in modalForm.features_json" :key="idx" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded-md text-xs flex items-center gap-2">
                            {{ feat }}
                            <i v-if="modalMode !== 'view'" class="pi pi-times cursor-pointer hover:text-red-500" @click="removeFeature(idx)"></i>
                        </div>
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

