<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import Swal from 'sweetalert2';
import SalesOrderEditForm from './SalesOrderEditForm.vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

const props = withDefaults(defineProps<{
    salesOrders?: any[];
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    customerPOs?: any[];
    statuses?: { label: string; value: number }[];
    concretePumpOptions?: any[];
    salesExecutives?: any[];
    taxes?: any[];
    pumpRates?: any[];
}>(), {
    salesOrders: () => [],
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    customerPOs: () => [],
    statuses: () => [],
    concretePumpOptions: () => [],
    salesExecutives: () => [],
    taxes: () => [],
    pumpRates: () => [],
});

// console.log('sadssd',props.statuses);


const filters = ref({
    global: { value: null, matchMode: 'contains' },
    status: { value: null, matchMode: 'equals' },
});
const expandedRows = ref<Record<number, boolean>>({});
const { can, isSuperAdmin } = usePermissions();

const statusSeverity = (status: number) => {
    if (status === 3) return 'success';
    if (status === 2) return 'info';
    if (status === 4) return 'danger';
    return 'warn';
};

const statusLabel = (status: number) => props.statuses.find((entry) => entry.value === status)?.label ?? 'Unknown';

const destroy = (row: any) => {
    const hasActiveData = row.batches_count > 0 || row.dispatches_count > 0 || row.status === 3;
    const warningText = hasActiveData 
        ? `WARNING: This order has active batches or dispatches! Deleting it may cause data inconsistencies. Are you sure you want to delete Order ${row.order_no}?`
        : `Order ${row.order_no} will be archived.`;

    Swal.fire({
        title: hasActiveData ? 'Force Delete Sales Order?' : 'Delete Sales Order?',
        text: warningText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: hasActiveData ? 'Yes, force delete' : 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;
        
        const id = row.id ?? row.sales_order_id;
        router.delete(route('salesorders.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire('Deleted!', 'The sales order has been archived.', 'success');
            }
        });
    });
};

const onSaved = () => {
    expandedRows.value = {};
};

const actionPopover = ref<InstanceType<typeof Popover> | null>(null);
const activeActionRow = ref<any>(null);

const openActions = (event: Event, row: any) => {
    activeActionRow.value = row;
    actionPopover.value?.toggle(event);
};

const printSO = () => {
    const id = activeActionRow.value?.id;
    if (!id) return;
    window.open(route('print.document', { module: 'sales_orders', id, action: 'view' }), '_blank');
    actionPopover.value?.hide();
};

