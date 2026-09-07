<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';

const props = defineProps<{
    schedules: any;
    testTypes: any[];
    materials: any[];
    frequencyTypes: string[];
}>();
</script>

<template>
    <AppLayout title="Test Frequency & Schedule Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Test Schedule & Frequency" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Test Frequency & Schedule Configuration
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Specify how frequently each QC test should be performed for raw materials and concrete production.
                    </p>
                </div>
                <div>
                    <Link :href="route('quality.config.test-schedules.create')">
                        <BaseButton variant="primary">
                            <i class="pi pi-plus text-xs mr-2"></i> Add Test Schedule
                        </BaseButton>
                    </Link>
                </div>
            </div>

            <!-- BaseDataTable -->
            <BaseCard class="!p-0 overflow-hidden">
                <BaseDataTable
                    :value="schedules.data || []"
                    dataKey="id"
                    :paginator="true"
                    :rows="15"
                    :totalRecords="schedules.total"
                >
                    <Column field="material.title" header="Material" sortable>
                        <template #body="{ data }">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ data.material?.title }}</span>
                        </template>
                    </Column>

                    <Column field="test_type.name" header="Test Type" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold text-indigo-600">{{ data.test_type?.name }} ({{ data.test_type?.code }})</span>
                        </template>
                    </Column>

                    <Column field="frequency_type" header="Frequency">
                        <template #body="{ data }">
                            <Badge
                                :value="data.frequency_type"
                                :colorMap="{ [data.frequency_type]: 'bg-indigo-50 text-indigo-700 ring-indigo-200' }"
                            />
                        </template>
                    </Column>

                    <Column field="frequency_value" header="Interval Value">
                        <template #body="{ data }">
                            <span class="font-mono font-bold">{{ data.frequency_value || '-' }}</span>
                        </template>
                    </Column>

                    <Column field="is_active" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.is_active ? 'Active' : 'Inactive'"
                                :colorMap="{
                                    'Active': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Inactive': 'bg-red-50 text-red-700 ring-red-200'
                                }"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <Link :href="route('quality.config.test-schedules.edit', data.id)">
                                    <BaseButton size="small" variant="secondary">
                                        Edit
                                    </BaseButton>
                                </Link>
                                <BaseDeleteButton :url="route('quality.config.test-schedules.destroy', data.id)" />
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
