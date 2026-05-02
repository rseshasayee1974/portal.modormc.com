<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import PurchaseOrderEditForm from './components/PurchaseOrderEditForm.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Button from 'primevue/button';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps<{
    purchaseOrder: any;
    entities: any[];
    plants: any[];
    vendors: any[];
    vehicles: any[];
    currencies: any[];
    taxes: any[];
    products: any[];
    productUnits: any[];
    accounts: any[];
}>();

const page = usePage();

const activePlantId = computed(() => page.props.active_plant_id ?? null);

const form = useForm({
    id: props.purchaseOrder?.id || null,
    plant_id: props.purchaseOrder?.plant_id || activePlantId.value,

    vendor_id: props.purchaseOrder?.vendor_id || null,
    vehicle_id: props.purchaseOrder?.vehicle_id || null,
    po_number: props.purchaseOrder?.po_number || null,
    referencenumber: props.purchaseOrder?.ref_no || null, // In edit we use the existing ref_no
    date_order: props.purchaseOrder?.date_order ? props.purchaseOrder.date_order.substring(0, 10) : new Date().toISOString().substring(0, 10),
    date_planned: props.purchaseOrder?.date_planned ? props.purchaseOrder.date_planned.substring(0, 10) : null,
    due_date: props.purchaseOrder?.due_date ? props.purchaseOrder.due_date.substring(0, 10) : null,
    currency_id: props.purchaseOrder?.currency_id || (props.currencies?.[0]?.id || null),
    exchange_rate: props.purchaseOrder?.exchange_rate || 0.0,
    amount_untaxed: Number(props.purchaseOrder?.amount_untaxed) || 0,
    amount_tax: Number(props.purchaseOrder?.amount_tax) || 0,
    amount_total: Number(props.purchaseOrder?.amount_total) || 0,
    discount_amount: Number(props.purchaseOrder?.discount_amount) || 0,
    shipping_charges: Number(props.purchaseOrder?.shipping_charges) || 0,
    adjustment: Number(props.purchaseOrder?.adjustment) || 0,
    notes: props.purchaseOrder?.notes || '',
    terms_conditions: props.purchaseOrder?.terms_conditions || '',
    items: props.purchaseOrder?.items ? props.purchaseOrder.items.map(i => ({ 
        ...i, 
        product_quantity: Number(i.product_quantity) || 0,
        unit_price: Number(i.unit_price) || 0,
        discount_amount: Number(i.discount_amount) || 0,
        total_discount: Number(i.total_discount) || 0,
        price_subtotal: Number(i.price_subtotal) || 0,
        price_tax: Number(i.price_tax) || 0,
        price_total: Number(i.price_total) || 0,
        received_now: 0,
        received_date: new Date().toISOString().substring(0, 10),
        inward_no: ''
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
        price_total: 0,
        received_now: 0,
        received_date: new Date().toISOString().substring(0, 10),
        inward_no: ''
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
        item.product_uom = product.unit_id; // Store ID
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
    form.amount_total = form.amount_untaxed + form.amount_tax + (Number(form.shipping_charges) || 0) + (Number(form.adjustment) || 0);
};

watch(() => [form.shipping_charges, form.adjustment, form.discount_amount], calculateFinalTotals);

const submit = () => {
    form.put(route('purchaseorder.update', props.purchaseOrder.id), {
        onSuccess: () => {
            Swal.fire({ 
                toast: true, 
                position: 'top-end', 
                icon: 'success', 
                title: 'Receipt recorded successfully.', 
                showConfirmButton: false, 
                timer: 3000 
            });
            // Force a full site refresh to reload the latest inward numbers and state
            router.reload({ 
                onFinish: () => {
                   form.items.forEach((item: any) => {
                       item.received_now = 0;
                   });
                }
            });
        },
        preserveScroll: true,
        preserveState: true
    });
};
</script>

<template>
    <AppLayout :title="'Edit Order: ' + purchaseOrder.po_number">
        <template #header>
            <div class="flex flex-col space-y-4">
                <ModuleSubTopNav />
                <div class="flex items-center gap-4 bg-white/50 p-4 rounded-2xl border border-slate-200">
                    <Link :href="route('purchaseorder.index')">
                        <Button icon="pi pi-arrow-left" severity="secondary" rounded />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-black text-gray-800 tracking-tight leading-none">
                            Revision: {{ purchaseOrder.po_number }}
                        </h1>
                        <p class="text-[10px] text-amber-600 font-bold uppercase tracking-widest mt-1">Order Correction & Modification Mode</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
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
                    :removeItem="removeItem"
                    :submit="submit"
                    :accounts="accounts"
                />
            </div>
        </div>
    </AppLayout>
</template>

