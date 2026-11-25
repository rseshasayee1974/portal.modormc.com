<!--
Author: ragul-onemodo
Created: 2026-11-25 10:18:55 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Button from 'primevue/button';

const props = defineProps<{
    trip: any; order: any; sites: any[]; purchaseOrders: any[]; showTrip: boolean;
}>();
const emit = defineEmits<{ saved: [] }>();
const site = ref<number | null>(null);
const po = ref<number | null>(null);
const saving = ref(false);
const message = ref('');
const savedSite = computed(() => props.trip.unload_site_id ?? props.order?.site?.id ?? null);
const savedPo = computed(() => props.order?.customer_po_id ?? null);
watch([() => props.trip.id, savedSite, savedPo], () => {
    site.value = savedSite.value;
    po.value = savedPo.value;
}, { immediate: true });
const options = computed(() => props.purchaseOrders
    .filter(p => Number(p.patron_id) === Number(props.order?.customer?.id))
    .map(p => ({ id: p.id, label: [p.reference, p.customer_po_reference].filter(Boolean).join(' / ') || String(p.id) })));
const changed = computed(() => site.value !== savedSite.value || po.value !== savedPo.value);
async function save() {
    if (saving.value || !changed.value) return;
    saving.value = true;
    message.value = '';
    try {
        await axios.patch(route('dispatches.delivery-references', props.trip.id), {
            ...(site.value !== savedSite.value ? { unload_site_id: site.value } : {}),
            ...(po.value !== savedPo.value ? { customer_po_id: po.value } : {}),
        });
        emit('saved');
        message.value = 'Delivery references updated.';
    } catch (error: any) {
        const data = error.response?.data;
        message.value = data?.errors?.unload_site_id?.[0] || data?.errors?.customer_po_id?.[0] || data?.message || 'Unable to update delivery references.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-3 border-b p-4">
        <span v-if="showTrip">Dispatch {{ trip.dispatch_no || trip.id }}</span>
        <BaseSelect v-model="site" label="Unloading site" :options="sites" optionLabel="name" optionValue="id" filter />
        <BaseSelect v-model="po" label="Sales order customer PO" :disabled="!trip.sales_order_id" :options="options" optionLabel="label" optionValue="id" filter />
        <Button type="button" label="Update delivery references" :disabled="!changed" :loading="saving" @click="save" />
        <span role="status" class="text-sm">{{ message }}</span>
    </div>
</template>
