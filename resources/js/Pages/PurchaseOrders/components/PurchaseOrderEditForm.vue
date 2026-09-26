<script setup lang="ts">
import { entityToday, entityLocaleDateTime } from '@/Utils/entityDateTime';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    TrashIcon,
    PlusIcon,
    ChevronDownIcon,
    ArchiveBoxIcon,
    ClockIcon,
    CurrencyRupeeIcon,
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    WalletIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';
import { useWeighbridge } from '@/Composables/useWeighbridge';
import Dialog from 'primevue/dialog';

const { isScaleConnected, captureWeight } = useWeighbridge();

// Components
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Textarea from 'primevue/textarea';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    form: any;
    purchaseOrder?: any;
    vendors: any[];
    currencies: any[];
    taxes: any[];
    products: any[];
    productUnits: any[];
    accounts: any[];
    onProductChange: (index: number) => void;
    calculateItemTotals: (index: number) => void;
    addItem: () => void;
    removeItem: (index: number) => void;
    submit: () => void;
    isReceived?: boolean;
}>();
const isOpen = ref(true);
const emit = defineEmits(['billing-updated']);
const expandedIndex = ref<number | null>(0);
const showBillDialog = ref(false);
const showBillingPanel = ref(true);

const billForm = ref({
    account_id: null,
    invoice_date: entityToday(),
    due_date: props.form.due_date || entityToday(),
    items: [] as { order_item_id: number; unit_price: number }[]
});
const billErrors = ref<Record<string, string>>({});
const billableItems = computed(() => (props.purchaseOrder?.items || []).filter((item: any) => Number(item.received_quantity) > Number(item.invoiced_quantity)));
const billingQuantity = (item: any) => Number(item?.billing_preview?.quantity ?? Math.max(0, Number(item?.received_quantity || 0) - Number(item?.invoiced_quantity || 0)));
const billingUom = (item: any) => item?.billing_preview?.converted_uom ?? item?.uom?.unit_code;
const conversionText = (item: any) => (item?.converted_receipts || []).filter((r: any) => Number(r.quantity) > 0).map((r: any) => `${Number(r.quantity).toFixed(4)} ${r.converted_uom || ''}`).join(', ') || '—';
const hasConversionError = computed(() => billableItems.value.some((item: any) => item.billing_preview?.error));

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const toggleRow = (index: number, item: any) => {
    expandedIndex.value = expandedIndex.value === index ? null : index;
};


const vendorOptions = computed(() => props.vendors?.map(v => ({ label: v.legal_name, value: v.id })) || []);
// const productOptions = computed(() => props.products?.map(p => ({ label: p.title, value: p.id })) || []);
const unitOptions = computed(() => props.productUnits?.map(u => ({ label: u.unit_code, value: u.id })) || []);
const taxOptions = computed(() => props.taxes?.map(t => ({ label: t.tax_name, value: t.id })) || []);
const discountTypeOptions = [{ label: '%', value: '%' }, { label: '₹', value: '₹' }];

const stateOptions = [
    { label: 'Draft', value: 'Draft' },
    { label: 'Approved', value: 'Approved' },
    { label: 'Billed', value: 'Billed' },
    { label: 'Cancelled', value: 'Cancelled' }
];

const receiptStatusOptions = [
    { label: 'None', value: 0 },
    { label: 'Partial', value: 1 },
    { label: 'Full', value: 2 }
];

const approveStatusOptions = [
    { label: 'Pending', value: 0 },
    { label: 'Approved', value: 1 },
    { label: 'Rejected', value: 2 }
];

const handleGenerateBill = () => {
    billErrors.value = {};
    billForm.value.items = billableItems.value.map((item: any) => ({ order_item_id: item.id, unit_price: Number(item.unit_price) }));
    showBillDialog.value = true;
};

const executeBillGeneration = () => {
    if (!billForm.value.account_id) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Please select a ledger account', showConfirmButton: false, timer: 1500 });
        return;
    }

    router.post(route('purchaseorder.generate-bill', props.form.id), billForm.value, {
        onError: (errors) => { billErrors.value = errors; },
        onSuccess: () => {
            showBillDialog.value = false;
            emit('billing-updated');
        },
        preserveScroll: true
    });
};

const handleDeleteBill = (billId: number) => {
    Swal.fire({
        title: 'Void Purchase Bill?',
        text: 'This will void this bill and make its quantity available for billing again. Are you sure?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Void'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('purchaseorder.delete-bill', props.form.id), {
                data: { bill_id: billId },
                onSuccess: () => emit('billing-updated'),
                preserveScroll: true
            });
        }
    });
};


