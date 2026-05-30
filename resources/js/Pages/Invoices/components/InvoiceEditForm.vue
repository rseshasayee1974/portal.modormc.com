<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { 
    PencilSquareIcon, 
    TrashIcon,
    PlusIcon,
    DocumentTextIcon,
    ArrowPathIcon,
    ExclamationCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline';

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    invoice: any;
    patrons: any[];
    taxes: any[]; 
    accounts: any[];
    mixdesign: any[];
    units: any[];
    machines: any[];
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
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Invoice saved', life: 1500 });
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

const activeTab = ref<'invoice' | 'compliance'>('invoice');

const complianceForm = useForm({
    generate_eway: false,
    vehicle_no: props.invoice.eway_bill_no || '',
    distance_km: 100,
    trans_mode: '1',
    vehicle_type: 'Regular',
    transporter_id: '',
    transporter_name: '',
    cancel_reason: '2',
    cancel_remarks: '',
});

const generateEInvoice = () => {
    complianceForm.post(route('invoices.generate-einvoice', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'E-Invoice Generated', detail: 'Compliance details recorded', life: 1500 });
            emit('saved');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.add({ severity: 'error', summary: 'Compliance Error', detail: errors.error, life: 3000 });
            }
        }
    });
};

const cancelEInvoice = () => {
    complianceForm.post(route('invoices.cancel-einvoice', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'E-Invoice Cancelled', detail: 'IRN has been cancelled', life: 1500 });
            emit('saved');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.add({ severity: 'error', summary: 'Error', detail: errors.error, life: 3000 });
            }
        }
    });
};

const generateEWayBillOnly = () => {
    complianceForm.post(route('invoices.generate-ewaybill', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'E-Way Bill Generated', detail: 'E-Way bill number recorded', life: 1500 });
            emit('saved');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.add({ severity: 'error', summary: 'Compliance Error', detail: errors.error, life: 3000 });
            }
        }
    });
};

const cancelEWayBillOnly = () => {
    complianceForm.post(route('invoices.cancel-ewaybill', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'E-Way Bill Cancelled', detail: 'E-Way bill cancelled', life: 1500 });
            emit('saved');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.add({ severity: 'error', summary: 'Error', detail: errors.error, life: 3000 });
            }
        }
    });
};

const setupDemoCompliance = () => {
    complianceForm.post(route('invoices.setup-demo-compliance', props.invoice.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Demo Data Loaded', detail: 'GSTINs & Addresses updated for test', life: 1500 });
            emit('saved');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.add({ severity: 'error', summary: 'Error', detail: errors.error, life: 3000 });
            }
        }
    });
};
</script>

