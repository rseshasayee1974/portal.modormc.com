<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps<{ invoiceId?: number | string; batchId?: number | string }>();
const details = ref<any>(null);
const error = ref('');
let sequence = 0;
watch(() => [props.invoiceId, props.batchId], async () => {
    const current = ++sequence;
    details.value = null;
    error.value = '';
    if (!props.invoiceId && !props.batchId) return;
    try {
        const response = await axios.get(route('ewaybills.route-preview'), { params: { invoice_id: props.invoiceId, batch_id: props.batchId } });
        if (current === sequence) details.value = response.data;
    } catch (e: any) {
        if (current === sequence) error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Could not load delivery addresses.';
    }
}, { immediate: true });
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm space-y-2 dark:bg-slate-800 dark:border-slate-700">
        <p v-if="error" class="text-red-600">{{ error }}</p>
        <template v-else-if="details">
            <p><strong>From — Plant:</strong> {{ details.from_address }}</p>
            <p><strong>To — {{ details.destination_source }}:</strong> {{ details.to_address }}</p>
            <p class="text-xs text-slate-500">PIN {{ details.from_zipcode }} → {{ details.to_zipcode }}. E-way bill distance is calculated during generation.</p>
            <a :href="details.maps_url" target="_blank" rel="noopener noreferrer" class="text-teal-700 underline">View driving route and distance in Google Maps</a>
        </template>
        <p v-else>Loading delivery addresses…</p>
    </div>
</template>
