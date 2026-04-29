<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';

import EntityCreateForm from './components/EntityCreateForm.vue';
import EntityIndexList from './components/EntityIndexList.vue';
import {
    useEntityStore,
    type Entity,
    type EntityAddress,
    type EntityContact,
    type EntityBankAccount,
    type EntityTax,
} from '@/Pages/Entities/useEntityStore';
import { usePage } from '@inertiajs/vue3';

// ── Props ────────────────────────────────────────────────
interface Props {
    entities: Entity[];
    entityTypes: { id: number; type: string }[];
    addressTypes: { id: number; type: string }[];
    contactTypes: { id: number; type: string }[];
    bankAccountTypes: { id: number; type: string }[];
    countries: { id: number; country_name: string }[];
    stateCodes: { id: number; state_name: string; state_code: string; country_id: number }[];
}

const props = defineProps<Props>();

const page = usePage();
const store = useEntityStore();
const toast = useToast();

const isSuperAdmin = computed(() => (page.props as any).user_role === 'Super Administrator');

onMounted(() => {
    store.setEntities(props.entities);
});

// ── State ─────────────────────────────────────────────────
const searchQuery = ref('');
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});
const processing = ref(false);

// ── Forms ─────────────────────────────────────────────────
const blankForm = () => ({
    entity_type: null as number | null,
    legal_name: '',
    alias: '',
    email: '',
    url: '',
    logo_file: '',
    description: '',
    is_active: true,
    is_suspended: false,
    addresses: [] as EntityAddress[],
    contacts: [] as EntityContact[],
    bank_accounts: [] as EntityBankAccount[],
    taxes: [] as EntityTax[],
    processing: false,
    errors: {} as Record<string, string>,
});

const createForm = ref(blankForm());
const editForm = ref(blankForm());

// ── Filtered Entities ──────────────────────────────────────
const filteredEntities = computed(() => {
    if (!searchQuery.value) return store.entities;
    const q = searchQuery.value.toLowerCase();
    return store.entities.filter((e: Entity) =>
        (e.legal_name?.toLowerCase().includes(q) ?? false) ||
        (e.alias?.toLowerCase().includes(q) ?? false) ||
        (e.email?.toLowerCase().includes(q) ?? false)
    );
});

// ── Reset Helpers ──────────────────────────────────────────
const resetCreateForm = () => {
    createForm.value = blankForm();
};

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.value = blankForm();
};

// ── Populate Edit Form from Entity ────────────────────────
const populateEditForm = async (entity: Entity) => {
    editForm.value = {
        ...blankForm(),
        entity_type: entity.entity_type,
        legal_name: entity.legal_name,
        alias: entity.alias ?? '',
        email: entity.email ?? '',
        url: entity.url ?? '',
        logo_file: entity.logo_file ?? '',
        description: entity.description ?? '',
        is_active: !!entity.is_active,
        is_suspended: !!entity.is_suspended,
        addresses: [],
        contacts: [],
        bank_accounts: [],
        taxes: [],
    };

    // Load full details via API
    processing.value = true;
    try {
        const res = await axios.get(route('entities.show', entity.id));
        const full = res.data.entity;
        editForm.value.addresses = full.addresses || [];
        editForm.value.contacts = full.contacts || [];
        editForm.value.bank_accounts = full.bankAccounts || full.bank_accounts || [];
        editForm.value.taxes = full.taxes || [];
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load entity details.', life: 3000 });
    } finally {
        processing.value = false;
    }
};

// ── Row Expansion Handler ──────────────────────────────────
const handleExpandedRowsUpdate = async (rows: Record<number, boolean>) => {
    const nextRows = rows || {};
    const expandedId = Object.keys(nextRows).find(key => nextRows[Number(key)]);

    if (!expandedId) {
        resetEditForm();
        return;
    }

    const entityId = Number(expandedId);
    const entity = store.entities.find((e: Entity) => e.id === entityId);

    if (!entity) {
        resetEditForm();
        return;
    }

    if (editingId.value !== entityId) {
        editingId.value = entityId;
        editForm.value = blankForm();
        await populateEditForm(entity);
    }

    expandedRows.value = { [entityId]: true };
};

