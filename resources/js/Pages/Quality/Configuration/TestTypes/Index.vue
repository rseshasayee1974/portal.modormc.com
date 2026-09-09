<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ToggleSwitch from 'primevue/toggleswitch';
import TestTypeForm from './TestTypeForm.vue';

const props = defineProps<{
    products: any;
    testTypes: any;
    concrete_grade: any;
    units?: any;
}>();

const toast = useToast();
const page = usePage();

// Row expansion management (Object-based for PrimeVue dataKey & BaseDataTable compatibility)
const expandedRows = ref<Record<number, boolean>>({});

const isRowExpanded = (row: any) => {
    if (!row || row.id === undefined) return false;
    return Boolean(expandedRows.value?.[row.id]);
};

const toggleRowEdit = (row: any) => {
    if (!row || row.id === undefined) return;
    if (expandedRows.value[row.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [row.id]: true };
    }
};

const closeExpansion = (row?: any) => {
    expandedRows.value = {};
};

const handleRowSaved = (row: any) => {
    closeExpansion(row);
    toast.add({ severity: 'success', summary: 'Updated', detail: 'Test type updated successfully', life: 2500 });
};

const handleFormSaved = () => {
    toast.add({ severity: 'success', summary: 'Created', detail: 'New test type defined successfully', life: 2500 });
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

// Helper to resolve material/grade display name
const getMaterialDisplay = (data: any) => {
    if (!data?.material_type) return '';
    // Check if it matches concrete grade design_type, id, or name
    const grade = (props.concrete_grade || []).find((g: any) => 
        (g.design_type && String(g.design_type) === String(data.material_type)) ||
        String(g.id) === String(data.material_type) || 
        g.name === data.material_type
    );
    if (grade) {
        return grade.design_type || grade.name;
    }
    // Check if it matches product id or title
    const prod = (props.products || []).find((p: any) => String(p.id) === String(data.material_type) || (p.title || p.name) === data.material_type);
    if (prod) {
        return prod.title || prod.name;
    }
    return data.material_type;
};

// Client-side table search & filter
const tableFilters = ref({
    global: { value: null, matchMode: 'contains' }
});

const hasActiveFilters = computed(() => {
    return Boolean(tableFilters.value?.global?.value);
});

const resetFilters = () => {
    if (tableFilters.value?.global) {
        tableFilters.value.global.value = null;
    }
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
            <div>
                <TestTypeForm
                    :products="products"
                    :concrete_grade="concrete_grade"
                    :units="units"
                    :isEditing="false"
                    @saved="handleFormSaved"
                />
            </div>

            <!-- Sleek Enterprise Data Table with Integrated Heading, Expander & Search -->
            <BaseDataTable
                :value="testTypes || []"
                dataKey="id"
                showSerial
                showSearch
                :showAdvancedFilter="false"
                v-model:filters="tableFilters"
                :globalFilterFields="['name', 'code', 'standard_reference', 'material_type']"
                v-model:expandedRows="expandedRows"
                :rowClass="getRowClass"
                :paginator="true"
                :rows="30"
                :rowsPerPageOptions="[30, 50, 100]"
                heading="List of Test Types"
                headingIcon="pi pi-list"
                class="rounded-2xl overflow-hidden shadow-xs border border-gray-200/80 dark:border-gray-800"
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
                                Try adjusting your search query.
                            </p>
                        </div>
                        <div v-if="hasActiveFilters">
                            <button
                                @click="resetFilters"
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 dark:text-indigo-400 hover:bg-indigo-100 transition-colors cursor-pointer"
                            >
                                <i class="pi pi-refresh text-xs"></i> Reset Search
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Expander Column for Inline Edit -->
                <!-- <Column expander style="width: 3.5rem; text-align: center" /> -->

                <!-- Test Type & Standard Reference -->
                <Column field="name" header="Test Name" sortable>
                    <template #body="{ data }">
                        <div class="py-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-900 dark:text-gray-100 text-xs">
                                    {{ data.name }}
                                </span>
                                <!-- <span class="font-mono text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                    {{ data.code }}
                                </span> -->
                                
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <!-- <span v-if="data.standard_reference" class="font-mono font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ data.standard_reference }}
                                </span> -->
                                <!-- <span v-if="data.standard_reference && data.material_type">&bull;</span> -->
                                <span v-if="data.material_type" class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 font-semibold text-[11px]">
                                    {{ getMaterialDisplay(data) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                 <Column header="Category" style="min-width: 120px;">
                    <template #body="{ data }">
                       <span
                            v-if="data.category"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                            :class="(data.category || '').toLowerCase() === 'concrete' ? 'bg-blue-50 text-blue-700 border border-blue-200/60 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60' : ((data.category || '').toLowerCase().includes('raw') ? 'bg-amber-50 text-amber-700 border border-amber-200/60 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/60' : 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300')"
                        >
                            {{ data.category }}
                        </span>
                    </template>
                </Column>

                <!-- QC Parameters Preview -->
                <Column header="QC Parameters" style="min-width: 220px;">
                    <template #body="{ data }">
                        <div v-if="data.parameters && data.parameters.length" class="flex flex-wrap gap-1.5 items-center py-1">
                            <span
                                v-for="param in data.parameters.slice(0, 3)"
                                :key="param.id"
                                class="px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
                            >
                                {{ param.name }}: {{ param.target_value ?? param.min_value }} {{ param.unit || 'MPa' }}
                            </span>
                            <span v-if="data.parameters.length > 3" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold">
                                +{{ data.parameters.length - 3 }} more
                            </span>
                        </div>
                        <span v-else class="text-xs text-gray-400 italic">No parameters</span>
                    </template>
                </Column>

                <!-- Status Indicator -->
                <Column field="is_active" header="Status" sortable style="width: 120px;">
                    <template #body="{ data }">
                        <button
                            type="button"
                            @click="toggleStatus(data)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer select-none border shadow-2xs hover:shadow-xs active:scale-95"
                            :class="data.is_active
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200/80 hover:bg-emerald-100/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60'
                                : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'"
                            title="Click to toggle status"
                        >
                            <span
                                class="w-1.5 h-1.5 rounded-full"
                                :class="data.is_active ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.7)]' : 'bg-gray-400'"
                            ></span>
                            <span>{{ data.is_active ? 'Active' : 'Inactive' }}</span>
                        </button>
                    </template>
                </Column>

                <!-- Actions -->
                <Column header="" alignFrozen="right" freezeRight style="width: 50px; text-align: right;">
                    <template #body="{ data }">
                        <div class="flex items-center justify-end gap-1">
                            
                            <BaseDeleteButton :url="route('quality.config.test-types.destroy', data.id)" />
                        </div>
                    </template>
                </Column>

                <!-- Row Expansion Inline Editor -->
                <template #expansion="{ data }">
                    <div class="bg-gradient-to-b from-amber-50/40 via-slate-50/60 to-white dark:from-amber-950/20 dark:via-slate-900/60 dark:to-gray-950">
                        <TestTypeForm
                            :testType="data"
                            :products="products"
                            :concrete_grade="concrete_grade"
                            :units="units"
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
