<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ExpenseCreateForm from './components/ExpenseCreateForm.vue';
import ExpenseBatchForm from './components/ExpenseBatchForm.vue';
import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps<{
    expenses: any[];
    expenseTypes: any[];
    ledgers: any[];
    machines: any[];
    patrons: any[];
}>();

const toast = useToast();
const { can } = usePermissions();
const permits = (action: string) => can(`EXPENSE.${action}`) || can(`EXPENSES.${action}`);
const editingExpense = ref<any>(null);
const showEdit = ref(false);
const deletingExpense = ref(false);

const editExpense = (expense: any) => {
    editingExpense.value = expense;
    showEdit.value = true;
};

const deleteExpense = async (expense: any) => {
    const result = await Swal.fire({
        title: 'Delete expense?',
        text: expense.ref_no,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
    });
    if (!result.isConfirmed || deletingExpense.value) return;

    deletingExpense.value = true;
    router.delete(route('expenses.destroy', expense.id), {
        preserveScroll: true,
        onSuccess: () => toast.add({ severity: 'success', summary: 'Expense deleted', life: 2500 }),
        onError: (errors) => toast.add({ severity: 'error', summary: 'Unable to delete expense', detail: errors.expense, life: 4500 }),
        onFinish: () => { deletingExpense.value = false; },
    });
};

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat(APP_LOCALE, { style: 'currency', currency: 'INR' }).format(val);
};

</script>

<template>
    <AppLayout title="Operational Expenses">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Toast />

        <div class="min-h-screen py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-8">
                
                <!-- Creation Form Section -->
                <section v-if="permits('CREATE')">
                    <ExpenseBatchForm
                        :expense-types="expenseTypes"
                        :ledgers="ledgers"
                        :machines="machines"
                        :patrons="patrons"
                    />
                </section>

                <section class="space-y-4">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <BaseDataTable
                            :value="expenses"
                            v-model:filters="filters"
                            :globalFilterFields="['ref_no', 'note', 'expense_type.name', 'vendor.legal_name']"
                            showSearch
                            showSerial
                            heading="Expense History"
                            headingIcon="CreditCardIcon"
                            :rows="15"
                        >
                            
                            <Column field="date" header="Date" sortable>
                                <template #body="slotProps">
                                    <span class="text-xs font-bold text-slate-600">{{ entityLocaleDate(slotProps.data.date, APP_LOCALE, { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                                </template>
                            </Column>

                            <Column field="ref_no" header="Reference" sortable>
                                <template #body="slotProps">
                                    <span class="text-[11px] font-mono font-black text-indigo-600">{{ slotProps.data.ref_no }}</span>
                                </template>
                            </Column>

                            <Column field="expense_type.name" header="Category" sortable>
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700 text-sm">{{ slotProps.data.expense_type?.name }}</span>
                                        <span class="text-[10px] text-slate-400 uppercase font-black">{{ slotProps.data.machine?.registration || 'General Plant' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="vendor.legal_name" header="Payee">
                                <template #body="slotProps">
                                    <span class="text-xs text-slate-500 font-medium">{{ slotProps.data.vendor?.legal_name || slotProps.data.customer?.legal_name || '-' }}</span>
                                </template>
                            </Column>

                            <Column field="amount" header="Amount" sortable class="text-right">
                                <template #body="slotProps">
                                    <span class="font-black text-slate-800">{{ formatCurrency(slotProps.data.amount) }}</span>
                                </template>
                            </Column>

                            <Column field="ledger.title" header="Paid Through" />
                            <Column field="note" header="Notes" />
                            <Column header="Accounting">
                                <template #body="{ data }">
                                    <Tag :value="data.accounting_entry?.is_status === 'POSTED' ? 'Posted' : 'Not posted'"
                                        :severity="data.accounting_entry?.is_status === 'POSTED' ? 'success' : 'warn'" />
                                </template>
                            </Column>
                            <Column header="Status">
                                <template #body="{ data }">
                                    <Tag :value="Number(data.status) ? 'Recorded' : 'Inactive'" :severity="Number(data.status) ? 'success' : 'secondary'" />
                                </template>
                            </Column>
                            <Column v-if="permits('UPDATE') || permits('DELETE')" header="Actions">
                                <template #body="{ data }">
                                    <Button v-if="permits('UPDATE')" icon="pi pi-pencil" text aria-label="Edit expense" @click="editExpense(data)" />
                                    <Button v-if="permits('DELETE')" icon="pi pi-trash" text severity="danger" aria-label="Delete expense"
                                        :disabled="deletingExpense" @click="deleteExpense(data)" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>
                </section>
            </div>
        </div>
        <Dialog v-model:visible="showEdit" modal header="Edit Expense" class="w-full max-w-5xl">
            <ExpenseCreateForm v-if="showEdit && editingExpense" :expense="editingExpense"
                :expense-types="expenseTypes" :ledgers="ledgers" :machines="machines" :patrons="patrons"
                @saved="showEdit = false" />
        </Dialog>
    </AppLayout>
</template>

