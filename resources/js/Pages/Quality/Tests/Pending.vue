<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Badge from '@/Components/Mm/Badge.vue';

const props = defineProps<{
    title: string;
    tests: any;
    filters: any;
}>();
</script>

<template>
    <AppLayout title="Pending Quality Tests">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Pending QC Tests" />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Pending Quality Tests
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Active laboratory tests awaiting technician measurements and formula evaluation.
                    </p>
                </div>
            </div>

            <!-- BaseDataTable -->
            <BaseCard class="!p-0 overflow-hidden">
                <BaseDataTable
                    :value="tests.data || []"
                    dataKey="id"
                    :paginator="true"
                    :rows="15"
                    :totalRecords="tests.total"
                >
                    <Column field="test_no" header="Test No" sortable>
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-indigo-600">{{ data.test_no }}</span>
                        </template>
                    </Column>

                    <Column field="sample.sample_no" header="Sample No">
                        <template #body="{ data }">
                            <span class="font-mono font-semibold">{{ data.sample?.sample_no }}</span>
                        </template>
                    </Column>

                    <Column field="sample.material.title" header="Material">
                        <template #body="{ data }">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ data.sample?.material?.title }}</span>
                        </template>
                    </Column>

                    <Column header="Test Procedure">
                        <template #body="{ data }">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 font-bold text-[10px] rounded-lg">
                                {{ data.test_type?.name }} ({{ data.test_type?.code }})
                            </span>
                        </template>
                    </Column>

                    <Column header="Sample Date">
                        <template #body="{ data }">
                            <span class="font-mono text-gray-500">{{ data.sample?.sample_date ? data.sample.sample_date.substring(0, 10) : '' }}</span>
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <Link :href="`/quality/tests/${data.id}/execute`">
                                <BaseButton size="small" variant="primary">
                                    Execute Test
                                </BaseButton>
                            </Link>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
