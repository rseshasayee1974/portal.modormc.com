<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import PlantCreateForm from './components/PlantCreateForm.vue';
import PlantIndexList from './components/PlantIndexList.vue';

const props = defineProps<{
    plants: any;
    filters: any;
    entities: any[];
    addressTypes: any[];
    contactTypes: any[];
    states: any[];
}>();

const toast = useToast();
const page = usePage();
const canEditPlantIdentityOnUpdate = computed(() => (page.props as any).user_role === 'Saas Owner');

const searchQuery = ref(props.filters?.search || '');
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const blankForm = () => ({
    entity_id: props.entities?.[0]?.id ?? null,
    code: '',
    name: '',
    mixer_capacity: null as number | null,
    email_address: '',
    mobile_number: '',
    plant_type: '',
    gstin: '',
    latitude: '',
    longitude: '',
    is_main: false,
    is_active: true,
    scheduler_api_url: '',
    scheduler_api_token: '',
    scheduler_oauth_url: '',
    scheduler_client_id: '',
    scheduler_client_secret: '',
    einvoice_client_id: '',
    einvoice_secret: '',
    ewaybill_client_id: '',
    ewaybill_secret: '',
    logo: null as File | null,
    seal_sign_path: null as string | null,
    seal_sign: null as File | null,
    address: {
        address_type_id: props.addressTypes?.[0]?.id ?? null,
        line_1: '', line_2: '', city: '',
        state_id: null,
        zipcode: '', landmark: '',
    },
    contact: {
        contact_type_id: props.contactTypes?.[0]?.id ?? null,
        name: '', email: '', mobile: '', alt_mobile: '', landline: '',
    },
});

const createForm = useForm(blankForm());
const editForm = useForm(blankForm());

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
};

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
};

const populatePlantForm = (form: any, plant: any) => {
    const address = plant.addresses?.[0] || {};
    const contact = plant.contacts?.[0] || {};
    
    form.entity_id = plant.entity_id;
    form.code = plant.code;
    form.name = plant.name;
    form.mixer_capacity = plant.mixer_capacity != null ? Number(plant.mixer_capacity) : null;
    form.email_address = plant.email_address || '';
    form.mobile_number = plant.mobile_number || '';
    form.plant_type = plant.plant_type || '';
    form.gstin = plant.gstin || '';
    form.latitude = plant.latitude || '';
    form.longitude = plant.longitude || '';
    form.is_main = Boolean(plant.is_main);
    form.is_active = Boolean(plant.is_active);
    form.scheduler_api_url = plant.scheduler_api_url || '';
    form.scheduler_api_token = plant.scheduler_api_token || '';
    form.scheduler_oauth_url = plant.scheduler_oauth_url || '';
    form.scheduler_client_id = plant.scheduler_client_id || '';
    form.scheduler_client_secret = plant.scheduler_client_secret || '';
    form.einvoice_client_id = plant.einvoice_client_id || '';
    form.einvoice_secret = plant.einvoice_secret || '';
    form.ewaybill_client_id = plant.ewaybill_client_id || '';
    form.ewaybill_secret = plant.ewaybill_secret || '';
    form.logo_path = plant.logo_path || null;
    form.logo = null;
    form.seal_sign_path = plant.seal_sign_path || null;
    form.seal_sign = null;
    
    form.address = {
        address_type_id: address.address_type_id || (props.addressTypes?.[0]?.id ?? null),
        line_1: address.line_1 || '',
        line_2: address.line_2 || '',
        city: address.city || '',
        state_id: address.state_id || null,
        zipcode: address.zipcode || '',
        landmark: address.landmark || '',
    };
    
    form.contact = {
        contact_type_id: contact.contact_type_id || (props.contactTypes?.[0]?.id ?? null),
        name: contact.name || '',
        email: contact.email || '',
        mobile: contact.mobile || '',
        alt_mobile: contact.alt_mobile || '',
        landline: contact.landline || '',
    };
};

const handleExpandedRowsUpdate = (rows: Record<number, boolean>) => {
    const nextRows = rows || {};
    const expandedIdStr = Object.keys(nextRows).find((key) => nextRows[Number(key)]);

    if (!expandedIdStr) {
        resetEditForm();
        return;
    }

    const plantId = Number(expandedIdStr);
    const plant = props.plants.data.find((item: any) => item.id === plantId);

    if (!plant) {
        resetEditForm();
        return;
    }

    if (editingId.value !== plantId) {
        editingId.value = plantId;
        editForm.clearErrors();
        populatePlantForm(editForm, plant);
    }

    expandedRows.value = { [plantId]: true };
};

