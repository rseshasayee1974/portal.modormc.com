<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ParameterForm from './ParameterForm.vue';

const props = defineProps<{
    testTypes: any[];
    selectedTestTypeId: number;
    parameters: any[];
    units: any[];
    ruleTypes?: string[];
    ruleTypeLabels?: Record<string, string>;
}>();

const toast = useToast();
const page = usePage();

const activeTestTypeId = ref<number>(props.selectedTestTypeId);
watch(() => props.selectedTestTypeId, (newVal) => {
    activeTestTypeId.value = newVal;
});

const currentTestType = computed(() => {
    return (props.testTypes || []).find(t => t.id === activeTestTypeId.value) || null;
});

const testTypeOptions = computed(() => {
    return (props.testTypes || []).map(t => ({
        label: `${t.name} (${t.code}) - ${t.category}`,
        value: t.id
    }));
});

const changeTestType = () => {
    closeExpansion();
    router.get(route('quality.config.test-parameters.index'), {
        test_type_id: activeTestTypeId.value,
    }, { preserveState: true, replace: true });
};

// Row Expansion Management
const expandedRows = ref<any[]>([]);

const isRowExpanded = (row: any) => {
    if (!row || row.id === undefined) return false;
    if (Array.isArray(expandedRows.value)) {
        return expandedRows.value.some((r: any) => (r?.id !== undefined ? r.id === row.id : r === row.id));
    }
    return Boolean(expandedRows.value && (expandedRows.value as any)[row.id]);
};

const toggleRowEdit = (row: any) => {
    if (!row || row.id === undefined) return;
    if (isRowExpanded(row)) {
        closeExpansion(row);
    } else {
        expandedRows.value = [row];
    }
};

const closeExpansion = (row?: any) => {
    if (row && row.id !== undefined) {
        if (Array.isArray(expandedRows.value)) {
            expandedRows.value = expandedRows.value.filter((r: any) => (r?.id !== undefined ? r.id !== row.id : r !== row.id));
        } else {
            const newExp = { ...expandedRows.value };
            delete (newExp as any)[row.id];
            expandedRows.value = newExp;
        }
    } else {
        expandedRows.value = [];
    }
};

const getRowClass = (data: any) => {
    return isRowExpanded(data) ? '!bg-amber-50/40 dark:!bg-amber-950/20' : '';
};

// Flash messages
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

const handleTopFormSaved = () => {
    toast.add({ severity: 'success', summary: 'Created', detail: 'New QC parameter defined successfully', life: 2500 });
};

const handleRowSaved = (row: any) => {
    closeExpansion(row);
    toast.add({ severity: 'success', summary: 'Updated', detail: 'Parameter updated successfully', life: 2500 });
};

// Table filtering
const tableFilters = ref({
    global: { value: null, matchMode: 'contains' }
});

const getUnitDisplay = (unitSymbol: string) => {
    if (!unitSymbol) return '-';
    const found = (props.units || []).find(u => u.symbol === unitSymbol || u.code === unitSymbol);
    return found ? `${found.name} (${found.symbol || found.code})` : unitSymbol;
};

const formatParamRule = (param: any) => {
    if (!param.rule_type) return null;
    const unit = param.unit ? ` ${param.unit}` : '';
    switch (param.rule_type) {
        case 'MIN_MAX':
        case 'RANGE':
            if (param.min_value !== null && param.max_value !== null) {
                return `${Number(param.min_value)} – ${Number(param.max_value)}${unit}`;
            }
            return param.min_value !== null ? `≥ ${Number(param.min_value)}${unit}` : `≤ ${Number(param.max_value)}${unit}`;
        case 'GREATER_THAN':
            return `> ${Number(param.min_value)}${unit}`;
        case 'MIN_ONLY':
        case 'GREATER_THAN_OR_EQUAL':
            return `≥ ${Number(param.min_value)}${unit}`;
        case 'LESS_THAN':
            return `< ${Number(param.max_value)}${unit}`;
        case 'MAX_ONLY':
        case 'LESS_THAN_OR_EQUAL':
            return `≤ ${Number(param.max_value)}${unit}`;
        case 'EXACT_VALUE':
        case 'EQUAL':
            return `= ${Number(param.target_value)}${unit}`;
        case 'TARGET_TOLERANCE':
            return `${Number(param.target_value)} ± ${Number(param.tolerance)}${unit}`;
        case 'PASS_FAIL':
            return 'Pass / Fail Criteria';
        default:
            return param.rule_type;
    }
};
</script>

