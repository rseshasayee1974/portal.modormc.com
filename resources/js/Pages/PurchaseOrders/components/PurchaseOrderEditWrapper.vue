<script setup lang="ts">
import { useForm, usePage, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PurchaseOrderEditForm from './PurchaseOrderEditForm.vue';
import Swal from 'sweetalert2';

const props = defineProps<{
    purchaseOrder: any;
    vendors: any[];
    currencies: any[];
    accounts: any[];
    taxes: any[];
    products: any[];
    productUnits: any[];
}>();

const page = usePage();
const activePlantId = computed(() => page.props.active_plant_id ?? null);

const itemDiscountsTotalInitial = props.purchaseOrder?.items?.reduce((sum, i) => sum + (Number(i.total_discount) || 0), 0) || 0;
const isReceived = computed(() => {
    const hasReceipts = Number(props.purchaseOrder?.receipt_status || 0) > 0;
    const isFinalState = ['approved', 'done', 'cancel'].includes(props.purchaseOrder?.state);
    return hasReceipts || isFinalState;
});

const form = useForm({
    id: props.purchaseOrder?.id || null,
    plant_id: props.purchaseOrder?.plant_id || activePlantId.value,
    vendor_id: props.purchaseOrder?.vendor_id || null,
    vehicle_id: props.purchaseOrder?.vehicle_id || null,
    po_number: props.purchaseOrder?.po_number || null,
    referencenumber: props.purchaseOrder?.ref_no || null, 
    date_order: props.purchaseOrder?.date_order ? props.purchaseOrder.date_order.substring(0, 10) : new Date().toISOString().substring(0, 10),
    billed_date: props.purchaseOrder?.billed_date ? props.purchaseOrder.billed_date.substring(0, 10) : null,
    due_date: props.purchaseOrder?.due_date ? props.purchaseOrder.due_date.substring(0, 10) : null,
    currency_id: props.purchaseOrder?.currency_id || (props.currencies?.[0]?.id || null),
    exchange_rate: props.purchaseOrder?.exchange_rate || 0.0,
    amount_untaxed: Number(props.purchaseOrder?.amount_untaxed) || 0,
    amount_tax: Number(props.purchaseOrder?.amount_tax) || 0,
    amount_total: Number(props.purchaseOrder?.amount_total) || 0,
    discount_amount: Number(props.purchaseOrder?.discount_amount) || 0,
    shipping_charges: Number(props.purchaseOrder?.shipping_charges) || 0,
    adjustment: Number(props.purchaseOrder?.adjustment) || 0,
    rounding_value: Number(props.purchaseOrder?.rounding_value) || 0,
    notes: props.purchaseOrder?.notes || '',
    terms_conditions: props.purchaseOrder?.terms_conditions || '',
    state: props.purchaseOrder?.state || 'draft',
    approve_status: props.purchaseOrder?.approve_status || 0,
    receipt_status: props.purchaseOrder?.receipt_status || 0,
    invoice_status: props.purchaseOrder?.invoice_status || 'no_invoice',
    items: props.purchaseOrder?.items ? props.purchaseOrder.items.map(i => ({ 
        ...i, 
        product_quantity: Number(i.product_quantity) || 0,
        unit_price: Number(i.unit_price) || 0,
        discount_amount: Number(i.discount_amount) || 0,
        total_discount: Number(i.total_discount) || 0,
        price_subtotal: Number(i.price_subtotal) || 0,
        price_tax: Number(i.price_tax) || 0,
        price_total: Number(i.price_total) || 0
    })) : []
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

const addItem = () => {
    form.items.push(createNewItem());
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        calculateFinalTotals();
    } else {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'At least one item required', showConfirmButton: false, timer: 3000 });
    }
};

const onProductChange = (index: number) => {
    const item = form.items[index];
    const product = props.products.find(p => p.id === item.product_id);
    if (product) {
        item.product_uom = product.unit_id;
        item.unit_price = Number(product.purchase_price) || 0;
        item.tax_id = product.purchase_tax_id || null;
        item.description = product.description || '';
        calculateItemTotals(index);
    }
};

const calculateItemTotals = (index: number) => {
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
    
    // amount_untaxed is final net before tax
    form.amount_untaxed = itemSubtotalsSum - (Number(form.discount_amount) || 0);
    form.amount_tax = itemTaxesTotal;
    form.amount_total = form.amount_untaxed + form.amount_tax + (Number(form.shipping_charges) || 0) + (Number(form.adjustment) || 0) + (Number(form.rounding_value) || 0);
};

watch(() => [form.shipping_charges, form.adjustment, form.rounding_value, form.discount_amount], calculateFinalTotals);

const submit = () => {
    form.transform(data => ({
        ...data,
        date_order: data.date_order ? new Date(data.date_order).toISOString().split('T')[0] : null,
        billed_date: data.billed_date ? new Date(data.billed_date).toISOString().split('T')[0] : null,
        due_date: data.due_date ? new Date(data.due_date).toISOString().split('T')[0] : null,
    })).put(route('purchaseorder.update', props.purchaseOrder.id), {
        onSuccess: () => {
            Swal.fire({ 
                toast: true, 
                position: 'top-end', 
                icon: 'success', 
                title: 'Purchase Order updated successfully.', 
                showConfirmButton: false, 
                timer: 3000 
            });
        },
        preserveScroll: true,
        preserveState: true
    });
};

// Initial calculation to sync any potential string/number mismatches or newly loaded items
calculateFinalTotals();
</script>

<template>
    <div class="">
        <PurchaseOrderEditForm 
            :form="form"
            :vendors="vendors"
            :currencies="currencies"
            :taxes="taxes"
            :products="products"
            :productUnits="productUnits"
            :onProductChange="onProductChange"
            :calculateItemTotals="calculateItemTotals"
            :addItem="addItem"
            :accounts="accounts"
            :removeItem="removeItem"
            :submit="submit"
            :isReceived="isReceived"
        />
    </div>
</template>

