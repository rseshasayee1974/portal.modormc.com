<script setup lang="ts">
import { ref, watch } from 'vue';
import Tag from 'primevue/tag';
import Column from 'primevue/column';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';

const props = defineProps<{
    tests: any;
    stats: {
        total: number;
        passed: number;
        failed: number;
        passedRate: number;
        slumpAvg: number;
    };
    searchQuery: string;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    view: [test: any];
    edit: [test: any];
    delete: [id: number];
    page: [event: any];
    sort: [event: any];
}>();

const filters = ref({
    global: { value: props.searchQuery, matchMode: 'contains' }
});

watch(() => props.searchQuery, (newVal) => {
    filters.value.global.value = newVal;
});

watch(() => filters.value.global.value, (newVal) => {
    emit('update:searchQuery', newVal || '');
});
</script>

<template>
    <div class="space-y-8">
        <!-- ── Rich Stats Dashboard ── -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 flex items-center justify-center text-blue-600 dark:text-blue-400 text-2xl">
                    <i class="pi pi-file"></i>
                </div>
                <div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium block">Total QC Logs</span>
                    <span class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ stats.total }} records</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-2xl">
                    <i class="pi pi-check-circle"></i>
                </div>
                <div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium block">Passed Rate</span>
                    <span class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ stats.passedRate }}% Passed</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-950/40 flex items-center justify-center text-rose-600 dark:text-rose-400 text-2xl">
                    <i class="pi pi-times-circle"></i>
                </div>
                <div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium block">Failed Batches</span>
                    <span class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ stats.failed }} critical</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 flex items-center justify-center text-amber-600 dark:text-amber-400 text-2xl">
                    <i class="pi pi-sliders-h"></i>
                </div>
                <div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium block">Avg Slump Value</span>
                    <span class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ stats.slumpAvg }} mm</span>
                </div>
            </div>
        </div>

        <!-- ── Quality Controls Table using BaseDataTable ───────────────── -->
        <BaseDataTable
            :value="tests.data"
            dataKey="id"
            stripedRows
            paginator
            :rows="tests.per_page || 30"
            :totalRecords="tests.total || 0"
            lazy
            heading="Concrete Quality Records"
            headingIcon="BeakerIcon"
            showSearch
            showSerial
            v-model:filters="filters"
            @page="emit('page', $event)"
            @sort="emit('sort', $event)"
        >

            <Column field="test_number" header="Test Number" sortable>
                <template #body="slotProps">
                    <span class="font-bold text-sky-700">
                        {{ slotProps.data.test_number || slotProps.data.test_code }}
                    </span>
                    <span v-if="slotProps.data.plant" class="block text-[10px] text-gray-400">
                        {{ slotProps.data.plant.name }}
                    </span>
                </template>
            </Column>

            <Column header="Customer / Invoice">
                <template #body="slotProps">
                    <div class="flex flex-col text-xs">
                        <span class="font-bold text-gray-800">
                            {{ slotProps.data.account_name || slotProps.data.patron?.name || (slotProps.data.batch?.work_order?.customer?.legal_name) || '—' }}
                        </span>
                        <span v-if="slotProps.data.invoice_no" class="text-[11px] text-gray-500 font-mono">
                            Inv: {{ slotProps.data.invoice_no }}
                        </span>
                    </div>
                </template>
            </Column>

            <Column header="Grade / Age">
                <template #body="slotProps">
                    <div class="flex flex-col text-xs">
                        <span class="font-bold text-purple-700">
                            {{ slotProps.data.grade || (slotProps.data.batch?.work_order?.mix_design?.design_name) || '—' }}
                        </span>
                        <span class="text-[11px] text-gray-500">
                            Age: {{ slotProps.data.age_of_test_days || 7 }} Days
                        </span>
                    </div>
                </template>
            </Column>

            <Column header="Casting / Test Date">
                <template #body="slotProps">
                    <div class="flex flex-col text-xs">
                        <span class="text-gray-700 font-medium">
                            Cast: {{ slotProps.data.concrete_date ? slotProps.data.concrete_date.substring(0, 10) : '—' }}
                        </span>
                        <span class="text-gray-500 text-[11px]">
                            Test: {{ slotProps.data.date_of_testing ? slotProps.data.date_of_testing.substring(0, 10) : (slotProps.data.test_date ? slotProps.data.test_date.substring(0, 10) : '—') }}
                        </span>
                    </div>
                </template>
            </Column>

            <Column header="Slump / Temp">
                <template #body="slotProps">
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-semibold">
                            {{ slotProps.data.slump_value }} mm
                        </span>
                        <span v-if="slotProps.data.fresh_temperature" class="px-2 py-0.5 rounded bg-orange-50 text-orange-700 font-semibold">
                            {{ slotProps.data.fresh_temperature }}°C
                        </span>
                    </div>
                </template>
            </Column>

            <Column header="Avg Compressive Strength">
                <template #body="slotProps">
                    <span v-if="slotProps.data.avg_compressive_strength" class="px-2.5 py-1 rounded bg-sky-50 text-sky-800 font-extrabold text-xs">
                        {{ slotProps.data.avg_compressive_strength }} N/mm²
                    </span>
                    <span v-else-if="slotProps.data.cube_strength_28_days || slotProps.data.cube_strength_7_days" class="text-xs text-gray-600 font-bold">
                        {{ slotProps.data.cube_strength_28_days || slotProps.data.cube_strength_7_days }} MPa
                    </span>
                    <span v-else class="text-xs text-gray-400 italic">—</span>
                </template>
            </Column>

            <Column field="status" header="Status" sortable style="width: 110px">
                <template #body="slotProps">
                    <Tag 
                        :severity="slotProps.data.status === 'passed' ? 'success' : (slotProps.data.status === 'failed' ? 'danger' : 'warning')" 
                        :value="slotProps.data.status ? slotProps.data.status.toUpperCase() : 'PENDING'" 
                        class="rounded-lg px-2.5 py-1 text-xs font-bold tracking-wider"
                    />
                </template>
            </Column>

            <Column header="Actions" class="text-right" style="width: 140px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <BaseActionButton
                            icon="pi pi-eye"
                            severity="info"
                            tooltip="View Test Certificate"
                            @click.stop="emit('view', slotProps.data)"
                        />
                        <BaseActionButton
                            icon="pi pi-pencil"
                            severity="secondary"
                            tooltip="Modify Record"
                            @click.stop="emit('edit', slotProps.data)"
                        />
                        <BaseActionButton
                            icon="pi pi-trash"
                            severity="danger"
                            tooltip="Delete Record"
                            @click.stop="emit('delete', slotProps.data.id)"
                        />
                    </div>
                </template>
            </Column>

            <template #empty>
                <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                    <i class="pi pi-inbox text-4xl block mb-2"></i>
                    No quality testing logs found.
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped>
/* Scoped overrides to inherit standard theme styles */
:deep(.p-datatable-thead > tr > th) {
    @apply !bg-slate-50/50 dark:!bg-slate-900/50 !text-slate-400 !font-black !text-[10px] !uppercase !tracking-[0.2em] !py-6 !border-b !border-slate-100 dark:!border-slate-800;
}
:deep(.p-datatable-tbody > tr:hover) {
    @apply !bg-indigo-50/20 dark:!bg-indigo-900/10;
}
</style>
