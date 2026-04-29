<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import PurchaseOrderForm from './PurchaseOrderForm.vue';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    entities: Array,
    plants: Array,
    vendors: Array,
    vehicles: Array,
    currencies: Array,
    taxes: Array,
    products: Array,
    productUnits: Array,
});
</script>

<template>
    <AppLayout title="Create Purchase Order">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-12 p-4">
            <div class="max-w-7xl mx-auto">
                <PurchaseOrderForm 
                    :entities="entities" 
                    :plants="plants" 
                    :vendors="vendors" 
                    :vehicles="vehicles" 
                    :currencies="currencies" 
                    :taxes="taxes" 
                    :products="products" 
                    :productUnits="productUnits"
                />
            </div>
        </div>
    </AppLayout>
</template>

