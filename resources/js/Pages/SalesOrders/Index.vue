<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import SalesOrderCreateForm from './components/SalesOrderCreateForm.vue';
import SalesOrderDataTable from './components/SalesOrderDataTable.vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

defineProps<{
    salesOrders: any[];
    plants: any[];
    customers: any[];
    sites: any[];
    mixDesigns: any[];
    customerPOs?: any[];
    statuses: { label: string; value: number }[];
    activePlantId: number;
    nextReference: string;
    concretePumpOptions?: any[];
    products?: any[];
    units?: any[];
    designTypes?: any[];
}>();
</script>

<template>
    <AppLayout title="Sales Orders">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="px-4 py-5 md:px-6 space-y-4">
            <div class="rounded-xl border border-slate-200 bg-gradient-to-r from-slate-900 via-indigo-900 to-sky-800 px-5 py-4 text-white shadow">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-white/10 p-2 text-indigo-100">
                        <ClipboardDocumentListIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight">Sales Order Control Room</h1>
                        <p class="mt-1 text-xs text-slate-200">Create at top, monitor below, and click row to edit inline.</p>
                    </div>
                </div>
            </div>

            <SalesOrderCreateForm
                :customers="customers"
                :sites="sites"
                :mixDesigns="mixDesigns"
                :customerPOs="customerPOs"
                :statuses="statuses"
                :activePlantId="activePlantId"
                :nextReference="nextReference"
                :concretePumpOptions="concretePumpOptions"
                :products="products"
                :units="units"
                :designTypes="designTypes"
            />

            <SalesOrderDataTable
                :salesOrders="salesOrders"
                :customers="customers"
                :sites="sites"
                :mixDesigns="mixDesigns"
                :customerPOs="customerPOs"
                :statuses="statuses"
                :concretePumpOptions="concretePumpOptions"
            />
        </div>
    </AppLayout>
</template>