<template>
    <AppLayout title="QC Test Parameters Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Test Parameters" />
        <Toast />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <!-- <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-gray-200/80 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('quality.config.test-types.index')"
                        class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors shadow-2xs"
                        title="Back to Test Types"
                    >
                        <i class="pi pi-arrow-left text-xs font-bold"></i>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                                {{ currentTestType?.name || 'QC Test Parameters' }}
                            </h1>
                            <span v-if="currentTestType?.code" class="text-xs font-mono font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                                {{ currentTestType.code }}
                            </span>
                            <span v-if="currentTestType?.category" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                                {{ currentTestType.category }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Define measured input variables, formula calculation expressions, and automated acceptance rules.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('quality.config.test-types.index')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                    >
                        <i class="pi pi-list text-xs"></i>
                        <span>All Test Types</span>
                    </Link>
                </div>
            </div> -->

            <!-- Test Type Selection Switcher Bar -->
            <!-- <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-2xl shadow-2xs">
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">Current Test Definition:</span>
                    <span class="font-bold text-gray-900 dark:text-gray-100">
                        {{ currentTestType?.name || 'Select Test Type' }}
                    </span>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                        {{ parameters?.length || 0 }} {{ parameters?.length === 1 ? 'parameter' : 'parameters' }}
                    </span>
                </div>
                <div class="w-full sm:w-96">
                    <BaseSelect
                        v-model="activeTestTypeId"
                        :options="testTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Switch Test Type"
                        @change="changeTestType"
                    />
                </div>
            </div> -->

            <!-- Form on Top: Create New QC Parameter -->
            <div>
                <ParameterForm
                    :testTypes="testTypes"
                    :selectedTestTypeId="activeTestTypeId"
                    :units="units"
                    :ruleTypes="ruleTypes"
                    :ruleTypeLabels="ruleTypeLabels"
                    :availableParameters="parameters"
                    :isEditing="false"
                    @saved="handleTopFormSaved"
                />
            </div>

            <!-- Parameters Data Table Below -->
            <BaseDataTable
                :value="parameters || []"
                dataKey="id"
                showSerial
                showSearch
                :globalFilterFields="['name', 'code', 'formula', 'unit', 'default_value', 'rule_type', 'standard_reference']"
                v-model:filters="tableFilters"
                v-model:expandedRows="expandedRows"
                :rowClass="getRowClass"
                heading="Configured Test Parameters"
                headingIcon="pi pi-sliders-h"
                class="rounded-2xl overflow-hidden shadow-xs border border-gray-200/80 dark:border-gray-800"
            >
                <!-- Empty State -->
                <template #empty>
                    <div class="text-center py-12 px-4 space-y-3">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400">
                            <i class="pi pi-sliders-h text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                No parameters defined for {{ currentTestType?.name || 'this test' }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Use the parameter creation form above to define your first variable and acceptance limits.
                            </p>
                        </div>
                    </div>
                </template>

                <!-- Expander Column for Inline Edit -->
                <Column expander style="width: 3.5rem; text-align: center" />

                <!-- Display Order -->
                <Column field="display_order" header="#" sortable style="width: 65px;">
                    <template #body="{ data }">
                        <span class="font-mono text-xs font-bold text-gray-400">
                            #{{ data.display_order }}
                        </span>
                    </template>
                </Column>

                <!-- Parameter Name & Code -->
                <Column field="name" header="Parameter Name & Code" sortable>
                    <template #body="{ data }">
                        <div class="py-1 space-y-0.5">
                            <div class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                                {{ data.name }}
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono text-[10px] font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                                    {{ data.code }}
                                </span>
                                <span v-if="data.is_required" class="text-[9px] font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-1.5 py-0.2 rounded uppercase">
                                    Required
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Type (Measured vs Calculated) -->
                <Column header="Type" style="width: 140px;">
                    <template #body="{ data }">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold"
                            :class="data.is_calculated
                                ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-200/60 dark:border-purple-800/60'
                                : 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="data.is_calculated ? 'bg-purple-600' : 'bg-blue-600'"></span>
                            {{ data.is_calculated ? 'Calculated' : 'Measured' }}
                        </span>
                    </template>
                </Column>

                <!-- Unit -->
                <Column field="unit" header="Unit" style="width: 130px;">
                    <template #body="{ data }">
                        <span v-if="data.unit" class="font-mono text-xs text-gray-700 dark:text-gray-300">
                            {{ getUnitDisplay(data.unit) }}
                        </span>
                        <span v-else class="text-gray-400 text-xs">-</span>
                    </template>
                </Column>

                <!-- Default / Standard Value -->
                <Column field="default_value" header="Default Value" style="width: 150px;">
                    <template #body="{ data }">
                        <span v-if="data.default_value !== null && data.default_value !== ''" class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200/60 dark:border-emerald-800/60">
                            {{ data.default_value }} <span v-if="data.unit" class="text-[10px] text-gray-500 font-normal">({{ data.unit }})</span>
                        </span>
                        <span v-else class="text-gray-400 text-xs italic">-</span>
                    </template>
                </Column>

                <!-- Formula Expression -->
                <Column field="formula" header="Formula Expression">
                    <template #body="{ data }">
                        <span v-if="data.is_calculated && data.formula" class="font-mono text-xs text-purple-700 dark:text-purple-300 bg-purple-50/70 dark:bg-purple-950/40 px-2.5 py-1 rounded border border-purple-200/60 dark:border-purple-800/60">
                            {{ data.formula }}
                        </span>
                        <span v-else-if="!data.is_calculated" class="text-gray-400 text-xs italic">
                            Lab Input
                        </span>
                        <span v-else class="text-rose-500 text-xs font-medium">
                            Missing formula
                        </span>
                    </template>
                </Column>

                <!-- Acceptance Criteria / Limits -->
                <Column header="Acceptance Criteria / Limit" style="width: 220px;">
                    <template #body="{ data }">
                        <div v-if="data.rule_type" class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-mono font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200/60 dark:border-amber-800/60">
                                <i class="pi pi-check-circle text-[10px] text-amber-600 dark:text-amber-400"></i>
                                <span>{{ formatParamRule(data) }}</span>
                            </div>
                            <div v-if="data.standard_reference" class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                {{ data.standard_reference }}
                            </div>
                        </div>
                        <span v-else class="text-gray-400 text-xs italic">
                            Informational only
                        </span>
                    </template>
                </Column>

                <!-- Actions -->
                <Column header="Actions" alignFrozen="right" freezeRight style="width: 90px; text-align: right;">
                    <template #body="{ data }">
                        <div class="flex items-center justify-end gap-1">
                            <button
                                type="button"
                                @click="toggleRowEdit(data)"
                                :class="[
                                    'p-1.5 rounded-lg transition-colors cursor-pointer',
                                    isRowExpanded(data)
                                        ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300'
                                        : 'text-gray-500 hover:text-amber-600 hover:bg-gray-100 dark:hover:bg-gray-800'
                                ]"
                                title="Edit Parameter Inline"
                            >
                                <i class="pi pi-pencil text-xs"></i>
                            </button>
                            <BaseDeleteButton :url="route('quality.config.test-parameters.destroy', data.id)" />
                        </div>
                    </template>
                </Column>

                <!-- Row Expansion: Inline Edit Form -->
                <template #expansion="{ data }">
                    <div class="bg-gradient-to-b from-amber-50/40 via-slate-50/60 to-white dark:from-amber-950/20 dark:via-slate-900/60 dark:to-gray-950">
                        <ParameterForm
                            :parameter="data"
                            :testTypes="testTypes"
                            :units="units"
                            :ruleTypes="ruleTypes"
                            :ruleTypeLabels="ruleTypeLabels"
                            :availableParameters="parameters"
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

<style scoped>
:deep(.p-row-toggler) {
    width: 2rem !important;
    height: 2rem !important;
    border-radius: 0.5rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #6366f1 !important;
    background: #eef2ff !important;
    border: 1px solid #e0e7ff !important;
    transition: all 0.2s ease !important;
    cursor: pointer !important;
}
:deep(.p-row-toggler:hover) {
    background: #6366f1 !important;
    color: #ffffff !important;
    border-color: #6366f1 !important;
    transform: scale(1.08) !important;
}
:deep(.dark .p-row-toggler) {
    background: rgba(99, 102, 241, 0.15) !important;
    border-color: rgba(99, 102, 241, 0.3) !important;
    color: #a5b4fc !important;
}
:deep(.dark .p-row-toggler:hover) {
    background: #6366f1 !important;
    color: #ffffff !important;
}
</style>
