<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';

const props = defineProps<{
    testTypes: any[];
    selectedTestTypeId: number;
    parameters: any[];
    units: any[];
    ruleTypes?: string[];
    ruleTypeLabels?: Record<string, string>;
}>();

const activeTestTypeId = ref<number>(props.selectedTestTypeId);
watch(() => props.selectedTestTypeId, (newVal) => {
    activeTestTypeId.value = newVal;
});

const currentTestType = computed(() => {
    return (props.testTypes || []).find(t => t.id === activeTestTypeId.value) || null;
});

const testTypeOptions = computed(() => {
    return (props.testTypes || []).map(t => ({
        label: `${t.code} — ${t.name} (${t.category})`,
        value: t.id
    }));
});

const getUnitDisplay = (unitSymbol: string) => {
    if (!unitSymbol) return '-';
    const found = (props.units || []).find(u => u.symbol === unitSymbol || u.code === unitSymbol);
    return found ? `${found.name} (${found.symbol})` : unitSymbol;
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

const changeTestType = () => {
    router.get(route('quality.config.test-parameters.index'), {
        test_type_id: activeTestTypeId.value,
    });
};
</script>

<template>
    <AppLayout title="QC Test Parameters Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Test Parameters" />
        <Toast />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('quality.config.test-types.index')"
                        class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors"
                        title="Back to Test Types"
                    >
                        <i class="pi pi-arrow-left text-xs font-bold"></i>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                                {{ currentTestType?.name || 'Parameters Master' }}
                            </h1>
                            <span class="text-xs font-mono font-bold text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                                {{ currentTestType?.code }}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                                {{ currentTestType?.category || 'Quality Master' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Configure measured input variables, formula expressions, and automated acceptance rules.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link
                        :href="route('quality.config.test-parameters.create', { test_type_id: activeTestTypeId })"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-indigo-600/20 transition-all cursor-pointer"
                    >
                        <i class="pi pi-plus text-xs"></i>
                        <span>Add Parameter</span>
                    </Link>
                </div>
            </div>

            <!-- Test Type Switcher Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-2xl shadow-2xs">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Configuring parameters for: <span class="font-bold text-gray-900 dark:text-gray-100">{{ currentTestType?.name }}</span>
                </div>
                <div class="w-full sm:w-80">
                    <BaseSelect
                        v-model="activeTestTypeId"
                        :options="testTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        @change="changeTestType"
                    />
                </div>
            </div>

            <!-- Parameters Data Table -->
            <BaseCard class="!p-0 overflow-hidden border border-gray-200/80 dark:border-gray-800 rounded-2xl shadow-xs">
                <BaseDataTable
                    :value="parameters || []"
                    dataKey="id"
                >
                    <!-- Order -->
                    <Column field="display_order" header="#" sortable style="width: 60px;">
                        <template #body="{ data }">
                            <span class="font-mono text-xs font-bold text-gray-400">
                                #{{ data.display_order }}
                            </span>
                        </template>
                    </Column>

                    <!-- Parameter Name & Code -->
                    <Column field="name" header="Parameter Name & Code">
                        <template #body="{ data }">
                            <div class="py-1 space-y-0.5">
                                <div class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                                    {{ data.name }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-[10px] font-semibold text-gray-500">
                                        [{{ data.code }}]
                                    </span>
                                    <span v-if="data.is_required" class="text-[9px] font-bold text-rose-500 uppercase">
                                        Required
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Type (Measured vs Calculated) -->
                    <Column header="Type" style="width: 150px;">
                        <template #body="{ data }">
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold"
                                :class="data.is_calculated
                                    ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300'
                                    : 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="data.is_calculated ? 'bg-purple-600' : 'bg-blue-600'"></span>
                                {{ data.is_calculated ? 'Calculated' : 'Measured' }}
                            </span>
                        </template>
                    </Column>

                    <!-- Unit -->
                    <Column field="unit" header="Unit" style="width: 120px;">
                        <template #body="{ data }">
                            <span v-if="data.unit" class="font-mono text-xs text-gray-700 dark:text-gray-300">
                                {{ getUnitDisplay(data.unit) }}
                            </span>
                            <span v-else class="text-gray-400 text-xs">-</span>
                        </template>
                    </Column>

                    <!-- Default / Standard Value -->
                    <Column field="default_value" header="Default / Standard Value" style="width: 170px;">
                        <template #body="{ data }">
                            <span v-if="data.default_value !== null && data.default_value !== ''" class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                                {{ data.default_value }} <span v-if="data.unit" class="text-[10px] text-gray-500 font-normal">({{ data.unit }})</span>
                            </span>
                            <span v-else class="text-gray-400 text-xs italic">-</span>
                        </template>
                    </Column>

                    <!-- Formula -->
                    <Column field="formula" header="Formula Expression">
                        <template #body="{ data }">
                            <span v-if="data.is_calculated && data.formula" class="font-mono text-xs text-purple-700 dark:text-purple-300 bg-purple-50/70 dark:bg-purple-950/40 px-2 py-0.5 rounded">
                                {{ data.formula }}
                            </span>
                            <span v-else-if="!data.is_calculated" class="text-gray-400 text-xs italic">
                                Lab Entry
                            </span>
                            <span v-else class="text-rose-500 text-xs font-medium">
                                Missing formula
                            </span>
                        </template>
                    </Column>

                    <!-- Acceptance Criteria / Limits -->
                    <Column header="Acceptance Criteria / Limit" style="width: 220px;">
                        <template #body="{ data }">
                            <div v-if="data.rule_type" class="space-y-0.5">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-mono font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200/60 dark:border-amber-800/60">
                                    <i class="pi pi-check-circle text-[10px] text-amber-600 dark:text-amber-400"></i>
                                    <span>{{ formatParamRule(data) }}</span>
                                </div>
                            </div>
                            <span v-else class="text-gray-400 text-xs italic">
                                None (Info only)
                            </span>
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" alignFrozen="right" freezeRight style="width: 100px;">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="route('quality.config.test-parameters.edit', data.id)"
                                    class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                    title="Edit Parameter"
                                >
                                    <i class="pi pi-pencil text-xs"></i>
                                </Link>
                                <BaseDeleteButton :url="route('quality.config.test-parameters.destroy', data.id)" />
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
