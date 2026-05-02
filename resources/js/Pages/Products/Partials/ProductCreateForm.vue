<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@/Composables/useForm';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

interface Option {
    id: number;
    name?: string;
    title?: string;
    unit_code?: string;
}

const props = defineProps<{
    categories: Option[];
    units: Option[];
    purchaseTaxes?: Option[];
    saleTaxes?: Option[];
    productTypes?: string[];
}>();

const toast = useToast();
const isOpen = ref(true);

const form = useForm({
    title: '',
    code: '',
    category_id: props.categories.length > 0 ? props.categories[0].id : null,
    unit_id: props.units.length > 0 ? props.units[0].id : null,
    purchase_price: 0,
    sales_price: 0,
    status: true,
    hsn_code: '',
    material_code: '',
    tax_mode:  false,
    purchase_tax_id: null as number | null,
    sale_tax_id: null as number | null,
    is_service: false,
    product_type: props.productTypes && props.productTypes.length > 0 ? props.productTypes[0] : 'Purchase',
    stock_alert: 0,
    conversion_quantity: 0,
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
    (props.saleTaxes || []).map((t) => ({ label: (t as any).tax_name ?? t.name ?? t.title ?? '', value: t.id }))
);

const productTypeOptions = computed(() =>
    (props.productTypes || []).map((t) => ({ label: t, value: t }))
);

const toggle = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        form.reset();
        form.clearErrors();
    }
};

const submit = () => {
    form.post(route('products.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Created', detail: 'Product created successfully.', life: 3000 });
            form.reset();
            isOpen.value = false; // Reset the panel state
            setTimeout(() => { isOpen.value = true; }, 500);
        },
    });
};
</script>

<template>
    <div class="create-panel" :class="{ 'create-panel--open': isOpen }">
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <i class="pi pi-box text-indigo-500 text-sm"></i>
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Create Product</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Add catalog item with pricing and unit</p>
                </div>
            </div>
            <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': isOpen }">
                <i class="pi pi-plus text-[10px]"></i>
            </div>
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <div class="grid grid-cols-12   gap-4">
                    <div class="field-group col-span-12 md:col-span-2">
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
                    <div class="col-span-12 md:col-span-2">
                        <BaseInput 
                            v-model="form.title" 
                            label="Product Name"
                            required
                            :error="form.errorFor('title')"
                        />
                    </div>
                    
                    <div class="field-group col-span-12 md:col-span-2">
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
                    <div class="col-span-12 md:col-span-2">
                        <BaseInput 
                            v-model="form.purchase_price" 
                            label="Purchase Price"
                            required
                            type="number"
                            :error="form.errorFor('purchase_price')"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-2">
                        <BaseInput 
                            v-model="form.sales_price" 
                            label="Sale Price"
                            required
                            type="number"
                            :error="form.errorFor('sales_price')"
                        />
                    </div>
                
                    <div class="field-group col-span-12 md:col-span-2">
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
                    <div class="col-span-12 md:col-span-2">
                        <BaseInput 
                            v-model="form.hsn_code" 
                            label="HSN Code"
                            :error="form.errorFor('hsn_code')"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-2">
                        <BaseInput 
                            v-model="form.material_code" 
                            label="Material Code"
                            :error="form.errorFor('material_code')"
                        />
                    </div>
                    <div class="field-group col-span-12 md:col-span-2">
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
                    <div class="field-group col-span-12 md:col-span-2">
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
                    <div class="col-span-12 md:col-span-2">
                        <BaseInput 
                            v-model="form.stock_alert" 
                            label="Stock Alert Qty"
                            type="number"
                            :error="form.errorFor('stock_alert')"
                        />
                    </div>
                    <div class="col-span-12 md:col-span-2">
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
                    label="Add Product"
                    cancelLabel="Reset"
                    :loading="form.processing"
                    @submit="submit"
                    @reset="form.reset(); form.clearErrors()"
                />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.create-panel { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
.create-panel--open { border-color: #c7d2fe; box-shadow: 0 0 0 3px rgba(99,102,241,0.07), 0 1px 3px rgba(0,0,0,0.05); }
.create-panel__header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 14px 18px; background: transparent; border: none; cursor: pointer; text-align: left; transition: background 0.15s ease; }
.create-panel__header:hover { background: #f8fafc; }
.create-panel__icon { width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, #eef2ff, #e0e7ff); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.create-panel__toggle { width: 26px; height: 26px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; color: #6366f1; transition: background 0.2s, transform 0.25s ease; }
.create-panel__toggle--open { transform: rotate(45deg); background: #6366f1; color: white; }
.create-panel__body { padding: 20px 18px; border-top: 1px solid #eef2ff; background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%); }
.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.field-error { font-size: 11px; color: #e11d48; }
.expansion-actions { display: flex; gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #e0e7ff; align-items: center; }
.panel-slide-enter-active { transition: all 0.22s cubic-bezier(0.4,0,0.2,1); }
.panel-slide-leave-active { transition: all 0.16s ease; }
.panel-slide-enter-from, .panel-slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
