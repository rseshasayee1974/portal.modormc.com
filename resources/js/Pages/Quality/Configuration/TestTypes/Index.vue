<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    testTypes: any;
    materials: any[];
    filters: any;
    categories: string[];
}>();

const toast = useToast();

const searchQuery = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || 'All');
const selectedStatus = ref(props.filters?.status || 'All');
const selectedLayoutType = ref(props.filters?.layout_type || 'All');
const perPage = ref(Number(props.filters?.per_page) || 15);

const statusFilterOptions = [
    { label: 'All Status', value: 'All' },
    { label: 'Active', value: 'Active' },
    { label: 'Inactive', value: 'Inactive' }
];

const layoutTypeOptions = [
    { value: 'SINGLE_TRIAL', label: 'Single Reading / Trial', icon: 'pi pi-check-circle' },
    { value: 'MULTI_TRIAL', label: 'Multi-Specimen Trials Matrix', icon: 'pi pi-table' },
    { value: 'SIEVE_GRADATION', label: 'Sieve Gradation Grid', icon: 'pi pi-sliders-h' },
    { value: 'GAUGE_MATRIX', label: 'Gauge Matrix Breakdown', icon: 'pi pi-percentage' },
    { value: 'TIMED_OBSERVATION', label: 'Timed Chronology', icon: 'pi pi-clock' },
    { value: 'BEFORE_AFTER', label: 'Before / After Mass Delta', icon: 'pi pi-arrows-alt' },
    { value: 'DENSITY_VOLUME', label: 'Mass / Volume / Density', icon: 'pi pi-box' },
    { value: 'OBSERVATION_CLASSIFICATION', label: 'Visual Observation & Defects', icon: 'pi pi-eye' }
];

const layoutFilterOptions = computed(() => [
    { label: 'All UI Formats', value: 'All' },
    ...layoutTypeOptions.map(l => ({ label: l.label, value: l.value }))
]);

const perPageOptions = [
    { label: '15 / page', value: 15 },
    { label: '30 / page', value: 30 },
    { label: '50 / page', value: 50 },
    { label: '100 / page', value: 100 }
];

const hasActiveFilters = computed(() => {
    return (
        Boolean(searchQuery.value) ||
        selectedCategory.value !== 'All' ||
        selectedStatus.value !== 'All' ||
        selectedLayoutType.value !== 'All' ||
        perPage.value !== 15
    );
});

const applyFilters = (page: number = 1) => {
    router.get(route('quality.config.test-types.index'), {
        page: page > 1 ? page : undefined,
        per_page: perPage.value !== 15 ? perPage.value : undefined,
        search: searchQuery.value ? searchQuery.value.trim() : undefined,
        category: selectedCategory.value !== 'All' ? selectedCategory.value : undefined,
        status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
        layout_type: selectedLayoutType.value !== 'All' ? selectedLayoutType.value : undefined,
    }, { preserveState: true, replace: true });
};

const handleSearch = () => {
    applyFilters(1);
};

const clearSearch = () => {
    searchQuery.value = '';
    applyFilters(1);
};

const filterByCategory = (cat: string) => {
    selectedCategory.value = cat;
    applyFilters(1);
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = 'All';
    selectedStatus.value = 'All';
    selectedLayoutType.value = 'All';
    perPage.value = 15;
    applyFilters(1);
};

const onPageChange = (event: any) => {
    const page = event.page !== undefined ? event.page + 1 : Math.floor(event.first / event.rows) + 1;
    if (event.rows && event.rows !== perPage.value) {
        perPage.value = event.rows;
    }
    applyFilters(page);
};

const categoryList = computed(() => {
    return ['All', ...(props.categories || ['Aggregate', 'Cement', 'Concrete', 'Admixture', 'Water', 'General'])];
});

