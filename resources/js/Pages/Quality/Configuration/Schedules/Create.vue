<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import ScheduleForm from './ScheduleForm.vue';

defineProps<{
    testTypes: any[];
    materials: any[];
    frequencyTypes: string[];
}>();
</script>

<template>
    <AppLayout title="Create QC Test Schedule">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Create QC Test Schedule" />

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-800">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('quality.config.test-schedules.index')"
                            class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                        >
                            <i class="pi pi-arrow-left text-[10px]"></i> QC Schedules Master
                        </Link>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                        Create QC Test Schedule
                    </h1>
                </div>
            </div>

            <!-- Form -->
            <ScheduleForm
                :testTypes="testTypes"
                :materials="materials"
                :frequencyTypes="frequencyTypes"
                :isEditing="false"
            />
        </div>
    </AppLayout>
</template>
