<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { 
    PencilSquareIcon, ArrowUpRightIcon 
} from '@heroicons/vue/24/outline';

// PrimeVue
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';

const props = defineProps<{
    payment: any;
    ledgerOptions: any[];
    patronOptions: any[];
}>();

const emit = defineEmits(['success', 'close']);

const form = useForm({
    transaction_date: (props.payment.transaction_date || props.payment.created_at || new Date().toLocaleDateString('en-CA')).split('T')[0],
    ledger_id: props.payment.ledger_id,
    patron_id: props.payment.patron_id,
    partner_type: props.payment.partner_type || 'Master',
    amount: parseFloat(props.payment.amount),
    excess_amount: parseFloat(props.payment.excess_amount || 0),
    use_excess_amount: props.payment.use_excess_amount || false,
    transaction_type: props.payment.transaction_type,
    transaction_mode: props.payment.transaction_mode || 'Cash',
    reconcile_opening_balance: props.payment.reconcile_opening_balance || false,
    batch_deposit: props.payment.batch_deposit || false,
    description: props.payment.description || '',
    reference: props.payment.reference || '',
    status: props.payment.status
});

const submit = () => {
    form.put(route('payments.update', props.payment.id), {
        onSuccess: () => {
            emit('success');
        }
    });
};
</script>

<template>
    <div class="bg-[#EBF1F9] dark:bg-slate-800/50 px-8 py-6 rounded-[0.5rem] border border-[#CBD5E0] dark:border-slate-700 shadow-inner my-6 relative overflow-hidden transition-all">
        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Row 1 -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Type Payment/Receipt *</label>
                    <BaseSelect v-model="form.transaction_type" :options="[{label:'Payment',value:'payment'},{label:'Receipt',value:'receipt'}]" optionLabel="label" optionValue="value" class="w-full"  />
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Partner Type *</label>
                    <BaseSelect v-model="form.partner_type" :options="[{label:'Master',value:'Master'},{label:'Other',value:'Other'}]" optionLabel="label" optionValue="value" class="w-full"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Partner *</label>
                    <BaseSelect v-model="form.patron_id" :options="patronOptions" optionLabel="label" optionValue="value" filter placeholder="None" class="w-full"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Amount *</label>
                    <BaseInputNumber v-model="form.amount" class="w-full font-bold"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Journal Type *</label>
                    <BaseSelect v-model="form.ledger_id" :options="ledgerOptions" optionLabel="label" optionValue="value" filter class="w-full"  />
                </div>

                <!-- Row 2 -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Date *</label>
                    <DatePicker v-model="form.transaction_date" dateFormat="yy-mm-dd" class="w-full"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Transaction Mode*</label>
                    <BaseSelect v-model="form.transaction_mode" :options="[{label:'Cash',value:'Cash'},{label:'Bank Transfer',value:'Bank'},{label:'Cheque',value:'Cheque'}]" optionLabel="label" optionValue="value" filter class="w-full"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Status</label>
                    <BaseSelect v-model="form.status" :options="[{label:'Pending',value:'pending'},{label:'Completed',value:'completed'}]" optionLabel="label" optionValue="value" class="w-full"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Reference No.</label>
                    <BaseInput v-model="form.reference" placeholder="UTR..." class="w-full"  />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Notes</label>
                    <Textarea v-model="form.description" rows="1" class="w-full"  />
                </div>

                <!-- Submit Row -->
                <div class="md:col-span-1 flex items-end">
                    <Button 
                        @click="submit" 
                        :loading="form.processing"
                        class="h-10 px-8 bg-indigo-600 border-none rounded text-white font-black uppercase text-[10px] shadow-lg shadow-indigo-100 flex items-center gap-2 group w-full justify-center"
                    >
                        <span>Update</span>
                        <ArrowUpRightIcon class="w-3.5 h-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                    </Button>
                </div>
                <div class="md:col-span-1 flex items-end">
                    <Button 
                        label="Cancel" 
                        text 
                        severity="secondary" 
                         
                        class="w-full"
                        @click="emit('close')"
                    />
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped>
</style>
