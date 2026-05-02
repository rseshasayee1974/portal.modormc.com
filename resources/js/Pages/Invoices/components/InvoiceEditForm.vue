<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
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
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    invoice: any;
    patrons: any[];
    taxes: any[]; 
    accounts: any[];
    mixdesign: any[];
    units: any[];
}>();

const emit = defineEmits(['cancel', 'saved']);
const toast = useToast();

const form = useForm({
    partner_id: props.invoice.partner_id,
    account_id: props.invoice.account_id, 
    invoice_type: props.invoice.invoice_type,
    invoice_label: props.invoice.invoice_label,
    prefix: props.invoice.prefix,
    invoice_number: props.invoice.invoice_number,
    ref_id: props.invoice.ref_id,
    ref_title: props.invoice.ref_title,
    invoice_date: props.invoice.invoice_date ? new Date(props.invoice.invoice_date) : null,
    due_date: props.invoice.due_date ? new Date(props.invoice.due_date) : null,
    period: props.invoice.period,
    global_discount_type: props.invoice.global_discount_type || '₹',
    global_discount: Number(props.invoice.global_discount) || 0,
    adjustment: Number(props.invoice.adjustment) || 0,
    shipping_charges: Number(props.invoice.shipping_charges) || 0,
    shipping_tax_id: props.invoice.shipping_tax_id,
    amount_untaxed: 0,
    amount_tax: 0,
    amount_total: 0,
    items: (props.invoice.items || []).map((it: any) => ({
        id: it.id,
        mix_design_id: it.mix_design_id,
        uom_id: it.uom_id,
        item_name: it.item_name,
        hsn_code: it.hsn_code,
        tax_id: it.tax_id,
        quantity: Number(it.quantity),
        price_unit: Number(it.price_unit),
        discount_type: it.discount_type || '%',
        discount: Number(it.discount),
        subtotal: 0,
        tax_amount: 0
    }))
});

