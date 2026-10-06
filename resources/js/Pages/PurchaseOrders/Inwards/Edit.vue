<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Link } from '@inertiajs/vue3';
import InwardEditForm from './components/InwardEditForm.vue';

defineProps<{ inward: any; vehicles?: any[]; units?: any[]; accounts?: any[]; canGenerateBill?: boolean }>();
</script>

<template>
    <AppLayout title="Edit Stock Inward">
        <template #header><ModuleSubTopNav /></template>
        <div class="py-6 px-4 max-w-7xl mx-auto space-y-4">
            <Link :href="route('inwards.index')" class="text-sm text-indigo-600 hover:underline">Back to registry</Link>
            <InwardEditForm :inward="inward" :vehicles="vehicles" :units="units" :accounts="accounts" :can-generate-bill="canGenerateBill" />
        </div>
    </AppLayout>
</template>
