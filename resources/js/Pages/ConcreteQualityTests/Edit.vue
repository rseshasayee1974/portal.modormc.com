<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import ConcreteQualityTestForm from './components/ConcreteQualityTestForm.vue';

defineProps<{
    test: any;
    plants: any[];
    grades: any[];
    patrons: any[];
}>();
</script>

<template>
    <AppLayout :title="`Edit Quality Test - ${test.test_number || test.test_code}`">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head :title="`Edit Quality Test - ${test.test_number || test.test_code}`" />

        <div class="min-h-screen bg-[#f4f6f9] py-6 px-4 sm:px-6 lg:px-8">
            <ConcreteQualityTestForm
                :initial-data="test"
                :plants="plants"
                :grades="grades"
                :patrons="patrons"
                :is-edit="true"
            />
        </div>
    </AppLayout>
</template>
