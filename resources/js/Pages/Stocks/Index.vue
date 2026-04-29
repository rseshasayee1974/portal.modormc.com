<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import StockTable from './Partials/StockTable.vue';

defineProps<{
    stocks: any[];
    plants: any[];
}>();
</script>

<template>
    <AppLayout title="Stock Quantities">
        <template #header>
            <ModuleSubTopNav />
        </template>
        
        <Head title="Stock Inventory" />
        
        <div class="page-container mt-6">
            <StockTable :stocks="stocks" :plants="plants" />
        </div>
    </AppLayout>
</template>

<style scoped>
.page-container {
    padding: 0 24px 24px 24px;
    max-width: 1400px;
    margin: 0 auto;
}
</style>
