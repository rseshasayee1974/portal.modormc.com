<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted } from 'vue';
import axios from 'axios';
import { 
    BanknotesIcon, 
    ArrowRightEndOnRectangleIcon,
    PlusCircleIcon,
    ChevronLeftIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const props = defineProps<{
    // Props removed for dynamic fetching
}>();

const createForm = useForm({
 transaction_date : new Date().toLocaleDateString('en-CA', {
    timeZone: 'Asia/Kolkata'
}),   ledger_id: null as number | null,
    patron_id: null as number | null,
    partner_type: 'Customer', // Default to Customer
    amount: 0,
    excess_amount: 0,
    use_excess_amount: false,
    transaction_type: 'payment',
    transaction_mode: 'Cash',
    reconcile_opening_balance: false,
    batch_deposit: false,
    description: '',
    reference: '',
    status: 'paid',
    allocations: [] as { invoice_id: number; amount: number; invoice_number: string; balance: number }[]
});

const patrons = ref<{ label: string; value: any }[]>([]);
const ledgers = ref<{ label: string; value: any }[]>([]);
const outstandingInvoices = ref<any[]>([]);

const ledgerOptions = computed(() => ledgers.value);
const patronOptions = computed(() => patrons.value);

const fetchPatrons = async (type: string) => {
    try {
        const response = await axios.get(route('patrons.dropdown'), { params: { type } });
        patrons.value = response.data;
    } catch (error) {
        console.error('Failed to fetch patrons:', error);
    }
};

const fetchLedgers = async (type: string) => {
    try {
        const response = await axios.get(route('ledgers.dropdown'), { params: { type } });
        ledgers.value = response.data;
    } catch (error) {
        console.error('Failed to fetch ledgers:', error);
    }
};

const fetchOutstandingInvoices = async (patronId: number) => {
    if (!patronId) {
        outstandingInvoices.value = [];
        return;
    }
    try {
        const response = await axios.get(route('invoices.outstanding'), { 
            params: { 
                partner_id: patronId, 
                type: createForm.transaction_type === 'receipt' ? 'sales' : 'bill'
            } 
        });
        outstandingInvoices.value = response.data;
    } catch (error) {
        console.error('Failed to fetch invoices:', error);
    }
};

onMounted(() => {
    fetchLedgers(createForm.transaction_type);
    fetchPatrons(createForm.partner_type);
});

watch(() => createForm.partner_type, (newType) => {
    createForm.patron_id = null;
    fetchPatrons(newType);
});

const patronAdvanceBalance = ref(0);

const fetchPatronAdvanceBalance = async (patronId: number) => {
    try {
        const response = await axios.get(route('payments.patron-advance-balance'), { params: { patron_id: patronId } });
        patronAdvanceBalance.value = response.data.available_excess_amount;
    } catch (error) {
        console.error('Failed to fetch patron advance balance:', error);
        patronAdvanceBalance.value = 0;
    }
};

watch(() => createForm.patron_id, (newId) => {
    if (newId) {
        fetchOutstandingInvoices(newId);
        fetchPatronAdvanceBalance(newId);
    } else {
        outstandingInvoices.value = [];
        patronAdvanceBalance.value = 0;
    }
    createForm.allocations = [];
    createForm.use_excess_amount = false;
});

watch(() => createForm.transaction_type, (newType) => {
    createForm.ledger_id = null;
    fetchLedgers(newType);
    if (createForm.patron_id) fetchOutstandingInvoices(createForm.patron_id);
});

const fetchNextReferenceNumber = async () => {
    if (!createForm.ledger_id || !createForm.transaction_type) {
        createForm.reference = '';
        return;
    }
    try {
        const response = await axios.get(route('payments.next-reference'), {
            params: {
                ledger_id: createForm.ledger_id,
                transaction_type: createForm.transaction_type,
                transaction_date: createForm.transaction_date,
            }
        });
        createForm.reference = response.data.reference;
    } catch (error) {
        console.error('Failed to fetch next reference number:', error);
    }
};

watch(() => [createForm.ledger_id, createForm.transaction_type, createForm.transaction_date], () => {
    fetchNextReferenceNumber();
});

const totalAllocated = computed(() => {
    return createForm.allocations.reduce((sum, a) => sum + a.amount, 0);
});

const totalAvailableFunding = computed(() => {
    let funding = createForm.amount || 0;
    if (createForm.use_excess_amount) {
        funding += patronAdvanceBalance.value || 0;
    }
    return funding;
});

watch(() => [createForm.amount, totalAllocated.value, createForm.use_excess_amount], () => {
    if (createForm.use_excess_amount) {
        // If they checked use_excess_amount, any fresh cash exceeding the allocation is still saved as new excess_amount
        if (createForm.amount > totalAllocated.value) {
            createForm.excess_amount = createForm.amount - totalAllocated.value;
        } else {
            createForm.excess_amount = 0;
        }
    } else if (createForm.amount > totalAllocated.value) {
        createForm.excess_amount = createForm.amount - totalAllocated.value;
    } else {
        createForm.excess_amount = 0;
    }
});

const autoAllocate = () => {
    let remaining = totalAvailableFunding.value;
    createForm.allocations = [];
    
    for (const inv of outstandingInvoices.value) {
        if (remaining <= 0) break;
        const toAllocate = Math.min(remaining, parseFloat(inv.balance_amount || inv.total_amount));
        createForm.allocations.push({
            invoice_id: inv.id,
            invoice_number: inv.full_number,
            amount: toAllocate,
            balance: parseFloat(inv.balance_amount || inv.total_amount)
        });
        remaining -= toAllocate;
    }
};

const handleCreate = () => {
    createForm.post(route('payments.store'), {
        onSuccess: () => {
            createForm.reset();
            outstandingInvoices.value = [];
            window.location.reload();
        },
    });
};
</script>

<template>
    <div class="bg-[#E3EBF6] dark:bg-slate-800 rounded-xl border border-[#CBD5E0] dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-8 py-4 border-b border-[#CBD5E0]/50 dark:border-slate-700/50 flex items-center justify-between bg-[#F8FAFC] dark:bg-slate-900/50">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-500 p-2 rounded-lg text-white shadow-md">
                    <PlusCircleIcon class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-800 dark:text-gray-200 uppercase tracking-tight">Create Transaction</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Post new payment or receipt</p>
                </div>
            </div>
        </div>

        <div class="p-8 lg:p-10">
            <!-- General Backend Error Alert -->
            <div v-if="createForm.errors.error" class="mb-6 p-4 rounded-xl border border-red-200 bg-red-50 dark:bg-red-950/20 dark:border-red-800 flex items-start gap-3 shadow-sm">
                <div class="bg-red-500 p-1.5 rounded-lg text-white mt-0.5 shadow-md flex-shrink-0">
                    <svg class="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-black text-red-800 dark:text-red-200 uppercase tracking-wider">Transaction Failed</h4>
                    <p class="text-xs text-red-600 dark:text-red-400 font-bold mt-0.5">{{ createForm.errors.error }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-6">
                <BaseSelect 
                    v-model="createForm.transaction_type" 
                    label="Type"
                    required
                    :options="[{label:'Payment',value:'payment'},{label:'Receipt',value:'receipt'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    class="w-full" 
                    :error="createForm.errors.transaction_type"
                />
                
                <BaseSelect 
                    v-model="createForm.partner_type" 
                    label="Partner Type"
                    required
                    :options="[{label:'Customer',value:'Customer'},{label:'Vendor',value:'Vendor'},{label:'Employee',value:'Employee'},{label:'Transporter',value:'Transporter'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    class="w-full" 
                    :error="createForm.errors.partner_type"
                />

                <BaseSelect 
                    v-model="createForm.patron_id" 
                    label="Partner"
                    required
                    :options="patronOptions" 
                    optionLabel="label" 
                    optionValue="value" 
                    filter 
                    placeholder="None" 
                    class="w-full" 
                    :error="createForm.errors.patron_id"
                />

                <BaseInputNumber 
                    v-model="createForm.amount" 
                    label="Amount"
                    required
                    class="w-full" 
                    :minFractionDigits="2" 
                    :error="createForm.errors.amount"
                />

                <BaseSelect 
                    v-model="createForm.ledger_id" 
                    label="Journal"
                    required
                    :options="ledgerOptions" 
                    optionLabel="label" 
                    optionValue="value" 
                    filter 
                    placeholder="Select Journal..." 
                    class="w-full" 
                    :error="createForm.errors.ledger_id"
                />

                <BaseDatePicker 
                    v-model="createForm.transaction_date" 
                    label="Date"
                    required
                    dateFormat="yy-mm-dd" 
                    class="w-full !bg-white " 
                    :error="createForm.errors.transaction_date"
                />

                <BaseSelect 
                    v-model="createForm.transaction_mode" 
                    label="Mode"
                    required
                    :options="[{label:'Cash',value:'Cash'},{label:'Bank',value:'Bank'},{label:'Cheque',value:'Cheque'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    filter 
                    class="w-full" 
                    :error="createForm.errors.transaction_mode"
                />

                <BaseInput 
                    v-model="createForm.reference" 
                    label="Reference"
                    placeholder="Ref/Chq No" 
                    class="w-full" 
                    :error="createForm.errors.reference"
                />

                <div class="md:col-span-2 flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Notes</label>
                    <Textarea v-model="createForm.description" rows="1" class="w-full !bg-white" />
                </div>
            </div>

            <!-- Previous Excess / Advance Balance Box -->
            <div v-if="patronAdvanceBalance > 0" class="mt-6 p-4 rounded-xl border border-indigo-200 bg-indigo-50 dark:bg-indigo-950/20 dark:border-indigo-850 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-600 p-2 rounded-lg text-white shadow-md">
                        <BanknotesIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-indigo-800 dark:text-indigo-200 uppercase tracking-wider">Available Patron Advance Balance</h4>
                        <p class="text-sm font-black text-indigo-700 dark:text-indigo-300 font-mono mt-0.5">
                            ₹ {{ patronAdvanceBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 bg-white dark:bg-slate-800 px-4 py-2 rounded-lg border border-indigo-100 dark:border-slate-700 shadow-inner">
                    <input 
                        type="checkbox" 
                        id="use_excess_amount" 
                        v-model="createForm.use_excess_amount"
                        class="w-4.5 h-4.5 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500 cursor-pointer bg-white dark:bg-slate-950"
                    />
                    <label for="use_excess_amount" class="text-xs font-bold text-slate-700 dark:text-slate-200 select-none cursor-pointer">
                        Use Previous Excess Amount (Advance)
                    </label>
                </div>
            </div>

            <!-- Current Excess / Unallocated Amount Box -->
            <div v-if="!createForm.use_excess_amount && createForm.amount > totalAllocated" class="mt-6 p-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-500 p-2 rounded-lg text-white shadow-md">
                        <BanknotesIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-amber-800 dark:text-amber-200 uppercase tracking-wider">Unallocated Excess Amount</h4>
                        <p class="text-xs text-amber-600 dark:text-amber-400 font-bold font-mono">
                            ₹ {{ (createForm.amount - totalAllocated).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </p>
                    </div>
                </div>
                <div class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-wider bg-white dark:bg-amber-950/30 px-3 py-1.5 rounded-lg border border-amber-100 dark:border-amber-900/50 shadow-inner">
                    Will be saved as new Advance Balance
                </div>
            </div>

            <!-- Advance Consumed / Applied Amount Box -->
            <div v-if="createForm.use_excess_amount && totalAllocated > createForm.amount" class="mt-6 p-4 rounded-xl border border-indigo-200 bg-indigo-50 dark:bg-indigo-950/20 dark:border-indigo-850 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-600 p-2 rounded-lg text-white shadow-md">
                        <BanknotesIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-indigo-800 dark:text-indigo-200 uppercase tracking-wider">Advance Balance Applied</h4>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-bold font-mono">
                            ₹ {{ (totalAllocated - createForm.amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </p>
                    </div>
                </div>
                <div class="text-[10px] font-black text-indigo-700 dark:text-indigo-400 uppercase tracking-wider bg-white dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50 shadow-inner">
                    Applying previous advance to this transaction
                </div>
            </div>

            <!-- ERP-Style Allocation Section -->
            <div v-if="outstandingInvoices.length > 0" class="mt-8 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                <!-- Header -->
                <div class="px-4 py-3 border-b border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <BanknotesIcon class="w-5 h-5 text-slate-500" />
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-gray-200">
                            Outstanding {{ createForm.transaction_type === 'receipt' ? 'Invoices' : 'Bills' }} Allocation
                        </h4>
                    </div>
                    <Button 
                        @click="autoAllocate" 
                        size="small" 
                        severity="secondary"
                        class="!px-3 !py-1.5 !text-xs !font-medium"
                    >
                        Auto Allocate (FIFO)
                    </Button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left whitespace-nowrap">
                        <thead class="text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/20 border-b border-slate-200 dark:border-slate-700 uppercase">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Document No.</th>
                                <th class="px-4 py-3 font-semibold">Date</th>
                                <th class="px-4 py-3 font-semibold text-right">Original Amount</th>
                                <th class="px-4 py-3 font-semibold text-right">Balance Due</th>
                                <th class="px-4 py-3 font-semibold text-right w-48">Payment Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-for="inv in outstandingInvoices" :key="inv.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-4 py-2 font-medium text-slate-900 dark:text-slate-100">
                                    {{ inv.full_number }}
                                    <span v-if="inv.due_date && new Date(inv.due_date) < new Date()" class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                        Overdue
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-slate-500 dark:text-slate-400">
                                    {{ new Date(inv.invoice_date).toLocaleDateString('en-CA', {timeZone: 'Asia/Kolkata' }) }}
                                </td>
                                <td class="px-4 py-2 text-right text-slate-500 dark:text-slate-400 font-mono text-xs">
                                    {{ Number(inv.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-4 py-2 text-right font-medium text-slate-700 dark:text-slate-300 font-mono text-xs">
                                    {{ Number(inv.balance_amount || inv.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button 
                                            type="button"
                                            @click.prevent="() => {
                                                const bal = parseFloat(inv.balance_amount || inv.total_amount);
                                                const idx = createForm.allocations.findIndex(a => a.invoice_id === inv.id);
                                                if (idx > -1 && createForm.allocations[idx].amount === bal) createForm.allocations.splice(idx, 1);
                                                else if (idx > -1) createForm.allocations[idx].amount = bal;
                                                else createForm.allocations.push({ invoice_id: inv.id, invoice_number: inv.full_number, amount: bal, balance: bal });
                                            }"
                                            class="text-[10px] uppercase font-bold text-indigo-600 hover:text-indigo-800 px-1.5 py-1 bg-indigo-50 hover:bg-indigo-100 rounded border border-indigo-200 transition-colors"
                                            title="Settle Full Balance"
                                        >
                                            Full
                                        </button>
                                        <div class="relative w-32">
                                            <input 
                                                type="number" 
                                                :value="createForm.allocations.find(a => a.invoice_id === inv.id)?.amount || ''" 
                                                @input="(e) => {
                                                    const val = parseFloat((e.target as HTMLInputElement).value) || 0;
                                                    const idx = createForm.allocations.findIndex(a => a.invoice_id === inv.id);
                                                    if (val > 0) {
                                                        if (idx > -1) createForm.allocations[idx].amount = val;
                                                        else createForm.allocations.push({ invoice_id: inv.id, invoice_number: inv.full_number, amount: val, balance: inv.balance_amount || inv.total_amount });
                                                    } else if (idx > -1) {
                                                        createForm.allocations.splice(idx, 1);
                                                    }
                                                }"
                                                class="w-full text-right h-8 py-1 px-2 border border-slate-300 dark:border-slate-600 rounded text-xs font-mono focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                                placeholder="0.00"
                                            />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-800/50 border-t border-slate-300 dark:border-slate-700">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-300">
                                    Total Allocated
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-indigo-600 dark:text-indigo-400 font-mono text-sm border-l border-slate-200 dark:border-slate-700">
                                    {{ totalAllocated.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </td>
                            </tr>
                            <tr v-if="totalAvailableFunding !== totalAllocated">
                                <td colspan="4" class="px-4 py-2 text-right font-medium text-slate-500">
                                    {{ totalAvailableFunding > totalAllocated ? 'Unallocated (Advance)' : 'Over-allocated (Invalid)' }}
                                </td>
                                <td :class="`px-4 py-2 text-right font-bold font-mono text-sm border-l border-slate-200 dark:border-slate-700 ${totalAvailableFunding > totalAllocated ? 'text-amber-600 dark:text-amber-500' : 'text-red-600 dark:text-red-500'}`">
                                    {{ (totalAvailableFunding - totalAllocated).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex justify-end items-center pt-6 border-t border-slate-200 dark:border-slate-700">
                <Button 
                    @click="handleCreate" 
                    :loading="createForm.processing" 
                    :disabled="createForm.processing || totalAllocated > totalAvailableFunding"
                    class="!h-[48px] !px-10 !bg-indigo-600 hover:!bg-indigo-700 disabled:!bg-slate-300 disabled:!text-slate-500 disabled:!shadow-none !border-none !shadow-lg !shadow-indigo-200 dark:!shadow-none !rounded-xl transition-all group flex items-center gap-3"
                >
                    <span class="text-white font-black uppercase text-[12px] tracking-widest">Create</span>
                    <ArrowRightEndOnRectangleIcon class="w-5 h-5 text-white/80 transition-transform group-hover:translate-x-1" />
                </Button>
            </div>
        </div>
    </div>
</template>
