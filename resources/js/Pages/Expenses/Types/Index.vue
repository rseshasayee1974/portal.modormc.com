<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import { TagIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';

const page = usePage();

const props = defineProps<{
    expenseTypes: any[];
    ledgers: { id: number; name: string; code: string }[];
}>();

const searchQuery   = ref('');
const editingId     = ref<number | null>(null);

const filtered = computed(() => {
    if (!searchQuery.value) return props.expenseTypes;
    const q = searchQuery.value.toLowerCase();
    return props.expenseTypes.filter((t: any) => t.name.toLowerCase().includes(q));
});

const ledgerOptions = computed(() => props.ledgers.map(l => ({ label: `${l.name} (${l.code})`, value: l.id })));

const createForm = useForm({ name: '', ledger_id: null as number | null });
const editForm   = useForm({ name: '', ledger_id: null as number | null, status: true });

const submitCreate = () => {
    createForm.post(route('expensetypes.store'), {
        onSuccess: () => { createForm.reset(); createForm.clearErrors(); }
    });
};

const startEdit = (row: any) => {
    editingId.value = row.id;
    editForm.name      = row.name;
    editForm.ledger_id = row.ledger_id;
    editForm.status    = row.status;
};

const submitEdit = () => {
    if (!editingId.value) return;
    editForm.put(route('expensetypes.update', editingId.value), {
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
        if (r.isConfirmed) {
            editForm.delete(route('expensetypes.destroy', id), {
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted', showConfirmButton: false, timer: 2000 })
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 3000 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Expense Types">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-12 bg-slate-50/50 dark:bg-slate-950 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Create Form -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-xl border-t-4 border-purple-500 overflow-hidden">
                    <div class="p-5 bg-purple-50/50 dark:bg-purple-900/10 flex items-center gap-3 border-b dark:border-slate-800">
                        <div class="p-2 bg-purple-600 rounded-xl shadow">
                            <TagIcon class="w-5 h-5 text-white" />
                        </div>
                        <h2 class="text-lg font-black text-gray-800 dark:text-gray-100 uppercase tracking-tight">New Expense Category</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-24 gap-6 items-end">
                            <div class="md:col-span-11 flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Category Name *</label>
                                <BaseInput v-model="createForm.name" placeholder="e.g. Fuel, Maintenance..." class="w-full" :class="{'p-invalid': createForm.errors.name}" />
                                <small v-if="createForm.errors.name" class="p-error">{{ createForm.errors.name }}</small>
                            </div>
                            <div class="md:col-span-10 flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Linked Ledger</label>
                                <BaseSelect v-model="createForm.ledger_id" :options="ledgerOptions" optionLabel="label" optionValue="value" filter placeholder="Optional ledger..." class="w-full" />
                            </div>
                            <div class="md:col-span-3">
                                <Button severity="primary" :loading="createForm.processing" @click="submitCreate" class="w-full rounded-xl">
                                    <template #icon><PlusIcon class="w-4 h-4 mr-2"/></template>
                                    Add
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Types Table -->
                <div class="bg-white dark:bg-slate-900 shadow-xl rounded-3xl p-8 border border-slate-100 dark:border-slate-800">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-black text-gray-800 dark:text-gray-100 uppercase tracking-tight">Expense Categories</h3>
                        <span class="relative w-64">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <MagnifyingGlassIcon class="w-4 h-4 text-gray-400" />
                            </span>
                            <BaseInput v-model="searchQuery" placeholder="Search..." class="w-full pl-9 rounded-full" />
                        </span>
                    </div>

                    <DataTable :value="filtered" stripedRows paginator :rows="15" class="modern-table">
                        <Column header="Type Name">
                            <template #body="slotProps">
                                <div v-if="editingId === slotProps.data.id">
                                    <BaseInput v-model="editForm.name" class="w-full"  />
                                </div>
                                <div v-else class="font-semibold text-gray-800 dark:text-slate-200">
                                    {{ slotProps.data.name }}
                                </div>
                            </template>
                        </Column>
                        <Column header="Linked Ledger">
                            <template #body="slotProps">
                                <div v-if="editingId === slotProps.data.id">
                                    <BaseSelect v-model="editForm.ledger_id" :options="ledgerOptions" optionLabel="label" optionValue="value" filter placeholder="Select ledger" class="w-full"  />
                                </div>
                                <div v-else class="text-xs text-gray-500 font-bold uppercase">
                                    {{ slotProps.data.ledger?.name || '—' }}
                                </div>
                            </template>
                        </Column>
                        <Column header="Status" align="center">
                            <template #body="slotProps">
                                <Tag :severity="slotProps.data.status ? 'success' : 'danger'" rounded class="text-[10px] font-black tracking-widest">
                                    {{ slotProps.data.status ? 'ACTIVE' : 'OFF' }}
                                </Tag>
                            </template>
                        </Column>
                        <Column header="Actions" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <template v-if="editingId === slotProps.data.id">
                                        <Button icon="pi pi-check" severity="success" rounded text @click="submitEdit" :loading="editForm.processing" />
                                        <Button icon="pi pi-times" severity="secondary" rounded text @click="editingId = null" />
                                    </template>
                                    <template v-else>
                                        <Button icon="pi pi-pencil" severity="info" text rounded @click="startEdit(slotProps.data)" />
                                        <Button icon="pi pi-trash" severity="danger" text rounded @click="deleteType(slotProps.data.id)" />
                                    </template>
                                </div>
                            </template>
                        </Column>
                        <template #empty>
                            <div class="py-12 flex justify-center opacity-40">
                                <span>No categories yet</span>
                            </div>
                        </template>
                    </DataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-purple-50/50 dark:bg-slate-950 text-purple-500 font-black uppercase text-[11px] tracking-widest py-5 dark:border-slate-800;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply dark:border-slate-800;
}
</style>


