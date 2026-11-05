<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Menu from 'primevue/menu';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ExpenseCreateForm from './components/ExpenseCreateForm.vue';
import ExpenseBatchForm from './components/ExpenseBatchForm.vue';
import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps<{
    expenses: any[];
    expenseTypes: any[];
    employees: any[];
    ledgers: any[];
    machines: any[];
    patrons: any[];
}>();

const toast = useToast();
const { can } = usePermissions();
const permits = (action: string) => can(`EXPENSE.${action}`) || can(`EXPENSES.${action}`);
const expandedRows = ref<Record<number, boolean>>({});
const actionMenu = ref();
const selectedExpense = ref<any>(null);
const deletingExpense = ref(false);

const editExpense = (expense: any) => {
    if (!permits('UPDATE')) return;
    expandedRows.value = expandedRows.value[expense.id] ? {} : { [expense.id]: true };
};
const toggleActions = (event: Event, expense: any) => {
    selectedExpense.value = expense;
    actionMenu.value?.toggle(event);
};
const actionItems = computed(() => [
    ...(permits('UPDATE') ? [{ label: expandedRows.value[selectedExpense.value?.id] ? 'Close edit' : 'Edit expense', icon: 'pi pi-pencil', command: () => editExpense(selectedExpense.value) }] : []),
    ...(permits('DELETE') ? [{ label: 'Delete expense', icon: 'pi pi-trash', disabled: deletingExpense.value, command: () => deleteExpense(selectedExpense.value) }] : []),
]);

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
        onSuccess: () => {
            expandedRows.value = {};
            toast.add({ severity: 'success', summary: 'Expense deleted', life: 2500 });
        },
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
        <Menu ref="actionMenu" id="expense-row-actions" :model="actionItems" popup />

        <div class="min-h-screen py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-8">
                
                <!-- Creation Form Section -->
                <section v-if="permits('CREATE')">
                    <ExpenseBatchForm
                        :expense-types="expenseTypes"
                        :employees="employees"
                        :ledgers="ledgers"
                        :machines="machines"
                        :patrons="patrons"
                    />
                </section>

                <section class="space-y-4">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <BaseDataTable
                            :value="expenses"
                            v-model:expandedRows="expandedRows"
                            :expandOnRowClick="permits('UPDATE')"
                            v-model:filters="filters"
                            :globalFilterFields="['ref_no', 'note', 'expense_type.name', 'vendor.legal_name', 'made_by_employee.first_name', 'made_by_employee.last_name']"
                            showSearch
                            showSerial
                            heading="Expense History"
                            headingIcon="CreditCardIcon"
                            :rows="30"
                        >
                            <!-- <Column v-if="permits('UPDATE')" header="" style="width: 2.5rem">
                                <template #body="{ data }">
                                    <Button :icon="expandedRows[data.id] ? 'pi pi-chevron-down' : 'pi pi-chevron-right'" text
                                        :aria-label="expandedRows[data.id] ? 'Close expense edit' : 'Expand expense edit'"
                                        :aria-expanded="Boolean(expandedRows[data.id])" @click.stop="editExpense(data)" />
                                </template>
                            </Column> -->
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
                            <!-- <Column field="made_by_employee.first_name" header="Made By" sortable>
                                <template #body="{ data }">
                                    <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                        <i class="pi pi-user text-[#3B638E]" aria-hidden="true" />{{ data.made_by_employee ? [data.made_by_employee.first_name, data.made_by_employee.last_name].filter(Boolean).join(' ') : '—' }}
                                    </span>
                                </template>
                            </Column> -->
                            <!-- <Column field="note" header="Notes" />
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
                            </Column> -->
                            <Column v-if="permits('UPDATE') || permits('DELETE')" header="Actions">
                                <template #body="{ data }">
                                    <Button icon="pi pi-ellipsis-v" text aria-label="Expense actions" aria-haspopup="true"
                                        aria-controls="expense-row-actions" @click.stop="toggleActions($event, data)" />
                                </template>
                            </Column>
                            <template v-if="permits('UPDATE')" #expansion="{ data }">
                                <div class="bg-slate-50 p-3 sm:p-4">
                                    <div class="mb-2 flex justify-end">
                                        <Button icon="pi pi-times" text label="Close" @click="expandedRows = {}" />
                                    </div>
                                    <ExpenseCreateForm :key="data.id" :expense="data"
                                        :employees="employees"
                                        :expense-types="expenseTypes" :ledgers="ledgers" :machines="machines" :patrons="patrons"
                                        @saved="expandedRows = {}" />
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

