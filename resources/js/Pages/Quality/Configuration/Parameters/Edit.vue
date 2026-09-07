<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import ParameterForm from './ParameterForm.vue';

defineProps<{
    parameter: any;
    testTypes: any[];
    units: any[];
    ruleTypes: string[];
    ruleTypeLabels: Record<string, string>;
}>();
</script>

<template>
    <AppLayout :title="`Edit ${parameter.name}`">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head :title="`Edit ${parameter.name}`" />

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link
                    :href="route('quality.config.test-parameters.index', { test_type_id: parameter.test_type_id })"
                    class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors"
                    title="Back to Parameters"
                >
                    <i class="pi pi-arrow-left text-xs font-bold"></i>
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                            Edit: {{ parameter.name }}
                        </h1>
                        <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                            {{ parameter.code }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Update measurement properties, formula math, or pass/fail thresholds.
                    </p>
                </div>
            </div>

            <!-- Form -->
            <ParameterForm
                :parameter="parameter"
                :testTypes="testTypes"
                :units="units"
                :ruleTypes="ruleTypes"
                :ruleTypeLabels="ruleTypeLabels"
                :isEditing="true"
            />
        </div>
    </AppLayout>
</template>
