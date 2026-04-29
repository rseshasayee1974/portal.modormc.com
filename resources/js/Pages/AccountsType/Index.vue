<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useAccountsTypeStore, AccountsType } from '@/Pages/AccountsType/useAccountsTypeStore';
import { useToast } from 'primevue/usetoast';

// Components
import AccountTypeTable from './components/AccountTypeTable.vue';
import AccountTypeCreateForm from './components/AccountTypeCreateForm.vue';
import AccountTypeEditForm from './components/AccountTypeEditForm.vue';

const store = useAccountsTypeStore();
const toast = useToast();

const props = defineProps<{
    account_types: any[];
    accounts: any[];
}>();
console.log(props.account_types);

onMounted(() => {
    store.setAccountTypes(props.account_types);
});

// Modal state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const modalMode = ref<'edit' | 'view'>('edit');
const selectedAccountType = ref<AccountsType | null>(null);

// Modal helpers
const openCreateModal = () => {
    showCreateModal.value = true;
};

const openEditModal = (at: AccountsType) => {
    modalMode.value = 'edit';
    selectedAccountType.value = at;
    showEditModal.value = true;
};

const openViewModal = (at: AccountsType) => {
    modalMode.value = 'view';
    selectedAccountType.value = at;
    showEditModal.value = true;
};

// Delete
const deleteAccountType = (id: number) => {
    Swal.fire({
        title: 'Delete Account Type?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await axios.delete(route('accounttypes.destroy', id));
                store.removeAccountType(id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: response.data.message, life: 3000 });
            } catch {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete record.', life: 3000 });
            }
        }
    });
};
</script>

<template>
    <AppLayout title="Account Types">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6">
            <AccountTypeTable 
                :accountTypes="store.accountTypes"
                @create="openCreateModal"
                @edit="openEditModal"
                @view="openViewModal"
                @delete="deleteAccountType"
            />
        </div>

        <!-- Create Form Component -->
        <AccountTypeCreateForm 
            v-model:show="showCreateModal"
            :accounts="accounts"
        />

        <!-- Edit/View Form Component -->
        <AccountTypeEditForm 
            v-model:show="showEditModal"
            :mode="modalMode"
            :accountType="selectedAccountType"
            :accounts="accounts"
        />
    </AppLayout>
</template>
