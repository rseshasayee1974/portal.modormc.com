<script setup lang="ts">
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { 
    DocumentChartBarIcon, 
    TrashIcon,
    PlusIcon,
    TruckIcon,
    CalendarIcon,
    BanknotesIcon,
    DocumentTextIcon,
    Square3Stack3DIcon
} from '@heroicons/vue/24/outline';

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    patrons: any[];
    taxes: any[]; 
    accounts: any[];
    mixdesign: any[];
    units: any[];
    instant_invoice_patron: number | boolean;
    
}>();

const page = usePage();
const toast = useToast();
const isOpen = ref(true);

const form = useForm({
    partner_id: null,
    account_id: null,
    journal_id: null,
    invoice_type: 'sales',
    invoice_label: 'Tax Invoice',
    prefix: 'INV',
    invoice_number: '',
    ref_id: null,
    ref_title: '',
    truck_id: null,
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: null,
    period: '',
    global_discount_type: '₹',
    global_discount: 0,
    adjustment: 0,
    shipping_charges: 0,
    shipping_tax_id: null,
    amount_untaxed: 0,
    amount_tax: 0,
    amount_total: 0,
    items: [] as any[]
});

// Initialize with one item
form.items.push(createNewItem());

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
        tax_amount: 0,
        total: 0
    };
}

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const addItem = () => {
    form.items.push(createNewItem());
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        calculateTotals();
    } else {
        toast.add({ severity: 'warn', summary: 'Warning', detail: 'At least one item is required', life: 3000 });
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

    form.amount_total = untaxed + taxTotal - globalDiscount + (Number(form.adjustment) || 0) + (Number(form.shipping_charges) || 0);
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

watch(() => [form.items, form.adjustment, form.shipping_charges], calculateTotals, { deep: true });

const submit = () => {
    form.post(route('invoices.store'), {
        onSuccess: () => {
            form.reset();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice processed successfully', life: 3000 });
        },
    });
};

const isInstantPartnerEnabled = computed(() => Number(props.instant_invoice_patron) === 1);
const isCreatingPartner = ref(false);

const handleCreatePartner = async (name: string) => {
    isCreatingPartner.value = true;
    try {
        const response = await axios.post(route('patrons.store'), {
            legal_name: name,
            patron_type: form.invoice_type === 'purchase' ? ['Vendor', 'Supplier'] : ['Customer'],
            operational_status: 'active',
            status: true,
            displayed: true,
        });
        
        const newPatron = response.data.patron;
        
        toast.add({ severity: 'success', summary: 'Partner Created', detail: `${name} is now available`, life: 3000 });

        router.reload({
            only: ['patrons'],
            onSuccess: () => {
                form.partner_id = newPatron.id;
            }
        });
    } catch (error: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Failed to create partner', life: 3000 });
    } finally {
        isCreatingPartner.value = false;
    }
};

const invoiceTypeOptions = [
    { label: 'Sales Invoice', value: 'sales' },
    { label: 'Purchase Invoice', value: 'purchase' },
    { label: 'Proforma Invoice', value: 'proforma' },
    { label: 'Credit Note', value: 'credit_note' },
    { label: 'Debit Note', value: 'debit_note' },
];

const taxOptions = computed(() => props.taxes);

</script>

<template>
    <div class="create-panel" :class="{ 'create-panel--open': isOpen }">
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <DocumentChartBarIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Generate Invoices</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Manual Invoice & Logistics Reconciliation Module</p>
                </div>
            </div>
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Top Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 px-1">
                        <BaseCreatableSelect
                            v-if="isInstantPartnerEnabled"
                            v-model="form.partner_id" 
                            label="Partner / Customer"
                            :options="patrons"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Partner"
                            :error="form.errors.partner_id"
                            required
                            :creating="isCreatingPartner"
                            @create="handleCreatePartner"
                        />
                        <BaseSelect 
                            v-else
                            v-model="form.partner_id" 
                            label="Partner / Customer"
                            :options="patrons"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Partner"
                            :error="form.errors.partner_id"
                            filter
                            required
                        />
                         <BaseSelect 
                            v-model="form.account_id" 
                            label="Ledger Account"
                            :options="accounts"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Account"
                            :error="form.errors.account_id"
                            filter
                        />
                        <BaseSelect 
                            v-model="form.invoice_type" 
                            label="Document Type"
                            :options="invoiceTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Type"
                            :error="form.errors.invoice_type"
                            required
                        />
                        
                    <!-- </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 px-1"> -->
                        <BaseDatePicker
                            v-model="form.invoice_date"
                            label="Invoice Date"
                            required
                            :error="form.errors.invoice_date"
                        />
                        <!-- <BaseDatePicker 
                            v-model="form.due_date" 
                            label="Due Date" 
                            :error="form.errors.due_date"
                        /> -->
                        <!-- <BaseSelect 
                            v-model="form.truck_id" 
                            label="Linked Vehicle"
                            :options="trucks"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Truck"
                            :error="form.errors.truck_id"
                            filter
                        /> -->
                        <!-- <BaseInput 
                            v-model="form.prefix" 
                            label="Prefix"
                            placeholder="INV"
                            :error="form.errors.prefix"
                        /> -->
                    </div>

                    <!-- Items Table Area -->
                    <div class="mt-6 border border-slate-100 rounded-sm shadow-sm overflow-hidden bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[900px]">
                                <thead class="bg-slate-50 border-b border-slate-100 uppercase tracking-tighter text-[10px] font-bold text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 250px;">Product / Service</th>
                                        <!-- <th class="px-4 py-3" style="width: 200px;">Description</th> -->
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
                                        <!-- <td class="p-2">
                                            <BaseInput v-model="item.item_name" placeholder="Description" />
                                        </td> -->
                                        
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
                                                <div v-if="item.discount > 0" class="text-[10px] text-right text-rose-500 font-bold px-1">
                                                    -{{ (item.discount_type === 'fixed' ? item.discount : (item.quantity * item.price_unit * (item.discount / 100))).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
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
                                
                                <div class="pt-8">
                                    <BaseFormActions
                                        label="Commit Invoice"
                                        :loading="form.processing"
                                        @submit="submit"
                                        @reset="form.reset()"
                                        cancelLabel="Clear"
                                        class="!justify-end"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.create-panel {
    @apply bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-2xl shadow-indigo-900/5 overflow-hidden transition-all duration-500 ease-in-out;
}
 
.create-panel__header {
    @apply w-full p-3 px-8 bg-gradient-to-r from-slate-50 to-white dark:from-slate-900 dark:to-slate-800 flex justify-between items-center border-b border-slate-100 dark:border-slate-700 hover:bg-slate-100/50 transition-colors;
}
.create-panel__icon {
    @apply w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shadow-inner;
}
.create-panel__body {
    @apply p-8;
}

/* Panel Slide Animation */
.panel-slide-enter-active, .panel-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.panel-slide-enter-from, .panel-slide-leave-to {
    opacity: 0;
    transform: translateY(-20px);
    max-height: 0;
}
</style>
