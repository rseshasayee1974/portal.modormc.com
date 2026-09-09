<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import UnitForm from './UnitForm.vue';

defineProps<{
    unit: any;
    dimensions: string[];
}>();
</script>

<template>
    <AppLayout :title="`Edit ${unit.name}`">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head :title="`Edit ${unit.name}`" />

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex items-center gap-3.5">
                <Link
                    :href="route('quality.config.units.index')"
                    class="w-10 h-10 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-all shadow-2xs hover:shadow-sm"
                    title="Back to Units"
                >
                    <i class="pi pi-arrow-left text-xs font-bold"></i>
                </Link>
                <div>
                    <div class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-0.5">
                        <i class="pi pi-compass text-[10px]"></i> Quality Configuration
                    </div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                            Edit: {{ unit.name }}
                        </h1>
                        <span class="font-mono text-xs font-black px-2.5 py-1 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            {{ unit.symbol }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Update display symbol, system code, or physical dimension classification.
                    </p>
                </div>
            </div>

            <UnitForm :unit="unit" :dimensions="dimensions" :isEditing="true" />
        </div>
    </AppLayout>
</template>
