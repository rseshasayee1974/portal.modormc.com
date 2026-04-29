<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { 
    PencilSquareIcon, 
    TrashIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    invoice: any;
    patrons: any[];
    taxes: any[];
    trucks: any[];
    accounts: any[];
}>();

const emit = defineEmits(['cancel']);
const toast = useToast();

const form = useForm({
    partner_id: props.invoice.partner_id,
    account_id: props.invoice.account_id,
    journal_id: props.invoice.journal_id,
    invoice_type: props.invoice.invoice_type,
    invoice_label: props.invoice.invoice_label,
    prefix: props.invoice.prefix,
    invoice_number: props.invoice.invoice_number,
    ref_id: props.invoice.ref_id,
    ref_title: props.invoice.ref_title,
    truck_id: props.invoice.truck_id,
    invoice_date: props.invoice.invoice_date,
    due_date: props.invoice.due_date,
    period: props.invoice.period,
    adjustment: props.invoice.adjustment,
    shipping_charges: props.invoice.shipping_charges,
    shipping_tax_id: props.invoice.shipping_tax_id,
    amount_untaxed: 0,
    amount_total: 0,
    items: props.invoice.items.map((it: any) => ({
        id: it.id,
        item_name: it.item_name,
        hsn_code: it.hsn_code,
        quantity: Number(it.quantity),
        price_unit: Number(it.price_unit),
        discount_type: it.discount_type,
        discount: Number(it.discount),
        subtotal: 0
    }))
});

function createNewItem() {
    return {
        item_name: '',
        hsn_code: '',
        quantity: 1,
        price_unit: 0,
        discount_type: 'percent',
        discount: 0,
        subtotal: 0
    };
}

const addItem = () => {
    form.items.push(createNewItem());
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        calculateTotals();
    }
};

const calculateTotals = () => {
    let untaxed = 0;
    form.items.forEach(item => {
        const gross = (Number(item.quantity) || 0) * (Number(item.price_unit) || 0);
        const discount = item.discount_type === 'fixed' 
            ? (Number(item.discount) || 0) 
            : gross * ((Number(item.discount) || 0) / 100);
        
        item.subtotal = gross - discount;
        untaxed += item.subtotal;
    });
    form.amount_untaxed = untaxed;
    form.amount_total = untaxed + (Number(form.adjustment) || 0) + (Number(form.shipping_charges) || 0);
};

watch(() => [form.items, form.adjustment, form.shipping_charges], calculateTotals, { deep: true, immediate: true });

const submit = () => {
    form.put(route('invoices.update', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Invoice saved', life: 3000 });
            emit('cancel');
        },
    });
};

const invoiceTypeOptions = [
    { label: 'Sales Invoice', value: 'sales' },
    { label: 'Purchase Invoice', value: 'purchase' },
    { label: 'Proforma Invoice', value: 'proforma' },
    { label: 'Credit Note', value: 'credit_note' },
    { label: 'Debit Note', value: 'debit_note' },
];

</script>

