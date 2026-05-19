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

            <Column field="test_code" header="QC Code" sortable>
                <template #body="slotProps">
                    <span class="font-bold text-indigo-600 dark:text-indigo-400">
                        {{ slotProps.data.test_code }}
                    </span>
                </template>
            </Column>

            <Column field="batch_id" header="Batch No" sortable>
                <template #body="slotProps">
                    <div v-if="slotProps.data.batch" class="flex flex-col">
                        <span class="font-extrabold text-slate-800 dark:text-slate-200">#{{ slotProps.data.batch.batch_no }}</span>
                        <span class="text-[10px] text-gray-400 font-semibold mt-0.5">RMC Production</span>
                    </div>
                    <span v-else class="text-xs text-gray-400 dark:text-gray-600 italic">Generic / Manual</span>
                </template>
            </Column>

            <Column header="Order / Dispatch Details">
                <template #body="slotProps">
                    <div v-if="slotProps.data.batch?.work_order" class="flex flex-col gap-1 text-xs">
                        <!-- Mix Design -->
                        <div class="flex items-center gap-1.5" v-if="slotProps.data.batch.work_order.mix_design">
                            <span class="text-[9px] font-black bg-purple-50 dark:bg-purple-900/20 text-purple-600 px-1.5 py-0.5 rounded uppercase">Mix</span>
                            <span class="font-bold text-gray-700 dark:text-gray-300">
                                {{ slotProps.data.batch.work_order.mix_design.design_name }}
                            </span>
                        </div>
                        
                        <!-- Customer -->
                        <div class="flex items-center gap-1.5" v-if="slotProps.data.batch.work_order.customer">
                            <span class="text-[9px] font-black bg-blue-50 dark:bg-blue-900/20 text-blue-600 px-1.5 py-0.5 rounded uppercase">Cust</span>
                            <span class="text-gray-600 dark:text-gray-400 font-semibold truncate max-w-[150px]" :title="slotProps.data.batch.work_order.customer.legal_name">
                                {{ slotProps.data.batch.work_order.customer.legal_name }}
                            </span>
                        </div>

                        <!-- Truck -->
                        <div class="flex items-center gap-1.5" v-if="slotProps.data.batch.dispatches?.[0]?.truck">
                            <span class="text-[9px] font-black bg-amber-50 dark:bg-amber-900/20 text-amber-600 px-1.5 py-0.5 rounded uppercase">Truck</span>
                            <span class="font-mono text-gray-600 dark:text-gray-400 font-bold uppercase">
                                {{ slotProps.data.batch.dispatches[0].truck.registration }}
                            </span>
                        </div>
                    </div>
                    <span v-else class="text-xs text-gray-400 dark:text-gray-600 italic">No Dispatch Linked</span>
                </template>
            </Column>

            <Column header="Tested By / Date">
                <template #body="slotProps">
                    <span class="block font-medium text-gray-800 dark:text-gray-200">{{ slotProps.data.tested_by || 'Unknown Operator' }}</span>
                    <span class="block text-xs text-gray-400 mt-0.5">{{ new Date(slotProps.data.test_date).toLocaleDateString('en-IN') }}</span>
                </template>
            </Column>

            <Column header="Reference Photos" style="width: 120px">
                <template #body="slotProps">
                    <div v-if="slotProps.data.photos && slotProps.data.photos.length" class="flex items-center gap-1.5">
                        <a 
                            :href="slotProps.data.photos[0].url" 
                            target="_blank" 
                            class="block group relative w-10 h-10 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm bg-gray-50 flex items-center justify-center"
                        >
                            <img 
                                :src="slotProps.data.photos[0].url" 
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                            />
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <i class="pi pi-eye text-white text-xs"></i>
                            </div>
                        </a>
                        
                        <!-- Extra images counter badge -->
                        <div v-if="slotProps.data.photos.length > 1" class="flex flex-col gap-0.5">
                            <span class="text-[9px] font-black bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded-md">
                                +{{ slotProps.data.photos.length - 1 }}
                            </span>
                            <span class="text-[8px] text-gray-400 font-bold uppercase tracking-wider">More</span>
                        </div>
                    </div>
                    <span v-else class="text-[9px] font-black text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded uppercase tracking-wider">
                        No Photos
                    </span>
                </template>
            </Column>

            <Column header="Fresh Testing Details">
                <template #body="slotProps">
                    <div class="flex flex-wrap gap-2">
                        <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-xs font-semibold">
                            Slump: {{ slotProps.data.slump_value }} mm
                        </span>
                        <span class="px-2 py-0.5 rounded bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 text-xs font-semibold">
                            Temp: {{ slotProps.data.fresh_temperature }}°C
                        </span>
                        <span class="px-2 py-0.5 rounded bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 text-xs font-semibold">
                            Density: {{ slotProps.data.fresh_density }}
                        </span>
                    </div>
                </template>
            </Column>

            <Column header="Hardened Compressive Strength">
                <template #body="slotProps">
                    <div class="flex flex-wrap gap-2">
                        <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-xs font-semibold">
                            7d: {{ slotProps.data.cube_strength_7_days }} MPa
                        </span>
                        <span class="px-2 py-0.5 rounded bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 text-xs font-semibold">
                            28d: {{ slotProps.data.cube_strength_28_days }} MPa
                        </span>
                    </div>
                </template>
            </Column>

            <Column field="status" header="Status" sortable style="width: 120px">
                <template #body="slotProps">
                    <Tag 
                        :severity="slotProps.data.status === 'passed' ? 'success' : (slotProps.data.status === 'failed' ? 'danger' : 'warning')" 
                        :value="slotProps.data.status.toUpperCase()" 
                        class="rounded-lg px-2.5 py-1 text-xs font-bold tracking-wider"
                    />
                </template>
            </Column>

            <Column header="Actions" class="text-right" style="width: 120px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
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
