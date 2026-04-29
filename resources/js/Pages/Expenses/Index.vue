<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';

interface Expense {
    id: number;
    reference: string;
    expense_category?: { title: string };
    payment_mode: string;
    amount: number;
    expense_date: string;
    description?: string;
    machine?: { registration: string };
}

const props = defineProps<{
    expenses: Expense[];
    categories: { id: number; title: string }[];
    paymentModes: string[];
    machines: { id: number; registration: string }[];
}>();

const toast = useToast();
const showDialog = ref(false);
const editingId = ref<number | null>(null);
const searchQuery = ref('');

const filteredExpenses = computed(() => {
    if (!searchQuery.value) return props.expenses;
    const q = searchQuery.value.toLowerCase();
    return props.expenses.filter((e: Expense) => 
        (e.reference && e.reference.toLowerCase().includes(q)) ||
        (e.description && e.description.toLowerCase().includes(q))
    );
});

const form = useForm({
    expense_date: new Date(),
    expense_category_id: null as number | null,
    machine_id: null as number | null,
    amount: 0,
    payment_mode: 'Cash',
    description: '',
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
};

const startEdit = (e: Expense) => {
    editingId.value = e.id;
    form.expense_date = new Date(e.expense_date);
    form.expense_category_id = (e as any).expense_category_id;
    form.machine_id = (e as any).machine_id;
    form.amount = Number(e.amount);
    form.payment_mode = e.payment_mode;
    form.description = e.description || '';
    showDialog.value = true;
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('expenses.update', editingId.value), {
            onSuccess: () => {
                showDialog.value = false;
                toast.add({ severity: 'success', summary: 'Updated', detail: 'Expense log synchronized', life: 3000 });
            }
        });
    } else {
        form.post(route('expenses.store'), {
            onSuccess: () => {
                showDialog.value = false;
                toast.add({ severity: 'success', summary: 'Recorded', detail: 'New expense entry saved', life: 3000 });
            }
        });
    }
};

const deleteExpense = (id: number) => {
    Swal.fire({
        title: 'Delete Expense Log?',
        text: "This removal will reverse bookkeeping entries.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('expenses.destroy', id), {
                onSuccess: () => toast.add({ severity: 'info', summary: 'Removed', detail: 'Expense log purged', life: 3000 })
            });
        }
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(val);
};

</script>

<template>
    <AppLayout title="Operational Expenses">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Toast />

        <main class="p-6 flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-gray-800 uppercase">Expense Ledger</h1>
                    <p class="text-sm text-gray-500 font-medium">Monitor and log operational costs, fuel, and maintenance.</p>
                </div>
                <Button label="Record Expense" icon="pi pi-plus"  @click="openCreate" />
            </div>

            <div class="card bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
                <DataTable :value="filteredExpenses" stripedRows class="p-datatable-sm" paginator :rows="15">
                    <template #header>
                        <div class="flex justify-end">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search ml-2" />
                                <BaseInput v-model="searchQuery" placeholder="Search Ref/Desc..."  />
                            </span>
                        </div>
                    </template>

                    <Column header="S.No" style="width: 70px">
                        <template #body="slotProps">
                            <span class="text-gray-400 font-bold">{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
                    <Column field="expense_date" header="Date" sortable>
                        <template #body="slotProps">
                            <span class="text-xs font-semibold text-gray-500">{{ new Date(slotProps.data.expense_date).toLocaleDateString() }}</span>
                        </template>
                    </Column>

                    <Column field="expense_category.title" header="Category / Machine" sortable>
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span class="font-semibold text-gray-700">{{ slotProps.data.expense_category?.title }}</span>
                                <span class="text-[10px] text-indigo-400 font-black uppercase tracking-widest">{{ slotProps.data.machine?.registration || 'General' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="payment_mode" header="Mode" sortable>
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.payment_mode" severity="secondary" rounded pt:root:style="font-size: 8px" />
                        </template>
                    </Column>

                    <Column field="amount" header="Amount" sortable class="text-right">
                        <template #body="slotProps">
                            <span class="font-mono font-black text-gray-800">{{ formatCurrency(slotProps.data.amount) }}</span>
                        </template>
                    </Column>

                    <Column header="Actions" class="text-right" style="width: 100px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1">
                                <Button icon="pi pi-pencil" text rounded  severity="primary" @click="startEdit(slotProps.data)" />
                                <Button icon="pi pi-trash" text rounded  severity="danger" @click="deleteExpense(slotProps.data.id)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </main>

        <Dialog v-model:visible="showDialog" modal :header="editingId ? 'Modify Expense Entry' : 'Log Operational Expense'" :style="{ width: '500px' }" class="p-fluid">
            <div class="grid grid-cols-12 gap-4 py-4">
                <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Date of Expense</label>
                    <DatePicker v-model="form.expense_date" dateFormat="yy-mm-dd"  />
                </div>
                <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Payment Mode</label>
                    <BaseSelect v-model="form.payment_mode" :options="paymentModes" placeholder="Select Mode"  />
                </div>
                <div class="col-span-12 flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Expense Category</label>
                    <BaseSelect v-model="form.expense_category_id" :options="categories" optionLabel="title" optionValue="id" placeholder="e.g., Fuel / Maintenance"  filter />
                </div>
                <div class="col-span-12 flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Machine / Asset (Optional)</label>
                    <BaseSelect v-model="form.machine_id" :options="machines" optionLabel="registration" optionValue="id" placeholder="Associate with Machine"  showClear filter />
                </div>
                <div class="col-span-12 flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Total Amount (₹)</label>
                    <BaseInputNumber v-model="form.amount" mode="currency" currency="INR" locale="en-IN"  />
                </div>
                <div class="col-span-12 flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Narration / Notes</label>
                    <BaseInput v-model="form.description" placeholder="Brief details about the expense..."  />
                </div>
            </div>

            <div class="flex gap-2 justify-end mt-4 pt-4 border-t">
                <Button label="Cancel" text severity="secondary" @click="showDialog = false" />
                <Button :label="editingId ? 'Update Entry' : 'Post Expense'" icon="pi pi-check" :loading="form.processing" @click="submitForm" />
            </div>
        </Dialog>
    </AppLayout>
</template>

