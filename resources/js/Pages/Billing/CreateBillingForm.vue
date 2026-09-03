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
    Square3Stack3DIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps<{
    patrons: any[];
    taxes: any[]; 
    accounts: any[];
    products: any[];
    units: any[];
    instant_invoice_patron: number | boolean;
    next_invoice_number?: string;
    next_invoice_details?: {
        prefix?: string;
        next_number?: string | number;
        full_number?: string;
    };
}>();

const page = usePage();
const toast = useToast();
const isOpen = ref(true);

const form = useForm({
    partner_id: null,
    account_id: null,
    journal_id: null,
    invoice_type: 'bill',
    invoice_label: 'Purchase Bill',
    prefix: props.next_invoice_details?.prefix || '',
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
    round_off: 0,
    shipping_charges: 0,
    shipping_tax_id: null,
    amount_untaxed: 0,
    amount_tax: 0,
    amount_total: 0,
    items: [] as any[],
    purchase_order_ids: [] as number[],
    startDate: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
});

const billingMode = ref('manual'); // 'manual' or 'po'
const unbilledPOs = ref<any[]>([]);
const selectedPOs = ref<number[]>([]);
const isFetchingPOs = ref(false);

const fetchUnbilledPOs = async () => {
    if (!form.partner_id) {
        toast.add({ severity: 'warn', summary: 'Missing Vendor', detail: 'Please select a vendor first', life: 1500 });
        return;
    }
    isFetchingPOs.value = true;
    try {
        const response = await axios.get(route('billings.unbilled-pos'), {
            params: {
                partner_id: form.partner_id,
                start_date: form.startDate,
                end_date: form.endDate
            }
        });
        unbilledPOs.value = response.data;
        if (unbilledPOs.value.length === 0) {
             toast.add({ severity: 'info', summary: 'No Data', detail: 'No unbilled purchase orders found for this period.', life: 2000 });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch purchase orders', life: 1500 });
    } finally {
        isFetchingPOs.value = false;
    }
};

const getPOTotal = (po: any) => {
    if (po.total_amount !== undefined && po.total_amount !== null && !isNaN(Number(po.total_amount))) {
        return Number(po.total_amount);
    }
    if (po.amount_total !== undefined && po.amount_total !== null && !isNaN(Number(po.amount_total))) {
        return Number(po.amount_total);
    }
    if (po.items && Array.isArray(po.items)) {
        return po.items.reduce((sum: number, item: any) => {
            const qty = Number(item.product_quantity || item.quantity || 0);
            const price = Number(item.unit_price || item.price_unit || 0);
            return sum + (qty * price);
        }, 0);
    }
    return 0;
};

const mergeSelectedPOs = () => {
    if (selectedPOs.value.length === 0) {
        toast.add({ severity: 'warn', summary: 'Selection Required', detail: 'Please select at least one purchase order.', life: 1500 });
        return;
    }
    
    const grouped: Record<number, any> = {};
    selectedPOs.value.forEach(id => {
        const po = unbilledPOs.value.find(x => x.id === id);
        if (!po || !po.items) return;
        
        po.items.forEach((item: any) => {
            const key = item.product_id;
            if (!grouped[key]) {
                grouped[key] = {
                    item_id: item.product_id, 
                    uom_id: item.product_uom || item.uom_id,
                    item_name: item.product?.title || item.description || 'Product',
                    hsn_code: item.hsn_code || '',
                    tax_id: item.tax_id || null,
                    quantity: 0,
                    price_unit: Number(item.unit_price || item.price_unit || 0),
                    discount_type: item.discount_type === '%' ? '%' : '₹',
                    discount: Number(item.discount_amount || item.discount || 0),
                    subtotal: 0,
                    tax_amount: 0,
                    total: 0
                };
            }
            grouped[key].quantity += Number(item.product_quantity || item.quantity || 0);
        });
    });
    
    form.items = Object.values(grouped);
    form.purchase_order_ids = [...selectedPOs.value];
    calculateTotals();
    toast.add({ severity: 'success', summary: 'Merged', detail: `${selectedPOs.value.length} POs consolidated.`, life: 1500 });
};

const togglePOSelection = (id: number) => {
    const idx = selectedPOs.value.indexOf(id);
    if (idx > -1) {
        selectedPOs.value.splice(idx, 1);
    } else {
        selectedPOs.value.push(id);
    }
};

const selectAllPOs = () => {
    if (selectedPOs.value.length === unbilledPOs.value.length) {
        selectedPOs.value = [];
    } else {
        selectedPOs.value = unbilledPOs.value.map(p => p.id);
    }
};

// Initialize with one item
form.items.push(createNewItem());

function createNewItem() {
    return {
        item_id: null,
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
    
    // Calculate global discount (Rupee amount)
    const globalDiscount = Number(form.global_discount) || 0;

    // Add shipping tax if applicable
    if (form.shipping_charges > 0 && form.shipping_tax_id) {
        const sTax = props.taxes.find(t => t.value === form.shipping_tax_id);
        if (sTax) {
            form.amount_tax += form.shipping_charges * (Number(sTax.rate) / 100);
        }
    }

    const rawTotal = untaxed + taxTotal - globalDiscount + (Number(form.adjustment) || 0) + (Number(form.shipping_charges) || 0);
    const calculatedRoundOff = Number((Math.round(rawTotal) - rawTotal).toFixed(2));
    if (form.round_off === 0 || form.round_off === null || form.round_off === undefined) {
        form.round_off = calculatedRoundOff;
    }
    form.amount_total = Number((rawTotal + (Number(form.round_off) || 0)).toFixed(2));
};

const onProductChange = (index: number) => {
    const item = form.items[index];
    const product = props.products.find(p => p.id === item.item_id);
    
    if (product) {
        item.item_name = product.title;
        item.price_unit = product.purchase_price || 0;
        item.uom_id = product.unit_id || null;
    }
    calculateTotals();
};

watch(() => [
    form.items, 
    form.adjustment, 
    form.shipping_charges,
    form.global_discount,
    form.global_discount_type,
    form.shipping_tax_id
], calculateTotals, { deep: true });

const isManualInvoiceNumber = ref(false);
const isCheckingInvoiceNumber = ref(false);
const invoiceNumberStatus = ref<{
    checked: boolean;
    exists: boolean;
    message: string;
}>({
    checked: false,
    exists: false,
    message: ''
});
let checkDebounceTimeout: any = null;

const validateInvoiceNumber = async () => {
    if (!isManualInvoiceNumber.value || !form.invoice_number?.trim()) {
        invoiceNumberStatus.value = { checked: false, exists: false, message: '' };
        return;
    }

    isCheckingInvoiceNumber.value = true;
    try {
        const response = await axios.get(route('invoices.check-number'), {
            params: {
                invoice_number: form.invoice_number.trim(),
                prefix: form.prefix || '',
                account_id: form.account_id || '',
                invoice_type: 'bill'
            }
        });
        invoiceNumberStatus.value = {
            checked: true,
            exists: Boolean(response.data.exists),
            message: response.data.message || ''
        };
    } catch (error) {
        console.error('Failed to validate bill number', error);
    } finally {
        isCheckingInvoiceNumber.value = false;
    }
};

const allowOnlyInvoiceChars = (e: KeyboardEvent) => {
    const char = e.key;
    if (!/^[0-9A-Za-z\-_]$/.test(char) && !['Backspace', 'ArrowLeft', 'ArrowRight', 'Tab', 'Delete', 'Enter'].includes(char)) {
        e.preventDefault();
    }
};

const onInvoiceNumberInput = () => {
    // Strip prefix if user pastes the full number
    if (form.prefix && form.invoice_number && form.invoice_number.startsWith(form.prefix)) {
        form.invoice_number = form.invoice_number.substring(form.prefix.length);
    }
    invoiceNumberStatus.value = { checked: false, exists: false, message: '' };
    if (checkDebounceTimeout) clearTimeout(checkDebounceTimeout);
    checkDebounceTimeout = setTimeout(() => {
        validateInvoiceNumber();
    }, 350);
};

const autoInvoicePreview = ref(props.next_invoice_number || props.next_invoice_details?.full_number || '');

const onAccountChange = async () => {
    if (!form.account_id) return;
    try {
        const response = await axios.get(route('invoices.next-number'), {
            params: {
                account_id: form.account_id,
                invoice_type: 'bill'
            }
        });
        if (response.data?.prefix) {
            form.prefix = response.data.prefix;
        }
        if (response.data?.full_number) {
            autoInvoicePreview.value = response.data.full_number;
        }
        // If manual invoice number is entered, re-validate with the new prefix
        if (isManualInvoiceNumber.value && form.invoice_number?.trim()) {
            validateInvoiceNumber();
        }
    } catch (err) {
        console.error('Failed to get sequence for account', err);
    }
};

watch(() => form.account_id, () => {
    onAccountChange();
});

watch(isManualInvoiceNumber, (manual) => {
    if (!manual) {
        form.invoice_number = '';
        invoiceNumberStatus.value = { checked: false, exists: false, message: '' };
    } else {
        if (!form.prefix) {
            if (props.next_invoice_details?.prefix) {
                form.prefix = props.next_invoice_details.prefix;
            }
        }
    }
});

const resetForm = () => {
    form.reset();
    form.items = [createNewItem()];
    selectedPOs.value = [];
    unbilledPOs.value = [];
    isManualInvoiceNumber.value = false;
    invoiceNumberStatus.value = { checked: false, exists: false, message: '' };
};

const submit = async () => {
    // Validate manual invoice number if manual entry is enabled
    if (isManualInvoiceNumber.value) {
        if (!form.invoice_number?.trim()) {
            toast.add({ 
                severity: 'error', 
                summary: 'Bill Number Required', 
                detail: 'Please enter a manual bill number or switch to auto generation.', 
                life: 3000 
            });
            return;
        }

        if (!invoiceNumberStatus.value.checked || isCheckingInvoiceNumber.value) {
            await validateInvoiceNumber();
        }

        if (invoiceNumberStatus.value.exists) {
            toast.add({ 
                severity: 'error', 
                summary: 'Duplicate Bill', 
                detail: invoiceNumberStatus.value.message || 'Bill number already exists. Duplicates are not allowed.', 
                life: 4000 
            });
            return;
        }
    }

    form.post(route('billings.store'), {
        onSuccess: () => {
            resetForm();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Bill processed successfully', life: 1500 });
        },
        onError: (err: any) => {
            if (err.invoice_number) {
                invoiceNumberStatus.value = {
                    checked: true,
                    exists: true,
                    message: err.invoice_number
                };
                toast.add({
                    severity: 'error',
                    summary: 'Duplicate Bill',
                    detail: err.invoice_number,
                    life: 4000
                });
            }
        }
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
    // { label: 'Sales Invoice', value: 'sales' },
    { label: 'Purchase Invoice', value: 'purchase' },
    // { label: 'Proforma Invoice', value: 'proforma' },
    // { label: 'Credit Note', value: 'credit_note' },
    // { label: 'Debit Note', value: 'debit_note' },
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
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Generate Bills</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Purchase Bill & Expense Reconciliation Module</p>
                </div>
            </div>
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Top Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 px-1">
                        <div class="col-span-12 md:col-span-3">
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
                        </div>

                        <div class="col-span-12 md:col-span-3">
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
                        </div>

                        <div class="col-span-12 md:col-span-2">
                            <BaseDatePicker
                                v-model="form.invoice_date"
                                label="Bill Date"
                                required
                                :error="form.errors.invoice_date"
                            />
                        </div>

                        <!-- Manual Bill Number & Real-Time Axios Validation -->
                        <div class="col-span-12 md:col-span-4">
                            <div class="flex flex-col justify-between h-full">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">
                                        Bill #
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input 
                                            type="checkbox" 
                                            v-model="isManualInvoiceNumber" 
                                            class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" 
                                        />
                                        <span class="text-[11px] font-semibold" :class="isManualInvoiceNumber ? 'text-indigo-700 font-bold' : 'text-slate-500'">
                                            Manual Enter
                                        </span>
                                    </label>
                                </div>

                                <!-- Auto-generated Preview Mode -->
                                <div v-if="!isManualInvoiceNumber" class="flex items-center gap-2 h-10 px-3 bg-slate-50 border border-dashed border-slate-300 rounded-md text-xs text-slate-600">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-200 text-slate-700">
                                        AUTO
                                    </span>
                                    <span class="font-mono text-slate-600 font-semibold truncate" title="Will be auto-generated upon saving">
                                        {{ autoInvoicePreview || next_invoice_number || (form.prefix ? form.prefix + 'XXXX' : 'Auto Sequence') }}
                                    </span>
                                    <span class="ml-auto text-[10px] text-slate-400 italic">Auto-generated</span>
                                </div>

                                <!-- Manual Entry with Axios Real-Time Validation -->
                                <div v-else class="space-y-1">
                                    <div class="flex items-center gap-1.5">
                                        <!-- Locked System Prefix Display (Non-editable, tamper-proof) -->
                                        <div 
                                            class="w-28 h-10 px-2.5 rounded-md border border-slate-300 text-xs font-mono font-bold text-slate-700 bg-slate-100 flex items-center justify-center select-none cursor-not-allowed pointer-events-none truncate shadow-inner"
                                            :title="'System Prefix: ' + (form.prefix || 'Auto')"
                                        >
                                            {{ form.prefix || 'Auto' }}
                                        </div>
                                        <div class="relative flex-1">
                                            <input 
                                                type="text" 
                                                v-model="form.invoice_number" 
                                                @input="onInvoiceNumberInput"
                                                @keypress="allowOnlyInvoiceChars"
                                                @blur="validateInvoiceNumber"
                                                placeholder="Enter Bill #" 
                                                class="w-full h-10 px-3 pr-8 rounded-md border text-xs font-mono font-bold transition-all focus:outline-none focus:ring-1"
                                                :class="[
                                                    invoiceNumberStatus.checked && invoiceNumberStatus.exists
                                                        ? 'border-red-500 text-red-700 bg-red-50/40 focus:ring-red-500 focus:border-red-500'
                                                        : invoiceNumberStatus.checked && !invoiceNumberStatus.exists
                                                        ? 'border-emerald-500 text-emerald-800 bg-emerald-50/30 focus:ring-emerald-500 focus:border-emerald-500'
                                                        : 'border-slate-300 text-slate-800 bg-white focus:ring-indigo-500 focus:border-indigo-500'
                                                ]"
                                            />
                                            <!-- Status Icon Indicator -->
                                            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                                                <svg v-if="isCheckingInvoiceNumber" class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                </svg>
                                                <span v-else-if="invoiceNumberStatus.checked && !invoiceNumberStatus.exists" class="text-emerald-600 font-bold text-sm" title="Available">
                                                    ✓
                                                </span>
                                                <span v-else-if="invoiceNumberStatus.checked && invoiceNumberStatus.exists" class="text-red-600 font-bold text-sm" title="Duplicate Not Allowed">
                                                    ✕
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Feedback Status Message -->
                                    <div class="text-[11px] leading-tight min-h-[16px]">
                                        <span v-if="isCheckingInvoiceNumber" class="text-indigo-600 flex items-center gap-1 font-medium">
                                            Checking availability...
                                        </span>
                                        <span v-else-if="invoiceNumberStatus.checked && invoiceNumberStatus.exists" class="text-red-600 font-bold flex items-center gap-1">
                                            <span>⚠</span> {{ invoiceNumberStatus.message }}
                                        </span>
                                        <span v-else-if="invoiceNumberStatus.checked && !invoiceNumberStatus.exists" class="text-emerald-700 font-bold flex items-center gap-1">
                                            <span>✓</span> {{ invoiceNumberStatus.message }}
                                        </span>
                                        <span v-else-if="form.errors.invoice_number" class="text-red-600 font-bold flex items-center gap-1">
                                            <span>⚠</span> {{ form.errors.invoice_number }}
                                        </span>
                                        <span v-else class="text-slate-400 text-[10px]">
                                            Validated in real-time against plant database
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Mode Selection -->
                    <div class="flex gap-4 p-1">
                        <button 
                            type="button"
                            @click="billingMode = 'manual'"
                            :class="billingMode === 'manual' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                            class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all"
                        >
                            Manual Entry
                        </button>
                        <button 
                            type="button"
                            @click="billingMode = 'po'"
                            :class="billingMode === 'po' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                            class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2"
                        >
                            <Square3Stack3DIcon class="w-3.5 h-3.5" />
                            Pull from Purchase Orders
                        </button>
                    </div>

                    <!-- PO Selection Area -->
                    <div v-if="billingMode === 'po'" class="bg-slate-50/50 rounded-2xl p-6 border border-slate-100 space-y-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <BaseDatePicker v-model="form.startDate" label="Start Date" size="small" />
                            <BaseDatePicker v-model="form.endDate" label="End Date" size="small" />
                            <BaseButton 
                                label="Fetch Orders" 
                                @click="fetchUnbilledPOs" 
                                :loading="isFetchingPOs"
                                size="small"
                                variant="secondary"
                            />
                        </div>

                        <div v-if="unbilledPOs.length > 0" class="space-y-4">
                            <div class="flex items-center justify-between px-2">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    {{ unbilledPOs.length }} Orders Found | {{ selectedPOs.length }} Selected
                                </p>
                                <button type="button" @click="selectAllPOs" class="text-[10px] font-black text-indigo-600 uppercase hover:underline">
                                    {{ selectedPOs.length === unbilledPOs.length ? 'Deselect All' : 'Select All' }}
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-60 overflow-y-auto p-1">
                                <div 
                                    v-for="po in unbilledPOs" 
                                    :key="po.id"
                                    @click="togglePOSelection(po.id)"
                                    :class="selectedPOs.includes(po.id) ? 'border-indigo-500 bg-indigo-50/50' : 'border-slate-100 bg-white'"
                                    class="p-3 rounded-xl border-2 cursor-pointer hover:border-indigo-300 transition-all group"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-[10px] font-black text-slate-800 uppercase">{{ po.po_number }}</p>
                                            <p class="text-[9px] font-bold text-slate-400">{{ new Date(po.date_order).toLocaleDateString() }}</p>
                                        </div>
                                        <div 
                                            :class="selectedPOs.includes(po.id) ? 'bg-indigo-600 border-indigo-600' : 'bg-white border-slate-200'"
                                            class="w-4 h-4 rounded border flex items-center justify-center transition-colors"
                                        >
                                            <CheckCircleIcon v-if="selectedPOs.includes(po.id)" class="w-3 h-3 text-white" />
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                         <Tag v-for="item in po.items" :key="item.id" :value="`${item.product_quantity || item.quantity || 0} ${item.uom?.unit_code || item.uom?.unit_name || ''}`" class="!text-[7px] !px-1" severity="secondary" />
                                     </div>
                                     <div class="mt-2 text-right">
                                         <span class="text-xs font-black text-slate-700">₹ {{ getPOTotal(po).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                     </div>
                                </div>
                            </div>

                            <div class="flex justify-center pt-2">
                                <BaseButton 
                                    label="Merge & Consolidate Items" 
                                    @click="mergeSelectedPOs" 
                                    variant="primary"
                                    class="!px-8"
                                    icon="Square3Stack3DIcon"
                                />
                            </div>
                        </div>
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
                                                v-model="item.item_id" 
                                                :options="products" 
                                                optionLabel="title" 
                                                optionValue="id" 
                                                placeholder="Select Product" 
                                                filter
                                                :error="form.errors[`items.${index}.item_id`]"
                                                @change="onProductChange(index)"
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
                                                :error="form.errors[`items.${index}.uom_id`]"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber v-model="item.price_unit" :minFractionDigits="2" size="small" inputClass="font-semibold text-indigo-600" :error="form.errors[`items.${index}.price_unit`]" />
                                        </td>
                                        <td class="p-2">
                                            <BaseSelect 
                                                v-model="item.tax_id" 
                                                :options="taxes" 
                                                optionLabel="label" 
                                                optionValue="value" 
                                                placeholder="Tax" 
                                                filter
                                                :error="form.errors[`items.${index}.tax_id`]"
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
                                    <BaseInputNumber v-model="form.global_discount" size="small" class="w-28" />
                                </div>
                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">Shipping Charges (+)</span>
                                    <BaseInputNumber v-model="form.shipping_charges" size="small" class="w-28" />
                                </div>
                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">Adjustment (+)</span>
                                    <BaseInputNumber v-model="form.adjustment" size="small" class="w-28" />
                                </div>
                                <div class="flex justify-between items-center gap-4 border-t border-slate-200/50 pt-4">
                                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">Round Off (+/-)</span>
                                    <BaseInputNumber v-model="form.round_off" size="small" class="w-28" :minFractionDigits="2" :maxFractionDigits="2" />
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
                                        label="Commit Bill"
                                        :loading="form.processing"
                                        :disabled="isManualInvoiceNumber && (invoiceNumberStatus.exists || isCheckingInvoiceNumber || !form.invoice_number?.trim())"
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