<template>
    <div class="bg-slate-50/80 dark:bg-slate-900/50 p-8 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden mt-6">
        <form @submit.prevent="submit" class="space-y-6">
            
            <div class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500 flex items-center justify-center shadow-lg shadow-amber-200">
                        <PencilSquareIcon class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Editing Draft</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ invoice.invoice_number }}</p>
                    </div>
                </div>
                
                 <div class="flex gap-2">
                    <Button label="Cancel" severity="secondary" @click="$emit('cancel')" text class="!text-[10px] !font-black !uppercase !tracking-widest" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 px-1">
                <BaseSelect v-model="form.partner_id" label="Partner" :options="patrons" optionLabel="label" optionValue="value" filter required />
                <BaseSelect v-model="form.account_id" label="Ledger Account" :options="accounts" optionLabel="label" optionValue="value" filter />
                <BaseSelect v-model="form.invoice_type" label="Type" :options="invoiceTypeOptions" optionLabel="label" optionValue="value" required />
                <BaseInput v-model="form.invoice_label" label="Label" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 px-1">
                <BaseDatePicker v-model="form.invoice_date" label="Date" required />
                <BaseDatePicker v-model="form.due_date" label="Due Date" />
                <BaseSelect v-model="form.truck_id" label="Vehicle" :options="trucks" optionLabel="label" optionValue="value" filter />
                <BaseInput v-model="form.prefix" label="Prefix" />
            </div>

            <!-- Items Table -->
            <div class="mt-6 border border-slate-100 rounded-sm shadow-sm overflow-hidden bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[900px]">
                        <thead class="bg-slate-50 border-b border-slate-100 uppercase tracking-tighter text-[10px] font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3" style="width: 250px;">Item</th>
                                <th class="px-4 py-3 text-center" style="width: 100px;">HSN</th>
                                <th class="px-4 py-3 text-center" style="width: 100px;">Qty</th>
                                <th class="px-4 py-3 text-center" style="width: 140px;">Rate</th>
                                <th class="px-4 py-3 text-center" style="width: 180px;">Discount</th>
                                <th class="px-4 py-3 text-right">Net</th>
                                <th class="px-1 py-1" style="width: 50px;">
                                    <button type="button" @click="addItem" class="text-indigo-600 font-bold hover:text-indigo-700">
                                        <PlusIcon class="w-5 h-5 m-2 shadow-sm border border-slate-200 bg-indigo-50 hover:bg-indigo-600 hover:text-white rounded transition-colors" />
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-indigo-50/20 transition-colors text-[12px]">
                                <td class="p-2 pt-4">
                                    <BaseInput v-model="item.item_name" required />
                                </td>
                                <td class="p-2 pt-4">
                                    <BaseInput v-model="item.hsn_code" />
                                </td>
                                <td class="p-2 pt-4 text-center">
                                    <BaseInputNumber v-model="item.quantity" :minFractionDigits="2" size="small" />
                                </td>
                                <td class="p-2 pt-4">
                                    <BaseInputNumber v-model="item.price_unit" :minFractionDigits="2" size="small" inputClass="font-semibold text-indigo-600" />
                                </td>
                                <td class="p-2 pt-4">
                                    <div class="flex gap-1">
                                        <BaseSelect v-model="item.discount_type" :options="[{label: '%', value: 'percent'}, {label: 'Fixed', value: 'fixed'}]" optionLabel="label" optionValue="value" class="!w-24" />
                                        <BaseInputNumber v-model="item.discount" size="small" class="flex-grow" />
                                    </div>
                                </td>
                                <td class="p-2 pt-4 text-sm text-right font-black text-slate-700">
                                    {{ item.subtotal.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="p-2 pt-4 text-center text-red-400">
                                    <button v-if="form.items.length > 1" type="button" @click="removeItem(index)" class="hover:text-rose-500 transition-colors">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-10 px-1">
                <div class="space-y-4 pt-4">
                    <label class="text-[10px] uppercase font-black text-slate-400 tracking-widest block mb-1">Remarks</label>
                    <Textarea v-model="form.period" rows="4" class="w-full text-xs rounded-xl border-slate-200" />
                    <div class="grid grid-cols-2 gap-4">
                        <BaseInput v-model="form.invoice_number" label="Invoice Override" />
                        <BaseInput v-model="form.ref_title" label="Ref Title" />
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-slate-800/80 rounded-2xl p-8 border border-slate-100 shadow-sm">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                            <span>Subtotal</span>
                            <span>{{ form.amount_untaxed.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Shipping (+)</span>
                            <BaseInputNumber v-model="form.shipping_charges" size="small" class="w-28" />
                        </div>
                        <div class="flex justify-between items-center gap-4 border-t border-slate-100 pt-4">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Adjustment (+/-)</span>
                            <BaseInputNumber v-model="form.adjustment" size="small" class="w-28" />
                        </div>

                        <div class="flex justify-between items-center border-t border-slate-200 pt-6 mt-6">
                            <span class="text-[14px] font-black text-indigo-700 uppercase tracking-[0.2em]">Total</span>
                            <div class="text-right flex items-baseline gap-1">
                                <span class="text-xs text-indigo-700 font-bold">₹</span>
                                <span class="text-2xl font-black text-slate-800 tracking-tight">
                                     {{ form.amount_total.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="pt-8">
                            <BaseFormActions
                                label="Update Records"
                                :loading="form.processing"
                                @submit="submit"
                                @reset="$emit('cancel')"
                                cancelLabel="Discard Changes"
                                class="!justify-end"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
