<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import SaasErpDashboard from '@/Components/Dashboard/SaasErpDashboard.vue';
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="px-4 py-6 md:px-6 lg:px-8">
            <SaasErpDashboard />
        </div>
    </AppLayout>
</template>

