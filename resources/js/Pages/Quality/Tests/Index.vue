<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, onBeforeUnmount } from 'vue';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Toast from 'primevue/toast';
import Popover from 'primevue/popover';

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
const currentStatus = ref(props.filters.status || 'all');
const actionsPopover = ref<InstanceType<typeof Popover> | null>(null);
const actionTest = ref<any>(null);
const openActions = (event: MouseEvent, test: any) => {
    actionTest.value = test;
    actionsPopover.value?.toggle(event);
};
const closeActions = () => actionsPopover.value?.hide();

const kpiCards = [
    {
        status: 'all',
        label: 'All Tests',
        countKey: 'all' as const,
        icon: 'pi pi-list',
        badge: 'Registry',
        subtext: 'Total logged tests',
        activeRing: 'ring-indigo-500 border-indigo-500 text-indigo-600',
        iconBg: 'bg-indigo-50 text-indigo-600',
    },
    {
        status: 'pending',
        label: 'Pending Execution',
        countKey: 'pending' as const,
        icon: 'pi pi-clock',
        badge: 'In Queue',
        subtext: 'Awaiting lab testing',
        activeRing: 'ring-amber-500 border-amber-500 text-amber-600',
        iconBg: 'bg-amber-50 text-amber-600',
    },
    {
        status: 'pass',
        label: 'Passed / Hold',
        countKey: 'pass' as const,
        icon: 'pi pi-check-circle',
        badge: 'Compliant',
        subtext: 'Met design strength',
        activeRing: 'ring-emerald-500 border-emerald-500 text-emerald-600',
        iconBg: 'bg-emerald-50 text-emerald-600',
    },
    {
        status: 'fail',
        label: 'Failed / Retest',
        countKey: 'fail' as const,
        icon: 'pi pi-exclamation-circle',
        badge: 'Non-compliant',
        subtext: 'Below target strength',
        activeRing: 'ring-rose-500 border-rose-500 text-rose-600',
        iconBg: 'bg-rose-50 text-rose-600',
    },
];

const statusTabs = [
    { value: 'all', label: 'All Tests', icon: 'pi pi-list', count: 'all' as const },
    { value: 'pending', label: 'Pending', icon: 'pi pi-clock', count: 'pending' as const },
    { value: 'pass', label: 'Passed / Hold', icon: 'pi pi-check-circle', count: 'pass' as const },
    { value: 'fail', label: 'Failed / Retest', icon: 'pi pi-exclamation-circle', count: 'fail' as const },
] as const;

const canPrintPdf = (test: any) => {
    const type = `${test.test_type?.name || ''} ${test.test_type?.code || ''}`.toLowerCase();
    return ['pass', 'fail', 'retest'].includes(test.overall_status)
        && !/flexur|slump|tensile|beam|cement|aggregate/.test(type)
        && (test.test_type?.category === 'Concrete' || /cube|compressive/.test(type));
};

const resultValue = (test: any) => {
    const value = test.results?.[0]?.final_value;
    return value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))
        ? Number(value).toFixed(2) : value || '-';
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;

const updateFilters = (statusVal?: string, page = 1) => {
    clearTimeout(searchTimer);
    if (statusVal !== undefined) {
        currentStatus.value = statusVal;
    }
    router.get(
        route('quality.tests.index'),
        {
            search: search.value,
            status: currentStatus.value,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const clearSearch = () => {
    search.value = '';
    updateFilters();
};

const clearAllFilters = () => {
    search.value = '';
    currentStatus.value = 'all';
    updateFilters('all', 1);
};

const statusBadgeClass = (status?: string) => {
    switch ((status || 'pending').toLowerCase()) {
        case 'pass':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
        case 'fail':
            return 'bg-rose-50 text-rose-700 border-rose-200/80';
        case 'retest':
            return 'bg-orange-50 text-orange-700 border-orange-200/80';
        case 'hold':
            return 'bg-blue-50 text-blue-700 border-blue-200/80';
        case 'pending':
        default:
            return 'bg-amber-50 text-amber-700 border-amber-200/80';
    }
};

const statusDotClass = (status?: string) => {
    switch ((status || 'pending').toLowerCase()) {
        case 'pass':
            return 'bg-emerald-500';
        case 'fail':
            return 'bg-rose-500';
        case 'retest':
            return 'bg-orange-500';
        case 'hold':
            return 'bg-blue-500';
        case 'pending':
        default:
            return 'bg-amber-500';
    }
};

const statusLabel = (status?: string) => {
    switch ((status || 'pending').toLowerCase()) {
        case 'pass':
            return 'Passed';
        case 'fail':
            return 'Failed';
        case 'retest':
            return 'Retest';
        case 'hold':
            return 'On Hold';
        case 'pending':
        default:
            return 'Pending';
    }
};

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => updateFilters(), 300);
});

onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <AppLayout title="Laboratory Quality Control Tests">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Head title="Laboratory Tests" />
        <Toast />
        <Popover ref="actionsPopover" id="qc-test-actions-popover" appendTo="body">
            <div v-if="actionTest" class="flex w-52 max-w-full flex-col gap-1" aria-label="Test actions">
                <p class="mb-1 truncate border-b border-slate-100 px-3 pb-2 text-xs font-semibold text-slate-500"
                    :title="actionTest.test_no">{{ actionTest.test_no }}</p>
                <a v-if="canPrintPdf(actionTest)"
                    :href="route('quality.tests.cube-report-pdf', { test: actionTest.id, inline: 1 })" target="_blank"
                    rel="noopener" @click="closeActions"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                    <i class="pi pi-print" aria-hidden="true"></i>
                    <span>Print PDF</span>
                </a>
                <button v-else type="button" disabled title="PDF unavailable for this test"
                    class="flex cursor-not-allowed items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-left text-sm text-slate-400">
                    <i class="pi pi-print" aria-hidden="true"></i>
                    <span>PDF unavailable</span>
                </button>
                <Link
                    :href="route('quality.tests.execute', actionTest.id)"
                    @click="closeActions"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2"
                    :class="actionTest.overall_status === 'pending'
                        ? 'text-emerald-700 hover:bg-emerald-50 focus-visible:ring-emerald-500'
                        : 'text-amber-800 hover:bg-amber-50 focus-visible:ring-amber-500'">
                    <i :class="actionTest.overall_status === 'pending' ? 'pi pi-play' : 'pi pi-pencil'" aria-hidden="true"></i>
                    <span>{{ actionTest.overall_status === 'pending' ? 'Execute test' : 'Edit results' }}</span>
                </Link>
            </div>
        </Popover>

        <div class="min-h-screen min-w-0 max-w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header section with clean breathing room -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/20">
                        <i class="pi pi-verified text-xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                                Laboratory Tests Registry
                            </h1>
                            <span
                                class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                                QC Testing
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 mt-0.5">
                            Track compressive strengths, monitor specimen compliance, and generate laboratory test
                            reports.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 self-start sm:self-auto">
                    <Link :href="route('quality.samples.index')" class="inline-flex">
                        <BaseButton icon="pi pi-plus" label="New Sample / Cast Cubes" variant="filled"
                            severity="primary"
                            class="!px-4 !py-2 !rounded-xl !shadow-xs !font-semibold !text-xs !bg-indigo-600 hover:!bg-indigo-700" />
                    </Link>
                </div>
            </div>

            <!-- KPI Metric Summary Cards with Whitespace & Interactive Filtering -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <button v-for="card in kpiCards" :key="card.status" type="button" @click="updateFilters(card.status)"
                    class="group relative flex flex-col justify-between p-4 sm:p-5 rounded-2xl bg-white border text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    :class="[
                        currentStatus === card.status
                            ? `ring-2 shadow-md ${card.activeRing}`
                            : 'border-slate-200/80 hover:border-slate-300 hover:shadow-xs'
                    ]">
                    <div class="flex items-center justify-between w-full">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {{ card.label }}
                        </span>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-105"
                            :class="card.iconBg">
                            <i :class="[card.icon, 'text-sm']" aria-hidden="true"></i>
                        </div>
                    </div>

                    <div class="mt-3 flex items-baseline justify-between w-full">
                        <span class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 tabular-nums">
                            {{ statusCounts[card.countKey] ?? 0 }}
                        </span>
                        <span v-if="currentStatus === card.status"
                            class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                            Active
                        </span>
                    </div>

                    <div class="mt-1 text-xs text-slate-400">
                        {{ card.subtext }}
                    </div>
                </button>
            </div>

            <!-- Modern Filter & Search Control Bar -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-2.5 sm:p-3 shadow-xs">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Segmented Status Tabs -->
                    <div class="flex p-1 bg-slate-100/80 rounded-xl gap-1 overflow-x-auto no-scrollbar" role="tablist"
                        aria-label="Filter laboratory tests by status">
                        <button v-for="tab in statusTabs" :key="tab.value" type="button" role="tab"
                            :aria-selected="currentStatus === tab.value" @click="updateFilters(tab.value)"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all duration-150 focus:outline-none"
                            :class="[
                                currentStatus === tab.value
                                    ? 'bg-white text-indigo-600 shadow-xs font-bold'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]">
                            <i :class="[tab.icon, 'text-xs']" aria-hidden="true"></i>
                            <span>{{ tab.label }}</span>
                            <span class="tabular-nums text-[11px] px-1.5 py-0.2 rounded-full font-bold" :class="[
                                currentStatus === tab.value
                                    ? 'bg-indigo-50 text-indigo-700'
                                    : 'bg-slate-200/70 text-slate-600'
                            ]">
                                {{ statusCounts[tab.count] }}
                            </span>
                        </button>
                    </div>

                    <!-- Search Input with Clear Button -->
                    <div class="w-full sm:w-64 lg:w-72 relative">
                        <div class="relative flex items-center">
                            <i class="pi pi-search absolute left-3 text-xs text-slate-400 pointer-events-none"
                                aria-hidden="true"></i>
                            <input v-model="search" type="text" placeholder="Search test, sample, grade..."
                                aria-label="Search laboratory tests"
                                class="w-full !pl-9 pr-8 py-1.5 text-xs font-medium bg-slate-50/80 border border-slate-200 rounded-xl placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" />
                            <button v-if="search" type="button" @click="clearSearch" aria-label="Clear search"
                                class="absolute right-2.5 text-slate-400 hover:text-slate-600 transition-colors p-1">
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BaseDataTable Container with Refined Spacing & No Awkward Wrapping -->
            <div
                class="qc-tests-table min-w-0 max-w-full rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <BaseDataTable :value="tests.data || []" dataKey="id" :paginator="true" :rows="tests.per_page || 15"
                    :first="((tests.current_page || 1) - 1) * (tests.per_page || 15)" :rowsPerPageOptions="[15, 30, 50]"
                    :lazy="true" :stripedRows="false" :showAdvancedFilter="false" :totalRecords="tests.total"
                    responsiveLayout="scroll" @page="updateFilters(undefined, $event.page + 1)">
                    <!-- Column 1: Test & Sample # -->
                    <Column field="test_no" header="Test & Sample #" style="width: 19%">
                        <template #body="{ data }">
                            <div class="leading-tight space-y-1">
                                <Link :href="route('quality.tests.execute', { test: data.id, view: 1 })"
                                    class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-800 block truncate"
                                    :title="data.test_no">
                                    {{ data.test_no }}
                                </Link>
                                <div class="font-mono text-[11px] text-slate-500 truncate flex items-center gap-1"
                                    :title="data.sample?.sample_no">
                                    <span
                                        class="text-slate-400 font-sans text-[10px] uppercase font-bold tracking-wider">SMP:</span>
                                    <span class="min-w-0 truncate">{{ data.sample?.sample_no || '-' }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 font-sans tabular-nums">
                                    <template v-if="data.test_date">
                                        {{ entityLocaleDate(data.test_date) }}
                                    </template>
                                    <template v-else>
                                        <span class="text-amber-600 font-medium">Not run</span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Column 3: Grade / Sample Context -->
                    <Column field="material" header="Grade & Context" style="width: 17%">
                        <template #body="{ data }">
                            <div v-if="data.sample?.concrete_grade" class="space-y-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span
                                        class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 font-bold text-xs border border-sky-200/80 tabular-nums">
                                        {{ data.sample.concrete_grade.name }}
                                    </span>
                                    <span v-if="data.age_days"
                                        class="px-1 py-0.5 rounded bg-purple-50 text-purple-700 font-bold text-[10px] border border-purple-200/80 tabular-nums">
                                        {{ data.age_days }}D
                                    </span>
                                </div>

                                <div v-if="data.sample.truck_no || data.sample.dispatch?.truck?.truck_no || data.sample.site_name"
                                    class="flex items-center gap-1 text-[11px] text-slate-400 truncate">
                                    <span v-if="data.sample.truck_no || data.sample.dispatch?.truck?.truck_no"
                                        class="font-mono whitespace-nowrap">
                                        {{ data.sample.truck_no || data.sample.dispatch?.truck?.truck_no }}
                                    </span>
                                    <span
                                        v-if="(data.sample.truck_no || data.sample.dispatch?.truck?.truck_no) && data.sample.site_name"
                                        class="text-slate-300">&bull;</span>
                                    <span v-if="data.sample.site_name" class="truncate" :title="data.sample.site_name">
                                        {{ data.sample.site_name }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="space-y-0.5">
                                <div class="font-bold text-slate-800 text-xs truncate"
                                    :title="data.sample?.material?.title">
                                    {{ data.sample?.material?.title || 'Raw Material' }}
                                </div>
                                <div v-if="data.sample?.supplier?.name" class="text-[11px] text-slate-400 truncate"
                                    :title="data.sample.supplier.name">
                                    {{ data.sample.supplier.name }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Column 4: Test & Schedule -->
                    <Column field="test_procedure" header="Test & Schedule" style="width: 32%">
                        <template #body="{ data }">
                            <div class="space-y-0.5">
                                <div class="font-semibold text-slate-800 text-xs whitespace-normal break-words"
                                    :title="data.test_type?.name">
                                    {{ data.test_type?.name }}
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 flex-wrap">
                                    <span class="font-medium text-slate-500">
                                        {{ data.test_type?.standard_reference || 'IS 516' }}
                                    </span>
                                    <span v-if="data.scheduled_date"
                                        class="tabular-nums flex items-center gap-1 text-slate-400">
                                        &bull; Due {{ entityLocaleDate(data.scheduled_date) }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Column 5: Target / Result -->
                    <Column field="target_strength" header="Target / Result" style="width: 14%">
                        <template #body="{ data }">
                            <div v-if="data.results && data.results.length > 0"
                                class="min-w-0 whitespace-normal break-words leading-tight">
                                <div class="flex flex-wrap items-baseline gap-1">
                                    <span class="text-sm font-bold tabular-nums" :class="[
                                        data.overall_status === 'pass'
                                            ? 'text-emerald-600'
                                            : (data.overall_status === 'fail' || data.overall_status === 'retest')
                                                ? 'text-rose-600'
                                                : 'text-slate-900'
                                    ]">
                                        {{ resultValue(data) }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium">{{ data.unit || 'MPa' }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    Target: {{ data.target_strength || '-' }} {{ data.unit || 'MPa' }}
                                </div>
                            </div>
                            <div v-else-if="data.target_strength"
                                class="min-w-0 whitespace-normal break-words leading-tight">
                                <div class="text-xs text-slate-700">
                                    Target: <span class="font-bold text-indigo-600 tabular-nums">{{
                                        data.target_strength }}</span> {{ data.unit || 'MPa' }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    Pending test
                                </div>
                            </div>
                            <span v-else class="text-xs text-slate-400 tabular-nums">-</span>
                        </template>
                    </Column>

                    <!-- Column 6: Status -->
                    <Column field="overall_status" header="Status" style="width: 10%">
                        <template #body="{ data }">
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide border whitespace-nowrap"
                                :class="statusBadgeClass(data.overall_status)">
                                <span class="w-1.5 h-1.5 rounded-full"
                                    :class="statusDotClass(data.overall_status)"></span>
                                <span>{{ statusLabel(data.overall_status) }}</span>
                            </span>
                        </template>
                    </Column>

                    <!-- Column 7: Actions -->
                    <Column header="Actions" style="width: 8%">
                        <template #body="{ data }">
                            <button type="button" @click.stop="openActions($event, data)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :aria-label="'Actions for ' + data.test_no" title="Test actions" aria-haspopup="dialog"
                                aria-controls="qc-test-actions-popover">
                                <i class="pi pi-ellipsis-v" aria-hidden="true"></i>
                            </button>
                        </template>
                    </Column>

                    <!-- Empty state -->
                    <template #empty>
                        <div class="py-12 px-4 text-center">
                            <div
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                <i class="pi pi-inbox text-2xl" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-bold text-slate-800">
                                No laboratory tests found
                            </h3>
                            <p class="mt-1 text-xs text-slate-400 max-w-sm mx-auto">
                                {{ search ? `No results found for "${search}". Try checking for spelling errors or clear
                                filters.` : 'There are currently no laboratory tests registered in this view.' }}
                            </p>
                            <div v-if="search || currentStatus !== 'all'" class="mt-4">
                                <button type="button" @click="clearAllFilters"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors">
                                    <i class="pi pi-filter-slash text-xs"></i>
                                    <span>Reset all filters</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </BaseDataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.qc-tests-table {
    container-type: inline-size;
}

:deep(.p-datatable-table) {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    table-layout: fixed !important;
}

:deep(.p-datatable-table-container) {
    overflow-x: hidden !important;
}

:deep(.p-datatable-thead > tr > th),
:deep(.p-datatable-tbody > tr > td) {
    white-space: normal;
    overflow-wrap: anywhere;
}

:deep(.p-datatable-column-header-content) {
    min-width: 0;
    flex-wrap: wrap;
}

@container (max-width: 900px) {

    :deep(.p-datatable-thead > tr > th),
    :deep(.p-datatable-tbody > tr > td) {
        padding-inline: 0.375rem !important;
    }
}

:deep(.p-datatable-thead > tr > th) {
    @apply !bg-slate-50 !text-slate-600 !font-bold !text-[11px] !uppercase !tracking-wider !py-3 !px-3 !border-b !border-slate-200;
}

:deep(.p-datatable-tbody > tr) {
    @apply !transition-colors !duration-150;
}

:deep(.p-datatable-tbody > tr:hover > td) {
    @apply !bg-slate-50/70;
}

:deep(.p-datatable-tbody > tr > td) {
    @apply !py-3 !px-3 !border-b !border-slate-100 !align-middle;
}

:deep(.p-paginator) {
    @apply !bg-transparent !border-t !border-slate-100 !py-2.5;
}
</style>