const submitCreate = () => {
    createForm.post(route('plants.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetCreateForm();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Plant created successfully', life: 1500 });
        }
    });
};

const submitEdit = () => {
    if (!editingId.value) return;

    editForm.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('plants.update', editingId.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetEditForm();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Plant updated successfully', life: 1500 });
        }
    });
};

const deletePlant = (id: number) => {
    Swal.fire({
        title: 'Delete Plant?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('plants.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    if (editingId.value === id) resetEditForm();
                    toast.add({ severity: 'error', summary: 'Deleted', detail: 'Plant removed', life: 1500 });
                }
            });
        }
    });
};

const initializePlant = (id: number) => {
    Swal.fire({
        title: 'Initialize Plant?',
        text: 'This will set up default accounting, taxes, products, and other master data. Recommended for new plants.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, initialize!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('plants.initialize', id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Initialized', detail: 'Plant default settings created', life: 1500 });
                }
            });
        }
    });
};

const sendCredentials = (id: number) => {
    const plant = props.plants.data.find((p: any) => p.id === id);
    if (!plant?.email_address) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Plant email address is missing.', life: 3000 });
        return;
    }

    Swal.fire({
        title: 'Send Credentials?',
        text: `This will reset the plant admin password and send new login details to ${plant.email_address}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, send them!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('plants.send-credentials', id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Sent', detail: 'Credentials sent successfully', life: 1500 });
                }
            });
        }
    });
};

// ── Search & Pagination Logic ──────────────────────────────────────────
const fetchPlants = () => {
    router.get(route('plants.index'), {
        search: searchQuery.value,
        sort_field: props.filters?.sort_field || 'id',
        sort_direction: props.filters?.sort_direction || 'desc',
    }, { preserveState: true, preserveScroll: true });
};

watch(searchQuery, () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        fetchPlants();
    }, 300);
});

const handlePageChange = (event: any) => {
    router.get(route('plants.index'), {
        search: searchQuery.value,
        page: event.page + 1,
        sort_field: props.filters?.sort_field,
        sort_direction: props.filters?.sort_direction,
    }, { preserveState: true, preserveScroll: true });
};

const handleSort = (event: any) => {
    router.get(route('plants.index'), {
        search: searchQuery.value,
        sort_field: event.sortField,
        sort_direction: event.sortOrder === 1 ? 'asc' : 'desc',
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Plants">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Plants" />
        <Toast />

        <div class="min-h-screen  pb-10">
            <div class="px-4 sm:px-6 py-8 space-y-10">
                
                <!-- ── Top Create Form ──────────────────────────────────────── -->
                <section class="max-w-7xl">
                    <PlantCreateForm
                        :form="createForm"
                        :entities="entities"
                        :address-types="addressTypes"
                        :contact-types="contactTypes"
                        :states="states"
                        :errors="createForm.errors"
                        :processing="createForm.processing"
                        @submit="submitCreate"
                    />
                </section>

                <!-- ── Plant List ────────────────────────────────────────── -->
                <section>
                    <PlantIndexList
                        :plants="props.plants.data"
                        :search-query="searchQuery"
                        :expanded-rows="expandedRows"
                        :editing-id="editingId"
                        :edit-form="editForm"
                        :entities="entities"
                        :address-types="addressTypes"
                        :contact-types="contactTypes"
                        :states="states"
                        :errors="editForm.errors"
                        :processing="editForm.processing"
                        :can-edit-identity-on-update="canEditPlantIdentityOnUpdate"
                        :total-records="props.plants.total"
                        :per-page="props.plants.per_page"
                        @update:search-query="searchQuery = $event"
                        @update:expanded-rows="handleExpandedRowsUpdate"
                        @page="handlePageChange"
                        @sort="handleSort"
                        @delete="deletePlant"
                        @initialize="initializePlant"
                        @send-credentials="sendCredentials"
                        @submit-edit="submitEdit"
                        @cancel-edit="resetEditForm"
                    />
                </section>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Any additional page-level logic */
</style>
