
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Tag from 'primevue/tag';
import ProductEditForm from './ProductEditForm.vue';

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

const onSaved = () => {
    expandedRows.value = {};
};

const onRowCollapse = () => {
    expandedRows.value = {};
};

watch([filters, filterCategory], () => {
    onRowCollapse();
}, { deep: true });
</script>

<template>
    <div class="unit-table-card">
        <div class="unit-toolbar">
            <div class="flex items-center gap-2">
                <span class="toolbar-accent"></span>
                <span class="toolbar-title">Products Directory</span>
                <span class="toolbar-count">{{ products.length }} records</span>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <BaseSelect
                    v-model="filterCategory"
                    :options="categories"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="All Categories"
                    class="filter-select"
                />
            </div>
        </div>

        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            :value="filteredProducts"
            dataKey="id"
            v-model:rows="perPage"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            stripedRows
            class="unit-datatable bg-gray-400"
            showSearch
            :globalFilterFields="['title', 'code', 'hsn_code', 'product_type']"
            showSerial
        >
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
                        :url="route('products.destroy', data.id)"
                        title="Delete product?"
                        :text="`${data.title} will be permanently removed.`"
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
    </div>
</template>

<style scoped>
</style>
