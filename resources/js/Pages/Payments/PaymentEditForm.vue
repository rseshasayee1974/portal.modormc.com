<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { 
    PencilSquareIcon, ArrowUpRightIcon, CheckCircleIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';

const props = defineProps<{
        payment: any;
}>();

const emit = defineEmits(['success', 'close']);

const form = useForm({
    transaction_date: (props.payment.transaction_date || props.payment.created_at || new Date().toLocaleDateString('en-CA')).split('T')[0],
    ledger_id: props.payment.ledger_id,
    patron_id: props.payment.patron_id,
    partner_type: props.payment.partner_type || 'Customer',
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

const patrons = ref<{ label: string; value: any }[]>([]);
const ledgers = ref<{ label: string; value: any }[]>([]);

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

onMounted(() => {
    fetchLedgers(form.transaction_type);
    fetchPatrons(form.partner_type);
});

watch(() => form.partner_type, (newType) => {
    fetchPatrons(newType);
});

watch(() => form.transaction_type, (newType) => {
    fetchLedgers(newType);
});

const isPosted = computed(() => props.payment.status === 'paid');

const submit = () => {
    if (isPosted.value) return;
    
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
                <BaseSelect 
                    v-model="form.transaction_type" 
                    label="Type"
                    required
                    :options="[{label:'Payment',value:'payment'},{label:'Receipt',value:'receipt'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    class="w-full"  
                    :error="form.errors.transaction_type"
                />
                
                <BaseSelect 
                    v-model="form.partner_type" 
                    label="Partner Type"
                    required
                    :options="[{label:'Customer',value:'Customer'},{label:'Vendor',value:'Vendor'},{label:'Employee',value:'Employee'},{label:'Transporter',value:'Transporter'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    class="w-full"  
                    :error="form.errors.partner_type"
                />

                <BaseSelect 
                    v-model="form.patron_id" 
                    label="Partner"
                    required
                    :options="patronOptions" 
                    optionLabel="label" 
                    optionValue="value" 
                    filter 
                    placeholder="None" 
                    class="w-full"  
                    :error="form.errors.patron_id"
                />

                <BaseInputNumber 
                    v-model="form.amount" 
                    label="Amount"
                    required
                    class="w-full font-bold"  
                    :error="form.errors.amount"
                />

                <BaseSelect 
                    v-model="form.ledger_id" 
                    label="Journal Type"
                    required
                    :options="ledgerOptions" 
                    optionLabel="label" 
                    optionValue="value" 
                    filter 
                    class="w-full"  
                    :error="form.errors.ledger_id"
                />

                <!-- Row 2 -->
                <BaseDatePicker 
                    v-model="form.transaction_date" 
                    label="Date"
                    required
                    dateFormat="yy-mm-dd" 
                    class="w-full"  
                    :error="form.errors.transaction_date"
                />

                <BaseSelect 
                    v-model="form.transaction_mode" 
                    label="Transaction Mode"
                    required
                    :options="[{label:'Cash',value:'Cash'},{label:'Bank Transfer',value:'Bank'},{label:'Cheque',value:'Cheque'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    filter 
                    class="w-full"  
                    :error="form.errors.transaction_mode"
                />

                <BaseSelect 
                    v-model="form.status" 
                    label="Status"
                    :options="[{label:'Pending',value:'pending'},{label:'paid',value:'paid'}]" 
                    optionLabel="label" 
                    optionValue="value" 
                    class="w-full"  
                    :error="form.errors.status"
                    :disabled="isPosted"
                />

                <!-- <BaseInput 
                    v-model="form.reference" 
                    label="Reference No."
                    placeholder="UTR..." 
                    class="w-full"  
                    :error="form.errors.reference"
                /> -->

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-bold text-[#4A5568] dark:text-gray-400 uppercase tracking-tighter">Notes</label>
                    <Textarea v-model="form.description" rows="1" class="w-full !bg-white"  />
                </div>

                <!-- Submit Row -->
                <!-- <div class="md:col-span-1 flex items-end">
                    <Button 
                        @click="submit" 
                        :loading="form.processing"
                        :disabled="isPosted"
                        class="h-10 px-8 bg-indigo-600 border-none rounded text-white font-black uppercase text-[10px] shadow-lg shadow-indigo-100 flex items-center gap-2 group w-full justify-center disabled:bg-slate-300 disabled:shadow-none"
                    >
                        <span>{{ isPosted ? 'Posted' : 'Update' }}</span>
                        <CheckCircleIcon v-if="isPosted" class="w-3.5 h-3.5" />
                        <ArrowUpRightIcon v-else class="w-3.5 h-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
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
                </div> -->
            </div>
        </form>
    </div>
</template>

<style scoped>
</style>
