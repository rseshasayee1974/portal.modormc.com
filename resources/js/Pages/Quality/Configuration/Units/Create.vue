<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import UnitForm from './UnitForm.vue';

defineProps<{
    dimensions: string[];
}>();
</script>

<template>
    <AppLayout title="Add Measurement Unit">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Add Measurement Unit" />

        <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link
                    :href="route('quality.config.units.index')"
                    class="w-9 h-9 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-colors"
                    title="Back to Units"
                >
                    <i class="pi pi-arrow-left text-xs font-bold"></i>
                </Link>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                        Add New QC Unit
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Define measurement dimensions and symbols for QC variables and acceptance criteria.
                    </p>
                </div>
            </div>

            <UnitForm :dimensions="dimensions" :isEditing="false" />
        </div>
    </AppLayout>
</template>
