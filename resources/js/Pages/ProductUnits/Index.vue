<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import UnitCreateForm from './Partials/UnitCreateForm.vue';
import UnitTable from './Partials/UnitTable.vue';

interface ProductUnit {
    id: number;
    unit_type: string;
    unit_name: string;
    unit_code: string | null;
}

defineProps<{
    productUnits: ProductUnit[];
    unitTypes: string[];
}>();
</script>

<template>
    <AppLayout title="Product Units">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Toast />

        <div class="page-container">
            <!-- Page heading -->
            <div class="page-heading">
                <div>
                    <h1 class="page-title">Measurement Units</h1>
                    <p class="page-sub">Manage inventory units &mdash; Weight, Volume, Distance and more</p>
                </div>
                <div class="page-stat">
                    <i class="pi pi-database text-indigo-400 text-lg"></i>
                    <span>{{ productUnits.length }} total units</span>
                </div>
            </div>

            <!-- Create Panel (top) -->
            <UnitCreateForm :unitTypes="unitTypes" />

            <!-- Data Table (below) -->
            <UnitTable :productUnits="productUnits" :unitTypes="unitTypes" />
        </div>
    </AppLayout>
</template>

<style scoped>
</style>
