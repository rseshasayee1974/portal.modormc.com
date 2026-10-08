<!--
Author: ragul-onemodo
Created: 2026-10-07 18:31:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import { usePermissions } from '@/Composables/usePermissions';

defineProps({ notes: { type: Array, default: () => [] } });
const emit = defineEmits(['edit']);
const { can } = usePermissions();
const filters = ref({ global: { value: null, matchMode: 'contains' } });
const print = note => window.open(route('print.document', { module: 'invoices', id: note.encrypted_id, action: 'view' }), '_blank');
</script>

<template>
    <BaseDataTable :value="notes" dataKey="id" v-model:filters="filters" :globalFilterFields="['full_number', 'invoice_label', 'ref_title', 'partner.legal_name', 'notes']" paginator :rows="15">
        <Column field="full_number" header="Note Number" sortable />
        <Column field="invoice_label" header="Type" sortable />
        <Column field="invoice_date" header="Date" sortable><template #body="{ data }">{{ entityLocaleDate(data.invoice_date) }}</template></Column>
        <Column field="ref_title" header="Against Invoice / Bill" sortable />
        <Column field="partner.legal_name" header="Account Name" sortable />
        <Column field="notes" header="Reason" />
        <Column field="total_amount" header="Net Amount" sortable><template #body="{ data }">₹ {{ Number(data.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</template></Column>
        <Column field="status" header="Status" />
        <Column header="Actions"><template #body="{ data }">
            <Button icon="pi pi-pencil" text title="Edit Note" v-if="can('CRDRNOTE.UPDATE')" :disabled="String(data.status).toLowerCase() !== 'approved' || !data.source_document" @click="emit('edit', data)" />
            <Button icon="pi pi-print" text title="Print Note" @click="print(data)" />
        </template></Column>
    </BaseDataTable>
</template>
