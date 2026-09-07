<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import TestTypeForm from './TestTypeForm.vue';

defineProps<{
    testType: any;
    materials: any[];
    categories: string[];
}>();
</script>

<template>
    <AppLayout :title="`Edit ${testType.name}`">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head :title="`Edit ${testType.name}`" />

        <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('quality.config.test-types.index')"
                        class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors"
                        title="Back to Test Types"
                    >
                        <i class="pi pi-arrow-left text-xs font-bold"></i>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                                Edit: {{ testType.name }}
                            </h1>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                {{ testType.code }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Update specifications, IS reference, or custom fractions grid.
                        </p>
                    </div>
                </div>

                <!-- Link to Parameters -->
                <Link
                    :href="route('quality.config.test-parameters.index', { test_type_id: testType.id })"
                    class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold border border-indigo-200 dark:border-indigo-800 flex items-center gap-1.5 hover:bg-indigo-100 transition-colors"
                >
                    <i class="pi pi-sliders-v text-xs"></i>
                    <span>Manage {{ testType.parameters?.length || 0 }} Parameters</span>
                </Link>
            </div>

            <!-- Form -->
            <TestTypeForm :testType="testType" :categories="categories" :isEditing="true" />
        </div>
    </AppLayout>
</template>
