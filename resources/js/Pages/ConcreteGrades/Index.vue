<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import GradeCreateForm from './Partials/GradeCreateForm.vue';
import GradeTable from './Partials/GradeTable.vue';

interface GradeItem {
    id?: number;
    product_id: number | null;
    quantity: number;
    product?: any;
}

interface Grade {
    id: number;
    name: string;
    concrete_code: string | null;
    concrete_ratio: string | null;
    cement_ratio: number | null;
    sand_ratio: number | null;
    aggregate_ratio: number | null;
    status: boolean;
    items: GradeItem[];
}

defineProps<{
    grades: Grade[];
    products: any[];
}>();

</script>

<template>
    <AppLayout title="Concrete Grade Master">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Head title="Concrete Grade Configuration" />
        <Toast />

        <div class="page-container">
            <!-- <div class="page-heading">
                <div class="flex items-center gap-4">
                    <div class="page-logo">
                        <i class="pi pi-beaker text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="page-title">Grade Specification Master</h1>
                        <p class="page-sub">Establish and manage concrete mix recipes and weight matrix</p>
                    </div>
                </div>
            </div> -->

            <GradeCreateForm :products="products" />
            
            <GradeTable :grades="grades" :products="products" />
        </div>
    </AppLayout>
</template>

<style scoped>
</style>
