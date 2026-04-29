<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import {
    PlusIcon,
    TrashIcon,
    DocumentChartBarIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    PencilSquareIcon,
    EyeIcon
} from '@heroicons/vue/24/outline';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useJournalStore, JournalEntry, JournalLine } from './useJournalStore';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import DatePicker from 'primevue/datepicker';

const props = defineProps<{
    entries: JournalEntry[];
    ledgers: any[];
    voucherTypes: any[];
    partners: any[];
}>();

const store = useJournalStore();
onMounted(() => {
    store.setInitialData(props);
});

// ── Form State ─────────────────────────────────────────────
const journalForm = useForm({
    voucher_type: null as string | null,
    voucher_date: new Date(),
    posting_date: new Date(),
    narration: '',
    lines: [
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
        { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
    ] as JournalLine[]
});

const addLine = () => {
    journalForm.lines.push({ account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' });
};

const removeLine = (index: number) => {
    if (journalForm.lines.length > 2) {
        journalForm.lines.splice(index, 1);
    }
};

const totalDebit = computed(() => journalForm.lines.reduce((sum, line) => sum + (line.debit_amount || 0), 0));
const totalCredit = computed(() => journalForm.lines.reduce((sum, line) => sum + (line.credit_amount || 0), 0));
const isBalanced = computed(() => totalDebit.value > 0 && Math.abs(totalDebit.value - totalCredit.value) < 0.0001);

const ledgerOptions = computed(() => store.ledgers.map(l => ({ label: `${l.code} - ${l.title}`, value: l.id })));
const voucherOptions = computed(() => store.voucherTypes.map(v => ({ label: v.journal_name, value: v.short_code })));
const partnerOptions = computed(() => store.partners.map(p => ({ label: p.legal_name, value: p.id })));

const submitForm = async () => {
    if (totalDebit.value === 0) {
        Swal.fire({ icon: 'error', title: 'Empty Journal', text: 'You must enter amounts in the journal.' });
        return;
    }
    if (!isBalanced.value) {
        Swal.fire({ icon: 'error', title: 'Unbalanced Journal', text: 'Total Debit must equal Total Credit.' });
        return;
    }

    try {
        const formData = {
            ...journalForm.data(),
            voucher_date: journalForm.voucher_date instanceof Date ? journalForm.voucher_date.toISOString().slice(0, 10) : journalForm.voucher_date,
            posting_date: journalForm.posting_date instanceof Date ? journalForm.posting_date.toISOString().slice(0, 10) : journalForm.posting_date,
        };
        const res = await axios.post(route('journalentries.store'), formData);
        Swal.fire({ icon: 'success', title: 'Created', text: res.data.message });
        store.addEntry(res.data.entry);
        journalForm.reset();
        // Reset lines
        journalForm.lines = [
            { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' },
            { account_id: null, debit_amount: 0, credit_amount: 0, partner_id: null, line_narration: '' }
        ];
    } catch (err: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'Validation Failed' });
    }
};

const deleteEntry = (id: number) => {
    Swal.fire({
        title: 'Delete Entry?',
        text: "Are you sure you want to delete this journal entry?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            store.removeEntry(id);
        }
    });
};
</script>

<template>
    <AppLayout title="Journal Entries">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 bg-gray-50/50 dark:bg-slate-950 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <!-- HEADER FORM CARD -->
                <div class="bg-white dark:bg-slate-900 shadow-xl rounded-2xl p-8 border border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3 mb-8">
                        <div class="bg-indigo-600 p-2 rounded-lg shadow-lg">
                            <DocumentChartBarIcon class="w-6 h-6 text-white" />
                        </div>
                        <h2 class="text-xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight uppercase">New Double-Entry Journal</h2>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Voucher Type *</label>
                                <BaseSelect v-model="journalForm.voucher_type" :options="voucherOptions" optionLabel="label" optionValue="value" placeholder="Choose Type..." class="w-full" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Voucher Date</label>
                                <DatePicker v-model="journalForm.voucher_date" class="w-full" dateFormat="yy-mm-dd" showIcon />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Posting Date</label>
                                <DatePicker v-model="journalForm.posting_date" class="w-full" dateFormat="yy-mm-dd" showIcon />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Reference #</label>
                                <BaseInput placeholder="Auto-gen or Manual" class="w-full" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Main Narration</label>
                            <Textarea v-model="journalForm.narration" placeholder="Describe the transaction..." rows="2" class="w-full rounded-xl" />
                        </div>

                        <!-- JOURNAL LINES TABLE -->
                        <div class="mt-6 border border-gray-100 dark:border-slate-800 rounded-xl overflow-hidden">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 dark:bg-slate-950 border-b border-gray-100 dark:border-slate-800">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Account / Ledger</th>
                                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Patron (Optional)</th>
                                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300 w-32">Debit (₹)</th>
                                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300 w-32">Credit (₹)</th>
                                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Line Note</th>
                                        <th class="px-4 py-3 text-center">
                                            <Button icon="pi pi-plus" severity="primary"  rounded text @click="addLine" />
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-slate-800">
                                    <tr v-for="(line, idx) in journalForm.lines" :key="idx" class="hover:bg-gray-50/40 dark:hover:bg-slate-800/20 transition">
                                        <td class="p-2">
                                            <BaseSelect v-model="line.account_id" :options="ledgerOptions" optionLabel="label" optionValue="value" filter placeholder="Select Ledger..." class="w-full"  />
                                        </td>
                                        <td class="p-2">
                                            <BaseSelect v-model="line.partner_id" :options="partnerOptions" optionLabel="label" optionValue="value" filter placeholder="Select Patron..." class="w-full"  />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber v-model="line.debit_amount" :minFractionDigits="2" :disabled="line.credit_amount > 0" placeholder="0.00" class="w-full"  :inputClass="'text-right'" />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber v-model="line.credit_amount" :minFractionDigits="2" :disabled="line.debit_amount > 0" placeholder="0.00" class="w-full"  :inputClass="'text-right'" />
                                        </td>
                                        <td class="p-2">
                                            <BaseInput v-model="line.line_narration" placeholder="Notes..." class="w-full"  />
                                        </td>
                                        <td class="p-2 text-center">
                                            <Button icon="pi pi-trash" severity="danger"  rounded text @click="removeLine(idx)" :disabled="journalForm.lines.length <= 2" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- TOTALS & ACTION -->
                        <div class="mt-8 flex flex-col md:flex-row justify-between items-center gap-6">
                            <div class="flex gap-4">
                                <div class="bg-gray-100 dark:bg-slate-800 px-6 py-4 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-inner">
                                    <span class="text-xs text-gray-500 uppercase font-bold tracking-widest block mb-1">Total Debit</span>
                                    <span class="text-2xl font-black text-indigo-700 dark:text-indigo-400">₹ {{ totalDebit.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="bg-gray-100 dark:bg-slate-800 px-6 py-4 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-inner">
                                    <span class="text-xs text-gray-500 uppercase font-bold tracking-widest block mb-1">Total Credit</span>
                                    <span class="text-2xl font-black text-indigo-700 dark:text-indigo-400">₹ {{ totalCredit.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div v-if="totalDebit > 0 || totalCredit > 0" class="flex items-center px-4 py-2 rounded-full text-sm font-bold shadow-sm"
                                    :class="isBalanced ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                    <CheckCircleIcon v-if="isBalanced" class="w-5 h-5 mr-1" />
                                    <ExclamationTriangleIcon v-else class="w-5 h-5 mr-1" />
                                    {{ isBalanced ? 'JOURNAL BALANCED' : 'OUT OF BALANCE' }}
                                </div>
                                <Button size="large" severity="primary" rounded class="px-10 h-14 text-lg font-bold shadow-lg uppercase tracking-widest" 
                                    :disabled="!isBalanced || journalForm.processing" @click="submitForm" :label="journalForm.processing ? 'POSTING...' : 'POST JOURNAL ENTRY'" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LIST CARD -->
                <div class="bg-white dark:bg-slate-900 shadow-xl rounded-2xl p-8 border border-slate-100 dark:border-slate-800">
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight uppercase">Recent Transactions</h3>
                            <p class="text-sm text-gray-500 italic mt-1 font-medium">History of posted journals for this entity.</p>
                        </div>
                        <Button icon="pi pi-refresh" severity="secondary" text rounded label="Refresh List" />
                    </div>

                    <DataTable
                        :value="store.entries"
                        stripedRows
                        paginator :rows="15"
                        class="modern-table"
                    >
                        <Column field="posting_date" header="Date" style="width: 120px" sortable />
                        <Column field="voucher_number" header="Voucher #" sortable />
                        <Column header="Narration">
                            <template #body="slotProps">
                                <div class="text-xs italic text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                    {{ slotProps.data.narration || 'No description' }}
                                </div>
                            </template>
                        </Column>
                        <Column header="Total" align="right">
                            <template #body="slotProps">
                                <span class="font-bold text-indigo-700 dark:text-indigo-400">
                                    ₹ {{ parseFloat(slotProps.data.total_debit.toString()).toLocaleString() }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Status">
                            <template #body="slotProps">
                                <Tag :severity="slotProps.data.is_status === 'POSTED' ? 'success' : 'warn'" rounded class="text-[10px] font-black uppercase tracking-widest">
                                    {{ slotProps.data.is_status }}
                                </Tag>
                            </template>
                        </Column>
                        <Column header="Actions" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-eye" severity="secondary" text rounded  />
                                    <Button icon="pi pi-trash" severity="danger" text rounded  @click="deleteEntry(slotProps.data.id)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
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


