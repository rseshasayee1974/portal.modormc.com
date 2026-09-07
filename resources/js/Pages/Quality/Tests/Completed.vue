<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    title: string;
    tests: any;
    filters: any;
}>();

const toast = useToast();

const approveTest = (test: any) => {
    router.post(route('quality.tests.approve', test.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Approved', detail: 'Test result approved', life: 1500 });
        }
    });
};
</script>

<template>
    <AppLayout title="Completed Quality Tests">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Completed QC Tests" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Completed Quality Tests
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Completed laboratory test executions, technical evaluations, and managerial approvals.
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
                                {{ data.test_type?.name }}
                            </span>
                        </template>
                    </Column>

                    <Column field="overall_status" header="Result Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.overall_status === 'pass' ? 'Active' : 'Inactive'"
                                :colorMap="{
                                    'Active': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Inactive': 'bg-red-50 text-red-700 ring-red-200'
                                }"
                            />
                        </template>
                    </Column>

                    <Column header="Approval">
                        <template #body="{ data }">
                            <span v-if="data.approval_status === 'approved'" class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-bold text-[10px] rounded-full">
                                Approved
                            </span>
                            <BaseButton v-else size="small" variant="primary" @click="approveTest(data)">
                                Approve
                            </BaseButton>
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <Link :href="`/quality/tests/${data.id}/execute`">
                                <BaseButton size="small" variant="secondary">
                                    View Details
                                </BaseButton>
                            </Link>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
