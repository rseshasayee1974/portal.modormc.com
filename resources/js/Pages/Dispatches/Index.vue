<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import DispatchCreateForm from './components/DispatchCreateForm.vue';
import DispatchEditForm from './components/DispatchEditForm.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import { TruckIcon, ListBulletIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    dispatches: any[];
    workOrders: any[];
    batches: any[];
    trucks: any[];
    drivers: any[];
}>();

const expandedRows = ref<any[]>([]);

const toggleRow = (data: any) => {
    const index = expandedRows.value.findIndex(row => row.id === data.id);
    if (index > -1) {
        expandedRows.value.splice(index, 1);
    } else {
        expandedRows.value = [data]; // Only expand one row at a time for clarity
    }
};

const onSaved = () => {
    expandedRows.value = [];
};
</script>

<template>
    <AppLayout title="Dispatches">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="px-4 py-5 space-y-6 md:px-6">
            <!-- Header Banner -->
            <div class="rounded-xl border border-slate-200 bg-gradient-to-r from-slate-900 via-emerald-900 to-emerald-700 px-5 py-4 text-white shadow-lg">
                <div class="flex items-start gap-4">
                    <div class="rounded-lg bg-white/10 p-2 text-emerald-100 ring-1 ring-white/20">
                        <TruckIcon class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight">Dispatch Execution Board</h1>
                        <p class="mt-1 text-xs text-emerald-100 opacity-80">Manage real-time truck dispatches and delivery tracking.</p>
                    </div>
                </div>
            </div>

            <!-- Create Section -->
            <DispatchCreateForm
                :workOrders="workOrders"
                :batches="batches"
                :trucks="trucks"
                :drivers="drivers"
            />

            <!-- List Section -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
                    <ListBulletIcon class="h-4 w-4 text-slate-500" />
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">Dispatch Log</h2>
                </div>

                <BaseDataTable
                    v-model:expandedRows="expandedRows"
                    :value="dispatches"
                    dataKey="id"
                    :rows="10"
                    show-search
                    search-placeholder="Search by WO or Truck..."
                    search-fields="work_order.order_no,truck.registration,driver.legal_name"
                >
                    <template #expansion="{ data }">
                        <BaseExpansionPanel :title="`Edit Dispatch: ${data.work_order?.order_no}`">
                            <DispatchEditForm
                                :dispatch="data"
                                :workOrders="workOrders"
                                :batches="batches"
                                :trucks="trucks"
                                :drivers="drivers"
                                @saved="onSaved"
                                @cancel="expandedRows = []"
                            />
                        </BaseExpansionPanel>
                    </template>

                    <Column field="dispatch_time" header="Time" class="w-32">
                        <template #body="{ data }">
                            <div class="text-xs font-medium text-slate-700">
                                {{ data.dispatch_time ? new Date(data.dispatch_time).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-' }}
                            </div>
                        </template>
                    </Column>

                    <Column field="work_order.order_no" header="Work Order" sortable>
                        <template #body="{ data }">
                            <button 
                                class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600 hover:bg-emerald-100 hover:text-emerald-700 transition-colors"
                                @click="toggleRow(data)"
                            >
                                {{ data.work_order?.order_no || '-' }}
                            </button>
                        </template>
                    </Column>

                    <Column field="batch.batch_no" header="Batch">
                        <template #body="{ data }">
                            <div class="text-xs font-bold text-slate-600">
                                B{{ data.batch?.batch_no || '-' }}
                            </div>
                        </template>
                    </Column>

                    <Column field="truck.registration" header="Truck">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-700">{{ data.truck?.registration || '-' }}</span>
                                <span class="text-[10px] text-slate-400">{{ data.driver?.legal_name || 'No Driver' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="delivered_qty" header="Qty" class="text-right">
                        <template #body="{ data }">
                            <div class="text-xs font-bold text-emerald-600">
                                {{ data.delivered_qty }} m³
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </div>
        </div>
    </AppLayout>
</template>
