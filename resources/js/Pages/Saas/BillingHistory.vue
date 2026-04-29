<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    apiKey: { type: String, default: '' },
    canManageBilling: { type: Boolean, default: false },
    entityId: { type: [Number, String], default: null },
    plantId: { type: [Number, String, null], default: null },
});

const rows = ref([]);
const apiKey = ref(props.apiKey);
const entityId = Number(props.entityId);
const plantId = props.plantId === null || props.plantId === '' ? null : Number(props.plantId);
const authHeader = computed(() => ({ Authorization: `Bearer ${apiKey.value}` }));

const load = async () => {
    if (!entityId || !props.canManageBilling || !apiKey.value) return;
    const res = await axios.get('/api/billing/history', {
        headers: authHeader.value,
        params: { entity_id: entityId, plant_id: plantId },
    });
    rows.value = res.data.data ?? [];
};

const generate = async () => {
    if (!entityId || !props.canManageBilling || !apiKey.value) return;
    await axios.post('/api/billing/generate', {
        entity_id: entityId,
        plant_id: plantId,
    }, { headers: authHeader.value });
    await load();
};

const mockPay = async (id, success) => {
    if (!props.canManageBilling || !apiKey.value) return;
    await axios.post(`/api/billing/${id}/pay`, {
        success,
        entity_id: entityId,
        plant_id: plantId,
    }, { headers: authHeader.value });
    await load();
};

onMounted(load);
</script>

<template>
    <AppLayout title="Billing History">
        <div class="p-6 space-y-4">
            <div v-if="!props.canManageBilling" class="bg-amber-50 border border-amber-200 text-amber-700 p-3 rounded">
                Only SaaS Owner or Platform Admin can access billing APIs.
            </div>
            <div class="flex gap-2">
                <button class="px-3 py-2 bg-indigo-600 text-white rounded disabled:opacity-50" :disabled="!props.canManageBilling" @click="generate">Generate Current Month Bill</button>
            </div>

            <div class="bg-white border rounded-lg p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">Month</th>
                            <th class="py-2">Total Amount</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in rows" :key="row.id">
                        <tr class="border-b">
                            <td class="py-2">{{ row.month }}</td>
                            <td class="py-2">${{ Number(row.total_amount).toFixed(4) }}</td>
                            <td class="py-2">{{ row.status }}</td>
                            <td class="py-2 flex gap-2">
                                <button class="px-2 py-1 bg-green-600 text-white rounded" @click="mockPay(row.id, true)">Mark Paid</button>
                                <button class="px-2 py-1 bg-gray-500 text-white rounded" @click="mockPay(row.id, false)">Mark Failed</button>
                            </td>
                        </tr>
                        <tr class="border-b bg-gray-50">
                            <td colspan="4" class="py-3">
                                <div class="text-xs font-semibold mb-2">Module Breakdown</div>
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-left border-b">
                                            <th class="py-1">Module</th>
                                            <th class="py-1">Tokens</th>
                                            <th class="py-1">Requests</th>
                                            <th class="py-1">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="moduleRow in row.module_breakdown || []" :key="`${row.id}-${moduleRow.module}`" class="border-b">
                                            <td class="py-1">{{ moduleRow.module }}</td>
                                            <td class="py-1">{{ moduleRow.tokens }}</td>
                                            <td class="py-1">{{ moduleRow.requests }}</td>
                                            <td class="py-1">${{ Number(moduleRow.cost).toFixed(4) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
