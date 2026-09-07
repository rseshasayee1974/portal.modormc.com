<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    title: string;
    tests: any;
    filters: any;
}>();

const toast = useToast();
const selectedTest = ref<any>(null);

const retestForm = useForm({
    retest_reason: '',
});

const openRetestPanel = (test: any) => {
    selectedTest.value = test;
    retestForm.reset();
};

const closeRetestPanel = () => {
    selectedTest.value = null;
    retestForm.reset();
};

const submitRetest = () => {
    if (!selectedTest.value?.id) return;
    retestForm.post(route('quality.tests.retest', selectedTest.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedTest.value = null;
            toast.add({ severity: 'info', summary: 'Retest Flagged', detail: 'Test flagged for retest successfully', life: 2000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Failed QC Tests & Retests">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Failed QC Tests" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-red-600 dark:text-red-400 tracking-tight flex items-center gap-2">
                        <i class="pi pi-exclamation-triangle"></i>
                        Failed Quality Tests & Retest Control
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Out-of-specification test failures, non-conformance logs, and retest workflow management.
                    </p>
                </div>
            </div>

            <!-- Inline Flag Retest Panel (Appears when a test is selected) -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <BaseCard v-if="selectedTest" class="border-2 border-amber-400 dark:border-amber-600 bg-amber-50/50 dark:bg-amber-950/30">
                    <form @submit.prevent="submitRetest" class="space-y-4">
                        <div class="flex items-center justify-between border-b border-amber-200 dark:border-amber-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center font-bold">
                                    <i class="pi pi-replay text-sm"></i>
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                        Flag Test for Retest: <span class="font-mono text-amber-700 dark:text-amber-300">{{ selectedTest.test_no }}</span>
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Material: {{ selectedTest.sample?.material?.title || 'N/A' }} &bull; Sample No: {{ selectedTest.sample?.sample_no || 'N/A' }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="closeRetestPanel"
                                class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>

                        <BaseInput
                            v-model="retestForm.retest_reason"
                            label="Reason for Retest / Quality Action"
                            required
                            placeholder="Explain failure cause and required retest protocol..."
                            :error="retestForm.errors.retest_reason"
                        />

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <BaseButton type="button" variant="secondary" size="small" @click="closeRetestPanel">
                                Cancel
                            </BaseButton>
                            <BaseButton
                                type="submit"
                                variant="primary"
                                size="small"
                                :disabled="retestForm.processing"
                                class="!bg-amber-600 hover:!bg-amber-700 !text-white"
                            >
                                <i class="pi pi-check text-xs mr-1.5"></i> Confirm Retest Protocol
                            </BaseButton>
                        </div>
                    </form>
                </BaseCard>
            </transition>

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
                            <span class="font-mono font-bold text-red-600">{{ data.test_no }}</span>
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

                    <Column header="Supplier">
                        <template #body="{ data }">
                            <span class="text-gray-500 text-xs">{{ data.sample?.supplier?.legal_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <Column field="overall_status" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.overall_status === 'retest' ? 'Due' : 'Inactive'"
                                :colorMap="{
                                    'Due': 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'Inactive': 'bg-red-50 text-red-700 ring-red-200'
                                }"
                            />
                        </template>
                    </Column>

                    <Column field="retest_reason" header="Retest Notes">
                        <template #body="{ data }">
                            <span class="text-gray-500 italic max-w-xs truncate text-xs">{{ data.retest_reason || 'N/A' }}</span>
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <BaseButton size="small" variant="secondary" @click="openRetestPanel(data)">
                                    Flag Retest
                                </BaseButton>
                                <Link :href="`/quality/tests/${data.id}/execute`">
                                    <BaseButton size="small" variant="primary">
                                        Re-Execute
                                    </BaseButton>
                                </Link>
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
