<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import InvoiceCreateForm from './createinvoiceform.vue';
import InvoiceIndexList from './components/InvoiceIndexList.vue';

const props = defineProps<{
    invoices: any[];
    patrons: any[];
    taxes: any[]; 
    accounts: any[];
    mixdesign: any[];
    units: any[];
    machines: any[];
    instant_invoice_patron: number | boolean;
    next_invoice_number?: string;
    next_invoice_details?: any;
}>();

</script>

<template>
    <AppLayout title="Invoices">
        <template #header>
            <ModuleSubTopNav />
        </template>
        
        <Head title="Invoice Management" />
        <Toast />

        <div class="min-h-screen py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
                
                <section>
                    <InvoiceCreateForm 
                        :patrons="patrons"
                        :taxes="taxes" 
                        :accounts="accounts"
                        :mixdesign="mixdesign"
                        :units="units"
                        :instant_invoice_patron="instant_invoice_patron"
                        :next_invoice_number="next_invoice_number"
                        :next_invoice_details="next_invoice_details"
                    />
                </section>

                <section>
                    <InvoiceIndexList 
                        :invoices="invoices"
                        :patrons="patrons"
                        :taxes="taxes" 
                        :accounts="accounts"
                        :mixdesign="mixdesign"
                        :units="units"
                        :machines="machines"
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
