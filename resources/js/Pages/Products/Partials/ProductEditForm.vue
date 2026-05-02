<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@/Composables/useForm';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

interface Product {
    id: number;
    title: string;
    code: string | null;
    category_id: number | null;
    unit_id: number | null;
    purchase_price: number;
    sales_price: number;
    status: boolean | number;
    hsn_code?: string;
    material_code?: string;
    tax_mode?: boolean;
    purchase_tax_id?: number | null;
    sale_tax_id?: number | null;
    is_service?: boolean;
    product_type?: string;
    stock_alert?: number;
    conversion_quantity?: number;
}

interface Option {
    id: number;
    name?: string;
    title?: string;
    unit_code?: string;
}

const props = defineProps<{
    product: Product;
    categories: Option[];
    units: Option[];
    purchaseTaxes?: Option[];
    saleTaxes?: Option[];
    productTypes?: string[];
}>();

const emit = defineEmits<{
    cancel: [];
    saved: [];
}>();

const toast = useToast();
const form = useForm({
    title: props.product.title ?? '',
    code: props.product.code ?? '',
    category_id: props.product.category_id ?? null,
    unit_id: props.product.unit_id ?? null,
    purchase_price: Number(props.product.purchase_price ?? 0),
    sales_price: Number(props.product.sales_price ?? 0),
    status: Boolean(props.product.status),
    hsn_code: props.product.hsn_code ?? '',
    material_code: props.product.material_code ?? '',
    tax_mode: Boolean(props.product.tax_mode ?? false),
    purchase_tax_id: props.product.purchase_tax_id ?? null,
    sale_tax_id: props.product.sale_tax_id ?? null,
    is_service: Boolean(props.product.is_service ?? false),
    product_type: props.product.product_type ?? 'Purchase',
    stock_alert: Number(props.product.stock_alert ?? 0),
    conversion_quantity: Number(props.product.conversion_quantity ?? 0),
});

const categoryOptions = computed(() =>
    props.categories.map((c) => ({ label: c.name ?? c.title ?? '', value: c.id }))
);

const unitOptions = computed(() =>
    props.units.map((u) => ({ label: u.unit_code ?? u.title ?? '', value: u.id }))
);

const purchaseTaxOptions = computed(() =>
    (props.purchaseTaxes || []).map((t) => ({ label: t.name ?? t.title ?? '', value: t.id }))
);

const salesTaxOptions = computed(() =>
    (props.saleTaxes || []).map((t) => ({ label: t.name ?? t.title ?? '', value: t.id }))
);

const productTypeOptions = computed(() =>
    (props.productTypes || []).map((t) => ({ label: t, value: t }))
);

const submit = () => {
    form.put(route('products.update', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Product updated successfully.', life: 3000 });
            emit('saved');
        },
    });
};
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4 max-w-6xl">
        <div class="field-group">
            <BaseInput 
                v-model="form.title" 
                label="Product Name"
                required
                :error="form.errorFor('title')"
            />
        </div>
        <div class="field-group">
            <label class="field-label">Category</label>
            <BaseSelect
                v-model="form.category_id"
                :options="categoryOptions"
                optionLabel="label"
                optionValue="value"
                filter
                :class="{ 'p-invalid': form.errorFor('category_id') }"
            />
            <small v-if="form.errorFor('category_id')" class="field-error">{{ form.errorFor('category_id') }}</small>
        </div>
        <div class="field-group">
            <label class="field-label">Unit</label>
            <BaseSelect
                v-model="form.unit_id"
                :options="unitOptions"
                optionLabel="label"
                optionValue="value"
                :class="{ 'p-invalid': form.errorFor('unit_id') }"
            />
            <small v-if="form.errorFor('unit_id')" class="field-error">{{ form.errorFor('unit_id') }}</small>
        </div>
        <div class="field-group">
            <BaseInput 
                v-model="form.purchase_price" 
                label="Purchase Price"
                required
                type="number"
                :error="form.errorFor('purchase_price')"
            />
        </div>
        <div class="field-group">
            <BaseInput 
                v-model="form.sales_price" 
                label="Sale Price"
                required
                type="number"
                :error="form.errorFor('sales_price')"
            />
        </div>
        <div class="field-group">
            <label class="field-label">Product Type</label>
            <BaseSelect
                v-model="form.product_type"
                :options="productTypeOptions"
                optionLabel="label"
                optionValue="value"
                :class="{ 'p-invalid': form.errorFor('product_type') }"
            />
            <small v-if="form.errorFor('product_type')" class="field-error">{{ form.errorFor('product_type') }}</small>
        </div>
        <div class="field-group">
            <BaseInput 
                v-model="form.hsn_code" 
                label="HSN Code"
                :error="form.errorFor('hsn_code')"
            />
        </div>
        <div class="field-group">
            <BaseInput 
                v-model="form.material_code" 
                label="Material Code"
                :error="form.errorFor('material_code')"
            />
        </div>
        <div class="field-group">
            <label class="field-label">Purchase Tax</label>
            <BaseSelect
                v-model="form.purchase_tax_id"
                :options="purchaseTaxOptions"
                optionLabel="label"
                optionValue="value"
                filter
                :class="{ 'p-invalid': form.errorFor('purchase_tax_id') }"
            />
            <small v-if="form.errorFor('purchase_tax_id')" class="field-error">{{ form.errorFor('purchase_tax_id') }}</small>
        </div>
        <div class="field-group">
            <label class="field-label">Sales Tax</label>
            <BaseSelect
                v-model="form.sale_tax_id"
                :options="salesTaxOptions"
                optionLabel="label"
                optionValue="value"
                filter
                :class="{ 'p-invalid': form.errorFor('sale_tax_id') }"
            />
            <small v-if="form.errorFor('sale_tax_id')" class="field-error">{{ form.errorFor('sale_tax_id') }}</small>
        </div>
        <div class="field-group">
            <BaseInput 
                v-model="form.stock_alert" 
                label="Stock Alert Qty"
                type="number"
                :error="form.errorFor('stock_alert')"
            />
        </div>
        <div class="field-group">
            <BaseInput 
                v-model="form.conversion_quantity" 
                label="Conversion Qty"
                type="number"
                :error="form.errorFor('conversion_quantity')"
            />
        </div>
    </div>
    <div class="expansion-actions">
        <div class="flex items-center gap-2 mr-6">
            <ToggleSwitch v-model="form.is_service" />
            <span class="text-xs font-semibold text-slate-500 uppercase">Is Service</span>
        </div>
        <div class="flex items-center gap-2 mr-auto">
            <ToggleSwitch v-model="form.status" />
            <span class="text-xs font-semibold text-slate-500 uppercase">Active</span>
        </div>
        <div class="flex items-center gap-2 mr-auto">
            <ToggleSwitch v-model="form.tax_mode" />
            <span class="text-xs font-semibold uppercase" :class="form.tax_mode ? 'text-indigo-500' : 'text-slate-400'">
                {{ form.tax_mode ? 'Tax Inclusive' : 'Tax Exclusive' }}
            </span>
        </div>
        <BaseFormActions
            label="Update Product"
            cancelLabel="Cancel"
            :loading="form.processing"
            @submit="submit"
            @reset="form.reset(); form.clearErrors()"
        />
    </div>
</template>

<style scoped>
.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.field-error { font-size: 11px; color: #e11d48; }
.expansion-actions { display: flex; gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #e0e7ff; align-items: center; }
</style>