const formatLayoutLabel = (typeValue: string) => {
    if (!typeValue) return 'Single Reading';
    const found = layoutTypeOptions.find(o => o.value === typeValue || o.value === typeValue.toUpperCase());
    if (found) return found.label;
    return typeValue.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const getLayoutIcon = (typeValue: string) => {
    const found = layoutTypeOptions.find(o => o.value === typeValue || o.value === typeValue?.toUpperCase());
    return found ? found.icon : 'pi pi-cog';
};

const toggleStatus = (type: any) => {
    router.post(route('quality.config.test-types.toggle', type.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'Status Updated', detail: 'Test type status updated', life: 1500 });
        }
    });
};
</script>

<template>
    <AppLayout title="QC Test Types Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Test Types" />
        <Toast />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                            QC Test Types Master
                        </h1>
                        <span class="px-2.5 py-0.5 text-xs font-bold font-mono rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            {{ testTypes.total || 0 }} definitions
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Manage quality test definitions, IS standard specifications, and execution templates.
                    </p>
                </div>
                <div>
                    <Link
                        :href="route('quality.config.test-types.create')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-indigo-600/20 transition-all cursor-pointer"
                    >
                        <i class="pi pi-plus text-xs"></i>
                        <span>Add Test Type</span>
                    </Link>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="space-y-3">
                <!-- Sleek Category Filter Tabs & Filter Badges -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <!-- Filter Pills -->
                    <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-800/80 rounded-xl text-xs font-semibold overflow-x-auto max-w-full border border-gray-200/50 dark:border-gray-700/50 shadow-2xs">
                        <button
                            v-for="cat in categoryList"
                            :key="cat"
                            @click="filterByCategory(cat)"
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all whitespace-nowrap cursor-pointer',
                                selectedCategory === cat
                                    ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-300 font-bold shadow-xs'
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                            ]"
                        >
                            {{ cat }}
                        </button>
                    </div>

                    <!-- Reset Filters Button (Visible if active filters) -->
                    <div v-if="hasActiveFilters" class="flex items-center gap-2">
                        <span class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold flex items-center gap-1">
                            <i class="pi pi-filter text-[10px]"></i> Filters Active
                        </span>
                        <button
                            @click="resetFilters"
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-800 transition-colors flex items-center gap-1 cursor-pointer"
                        >
                            <i class="pi pi-times text-[10px]"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- Secondary Filter Bar (Search, Status, UI Layout, Rows Per Page) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5 p-3 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-2xs">
                    <!-- Search Input (Span 5) -->
                    <div class="lg:col-span-5 relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input
                            v-model="searchQuery"
                            @keydown.enter="handleSearch"
                            type="text"
                            placeholder="Search tests, codes, IS standard, material..."
                            class="w-full pl-8 pr-8 py-1.5 bg-gray-50 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 shadow-2xs"
                        />
                        <button
                            v-if="searchQuery"
                            @click="clearSearch"
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                            title="Clear search"
                        >
                            <i class="pi pi-times text-xs"></i>
                        </button>
                    </div>

                    <!-- Status Filter (Span 2) -->
                    <div class="lg:col-span-2">
                        <select
                            v-model="selectedStatus"
                            @change="applyFilters(1)"
                            class="w-full px-2.5 py-1.5 bg-gray-50 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                        >
                            <option v-for="opt in statusFilterOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- UI Format Filter (Span 3) -->
                    <div class="lg:col-span-3">
                        <select
                            v-model="selectedLayoutType"
                            @change="applyFilters(1)"
                            class="w-full px-2.5 py-1.5 bg-gray-50 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                        >
                            <option v-for="opt in layoutFilterOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Rows per page (Span 2) -->
                    <div class="lg:col-span-2">
                        <select
                            v-model="perPage"
                            @change="applyFilters(1)"
                            class="w-full px-2.5 py-1.5 bg-gray-50 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                        >
                            <option v-for="opt in perPageOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sleek Enterprise Data Table -->
            <BaseCard class="!p-0 overflow-hidden border border-gray-200/80 dark:border-gray-800 rounded-2xl shadow-xs">
                <BaseDataTable
                    :value="testTypes.data || []"
                    dataKey="id"
                    :paginator="true"
                    :lazy="true"
                    :first="((testTypes.current_page || 1) - 1) * (testTypes.per_page || 15)"
                    :rows="Number(testTypes.per_page) || 15"
                    :totalRecords="testTypes.total || 0"
                    :rowsPerPageOptions="[10, 15, 25, 30, 50, 100]"
                    @page="onPageChange"
                >
                    <!-- Empty State Template -->
                    <template #empty>
                        <div class="text-center py-12 px-4 space-y-3">
                            <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400">
                                <i class="pi pi-search text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                    No test types found
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Try adjusting your search query, category, status, or layout filters.
                                </p>
                            </div>
                            <div v-if="hasActiveFilters">
                                <button
                                    @click="resetFilters"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 dark:text-indigo-400 hover:bg-indigo-100 transition-colors cursor-pointer"
                                >
                                    <i class="pi pi-refresh text-xs"></i> Reset Filters
                                </button>
                            </div>
                        </div>
                    </template>
                    <!-- Test Type & Standard Reference -->
                    <Column field="name" header="Test Type & Reference" sortable>
                        <template #body="{ data }">
                            <div class="py-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                                        {{ data.name }}
                                    </span>
                                    <span class="font-mono text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                        {{ data.code }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="data.standard_reference" class="font-mono font-medium text-indigo-600 dark:text-indigo-400">
                                        {{ data.standard_reference }}
                                    </span>
                                    <span v-if="data.standard_reference && data.material_type">&bull;</span>
                                    <span v-if="data.material_type">{{ data.material_type }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Category -->
                    <Column field="category" header="Category" sortable style="width: 130px;">
                        <template #body="{ data }">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold"
                                :class="{
                                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300': data.category === 'Aggregate',
                                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300': data.category === 'Concrete',
                                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300': data.category === 'Cement',
                                    'bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-300': data.category === 'Water',
                                    'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300': data.category === 'Admixture',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': !['Aggregate','Concrete','Cement','Water','Admixture'].includes(data.category)
                                }"
                            >
                                {{ data.category }}
                            </span>
                        </template>
                    </Column>

                    <!-- UI Format -->
                    <Column header="UI Layout Format" style="width: 200px;">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300 font-medium">
                                <i :class="getLayoutIcon(data.layout_type)" class="text-gray-400 text-xs"></i>
                                <span>{{ formatLayoutLabel(data.layout_type) }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Parameters & Criteria Metric Chip -->
                    <Column header="Parameters & Criteria" style="width: 200px;">
                        <template #body="{ data }">
                            <Link
                                :href="route('quality.config.test-parameters.index', { test_type_id: data.id })"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors group"
                            >
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-mono text-[11px] font-bold">
                                    {{ data.parameters?.length || 0 }} params
                                </span>
                                <span v-if="data.parameters?.some((p: any) => p.rule_type)" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    {{ data.parameters?.filter((p: any) => p.rule_type).length }} criteria
                                </span>
                                <i class="pi pi-arrow-right text-[9px] text-gray-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all"></i>
                            </Link>
                        </template>
                    </Column>

                    <!-- Status Indicator -->
                    <Column field="is_active" header="Status" style="width: 110px;">
                        <template #body="{ data }">
                            <button @click="toggleStatus(data)" type="button" class="inline-flex items-center gap-1.5 text-xs font-semibold cursor-pointer">
                                <span class="w-2 h-2 rounded-full" :class="data.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'"></span>
                                <span :class="data.is_active ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400'">
                                    {{ data.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </button>
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" alignFrozen="right" freezeRight style="width: 100px;">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="route('quality.config.test-types.edit', data.id)"
                                    class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                    title="Edit Test Type"
                                >
                                    <i class="pi pi-pencil text-xs"></i>
                                </Link>
                                <BaseDeleteButton :url="route('quality.config.test-types.destroy', data.id)" />
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
