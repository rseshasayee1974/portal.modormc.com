<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { 
    ShoppingCartIcon, 
    TrashIcon,
    PlusIcon,
    IdentificationIcon,
    ListBulletIcon,
    BanknotesIcon,
    DocumentTextIcon,
    CreditCardIcon,
    ArchiveBoxIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const { isScaleConnected, captureWeight } = useWeighbridge();

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';

const props = defineProps({
    purchaseOrder: Object,
    vendors: Array,    
    currencies: Array,
    taxes: Array,
    ref_no: Object,
    productUnits: Array,
    instant_vendor: [Number, Boolean], 
});

const page = usePage();
const activePlantId = computed(() => page.props.active_plant_id ?? null);
const isOpen = ref(true);
const activeTab = ref('items');

const isEdit = !!props.purchaseOrder;
const form = useForm({
    id: props.purchaseOrder?.id || null,
    plant_id: props.purchaseOrder?.plant_id || activePlantId.value,
    vendor_id: props.purchaseOrder?.vendor_id || null,
    vehicle_id: props.purchaseOrder?.vehicle_id || null,
    po_number: props.purchaseOrder?.po_number || null,
    prefix: props.ref_no?.prefix,
    referencenumber: props.ref_no?.formatted || props.purchaseOrder?.ref_no,
    ref_no: props.ref_no?.ref_no || props.purchaseOrder?.ref_no,
    date_order: props.purchaseOrder?.date_order ? props.purchaseOrder.date_order.substring(0, 10) : new Date().toISOString().substring(0, 10),
    date_planned: props.purchaseOrder?.date_planned ? props.purchaseOrder.date_planned.substring(0, 10) : null,
    due_date: props.purchaseOrder?.due_date ? props.purchaseOrder.due_date.substring(0, 10) : null,
    currency_id: props.purchaseOrder?.currency_id || (props.currencies?.[0]?.id || null),
    exchange_rate: props.purchaseOrder?.exchange_rate || 1.0,
    amount_untaxed: props.purchaseOrder?.amount_untaxed || 0,
    amount_tax: props.purchaseOrder?.amount_tax || 0,
    amount_total: props.purchaseOrder?.amount_total || 0,
    discount_amount: props.purchaseOrder?.discount_amount || 0,
    shipping_charges: props.purchaseOrder?.shipping_charges || 0,
    adjustment: props.purchaseOrder?.adjustment || 0,
    rounding_value: props.purchaseOrder?.rounding_value || 0,
    notes: props.purchaseOrder?.notes || '',
    terms_conditions: props.purchaseOrder?.terms_conditions || '',
    items: props.purchaseOrder?.items ? props.purchaseOrder.items.map(i => ({ 
        ...i, 
        total_discount: i.total_discount || 0,
        product_quantity: Number(i.product_quantity),
        unit_price: Number(i.unit_price)
    })) : [createNewItem()]
});

function createNewItem() {
    return {
        id: null,
        product_id: null,
        product_uom: null,
        tax_id: null,
        product_quantity: 1,
        unit_price: 0,
        discount_type: 'percentage',
        discount_amount: 0,
        total_discount: 0,
        description: '',
        price_subtotal: 0,
        price_tax: 0,
        price_total: 0
    };
}

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const addItem = () => {
    form.items.push(createNewItem());
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        calculateFinalTotals();
    } else {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'At least one item required', showConfirmButton: false, timer: 3000 });
    }
};

const onProductChange = (index) => {
    const item = form.items[index];
    const product = props.products.find(p => p.id === item.product_id);
    if (product) {
        item.product_uom = product.unit_id; 
        item.unit_price = product.purchase_price || 0;
        item.tax_id = product.purchase_tax_id || null;
        item.description = product.title || '';
        calculateItemTotals(index);
    }
};

