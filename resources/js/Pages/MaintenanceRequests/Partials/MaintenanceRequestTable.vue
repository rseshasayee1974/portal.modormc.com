<script setup lang="ts">
import { ref } from 'vue';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import {
    PencilSquareIcon, TrashIcon, WrenchScrewdriverIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    requests: any[];
    maintenanceTypes: Array<{ label: string; value: number }>;
    priorityLevels: Array<{ label: string; value: number }>;
    requestStatuses: Array<{ label: string; value: number }>;
}>();

const emit = defineEmits<{
    (e: 'edit', req: any): void;
    (e: 'delete', id: number): void;
}>();

const expandedRows = ref<any[]>([]);
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 shadow-xl shadow-slate-200/40 dark:shadow-none rounded-2xl border border-slate-100 dark:border-slate-800 overflow-hidden">
        <BaseDataTable
            :value="requests"
            v-model:filters="filters"
            :globalFilterFields="['name', 'description', 'machine.registration', 'vendor.legal_name']"
            showSearch
            showSerial
            heading="Maintenance Tickets Ledger"
            headingIcon="WrenchScrewdriverIcon"
            :rows="25"
            v-model:expandedRows="expandedRows"
            class="maintenance-table"
        >
            <!-- Row Expansion Trigger -->
            <Column expander style="width: 3rem" />

            <!-- Subject/Name -->
            <Column header="Ticket Subject" sortable field="name">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                            {{ slotProps.data.name }}
                        </span>
                        <span class="text-[9px] text-slate-400 font-black uppercase tracking-wider mt-0.5">
                            {{ slotProps.data.repair_location || 'Workshop' }}
                        </span>
                    </div>
                </template>
            </Column>

            <!-- Machine -->
            <Column header="Asset Registration" sortable field="machine.registration">
                <template #body="slotProps">
                    <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/70 dark:bg-indigo-950/50 px-2 py-0.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                        {{ slotProps.data.machine?.registration || '—' }}
                    </span>
                </template>
            </Column>

            <!-- Vendor -->
            <Column header="Repair Vendor">
                <template #body="slotProps">
                    <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">
                        {{ slotProps.data.vendor?.legal_name || '—' }}
                    </span>
                </template>
            </Column>

            <!-- Type -->
            <Column header="Maint. Type">
                <template #body="slotProps">
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                        {{ maintenanceTypes.find(t => t.value === slotProps.data.maintanence_type)?.label || 'Routine' }}
                    </span>
                </template>
            </Column>

            <!-- Priority -->
            <Column header="Priority" sortable field="priority">
                <template #body="slotProps">
                    <span
                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full inline-block"
                        :class="{
                            'bg-red-50 text-red-600 border border-red-200 dark:bg-red-950/60 dark:text-red-400 dark:border-red-800': slotProps.data.priority === 3,
                            'bg-orange-50 text-orange-600 border border-orange-200 dark:bg-orange-950/60 dark:text-orange-400 dark:border-orange-800': slotProps.data.priority === 2,
                            'bg-indigo-50 text-indigo-600 border border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-400 dark:border-indigo-800': slotProps.data.priority === 1,
                            'bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700': slotProps.data.priority === 0
                        }"
                    >
                        {{ priorityLevels.find(p => p.value === slotProps.data.priority)?.label || 'Low' }}
                    </span>
                </template>
            </Column>

            <!-- Deadline -->
            <Column header="Deadline">
                <template #body="slotProps">
                    <span class="text-xs font-mono font-semibold text-slate-500 dark:text-slate-400">
                        {{ entityLocaleDate(slotProps.data.dead_line) }}
                    </span>
                </template>
            </Column>

            <!-- Status -->
            <Column header="Status" sortable field="status">
                <template #body="slotProps">
                    <span
                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full inline-block"
                        :class="{
                            'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800': slotProps.data.status === 3,
                            'bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800': slotProps.data.status === 2,
                            'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800': slotProps.data.status === 1,
                            'bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700': slotProps.data.status === 0 || slotProps.data.status === 4
                        }"
                    >
                        {{ requestStatuses.find(s => s.value === slotProps.data.status)?.label || 'Draft' }}
                    </span>
                </template>
            </Column>

            <!-- Actions -->
            <Column header="Control" style="width: 110px" align="right">
                <template #body="slotProps">
                    <div class="flex justify-end items-center gap-1.5">
                        <button
                            @click="emit('edit', slotProps.data)"
                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-all active:scale-95 border border-indigo-100 dark:border-indigo-900/50"
                            title="Modify Request"
                        >
                            <PencilSquareIcon class="w-4 h-4" />
                        </button>
                        <button
                            @click="emit('delete', slotProps.data.id)"
                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-red-50 dark:bg-red-950/60 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/60 transition-all active:scale-95 border border-red-100 dark:border-red-900/50"
                            title="Remove Request"
                        >
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </template>
            </Column>

            <!-- Row Expansion Template (Parts & Tasks List) -->
            <template #expansion="slotProps">
                <div class="p-5 bg-slate-50/70 dark:bg-slate-950/50 rounded-2xl border border-slate-200/80 dark:border-slate-800 m-2">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <span>Required Parts, Services & Items ({{ slotProps.data.lines?.length || 0 }})</span>
                            <span v-if="slotProps.data.tax_inclusive" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800">
                                Tax Inclusive
                            </span>
                        </h4>
                    </div>
                    <DataTable :value="slotProps.data.lines" class="lines-subtable border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
                        <Column field="name" header="Line Detail" />
                        <Column field="product.title" header="Product" />
                        <Column field="product_quantity" header="Qty" />
                        <Column field="uom.unit_code" header="UOM" />
                        <Column header="Unit Price">
                            <template #body="subProps">
                                ₹{{ Number(subProps.data.price_unit || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                            </template>
                        </Column>
                        <Column header="Subtotal">
                            <template #body="subProps">
                                ₹{{ Number(subProps.data.price_subtotal || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                            </template>
                        </Column>
                        <Column header="Tax">
                            <template #body="subProps">
                                ₹{{ Number(subProps.data.price_tax || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                            </template>
                        </Column>
                        <Column header="Total Price">
                            <template #body="subProps">
                                <span class="font-bold text-slate-700 dark:text-slate-200">
                                    ₹{{ Number(subProps.data.price_total || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>

            <!-- Empty State -->
            <template #empty>
                <div class="py-16 flex flex-col items-center gap-3">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-300 dark:text-slate-600">
                        <WrenchScrewdriverIcon class="w-7 h-7" />
                    </div>
                    <div class="text-center">
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Maintenance Tickets Recorded</p>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">Use the form above to register a fleet maintenance ticket.</p>
                    </div>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped>
:deep(.lines-subtable .p-datatable-thead > tr > th) {
    @apply !bg-slate-100/80 dark:!bg-slate-800/80 !text-slate-500 !font-black !text-[9px] !uppercase !py-2.5;
}
:deep(.lines-subtable .p-datatable-tbody > tr > td) {
    @apply !py-2 !text-xs !text-slate-600 dark:!text-slate-300;
}
</style>
