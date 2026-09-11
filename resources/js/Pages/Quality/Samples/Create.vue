<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import SampleForm from './SampleForm.vue';

defineProps<{
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    batches?: any[];
    testTypes?: any[];
    personnels?: any[];
}>();
</script>

<template>
    <AppLayout title="Log Concrete Cube Sample">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Log Concrete Sample" />

        <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <SampleForm
                :concreteGrades="concreteGrades"
                :materials="materials"
                :customers="customers"
                :dispatches="dispatches"
                :batches="batches"
                :testTypes="testTypes"
                :personnels="personnels"
                :isEditing="false"
            />
        </div>
    </AppLayout>
</template>
