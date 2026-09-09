<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Toast from 'primevue/toast';

const props = defineProps<{
    samples: any;
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    testTypes?: any[];
    filters: any;
}>();

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'All');

const handleSearch = () => {
    router.get(route('quality.samples.index'), {
        search: searchQuery.value ? searchQuery.value.trim() : undefined,
        status: statusFilter.value !== 'All' ? statusFilter.value : undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <AppLayout title="Concrete Samples & Cube Casting">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Concrete Samples" />
        <Toast />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Hero Header -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl shadow-indigo-950/20 border border-indigo-800/40">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5 relative z-10">
                    <div class="space-y-1.5">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-400/20 text-xs font-bold uppercase tracking-wider">
                            <i class="pi pi-box text-[11px]"></i>
                            <span>Fresh Concrete Sampling & Cube Casting</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            Concrete Grade Batch Samples
                        </h1>
                        <p class="text-xs sm:text-sm text-indigo-200/80 max-w-2xl">
                            Log concrete cubes cast from transit mixers and batching plants with fresh concrete slump, temperatures, and automated 7-Day & 28-Day CTM testing schedules.
                        </p>
                    </div>

                    <div>
                        <Link :href="route('quality.samples.create')">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs font-extrabold bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white shadow-lg shadow-emerald-500/25 transition-all cursor-pointer"
                            >
                                <i class="pi pi-plus text-xs"></i>
                                <span>+ Log Concrete Cube Sample</span>
                            </button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="relative flex-1 w-full max-w-md">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input
                        v-model="searchQuery"
                        @keydown.enter="handleSearch"
                        type="text"
                        placeholder="Search by sample no, grade, truck, customer, site..."
                        class="w-full pl-8 pr-4 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500/20 shadow-2xs"
                    />
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select
                        v-model="statusFilter"
                        @change="handleSearch"
                        class="px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500/20 cursor-pointer shadow-2xs"
                    >
                        <option value="All">All Statuses</option>
                        <option value="pending_test">Pending Testing</option>
                        <option value="in_progress">Testing In Progress</option>
                        <option value="completed">Completed</option>
                    </select>

                    <button
                        type="button"
                        @click="handleSearch"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                    >
                        Filter
                    </button>
                </div>
            </div>

            <!-- Samples Data Table -->
            <BaseDataTable
                :value="samples.data || []"
                dataKey="id"
                showSerial
                :paginator="true"
                :rows="20"
                :totalRecords="samples.total"
                heading="Concrete Samples Register"
                headingIcon="pi pi-list"
                class="rounded-3xl overflow-hidden shadow-xs border border-gray-200/80 dark:border-gray-800"
            >
                <!-- Sample No & Date -->
                <Column field="sample_no" header="Sample ID & Cast Date" sortable style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="py-1">
                            <div class="font-mono font-black text-xs text-indigo-600 dark:text-indigo-400">
                                {{ data.sample_no }}
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-0.5">
                                <i class="pi pi-calendar text-[10px]"></i>
                                <span>Cast: {{ data.sample_date ? data.sample_date.substring(0, 10) : '—' }}</span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Concrete Grade & Client -->
                <Column header="Concrete Grade & Transit Mixer" style="min-width: 15rem">
                    <template #body="{ data }">
                        <div class="py-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/80">
                                    {{ data.concrete_grade?.name || data.material?.title || 'Concrete Mix' }}
                                </span>
                                <span v-if="data.truck_no" class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    {{ data.truck_no }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-300 truncate">
                                {{ data.customer?.legal_name || 'Client: Standard Dispatch' }}
                                <span v-if="data.site_name" class="text-gray-400"> &bull; {{ data.site_name }}</span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Fresh Concrete Tests (Slump & Temp) -->
                <Column header="Slump & Temp" style="min-width: 9rem">
                    <template #body="{ data }">
                        <div class="py-1 space-y-0.5 text-xs">
                            <div class="font-semibold text-gray-800 dark:text-gray-200">
                                Slump: <strong class="font-mono text-emerald-600 dark:text-emerald-400">{{ data.slump_mm ? data.slump_mm + ' mm' : '—' }}</strong>
                            </div>
                            <div class="text-[11px] text-gray-500">
                                Temp: <span class="font-mono">{{ data.concrete_temp_c ? data.concrete_temp_c + '°C' : '—' }}</span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Specimens & Curing -->
                <Column header="Specimens / Tank" style="min-width: 10rem">
                    <template #body="{ data }">
                        <div class="py-1 text-xs space-y-0.5">
                            <div class="font-bold text-gray-800 dark:text-gray-200">
                                {{ data.specimen_count || 6 }} Cubes ({{ data.specimen_size || '150mm' }})
                            </div>
                            <div class="text-[11px] text-gray-500 truncate">
                                {{ data.curing_tank_id || 'Water Curing' }}
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Scheduled Tests (7D & 28D) -->
                <Column header="Testing Schedule (CTM)" style="min-width: 14rem">
                    <template #body="{ data }">
                        <div class="flex flex-wrap items-center gap-1.5 py-1">
                            <Link
                                v-for="t in data.tests"
                                :key="t.id"
                                :href="route('quality.tests.execute', t.id)"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-bold transition-all shadow-2xs cursor-pointer border"
                                :class="t.overall_status === 'pass'
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : t.overall_status === 'fail'
                                    ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300'
                                    : 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 hover:bg-amber-100'"
                                :title="`Click to execute test (${t.test_no})`"
                            >
                                <span class="font-mono font-black">{{ t.age_days ? t.age_days + 'D' : t.test_type?.code }}</span>
                                <span class="text-[9px] opacity-80">({{ t.scheduled_date ? t.scheduled_date.substring(5, 10) : 'Due' }})</span>
                                <i :class="t.overall_status === 'pass' ? 'pi pi-check' : t.overall_status === 'fail' ? 'pi pi-times' : 'pi pi-arrow-right'" class="text-[9px]"></i>
                            </Link>

                            <span v-if="!data.tests || data.tests.length === 0" class="text-xs text-gray-400">
                                No test scheduled
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- Actions -->
                <Column header="Actions" alignFrozen="right" freezeRight style="width: 7rem; text-align: right">
                    <template #body="{ data }">
                        <div class="flex items-center justify-end gap-1.5">
                            <Link
                                :href="route('quality.samples.edit', data.id)"
                                class="p-1.5 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 rounded-lg transition-colors cursor-pointer"
                                title="Edit Sample"
                            >
                                <i class="pi pi-pencil text-xs"></i>
                            </Link>
                            <BaseDeleteButton
                                :url="route('quality.samples.destroy', data.id)"
                                class="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/60 rounded-lg transition-colors cursor-pointer"
                            />
                        </div>
                    </template>
                </Column>
            </BaseDataTable>
        </div>
    </AppLayout>
</template>
