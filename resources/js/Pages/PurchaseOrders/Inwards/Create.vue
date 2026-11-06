<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import InwardCreateForm from './components/InwardCreateForm.vue';

defineProps<{ purchase_order?: any; purchaseOrders: any[]; directProducts?: any[]; directVendors?: any[]; vehicles?: any[]; units?: any[] }>();
</script>

<template>
    <AppLayout title="Record Stock Inward">
        <template #header><ModuleSubTopNav /></template>
        <InwardCreateForm :directProducts="directProducts" :directVendors="directVendors" :purchase_order="purchase_order" :purchaseOrders="purchaseOrders" :vehicles="vehicles" :units="units" />
    </AppLayout>
</template>