function createNewItem() {
    return {
        mix_design_id: null,
        uom_id: null,
        item_name: '',
        hsn_code: '',
        tax_id: null,
        quantity: 1,
        price_unit: 0,
        discount_type: '%',
        discount: 0,
        subtotal: 0,
        tax_amount: 0
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
    let taxTotal = 0;

    form.items.forEach(item => {
        const gross = (Number(item.quantity) || 0) * (Number(item.price_unit) || 0);
        const discount = item.discount_type === '₹' 
            ? (Number(item.discount) || 0) 
            : gross * ((Number(item.discount) || 0) / 100);
        
        item.subtotal = gross - discount;
        
        // Calculate tax for this line
        const tax = props.taxes.find(t => t.value === item.tax_id);
        const rate = tax ? Number(tax.rate) : 0;
        item.tax_amount = item.subtotal * (rate / 100);
        
        untaxed += item.subtotal;
        taxTotal += item.tax_amount;
    });

    form.amount_untaxed = untaxed;
    form.amount_tax = taxTotal;
    
    // Calculate global discount
    const globalDiscount = form.global_discount_type === '₹' 
        ? (Number(form.global_discount) || 0) 
        : untaxed * ((Number(form.global_discount) || 0) / 100);

    // Add shipping tax if applicable
    if (form.shipping_charges > 0 && form.shipping_tax_id) {
        const sTax = props.taxes.find(t => t.value === form.shipping_tax_id);
        if (sTax) {
            form.amount_tax += form.shipping_charges * (Number(sTax.rate) / 100);
        }
    }

    form.amount_total = untaxed + form.amount_tax - globalDiscount + (Number(form.adjustment) || 0) + (Number(form.shipping_charges) || 0);
};

const onMixDesignChange = (index: number) => {
    const item = form.items[index];
    const design = props.mixdesign.find(p => p.value === item.mix_design_id);
    
    if (design) {
        item.item_name = design.label;
        item.price_unit = design.rate || 0;
        item.uom_id = design.uom_id || null;
    }
    calculateTotals();
};


watch(() => [form.items, form.adjustment, form.shipping_charges, form.global_discount, form.global_discount_type, form.shipping_tax_id], calculateTotals, { deep: true, immediate: true });

const submit = () => {
    form.put(route('invoices.update', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Invoice saved', life: 3000 });
            emit('saved');
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
    <div class="    overflow-hidden ">
        <form @submit.prevent="submit" class="space-y-2">
            
            <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-md bg-amber-500/10 flex items-center justify-center shadow-inner">
                        <PencilSquareIcon class="w-4 h-4 text-amber-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-widest">Editing</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ invoice.invoice_number }}</p>
                    </div>
                </div>
                 
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 px-1">
                <BaseSelect v-model="form.partner_id" label="Partner / Customer" :options="patrons" optionLabel="label" optionValue="value" filter required />
                <BaseSelect v-model="form.account_id" label="Ledger Account" :options="accounts" optionLabel="label" optionValue="value" filter />
                <!-- <BaseSelect v-model="form.invoice_type" label="Document Type" :options="invoiceTypeOptions" optionLabel="label" optionValue="value" required /> -->
                <!-- <BaseInput v-model="form.invoice_number" label="Invoice Number" :disabled="true" aria-readonly="true" /> -->
            <!-- </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 px-1"> -->
                <BaseDatePicker v-model="form.invoice_date" label="Invoice Date" required />
                <BaseDatePicker v-model="form.due_date" label="Due Date" />
                <!-- <BaseInput v-model="form.prefix" label="Prefix" /> -->
            </div>

            <!-- Items Table Area -->
            <div class="mt-6 border border-slate-100 rounded-sm shadow-sm overflow-hidden bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[900px]">
                        <thead class="bg-slate-50 border-b border-slate-100 uppercase tracking-tighter text-[10px] font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3" style="width: 250px;">Product / Service</th>
                                <th class="px-4 py-3 text-center" style="width: 100px;">Qty</th>
                                <th class="px-4 py-3 text-center" style="width: 100px;">UOM</th>
                                <th class="px-4 py-3 text-center" style="width: 140px;">Rate</th>
                                <th class="px-4 py-3 text-center" style="width: 120px;">TAX</th>
                                <th class="px-4 py-3 text-center" style="width: 180px;">Discount</th>
                                <th class="px-4 py-3 text-right">Net Amount</th>
                                <th class="px-1 py-1" style="width: 50px;">
                                    <button type="button" @click="addItem" class="text-indigo-600 font-bold hover:text-indigo-700">
                                        <PlusIcon class="w-5 h-5 m-2 shadow-sm border border-slate-200 bg-indigo-50 hover:bg-indigo-600 hover:text-white rounded transition-colors" />
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-indigo-50/20 transition-colors text-[12px]">
                                <td class="p-2">
                                    <BaseSelect 
                                        v-model="item.mix_design_id" 
                                        :options="mixdesign" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Select Mix Design" 
                                        filter
                                        @change="onMixDesignChange(index)"
                                    />
                                </td>
                                <td class="p-2 text-center">
                                    <BaseInputNumber v-model="item.quantity" :minFractionDigits="2" size="small" />
                                </td>
                                <td class="p-2">
                                    <BaseSelect 
                                        v-model="item.uom_id" 
                                        :options="units" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="UOM" 
                                        filter
                                    />
                                </td>
                                <td class="p-2">
                                    <BaseInputNumber v-model="item.price_unit" :minFractionDigits="2" size="small" inputClass="font-semibold text-indigo-600" />
                                </td>
                                <td class="p-2">
                                    <BaseSelect 
                                        v-model="item.tax_id" 
                                        :options="taxes" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Tax" 
                                        filter
                                    />
                                </td>
                                <td class="p-2">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex gap-1">
                                            <BaseSelect 
                                                v-model="item.discount_type" 
                                                :options="[{label: '%', value: '%'}, {label: '₹', value: '₹'}]" 
                                                optionLabel="label" 
                                                optionValue="value" 
                                                class="!w-16"
                                            />
                                            <BaseInputNumber v-model="item.discount" size="small" class="flex-grow" />
                                        </div>
                                    </div>
                                </td>
                                <td class="p-2 text-sm text-right font-black text-slate-700">
                                    {{ item.subtotal.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="p-2 text-center text-red-400">
                                    <button v-if="form.items.length > 1" type="button" @click="removeItem(index)" class="hover:text-rose-500 transition-colors">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Summary Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-10 px-1">
                <div class="space-y-4 pt-4">
                    <div class="field-group">
                        <label class="text-[10px] uppercase font-black text-slate-400 tracking-widest block mb-2 px-1">Invoice Remarks / Period Info</label>
                        <Textarea v-model="form.period" rows="4" placeholder="Billing period, reference notes..." class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <BaseInput v-model="form.ref_title" label="Reference Title" placeholder="PO Ref, etc." />
                    </div>
                    <div class="flex items-center gap-6">
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Created By</span>
                        <span class="text-[11px] font-bold text-slate-700 uppercase tracking-tight mt-0.5">
                            {{ invoice.created_by?.username || 'System' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Created On</span>
                        <span class="text-[11px] font-bold text-slate-700 uppercase tracking-tight mt-0.5">
                            {{ new Date(invoice.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) }}
                        </span>
                    </div>
                </div>
                </div>

                <div class="bg-indigo-50/30 rounded-2xl p-8 border border-indigo-100 shadow-inner">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center text-[11px] font-bold text-slate-600 uppercase tracking-widest">
                            <span>Subtotal (Untaxed)</span>
                            <span class="text-slate-900">{{ form.amount_untaxed.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px] font-bold text-slate-600 uppercase tracking-widest">
                            <span>Tax Amount (+)</span>
                            <span class="text-slate-900">{{ form.amount_tax.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">Global Discount (-)</span>
                            <div class="flex gap-1 w-44">
                                <!-- <BaseSelect 
                                    v-model="form.global_discount_type" 
                                    :options="[{label: '%', value: '%'}, {label: '₹', value: '₹'}]" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    class="!w-16"
                                /> -->
                                <BaseInputNumber v-model="form.global_discount" size="small" class="flex-grow" />
                            </div>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">Shipping Charges (+)</span>
                            <BaseInputNumber v-model="form.shipping_charges" size="small" class="w-28" />
                        </div>
                        <div class="flex justify-between items-center gap-4 border-t border-slate-200/50 pt-4">
                            <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">Round Off / Adj (+/-)</span>
                            <BaseInputNumber v-model="form.adjustment" size="small" class="w-28" />
                        </div>

                        <div class="flex justify-between items-center border-t border-slate-200 pt-6 mt-6">
                            <div class="flex flex-col">
                                <span class="text-[14px] font-black text-indigo-700 uppercase tracking-[0.2em]">Payable Amount</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase mt-1">Inclusive of all manual adjustments</span>
                            </div>
                            <div class="text-right flex items-baseline gap-1">
                                <span class="text-xs text-indigo-700 font-black">₹</span>
                                <span class="text-3xl font-black text-slate-800 tracking-tight">
                                     {{ form.amount_total.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- <div class="pt-8">
                            <BaseFormActions
                                label="Update Document"
                                :loading="form.processing"
                                @submit="submit"
                                @reset="$emit('cancel')"
                                cancelLabel="Discard Changes"
                                class="!justify-end"
                            />
                        </div> -->
                    </div>
                </div>
            </div>

       
        </form>
    </div>
</template>
