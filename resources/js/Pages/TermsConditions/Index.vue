<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useTermsConditionStore } from './useTermsConditionStore';

// PrimeVue
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Textarea from 'primevue/textarea';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const props = defineProps({
    termsConditions: Object,
    filters:         Object,
    entities:        Array as () => any[],
});

const store = useTermsConditionStore();
const toast = useToast();
const page  = usePage();

onMounted(() => store.setTermsConditions(props.termsConditions?.data || []));

watch(() => props.termsConditions, (newVal) => {
    store.setTermsConditions(newVal?.data || []);
}, { deep: true });
// ── UI state ───────────────────────────────────────────────────────────────
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const editingId = ref<number | null>(null);
const processing = ref(false);

// ── Table / pagination / search ───────────────────────────────────────────
const search = ref(props.filters?.search || '');
const currentPage = ref(props.termsConditions?.current_page || 1);

const filters = ref({
    global: { value: props.filters?.search || null, matchMode: 'contains' },
});

let searchTimeout: any = null;
watch(() => filters.value.global.value, (newVal) => {
    search.value = newVal || '';
    currentPage.value = 1;
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchTerms();
    }, 400);
});

const form = ref({
    entity_id: props.entities?.[0]?.id ?? null as number | null,
    order_type: '',
    terms_condition: '',
    status: 'active',
    errors: {} as any
});

const typeOptions = [
    { label: 'Purchase Order', value: 'Purchase Order' },
    { label: 'Customer PO', value: 'Customer PO' },
    { label: 'Quotation', value: 'Quotation' },
    { label: 'Delivery Challan', value: 'Delivery Challan' },
];

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    form.value = {
        entity_id: props.entities?.[0]?.id ?? null,
        order_type: '',
        terms_condition: '',
        status: 'active',
        errors: {}
    };
    showModal.value = true;
};

const openEditModal = (item: any) => {
    modalMode.value = 'edit';
    editingId.value = item.id;
    form.value = {
        entity_id: item.entity_id,
        order_type: item.order_type,
        terms_condition: item.terms_condition,
        status: item.status,
        errors: {}
    };
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submitForm = async () => {
    processing.value = true;
    form.value.errors = {};
    try {
        if (modalMode.value === 'edit') {
            await axios.put(route('termsconditions.update', { termscondition: editingId.value }), form.value);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Terms updated' });
        } else {
            await axios.post(route('termsconditions.store'), form.value);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Terms created' });
        }
        closeModal();
        fetchTerms();
    } catch (err: any) {
        if (err.response?.data?.errors) form.value.errors = err.response.data.errors;
        else toast.add({ severity: 'error', summary: 'Error', detail: 'An error occurred' });
    } finally {
        processing.value = false;
    }
};

const deleteTerms = (id: number) => {
    Swal.fire({
        title: 'Delete these terms?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Delete',
    }).then(async res => {
        if (res.isConfirmed) {
            try {
                await axios.delete(route('termsconditions.destroy', { termscondition: id }));
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Terms removed' });
                fetchTerms();
            } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete' }); }
        }
    });
};

const fetchTerms = () => router.get(route('termsconditions.index'), {
    search: search.value, 
    page: currentPage.value,
}, { preserveState: true, preserveScroll: true });

const onPageChange = (event: any) => {
    currentPage.value = event.page + 1;
    fetchTerms();
};

const onSearch = () => {
    currentPage.value = 1;
    fetchTerms();
};
</script>

<template>
    <AppLayout title="Terms & Conditions">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <BaseDataTable 
                :value="store.termsConditions" 
                v-model:filters="filters"
                lazy 
                :paginator="true" 
                :rows="props.termsConditions.per_page" 
                :totalRecords="props.termsConditions.total"
                @page="onPageChange" 
                :first="(currentPage - 1) * props.termsConditions.per_page"
                showSearch
                showSerial
                heading="Contractual Clauses"
                headingIcon="DocumentTextIcon"
            >
                <template #toolbar>
                    <Button label="New Terms" icon="pi pi-plus"  @click="openCreateModal" />
                </template>

                <Column field="entity.legal_name" header="Entity" sortable></Column>
                <Column field="order_type" header="Type" sortable>
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.order_type" severity="info"  />
                    </template>
                </Column>
                <Column field="terms_condition" header="Terms Preview">
                    <template #body="slotProps">
                        <div class="line-clamp-1 opacity-70">{{ slotProps.data.terms_condition }}</div>
                    </template>
                </Column>
                <Column field="status" header="Status" sortable style="width: 100px">
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.status.toUpperCase()" :severity="slotProps.data.status === 'active' ? 'success' : 'secondary'" rounded />
                    </template>
                </Column>
                <Column header="Actions" class="text-right" style="width: 120px">
                    <template #body="slotProps">
                        <div class="flex justify-end gap-2">
                            <Button icon="pi pi-pencil" text rounded  @click="openEditModal(slotProps.data)" severity="info" />
                            <Button icon="pi pi-trash" text rounded  @click="deleteTerms(slotProps.data.id)" severity="danger" />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>

        <Dialog v-model:visible="showModal" modal :header="modalMode.toUpperCase() + ' TERMS & CONDITIONS'" :style="{ width: '800px' }">
            <div class="grid grid-cols-2 gap-4 py-4">
                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Legal Entity</label>
                    <BaseSelect v-model="form.entity_id" :options="props.entities" optionLabel="legal_name" optionValue="id" placeholder="Select Entity" fluid filter />
                    <small v-if="form.errors.entity_id" class="text-red-500">{{ form.errors.entity_id[0] }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Order Type</label>
                    <BaseSelect v-model="form.order_type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" fluid editable />
                    <small v-if="form.errors.order_type" class="text-red-500">{{ form.errors.order_type[0] }}</small>
                </div>

                <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Status</label>
                    <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <div class="flex flex-col gap-2 col-span-2">
                    <label class="text-xs font-semibold uppercase text-gray-500">Terms & Conditions Content</label>
                    <Textarea v-model="form.terms_condition" rows="12" fluid placeholder="Enter the full text for these terms..." />
                    <small v-if="form.errors.terms_condition" class="text-red-500">{{ form.errors.terms_condition[0] }}</small>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <Button label="Cancel" text severity="secondary" @click="closeModal" />
                    <Button label="Save Changes" :loading="processing" @click="submitForm" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

