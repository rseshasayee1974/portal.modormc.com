<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import {
    CurrencyRupeeIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, PlusIcon, LockClosedIcon, DocumentCheckIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import DatePicker from 'primevue/datepicker';
import Divider from 'primevue/divider';

const page = usePage();

interface PcItem {
    id?: number;
    expense_id: number | null;
    amount: number;
    debit: number;
    credit: number;
    date: Date;
    description: string;
    remarks: string;
}

const props = defineProps<{
    pettyCashes: any[];
    expenses: any[];
    expenseTypes: { id: number; name: string }[];
    patrons: { id: number; name: string }[];
    users: { id: number; name: string }[];
}>();

const searchQuery   = ref('');
const expandedRows  = ref([]);

const filtered = computed(() => {
    if (!searchQuery.value) return props.pettyCashes;
    const q = searchQuery.value.toLowerCase();
    return props.pettyCashes.filter((pc: any) => pc.ref_no?.toLowerCase().includes(q));
});

const expenseOptions = computed(() => props.expenses.map(e => ({
    label: `${e.ref_no} — ₹${e.amount}`, value: e.id
})));
const userOptions = computed(() => props.users.map(u => ({ label: u.name, value: u.id })));

const makeBlankItem = (): PcItem => ({
    expense_id: null, amount: 0, debit: 0, credit: 0,
    date: new Date(),
    description: '', remarks: ''
});

const createForm = useForm({
    date:            new Date(),
    opening_balance: 0 as number,
    paid_by:         null as number | null,
    paid_to:         null as number | null,
    request_amount:  null as number | null,
    items:           [makeBlankItem()] as PcItem[],
});

const editForm = useForm({
    id:              null as number | null,
    date:            new Date(),
    opening_balance: 0 as number,
    paid_by:         null as number | null,
    paid_to:         null as number | null,
    request_amount:  null as number | null,
    items:           [] as PcItem[],
});

const computedBalance = (form: any) =>
    (form.opening_balance - form.items.reduce((s: number, i: PcItem) => s + (i.debit || 0), 0) + form.items.reduce((s: number, i: PcItem) => s + (i.credit || 0), 0)).toFixed(2);

const addItem = (form: any) => form.items.push(makeBlankItem());
const removeItem = (form: any, idx: number) => form.items.splice(idx, 1);

const submitCreate = () => {
    const data = {
        ...createForm.data(),
        date: createForm.date.toISOString().slice(0, 19).replace('T', ' '),
        items: createForm.items.map(i => ({
            ...i,
            date: i.date.toISOString().slice(0, 19).replace('T', ' ')
        }))
    };

    createForm.transform(() => data).post(route('pettycash.store'), {
        onSuccess: () => { createForm.reset(); createForm.items = [makeBlankItem()]; }
    });
};

const startEdit = (row: any) => {
    editForm.id              = row.id;
    editForm.date            = new Date(row.date);
    editForm.opening_balance = parseFloat(row.opening_balance);
    editForm.paid_by         = row.paid_by;
    editForm.paid_to         = row.paid_to;
    editForm.request_amount  = row.request_amount;
    editForm.items           = row.items.map((i: any) => ({
        id: i.id, expense_id: i.expense_id, amount: parseFloat(i.amount),
        debit: parseFloat(i.debit), credit: parseFloat(i.credit),
        date: new Date(i.date), description: i.description || '', remarks: i.remarks || ''
    }));
};

const cancelEdit = () => {
    editForm.reset();
    expandedRows.value = [];
};

const submitEdit = () => {
    if (!editForm.id) return;
    const data = {
        ...editForm.data(),
        date: editForm.date.toISOString().slice(0, 19).replace('T', ' '),
        items: editForm.items.map(i => ({
            ...i,
            date: i.date.toISOString().slice(0, 19).replace('T', ' ')
        }))
    };

    editForm.transform(() => data).put(route('pettycash.update', editForm.id), {
        onSuccess: () => cancelEdit()
    });
};

const closePc = (id: number) => {
    Swal.fire({ 
        title: 'Close this register?', 
        text: 'No edits allowed after closing.', 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonText: 'Yes, Close It',
        confirmButtonColor: '#10b981'
    })
    .then((r) => {
        if (r.isConfirmed) {
            editForm.post(route('pettycash.close', id), { 
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Closed', timer: 2000 }) 
            });
        }
    });
};

const deletePc = (id: number) => {
    Swal.fire({ 
        title: 'Void this register?', 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#ef4444', 
        confirmButtonText: 'Yes, Void' 
    })
    .then((r) => {
        if (r.isConfirmed) {
            editForm.delete(route('pettycash.destroy', id), { 
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Voided', timer: 2000 }) 
            });
        }
    });
};

const onRowExpand = (event: any) => {
    startEdit(event.data);
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 3000 });
    if (flash?.error) Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: flash.error, showConfirmButton: false, timer: 4000 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Petty Cash">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-12 bg-slate-50/50 dark:bg-slate-950 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Create Petty Cash -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-xl border-t-4 border-teal-500 overflow-hidden">
                    <div class="p-6 bg-teal-50/50 dark:bg-teal-900/10 flex items-center gap-3 border-b dark:border-slate-800">
                        <div class="p-2 bg-teal-500 rounded-xl shadow-lg">
                            <CurrencyRupeeIcon class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-gray-800 dark:text-gray-100 uppercase tracking-tight">New Petty Cash Register</h2>
                            <p class="text-[10px] text-teal-400 font-black uppercase tracking-widest">Closing balance auto-calculated from items</p>
                        </div>
                    </div>

                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-24 gap-6 mb-8">
                            <div class="md:col-span-6 flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Register Date *</label>
                                <DatePicker v-model="createForm.date" class="w-full" showTime hourFormat="24" showIcon />
                                <small v-if="createForm.errors.date" class="p-error">{{ createForm.errors.date }}</small>
                            </div>
                            <div class="md:col-span-5 flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Opening Balance (₹) *</label>
                                <BaseInputNumber v-model="createForm.opening_balance" :minFractionDigits="2" class="w-full" />
                            </div>
                            <div class="md:col-span-4 flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Request Amount</label>
                                <BaseInputNumber v-model="createForm.request_amount" :minFractionDigits="2" class="w-full" />
                            </div>
                            <div class="md:col-span-4 flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Paid By</label>
                                <BaseSelect v-model="createForm.paid_by" :options="userOptions" optionLabel="label" optionValue="value" filter placeholder="Select user" class="w-full" />
                            </div>
                            <div class="md:col-span-5 flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Paid To</label>
                                <BaseSelect v-model="createForm.paid_to" :options="userOptions" optionLabel="label" optionValue="value" filter placeholder="Select user" class="w-full" />
                            </div>
                        </div>

                        <Divider align="center" class="my-6">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Expense Lines</span>
                        </Divider>

                        <div class="space-y-4">
                            <div v-for="(item, idx) in createForm.items" :key="idx"
                                class="p-6 bg-slate-50 dark:bg-slate-800/20 rounded-2xl border border-slate-100 dark:border-slate-800 flex items-end gap-3 flex-wrap">
                                <div class="flex flex-col gap-1 flex-1 min-w-48">
                                    <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Expense Ref</label>
                                    <BaseSelect v-model="item.expense_id" :options="expenseOptions" optionLabel="label" optionValue="value" filter placeholder="Ref..." class="w-full"  />
                                </div>
                                <div class="flex flex-col gap-1 w-28">
                                    <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Amount</label>
                                    <BaseInputNumber v-model="item.amount" :minFractionDigits="2" class="w-full"  />
                                </div>
                                <div class="flex flex-col gap-1 w-28">
                                    <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Debit</label>
                                    <BaseInputNumber v-model="item.debit" :minFractionDigits="2" class="w-full"  />
                                </div>
                                <div class="flex flex-col gap-1 w-28">
                                    <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Credit</label>
                                    <BaseInputNumber v-model="item.credit" :minFractionDigits="2" class="w-full"  />
                                </div>
                                <div class="flex flex-col gap-1 w-48">
                                    <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Date</label>
                                    <DatePicker v-model="item.date" class="w-full" showTime hourFormat="24"  />
                                </div>
                                <div class="flex flex-col gap-1 flex-1">
                                    <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Description</label>
                                    <BaseInput v-model="item.description" placeholder="Details..." class="w-full"  />
                                </div>
                                <Button icon="pi pi-trash" severity="danger" text rounded @click="removeItem(createForm, idx)"  />
                            </div>
                        </div>

                        <Button icon="pi pi-plus" label="Add Expense Line" severity="info" text class="mt-4 w-full border-2 border-dashed border-info-200 rounded-xl font-bold uppercase tracking-widest text-xs h-12" @click="addItem(createForm)" />

                        <!-- Live Balance Preview -->
                        <div class="mt-8 p-6 bg-teal-50 dark:bg-teal-900/10 rounded-3xl border border-teal-100 dark:border-teal-900 flex flex-col md:flex-row justify-between items-center gap-6">
                            <div>
                                <p class="text-[10px] font-black text-teal-400 uppercase tracking-widest">Projected Closing Balance</p>
                                <p class="text-3xl font-black text-teal-700 dark:text-teal-400 tracking-tighter mt-1">₹ {{ computedBalance(createForm) }}</p>
                            </div>
                            <Button
                                severity="primary"
                                size="large"
                                class="px-12 rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-teal-500/20 h-16"
                                :loading="createForm.processing"
                                @click="submitCreate"
                            >
                                <template #icon><DocumentCheckIcon class="w-5 h-5 mr-2 stroke-[3px]" /></template>
                                Open Register
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Petty Cash Table  -->
                <div class="bg-white dark:bg-slate-900 shadow-xl rounded-3xl p-10 border border-slate-100 dark:border-slate-800">
                    <div class="flex justify-between items-center mb-8 gap-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-teal-50 dark:bg-slate-800 rounded-2xl">
                                <CurrencyRupeeIcon class="w-8 h-8 text-teal-600" />
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-gray-800 dark:text-gray-100 tracking-tighter uppercase leading-none">Cash Registers</h3>
                                <p class="text-[10px] text-gray-400 font-bold mt-1 uppercase tracking-widest">Click icon to expand and edit</p>
                            </div>
                        </div>
                        <span class="relative w-80">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400" />
                            </span>
                            <BaseInput v-model="searchQuery" placeholder="Search by reference..." class="w-full pl-10 rounded-full" />
                        </span>
                    </div>

                    <DataTable
                        v-model:expandedRows="expandedRows"
                        :value="filtered"
                        stripedRows
                        paginator :rows="10"
                        dataKey="id"
                        @rowExpand="onRowExpand"
                        class="modern-table"
                    >
                        <Column expander style="width: 3rem" />
                        <Column field="ref_no" header="Reference">
                            <template #body="slotProps">
                                <span class="font-black text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-900/30 px-3 py-1 rounded-lg text-sm">
                                    {{ slotProps.data.ref_no }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Date">
                            <template #body="slotProps">
                                <span class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ new Date(slotProps.data.date).toLocaleDateString('en-IN') }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Opening" align="right">
                            <template #body="slotProps">
                                <span class="font-mono font-black text-green-600 dark:text-green-400">
                                    ₹ {{ Number(slotProps.data.opening_balance).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Closing" align="right">
                            <template #body="slotProps">
                                <span class="font-mono font-black text-blue-600 dark:text-blue-400">
                                    ₹ {{ Number(slotProps.data.closing_balance).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Status" align="center">
                            <template #body="slotProps">
                                <Tag v-if="slotProps.data.closed_status" severity="warn" rounded class="text-[10px] font-black uppercase tracking-widest">🔒 Closed</Tag>
                                <Tag v-else-if="slotProps.data.journal_status" severity="info" rounded class="text-[10px] font-black uppercase tracking-widest">📒 Journalized</Tag>
                                <Tag v-else severity="success" rounded class="text-[10px] font-black uppercase tracking-widest">Open</Tag>
                            </template>
                        </Column>
                        <Column header="Actions" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <Button v-if="!slotProps.data.closed_status" icon="pi pi-lock" severity="warn" text rounded @click="closePc(slotProps.data.id)"  />
                                    <Button v-if="!slotProps.data.journal_status" icon="pi pi-trash" severity="danger" text rounded @click="deletePc(slotProps.data.id)"  />
                                </div>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <div class="py-12 p-8 bg-slate-50 dark:bg-slate-950/50 rounded-2xl m-4 border border-slate-100 dark:border-slate-800">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Opening Balance</label>
                                        <BaseInputNumber v-model="editForm.opening_balance" :minFractionDigits="2" class="w-full"  :disabled="slotProps.data.closed_status" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Live Balance</label>
                                        <div class="text-2xl font-black text-blue-600 dark:text-blue-400">₹ {{ computedBalance(editForm) }}</div>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Paid By</label>
                                        <BaseSelect v-model="editForm.paid_by" :options="userOptions" optionLabel="label" optionValue="value" filter placeholder="..." class="w-full"  :disabled="slotProps.data.closed_status" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Paid To</label>
                                        <BaseSelect v-model="editForm.paid_to" :options="userOptions" optionLabel="label" optionValue="value" filter placeholder="..." class="w-full"  :disabled="slotProps.data.closed_status" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div v-for="(item, idx) in editForm.items" :key="idx"
                                        class="p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-end gap-3 flex-wrap shadow-sm">
                                        <div class="flex flex-col gap-1 flex-1 min-w-48">
                                            <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Expense Ref</label>
                                            <BaseSelect v-model="item.expense_id" :options="expenseOptions" optionLabel="label" optionValue="value" filter placeholder="Ref..." class="w-full"  :disabled="slotProps.data.closed_status" />
                                        </div>
                                        <div class="flex flex-col gap-1 w-28">
                                            <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Amount</label>
                                            <BaseInputNumber v-model="item.amount" :minFractionDigits="2" class="w-full"  :disabled="slotProps.data.closed_status" />
                                        </div>
                                        <div class="flex flex-col gap-1 w-28">
                                            <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Debit</label>
                                            <BaseInputNumber v-model="item.debit" :minFractionDigits="2" class="w-full"  :disabled="slotProps.data.closed_status" />
                                        </div>
                                        <div class="flex flex-col gap-1 w-28">
                                            <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Credit</label>
                                            <BaseInputNumber v-model="item.credit" :minFractionDigits="2" class="w-full"  :disabled="slotProps.data.closed_status" />
                                        </div>
                                        <div class="flex flex-col gap-1 flex-1">
                                            <label class="text-[9px] font-black tracking-widest text-gray-400 uppercase">Description</label>
                                            <BaseInput v-model="item.description" placeholder="Details..." class="w-full"  :disabled="slotProps.data.closed_status" />
                                        </div>
                                        <Button v-if="!slotProps.data.closed_status" icon="pi pi-trash" severity="danger" text rounded @click="removeItem(editForm, idx)"  />
                                    </div>
                                    <Button v-if="!slotProps.data.closed_status" icon="pi pi-plus" label="Add Line" severity="info" text class="w-full h-12 border-2 border-dashed border-info-200 rounded-xl" @click="addItem(editForm)" />
                                </div>

                                <div v-if="!slotProps.data.closed_status" class="flex justify-end gap-3 mt-8 pt-6 border-t dark:border-slate-800">
                                    <Button label="Cancel" severity="secondary" text @click="cancelEdit" class="px-8 font-bold uppercase tracking-widest text-xs" />
                                    <Button label="Save Changes" severity="primary" class="px-10 font-black uppercase tracking-widest text-xs h-12 rounded-xl" :loading="editForm.processing" @click="submitEdit" />
                                </div>
                            </div>
                        </template>

                        <template #empty>
                            <div class="py-20 flex flex-col items-center opacity-40">
                                <CurrencyRupeeIcon class="w-16 h-16 mb-4" />
                                <span class="font-bold">No Petty Cash Registers Found</span>
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
    @apply bg-teal-50/50 dark:bg-slate-950 text-teal-600 font-black uppercase text-[11px] tracking-widest py-5 dark:border-slate-800;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply dark:border-slate-800;
}
</style>


