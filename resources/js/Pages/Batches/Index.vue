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
    customers: any[];
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
    batchingSettings: any;
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
    'work_order.id': { value: null, matchMode: 'equals' },
    'work_order.customer.id': { value: null, matchMode: 'equals' },
});

const dateFrom = ref(null);
const dateTo = ref(null);

const filteredBatches = computed(() => {
    let result = props.batches;
    
    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        result = result.filter(b => new Date(b.created_at) >= from);
    }
    
    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        result = result.filter(b => new Date(b.created_at) <= to);
    }
    
    return result;
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
const downloadPdf = (id: number) => {
    window.open(route('batches.download', id), '_blank');
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

                <div class="bg-white shadow-xl sm:rounded-lg">

                    <BaseDataTable
                        :value="filteredBatches"
                        v-model:first="first"
                        v-model:rows="rows"
                        v-model:filters="filters"
                        v-model:expandedRows="expandedRows"
                        v-model:dateFrom="dateFrom"
                        v-model:dateTo="dateTo"
                        dataKey="id"
                        paginator
                        stripedRows
                        removableSort
                        rowHover
                        filterDisplay="menu"
                        class="cursor-pointer"
                        :globalFilterFields="['batch_no', 'work_order.order_no', 'truck.registration', 'work_order.customer.legal_name', 'work_order.mix_design.design_name']"
                        showSerial
                        heading="List Of Batches"
                        headingIcon="ListBulletIcon"
                        :showSearch="true"
                        showExport
                        exportFilename="batch-report"
                    >
                        <template #filters>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Filter by Work Order</label>
                                <BaseSelect 
                                    v-model="filters['work_order.id'].value"
                                    :options="[{order_no: 'All Orders', id: null}, ...workOrders]"
                                    optionLabel="order_no"
                                    optionValue="id"
                                    placeholder="Select Order"
                                    class="!h-9 !text-xs !rounded-lg"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Filter by Customer</label>
                                <BaseSelect 
                                    v-model="filters['work_order.customer.id'].value"
                                    :options="[{legal_name: 'All Customers', id: null}, ...customers]"
                                    optionLabel="legal_name"
                                    optionValue="id"
                                    placeholder="Select Customer"
                                    class="!h-9 !text-xs !rounded-lg"
                                    filter
                                />
                            </div>
                        </template>
                        <Column field="empty_time" header="Empty Time" sortable>
                            <template #body="slotProps">
                                <div v-if="slotProps.data.empty_time" class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-700">
                                        {{ new Date(slotProps.data.empty_time).toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium uppercase">
                                        {{ new Date(slotProps.data.empty_time).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                </div>
                                <span v-else class="text-xs text-slate-300 italic">N/A</span>
                            </template>
                        </Column>
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
                                    <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">{{ slotProps.data.work_order?.order_no || '-' }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="work_order.customer.legal_name" header="Customer" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-700">{{ slotProps.data.work_order?.customer?.legal_name || '-' }}</span>
                                    <span class="text-[10px] text-slate-400 font-medium uppercase">{{ slotProps.data.work_order?.site?.name || 'Main Site' }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column field="work_order.mix_design.design_name" header="Design" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-slate-800">{{ slotProps.data.work_order?.mix_design?.design_name || '-' }}</span>
                                    <span class="text-[10px] text-emerald-600 font-black tracking-tighter uppercase">{{ slotProps.data.work_order?.mix_design?.design_code || '-' }}</span>
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

                        

                        <Column field="status" header="Status" sortable>
                            <template #body="slotProps">
                                <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" rounded />
                            </template>
                        </Column>

                        <Column header="Actions">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <BaseButton
                                        icon="pi pi-eye"
                                        severity="secondary"
                                        variant="text"
                                        rounded
                                        @click.stop="router.get(route('batches.report', slotProps.data.id))"
                                        title="Preview Batch Sheet"
                                    />
                                    <BaseButton
                                        icon="pi pi-download"
                                        severity="info"
                                        variant="text"
                                        rounded
                                        @click.stop="downloadPdf(slotProps.data.id)"
                                        title="Download PDF"
                                    />
                                    <BaseButton
                                        v-if="slotProps.data.status < 3"
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
                                    <!-- v-if="slotProps.data.status < 3" -->
                                    <TabPanel header="Batching" >
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
                                                :settings="batchingSettings"
                                                @saved="collapseExpandedRows"
                                                @cancel="collapseExpandedRows"
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
