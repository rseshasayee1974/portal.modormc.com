<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, computed, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { ReceiptPercentIcon } from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { useToast } from 'primevue/usetoast';

import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const page = usePage();
const toast = useToast();

interface Entity { id: number; legal_name: string; }
interface Ledger { id: number; title: string; }
interface Tax {
    id: number;
    entity_id: number;
    entity?: Entity;
    tax_name: string;
    tax_type: 'sales' | 'purchase' | 'other sales' | 'other purchase' | 'others';
    tax_group: 'GST' | 'CGST' | 'SGST' | 'IGST' | 'TDS' | 'TCS' | 'CESS' | 'OTHER';
    tax_rate: number;
    parent_id: number | null;
    parent?: Tax;
    account_id: number | null;
    status: number;
}

const props = defineProps<{
    taxes: Tax[];
    parentTaxes: { id: number, tax_name: string }[];
    entities: Entity[];
    ledgers: Ledger[];
    taxGroups: string[];
    taxTypes: string[];
}>();

const editingId = ref<number | null>(null);
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const form = useForm({
    tax_name: '',
    tax_type: 'sales' as any,
    tax_group: 'GST' as any,
    tax_rate: 0,
    parent_id: null as number | null,
    account_id: null as number | null,
    status: 1,
});

const taxGroupOptions = computed(() => props.taxGroups.map(g => ({ label: g, value: g })));
const taxTypeOptions = computed(() => props.taxTypes.map(t => ({ label: t.charAt(0).toUpperCase() + t.slice(1), value: t })));
const parentTaxOptions = computed(() => props.parentTaxes.map(p => ({ label: p.tax_name, value: p.id })));

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (tax: Tax) => {
    modalMode.value = 'edit';
    editingId.value = tax.id;
    form.tax_name = tax.tax_name;
    form.tax_type = tax.tax_type;
    form.tax_group = tax.tax_group;
    form.tax_rate = Number(tax.tax_rate);
    form.parent_id = tax.parent_id;
    form.account_id = tax.account_id;
    form.status = tax.status;
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submit = () => {
    if (editingId.value) {
        form.put(route('taxes.update', editingId.value), {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Success', detail: 'Tax updated' });
                closeModal();
            },
        });
    } else {
        form.post(route('taxes.store'), {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Success', detail: 'Tax created' });
                closeModal();
            },
        });
    }
};

const deleteTax = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('taxes.destroy', id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Tax removed' });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Taxes">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <BaseDataTable 
                :value="props.taxes" 
                showSerial
                showSearch
                v-model:filters="filters"
                :globalFilterFields="['tax_name', 'tax_group', 'tax_type']"
                class="text-xs"
            >
                <template #header>
                    <div class="flex items-center justify-between p-2">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                                <ReceiptPercentIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <span class="text-xl font-semibold uppercase tracking-tight">Tax Configuration</span>
                        </div>
                        <Button label="New Tax" icon="pi pi-plus"  @click="openCreateModal" />
                    </div>
                </template>

                <Column field="tax_name" header="Tax Name" sortable></Column>
                <Column field="tax_group" header="Group" sortable>
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.tax_group" severity="info"  />
                    </template>
                </Column>
                <Column field="tax_rate" header="Rate" sortable>
                    <template #body="slotProps">
                        {{ slotProps.data.tax_rate }}%
                    </template>
                </Column>
                <Column field="tax_type" header="Type" sortable class="capitalize"></Column>
                <Column field="status" header="Status" sortable>
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.status === 1 ? 'Active' : 'Inactive'" :severity="slotProps.data.status === 1 ? 'success' : 'danger'" rounded />
                    </template>
                </Column>
                <Column header="Actions" class="text-right" style="width: 120px">
                    <template #body="slotProps">
                        <div class="flex justify-end gap-2">
                            <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                            <Button icon="pi pi-trash" text rounded  @click="deleteTax(slotProps.data.id)" severity="danger" />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' TAX CONFIGURATION'" :style="{ width: '600px' }">
            <div class="grid grid-cols-2 gap-4 py-4">
                <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Tax Name</label>
                    <BaseInput v-model="form.tax_name" fluid autofocus />
                    <small v-if="form.errors.tax_name" class="text-red-500">{{ form.errors.tax_name }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Tax Group</label>
                    <BaseSelect v-model="form.tax_group" :options="taxGroupOptions" optionLabel="label" optionValue="value" fluid />
                    <small v-if="form.errors.tax_group" class="text-red-500">{{ form.errors.tax_group }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Tax Type</label>
                    <BaseSelect v-model="form.tax_type" :options="taxTypeOptions" optionLabel="label" optionValue="value" fluid />
                    <small v-if="form.errors.tax_type" class="text-red-500">{{ form.errors.tax_type }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Tax Rate (%)</label>
                    <BaseInputNumber v-model="form.tax_rate" :minFractionDigits="2" fluid />
                    <small v-if="form.errors.tax_rate" class="text-red-500">{{ form.errors.tax_rate }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Status</label>
                    <div class="flex items-center gap-2 mt-2">
                        <ToggleSwitch v-model="form.status" :trueValue="1" :falseValue="0" />
                        <span class="text-sm">{{ form.status === 1 ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Parent Tax (Compound)</label>
                    <BaseSelect v-model="form.parent_id" :options="parentTaxOptions" optionLabel="label" optionValue="value" placeholder="None (Root Tax)" fluid showClear filter />
                </div>

                <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Ledger Account Mapping</label>
                    <BaseSelect v-model="form.account_id" :options="ledgers" optionLabel="title" optionValue="id" placeholder="Select Ledger" fluid showClear filter />
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

