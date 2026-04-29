<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BatchCreateForm from './components/BatchCreateForm.vue';
import BatchEditForm from './components/BatchEditForm.vue';
import DispatchSection from './components/DispatchSection.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import BaseButton from '@/Components/Base/BaseButton.vue';
import { CubeIcon, ListBulletIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    batches: any[];
    workOrders: any[];
    trucks: any[];
    transporters: any[];
    personnel: any[];
    taxes: any[];
    products: any[];
    loading_sites: any[];
    unloading_sites: any[];
    uoms: any[];
    statuses: { label: string; value: number }[];
    schemaWarning?: string | null;
    nextBatchNo: number;
}>();

const dropdownData = computed(() => ({
    trucks: props.trucks,
    transporters: props.transporters,
    personnel: props.personnel,
    taxes: props.taxes,
    uoms: props.uoms,
    unloading_sites : props.unloading_sites,
    loading_sites : props.loading_sites,
}));

const filters = ref({
    global: { value: null, matchMode: 'contains' },
    status: { value: null, matchMode: 'equals' },
});

const expandedRows = ref({});
const first = ref(0);
const rows = ref(30);
const entriesOptions = [
    { label: '30', value: 30 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

const toggleExpand = (data: any) => {
    if (expandedRows.value[data.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [data.id]: true };
    }
};

const collapseExpandedRows = () => {
    expandedRows.value = {};
};

const statusSeverity = (status: number) => {
    if (status === 4) return 'success';
    if (status === 2 || status === 3) return 'info';
    if (status === 5) return 'danger';
    return 'warn';
};

const statusLabel = (status: number) => props.statuses.find((entry) => entry.value === status)?.label ?? 'Unknown';

const destroy = (row: any) => {
    Swal.fire({
        title: 'Delete batch?',
        text: `Batch #${row.batch_no} will be deleted.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('batches.destroy', row.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Batch deleted successfully.',
                    showConfirmButton: false,
                    timer: 2500,
                });
            }
        });
    });
};
</script>

<template>
    <AppLayout title="Batches">
        <div class="py-2 px-4">
            <ModuleSubTopNav />

            <div class="max-w-7xl mx-auto mt-4 space-y-4">
                <div v-if="schemaWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                    {{ schemaWarning }}
                </div>

                <BatchCreateForm
                    :workOrders="workOrders"
                    :trucks="trucks"
                    :transporters="transporters"
                    :personnel="personnel"
                    :products="products"
                    :uoms="uoms"
                    :unloading_sites="unloading_sites"
                    :loading_sites="loading_sites"
                    :taxes="taxes"
                    :statuses="statuses"
                    :nextBatchNo="nextBatchNo"
                />

                <hr class="border-slate-200 border-dashed" />

                <div class="bg-white shadow-xl sm:rounded-lg p-8">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-lg bg-cyan-50 flex items-center justify-center shadow-sm border border-cyan-100/50">
                                <ListBulletIcon class="w-5 h-5 text-cyan-600" />
                            </div>
                            <div>
                                <h3 class="text-md font-semibold text-slate-800 uppercase">List Of Batches</h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] leading-none">Batch Execution Directory</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                            <BaseSelect 
                                v-model="rows" 
                                :options="entriesOptions" 
                                optionLabel="label" 
                                optionValue="value"
                                class="!h-10 w-20 flex items-center justify-center font-bold text-xs"
                                :pt="{
                                    root: { class: 'border border-slate-300 rounded-md' },
                                    label: { class: 'text-xs p-2' }
                                }"
                            />
                            <BaseInput
                                v-model="filters.global.value"
                                placeholder="Search order/batch/truck..."
                                inputClass="!h-9 !w-72"
                            />
                            <BaseSelect
                                v-model="filters.status.value"
                                :options="[{label: 'All Statuses', value: null}, ...statuses]"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Filter status"
                                class="w-44"
                            />
                        </div>
                    </div>

                    <BaseDataTable
                        :value="batches"
                        v-model:first="first"
                        v-model:rows="rows"
                        v-model:filters="filters"
                        v-model:expandedRows="expandedRows"
                        dataKey="id"
                        paginator
                        stripedRows
                        removableSort
                        rowHover
                        filterDisplay="menu"
                        class="cursor-pointer"
                        :globalFilterFields="['batch_no', 'work_order.order_no', 'truck.registration']"
                        showSerial
                        :showSearch="false"
                    >
                        <Column field="batch_no" header="Order / Batch" sortable>
                            <template #body="slotProps">
                                <div>
                                    <button
                                        class="text-indigo-700 font-inter text-sm font-semibold hover:underline"
                                        type="button"
                                        @click.stop="toggleExpand(slotProps.data)"
                                    >
                                        B{{ slotProps.data.batch_no }}
                                    </button>
                                    <div class="text-xs text-slate-500">{{ slotProps.data.work_order?.order_no || '-' }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="truck.registration" header="Truck" sortable>
                            <template #body="slotProps">
                                <span class="text-xs font-semibold text-slate-700">{{ slotProps.data.truck?.registration || '-' }}</span>
                            </template>
                        </Column>

                        <Column field="batch_size" header="Batch Size" sortable>
                            <template #body="slotProps">
                                <span class="text-xs font-bold text-slate-700">{{ slotProps.data.batch_size }} m³</span>
                            </template>
                        </Column>

                        <Column header="Materials">
                            <template #body="slotProps">
                                <span class="text-xs text-slate-500">{{ slotProps.data.materials?.length || 0 }} line(s)</span>
                            </template>
                        </Column>

                        <Column field="status" header="Status" sortable>
                            <template #body="slotProps">
                                <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" rounded />
                            </template>
                        </Column>

                        <Column header="Actions">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <BaseButton
                                        icon="pi pi-trash"
                                        severity="danger"
                                        variant="text"
                                        rounded
                                        @click.stop="destroy(slotProps.data)"
                                        title="Delete"
                                    />
                                </div>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <div class="p-1 bg-slate-50/50 border-y border-slate-100">
                                <TabView class="compact-tabs">
                                    <TabPanel header="Batching">
                                        <div class="">
                                            <BatchEditForm
                                                :batch="slotProps.data"
                                                :workOrders="workOrders"
                                                :trucks="trucks"
                                                :transporters="transporters"
                                                :personnel="personnel"
                                                :products="products"
                                                :uoms="uoms"
                                                :statuses="statuses"
                                                @saved="collapseExpandedRows"
                                                @cancel="collapseExpandedRows"
                                            />
                                        </div>
                                    </TabPanel>
                                    <TabPanel header="Dispatching">
                                        <div class="">
                                            <DispatchSection 
                                                :batch="slotProps.data" 
                                                :workOrder="slotProps.data.work_order"
                                                :dropdownData="dropdownData"
                                            />
                                        </div>
                                    </TabPanel>
                                </TabView>
                            </div>
                        </template>
                    </BaseDataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
