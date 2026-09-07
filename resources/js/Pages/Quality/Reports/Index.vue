<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Badge from '@/Components/Mm/Badge.vue';

const props = defineProps<{
    tests: any;
    summary: {
        total: number;
        passed: number;
        failed: number;
        retest: number;
        pass_rate: number;
    };
    materials: any[];
    filters: any;
}>();

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const materialId = ref(props.filters?.material_id || '');
const statusFilter = ref(props.filters?.status || 'All');

const materialOptions = computed(() => {
    return [{ label: 'All Materials', value: '' }].concat(
        (props.materials || []).map(m => ({ label: m.title, value: m.id }))
    );
});

const statusOptions = [
    { label: 'All Statuses', value: 'All' },
    { label: 'Passed Only', value: 'Pass' },
    { label: 'Failed Only', value: 'Fail' },
    { label: 'Retests', value: 'Retest' }
];

const applyFilters = () => {
    router.get(route('quality.reports.index'), {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        material_id: materialId.value,
        status: statusFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

const downloadPdf = () => {
    window.open(route('quality.reports.pdf', {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        material_id: materialId.value,
        status: statusFilter.value,
    }), '_blank');
};
</script>

<template>
    <AppLayout title="Quality Control Reports">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Reports" />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Quality Control Reports & Compliance Analytics
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Consolidated laboratory test summary reports, supplier quality metrics, and PDF exports.
                    </p>
                </div>
                <div>
                    <BaseButton @click="downloadPdf" variant="primary">
                        <i class="pi pi-file-pdf text-xs mr-2"></i> Export PDF Summary
                    </BaseButton>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <BaseCard class="p-5">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Tests Executed</div>
                    <div class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-1">{{ summary.total }}</div>
                </BaseCard>
                <BaseCard class="p-5">
                    <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Passed Tests</div>
                    <div class="text-2xl font-black text-emerald-600 mt-1">{{ summary.passed }}</div>
                </BaseCard>
                <BaseCard class="p-5">
                    <div class="text-xs font-bold text-red-500 uppercase tracking-wider">Failed Tests</div>
                    <div class="text-2xl font-black text-red-500 mt-1">{{ summary.failed }}</div>
                </BaseCard>
                <BaseCard class="p-5">
                    <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Quality Pass Rate</div>
                    <div class="text-2xl font-black text-indigo-600 mt-1">{{ summary.pass_rate }}%</div>
                </BaseCard>
            </div>

            <!-- Filters Bar -->
            <BaseCard class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 w-full">
                    <BaseInput v-model="dateFrom" type="date" label="Date From" @change="applyFilters" />
                    <BaseInput v-model="dateTo" type="date" label="Date To" @change="applyFilters" />
                    <BaseSelect v-model="materialId" label="Material" :options="materialOptions" optionLabel="label" optionValue="value" @change="applyFilters" />
                    <BaseSelect v-model="statusFilter" label="Result Status" :options="statusOptions" optionLabel="label" optionValue="value" @change="applyFilters" />
                </div>
            </BaseCard>

            <!-- BaseDataTable -->
            <BaseCard class="!p-0 overflow-hidden">
                <BaseDataTable
                    :value="tests.data || []"
                    dataKey="id"
                    :paginator="true"
                    :rows="15"
                    :totalRecords="tests.total"
                >
                    <Column field="test_date" header="Date" sortable>
                        <template #body="{ data }">
                            <span class="font-mono text-gray-500 text-xs">{{ data.test_date ? data.test_date.substring(0, 10) : '' }}</span>
                        </template>
                    </Column>

                    <Column field="test_no" header="Test No" sortable>
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-indigo-600">{{ data.test_no }}</span>
                        </template>
                    </Column>

                    <Column field="sample.sample_no" header="Sample No">
                        <template #body="{ data }">
                            <span class="font-mono font-semibold">{{ data.sample?.sample_no }}</span>
                        </template>
                    </Column>

                    <Column field="sample.material.title" header="Material">
                        <template #body="{ data }">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ data.sample?.material?.title }}</span>
                        </template>
                    </Column>

                    <Column header="Supplier">
                        <template #body="{ data }">
                            <span class="text-gray-500 text-xs">{{ data.sample?.supplier?.legal_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <Column field="test_type.name" header="Test Procedure">
                        <template #body="{ data }">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ data.test_type?.name }}</span>
                        </template>
                    </Column>

                    <Column field="overall_status" header="Evaluation">
                        <template #body="{ data }">
                            <Badge
                                :value="data.overall_status === 'pass' ? 'Active' : 'Inactive'"
                                :colorMap="{
                                    'Active': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Inactive': 'bg-red-50 text-red-700 ring-red-200'
                                }"
                            />
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
