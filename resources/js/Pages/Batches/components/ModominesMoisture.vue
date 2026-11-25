<!--
Author: ragul-onemodo
Created: 2026-11-25 13:17:33 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps<{ batchId: number; readings: Array<{
    product_id: number; design_moisture_percentage: number | null; consumption_moisture_percentage: number | null;
}> }>();
const emit = defineEmits<{ ready: [value: boolean] }>();
const rows = ref<Array<{ id: number; name: string; volume: number; target: number; actual: number }>>([]);
const message = ref('');

watch(() => [props.batchId, props.readings], (_, __, cleanup) => {
    rows.value = [];
    emit('ready', false);
    message.value = 'Calculating targets…';
    const controller = new AbortController();
    const timer = setTimeout(async () => {
        try {
            const readings = props.readings.map(({ product_id, design_moisture_percentage, consumption_moisture_percentage }) =>
                ({ product_id, design_moisture_percentage, consumption_moisture_percentage }));
            const { data } = await axios.post(route('batches.moisture-preview', props.batchId),
                { moisture_contents: readings }, { signal: controller.signal });
            if (controller.signal.aborted) return;
            rows.value = data.targets;
            message.value = '';
            emit('ready', rows.value.length > 0);
        } catch (error: any) {
            if (controller.signal.aborted) return;
            message.value = Object.values(error.response?.data?.errors || {}).flat().join(' ') || 'Unable to calculate batch targets.';
        }
    }, 120);
    cleanup(() => { clearTimeout(timer); controller.abort(); });
}, { deep: true, immediate: true });
</script>

<template>
    <section class="mt-4 text-sm" aria-label="Modomines corrected batch targets">
        <p v-if="message" role="status">{{ message }}</p>
        <div v-else class="max-h-72 overflow-auto">
            <table class="w-full text-right tabular-nums">
                <caption class="pb-2 text-left font-semibold">Moisture correction preview</caption>
                <thead class="sticky top-0 bg-slate-50"><tr>
                    <th class="text-left">Material / run</th><th>Volume (m³)</th><th>Corrected target</th><th>Measured actual</th>
                </tr></thead>
                <tbody><tr v-for="row in rows" :key="row.id" class="border-b">
                    <th class="py-1 text-left font-normal">{{ row.name }}</th><td>{{ row.volume }}</td>
                    <td>{{ row.target.toFixed(3) }}</td><td>{{ row.actual.toFixed(3) }}</td>
                </tr></tbody>
            </table>
        </div>
    </section>
</template>
