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
    trucks: any[];
    accounts: any[];
}>();

</script>

<template>
    <AppLayout title="Invoices">
        <template #header>
            <ModuleSubTopNav />
        </template>
        
        <Head title="Invoice Management" />
        <Toast />

        <div class="min-h-screen pb-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-10">
                
                <!-- v4: Invoice create form want to need for table top -->
                <section>
                    <InvoiceCreateForm 
                        :patrons="patrons"
                        :taxes="taxes"
                        :trucks="trucks"
                        :accounts="accounts"
                    />
                </section>

                <hr class="border-slate-200 border-dashed" />

                <!-- v4: and below datatable show -->
                <section>
                    <InvoiceIndexList 
                        :invoices="invoices"
                        :patrons="patrons"
                        :taxes="taxes"
                        :trucks="trucks"
                        :accounts="accounts"
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
