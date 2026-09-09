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
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import UnitForm from './UnitForm.vue';

const props = defineProps<{
    units: any;
    filters: any;
    dimensions: string[];
}>();

const toast = useToast();
const page = usePage();

// Normalized list of units
const unitsList = computed<any[]>(() => {
    if (Array.isArray(props.units)) return props.units;
    if (props.units && Array.isArray(props.units.data)) return props.units.data;
    return [];
});

// UI State: inline create form toggle & edit modal
const showCreatePanel = ref(false);
const editingUnit = ref<any | null>(null);
const showEditModal = ref(false);

// Active Dimension Filter tab
const selectedDimension = ref<string>('all');

// Dimension styling map
const dimensionMeta: Record<string, { label: string; icon: string; badgeClass: string; sample: string }> = {
    pressure: {
        label: 'Pressure / Stress',
        icon: 'pi pi-chart-line',
        badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
        sample: '30.00'
    },
    force: {
        label: 'Force / Load',
        icon: 'pi pi-bolt',
        badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800',
        sample: '450.0'
    },
    mass: {
        label: 'Mass / Weight',
        icon: 'pi pi-box',
        badgeClass: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
        sample: '8.40'
    },
    length: {
        label: 'Length / Slump',
        icon: 'pi pi-arrows-h',
        badgeClass: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
        sample: '125'
    },
    area: {
        label: 'Area',
        icon: 'pi pi-table',
        badgeClass: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/60 dark:text-orange-300 dark:border-orange-800',
        sample: '22500'
    },
    volume: {
        label: 'Volume',
        icon: 'pi pi-database',
        badgeClass: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/60 dark:text-violet-300 dark:border-violet-800',
        sample: '0.0033'
    },
    density: {
        label: 'Density',
        icon: 'pi pi-filter',
        badgeClass: 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/60 dark:text-cyan-300 dark:border-cyan-800',
        sample: '2410'
    },
    temperature: {
        label: 'Temperature',
        icon: 'pi pi-sun',
        badgeClass: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
        sample: '27.5'
    },
    time: {
        label: 'Time / Age',
        icon: 'pi pi-clock',
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        sample: '28'
    },
    ratio: {
        label: 'Ratio / %',
        icon: 'pi pi-percentage',
        badgeClass: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800',
        sample: '95.0'
    },
    other: {
        label: 'Other',
        icon: 'pi pi-tag',
        badgeClass: 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
        sample: '100'
    }
};

const getDimensionMeta = (dim: string) => {
    return dimensionMeta[dim?.toLowerCase()] || dimensionMeta.other;
};

// Summary metrics
const metrics = computed(() => {
    const all = unitsList.value;
    const total = all.length;
    const active = all.filter(u => u.is_active).length;
    const distinctDimensions = new Set(all.map(u => u.dimension).filter(Boolean)).size;
    const pressureCount = all.filter(u => u.dimension === 'pressure').length;
    const forceCount = all.filter(u => u.dimension === 'force').length;
    return { total, active, distinctDimensions, pressureCount, forceCount };
});

// Dimension filter counts
const dimensionCounts = computed(() => {
    const counts: Record<string, number> = { all: unitsList.value.length };
    unitsList.value.forEach(u => {
        const dim = (u.dimension || 'other').toLowerCase();
        counts[dim] = (counts[dim] || 0) + 1;
    });
    return counts;
});

// Filtered units by dimension tab
const filteredUnits = computed(() => {
    if (selectedDimension.value === 'all') return unitsList.value;
    return unitsList.value.filter(u => (u.dimension || 'other').toLowerCase() === selectedDimension.value);
});

// Client-side search filters for BaseDataTable
const tableFilters = ref({
    global: { value: null, matchMode: 'contains' }
});

const hasActiveFilters = computed(() => {
    return Boolean(tableFilters.value?.global?.value) || selectedDimension.value !== 'all';
});

const resetFilters = () => {
    if (tableFilters.value?.global) {
        tableFilters.value.global.value = null;
    }
    selectedDimension.value = 'all';
};

// Toggle status inline
const toggleStatus = (unit: any) => {
    router.post(route('quality.config.units.toggle', unit.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'Status Updated', detail: `Unit "${unit.name}" status updated`, life: 1500 });
        }
    });
};

// Edit in Modal
const openEditModal = (unit: any) => {
    editingUnit.value = unit;
    showEditModal.value = true;
};

