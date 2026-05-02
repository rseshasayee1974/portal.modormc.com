<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
    BanknotesIcon, 
    ArrowRightEndOnRectangleIcon,
    PlusCircleIcon
} from '@heroicons/vue/24/outline';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const props = defineProps<{
    ledgers: { id: number; title: string }[];
    patrons: { id: number; legal_name: string }[];
}>();

const createForm = useForm({
    transaction_date: new Date().toLocaleDateString('en-CA'),
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
        onSuccess: () => {
            createForm.reset();
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
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-6">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Type *</label>
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
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Amount *</label>
                    <BaseInputNumber v-model="createForm.amount" class="w-full" :minFractionDigits="2" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Journal *</label>
                    <BaseSelect v-model="createForm.ledger_id" :options="ledgerOptions" optionLabel="label" optionValue="value" filter placeholder="Select Journal..." class="w-full" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Date *</label>
                    <DatePicker v-model="createForm.transaction_date" dateFormat="yy-mm-dd" class="w-full !bg-white h-[42px]" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Mode*</label>
                    <BaseSelect v-model="createForm.transaction_mode" :options="[{label:'Cash',value:'Cash'},{label:'Bank',value:'Bank'},{label:'Cheque',value:'Cheque'}]" optionLabel="label" optionValue="value" filter class="w-full" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Reference</label>
                    <BaseInput v-model="createForm.reference" placeholder="Ref/Chq No" class="w-full h-[42px] bg-white" />
                </div>

                <div class="md:col-span-2 flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Notes</label>
                    <Textarea v-model="createForm.description" rows="1" class="w-full !bg-white" />
                </div>
            </div>

            <div class="flex justify-end items-center mt-8 pt-6 border-t border-[#CBD5E0]/30 dark:border-slate-700/30">
                <Button 
                    @click="handleCreate" 
                    :loading="createForm.processing" 
                    class="!h-[48px] !px-10 !bg-indigo-600 hover:!bg-indigo-700 !border-none !shadow-lg !shadow-indigo-200 dark:!shadow-none !rounded-xl transition-all group flex items-center gap-3"
                >
                    <span class="text-white font-black uppercase text-[12px] tracking-widest">Post Transaction</span>
                    <ArrowRightEndOnRectangleIcon class="w-5 h-5 text-white/80 transition-transform group-hover:translate-x-1" />
                </Button>
            </div>
        </div>
    </div>
</template>
