<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import TestTypeForm from './TestTypeForm.vue';

const props = defineProps<{
    testTypes: any;
    materials: any[];
    filters: any;
    categories: string[];
    editingTestType?: any;
}>();

const toast = useToast();
const page = usePage();

const formContainerRef = ref<HTMLElement | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const isRowExpanded = (row: any) => {
    if (!row || row.id === undefined) return false;
    if (Array.isArray(expandedRows.value)) {
        return (expandedRows.value as any[]).some((r: any) => (r.id || r) === row.id);
    }
    return Boolean(expandedRows.value && expandedRows.value[row.id]);
};

const toggleRowEdit = (row: any) => {
    if (!row || row.id === undefined) return;
    const id = row.id;
    if (isRowExpanded(row)) {
        closeExpansion(row);
    } else {
        if (Array.isArray(expandedRows.value)) {
            expandedRows.value = [row];
        } else {
            expandedRows.value = { [id]: true };
        }
    }
};

const closeExpansion = (row?: any) => {
    if (row && row.id !== undefined) {
        if (Array.isArray(expandedRows.value)) {
            expandedRows.value = (expandedRows.value as any[]).filter((r: any) => (r.id || r) !== row.id);
        } else {
            const newExp = { ...expandedRows.value };
            delete newExp[row.id];
            expandedRows.value = newExp;
        }
    } else {
        expandedRows.value = {};
    }
};

const handleRowSaved = (row: any) => {
    closeExpansion(row);
    toast.add({ severity: 'success', summary: 'Updated', detail: 'Test type updated successfully', life: 2500 });
};

const getRowClass = (data: any) => {
    return isRowExpanded(data) ? '!bg-amber-50/40 dark:!bg-amber-950/20' : '';
};

watch(
    () => props.editingTestType,
    (newVal) => {
        if (newVal && newVal.id) {
            expandedRows.value = { [newVal.id]: true };
        }
    },
    { immediate: true }
);

watch(
    () => (page.props as any).flash,
    (flash: any) => {
        if (flash?.success) {
            toast.add({ severity: 'success', summary: 'Success', detail: flash.success, life: 2500 });
        }
        if (flash?.error) {
            toast.add({ severity: 'error', summary: 'Error', detail: flash.error, life: 3000 });
        }
    },
    { immediate: true, deep: true }
);

const handleFormSaved = () => {
    toast.add({ severity: 'success', summary: 'Created', detail: 'New test type defined successfully', life: 2500 });
};

const selectedCategory = ref(props.filters?.category || 'All');
const perPage = ref(Number(props.filters?.per_page) || 15);

const tableFilters = ref({
    global: { value: props.filters?.search || '', matchMode: 'contains' }
});

let searchDebounceTimer: any = null;

watch(
    () => tableFilters.value?.global?.value,
    (newVal) => {
        const query = newVal ? String(newVal).trim() : '';
        if (query === (props.filters?.search || '')) return;

        if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
        }
        searchDebounceTimer = setTimeout(() => {
            applyFilters(1);
        }, 350);
    }
);

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        if (tableFilters.value?.global) {
            tableFilters.value.global.value = newFilters.search || '';
        }
        selectedCategory.value = newFilters.category || 'All';
        perPage.value = Number(newFilters.per_page) || 15;
    }
}, { deep: true });

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

const hasActiveFilters = computed(() => {
    return (
        Boolean(tableFilters.value?.global?.value) ||
        selectedCategory.value !== 'All' ||
        perPage.value !== 15
    );
});

