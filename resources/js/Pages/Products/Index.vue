<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import ProductCreateForm from './Partials/ProductCreateForm.vue';
import ProductTable from './Partials/ProductTable.vue';

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
}

interface Option {
    id: number;
    name?: string;
    title?: string;
    unit_code?: string;
}

defineProps<{
    products: Product[];
    categories: Option[];
    units: Option[];
    purchaseTaxes?: Option[];
    saleTaxes?: Option[];
    productTypes?: string[];
}>();
</script>

<template>
    <AppLayout title="Products">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Products" />
        <Toast />

        <div class="page-container">
            <!-- <div class="page-heading">
                <div class="flex items-center gap-4">
                    <div class="page-logo">
                        <i class="pi pi-box text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="page-title">Product Catalog</h1>
                        <p class="page-sub">Manage item definitions, pricing and units</p>
                    </div>
                </div>
                <div class="page-stat">
                    <i class="pi pi-database text-indigo-400 text-lg"></i>
                    <span>{{ products.length }} total products</span>
                </div>
            </div> -->

            <ProductCreateForm :categories="categories" :units="units" :purchaseTaxes="purchaseTaxes" :saleTaxes="saleTaxes" :productTypes="productTypes" />
            <ProductTable :products="products" :categories="categories" :units="units" :purchaseTaxes="purchaseTaxes" :saleTaxes="saleTaxes" :productTypes="productTypes" />
        </div>
    </AppLayout>
</template>

<style scoped>

</style>