const calculateItemTotals = (index) => {
    const item = form.items[index];
    const qty = Number(item.product_quantity) || 0;
    const price = Number(item.unit_price) || 0;
    const subtotal = qty * price;
    
    let discount = 0;
    const discAmount = Number(item.discount_amount) || 0;
    if (item.discount_type === 'percentage') {
        discount = (subtotal * discAmount) / 100;
    } else {
        discount = discAmount;
    }
    
    item.total_discount = discount;
    item.price_subtotal = subtotal - discount;
    
    const tax = props.taxes.find(t => t.id === item.tax_id);
    if (tax) {
        item.price_tax = (item.price_subtotal * (Number(tax.tax_rate) || 0)) / 100;
    } else {
        item.price_tax = 0;
    }
    
    item.price_total = item.price_subtotal + item.price_tax;
    calculateFinalTotals();
};

const calculateFinalTotals = () => {
    const itemSubtotalsSum = form.items.reduce((sum, item) => sum + (Number(item.price_subtotal) || 0), 0);
    const itemTaxesTotal = form.items.reduce((sum, item) => sum + (Number(item.price_tax) || 0), 0);
    
    // amount_untaxed is net before tax (including all discounts)
    form.amount_untaxed = itemSubtotalsSum - (Number(form.discount_amount) || 0);
    form.amount_tax = itemTaxesTotal;
    form.amount_total = form.amount_untaxed + form.amount_tax + (Number(form.shipping_charges) || 0) + (Number(form.adjustment) || 0) + (Number(form.rounding_value) || 0);
};

watch(() => [form.shipping_charges, form.adjustment, form.rounding_value, form.discount_amount], calculateFinalTotals);

const submit = () => {
    const routeName = isEdit ? 'purchaseorder.update' : 'purchaseorder.store';
    const routeParams = isEdit ? props.purchaseOrder.id : [];
    
    form.transform(data => ({
        ...data,
        date_order: data.date_order ? new Date(data.date_order).toISOString().split('T')[0] : null,
        date_planned: data.date_planned ? new Date(data.date_planned).toISOString().split('T')[0] : null,
        due_date: data.due_date ? new Date(data.due_date).toISOString().split('T')[0] : null,
    }))[isEdit ? 'put' : 'post'](route(routeName, routeParams), {
        onSuccess: () => {
            Swal.fire({ 
                toast: true, 
                position: 'top-end', 
                icon: 'success', 
                title: isEdit ? 'Purchase Order updated.' : 'Purchase Order generated.', 
                showConfirmButton: false, 
                timer: 3000 
            });
            if (!isEdit) form.reset();
        }
    });
};

const isInstantVendorEnabled = computed(() => Number(props.instant_vendor) === 1);

const isCreatingVendor = ref(false);
const handleCreateVendor = async (name) => {
    isCreatingVendor.value = true;
    try {
        const response = await axios.post(route('patrons.store'), {
            legal_name: name,
            patron_type: ['Vendor', 'Supplier'],
            operational_status: 'active',
            status: true,
            displayed: true,
        });
        
        const newVendor = response.data.patron;
        
        Swal.fire({ 
            toast: true, 
            position: 'top-end', 
            icon: 'success', 
            title: `Vendor "${name}" created.`, 
            showConfirmButton: false, 
            timer: 3000 
        });

        // Reload vendors prop to keep sync with backend and select new vendor
        router.reload({
            only: ['vendors'],
            onSuccess: () => {
                form.vendor_id = newVendor.id;
            }
        });
    } catch (error) {
        console.error(error);
        Swal.fire({ 
            icon: 'error', 
            title: 'Creation Failed', 
            text: error.response?.data?.message || 'Could not create vendor automatically.' 
        });
    } finally {
        isCreatingVendor.value = false;
    }
};

const vendorOptions = computed(() => props.vendors?.map(v => ({ label: v.legal_name, value: v.id })) || []);
const productOptions = computed(() => props.products?.map(p => ({ label: p.title, value: p.id })) || []);
const unitOptions = computed(() => props.productUnits?.map(u => ({ label: u.unit_code, value: u.id })) || []);
const taxOptions = computed(() => props.taxes?.map(t => ({ label: t.tax_name, value: t.id })) || []);
const discountTypeOptions = [{ label: '%', value: 'percentage' }, { label: 'Fixed', value: 'fixed' }];

</script>

