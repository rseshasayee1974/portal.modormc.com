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
import BaseButton from '@/Components/Base/BaseButton.vue';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

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
    items: [] as any[],
    dispatch_ids: [] as number[]
});

const billingMode = ref('manual'); // 'manual' or 'dispatch'
const dispatchFilters = ref({
    startDate: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
});
const uninvoicedDispatches = ref<any[]>([]);
const selectedDispatches = ref<number[]>([]);
const isFetchingDispatches = ref(false);

const fetchDispatches = async () => {
    if (!form.partner_id) {
        toast.add({ severity: 'warn', summary: 'Missing Customer', detail: 'Please select a customer first', life: 1500 });
        return;
    }
    isFetchingDispatches.value = true;
    try {
        const response = await axios.get(route('invoices.uninvoiced-dispatches'), {
            params: {
                partner_id: form.partner_id,
                start_date: dispatchFilters.value.startDate,
                end_date: dispatchFilters.value.endDate
            }
        });
        uninvoicedDispatches.value = response.data;
        if (uninvoicedDispatches.value.length === 0) {
             toast.add({ severity: 'info', summary: 'No Data', detail: 'No uninvoiced dispatches found for this period.', life: 2000 });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch dispatches', life: 1500 });
    } finally {
        isFetchingDispatches.value = false;
    }
};

const mergeSelectedDispatches = () => {
    if (selectedDispatches.value.length === 0) {
        toast.add({ severity: 'warn', summary: 'Selection Required', detail: 'Please select at least one dispatch record.', life: 1500 });
        return;
    }
    
    const grouped: Record<number, any> = {};
    selectedDispatches.value.forEach(id => {
        const d = uninvoicedDispatches.value.find(x => x.id === id);
        if (!d) return;
        
        const key = d.mixdesign_id;
        const qty = Number(d.delivered_qty || d.batch?.batch_size || 0);
        const rate = Number(d.load_rate || 0);

        if (!grouped[key]) {
            grouped[key] = {
                item_id: d.mixdesign_id,
                mix_design_id: d.mixdesign_id,
                uom_id: d.uom_id || d.mix_design?.unit_id || 1, 
                item_name: d.mix_design?.design_name || 'RMC',
                hsn_code: d.mix_design?.hsn_code || '0',
                tax_id: d.load_tax_id,
                quantity: 0,
                price_unit: rate,
                discount_type: '%',
                discount: 0,
                subtotal: 0,
                tax_amount: 0,
                total: 0
            };
        }
        grouped[key].quantity += qty;
    });
    
    form.items = Object.values(grouped);
    form.dispatch_ids = [...selectedDispatches.value];
    calculateTotals();
    toast.add({ severity: 'success', summary: 'Merged', detail: `${selectedDispatches.value.length} dispatches consolidated.`, life: 1500 });
};

const toggleDispatchSelection = (id: number) => {
    const idx = selectedDispatches.value.indexOf(id);
    if (idx > -1) {
        selectedDispatches.value.splice(idx, 1);
    } else {
        selectedDispatches.value.push(id);
    }
};

const selectAllDispatches = () => {
    if (selectedDispatches.value.length === uninvoicedDispatches.value.length) {
        selectedDispatches.value = [];
    } else {
        selectedDispatches.value = uninvoicedDispatches.value.map(d => d.id);
    }
};

// Initialize with one item
form.items.push(createNewItem());

function createNewItem() {
    return {
        item_id: null,
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
        toast.add({ severity: 'warn', summary: 'Warning', detail: 'At least one item is required', life: 1500 });
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
    // const globalDiscount = form.global_discount_type === '₹' 
    //     ? (Number(form.global_discount) || 0) 
    //     : untaxed * ((Number(form.global_discount) || 0) / 100);
    
    // Calculate global discount (always in Rupees)
    const globalDiscount = Number(form.global_discount) || 0;

    // Add shipping tax if applicables
    if (form.shipping_charges > 0 && form.shipping_tax_id) {
        const sTax = props.taxes.find(t => t.value === form.shipping_tax_id);
        if (sTax) {
            form.amount_tax += form.shipping_charges * (Number(sTax.rate) / 100);
        }
    }

    form.amount_total = untaxed + taxTotal - globalDiscount + (Number(form.adjustment) || 0) + (Number(form.shipping_charges) || 0);
};

// Automatically recalculate totals whenever items, global discount, shipping or adjustments change
watch(
    () => [
        form.items,
        form.global_discount,
        form.shipping_charges,
        form.shipping_tax_id,
        form.adjustment,
    ],
    () => {
        calculateTotals();
    },
    { deep: true, immediate: true }
);

const onMixDesignChange = (index: number) => {
    const item = form.items[index];
    const design = props.mixdesign.find(p => p.value === item.mix_design_id);
    
    if (design) {
        item.item_id = item.mix_design_id;
        item.item_name = design.label;
        item.price_unit = design.rate || 0;
        item.uom_id = design.uom_id || null;
    }
    calculateTotals();
};

watch(() => [form.items, form.adjustment, form.shipping_charges], calculateTotals, { deep: true });

const resetForm = () => {
    form.reset();
    form.items = [createNewItem()];
    form.dispatch_ids = [];
    selectedDispatches.value = [];
    uninvoicedDispatches.value = [];
};

const submit = () => {
    // Check item quantity client-side before sending
    for (let i = 0; i < form.items.length; i++) {
        const item = form.items[i];
        if (!item.quantity || Number(item.quantity) < 0.01) {
            toast.add({ 
                severity: 'error', 
                summary: 'Validation Error', 
                detail: `Item #${i + 1} must have a quantity of at least 0.01`, 
                life: 3000 
            });
            return;
        }
    }

    form.post(route('invoices.store'), {
        onSuccess: () => {
            resetForm();
            router.reload({
                onSuccess: () => {
                    if (billingMode.value === 'dispatch' && form.partner_id) {
                        fetchDispatches();
                    }
                }
            });
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice processed successfully', life: 1500 });
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
        
        toast.add({ severity: 'success', summary: 'Partner Created', detail: `${name} is now available`, life: 1500 });

        router.reload({
            only: ['patrons'],
            onSuccess: () => {
                form.partner_id = newPatron.id;
            }
        });
    } catch (error: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Failed to create partner', life: 1500 });
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
        <div class="create-panel__header" @click="toggle">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <div class="create-panel__icon">
                        <DocumentChartBarIcon class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div class="text-left">
                        <p class="text-xs font-semibold text-gray-700 uppercase ">Generate Invoices</p>
                        <p class="text-[11px] text-gray-400 font-medium mt-0.5">Manual Invoice & Logistics Reconciliation Module</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 w-fit" @click.stop>
                    <div class="flex bg-white rounded-xl p-1 shadow-sm border border-slate-200">
                        <button 
                            type="button" 
                            @click="billingMode = 'manual'" 
                            :class="billingMode === 'manual' ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:text-slate-600'"
                            class="flex items-center gap-2 px-6 py-2 rounded-lg text-[10px] font-black uppercase tracking-tighter transition-all"
                        >
                            <DocumentTextIcon class="w-4 h-4" />
                            Manual Entry
                        </button>
                        <button 
                            type="button" 
                            @click="billingMode = 'dispatch'" 
                            :class="billingMode === 'dispatch' ? 'bg-emerald-600 text-white shadow-lg' : 'text-slate-400 hover:text-slate-600'"
                            class="flex items-center gap-2 px-6 py-2 rounded-lg text-[10px] font-black uppercase tracking-tighter transition-all"
                        >
                            <TruckIcon class="w-4 h-4" />
                            Dispatch-Wise
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Transition name="panel-slide">
            <div   class="create-panel__body">
                <!-- Mode Selector -->
                

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Top Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 px-1">
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
                             required
                        />
                        <!-- <BaseSelect 
                            v-model="form.invoice_type" 
                            label="Document Type"
                            :options="invoiceTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Type"
                            :error="form.errors.invoice_type"
                            required
                        /> -->
                        
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

                    <!-- Dispatch Selection Area (Only in Dispatch Mode) -->
                    <div v-if="billingMode === 'dispatch'" class="bg-emerald-50/30 p-6 rounded-2xl border border-emerald-100 shadow-sm animate-in fade-in slide-in-from-top-4">
                        <div class="flex flex-col lg:flex-row lg:items-end gap-4 mb-6">
                            <BaseDatePicker v-model="dispatchFilters.startDate" label="From Date" size="small" class="w-44" />
                            <BaseDatePicker v-model="dispatchFilters.endDate" label="To Date" size="small" class="w-44" />
                            <BaseButton variant="filled" severity="info" label="Find Dispatches" :loading="isFetchingDispatches" @click="fetchDispatches" class="!bg-emerald-600 !border-emerald-600 !h-10 !px-6 text-[10px] uppercase font-black  shadow-md" />
                            
                            <div class="ml-auto" v-if="uninvoicedDispatches.length > 0">
                                <BaseButton variant="filled" severity="primary" label="Merge Into Invoice" icon="pi pi-sync" @click="mergeSelectedDispatches" :disabled="selectedDispatches.length === 0" class="!bg-indigo-600 !h-10 !px-6 text-[10px] uppercase font-black  shadow-md" />
                            </div>
                        </div>

                        <div v-if="uninvoicedDispatches.length > 0" class="overflow-hidden border border-emerald-100 rounded-xl bg-white shadow-inner">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-emerald-50/50 text-[10px] font-black uppercase text-emerald-700  border-b border-emerald-100">
                                    <tr>
                                        <th class="p-4 w-12 text-center">
                                            <input type="checkbox" :checked="selectedDispatches.length === uninvoicedDispatches.length" @change="selectAllDispatches" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                        </th>
                                        <th class="p-4">Ticket</th>
                                        <th class="p-4 text-center">Date</th>
                                        <th class="p-4">Grade</th>
                                        <th class="p-4">Vehicle</th>
                                        <th class="p-4 text-right pr-8">Qty (m³)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-emerald-50">
                                    <tr v-for="d in uninvoicedDispatches" :key="d.id" class="hover:bg-emerald-50/30 transition-colors cursor-pointer" @click="toggleDispatchSelection(d.id)">
                                        <td class="p-4 text-center" @click.stop>
                                            <input type="checkbox" :value="d.id" v-model="selectedDispatches" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                        </td>
                                        <td class="p-4 font-black text-emerald-700">{{ d.dispatch_no }}</td>
                                        <td class="p-4 text-center text-slate-500">{{ (d.dispatch_time || d.dispatch_date || d.created_at) ? new Date(d.dispatch_time || d.dispatch_date || d.created_at).toLocaleDateString('en-GB') : '-' }}</td>
                                        <td class="p-4  text-slate-700">{{ d.mix_design?.design_name || 'RMC' }}</td>
                                        <td class="p-4 font-medium text-slate-500">{{ d.truck?.registration || '-' }}</td>
                                        <td class="p-4 text-right font-black text-slate-900 pr-8">{{ Number(d.delivered_qty || d.batch?.batch_size || 0).toFixed(3) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="!isFetchingDispatches" class="py-12 text-center border-2 border-dashed border-emerald-100 rounded-xl">
                            <TruckIcon class="w-10 h-10 text-emerald-200 mx-auto mb-3" />
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">No Pending Dispatches Found</p>
                            <p class="text-[11px] text-slate-300 mt-1 font-medium">Select a customer and date range above</p>
                        </div>
                    </div>

                    <!-- Items Table Area -->
                    <div class="mt-6 border border-slate-100 rounded-sm shadow-sm overflow-hidden bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[900px]">
                                <thead class="bg-slate-50 border-b border-slate-100 uppercase tracking-tighter text-[10px] font-semibold text-slate-500">
                                    <tr>
                                        <th class="px-4 py-1" style="width: 250px;">Product / Service</th>
                                        <!-- <th class="px-4 py-1" style="width: 200px;">Description</th> -->
                                        <th class="px-4 py-1 text-center" style="width: 100px;">Qty</th>
                                        <th class="px-4 py-1 text-center" style="width: 100px;">UOM</th>
                                        <th class="px-4 py-1 text-center" style="width: 140px;">Rate</th>
                                        <th class="px-4 py-1 text-center" style="width: 120px;">TAX</th>
                                        <th class="px-4 py-1 text-center" style="width: 180px;">Discount</th>
                                        <th class="px-4 py-1 text-right">Net Amount</th>
                                        <th class="px-1 py-1" style="width: 50px;">
                                            <button type="button" @click="addItem" class="text-indigo-600 font-semibold hover:text-indigo-700">
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
                                            <BaseInputNumber v-model="item.quantity" :minFractionDigits="2" size="small" :error="form.errors[`items.${index}.quantity`]" />
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
                                            <div class="flex flex-row items-center justify-center gap-1">
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
                                                <div v-if="item.discount > 0" class="text-[10px] text-right text-rose-500 font-semibold px-1">
                                                    -{{ (item.discount_type === '₹' ? item.discount : (item.quantity * item.price_unit * (item.discount / 100))).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
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
                        <div class="space-y-4 ">
                            <div class="field-group">
                                <label class="text-[10px]  font-semibold text-slate-600  block">Invoice Remarks / Period Info</label>
                                <Textarea v-model="form.period" rows="3" placeholder="Billing period, reference notes..." class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <BaseInput v-model="form.ref_title" label="Reference Title" placeholder="PO Ref, etc." />
                            </div>
                        </div>

                        <div class="bg-indigo-50/30 rounded-xl p-4 border border-indigo-100 shadow-inner">
                            <div class="space-y-4">
                                <div class="flex justify-between items-center text-[11px]  text-slate-600 uppercase ">
                                    <span>Subtotal (Untaxed)</span>
                                    <span class="text-slate-900">{{ form.amount_untaxed.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[11px]  text-slate-600 uppercase ">
                                    <span>Tax Amount (+)</span>
                                    <span class="text-slate-900">{{ form.amount_tax.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px]  text-slate-600 uppercase ">Global Discount (-)</span>
                                    <BaseInputNumber v-model="form.global_discount" size="small" class="w-28" />
                                </div>
                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px]  text-slate-600 uppercase ">Shipping Charges (+)</span>
                                    <BaseInputNumber v-model="form.shipping_charges" size="small" class="w-28" />
                                </div>
                                <div class="flex justify-between items-center gap-4 border-slate-200/50 pt-4">
                                    <span class="text-[11px]  text-slate-600 uppercase ">Round Off / Adj (+)</span>
                                    <BaseInputNumber v-model="form.adjustment" size="small" class="w-28" />
                                </div>

                                <div class="flex justify-between items-center border-slate-200">
                                    <div class="flex flex-col">
                                        <span class="text-[14px] text-indigo-700 uppercase ">Total Amount</span>
                                    </div>
                                    <div class="text-right flex items-baseline gap-1">
                                        <span class="text-xs text-indigo-700 font-black">₹</span>
                                        <span class=" font-black text-slate-800 tracking-tight">
                                             {{ form.amount_total.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="pt-4">
                                    <BaseFormActions
                                        label="Invoice"
                                        :loading="form.processing"
                                        @submit="submit"
                                        @reset="resetForm()"
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