<template>
    <div class="overflow-hidden p-1">
        <!-- Tab Navigation -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 rounded-lg p-1 w-fit border border-slate-200 dark:border-slate-700 mb-6">
            <button
                v-for="tab in [
                    { key: 'invoice', label: 'Invoice Editor', icon: PencilSquareIcon },
                    { key: 'compliance', label: 'E-Invoice & E-Way Bill', icon: DocumentTextIcon },
                ]"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key"
                :class="[
                    'flex items-center gap-2 px-5 py-2 text-[10px] font-black uppercase tracking-widest rounded-md transition-all duration-200',
                    activeTab === tab.key
                        ? 'bg-white dark:bg-slate-800 text-indigo-600 shadow-sm border border-slate-200 dark:border-slate-700'
                        : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50'
                ]"
            >
                <component :is="tab.icon" class="w-3.5 h-3.5" />
                {{ tab.label }}
            </button>
        </div>

        <!-- 1. INVOICE EDITOR TAB -->
        <div v-show="activeTab === 'invoice'">
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
                    <BaseSelect v-model="form.partner_id" label="Partner / Customer" :options="patrons" optionLabel="label" optionValue="value" filter :error="form.errors.partner_id" required />
                    <BaseSelect v-model="form.account_id" label="Ledger Account" :options="accounts" optionLabel="label" optionValue="value" filter :error="form.errors.account_id" />
                    <BaseDatePicker v-model="form.invoice_date" label="Invoice Date" required />
                    <BaseDatePicker v-model="form.due_date" label="Due Date" />
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
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- 2. COMPLIANCE TAB -->
        <div v-show="activeTab === 'compliance'" class="space-y-6">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-md bg-indigo-500/10 flex items-center justify-center shadow-inner">
                        <DocumentTextIcon class="w-4 h-4 text-indigo-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-widest">Compliance Control</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ invoice.invoice_number }}</p>
                    </div>
                </div>
            </div>

            <!-- Compliance Validation Errors -->
            <div v-if="complianceForm.hasErrors" class="bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 rounded-2xl p-6 space-y-4">
                <div class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                    <ExclamationCircleIcon class="w-5 h-5 flex-shrink-0" />
                    <h4 class="text-xs font-black uppercase tracking-wider text-rose-700 dark:text-rose-300">Compliance Validation Failed</h4>
                </div>
                <ul class="text-[11px] font-semibold text-rose-700 dark:text-rose-300 space-y-1.5 list-disc list-inside">
                    <li v-for="(err, key) in complianceForm.errors" :key="key">
                        <span class="font-bold capitalize">{{ key.replace('_', ' ') }}:</span> {{ err }}
                    </li>
                </ul>
                <div class="pt-3 border-t border-rose-200/50 dark:border-rose-800/50 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">To run the compliance simulator, you can automatically update this Plant and Customer with valid mock GSTINs and operational addresses.</p>
                    <Button 
                        @click="setupDemoCompliance"
                        :disabled="complianceForm.processing"
                        class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !px-4 !py-2 !rounded-lg !flex !items-center !gap-2 flex-shrink-0"
                    >
                        <ArrowPathIcon v-if="complianceForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>🔧 Setup Demo Compliance Data</span>
                    </Button>
                </div>
            </div>

            <!-- NOT GENERATED STATE -->
            <div v-if="!invoice.einvoice_irn && invoice.einvoice_status !== 'cancelled'" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="col-span-12 md:col-span-8 bg-slate-50/50 dark:bg-slate-900/50 rounded-2xl p-6 border border-slate-100 dark:border-slate-800 space-y-4">
                    <h4 class="text-xs font-black uppercase text-slate-500 tracking-wider">Generate E-Invoice & E-Way Bill</h4>
                    
                    <div class="flex items-center gap-3 py-2 px-1">
                        <input type="checkbox" id="generate_eway" v-model="complianceForm.generate_eway" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" />
                        <label for="generate_eway" class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider cursor-pointer">Generate E-Way Bill along with E-Invoice</label>
                    </div>

                    <!-- E-Way Bill Inputs -->
                    <div v-if="complianceForm.generate_eway" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <BaseSelect v-model="complianceForm.vehicle_no" label="Vehicle Number *" :options="machines" optionLabel="label" optionValue="value" placeholder="Select Vehicle" :error="complianceForm.errors.vehicle_no" filter required />
                        <BaseInputNumber v-model="complianceForm.distance_km" label="Distance in Km *" placeholder="150" :error="complianceForm.errors.distance_km" />
                        <BaseSelect v-model="complianceForm.trans_mode" label="Transport Mode *" :options="[{label: 'Road', value: '1'}, {label: 'Rail', value: '2'}, {label: 'Air', value: '3'}, {label: 'Ship', value: '4'}]" optionLabel="label" optionValue="value" :error="complianceForm.errors.trans_mode" required />
                        <BaseSelect v-model="complianceForm.vehicle_type" label="Vehicle Type *" :options="[{label: 'Regular', value: 'Regular'}, {label: 'ODC (Over Dimensional Cargo)', value: 'ODC'}]" optionLabel="label" optionValue="value" :error="complianceForm.errors.vehicle_type" required />
                        <BaseInput v-model="complianceForm.transporter_id" label="Transporter GSTIN/ID" placeholder="15-character GSTIN or ID" :error="complianceForm.errors.transporter_id" />
                        <BaseInput v-model="complianceForm.transporter_name" label="Transporter Name" placeholder="Transporter Company Name" />
                    </div>

                    <div class="flex justify-end pt-4">
                        <Button 
                            @click="generateEInvoice"
                            :disabled="complianceForm.processing"
                            class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !px-6 !py-2.5 !rounded-lg !flex !items-center !gap-2"
                        >
                            <ArrowPathIcon v-if="complianceForm.processing" class="w-4 h-4 animate-spin" />
                            <span>{{ complianceForm.generate_eway ? 'Generate E-Invoice & E-Way Bill' : 'Generate E-Invoice IRN' }}</span>
                        </Button>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-4 bg-amber-50/20 dark:bg-amber-950/10 border border-amber-100/50 dark:border-amber-900/30 rounded-2xl p-6 space-y-4">
                    <div class="flex gap-2 text-amber-600">
                        <ExclamationCircleIcon class="w-5 h-5 flex-shrink-0" />
                        <h4 class="text-xs font-black uppercase tracking-wider">Compliance Checklist</h4>
                    </div>
                    <ul class="text-[10px] font-medium text-slate-500 dark:text-slate-400 space-y-2 list-disc list-inside">
                        <li>Seller and Buyer must have valid 15-digit GSTINs.</li>
                        <li>HSN codes must be set for all line items.</li>
                        <li>Active internet connection or sandbox simulation must be online.</li>
                        <li>E-Invoice generation is legally required for dispatches.</li>
                    </ul>
                    <div class="pt-4 border-t border-amber-200/30 dark:border-amber-900/30">
                        <Button 
                            @click="setupDemoCompliance"
                            :disabled="complianceForm.processing"
                            class="w-full !bg-amber-600 hover:!bg-amber-700 !text-white !font-black !text-[9px] !uppercase !tracking-widest !py-2.5 !rounded-lg !flex !items-center !justify-center !gap-2"
                        >
                            <ArrowPathIcon v-if="complianceForm.processing" class="w-3.5 h-3.5 animate-spin" />
                            <span>🔧 Setup Demo Compliance Data</span>
                        </Button>
                        <a 
                            :href="route('compliance.test')"
                            class="mt-3 block text-center text-[9px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            🔗 Open Compliance Testing Center
                        </a>
                    </div>
                </div>
            </div>

            <!-- GENERATED STATE -->
            <div v-else-if="invoice.einvoice_status === 'generated'" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Details Card -->
                <div class="col-span-12 md:col-span-8 space-y-6">
                    <div class="bg-emerald-50/30 dark:bg-emerald-950/10 border border-emerald-100 dark:border-emerald-900/30 rounded-2xl p-6 space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xs font-black uppercase text-emerald-600 tracking-wider">E-Invoice Registered</h4>
                            <Tag value="IRN ACTIVE" severity="success" class="!text-[8px] !font-black !px-2 !py-0.5" />
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <div class="flex flex-col gap-1 p-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-lg">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Invoice Reference Number (IRN)</span>
                                <span class="font-mono font-bold text-slate-700 dark:text-slate-200 select-all break-all">{{ invoice.einvoice_irn }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1 p-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-lg">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Acknowledgement No</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ invoice.einvoice_ack_no }}</span>
                                </div>
                                <div class="flex flex-col gap-1 p-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-lg">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Acknowledgement Date</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ new Date(invoice.einvoice_ack_date).toLocaleString('en-IN') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- E-Way Bill Section -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-6 space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xs font-black uppercase text-indigo-600 tracking-wider">E-Way Bill Status</h4>
                            <Tag v-if="invoice.eway_bill_no" value="ACTIVE" severity="info" class="!text-[8px] !font-black !px-2 !py-0.5" />
                            <Tag v-else value="NOT GENERATED" severity="secondary" class="!text-[8px] !font-black !px-2 !py-0.5" />
                        </div>

                        <!-- E-Way Bill Details -->
                        <div v-if="invoice.eway_bill_no" class="grid grid-cols-3 gap-4 text-xs">
                            <div class="flex flex-col gap-1 p-2 bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">E-Way Bill No</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ invoice.eway_bill_no }}</span>
                            </div>
                            <div class="flex flex-col gap-1 p-2 bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Generated Date</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ new Date(invoice.eway_bill_date).toLocaleString('en-IN') }}</span>
                            </div>
                            <div class="flex flex-col gap-1 p-2 bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Valid Until</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ new Date(invoice.eway_bill_valid_until).toLocaleString('en-IN') }}</span>
                            </div>
                        </div>

                        <!-- Standalone generation form -->
                        <div v-else class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Generate Standalone E-Way Bill for this IRN</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <BaseSelect v-model="complianceForm.vehicle_no" label="Vehicle Number *" :options="machines" optionLabel="label" optionValue="value" placeholder="Select Vehicle" :error="complianceForm.errors.vehicle_no" filter required />
                                <BaseInputNumber v-model="complianceForm.distance_km" label="Distance in Km *" placeholder="150" :error="complianceForm.errors.distance_km" />
                                <BaseSelect v-model="complianceForm.trans_mode" label="Transport Mode *" :options="[{label: 'Road', value: '1'}, {label: 'Rail', value: '2'}, {label: 'Air', value: '3'}, {label: 'Ship', value: '4'}]" optionLabel="label" optionValue="value" :error="complianceForm.errors.trans_mode" required />
                                <BaseSelect v-model="complianceForm.vehicle_type" label="Vehicle Type *" :options="[{label: 'Regular', value: 'Regular'}, {label: 'ODC (Over Dimensional Cargo)', value: 'ODC'}]" optionLabel="label" optionValue="value" :error="complianceForm.errors.vehicle_type" required />
                            </div>
                            <div class="flex justify-end">
                                <Button 
                                    @click="generateEWayBillOnly"
                                    :disabled="complianceForm.processing"
                                    class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !px-6 !py-2.5 !rounded-lg !flex !items-center !gap-2"
                                >
                                    <ArrowPathIcon v-if="complianceForm.processing" class="w-4 h-4 animate-spin" />
                                    <span>Generate Standalone E-Way Bill</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Cancel E-Way Bill Action -->
                        <div v-if="invoice.eway_bill_no" class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
                            <h5 class="text-[10px] font-black text-rose-500 uppercase tracking-widest">Cancel E-Way Bill</h5>
                            <div class="flex gap-4 items-end">
                                <div class="flex-grow">
                                    <BaseSelect v-model="complianceForm.cancel_reason" label="Reason for Cancellation" :options="[{label: '1: Duplicate', value: '1'}, {label: '2: Order Cancelled', value: '2'}, {label: '3: Data Entry Mistake', value: '3'}, {label: '4: Others', value: '4'}]" optionLabel="label" optionValue="value" :error="complianceForm.errors.cancel_reason" required />
                                </div>
                                <Button 
                                    @click="cancelEWayBillOnly"
                                    :disabled="complianceForm.processing"
                                    class="!bg-rose-600 hover:!bg-rose-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !px-6 !py-2.5 !rounded-lg !flex !items-center !gap-2 h-10 align-middle"
                                >
                                    <ArrowPathIcon v-if="complianceForm.processing" class="w-4 h-4 animate-spin" />
                                    <span>Cancel E-Way Bill</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Cancel E-Invoice Section -->
                    <div class="bg-rose-50/10 dark:bg-rose-950/5 border border-rose-100 dark:border-rose-900/20 rounded-2xl p-6 space-y-4">
                        <h4 class="text-xs font-black uppercase text-rose-600 tracking-wider">Cancel E-Invoice IRN</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <BaseSelect v-model="complianceForm.cancel_reason" label="Cancellation Reason" :options="[{label: '1: Duplicate', value: '1'}, {label: '2: Data Entry Mistake', value: '2'}, {label: '3: Order Cancelled', value: '3'}, {label: '4: Others', value: '4'}]" optionLabel="label" optionValue="value" :error="complianceForm.errors.cancel_reason" required />
                            <BaseInput v-model="complianceForm.cancel_remarks" label="Cancellation Remarks *" placeholder="Explain why cancellation is required" :error="complianceForm.errors.cancel_remarks" />
                        </div>
                        <div class="flex justify-end">
                            <Button 
                                @click="cancelEInvoice"
                                :disabled="complianceForm.processing"
                                class="!bg-rose-600 hover:!bg-rose-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !px-6 !py-2.5 !rounded-lg !flex !items-center !gap-2"
                            >
                                <ArrowPathIcon v-if="complianceForm.processing" class="w-4 h-4 animate-spin" />
                                <span>Cancel E-Invoice IRN</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- QR Code & Printable Preview -->
                <div class="col-span-12 md:col-span-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col items-center justify-center text-center space-y-4 shadow-sm">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Compliance Signed QR Code</span>
                    <div class="p-4 bg-white rounded-2xl border border-slate-100 shadow-inner flex items-center justify-center">
                        <img 
                            :src="`https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(invoice.einvoice_qr_code)}`" 
                            alt="E-Invoice QR Code" 
                            class="w-40 h-40 object-contain"
                        />
                    </div>
                    <p class="text-[9px] font-bold text-slate-400 max-w-[200px] leading-tight">This QR Code is legally required on invoice PDFs. Scan to view signed details.</p>
                </div>
            </div>

            <!-- CANCELLED STATE -->
            <div v-else class="bg-rose-50/30 dark:bg-rose-950/10 border border-rose-100 dark:border-rose-900/30 rounded-2xl p-12 text-center flex flex-col items-center justify-center gap-3">
                <XCircleIcon class="w-12 h-12 text-rose-500" />
                <h4 class="text-sm font-black uppercase text-rose-700 tracking-wider">E-Invoice Cancelled</h4>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest max-w-md">The IRN for this invoice has been voided. A new document must be generated if billing is still active.</p>
            </div>
        </div>
    </div>
</template>
