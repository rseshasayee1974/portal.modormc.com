<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import MixDesignCreateForm from './Partials/MixDesignCreateForm.vue';
import MixDesignTable from './Partials/MixDesignTable.vue';

const props = defineProps<{
    mixDesigns: any[];
    partners: any[];
    products: any[];
    units: any[];
    defaultUomId?: number | null;
    designTypes: any[];
}>();
</script>

<template>
    <AppLayout title="Mix Design RMC">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class=" min-h-screen">
            <div class="max-w-full mx-auto px-4">

                <!-- Create Panel -->
                <MixDesignCreateForm
                    :products="mixDesigns ? products : products"
                    :units="units"
                    :partners="partners"
                    :defaultUomId="defaultUomId"
                    :designTypes="designTypes"
                />

                <!-- Directory Table -->
                <MixDesignTable
                    :mixDesigns="mixDesigns"
                    :products="products"
                    :units="units"
                    :partners="partners"
                    :defaultUomId="defaultUomId"
                    :designTypes="designTypes"
                />

            </div>
        </div>
    </AppLayout>
</template>
