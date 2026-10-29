
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Tag from 'primevue/tag';
import ProductEditForm from './ProductEditForm.vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { usePermissions } from '@/Composables/usePermissions';

const { can, isSuperAdmin, isAdmin, isSassOwner } = usePermissions();

const isSystemAdmin = computed(() => {
    return Boolean(isSuperAdmin.value || isAdmin.value || isSassOwner.value);
});

interface Product {
    id: number;
    title: string;
    code: string | null;
    category_id: number | null;
    category?: { name?: string; title?: string } | null;
    unit_id: number | null;
    unit?: { unit_code?: string; title?: string } | null;
    sales_price: number;
    purchase_price: number;
    status: boolean | number;
    hsn_code?: string;
    material_code?: string;
    tax_mode?: boolean;
    purchase_tax_id?: number | null;
    sale_tax_id?: number | null;
    is_service?: boolean;
    product_type?: string;
    is_in_use?: boolean;
    opening_quantity?: number;
    current_quantity?: number;
    can_update?: boolean;
}

interface Option {
    id: number;
    name?: string;
    title?: string;
    unit_code?: string;
}

const props = defineProps<{
    products: Product[];
    categories: Option[];
    units: Option[];
    purchaseTaxes?: Option[];
    saleTaxes?: Option[];
    productTypes?: string[];
}>();

const filterCategory = ref<number | null>(null);
const perPage = ref(30);
const expandedRows = ref<Record<number, boolean>>({});
const filters = ref({
    global: { value: null, matchMode: 'contains' },
    category_id: { value: null, matchMode: 'equals' },
});

const filteredProducts = computed(() => {
    if (!filterCategory.value) return props.products;
    return props.products.filter(p => p.category_id === filterCategory.value);
});

const unitLabel = (product: Product) => product.unit?.unit_code ?? product.unit?.title ?? '—';
const categoryLabel = (product: Product) => product.category?.name ?? product.category?.title ?? '—';

const toast = useToast();
const openingProduct = ref<Product | null>(null);
const openingDialogVisible = ref(false);
const openingForm = useForm({ opening_quantity: 0 });
const projectedQuantity = computed(() => Number(openingProduct.value?.current_quantity ?? 0)
    + Number(openingForm.opening_quantity || 0) - Number(openingProduct.value?.opening_quantity ?? 0));

const editOpeningQuantity = (product: Product) => {
    openingProduct.value = product;
    openingForm.opening_quantity = Number(product.opening_quantity ?? 0);
    openingForm.clearErrors();
    openingDialogVisible.value = true;
};

const saveOpeningQuantity = () => {
    if (!openingProduct.value || openingForm.processing) return;
    openingForm.patch(route('products.opening-quantity', openingProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            openingDialogVisible.value = false;
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Opening quantity updated successfully.', life: 2000 });
        },
    });
};

const onSaved = () => {
    expandedRows.value = {};
};

const onRowCollapse = () => {
    expandedRows.value = {};
};

const watchFilters = watch([filters, filterCategory], () => {
    onRowCollapse();
}, { deep: true });
</script>