<template>
    <div class="no-print create-panel" :class="{ 'create-panel--open': isOpen }">
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <ShoppingCartIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">
                        {{ isEdit ? 'Edit Purchase Order' : 'New Purchase Order' }}
                    </p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">
                        {{ isEdit ? 'Update details for ' + form.referencenumber : 'Create a new supply procurement order' }}
                    </p>
                </div>
            </div>
            <!-- <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': isOpen }">
                <PlusIcon class="w-3 h-3" />
            </div> -->
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <BaseCreatableSelect
                            v-if="isInstantVendorEnabled"
                            v-model="form.vendor_id"
                            :options="vendorOptions"
                            optionLabel="label" 
                            optionValue="value"
                            label="Vendor / Supplier"
                            placeholder="Select Vendor" 
                            required
                            :creating="isCreatingVendor"
                            :error="form.errors.vendor_id"
                            @create="handleCreateVendor"
                        />
                        <BaseSelect
                            v-else
                            v-model="form.vendor_id"
                            :options="vendorOptions"
                            optionLabel="label" 
                            optionValue="value"
                            label="Vendor / Supplier"
                            placeholder="Select Vendor" 
                            filter
                            required
                            :error="form.errors.vendor_id"
                        />
                        
                        <BaseDatePicker
                            v-model="form.date_order"
                            label="Order Date"
                            required
                            :error="form.errors.date_order"
                        />

                        <BaseDatePicker 
                            v-model="form.date_planned" 
                            label="Planned Date" 
                        />

                        <BaseInput 
                            v-model="form.referencenumber" 
                            label="Reference No."
                            placeholder="Auto" 
                            disabled 
                            inputClass="bg-gray-50/50" 
                        />
                    </div>

                    <!-- Items Table -->
                    <div class="mt-6">
                        <!-- <div class="flex items-center justify-between mb-2 px-1">
                            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Line Items</h3>
                            <Button label="Add Item" icon="pi pi-plus" text size="small" @click="addItem" class="!text-[10px] !font-bold" />
                        </div> -->
                        
                        <div class="overflow-x-auto rounded-sm border border-slate-100 shadow-sm overflow-hidden bg-white">
                            <table class="w-full text-left border-collapse min-w-[1000px]">
                                <thead class="bg-slate-50 border-b border-slate-100 uppercase tracking-tighter text-[10px] font-bold text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 280px;">Product</th>
                                        <th class="px-4 py-3 text-center" style="width: 100px;">Qty</th>
                                        <th class="px-4 py-3 text-center" style="width: 120px;">UOM</th>
                                        <th class="px-4 py-3 text-center" style="width: 140px;">Rate</th>
                                        <th class="px-4 py-3 text-center" style="width: 150px;">Tax</th>
                                        <th class="px-4 py-3 text-center" style="width: 180px;">Discount</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                        <th class="px-1 py-1" style="width: 50px;">
                                            <button v-if="!isLocked" type="button" @click="addItem" class="text-indigo-600 font-bold text-[10px] uppercase hover:text-indigo-700 flex items-center gap-1">
                                                <PlusIcon class="w-5 h-5 m-2 border-1 shadow-sm  hover:bg-indigo-500 bg-indigo-300 border-gray-400 rounded" />
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-slate-50/50 transition-colors text-[12px]">
                                        <td class="p-2">
                                            <BaseSelect
                                                v-model="item.product_id"
                                                :options="productOptions"
                                                optionLabel="label" 
                                                optionValue="value"
                                                placeholder="Product" 
                                                filter
                                                
                                                @update:modelValue="onProductChange(index)"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber v-model="item.product_quantity" :minFractionDigits="2" size="small" class="w-full" @update:modelValue="calculateItemTotals(index)" />
                                        </td>
                                        <td class="p-2">
                                            <BaseSelect
                                                v-model="item.product_uom"
                                                :options="unitOptions"
                                                optionLabel="label" 
                                                optionValue="value"
                                                placeholder="UOM" 
                                                filter
                                                 
                                            />
                                        </td>
                                        <td class="p-2">
                                            <BaseInputNumber v-model="item.unit_price" :minFractionDigits="2" size="small" inputClass="font-semibold text-indigo-600" @update:modelValue="calculateItemTotals(index)" />
                                        </td>
                                        <td class="p-2">
                                            <BaseSelect
                                                v-model="item.tax_id"
                                                :options="taxOptions"
                                                optionLabel="label" 
                                                optionValue="value"
                                                placeholder="Tax" 
                                                 class="w-32"
                                                @update:modelValue="calculateItemTotals(index)"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <div class="flex gap-1">
                                                <BaseSelect 
                                                    v-model="item.discount_type" 
                                                    :options="discountTypeOptions" 
                                                    optionLabel="label" 
                                                    optionValue="value" 
                                                      
                                                    class="!w-24"
                                                    @update:modelValue="calculateItemTotals(index)" 
                                                />
                                                <BaseInputNumber v-model="item.discount_amount" size="small" class="flex-grow" @update:modelValue="calculateItemTotals(index)" />
                                            </div>
                                        </td>
                                        <td class="p-2 text-sm text-right font-semibold text-slate-700">
                                            {{ item.price_total.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </td>
                                        <td class="p-2 text-center">
                                            <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-rose-500 transition-colors">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-10 p-1">
                        <div class="space-y-4">
                            <div class="field-group">
                                <label class="field-label">Internal Notes / Terms</label>
                                <Textarea v-model="form.notes" rows="6" placeholder="Specify terms, conditions or internal instructions..." class="w-full text-xs rounded-xl border-slate-200" />
                            </div>
                        </div>

                        <div class="bg-slate-50/50 rounded-lg p-6 border border-slate-100">
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-[11px] font-semibold text-slate-700 uppercase tracking-widest">
                                    <span>Untaxed Amount</span>
                                    <span class="text-slate-700">{{ form.amount_untaxed.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[11px] font-black text-indigo-500 uppercase tracking-widest">
                                    <span>Taxes & Duties (+)</span>
                                    <span>{{ form.amount_tax.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Freight Charges (+)</span>
                                    <BaseInputNumber v-model="form.shipping_charges" size="small" class="w-24" @update:modelValue="calculateFinalTotals" />
                                </div>

                                <div class="flex justify-between items-center gap-4 border-t border-slate-200/50 pt-3">
                                    <span class="text-[11px] font-semibold text-rose-700 uppercase tracking-widest">Global Discount (-)</span>
                                    <BaseInputNumber v-model="form.discount_amount" size="small" class="w-24" @update:modelValue="calculateFinalTotals" />
                                </div>

                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Other Adjustment (+/-)</span>
                                    <BaseInputNumber v-model="form.adjustment" size="small" class="w-24" @update:modelValue="calculateFinalTotals" />
                                </div>

                                <div class="flex justify-between items-center gap-4">
                                    <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Round Off (+/-)</span>
                                    <BaseInputNumber v-model="form.rounding_value" size="small" class="w-24" @update:modelValue="calculateFinalTotals" />
                                </div>

                                <div class="flex justify-between items-center border-t border-slate-200 pt-4 mt-4">
                                    <div class="flex flex-col">
                                        <span class="text-[14px] font-semibold text-indigo-700 uppercase tracking-[0.2em]">Final Total</span>
                                        <!-- <span class="text-[11px] text-slate-700 font-semibold uppercase">{{ currencies?.find(c => c.id === form.currency_id)?.currency_name || 'Rupees' }}</span> -->
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-indigo-700 font-semibold mr-1">{{ currencies?.find(c => c.id === form.currency_id)?.currency_code || '₹' }}</span>
                                        <span class="text-lg font-black text-slate-800 tracking-tighter">
                                             {{ form.amount_total.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8">
                                <BaseFormActions
                                    :label="isEdit ? 'Update Purchase Order' : 'Commit Order'"
                                    :loading="form.processing"
                                    @submit="submit"
                                    @reset="router.visit(route('purchaseorder.index'))"
                                    cancelLabel="Discard"
                                    class="!justify-end"
                                />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
</style>


