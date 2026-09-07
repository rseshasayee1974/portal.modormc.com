<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';

const props = defineProps<{
    stats: {
        total_tests: number;
        passed: number;
        failed: number;
        retest: number;
        pending: number;
        pass_rate: number;
    };
    recentTests: any[];
    recentFailures: any[];
}>();
</script>

<template>
    <AppLayout title="Quality Control Dashboard">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Dashboard" />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Quality Control & Technical Compliance
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Real-time technical pass rates, material testing trends, and quality control metrics.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        href="/quality/samples"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl shadow-lg transition-colors flex items-center gap-2"
                    >
                        <i class="pi pi-plus text-xs"></i>
                        Log Sample
                    </Link>
                    <Link
                        href="/quality/configuration/test-types"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-800 hover:bg-gray-300 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-semibold text-xs rounded-xl transition-colors flex items-center gap-2"
                    >
                        <i class="pi pi-cog text-xs"></i>
                        QC Configuration
                    </Link>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <Card class="dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Tests</span>
                                <div class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-1">{{ stats.total_tests }}</div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center">
                                <i class="pi pi-beaker text-lg"></i>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card class="dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Passed Tests</span>
                                <div class="text-2xl font-black text-emerald-600 mt-1">{{ stats.passed }}</div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center">
                                <i class="pi pi-check-circle text-lg"></i>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card class="dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-red-500">Failed Tests</span>
                                <div class="text-2xl font-black text-red-500 mt-1">{{ stats.failed }}</div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 text-red-500 flex items-center justify-center">
                                <i class="pi pi-times-circle text-lg"></i>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card class="dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-amber-500">Retest Flagged</span>
                                <div class="text-2xl font-black text-amber-500 mt-1">{{ stats.retest }}</div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 text-amber-500 flex items-center justify-center">
                                <i class="pi pi-refresh text-lg"></i>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card class="dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Pass Rate</span>
                                <div class="text-2xl font-black text-indigo-600 mt-1">{{ stats.pass_rate }}%</div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center">
                                <i class="pi pi-chart-line text-lg"></i>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Recent Activity Grids -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Completed Tests -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <i class="pi pi-list text-indigo-500"></i>
                            Recent Quality Tests
                        </h2>
                        <Link href="/quality/tests/completed" class="text-xs font-semibold text-indigo-600 hover:underline">View All</Link>
                    </div>

                    <div v-if="recentTests.length === 0" class="py-8 text-center text-sm text-gray-400">
                        No tests recorded yet.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="test in recentTests" :key="test.id" class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-xs text-gray-900 dark:text-gray-100">
                                    {{ test.test_no }} &bull; {{ test.test_type?.name }}
                                </div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    Sample: {{ test.sample?.sample_no }} &bull; Material: {{ test.sample?.material?.title }}
                                </div>
                            </div>
                            <div>
                                <span
                                    :class="[
                                        'px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full tracking-wider',
                                        test.overall_status === 'pass' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                        test.overall_status === 'fail' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' :
                                        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                    ]"
                                >
                                    {{ test.overall_status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Quality Failures / Retests -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-red-600 flex items-center gap-2">
                            <i class="pi pi-exclamation-triangle"></i>
                            Quality Failure Alerts
                        </h2>
                        <Link href="/quality/tests/failed" class="text-xs font-semibold text-red-600 hover:underline">View All Failures</Link>
                    </div>

                    <div v-if="recentFailures.length === 0" class="py-8 text-center text-sm text-emerald-600 font-medium flex flex-col items-center gap-2">
                        <i class="pi pi-check-circle text-2xl"></i>
                        No active test failures detected across plants.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="fail in recentFailures" :key="fail.id" class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-xs text-red-600">
                                    {{ fail.test_no }} &bull; {{ fail.test_type?.name }}
                                </div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    Material: {{ fail.sample?.material?.title }} &bull; Supplier: {{ fail.sample?.supplier?.legal_name || 'N/A' }}
                                </div>
                            </div>
                            <Link
                                :href="`/quality/tests/${fail.id}/execute`"
                                class="px-3 py-1 bg-red-50 text-red-600 hover:bg-red-100 font-bold text-[11px] rounded-lg transition-colors"
                            >
                                Inspect / Retest
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
