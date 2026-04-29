<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';
import { useLedgerStore, Ledger } from '@/Pages/Ledger/useLedgerStore';

// Components
import LedgerTable from './components/LedgerTable.vue';
import LedgerCreateForm from './components/LedgerCreateForm.vue';
import LedgerEditForm from './components/LedgerEditForm.vue';

const store = useLedgerStore();
const toast = useToast();

const props = defineProps<{
    ledgers: Ledger[];
    account_types: any[];
}>();

onMounted(() => {
    store.setLedgers(props.ledgers);
});

// Modal state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const modalMode = ref<'edit' | 'view'>('edit');
const selectedLedger = ref<Ledger | null>(null);

// Modal helpers
const openCreateModal = () => {
    showCreateModal.value = true;
};

const openEditModal = (ledger: Ledger) => {
    modalMode.value = 'edit';
    selectedLedger.value = ledger;
    showEditModal.value = true;
};

const openViewModal = (ledger: Ledger) => {
    modalMode.value = 'view';
    selectedLedger.value = ledger;
    showEditModal.value = true;
};

// Delete
const deleteLedger = (id: number) => {
    Swal.fire({
        title: 'Delete Ledger?',
        text: 'This action cannot be undone and may affect financial reports.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('ledgers.destroy', id), {
                onSuccess: () => {
                    toast.add({ severity: 'info', summary: 'Deleted', detail: 'Ledger removed' });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="General Ledger">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Toast />

        <main class="p-6 flex flex-col gap-6">
            <!-- <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-800 uppercase">Charter of Accounts</h1>
                <p class="text-sm text-gray-500 font-medium">Manage your financial structure and ledger mappings.</p>
            </div> -->

            <LedgerTable 
                :ledgers="store.ledgers"
                @create="openCreateModal"
                @edit="openEditModal"
                @view="openViewModal"
                @delete="deleteLedger"
            />
        </main>

        <!-- Create Form -->
        <LedgerCreateForm 
            v-model:show="showCreateModal"
            :accountTypes="account_types"
        />

        <!-- Edit/View Form -->
        <LedgerEditForm 
            v-model:show="showEditModal"
            :mode="modalMode"
            :ledger="selectedLedger"
            :accountTypes="account_types"
        />
    </AppLayout>
</template>
