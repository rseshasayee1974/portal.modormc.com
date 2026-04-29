<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, reactive, watch } from 'vue';
import Swal from 'sweetalert2';
import {
    BanknotesIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon,
    ArrowUpRightIcon, ArrowDownLeftIcon, ArrowRightEndOnRectangleIcon
} from '@heroicons/vue/24/outline';
import PaymentEditForm from './PaymentEditForm.vue';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const page = usePage();

const props = defineProps<{
    payments: any[];
    ledgers: { id: number; title: string }[];
    patrons: { id: number; legal_name: string }[];
}>();

const searchQuery = ref('');
const expandedRows = ref<any[]>([]);

const filteredPayments = computed(() => {
    if (!searchQuery.value) return props.payments;
    const q = searchQuery.value.toLowerCase();
    return props.payments.filter((txn: any) =>
        (txn.reference && txn.reference.toLowerCase().includes(q)) ||
        (txn.patron?.legal_name && txn.patron.legal_name.toLowerCase().includes(q)) ||
        (txn.ledger?.title && txn.ledger.title.toLowerCase().includes(q))
    );
});

// Creation Form State
const createForm = useForm({
    transaction_date: new Date().toLocaleDateString('en-CA'), // YYYY-MM-DD
    ledger_id: null as number | null,
    patron_id: null as number | null,
    partner_type: 'Master',
    amount: 0,
    excess_amount: 0,
    use_excess_amount: false,
    transaction_type: 'payment',
    transaction_mode: 'Cash',
    reconcile_opening_balance: false,
    batch_deposit: false,
    description: '',
    reference: '',
    status: 'completed'
});

const ledgerOptions = computed(() => props.ledgers.map(l => ({ label: l.title, value: l.id })));
const patronOptions = computed(() => props.patrons.map(p => ({ label: p.legal_name, value: p.id })));

