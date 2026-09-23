<script setup>
import RegisterReport from './RegisterReport.vue';
defineProps({ reportData: { type: Object, required: true }, currentPage: { type: Number, default: 1 } });
defineEmits(['page-change']);
</script>

<template>
    <RegisterReport :report-data="reportData" :current-page="currentPage" kind="purchase" @page-change="$emit('page-change', $event)" />
</template>
