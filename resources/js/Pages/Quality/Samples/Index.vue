<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, onBeforeUnmount } from 'vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';
import SampleForm from './SampleForm.vue';

const props = defineProps<{
    samples: any;
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    testTypes?: any[];
    salesOrders?: any[];
    batches?: any[];
    usedBatchIds?: number[];
    usedDispatchIds?: number[];
    personnels?: any[];
    filters: any;
}>();

const tableFilters = ref({ global: { value: props.filters?.search || '', matchMode: 'contains' } });
const statusFilter = ref(props.filters?.status || 'All');
const loading = ref(false);
const formKey = ref(0);
const expandedRows = ref<Record<number, boolean>>({});
const toggleEdit = (sample: any) => {
    expandedRows.value = expandedRows.value[sample.id] ? {} : { [sample.id]: true };
};
const onEditSaved = () => {
    expandedRows.value = {};
    tableFilters.value.global.value = '';
    statusFilter.value = 'All';
};
const onSampleSaved = () => {
    formKey.value++;
    tableFilters.value.global.value = '';
    statusFilter.value = 'All';
};
const statusOptions = [
    { label: 'All statuses', value: 'All' },
    { label: 'Pending testing', value: 'pending_test' },
    { label: 'In progress', value: 'in_progress' },
    { label: 'Completed', value: 'completed' },
];
const loadSamples = (changes: Record<string, any> = {}) => {
    expandedRows.value = {};
    router.get(route('quality.samples.index'), {
        search: tableFilters.value.global.value?.trim() || undefined,
        status: statusFilter.value === 'All' ? undefined : statusFilter.value,
        per_page: props.samples.per_page || 20,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        page: 1,
        ...changes,
    }, { preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; },
    });
};
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(() => tableFilters.value.global.value, (value) => {
    if (value === (props.filters?.search || '')) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadSamples(), 300);
});
onBeforeUnmount(() => clearTimeout(searchTimer));
const onPage = (event: any) => loadSamples({ page: Math.floor(event.first / event.rows) + 1, per_page: event.rows });
const onSort = (event: any) => loadSamples({ sort: event.sortField, direction: event.sortOrder === 1 ? 'asc' : 'desc' });
const statusLabel = (status: string) => statusOptions.find(option => option.value === status)?.label || status;
const statusSeverity = (status: string) => status === 'completed' ? 'success' : status === 'in_progress' ? 'info' : 'warn';
</script>

