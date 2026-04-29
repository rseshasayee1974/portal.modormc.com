<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import WorkOrderCreateForm from './components/WorkOrderCreateForm.vue';
import WorkOrderDataTable from './components/WorkOrderDataTable.vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

defineProps<{
    workOrders: any[];
    plants: any[];
    customers: any[];
    sites: any[];
    mixDesigns: any[];
    statuses: { label: string; value: number }[];
    activePlantId: number;
    nextReference: string;
}>();
</script>

<template>
    <AppLayout title="Work Orders">
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
                        <h1 class="text-xl font-bold tracking-tight">Work Order Control Room</h1>
                        <p class="mt-1 text-xs text-slate-200">Create at top, monitor below, and click row to edit inline.</p>
                    </div>
                </div>
            </div>

            <WorkOrderCreateForm
                :customers="customers"
                :sites="sites"
                :mixDesigns="mixDesigns"
                :statuses="statuses"
                :activePlantId="activePlantId"
                :nextReference="nextReference"
            />

            <WorkOrderDataTable
                :workOrders="workOrders"
                :customers="customers"
                :sites="sites"
                :mixDesigns="mixDesigns"
                :statuses="statuses"
            />
        </div>
    </AppLayout>
</template>
