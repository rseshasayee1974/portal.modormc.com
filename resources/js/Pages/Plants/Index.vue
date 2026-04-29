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
    plant_type: '',
    gstin: '',
    latitude: '',
    longitude: '',
    is_main: false,
    is_active: true,
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
    form.plant_type = plant.plant_type || '';
    form.gstin = plant.gstin || '';
    form.latitude = plant.latitude || '';
    form.longitude = plant.longitude || '';
    form.is_main = Boolean(plant.is_main);
    form.is_active = Boolean(plant.is_active);
    
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
            toast.add({ severity: 'success', summary: 'Success', detail: 'Plant created successfully', life: 3000 });
        }
    });
};

const submitEdit = () => {
    if (!editingId.value) return;

    editForm.put(route('plants.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            resetEditForm();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Plant updated successfully', life: 3000 });
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
                    toast.add({ severity: 'info', summary: 'Deleted', detail: 'Plant removed', life: 3000 });
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