// ── Create ─────────────────────────────────────────────────
const submitCreate = async () => {
    createForm.value.processing = true;
    createForm.value.errors = {};

    const payload = {
        ...createForm.value,
        is_active: createForm.value.is_active ? 1 : 0,
        is_suspended: createForm.value.is_suspended ? 1 : 0,
    };

    try {
        const res = await axios.post(route('entities.store'), payload);
        store.addEntity(res.data.entity);
        resetCreateForm();
        toast.add({ severity: 'success', summary: 'Success', detail: 'Entity created successfully.', life: 3000 });
    } catch (e: any) {
        if (e.response?.data?.errors) createForm.value.errors = e.response.data.errors;
        else toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to create entity.', life: 4000 });
    } finally {
        createForm.value.processing = false;
    }
};

// ── Update ─────────────────────────────────────────────────
const submitEdit = async () => {
    if (!editingId.value) return;

    editForm.value.processing = true;
    editForm.value.errors = {};

    const payload = {
        ...editForm.value,
        is_active: editForm.value.is_active ? 1 : 0,
        is_suspended: editForm.value.is_suspended ? 1 : 0,
    };

    try {
        const res = await axios.put(route('entities.update', editingId.value), payload);
        store.updateEntity(res.data.entity);
        resetEditForm();
        toast.add({ severity: 'success', summary: 'Success', detail: 'Entity updated successfully.', life: 3000 });
    } catch (e: any) {
        if (e.response?.data?.errors) editForm.value.errors = e.response.data.errors;
        else toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update entity.', life: 4000 });
    } finally {
        editForm.value.processing = false;
    }
};

// ── Delete ─────────────────────────────────────────────────
const deleteEntity = (id: number) => {
    Swal.fire({
        title: 'Delete Entity?',
        text: 'This will permanently remove all associated records.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                await axios.delete(route('entities.destroy', id));
                store.removeEntity(id);
                if (editingId.value === id) resetEditForm();
                toast.add({ severity: 'info', summary: 'Deleted', detail: 'Entity removed.', life: 3000 });
            } catch {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed.', life: 3000 });
            }
        }
    });
};

// ── Switch Entity Context ──────────────────────────────────
const switchToEntity = async (id: number) => {
    try {
        await axios.post('/context/selectentity', { entity_id: id });
        window.location.reload();
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Context switch failed.', life: 3000 });
    }
};
</script>

<template>
    <AppLayout title="Entity Directory">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Toast />

        <main class="p-6 flex flex-col gap-6">
            <!-- Create Form Card -->
            <section class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <EntityCreateForm
                    :form="createForm"
                    :entity-types="entityTypes"
                    :address-types="addressTypes"
                    :contact-types="contactTypes"
                    :bank-account-types="bankAccountTypes"
                    :countries="countries"
                    :state-codes="stateCodes"
                    @submit="submitCreate"
                />
            </section>

            <!-- Index List (DataTable) -->
            <EntityIndexList
                :entities="filteredEntities"
                :search-query="searchQuery"
                :expanded-rows="expandedRows"
                :editing-id="editingId"
                :edit-form="editForm"
                :entity-types="entityTypes"
                :address-types="addressTypes"
                :contact-types="contactTypes"
                :bank-account-types="bankAccountTypes"
                :countries="countries"
                :state-codes="stateCodes"
                :is-super-admin="isSuperAdmin"
                @update:search-query="searchQuery = $event"
                @update:expanded-rows="handleExpandedRowsUpdate"
                @delete="deleteEntity"
                @switch-entity="switchToEntity"
                @submit-edit="submitEdit"
                @cancel-edit="resetEditForm"
            />
        </main>
    </AppLayout>
</template>
