<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';

const props = defineProps<{
    tests: any;
    testTypes: any[];
    filters: {
        search: string;
        status: string;
    };
    statusCounts: {
        all: number;
        pending: number;
        pass: number;
        fail: number;
    };
}>();

const search = ref(props.filters.search || '');
const currentStatus = ref(props.filters.status || 'pending');

const updateFilters = (statusVal?: string) => {
    if (statusVal !== undefined) {
        currentStatus.value = statusVal;
    }
    router.get(
        route('quality.tests.index'),
        {
            search: search.value,
            status: currentStatus.value,
        },
        { preserveState: true, replace: true }
    );
};
watch(search, () => {
    updateFilters();
});
</script>

<template>
    <AppLayout title="Laboratory Quality Control Tests">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Laboratory Tests" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Laboratory Tests Registry
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Unified view for all laboratory specimen tests, execution, calculation results, and certificates.
                    </p>
                </div>
            </div>

            <!-- Status Filter Tab Bar -->
            <div class="flex flex-wrap items-center gap-2 bg-white dark:bg-gray-900 p-2 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs">
                <button
                    @click="updateFilters('all')"
                    :class="[
                        'px-4 py-2 text-xs font-bold rounded-xl flex items-center gap-2 transition-all',
                        currentStatus === 'all' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    ]"
                >
                    <span>All Laboratory Tests</span>
                    <span class="px-2 py-0.5 text-[10px] rounded-full font-mono font-black" :class="currentStatus === 'all' ? 'bg-indigo-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                        {{ statusCounts.all }}
                    </span>
                </button>

                <button
                    @click="updateFilters('pending')"
                    :class="[
                        'px-4 py-2 text-xs font-bold rounded-xl flex items-center gap-2 transition-all',
                        currentStatus === 'pending' ? 'bg-amber-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    ]"
                >
                    <span>Pending Tests</span>
                    <span class="px-2 py-0.5 text-[10px] rounded-full font-mono font-black" :class="currentStatus === 'pending' ? 'bg-amber-700 text-white' : 'bg-amber-100 text-amber-800'">
                        {{ statusCounts.pending }}
                    </span>
                </button>

                <button
                    @click="updateFilters('pass')"
                    :class="[
                        'px-4 py-2 text-xs font-bold rounded-xl flex items-center gap-2 transition-all',
                        currentStatus === 'pass' ? 'bg-emerald-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    ]"
                >
                    <span>Completed / Passed</span>
                    <span class="px-2 py-0.5 text-[10px] rounded-full font-mono font-black" :class="currentStatus === 'pass' ? 'bg-emerald-700 text-white' : 'bg-emerald-100 text-emerald-800'">
                        {{ statusCounts.pass }}
                    </span>
                </button>

                <button
                    @click="updateFilters('fail')"
                    :class="[
                        'px-4 py-2 text-xs font-bold rounded-xl flex items-center gap-2 transition-all',
                        currentStatus === 'fail' ? 'bg-red-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    ]"
                >
                    <span>Failed / Retest</span>
                    <span class="px-2 py-0.5 text-[10px] rounded-full font-mono font-black" :class="currentStatus === 'fail' ? 'bg-red-700 text-white' : 'bg-red-100 text-red-800'">
                        {{ statusCounts.fail }}
                    </span>
                </button>

                <div class="ml-auto w-full sm:w-64">
                    <BaseInput v-model="search" placeholder="Search Test #, Sample #, Code..." class="!mb-0" />
                </div>
            </div>

            <!-- BaseDataTable -->
            <BaseCard class="!p-0 overflow-hidden">
                <BaseDataTable
                    :value="tests.data || []"
                    dataKey="id"
                    :paginator="true"
                    :rows="15"
                    :totalRecords="tests.total"
                >
                    <Column field="test_no" header="Test #" sortable>
                        <template #body="{ data }">
                            <div class="font-mono font-bold text-indigo-600">{{ data.test_no }}</div>
                            <div class="text-[10px] text-gray-400 font-mono">{{ data.test_date ? data.test_date.substring(0, 10) : '' }}</div>
                        </template>
                    </Column>

                    <Column field="sample_no" header="Sample #">
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-gray-700 dark:text-gray-300">
                                {{ data.sample?.sample_no || '-' }}
                            </span>
                        </template>
                    </Column>

                    <Column field="material" header="Material / Plant">
                        <template #body="{ data }">
                            <div class="font-bold text-gray-900 dark:text-gray-100">
                                {{ data.sample?.material?.title || 'Raw Material' }}
                            </div>
                        </template>
                    </Column>

                    <Column field="test_procedure" header="Test Procedure">
                        <template #body="{ data }">
                            <div class="font-bold text-gray-800 dark:text-gray-200">
                                {{ data.test_type?.name }}
                            </div>
                            <div class="text-[10px] font-mono text-purple-600">
                                {{ data.test_type?.standard_reference || '' }}
                            </div>
                        </template>
                    </Column>

                    <Column field="overall_status" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.overall_status ? data.overall_status.toUpperCase() : 'PENDING'"
                                :colorMap="{
                                    'PASS': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'HOLD': 'bg-blue-50 text-blue-700 ring-blue-200',
                                    'PENDING': 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'FAIL': 'bg-red-50 text-red-700 ring-red-200',
                                    'RETEST': 'bg-orange-50 text-orange-700 ring-orange-200'
                                }"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <Link :href="route('quality.tests.execute', data.id)">
                                <BaseButton size="small" :variant="data.overall_status === 'pending' ? 'primary' : 'secondary'">
                                    <i :class="['pi text-xs mr-1.5', data.overall_status === 'pending' ? 'pi-pencil' : 'pi-file']"></i>
                                    {{ data.overall_status === 'pending' ? 'Execute Test' : 'View Certificate' }}
                                </BaseButton>
                            </Link>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