const downloadSOPDF = () => {
    const id = activeActionRow.value?.id;
    if (!id) return;
    window.open(route('print.document', { module: 'sales_orders', id, action: 'download' }), '_blank');
    actionPopover.value?.hide();
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-xl">
        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            :value="salesOrders"
            :paginator="true"
            :rows="30"
            :showSearch="true"
            dataKey="id"
            showSerial
            heading="List Of Sales Orders"
            headingIcon="ClipboardDocumentListIcon"
            showExport
            exportFilename="sales-orders-report"
            :globalFilterFields="['order_no', 'customer.legal_name', 'site.name', 'mix_design.design_name']"
        >
            <template #toolbar>
                <div class="flex items-center gap-2">
                    <BaseSelect 
                        v-model="filters.status.value" 
                        :options="[{label: 'All Statuses', value: null}, ...statuses]" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="Filter Status" 
                        class="w-44 !h-9 !rounded-lg !border-slate-300 !text-[11px]"
                        pt:label:class="!px-3 !py-1"
                    />
                </div>
            </template>

            <Column field="order_no" header="Order No" sortable>
                <template #body="{ data }">
                    <span class="font-mono text-xs font-bold text-indigo-600">{{ data.prefix + data.order_no }}</span>
                </template>
            </Column>

            <Column header="Customer / Site">
                <template #body="{ data }">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-700">{{ data.customer?.legal_name || '-' }}</span>
                        <span class="text-[11px] text-slate-400">{{ data.site?.name || '-' }}</span>
                    </div>
                </template>
            </Column>

            <Column header="Mix Design">
                <template #body="{ data }">
                    <div class="flex flex-col justify-center align-center">
                        <span class="text-xs font-semibold text-slate-700">{{ data.mix_design?.design_name || '-' }}</span>
                        <span class="text-[11px] text-slate-400">{{ data.mix_design?.design_code || '-' }}</span>
                    </div>
                </template>
            </Column>

              <Column header="Progress" sortable field="produced_qty" style="min-width: 160px">
                <template #body="{ data }">
                    <div class="flex flex-col gap-1 text-xs w-36 py-0.5">
                        <div class="flex items-center justify-between font-semibold text-slate-700">
                            <span>{{ Number(data.produced_qty || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }) }} / {{ Number(data.total_qty || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }) }} <span class="text-[10px] text-slate-400 font-normal">m³</span></span>
                            <!-- <span class="text-[10px] font-bold text-slate-500">{{ Math.min(100, Math.max(0, Math.round(((Number(data.produced_qty) || 0) / Math.max(1, Number(data.total_qty) || 1)) * 100))) }}%</span> -->
                        </div>

                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="(Number(data.produced_qty) >= Number(data.total_qty)) ? 'bg-emerald-500' : 'bg-indigo-600'"
                                :style="{ width: `${Math.min(100, Math.max(0, ((Number(data.produced_qty) || 0) / Math.max(1, Number(data.total_qty) || 1)) * 100))}%` }"
                            />
                        </div>

                        <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium">
                            <span><strong class="text-indigo-600 font-semibold">{{ Math.max(0, Number(data.total_qty || 0) - Number(data.produced_qty || 0)).toFixed(2) }}</strong></span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Status" sortable field="status">
                <template #body="{ data }">
                    <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" rounded />
                </template>
            </Column>

            <Column header="Actions" style="width: 60px">
                <template #body="{ data }">
                    <div class="flex justify-end">
                        <!-- Print actions popover trigger -->
                        <Button
                            icon="pi pi-ellipsis-v"
                            text
                            rounded
                            severity="secondary"
                            v-tooltip.top="'More Actions'"
                            @click="openActions($event, data)"
                        />
                    </div>
                </template>
            </Column>

            <!-- Actions Popover -->
            <Popover ref="actionPopover" class="z-50">
                <div class="flex flex-col gap-1 p-1 min-w-[180px]">
                    <button
                        @click="printSO"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors w-full text-left"
                    >
                        <i class="pi pi-eye text-indigo-500"></i>
                        View / Print SO
                    </button>
                    <button
                        @click="downloadSOPDF"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors w-full text-left"
                    >
                        <i class="pi pi-download text-emerald-500"></i>
                        Download PDF
                    </button>

                    <!-- Delete Option -->
                    <template v-if="can('SALES_ORDER.DELETE')">
                        <hr class="border-slate-100 my-1" />
                        <button
                            @click="() => { destroy(activeActionRow); actionPopover?.hide(); }"
                            :disabled="!isSuperAdmin && (activeActionRow?.batches_count > 0 || activeActionRow?.dispatches_count > 0 || activeActionRow?.status === 3)"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors w-full text-left disabled:opacity-50"
                        >
                            <i class="pi pi-trash text-red-500"></i>
                            Delete Sales Order
                        </button>
                    </template>
                </div>
            </Popover>

            <template #expansion="{ data }">
                <div class="p-3">
                    <SalesOrderEditForm
                        v-if="can('SALES_ORDER.UPDATE')"
                        :salesOrder="{ ...data, id: data?.id ?? data?.sales_order_id ?? null }"
                        :customers="customers"
                        :sites="sites"
                        :mixDesigns="mixDesigns"
                        :customerPOs="customerPOs"
                        :statuses="statuses"
                        :concretePumpOptions="concretePumpOptions"
                        :salesExecutives="salesExecutives"
                        :taxes="taxes"
                        :pumpRates="pumpRates"
                        @saved="onSaved"
                        @cancel="expandedRows = {}"
                    />
                    <div v-else class="text-sm text-center text-slate-500 py-4">
                        You do not have permission to edit this sales order.
                    </div>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>
