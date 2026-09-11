<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

// Components
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import InwardEditForm from './components/InwardEditForm.vue';
import InwardCreateForm from './components/InwardCreateForm.vue';

import { ArchiveBoxIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import PurchaseOrderPreviewDialog from '../components/PurchaseOrderPreviewDialog.vue';

const props = defineProps<{ inwards: any[]; purchaseOrders: any[]; vehicles: any[] }>();
// --- List Logic ---
const entriesPerPage = ref(30);
const expandedRows = ref<Record<string, boolean>>({});
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const dateFrom = ref(null);
const dateTo = ref(null);

const filteredInwards = computed(() => {
    let result = props.inwards;

    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        from.setHours(0, 0, 0, 0);
        result = result.filter(i => new Date(i.received_date) >= from);
    }

    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        result = result.filter(i => new Date(i.received_date) <= to);
    }

    return result;
});

const formatDate = (date: string) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
};

const previewVisible = ref(false);
const selectedOrder = ref(null);

const viewOrder = (order: any) => {
    selectedOrder.value = order;
    previewVisible.value = true;
};

const deleteInward = (inward: any) => {
    Swal.fire({
        title: 'Delete Inward Record?',
        text: "This will reverse the received quantity and adjust stock balances. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete Content'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('inwards.destroy', inward.id), {
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Record Deleted', showConfirmButton: false, timer: 1500 })
            });
        }
    });
};

</script>

<template>
    <AppLayout title="Inward Registry">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6 px-4 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-6">
                <InwardCreateForm :purchaseOrders="purchaseOrders" :vehicles="vehicles" embedded />

                <div class="space-y-4">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 px-1">
                        <div class="flex items-center gap-2">
                             <div class="w-1.5 h-6 bg-indigo-600 rounded-full"></div>
                             <h2 class="text-sm font-black text-slate-700 uppercase tracking-widest">Historical Inward Registry</h2>
                        </div>
                    </div>

                    <div class="bg-white rounded-sm shadow-sm border border-slate-200 overflow-hidden">
                        <BaseDataTable
                            :value="filteredInwards"
                            v-model:filters="filters"
                            v-model:rows="entriesPerPage"
                            v-model:dateFrom="dateFrom"
                            v-model:dateTo="dateTo"
                            v-model:expandedRows="expandedRows"
                            dataKey="id"
                            :rowsPerPageOptions="[30, 50, 100, 200]"
                            :globalFilterFields="['inward_no', 'order.po_number', 'product.title', 'order.vendor.legal_name']"
                            showSearch
                            showSerial
                            stripedRows
                            heading="Stock Inward Registry"
                            headingIcon="ArchiveBoxIcon"
                            showExport
                            exportFilename="stock-inward-report"
                        >
                            <template #toolbar>
                                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50 rounded-lg border border-indigo-100">
                                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ filteredInwards.length }} arrivals recorded</span>
                                </div>
                            </template>

                            <Column expander style="width: 3.5rem" />

                            <Column header="Inward #" sortable field="inward_no" style="min-width: 160px" class="py-4 px-4">
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-slate-700 font-inter tracking-tight">{{ slotProps.data.inward_no }}</span>
                                        <div class="flex items-center gap-1.5 mt-1 text-[9px] text-slate-400 font-bold uppercase">
                                            <CalendarDaysIcon class="w-3 h-3" />
                                            {{ formatDate(slotProps.data.received_date) }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Ref Po" sortable field="order.po_number" style="min-width: 130px">
                                <template #body="slotProps">
                                    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-tight">
                                        {{ slotProps.data.order?.vendor?.legal_name || '--' }}
                                    </div>
                                    <button
                                        type="button"
                                        @click="viewOrder(slotProps.data.order)"
                                        class="inline-flex items-center text-[10px] font-black px-2.5 py-1 bg-indigo-50/50 text-indigo-700 rounded border border-indigo-100 hover:bg-indigo-100 transition-colors uppercase cursor-pointer"
                                    >
                                        {{ slotProps.data.order?.po_number }}
                                    </button>
                                </template>
                            </Column>

                            <Column header="Product" sortable field="product.title" style="min-width: 140px">
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-slate-700 uppercase tracking-tight leading-tight">{{ slotProps.data.product?.title }}</span>
                                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">{{ slotProps.data.product?.hsn_code || 'No HSN' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Truck" sortable field="truck.registration" style="min-width: 150px">
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1.5 px-1">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="slotProps.data.truck_id ? 'bg-indigo-500' : 'bg-slate-300'"></div>
                                            <span class="text-[10px] font-black text-slate-700 uppercase tracking-tight">{{ slotProps.data.truck?.registration || 'External Vehicle' }}</span>
                                        </div>
                                        <div v-if="slotProps.data.truck?.code" class="text-[8px] text-slate-400 font-bold uppercase tracking-widest pl-4">
                                            V-REF: {{ slotProps.data.truck?.code }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Price" sortable field="unit_price" style="min-width: 110px" class="text-right">
                                <template #body="slotProps">
                                    <div class="flex flex-col items-center px-1 bg-slate-50/50 py-1.5 rounded">
                                        <span class="text-[12px] font-black text-slate-500">{{ Number(slotProps.data.unit_price) }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Received" sortable field="received_qty" style="min-width: 120px" class="text-right">
                                <template #body="slotProps">
                                    <div class=" bg-emerald-50/20 py-1.5 rounded border border-emerald-100/30">
                                        <span class="text-[13px] font-black text-emerald-600">{{ Number(slotProps.data.received_qty) }} /</span>
                                        <span class="text-[12px] font-black text-slate-500">{{ Number(slotProps.data.item.product_quantity) }} </span>
                                        <span class="text-[11px] text-gray-400 font-black uppercase tracking-widest">{{'  ' + slotProps.data.uom?.unit_code }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="" style="width: 120px" class="text-right">
                                <template #body="slotProps">
                                    <div class="flex items-center justify-end gap-1">
                                        <a
                                            :href="route('inwards.receipt', slotProps.data.id)"
                                            target="_blank"
                                            class="w-8 h-8 rounded-full text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 transition-colors inline-flex items-center justify-center cursor-pointer"
                                            title="Print Receipt (PDF Style with Print Button)"
                                        >
                                            <i class="pi pi-print text-sm"></i>
                                        </a>

                                        <a
                                            :href="route('inwards.download-receipt', slotProps.data.id)"
                                            class="w-8 h-8 rounded-full text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 transition-colors inline-flex items-center justify-center cursor-pointer"
                                            title="Download GRN as PDF"
                                        >
                                            <i class="pi pi-download text-sm"></i>
                                        </a>

                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            variant="text"
                                            size="small"
                                            rounded
                                            class="!text-red-400 hover:!text-red-500 h-8 w-8"
                                            @click.stop="deleteInward(slotProps.data)"
                                        />
                                    </div>
                                </template>
                            </Column>

                            <template #expansion="{ data }">
                                <InwardEditForm :key="data.id" :inward="data" />
                            </template>

                            <template #empty>
                                <div class="py-24 text-center">
                                    <ArchiveBoxIcon class="w-12 h-12 text-slate-100 mx-auto mb-4" />
                                    <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Inward registry is empty</h3>
                                    <p class="text-[10px] text-slate-300 mt-1">Confirmed goods receipts will appear here.</p>
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>
                </div>
            </div>
        </div>

        <PurchaseOrderPreviewDialog v-model:visible="previewVisible" :order="selectedOrder" />

    </AppLayout>
</template>

<style scoped>

</style>
