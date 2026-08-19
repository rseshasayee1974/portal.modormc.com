<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage<any>();

const plantLogo = computed(() => page.props.active_plant?.plant_logo);
const plantName = computed(() => page.props.active_plant?.plant_name);
const entityLogo = computed(() => {
    const logo = page.props.active_entity?.entity_logo;
    return logo ? (logo.startsWith('/storage/') ? logo : `/storage/${logo}`) : null;
});
const entityName = computed(() => page.props.active_entity?.entity_name);

const logoSrc = computed(() => {
    return plantLogo.value || entityLogo.value || '/assets/modormc_logo_v1.png';
});

const logoAlt = computed(() => {
    return plantName.value || entityName.value || 'ModoRMC';
});
</script>

<template>
    <img :src="logoSrc" :alt="logoAlt" class="object-contain" />
</template>