<template>
    <div class="unit-table-card">
        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            :value="filteredProducts"
            dataKey="id"
            v-model:rows="perPage"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            stripedRows
            class="unit-datatable"
            showSearch
            :globalFilterFields="['title', 'code', 'hsn_code', 'product_type']"
            showSerial
            heading="Products Directory"
            headingIcon="CubeIcon"
            showExport
            exportFilename="products-catalog"
        >
            <template #toolbar>
                <BaseSelect
                    v-model="filterCategory"
                    :options="categories"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="All Categories"
                    class="!h-9 !text-xs !w-48"
                    clearable
                />
            </template>
            <Column field="code" header="Code" sortable style="width: 120px">
                <template #body="{ data }">
                    <code class="code-chip">{{ data.code || '—' }}</code>
                </template>
            </Column>

            <Column field="title" header="Product" sortable>
                <template #body="{ data }">
                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-800 text-sm">{{ data.title }}</span>
                        <span class="text-[10px] text-gray-400 bg-gray-100 p-1 rounded w-fit font-black uppercase tracking-widest">{{data.hsn_code }}</span>
                    </div>
                </template>
            </Column>
            <Column field="category_id" header="Category" sortable style="width: 120px">
                <template #body="{ data }">
                    <span class="text-md  text-gray-800">{{  categoryLabel(data)  }}</span>
                </template>
            </Column>

             <Column field="product_type" header="Type" sortable style="width: 120px">
                <template #body="{ data }">
                    <span class="text-md  text-gray-800">{{ data.product_type }}</span>
                </template>
            </Column>
            <Column field="sales_price" header="Sale Price" sortable style="width: 170px">
                <template #body="{ data }">
                    <span class=" text-gray-800">₹{{ Number(data.sales_price).toFixed(2) }}</span>
                    <span class="text-[10px] text-gray-400 ml-1">/ {{ unitLabel(data) }}</span><br/>
                    <span class="text-[10px] bg-indigo-100 uppercase p-1 rounded w-fit text-indigo-800">{{ data.tax_mode ? 'Inclusive' : 'Exclusive' }}</span>
                </template>
            </Column>

            <Column field="opening_quantity" header="Opening Qty" sortable style="width: 160px">
                <template #body="{ data }">
                    <span v-if="data.is_service">—</span>
                    <div v-else class="flex flex-col items-start gap-1">
                        <span>{{ Number(data.opening_quantity ?? 0).toFixed(2) }} {{ unitLabel(data) }}</span>
                        <Button
                            v-if="can('PRODUCT.update') && data.can_update"
                            label="Set Opening Qty"
                            icon="pi pi-pencil"
                            text size="small"
                            :disabled="!data.unit_id"
                            @click.stop="editOpeningQuantity(data)"
                        />
                    </div>
                </template>
            </Column>

            <Column field="status" header="Status" sortable style="width: 120px">
                <template #body="{ data }">
                    <Tag
                        :value="Boolean(data.status) ? 'Active' : 'Inactive'"
                        :severity="Boolean(data.status) ? 'success' : 'secondary'"
                        rounded
                        pt:root:style="font-size:10px; font-weight:700"
                    />
                </template>
            </Column>

            <Column header="Action" style="width: 26px; text-align: right">
                <template #body="{ data }">
                    <BaseDeleteButton
                        v-if="can('PRODUCT.delete')"
                        :url="route('products.destroy', data.id)"
                        :title="data.is_in_use && isSystemAdmin ? 'Force Delete Product?' : 'Delete product?'"
                        :text="data.is_in_use && isSystemAdmin 
                            ? 'Warning: This product is used in an active mix design associated with a batch. Deleting it will affect related production records.' 
                            : `${data.title} will be permanently removed.`"
                        :disabled="Boolean(data.is_in_use && !isSystemAdmin)"
                    />
                </template>
            </Column>

            <template #expansion="{ data }">
                <div class="expansion-panel">
                    <div class="expansion-label">
                        <i class="pi pi-pen-to-square text-indigo-500 text-xs"></i>
                        <span>Editing — <strong>{{ data.title }}</strong></span>
                    </div>
                    <ProductEditForm
                        :product="data"
                        :categories="categories"
                        :units="units"
                        :purchaseTaxes="purchaseTaxes"
                        :saleTaxes="saleTaxes"
                        :productTypes="productTypes"
                        @cancel="onRowCollapse"
                        @saved="onSaved"
                    />
                </div>
            </template>

            <template #empty>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="pi pi-filter-slash text-2xl text-slate-300"></i>
                    </div>
                    <p class="empty-title">No products found</p>
                    <p class="empty-sub">Try clearing your search or changing filters</p>
                </div>
            </template>
        </BaseDataTable>
        <Dialog v-model:visible="openingDialogVisible" header="Set Opening Quantity" modal
            :style="{ width: '28rem' }" :breakpoints="{ '640px': '95vw' }"
            :closable="!openingForm.processing" :closeOnEscape="!openingForm.processing">
            <form @submit.prevent="saveOpeningQuantity" class="flex flex-col gap-4">
                <p class="font-semibold">{{ openingProduct?.title }}</p>
                <p class="text-sm text-slate-500">
                    Enter the opening stock in {{ openingProduct ? unitLabel(openingProduct) : '' }}.
                    Available stock changes by the difference from the previous opening quantity.
                </p>
                <BaseInput v-model="openingForm.opening_quantity" label="Opening Quantity"
                    type="number" :min="0" step="0.01" required
                    :disabled="openingForm.processing" :error="openingForm.errors.opening_quantity" />
                <p class="text-sm text-slate-600">Available stock after saving: {{ projectedQuantity.toFixed(2) }}</p>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" :disabled="openingForm.processing"
                        @click="openingDialogVisible = false" />
                    <Button label="Save Opening Quantity" type="submit" :loading="openingForm.processing" />
                </div>
            </form>
        </Dialog>
    </div>
</template>

<style scoped>
</style>
