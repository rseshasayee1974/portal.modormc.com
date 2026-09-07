<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import TestTypeForm from './TestTypeForm.vue';

defineProps<{
    materials: any[];
    categories: string[];
}>();
</script>

<template>
    <AppLayout title="Create QC Test Type">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Create QC Test Type" />

        <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link
                    :href="route('quality.config.test-types.index')"
                    class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors"
                    title="Back to Test Types"
                >
                    <i class="pi pi-arrow-left text-xs font-bold"></i>
                </Link>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                        Create New QC Test Type
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Define testing methodology, IS standard code, and interactive UI layout structure.
                    </p>
                </div>
            </div>

            <!-- Form -->
            <TestTypeForm :categories="categories" :isEditing="false" />
        </div>
    </AppLayout>
</template>
