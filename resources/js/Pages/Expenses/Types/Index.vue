<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';

// PrimeVue
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';

const page = usePage();
const { can } = usePermissions();
const permits = (action: string) => can(`EXPENSE_TYPE.${action}`) || can(`EXPENSE_TYPES.${action}`);

const props = defineProps<{
    expenseTypes: any[];
    ledgers: { id: number; title: string; code: string }[];
}>();

const editingId = ref<number | null>(null);
const expandedRows = computed(() => editingId.value === null ? {} : { [editingId.value]: true });
const createOpen = ref(false);
const deleting = ref(false);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const ledgerOptions = computed(() => props.ledgers.map(l => ({ label: `${l.title} (${l.code})`, value: l.id })));

const createForm = useForm({ name: '', ledger_id: null as number | null });
const editForm   = useForm({ name: '', ledger_id: null as number | null, status: true });

const submitCreate = () => {
    if (createForm.processing) return;
    createForm.post(route('expensetypes.store'), {
        preserveScroll: true,
        onSuccess: closeCreate,
    });
};

const closeCreate = () => {
    createOpen.value = false;
    createForm.reset();
    createForm.clearErrors();
};

const startEdit = (row: any) => {
    if (editForm.processing) return;
    editingId.value = row.id;
    editForm.clearErrors();
    editForm.name      = row.name;
    editForm.ledger_id = row.ledger_id;
    editForm.status    = Boolean(Number(row.status));
};

const submitEdit = () => {
    if (!editingId.value || editForm.processing) return;
    editForm.put(route('expensetypes.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; editForm.reset(); }
    });
};

const deleteType = (id: number) => {
    Swal.fire({ 
        title: 'Delete expense type?', 
        text: 'Are you sure you want to delete this category?',
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonText: 'Yes, Delete',
        confirmButtonColor: '#ef4444'
    }).then((r) => {
        if (r.isConfirmed && !deleting.value) {
            deleting.value = true;
            router.delete(route('expensetypes.destroy', id), {
                preserveScroll: true,
                onError: (errors) => Swal.fire({ icon: 'error', title: 'Unable to delete category', text: errors.expense_type }),
                onFinish: () => { deleting.value = false; },
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Expense Types">
        <template #header><ModuleSubTopNav /></template>

        <div class="page-container">
            <div class="page-heading">
                <div>
                    <h1 class="page-title">Expense Categories</h1>
                    <p class="page-sub">Manage expense categories and their linked ledgers</p>
                </div>
                <div class="page-stat">
                    <i class="pi pi-tags text-indigo-400" aria-hidden="true"></i>
                    <span>{{ expenseTypes.length }} total categories</span>
                </div>
            </div>

            <div v-if="permits('CREATE')" class="create-panel" :class="{ 'create-panel--open': createOpen }">
                <button type="button" class="create-panel__header" :aria-expanded="createOpen"
                    :disabled="createForm.processing" @click="createOpen ? closeCreate() : createOpen = true">
                    <div class="flex items-center gap-3">
                        <div class="create-panel__icon"><i class="pi pi-tag text-indigo-500"></i></div>
                        <div class="text-left">
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-700">New Expense Category</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">Add a category and optionally link a ledger</p>
                        </div>
                    </div>
                    <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': createOpen }">
                        <i class="pi pi-plus text-[10px]"></i>
                    </div>
                </button>

                <Transition name="panel-slide">
                    <form v-if="createOpen" class="create-panel__body space-y-5" @submit.prevent="submitCreate">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <BaseInput v-model="createForm.name" label="Category Name" required placeholder="e.g. Fuel"
                                :error="createForm.errors.name" :disabled="createForm.processing" />
                            <BaseSelect v-model="createForm.ledger_id" label="Linked Ledger" :options="ledgerOptions"
                                optionLabel="label" optionValue="value" filter showClear placeholder="Select a ledger"
                                :error="createForm.errors.ledger_id" :disabled="createForm.processing" />
                        </div>
                        <BaseFormActions label="Add Category" :loading="createForm.processing" @cancel="closeCreate" />
                    </form>
                </Transition>
            </div>

            <BaseDataTable :value="expenseTypes" :expandedRows="expandedRows" dataKey="id"
                v-model:filters="filters" :globalFilterFields="['name', 'ledger.title', 'ledger.code']"
                showSearch showSerial stripedRows heading="Expense Categories" headingIcon="ListBulletIcon" :rows="15">
                <Column field="name" header="Category Name" sortable />
                <Column field="ledger.title" header="Linked Ledger" sortable>
                    <template #body="{ data }">{{ data.ledger?.title || '—' }}</template>
                </Column>
                <Column field="status" header="Status" sortable>
                    <template #body="{ data }">
                        <Tag :value="Number(data.status) ? 'Active' : 'Inactive'"
                            :severity="Number(data.status) ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column v-if="permits('UPDATE') || permits('DELETE')" header="Actions">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <BaseButton v-if="permits('UPDATE')" icon="pi pi-pencil" variant="text"
                                aria-label="Edit category" :disabled="editForm.processing || deleting"
                                @click.stop="startEdit(data)" />
                            <BaseButton v-if="permits('DELETE')" icon="pi pi-trash" variant="text" severity="danger"
                                aria-label="Delete category" :disabled="deleting || editForm.processing"
                                @click.stop="deleteType(data.id)" />
                        </div>
                    </template>
                </Column>
                <template #expansion="{ data }">
                    <BaseExpansionPanel v-if="editingId === data.id" :title="data.name">
                        <form class="mt-4 space-y-5" @submit.prevent="submitEdit">
                            <div class="grid items-end gap-4 sm:grid-cols-3">
                                <BaseInput v-model="editForm.name" label="Category Name" required
                                    :error="editForm.errors.name" :disabled="editForm.processing" />
                                <BaseSelect v-model="editForm.ledger_id" label="Linked Ledger" :options="ledgerOptions"
                                    optionLabel="label" optionValue="value" filter showClear placeholder="Select a ledger"
                                    :error="editForm.errors.ledger_id" :disabled="editForm.processing" />
                                <div>
                                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                                        <input v-model="editForm.status" type="checkbox" :disabled="editForm.processing" />
                                        Active
                                    </label>
                                    <p v-if="editForm.errors.status" role="alert" class="text-xs text-rose-700">{{ editForm.errors.status }}</p>
                                </div>
                            </div>
                            <BaseFormActions :loading="editForm.processing" @cancel="editingId = null" />
                        </form>
                    </BaseExpansionPanel>
                </template>
                <template #empty>
                    <p class="py-8 text-center text-sm text-slate-500">No expense categories found.</p>
                </template>
            </BaseDataTable>
        </div>
    </AppLayout>
</template>
