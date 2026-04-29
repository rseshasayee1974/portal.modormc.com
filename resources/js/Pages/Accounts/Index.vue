<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import axios from 'axios';
import { useAccountsStore, Account } from '@/Pages/Accounts/useAccountsStore';
import {
    BriefcaseIcon,
    PencilSquareIcon,
    TrashIcon,
    EyeIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';

// PrimeVue
// Base Components
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

const page = usePage();
const store = useAccountsStore();

const props = defineProps<{
    accounts: Account[];
}>();

onMounted(() => {
    store.setAccounts(props.accounts);
});

// ── Account name type options ──────────────────────────────
const accountOptions = [
    'ASSET', 'EQUITY', 'EXPENSE', 'INCOME', 'LIABILITY', 'REVENUE',
].map(t => ({ label: t, value: t }));

// ── Modal state ────────────────────────────────────────────
const showModal = ref(false);
const modalMode = ref<'create' | 'edit' | 'view'>('create');
const editingId = ref<number | null>(null);

const defaultForm = () => ({
    title: '',
    code: '',
    status: 1,
    processing: false,
    errors: { title: '', code: '' },
});

const modalForm = ref(defaultForm());

// ── Modal helpers ──────────────────────────────────────────
const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    modalForm.value = defaultForm();
    showModal.value = true;
};

const openEditModal = (account: Account) => {
    modalMode.value = 'edit';
    editingId.value = account.id;
    modalForm.value = { ...defaultForm(), title: account.title, code: account.code || '', status: account.status };
    showModal.value = true;
};

const openViewModal = (account: Account) => {
    openEditModal(account);
    modalMode.value = 'view';
};

// ── Automated Code Generation ─────────────────────────────
const handleCategoryChange = async (val: string) => {
    if (modalMode.value !== 'create') return;
    if (!val) {
        modalForm.value.code = '';
        return;
    }

    try {
        const res = await axios.get(route('accounting.nextcode'), {
            params: { category: val, table: 'accounts' }
        });
        if (res.data.code) {
            modalForm.value.code = res.data.code;
        }
    } catch (e) {
        console.warn('Code gen failed', e);
    }
};

const closeModal = () => {
    showModal.value = false;
    modalForm.value = defaultForm();
};

// ── Submit ─────────────────────────────────────────────────
const submitModal = async () => {
    if (!modalForm.value.title || !modalForm.value.code) {
        Swal.fire({ title: 'Validation Error', text: 'Please complete the required fields.', icon: 'warning' });
        return;
    }

    modalForm.value.processing = true;
    modalForm.value.errors.title = '';
    modalForm.value.errors.code = '';

    try {
        if (modalMode.value === 'create') {
            const response = await axios.post(route('accounts.store'), { title: modalForm.value.title, code: modalForm.value.code });
            store.addAccount(response.data.account);
            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: response.data.message });
        } else {
            const response = await axios.put(route('accounts.update', editingId.value), {
                title: modalForm.value.title,
                code: modalForm.value.code,
                status: modalForm.value.status,
            });
            store.updateAccount(response.data.account);
            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: response.data.message });
        }
        closeModal();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            if (error.response.data.errors.title) {
                modalForm.value.errors.title = error.response.data.errors.title[0];
            }
            if (error.response.data.errors.code) {
                modalForm.value.errors.code = error.response.data.errors.code[0];
            }
        } else {
            Swal.fire({ title: 'Error', text: 'An unexpected error occurred.', icon: 'error' });
        }
    } finally {
        modalForm.value.processing = false;
    }
};

// ── Delete ─────────────────────────────────────────────────
const deleteAccount = (id: number) => {
    Swal.fire({
        title: 'Delete Account?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await axios.delete(route('accounts.destroy', id));
                store.removeAccount(id);
                Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: response.data.message });
            } catch {
                Swal.fire({ title: 'Error', text: 'Failed to delete Account.', icon: 'error' });
            }
        }
    });
};