const handleUnitSaved = () => {
    showCreatePanel.value = false;
    showEditModal.value = false;
    editingUnit.value = null;
    toast.add({ severity: 'success', summary: 'Success', detail: 'QC Unit saved successfully.', life: 2500 });
};

// Flash listener
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
</script>

<template>
    <AppLayout title="QC Units Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Units Master" />
        <Toast />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- TOP HERO SECTION WITH KPI METRICS -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl shadow-indigo-950/20 border border-indigo-800/40">
                <!-- Background decorative elements -->
                <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute right-1/3 -top-10 w-60 h-60 bg-purple-500/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-400/20 text-xs font-bold uppercase tracking-wider backdrop-blur-md">
                            <i class="pi pi-compass text-[11px]"></i>
                            <span>Quality Master Configuration</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            Units of Measurement (QC Units Master)
                        </h1>
                        <p class="text-xs sm:text-sm text-indigo-200/80 leading-relaxed">
                            Dynamic measurement units (<span class="text-emerald-400 font-mono font-bold">MPa</span>, <span class="text-indigo-300 font-mono font-bold">kN</span>, <span class="text-amber-300 font-mono font-bold">mm</span>, <span class="text-cyan-300 font-mono font-bold">kg/m³</span>, <span class="text-rose-300 font-mono font-bold">°C</span>) used in parameter matrices, lab testing CTM data entry, and NABL/IS compliant customer test certificates.
                        </p>
                    </div>

                    <!-- Top Action Button -->
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="showCreatePanel = !showCreatePanel"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs font-extrabold shadow-lg transition-all cursor-pointer backdrop-blur-md"
                            :class="showCreatePanel
                                ? 'bg-white/10 hover:bg-white/20 text-white border border-white/20'
                                : 'bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white shadow-emerald-500/25'"
                        >
                            <i :class="showCreatePanel ? 'pi pi-times' : 'pi pi-plus'" class="text-xs"></i>
                            <span>{{ showCreatePanel ? 'Close Form' : '+ Add QC Unit' }}</span>
                        </button>
                    </div>
                </div>

                <!-- KPI CARDS ROW -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mt-6 pt-6 border-t border-indigo-800/60">
                    <!-- Total Units -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-bold text-indigo-300 uppercase tracking-wider">Total Units</div>
                        <div class="text-2xl font-black text-white mt-0.5 font-mono">{{ metrics.total }}</div>
                        <div class="text-[10px] text-indigo-200/60 mt-0.5">Defined in system</div>
                    </div>

                    <!-- Active Units -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">Active Units</div>
                        <div class="text-2xl font-black text-emerald-300 mt-0.5 font-mono">{{ metrics.active }}</div>
                        <div class="text-[10px] text-emerald-200/60 mt-0.5">Available for parameters</div>
                    </div>

                    <!-- Physical Dimensions -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-bold text-cyan-300 uppercase tracking-wider">Dimensions</div>
                        <div class="text-2xl font-black text-cyan-200 mt-0.5 font-mono">{{ metrics.distinctDimensions }}</div>
                        <div class="text-[10px] text-cyan-200/60 mt-0.5">Pressure, force, length...</div>
                    </div>

                    <!-- Core Strength Units -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-bold text-amber-300 uppercase tracking-wider">Strength & Load</div>
                        <div class="text-2xl font-black text-amber-200 mt-0.5 font-mono">{{ metrics.pressureCount + metrics.forceCount }}</div>
                        <div class="text-[10px] text-amber-200/60 mt-0.5">MPa, N/mm², kN...</div>
                    </div>
                </div>
            </div>

            <!-- INLINE EXPANDABLE CREATE FORM (Top of the page) -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div v-if="showCreatePanel">
                    <UnitForm
                        :dimensions="dimensions"
                        :isEditing="false"
                        :isModal="true"
                        @saved="handleUnitSaved"
                        @cancel="showCreatePanel = false"
                    />
                </div>
            </transition>

            <!-- DIMENSION FILTER PILLS -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
                <button
                    type="button"
                    @click="selectedDimension = 'all'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5"
                    :class="selectedDimension === 'all'
                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750 border border-gray-200/80 dark:border-gray-700/80'"
                >
                    <span>All Dimensions</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="selectedDimension === 'all' ? 'bg-indigo-700 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                        {{ dimensionCounts.all || 0 }}
                    </span>
                </button>

                <button
                    v-for="(meta, dimKey) in dimensionMeta"
                    :key="dimKey"
                    v-show="dimensionCounts[dimKey] > 0"
                    type="button"
                    @click="selectedDimension = dimKey"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5"
                    :class="selectedDimension === dimKey
                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750 border border-gray-200/80 dark:border-gray-700/80'"
                >
                    <i :class="meta.icon" class="text-[11px]"></i>
                    <span>{{ meta.label }}</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="selectedDimension === dimKey ? 'bg-indigo-700 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                        {{ dimensionCounts[dimKey] || 0 }}
                    </span>
                </button>
            </div>

            <!-- DATA TABLE -->
            <BaseDataTable
                :value="filteredUnits"
                dataKey="id"
                showSerial
                showSearch
                :showAdvancedFilter="false"
                v-model:filters="tableFilters"
                :globalFilterFields="['name', 'code', 'symbol', 'dimension']"
                :paginator="true"
                :rows="25"
                :rowsPerPageOptions="[25, 50, 100]"
                heading="Units of Measurement Library"
                headingIcon="pi pi-list"
                class="rounded-2xl overflow-hidden shadow-xs border border-gray-200/80 dark:border-gray-800"
            >
                <!-- Empty State -->
                <template #empty>
                    <div class="text-center py-12 px-4 space-y-3">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400">
                            <i class="pi pi-compass text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                No measurement units found
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Try changing your search query or selected dimension tab.
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

                <!-- Symbol Column -->
                <Column field="symbol" header="Symbol" sortable style="min-width: 7rem">
                    <template #body="{ data }">
                        <span class="inline-block px-3 py-1 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-950/60 dark:to-purple-950/60 text-indigo-700 dark:text-indigo-300 font-mono font-black text-xs rounded-xl border border-indigo-200/80 dark:border-indigo-800/80 shadow-2xs">
                            {{ data.symbol }}
                        </span>
                    </template>
                </Column>

                <!-- Full Name & Code -->
                <Column field="name" header="Unit Name & Code" sortable style="min-width: 14rem">
                    <template #body="{ data }">
                        <div class="py-1">
                            <div class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                {{ data.name }}
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                    {{ data.code }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Physical Dimension Category -->
                <Column field="dimension" header="Dimension" sortable style="min-width: 11rem">
                    <template #body="{ data }">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border"
                                :class="getDimensionMeta(data.dimension).badgeClass"
                            >
                                <i :class="getDimensionMeta(data.dimension).icon" class="text-[10px]"></i>
                                <span class="capitalize">{{ data.dimension || 'Other' }}</span>
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- Example Sample Usage -->
                <Column header="Report Format Preview" style="min-width: 10rem">
                    <template #body="{ data }">
                        <span class="font-mono font-bold text-xs text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700">
                            {{ getDimensionMeta(data.dimension).sample }} {{ data.symbol }}
                        </span>
                    </template>
                </Column>

                <!-- Status Toggle Switch -->
                <Column field="is_active" header="Status" sortable style="width: 8rem; text-align: center">
                    <template #body="{ data }">
                        <div class="flex items-center justify-center gap-2">
                            <ToggleSwitch
                                :modelValue="Boolean(data.is_active)"
                                @update:modelValue="toggleStatus(data)"
                                class="scale-85 cursor-pointer"
                            />
                            <span class="text-[11px] font-bold" :class="data.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                                {{ data.is_active ? 'Active' : 'Off' }}
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- Actions -->
                <Column header="Actions" alignFrozen="right" freezeRight style="width: 8rem; text-align: right">
                    <template #body="{ data }">
                        <div class="flex items-center justify-end gap-1.5">
                            <button
                                type="button"
                                @click="openEditModal(data)"
                                class="p-1.5 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition-colors cursor-pointer"
                                title="Edit Unit"
                            >
                                <i class="pi pi-pencil text-xs"></i>
                            </button>
                            <BaseDeleteButton
                                :url="route('quality.config.units.destroy', data.id)"
                                class="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/60 rounded-lg transition-colors cursor-pointer"
                            />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>

        <!-- QUICK EDIT MODAL DIALOG -->
        <Dialog
            v-model:visible="showEditModal"
            modal
            :header="`Edit: ${editingUnit?.name || 'QC Unit'}`"
            :style="{ width: '560px' }"
            class="rounded-3xl overflow-hidden"
        >
            <div v-if="editingUnit" class="py-2">
                <UnitForm
                    :unit="editingUnit"
                    :dimensions="dimensions"
                    :isEditing="true"
                    :isModal="true"
                    @saved="handleUnitSaved"
                    @cancel="showEditModal = false"
                />
            </div>
        </Dialog>
    </AppLayout>
</template>
