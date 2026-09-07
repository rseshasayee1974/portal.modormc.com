<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import SampleForm from './SampleForm.vue';

defineProps<{
    materials: any[];
    suppliers: any[];
    customers: any[];
    inwards: any[];
    batches: any[];
    dispatches: any[];
    testTypes?: any[];
}>();
</script>

<template>
    <AppLayout title="Log QC Sample">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Log QC Sample" />

        <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-800">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('quality.samples.index')"
                            class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                        >
                            <i class="pi pi-arrow-left text-[10px]"></i> QC Samples
                        </Link>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                        Log Quality Sample
                    </h1>
                </div>
            </div>

            <!-- Form -->
            <SampleForm
                :materials="materials"
                :suppliers="suppliers"
                :customers="customers"
                :inwards="inwards"
                :batches="batches"
                :dispatches="dispatches"
                :testTypes="testTypes"
                :isEditing="false"
            />
        </div>
    </AppLayout>
</template>
