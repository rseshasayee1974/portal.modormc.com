<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import ParameterForm from './ParameterForm.vue';

defineProps<{
    testTypes: any[];
    selectedTestTypeId?: number;
    units: any[];
    ruleTypes: string[];
    ruleTypeLabels: Record<string, string>;
}>();
</script>

<template>
    <AppLayout title="Add Quality Parameter">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Add QC Parameter" />

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link
                    :href="route('quality.config.test-parameters.index', { test_type_id: selectedTestTypeId })"
                    class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors"
                    title="Back to Parameters"
                >
                    <i class="pi pi-arrow-left text-xs font-bold"></i>
                </Link>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                        Add New QC Parameter
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Configure formula engine expressions and acceptance criteria for test evaluation.
                    </p>
                </div>
            </div>

            <!-- Form -->
            <ParameterForm
                :testTypes="testTypes"
                :selectedTestTypeId="selectedTestTypeId"
                :units="units"
                :ruleTypes="ruleTypes"
                :ruleTypeLabels="ruleTypeLabels"
                :isEditing="false"
            />
        </div>
    </AppLayout>
</template>