</script>

<template>
    <div class="p-4 lg:p-4" >
        <!-- Header -->
        <div class="flex justify-end items-center mb-6">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Ref No:</span>
                <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 font-mono tracking-wider">{{ form.po_number }}</span>
            </div>
        </div>

        <div class="">
            <form @submit.prevent="submit" class="space-y-6">
                    

                    
                    <!-- General Info Grid -->
                    <div class="grid grid-cols-12 gap-4">
                        <BaseSelect
                            v-model="form.vendor_id"
                            :options="vendorOptions"
                            label="Vendor / Supplier"
                            optionLabel="label" 
                            optionValue="value"
                            placeholder="Select Vendor" 
                            filter
                            class="col-span-12 md:col-span-3"
                            :disabled="isReceived"
                        />
                        <div class="col-span-12 md:col-span-3">
                        <BaseDatePicker 
                            v-model="form.date_order" 
                            label="Order Date"
                            dateFormat="yy-mm-dd"  
                            :disabled="isReceived"
                        />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker 
                                v-model="form.billed_date" 
                                label="Billed Date"
                                dateFormat="yy-mm-dd"  
                                :disabled="isReceived"
                            />
                        </div>
                        <BaseSelect 
                            v-model="form.state" 
                            :options="stateOptions" 
                            label="Order Status"
                            optionLabel="label" 
                            optionValue="value" 
                            class="col-span-12 md:col-span-3"
                            :disabled="isReceived"
                        />

                        <!-- <BaseSelect 
                            v-model="form.receipt_status" 
                            :options="receiptStatusOptions" 
                            label="Receipt Status"
                            optionLabel="label" 
                            optionValue="value" 
                            class="col-span-12 md:col-span-2"
                            disabled
                        /> -->

                        <!-- <BaseSelect 
                            v-model="form.approve_status" 
                            :options="approveStatusOptions" 
                            label="Approve Status"
                            optionLabel="label" 
                            optionValue="value" 
                            class="col-span-12 md:col-span-2"
                            :disabled="isReceived"
                        /> -->
                    </div>

                    <!-- Items Table -->
                    <div class="mt-8">
                        <div v-if="!isReceived" class="flex items-center justify-between mb-3 px-1">
                            <h3 class="text-[10px] font-semibold text-indigo-600 uppercase tracking-[0.2em]">Procurement Lines</h3>
                            <BaseButton icon="pi pi-plus" class="h-8 w-8" severity="primary" variant="filled" @click="addItem" />
                        </div>
                                           
                        <div class="overflow-x-auto overflow-hidden">
                            <table class=" text-left border-collapse w-full">
                                <thead class="bg-slate-50/80 border-y border-slate-100 uppercase tracking-[0.15em] text-[9.5px] font-semibold text-slate-400">
                                    <tr>
                                        <!-- <th class="px-2 py-3 text-center" style="width: 40px;">RCV</th> -->
                                        <th class="px-2 py-3" style="width: 250px;">Product description</th>
                                        <th class="px-2 py-3 text-center" style="width: 150px;">Qty</th>
                                        <th class="px-1 py-2 text-center">Recieved <br/>Qty</th>
                                        <th class="px-2 py-3 text-center" style="width: 100px;">UOM</th>
                                        <th v-if="isReceived" class="px-2 py-3 text-center">Conversion Qty / UOM</th>
                                        <th class="px-2 py-3 text-center" style="width: 170px;">Rate</th>
                                        <th class="px-2 py-3 text-center" style="width: 130px;">Tax</th>
                                        <th class="px-2 py-3 text-center" style="width: 150px;">Discount</th>
                                        <th class="px-2 py-3 text-right">Net Amount</th>
                                        <th class="px-2 py-3" style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <template v-for="(item, index) in form.items" :key="index">
                                        <tr class="hover:bg-slate-50/50 transition-colors text-[13px]" :class="{'bg-amber-50/20 border-l-4 border-amber-400': expandedIndex === index}">
                                            <!-- <td class=" text-center text-slate-300">
                                                <ArchiveBoxIcon class="w-4 h-4 mx-auto" title="Inward History" />
                                            </td> -->
                                            <td class="">
                                                <BaseSelect
                                                    v-model="item.product_id"
                                                    :options="products"
                                                    optionLabel="title" optionValue="id"
                                                    placeholder="Product" filter
                                                    class="w-full"
                                                    @update:modelValue="onProductChange(Number(index))"
                                                    :disabled="isReceived"
                                                />
                                                <div v-if="item.history?.length > 0" class="mt-1 flex gap-1 px-1">
                                                     <span class="text-[8px] font-semibold text-slate-400 uppercase">Latest GRN: {{ item.history[item.history.length-1].inward_no }}</span>
                                                </div>
                                            </td>
                                            <td class="">
                                                <BaseInputNumber v-model="item.product_quantity" :minFractionDigits="2" class="w-full p-inputtext-sm" @update:modelValue="calculateItemTotals(Number(index))" :disabled="isReceived" />
                                            </td>
                                            <td class="text-center">
                                                 <span
                                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-mono text-sm font-semibold text-emerald-700"
                                >
                                    {{
                                        (
                                            Number(item.received_quantity) || 0
                                        ).toLocaleString("en-IN", {
                                            minimumFractionDigits: 2
                                        })
                                    }}
                                </span>
                            </td>
                            <td>
                                                <BaseSelect
                                                    v-model="item.product_uom"
                                                    :options="unitOptions"
                                                    optionLabel="label" optionValue="value"
                                                    placeholder="UOM" filter
                                                    class="w-full"
                                                    :disabled="isReceived"
                                                />
                                            </td>
                                            <td v-if="isReceived" class="px-2 text-center text-indigo-700">{{ conversionText(item) }}</td>
                                            <td class="">
                                                <BaseInputNumber v-model="item.unit_price" :minFractionDigits="2" class="w-full font-semibold text-slate-700" @update:modelValue="calculateItemTotals(Number(index))" :disabled="isReceived" />
                                            </td>
                                            <td class="">
                                                <BaseSelect
                                                    v-model="item.tax_id"
                                                    :options="taxOptions"
                                                    optionLabel="label" optionValue="value"
                                                    placeholder="Tax" 
                                                    class="w-full"
                                                    @update:modelValue="calculateItemTotals(Number(index))"
                                                    :disabled="isReceived"
                                                />
                                            </td>
                                            <td class="">
                                                <div class="flex ">
                                                    <BaseSelect v-model="item.discount_type" :options="discountTypeOptions" optionLabel="label" optionValue="value" class="w-16 " @update:modelValue="calculateItemTotals(Number(index))" :disabled="isReceived" />
                                                    <BaseInputNumber v-model="item.discount_amount" class="flex-grow   shadow-none" @update:modelValue="calculateItemTotals(Number(index))" :disabled="isReceived" />
                                                </div>
                                            </td>
                                             <td class=" text-right font-mono w-28 font-semibold text-slate-800">
                                                {{ (Number(item.price_total) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                            </td>
                                            <td class=" text-center" v-if="!isReceived">
                                                <button type="button" @click="removeItem(Number(index))" class="text-red-500 text-lg hover:text-rose-500 transition-colors">
                                                    <TrashIcon class="w-4 h-4" />
                                                </button>
                                            </td>
                                            <td v-else></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financials Footer -->
                    <div class="expansion-actions flex justify-between !items-start gap-y-6">
                        <div class="flex-grow space-y-4 max-w-sm">
                            <div class="field-group">
                                <label class="field-label">Internal Execution Notes</label>
                                <Textarea v-model="form.notes" rows="3" placeholder="Notes..." class="w-full text-xs rounded-lg border-slate-200 !bg-white" :disabled="isReceived" />
                            </div>
                        </div>

                        <div class="w-full md:w-80 space-y-2 pb-2">
                             <div class="flex justify-between items-center text-[12px] font-semibold text-slate-700 uppercase tracking-widest">
                                <span>Subtotal</span>
                                <span class="text-slate-700 font-mono">{{ Number(form.amount_untaxed || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                             <div class="flex justify-between items-center text-[12px] font-semibold text-amber-500 uppercase tracking-widest">
                                <span>Tax (+)</span>
                                <span class="font-mono">{{ Number(form.amount_tax || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                 <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Logistics (+)</span>
                                <BaseInputNumber v-model="form.shipping_charges" class="w-24 p-inputtext-sm text-right" :disabled="isReceived" />
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                 <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Discount (-)</span>
                                <BaseInputNumber v-model="form.discount_amount" class="w-24 p-inputtext-sm text-right" :disabled="isReceived" />
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                 <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Adjustment (+/-)</span>
                                <BaseInputNumber v-model="form.adjustment" class="w-24 p-inputtext-sm text-right" :disabled="isReceived" />
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                 <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Round Off (+/-)</span>
                                <BaseInputNumber v-model="form.rounding_value" class="w-24 p-inputtext-sm text-right" :disabled="isReceived" />
                            </div>
                             <div class="flex justify-between items-center border-t border-slate-100 pt-3 mt-2">
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-semibold text-slate-900 uppercase">Total Payable</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-slate-400 font-semibold mr-1">₹</span>
                                    <span class="text-lg font-semibold text-slate-900 tracking-tighter">
                                         {{ Number(form.amount_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        
                    </div>
                    <div class="w-full flex justify-end" v-if="!isReceived">
                            <BaseFormActions
                                label="Update PO"
                                :loading="form.processing"
                                @submit="submit"
                                @reset="submit"
                                cancelLabel="Revert"
                            />
                        </div>
                </form>
            </div>
    </div>
    <div v-if="isReceived" class="mt-8">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <!-- Header / Locked Banner -->
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center">
                        <i class="pi pi-lock text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Order Locked</h3>
                        <p class="text-[11px] text-slate-500 font-medium">This Purchase Order is received and restricted.</p>
                    </div>
                </div>

                <!-- Actions -->
                <BaseButton 
                    v-if="form.state !== 'cancel' && (purchaseOrder?.items || []).some((item: any) => Number(item.received_quantity) > Number(item.invoiced_quantity))"
                    label="Generate Purchase Bill" 
                    icon="pi pi-file-export" 
                    severity="primary" 
                    variant="filled"
                    size="small" 
                    @click="handleGenerateBill" 
                />
                
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-slate-600">Each bill includes only received quantities that have not yet been billed.</p>
                <p v-if="purchaseOrder?.conversion_billing_enabled" class="text-sm text-indigo-600">Conversion billing is enabled. Bill quantities and rates use the converted UOM.</p>
                <table class="w-full text-sm text-left">
                    <thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Conversion Qty / UOM</th><th>Billed</th><th>Unbilled</th><th>Remaining to receive</th></tr></thead>
                    <tbody><tr v-for="item in purchaseOrder?.items || []" :key="item.id">
                        <td>{{ item.product?.title }} ({{ item.uom?.unit_code }})</td><td>{{ item.product_quantity }}</td><td>{{ item.received_quantity }}</td><td>{{ conversionText(item) }}</td><td>{{ item.invoiced_quantity }}</td>
                        <td>{{ Math.max(0, Number(item.received_quantity) - Number(item.invoiced_quantity)).toFixed(2) }}</td>
                        <td>{{ Math.max(0, Number(item.product_quantity) - Number(item.received_quantity)).toFixed(2) }}</td>
                    </tr></tbody>
                </table>
                <h4 class="font-semibold">Purchase bills</h4>
                <div v-for="bill in purchaseOrder?.bills || []" :key="bill.id" class="flex items-center gap-4 border-t py-3 text-sm">
                    <span>{{ bill.prefix }}{{ bill.invoice_number }}</span>
                    <span>{{ bill.invoice_date?.substring(0, 10) }}</span>
                    <span>Amount: {{ bill.total_amount }}</span>
                    <a :href="route('print.document', { module: 'purchase_bills', id: bill.encrypted_id, action: 'view' })" target="_blank" class="text-indigo-600">Print bill</a>
                    <BaseButton label="Void" severity="danger" variant="text" @click="handleDeleteBill(bill.id)" />
                </div>
            </div>

            <!-- Bill Details (if generated) -->
            <div v-if="props.purchaseOrder?.bill && showBillingPanel" class="p-5 bg-white border-t border-slate-100">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bill Number</span>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-file text-indigo-500 text-xs"></i>
                            <span class="text-slate-700 font-bold text-sm">
                                {{ props.purchaseOrder.bill.prefix }}{{ props.purchaseOrder.bill.invoice_number }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bill Date & Time</span>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calendar text-slate-400 text-xs"></i>
                            <span class="text-slate-700 font-bold text-sm">
                                {{ entityLocaleDateTime(props.purchaseOrder.bill.created_at, ) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Created By</span>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-slate-400 text-xs"></i>
                            <span class="text-slate-700 font-bold text-sm capitalize">
                                {{ props.purchaseOrder.bill.created_by?.username ?? props.purchaseOrder.bill.created_by_user ?? 'System' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Posting Account (Ledger)</span>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-book text-indigo-500 text-xs"></i>
                            <span class="text-indigo-600 font-black text-sm uppercase tracking-wide">
                                {{ props.purchaseOrder.bill.account?.title ?? 'Not Selected' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Generation Dialog -->
    <Dialog v-model:visible="showBillDialog" modal header="Generate Purchase Bill" :style="{ width: '30vw' }" class="premium-dialog">
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <ArrowDownTrayIcon class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight uppercase">Bill Posting Details</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Select final ledger and dates</p>
                </div>
            </div>
        </template>

        <div class="space-y-6 py-4">
            <div class="space-y-3">
                <p class="text-sm text-slate-600">Set the rate for this bill. Each new bill starts with the PO rate.</p>
                <p v-if="purchaseOrder?.conversion_billing_enabled" class="text-sm text-indigo-600">Enter the rate per converted unit shown below.</p>
                <p v-else class="text-sm text-amber-700">Conversion billing is disabled. Enable “Bill purchases using conversion quantity” in Custom Settings to use the converted unit and quantity.</p>
                <div v-for="(line, index) in billForm.items" :key="line.order_item_id" class="border rounded-lg p-3 space-y-2">
                    <div class="font-semibold">{{ billableItems[index]?.product?.title }}</div>
                    <div v-if="!billableItems[index]?.billing_preview?.error" class="rounded-lg bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
                        <div class="text-xs font-semibold">{{ purchaseOrder?.conversion_billing_enabled ? 'Conversion Qty / UOM' : 'Bill Qty / UOM' }}</div>
                        <div class="mt-2 grid grid-cols-2 gap-4">
                            <div><div class="text-xs">Unit</div><div class="mt-1 font-semibold">{{ billingUom(billableItems[index]) }}</div></div>
                            <div><div class="text-xs">Quantity</div><div class="mt-1 font-semibold">{{ billingQuantity(billableItems[index]).toFixed(4) }}</div></div>
                        </div>
                        <div class="mt-1 text-xs">Unbilled quantity included in this bill</div>
                    </div>
                    <BaseInputNumber v-model="line.unit_price" :label="billingUom(billableItems[index]) ? `Rate per ${billingUom(billableItems[index])}` : 'Rate for this bill'" :min="0" :minFractionDigits="2" :maxFractionDigits="2" :error="billErrors[`items.${index}.unit_price`]" />
                    <p v-if="billableItems[index]?.billing_preview?.error" class="text-sm text-red-600">{{ billableItems[index].billing_preview.error }}</p>
                    <div v-else class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="flex justify-between gap-3"><span>Amount before discount and tax</span><strong>{{ (billingQuantity(billableItems[index]) * Number(line.unit_price || 0)).toFixed(2) }}</strong></div>
                        <div class="mt-1 text-xs text-slate-500">{{ billingQuantity(billableItems[index]).toFixed(4) }} {{ billingUom(billableItems[index]) }} × {{ Number(line.unit_price || 0).toFixed(2) }} per {{ billingUom(billableItems[index]) }}</div>
                    </div>
                </div>
                <p v-for="(error, key) in billErrors" :key="key" class="text-sm text-red-600">{{ error }}</p>
            </div>
            <BaseSelect 
                v-model="billForm.account_id" 
                label="Posting Ledger (Purchase Account)" 
                :options="accounts"
                optionLabel="label" 
                optionValue="value" 
                placeholder="Select Account" 
                filter 
            />

            <div class="grid grid-cols-2 gap-4">
                <BaseDatePicker 
                    v-model="billForm.invoice_date" 
                    label="Bill Date" 
                />
                <BaseDatePicker 
                    v-model="billForm.due_date" 
                    label="Due Date" 
                />
            </div>

            <div class="bg-amber-50 p-4 rounded-xl border border-amber-100">
                <div class="flex gap-3">
                    <div class="mt-0.5">
                        <ArchiveBoxIcon class="w-4 h-4 text-amber-600" />
                    </div>
                    <p class="text-[11px] font-medium text-amber-700 leading-relaxed">
                        This will bill only the unbilled received quantity at the rates entered above, with applicable taxes and proportional discounts and charges. These rates apply only to this bill.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-50">
                <BaseButton label="Cancel" variant="text" severity="secondary" @click="showBillDialog = false" class="!text-xs font-bold uppercase tracking-widest" />
                <BaseButton label="Generate Bill" variant="filled" severity="primary" :disabled="hasConversionError" @click="executeBillGeneration" />
            </div>
        </template>
    </Dialog>
</template>