const handleCreate = () => {
    createForm.post(route('payments.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const deleteTransaction = (id: number) => {
    Swal.fire({
        title: 'Void this record?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444'
    }).then(res => {
        if (res.isConfirmed) {
            createForm.delete(route('payments.destroy', id), {
                onSuccess: () => {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Transaction voided', showConfirmButton: false, timer: 3000 });
                }
            });
        }
    });
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '—';
    const pureDate = dateStr.split('T')[0];
    const parts = pureDate.split('-');
    if (parts.length !== 3) return pureDate;
    const [y, m, d] = parts;
    return `${d}-${m}-${y}`;
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 3000 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Journal Records">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-10 bg-[#EDF2F7] dark:bg-slate-900 min-h-screen">
            <div class="max-w-[1200px] mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Creation Desk -->
                <div class="relative bg-[#E3EBF6] dark:bg-slate-800 rounded-[0.5rem] border border-[#CBD5E0] dark:border-slate-700 shadow-sm overflow-hidden mt-6">
                    <div class="p-8 lg:p-12">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                            <!-- Row 1 -->
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Type Payment/Receipt *</label>
                                <BaseSelect v-model="createForm.transaction_type" :options="[{label:'Payment',value:'payment'},{label:'Receipt',value:'receipt'}]" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Partner Type *</label>
                                <BaseSelect v-model="createForm.partner_type" :options="[{label:'Master',value:'Master'},{label:'Other',value:'Other'}]" optionLabel="label" optionValue="value" class="w-full" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Partner *</label>
                                <BaseSelect v-model="createForm.patron_id" :options="patronOptions" optionLabel="label" optionValue="value" filter placeholder="None" class="w-full" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Receipt/Payment Amount *</label>
                                <BaseInputNumber v-model="createForm.amount" class="w-full" :minFractionDigits="2" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Journal Type *</label>
                                <BaseSelect v-model="createForm.ledger_id" :options="ledgerOptions" optionLabel="label" optionValue="value" filter placeholder="Select Journal..." class="w-full" />
                            </div>

                            <!-- Row 2 -->
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Date *</label>
                                <DatePicker v-model="createForm.transaction_date" dateFormat="yy-mm-dd" class="w-full" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Transaction Mode*</label>
                                <BaseSelect v-model="createForm.transaction_mode" :options="[{label:'Cash',value:'Cash'},{label:'Bank',value:'Bank'},{label:'Cheque',value:'Cheque'}]" optionLabel="label" optionValue="value" filter class="w-full" />
                            </div>

                            <div class="md:col-span-2 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Notes</label>
                                <Textarea v-model="createForm.description" rows="1" class="w-full" />
                            </div>

                            <!-- Submit Box -->
                            <div class="flex items-end">
                                <Button @click="handleCreate" :loading="createForm.processing" class="h-[48px] px-8 bg-white dark:bg-slate-900 border border-[#CBD5E0] dark:border-slate-700 shadow-sm rounded-lg hover:shadow-md transition-all group flex items-center justify-center w-full">
                                    <span class="text-[#2D3748] dark:text-gray-200 font-black uppercase text-[12px] tracking-widest mr-3">Payments</span>
                                    <ArrowRightEndOnRectangleIcon class="w-5 h-5 text-indigo-500 transition-transform group-hover:translate-x-1" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manifest Trail -->
                <div class="bg-white dark:bg-slate-800 shadow-2xl rounded-[1rem] border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700 flex flex-col md:flex-row justify-between items-center bg-[#F8FAFC] dark:bg-slate-900/50">
                        <div class="flex items-center gap-6 mb-4 md:mb-0">
                             <div class="flex flex-col">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] leading-none mb-1">Manifest Audit</span>
                                <span class="text-xs font-black text-slate-700 dark:text-gray-200 uppercase leading-none">Journal Trail</span>
                             </div>
                             <div class="h-8 w-px bg-slate-200 dark:bg-slate-700" />
                             <div class="text-[10px] text-indigo-500 font-black uppercase bg-indigo-50 dark:bg-indigo-900/20 px-4 py-1.5 rounded-full border border-indigo-100 dark:border-indigo-900/40">
                                Entries: {{ filteredPayments.length }}
                             </div>
                        </div>

                        <div class="relative w-full md:w-64">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <MagnifyingGlassIcon class="h-4 w-4 text-slate-300" />
                            </span>
                            <BaseInput v-model="searchQuery" placeholder="Quick Search..." class="w-full pl-9 rounded-full h-10 shadow-inner" />
                        </div>
                    </div>
                    
                    <DataTable 
                        :value="filteredPayments" 
                        v-model:expandedRows="expandedRows"
                        dataKey="id"
                        stripedRows
                        class="manifest-table p-datatable-sm"
                        paginator :rows="12" :rowsPerPageOptions="[12, 24, 50]"
                    >
                        <Column expander style="width: 3rem" />
                        <Column header="Date">
                            <template #body="slotProps">
                                <div class="text-[10px] text-slate-500 font-bold">
                                    {{ formatDate(slotProps.data.transaction_date || slotProps.data.created_at) }}
                                </div>
                            </template>
                        </Column>
                        <Column header="Type" style="width: 130px">
                            <template #body="slotProps">
                                <Tag 
                                    :severity="slotProps.data.transaction_type === 'payment' ? 'warn' : 'success'"
                                    rounded
                                    class="font-black text-[10px]"
                                >
                                    <div class="flex items-center gap-1">
                                        <ArrowUpRightIcon v-if="slotProps.data.transaction_type === 'payment'" class="w-3 h-3" />
                                        <ArrowDownLeftIcon v-else class="w-3 h-3" />
                                        <span>{{ slotProps.data.transaction_type === 'payment' ? 'PAYMENT' : 'RECEIPT' }}</span>
                                    </div>
                                </Tag>
                            </template>
                        </Column>
                        <Column header="Ledger Detail">
                            <template #body="slotProps">
                                <div>
                                    <div class="font-black text-slate-700 dark:text-gray-200 uppercase text-xs tracking-tighter">{{ slotProps.data.ledger?.title || '—' }}</div>
                                    <div class="text-[10px] text-indigo-500 font-bold">{{ slotProps.data.patron?.legal_name || 'General Ledger' }}</div>
                                </div>
                            </template>
                        </Column>
                        <Column header="Amount" textAlign="right">
                            <template #body="slotProps">
                                <div :class="`font-mono font-black text-base ${slotProps.data.transaction_type === 'receipt' ? 'text-green-600' : 'text-slate-800 dark:text-gray-100'}`">
                                    ₹{{ Number(slotProps.data.amount).toLocaleString('en-IN') }}
                                </div>
                            </template>
                        </Column>
                        <Column header="Actions" textAlign="right">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <Button 
                                        icon="pi pi-pencil" 
                                        severity="secondary" 
                                        text 
                                        rounded 
                                        
                                        @click="expandedRows = expandedRows.includes(slotProps.data) ? [] : [slotProps.data]"
                                    >
                                        <template #icon>
                                            <PencilSquareIcon class="w-4 h-4 text-emerald-600" />
                                        </template>
                                    </Button>
                                    <Button 
                                        icon="pi pi-trash" 
                                        severity="danger" 
                                        text 
                                        rounded 
                                        
                                        @click="deleteTransaction(slotProps.data.id)"
                                    >
                                        <template #icon>
                                            <TrashIcon class="w-4 h-4" />
                                        </template>
                                    </Button>
                                </div>
                            </template>
                        </Column>
                        <template #expansion="slotProps">
                            <div class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border dark:border-slate-700">
                                <PaymentEditForm 
                                    :payment="slotProps.data"
                                    :ledgerOptions="ledgerOptions"
                                    :patronOptions="patronOptions"
                                    @success="expandedRows = []"
                                    @close="expandedRows = []"
                                />
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
    @apply bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-700 text-[#718096] font-black uppercase text-[9px] tracking-widest py-6 px-4 !border-none;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply py-6 px-4 !border-none !bg-transparent;
}
</style>


