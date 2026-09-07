<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';

const props = defineProps<{
    samples: any;
    materials: any[];
    suppliers: any[];
    customers: any[];
    inwards: any[];
    batches: any[];
    dispatches: any[];
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
    <AppLayout title="QC Samples Management">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Samples" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Quality Control Samples
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Log raw material inward receipts, production batch samples, and field delivery samples.
                    </p>
                </div>
                <div>
                    <Link :href="route('quality.samples.create')">
                        <BaseButton variant="primary">
                            <i class="pi pi-plus text-xs mr-2"></i> Log New Sample
                        </BaseButton>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input
                        v-model="searchQuery"
                        @keydown.enter="handleSearch"
                        type="text"
                        placeholder="Search by sample no, material, location..."
                        class="w-full pl-8 pr-8 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 shadow-2xs"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <select
                        v-model="statusFilter"
                        @change="handleSearch"
                        class="w-full px-2.5 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                    >
                        <option value="All">All Statuses</option>
                        <option value="pending_test">Pending Test</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <!-- BaseDataTable -->
            <BaseCard class="!p-0 overflow-hidden">
                <BaseDataTable
                    :value="samples.data || []"
                    dataKey="id"
                    :paginator="true"
                    :rows="15"
                    :totalRecords="samples.total"
                >
                    <Column field="sample_no" header="Sample No" sortable>
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-indigo-600">{{ data.sample_no }}</span>
                        </template>
                    </Column>

                    <Column field="sample_date" header="Date" sortable>
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{ data.sample_date ? data.sample_date.substring(0, 10) : '-' }}</span>
                        </template>
                    </Column>

                    <Column field="material.title" header="Material">
                        <template #body="{ data }">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ data.material?.title }}</span>
                        </template>
                    </Column>

                    <Column header="Supplier / Source">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ data.supplier?.legal_name || data.source_location || 'Plant Inventory' }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Assigned Tests">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="t in data.tests" :key="t.id" class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded font-mono font-bold text-[10px]">
                                    {{ t.test_type?.code }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.status === 'completed' ? 'Active' : data.status === 'rejected' ? 'Inactive' : 'Due'"
                                :colorMap="{
                                    'Active': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Due': 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'Inactive': 'bg-red-50 text-red-700 ring-red-200'
                                }"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <Link :href="route('quality.samples.edit', data.id)">
                                    <BaseButton size="small" variant="secondary">
                                        Edit
                                    </BaseButton>
                                </Link>
                                <BaseDeleteButton :url="route('quality.samples.destroy', data.id)" />
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
