<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import CreateBillingForm from './CreateBillingForm.vue';
import BillingIndexList from './components/BillingIndexList.vue';

const props = defineProps<{
    invoices: any[];
    patrons: any[];
    taxes: any[]; 
    accounts: any[];
    products: any[];
    units: any[];
    instant_invoice_patron: number | boolean;
}>();

</script>

<template>
    <AppLayout title="Billings">
        <template #header>
            <ModuleSubTopNav />
        </template>
        
        <Head title="Billing Management" />
        <Toast />

        <div class="min-h-screen py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
                
                <section>
                    <CreateBillingForm 
                        :patrons="patrons"
                        :taxes="taxes" 
                        :accounts="accounts"
                        :products="products"
                        :units="units"
                        :instant_invoice_patron="instant_invoice_patron"
                    />
                </section>

                <section>
                    <BillingIndexList 
                        :invoices="invoices"
                        :patrons="patrons"
                        :taxes="taxes" 
                        :accounts="accounts"
                        :products="products"
                        :units="units"
                    />
                </section>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Page-specific premium transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
