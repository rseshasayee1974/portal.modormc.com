<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
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

const search = ref('');
const expandedRows = ref<Record<number, boolean>>({});

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return props.workOrders;

    return props.workOrders.filter((row) =>
        [row.order_no, row.customer?.legal_name, row.site?.name, row.mix_design?.design_name]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query))
    );
});

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
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-2.5">
                    <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600">
                        <ClipboardDocumentListIcon class="h-4 w-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">Work Orders</h2>
                        <p class="mt-1 text-xs text-slate-400">Click any row to expand and edit inline.</p>
                    </div>
                </div>
                <!-- <BaseInput v-model="search" placeholder="Search order/customer/site..." /> -->
            </div>
        </div>

        <BaseDataTable
            v-model:expandedRows="expandedRows"
            :value="filteredRows"
            :paginator="true"
            :rows="30"
            :showSearch="true"
            dataKey="id"
            showSerial
            
        >
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
