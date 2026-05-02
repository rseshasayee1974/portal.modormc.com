<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import WorkOrderEditForm from './WorkOrderEditForm.vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

const props = withDefaults(defineProps<{
    workOrders?: any[];
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    statuses?: { label: string; value: number }[];
}>(), {
    workOrders: () => [],
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    statuses: () => [],
});

const filters = ref({
    global: { value: null, matchMode: 'contains' },
    status: { value: null, matchMode: 'equals' },
});
const expandedRows = ref<Record<number, boolean>>({});

const statusSeverity = (status: number) => {
    if (status === 3) return 'success';
    if (status === 2) return 'info';
    if (status === 4) return 'danger';
    return 'warn';
};

const statusLabel = (status: number) => props.statuses.find((entry) => entry.value === status)?.label ?? 'Unknown';

const destroy = (row: any) => {
    Swal.fire({
        title: 'Delete work order?',
        text: `Order ${row.order_no} will be archived.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('workorders.destroy', row.id));
    });
};

const onSaved = () => {
    expandedRows.value = {};
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-xl">
        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            :value="workOrders"
            :paginator="true"
            :rows="30"
            :showSearch="true"
            dataKey="id"
            showSerial
            heading="List Of Work Orders"
            headingIcon="ClipboardDocumentListIcon"
            showExport
            exportFilename="work-orders-report"
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
                    <span class="font-mono text-xs font-bold text-indigo-600">{{ data.order_no }}</span>
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
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-700">{{ data.mix_design?.design_name || '-' }}</span>
                        <span class="text-[11px] text-slate-400">{{ data.mix_design?.design_code || '-' }}</span>
                    </div>
                </template>
            </Column>

            <Column header="Progress" sortable field="produced_qty">
                <template #body="{ data }">
                    <div class="text-xs">
                        <span class="font-semibold text-slate-700">{{ data.produced_qty }} / {{ data.total_qty }} m³</span>
                        <div class="mt-1 h-1.5 w-28 rounded bg-slate-100">
                            <div
                                class="h-1.5 rounded bg-emerald-500"
                                :style="{ width: `${Math.min(100, (Number(data.produced_qty) / Math.max(1, Number(data.total_qty))) * 100)}%` }"
                            />
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Status" sortable field="status">
                <template #body="{ data }">
                    <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" rounded />
                </template>
            </Column>

            <Column header="Actions" style="width: 80px">
                <template #body="{ data }">
                    <div class="flex justify-end">
                        <Button icon="pi pi-trash" text rounded severity="danger" @click="destroy(data)" />
                    </div>
                </template>
            </Column>

            <template #expansion="{ data }">
                <div class="p-3">
                    <WorkOrderEditForm
                        :workOrder="{ ...data, id: data?.id ?? data?.work_order_id ?? null }"
                        :customers="customers"
                        :sites="sites"
                        :mixDesigns="mixDesigns"
                        :statuses="statuses"
                        @saved="onSaved"
                        @cancel="expandedRows = {}"
                    />
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>
