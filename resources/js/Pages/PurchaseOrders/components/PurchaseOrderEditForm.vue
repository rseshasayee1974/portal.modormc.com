<script setup lang="ts">
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
console.log(props.accounts);
const isOpen = ref(true);
const expandedIndex = ref<number | null>(0);
const showBillDialog = ref(false);

const billForm = ref({
    account_id: null,
    invoice_date: new Date().toISOString().substring(0, 10),
    due_date: props.form.due_date || new Date().toISOString().substring(0, 10)
});

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const toggleRow = (index: number, item: any) => {
    expandedIndex.value = expandedIndex.value === index ? null : index;
};


const vendorOptions = computed(() => props.vendors?.map(v => ({ label: v.legal_name, value: v.id })) || []);
const productOptions = computed(() => props.products?.map(p => ({ label: p.title, value: p.id })) || []);
const unitOptions = computed(() => props.productUnits?.map(u => ({ label: u.unit_code, value: u.id })) || []);
const taxOptions = computed(() => props.taxes?.map(t => ({ label: t.tax_name, value: t.id })) || []);
const discountTypeOptions = [{ label: '%', value: 'percentage' }, { label: 'Fixed', value: 'fixed' }];

const stateOptions = [
    { label: 'Draft', value: 'draft' },
    { label: 'Approved', value: 'approved' },
    { label: 'Billed', value: 'billed' },
    { label: 'Cancelled', value: 'cancel' }
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
    showBillDialog.value = true;
};

const executeBillGeneration = () => {
    if (!billForm.value.account_id) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Please select a ledger account', showConfirmButton: false, timer: 3000 });
        return;
    }

    router.post(route('purchaseorder.generate-bill', props.form.id), billForm.value, {
        onSuccess: () => {
            showBillDialog.value = false;
        },
        preserveScroll: true
    });
};


</script>

<template>
    <div class="p-4 lg:p-4" >
        <!-- Header -->
        

      
            <div   class="">
                <form @submit.prevent="submit" class="space-y-6">
                    <div v-if="isReceived" class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl flex items-center justify-between gap-3 text-sm shadow-sm transition-all">
                        <div class="flex items-center gap-3">
                            <i class="pi pi-lock"></i>
                            <span>This Purchase Order is <strong>Locked</strong> (Received, Approved, or Cancelled). Changes are restricted.</span>
                        </div>
                        <BaseButton 
                            v-if="form.state !== 'cancel' && form.invoice_status !== 'invoiced'"
                            label="Generate Purchase Bill" 
                            icon="pi pi-file-export" 
                            severity="success" 
                            size="small" 
                            @click="handleGenerateBill" 
                        />
                         <div v-else-if="form.invoice_status === 'invoiced'" class="flex items-center gap-2 text-emerald-600 font-bold uppercase tracking-widest text-[10px] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
                            <i class="pi pi-check-circle"></i>
                            <span>Bill Generated</span>
                        </div>
                    </div>
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
                        <div class="field-group col-span-12 md:col-span-3">
                            <label class="field-label">Ref Number</label>
                             <BaseInput v-model="form.referencenumber" disabled class="w-full bg-slate-50 border-none font-mono font-semibold text-slate-500" />
                        </div>

                        <BaseSelect 
                            v-model="form.state" 
                            :options="stateOptions" 
                            label="Order Status"
                            optionLabel="label" 
                            optionValue="value" 
                            class="col-span-12 md:col-span-2"
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
                        <!-- <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.2em]">Procurement Lines</h3>
                            <BaseButton label="Add New Line" icon="pi pi-plus" variant="text" size="small" @click="addItem" class="!text-[10px] !font-semibold !text-amber-600" />
                        </div> -->
                        
                        <div class="overflow-x-auto      overflow-hidden">
                            <table class=" text-left border-collapse w-full">
                                <thead class="bg-slate-50/80 border-y border-slate-100 uppercase tracking-[0.15em] text-[9.5px] font-semibold text-slate-400">
                                    <tr>
                                        <!-- <th class="px-2 py-3 text-center" style="width: 40px;">RCV</th> -->
                                        <th class="px-2 py-3" style="width: 250px;">Product description</th>
                                        <th class="px-2 py-3 text-center" style="width: 150px;">Qty</th>
                                        <th class="px-2 py-3 text-center" style="width: 100px;">UOM</th>
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
                                                    :options="productOptions"
                                                    optionLabel="label" optionValue="value"
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
                                            <td class="">
                                                <BaseSelect
                                                    v-model="item.product_uom"
                                                    :options="unitOptions"
                                                    optionLabel="label" optionValue="value"
                                                    placeholder="UOM" filter
                                                    class="w-full"
                                                    :disabled="isReceived"
                                                />
                                            </td>
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
                                <Textarea v-model="form.notes" rows="3" placeholder="Notes..." class="w-full text-xs rounded-lg border-slate-200" :disabled="isReceived" />
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
                                 <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Round Off (+/-)</span>
                                <BaseInputNumber v-model="form.rounding_value" class="w-24 p-inputtext-sm text-right" :disabled="isReceived" />
                            </div>
                             <div class="flex justify-between items-center border-t border-slate-100 pt-3 mt-2">
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-semibold text-slate-900 uppercase">Total Payable</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-slate-400 font-semibold mr-1">{{ currencies?.find(c => c.id === form.currency_id)?.currency_code || '₹' }}</span>
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

    <!-- Bill Generation Dialog -->
    <Dialog v-model:visible="showBillDialog" modal header="Generate Purchase Bill" :style="{ width: '30vw' }" class="premium-dialog">
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <ArrowDownTrayIcon class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight uppercase">Bill Posting Details</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Select final ledger and dates</p>
                </div>
            </div>
        </template>

        <div class="space-y-6 py-4">
            <BaseSelect 
                v-model="billForm.account_id" 
                label="Posting Ledger (Expense Account)" 
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
                        This will generate an approved Purchase Bill in the Invoices module. Ensure the posting ledger correctly reflects your chart of accounts.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-50">
                <Button label="Cancel" text severity="secondary" @click="showBillDialog = false" class="!text-xs font-bold uppercase tracking-widest" />
                <Button label="Generate Bill" severity="success" @click="executeBillGeneration" class="!px-6 !text-xs font-bold uppercase tracking-widest shadow-lg shadow-emerald-200" />
            </div>
        </template>
    </Dialog>
</template>



