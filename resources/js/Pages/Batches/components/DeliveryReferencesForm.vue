<!--
Author: ragul-onemodo
Created: 2026-11-25 10:18:55 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

const props = defineProps<{ trips: any[]; order: any; sites: any[] }>();
const sitesByTrip = ref<Record<number, number | null>>({});
const savedSite = (trip: any) => trip.unload_site_id ?? props.order?.site?.id ?? null;
watch(() => [props.trips, props.order], () => {
    sitesByTrip.value = Object.fromEntries(props.trips.map(trip => [trip.id, savedSite(trip)]));
}, { immediate: true });
const changes = computed(() => props.trips
    .filter(trip => sitesByTrip.value[trip.id] !== savedSite(trip))
    .map(trip => ({ dispatch_id: trip.id, unload_site_id: sitesByTrip.value[trip.id] })));
defineExpose({ changes });
</script>

<template>
    <div class="flex flex-wrap items-end gap-3 border-b p-4">
        <BaseSelect v-for="trip in trips" :key="trip.id" v-model="sitesByTrip[trip.id]"
            :label="trips.length > 1 ? 'Unloading site · Dispatch ' + (trip.dispatch_no || trip.id) : 'Unloading site'"
            :options="sites" optionLabel="name" optionValue="id" filter />
    </div>
</template>
