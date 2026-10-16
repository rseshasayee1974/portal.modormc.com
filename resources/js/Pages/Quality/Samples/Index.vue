<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Toast from 'primevue/toast';
import SampleForm from './SampleForm.vue';

defineProps<{
    supportedTestAges?: number[];
    additionalSamples?: any[];
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    testTypes?: any[];
    salesOrders?: any[];
    batches?: any[];
    usedBatchIds?: number[];
    usedDispatchIds?: number[];
    personnels?: any[];
}>();

const formKey = ref(0);
const onSampleSaved = () => { formKey.value++; };
</script>

<template>
    <AppLayout title="Concrete Samples & Cube Casting">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Concrete Samples" />
        <Toast />

        <div class="samples-workspace w-full min-w-0 mx-auto px-3 py-3 sm:px-5">
            <SampleForm :key="formKey" :supportedTestAges="supportedTestAges" embedded :concreteGrades="concreteGrades" :materials="materials"
                :customers="customers" :dispatches="dispatches" :batches="batches" :salesOrders="salesOrders"
                :usedBatchIds="usedBatchIds" :usedDispatchIds="usedDispatchIds" :testTypes="testTypes"
                :personnels="personnels" :additionalSamples="additionalSamples" @saved="onSampleSaved" />
        </div>
    </AppLayout>
</template>