const applyFilters = (targetPage: number = 1) => {
    const searchVal = tableFilters.value?.global?.value ? String(tableFilters.value.global.value).trim() : undefined;
    router.get(route('quality.config.test-types.index'), {
        page: targetPage > 1 ? targetPage : undefined,
        per_page: perPage.value !== 15 ? perPage.value : undefined,
        search: searchVal || undefined,
        category: selectedCategory.value !== 'All' ? selectedCategory.value : undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

const filterByCategory = (cat: string) => {
    selectedCategory.value = cat;
    applyFilters(1);
};

const resetFilters = () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    if (tableFilters.value?.global) {
        tableFilters.value.global.value = '';
    }
    selectedCategory.value = 'All';
    perPage.value = 15;
    applyFilters(1);
};

const onRowsChange = (newRows: number) => {
    if (newRows && Number(newRows) !== perPage.value) {
        perPage.value = Number(newRows);
        applyFilters(1);
    }
};

const onPageChange = (event: any) => {
    const targetPage = event.page !== undefined ? event.page + 1 : Math.floor((event.first || 0) / (event.rows || perPage.value)) + 1;
    if (event.rows && Number(event.rows) !== perPage.value) {
        perPage.value = Number(event.rows);
    }
    applyFilters(targetPage);
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

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            

            <!-- Form on Top of the Page (Create New Test Type) -->
            <div ref="formContainerRef" class="scroll-mt-6">
                <TestTypeForm
                    :categories="categories"
                    :isEditing="false"
                    @saved="handleFormSaved"
                />
            </div>

            <!-- Sleek Enterprise Data Table with Integrated Heading & Filter Header -->
            <BaseDataTable
                :value="testTypes.data || []"
                dataKey="id"
                showSerial
                showSearch
                :showAdvancedFilter="false"
                v-model:filters="tableFilters"
                v-model:expandedRows="expandedRows"
                :rowClass="getRowClass"
                :paginator="true"
                :lazy="true"
                :first="((testTypes.current_page || 1) - 1) * perPage"
                :rows="perPage"
                :totalRecords="testTypes.total || 0"
                :rowsPerPageOptions="[10, 15, 25, 30, 50, 100]"
                heading="List of Test Types"
                headingIcon="pi pi-list"
                class="rounded-2xl overflow-hidden shadow-xs border border-gray-200/80 dark:border-gray-800"
                @page="onPageChange"
                @update:rows="onRowsChange"
            >
                <!-- Integrated Filters Header inside BaseDataTable -->
                <template #header>
                    <div class="p-3.5 bg-gray-50/70 dark:bg-gray-900/70 border-b border-gray-200/80 dark:border-gray-800">
                        <!-- Category Filter Tabs & Badges -->
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                            <!-- Filter Pills -->
                            <div class="inline-flex p-1 bg-white dark:bg-gray-800 rounded-xl text-xs font-semibold overflow-x-auto max-w-full border border-gray-200/60 dark:border-gray-700/60 shadow-2xs">
                                <button
                                    v-for="cat in categoryList"
                                    :key="cat"
                                    @click="filterByCategory(cat)"
                                    type="button"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg transition-all whitespace-nowrap cursor-pointer',
                                        selectedCategory === cat
                                            ? 'bg-indigo-600 text-white font-bold shadow-xs'
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
                    </div>
                </template>
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
                    <Column header="Actions" alignFrozen="right" freezeRight style="width: 35px; text-align: right;">
                        <template #body="{ data }">
                            <div class="">
                                
                                <BaseDeleteButton :url="route('quality.config.test-types.destroy', data.id)" />
                            </div>
                        </template>
                    </Column>

                    <!-- Row Expansion Inline Editor -->
                    <template #expansion="{ data }">
                        <div class="bg-gradient-to-b from-amber-50/40 via-slate-50/60 to-white dark:from-amber-950/20 dark:via-slate-900/60 dark:to-gray-950">
                            
                                <!-- Inline Edit Form for this Row -->
                                <TestTypeForm
                                    :testType="data"
                                    :categories="categories"
                                    :isEditing="true"
                                    :isExpansion="true"
                                    @saved="handleRowSaved(data)"
                                    @cancel="closeExpansion(data)"
                                />
                           
                        </div>
                    </template>
                </BaseDataTable>
            </div>
    </AppLayout>
</template>