<template>
    <AppLayout title="Concrete Samples & Cube Casting">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Concrete Samples" />
        <Toast />

        <div class="samples-workspace w-full min-w-0 mx-auto px-4 py-3 sm:px-6 space-y-6">
            <SampleForm :key="formKey" embedded :concreteGrades="concreteGrades" :materials="materials"
                :customers="customers" :dispatches="dispatches" :batches="batches" :salesOrders="salesOrders"
                :usedBatchIds="usedBatchIds" :usedDispatchIds="usedDispatchIds" :testTypes="testTypes"
                :personnels="personnels" @saved="onSampleSaved" />
            <BaseDataTable :value="samples.data || []" dataKey="id" lazy showSearch v-model:expandedRows="expandedRows" :expandOnRowClick="false"
                v-model:filters="tableFilters" :loading="loading" :rows="samples.per_page || 20"
                :first="(samples.current_page - 1) * samples.per_page" :totalRecords="samples.total"
                :rowsPerPageOptions="[10, 20, 30, 50, 100]" :showAdvancedFilter="false"
                heading="Concrete samples" headingIcon="BeakerIcon" @page="onPage" @sort="onSort"
                class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <template #toolbar>
                    <BaseSelect v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value"
                        aria-label="Sample status" class="w-40" @update:modelValue="loadSamples()" />
                </template>
                <Column header="S.No" style="width: 4%">
                    <template #body="{ index }">
                        <span class="text-xs text-slate-500">{{ (samples.current_page - 1) * samples.per_page + index + 1 }}</span>
                    </template>
                </Column>
                <Column field="sample_no" header="Sample" sortable style="width: 18%">
                    <template #body="{ data }">
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ data.sample_no }}</span>
                        <p class="mt-1 text-[11px] text-slate-500">{{ data.sample_date?.substring(0, 10) || '—' }}</p>
                        <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" class="mt-1 !text-[10px]" />
                    </template>
                </Column>
                <Column header="Batch / Dispatch" style="width: 16%">
                    <template #body="{ data }"><p class="text-xs font-medium">{{ data.batch?.batch_no || '—' }}</p><p class="mt-1 text-[11px] text-slate-500">{{ data.dispatch?.dispatch_no || '—' }}</p></template>
                </Column>
                <Column header="Grade / Customer" style="width: 24%">
                    <template #body="{ data }">
                        <div class="flex flex-wrap items-center gap-2"><Tag :value="data.concrete_grade?.name || data.material?.title || '—'" severity="info" class="!text-[10px]" /><span class="text-[11px] text-slate-500">{{ data.truck_no }}</span></div>
                        <p class="mt-1 text-xs">{{ data.customer?.legal_name || '—' }}</p><p v-if="data.site_name" class="mt-0.5 text-[11px] text-slate-500">{{ data.site_name }}</p>
                    </template>
                </Column>
                <Column header="Sample details" style="width: 16%" headerClass="hidden md:table-cell" bodyClass="hidden md:table-cell">
                    <template #body="{ data }">
                        <p class="text-xs">{{ data.slump_mm != null ? data.slump_mm + ' mm' : '—' }} · {{ data.concrete_temp_c != null ? data.concrete_temp_c + ' °C' : '—' }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ data.specimen_count ?? '—' }} specimens · {{ data.specimen_size || '—' }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ data.curing_tank_id || '—' }}</p>
                    </template>
                </Column>
                <Column header="Tests" style="width: 14%">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-1">
                            <Link v-for="test in data.tests" :key="test.id" :href="route('quality.tests.execute', test.id)" :title="`${test.test_no} · ${test.scheduled_date?.substring(0, 10) || 'Unscheduled'}`">
                                <Tag :value="`${test.age_days ? test.age_days + 'D' : test.test_type?.code || 'Test'} · ${test.scheduled_date?.substring(5, 10) || 'Due'}`" :severity="test.overall_status === 'pass' ? 'success' : test.overall_status === 'fail' ? 'danger' : 'warn'" class="!text-[10px] cursor-pointer" />
                            </Link>
                            <span v-if="!data.tests?.length" class="text-xs text-slate-400">No tests scheduled</span>
                        </div>
                    </template>
                </Column>
                <Column header="Actions" style="width: 8%">
                    <template #body="{ data }"><div class="flex flex-wrap items-center gap-1">
                        <BaseButton :icon="expandedRows[data.id] ? 'pi pi-times' : 'pi pi-pencil'" variant="text" :aria-label="expandedRows[data.id] ? 'Close editor' : 'Edit sample'" @click="toggleEdit(data)" />
                        <BaseDeleteButton :url="route('quality.samples.destroy', data.id)" />
                    </div></template>
                </Column>
                <template #expansion="{ data }">
                    <div class="min-w-0 bg-slate-50 p-3 sm:p-4 dark:bg-slate-900">
                        <SampleForm :key="data.id" :sample="data" isEditing embedded
                            :concreteGrades="concreteGrades" :materials="materials" :customers="customers"
                            :dispatches="dispatches" :batches="batches" :salesOrders="salesOrders"
                            :usedBatchIds="usedBatchIds" :usedDispatchIds="usedDispatchIds"
                            :testTypes="testTypes" :personnels="personnels"
                            @saved="onEditSaved" @cancel="expandedRows = {}" />
                    </div>
                </template>
                <template #empty><p class="py-6 text-center text-sm text-slate-500">No samples found. Adjust your search or create a new sample.</p></template>
            </BaseDataTable>
        </div>
    </AppLayout>
</template>

<style scoped>
.samples-workspace :deep(.p-datatable-table) {
    width: 100%;
    table-layout: fixed;
}
.samples-workspace :deep(.p-datatable-table-container) {
    overflow: visible !important;
    max-height: none !important;
}
.samples-workspace :deep(.p-datatable .p-datatable-thead > tr > th),
.samples-workspace :deep(.p-datatable .p-datatable-tbody > tr > td) {
    min-width: 0 !important;
    padding: 8px !important;
    white-space: normal;
    overflow-wrap: anywhere;
}
.samples-workspace :deep(.p-column-title),
.samples-workspace :deep(.p-tag),
.samples-workspace :deep(.p-tag-label) {
    white-space: normal !important;
    overflow-wrap: anywhere;
}
.samples-workspace :deep(.p-column-header-content),
.samples-workspace :deep(.p-paginator) {
    flex-wrap: wrap;
}
.samples-workspace :deep(.p-datatable-row-expansion > td) {
    padding: 0 !important;
}
</style>
