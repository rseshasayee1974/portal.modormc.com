<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import SaasErpDashboard from '@/Components/Dashboard/SaasErpDashboard.vue';

const props = defineProps({
    apiKey: { type: String, default: '' },
    plan: { type: String, default: 'free' },
    canManageBilling: { type: Boolean, default: false },
    entityId: { type: [Number, String], default: null },
    plantId: { type: [Number, String, null], default: null },
});
</script>

<template>
    <AppLayout title="SaaS Dashboard">
        <div class="px-4 py-6 md:px-6 lg:px-8">
            <SaasErpDashboard
                :api-key="props.apiKey"
                :plan="props.plan"
                :can-manage-billing="props.canManageBilling"
                :entity-id="props.entityId"
                :plant-id="props.plantId"
                show-api-panel
            />
        </div>
    </AppLayout>
</template>
