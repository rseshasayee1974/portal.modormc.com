<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import ConcreteQualityTestForm from './components/ConcreteQualityTestForm.vue';

defineProps<{
    plants: any[];
    grades: any[];
    patrons: any[];
    activePlantId: number | null;
    defaultTestNumber: string;
    today: string;
}>();
</script>

<template>
    <AppLayout title="Create Concrete Quality Test">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Create Concrete Quality Test" />

        <div class="min-h-screen bg-[#f4f6f9] py-6 px-4 sm:px-6 lg:px-8">
            <ConcreteQualityTestForm
                :plants="plants"
                :grades="grades"
                :patrons="patrons"
                :active-plant-id="activePlantId"
                :is-edit="false"
            />
        </div>
    </AppLayout>
</template>