// ── Flash messages ─────────────────────────────────────────
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: flash.success });
    }
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Accounts">
         <template #header>
            <ModuleSubTopNav />
        </template>
        

        <div class="py-12 bg-slate-50 dark:bg-slate-950 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 border border-slate-100 dark:border-slate-800">
                    <div class="flex justify-between items-center py-2 mb-6">
                        <h2 class="flex items-center font-black text-xl text-gray-800 dark:text-gray-100 uppercase tracking-tighter leading-tight">
                            <BriefcaseIcon class="mr-3 h-7 w-7 text-indigo-500" />
                            Chart of Accounts
                        </h2>
                        <BaseButton severity="contrast" label="Add Account" @click="openCreateModal" class="rounded-full px-6 shadow-lg uppercase tracking-widest font-black text-xs h-[44px]">
                            <template #icon><PlusIcon class="w-4 h-4 mr-2" /></template>
                        </BaseButton>
                    </div>

                    <BaseDataTable :value="store.accounts" :rows="12" showSerial>
                        <Column field="code" header="Code" style="width: 120px" sortable>
                            <template #body="slotProps">
                                <span class="font-mono text-[11px] font-black text-indigo-600 dark:text-indigo-400 px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/30 rounded">
                                    {{ slotProps.data.code || '—' }}
                                </span>
                            </template>
                        </Column>
                        <Column field="title" header="Account Title" sortable>
                            <template #body="slotProps">
                                <span class="font-semibold text-gray-800 dark:text-slate-200">{{ slotProps.data.title }}</span>
                            </template>
                        </Column>
                        <Column header="Status" style="width: 120px" sortable field="status">
                            <template #body="slotProps">
                                <Tag :severity="slotProps.data.status === 1 ? 'success' : 'danger'" rounded class="text-[9px] font-black tracking-widest">
                                    {{ slotProps.data.status === 1 ? 'ACTIVE' : 'INACTIVE' }}
                                </Tag>
                            </template>
                        </Column>
                        <Column header="Actions" align="right" style="width: 140px">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-1">
                                    <BaseButton icon="pi pi-eye" severity="secondary" text rounded @click="openViewModal(slotProps.data)" />
                                    <BaseButton icon="pi pi-pencil" severity="info" text rounded @click="openEditModal(slotProps.data)" />
                                    <BaseButton icon="pi pi-trash" severity="danger" text rounded @click="deleteAccount(slotProps.data.id)" />
                                </div>
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>
            </div>
        </div>

        <!-- Create / Edit / View Modal -->
        <Dialog v-model:visible="showModal" modal :header="modalMode === 'create' ? 'Add Account' : modalMode === 'edit' ? 'Edit Account' : 'View Account'" :style="{ width: '480px' }" class="rounded-3xl">
            <div class="space-y-4 pt-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Code</label>
                        <BaseInput v-model="modalForm.code" placeholder="Auto..." :disabled="modalMode === 'view'" class="w-full" :class="{'p-invalid': modalForm.errors.code}" />
                        <small v-if="modalForm.errors.code" class="p-error">{{ modalForm.errors.code }}</small>
                    </div>

                    <div class="col-span-2 flex flex-col gap-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Account Title (Category) *</label>
                        <BaseSelect
                            v-if="modalMode !== 'view'"
                            v-model="modalForm.title"
                            :options="accountOptions"
                            optionLabel="label"
                            optionValue="value"
                            filter
                            editable
                            placeholder="e.g. ASSET, EQUITY…"
                            class="w-full"
                            @change="(e) => handleCategoryChange(e.value)"
                            :class="{'p-invalid': modalForm.errors.title}"
                        />
                        <BaseInput v-else :value="modalForm.title" disabled class="w-full" />
                        <small v-if="modalForm.errors.title" class="p-error">{{ modalForm.errors.title }}</small>
                    </div>
                </div>

                <div v-if="modalMode === 'edit'" class="flex flex-col gap-1.5">
                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Status</label>
                    <BaseSelect
                        v-model="modalForm.status"
                        :options="[{ label: 'Active', value: 1 }, { label: 'Inactive', value: 0 }]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-6 border-t dark:border-slate-800">
                    <BaseButton :label="modalMode === 'view' ? 'Close' : 'Cancel'" severity="secondary" text @click="closeModal" class="rounded-xl px-6 font-bold uppercase tracking-widest text-[10px]" />
                    <BaseButton
                        v-if="modalMode !== 'view'"
                        severity="contrast"
                        :loading="modalForm.processing"
                        @click="submitModal"
                        class="rounded-xl px-8 font-black uppercase tracking-widest text-[10px]"
                        label="Save Account"
                    />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-slate-50/50 dark:bg-slate-950 text-slate-500 font-black uppercase text-[10px] tracking-widest py-5 dark:border-slate-800;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply dark:border-slate-800;
}
</style>


