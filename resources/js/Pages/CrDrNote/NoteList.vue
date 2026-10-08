<!--
Author: ragul-onemodo
Created: 2026-10-07 18:31:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import NoteEditForm from './NoteEditForm.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import { usePermissions } from '@/Composables/usePermissions';

defineProps({ notes: { type: Array, default: () => [] }, documents: { type: Array, default: () => [] }, taxes: { type: Array, default: () => [] }, units: { type: Array, default: () => [] }, products: { type: Array, default: () => [] }, mixdesign: { type: Array, default: () => [] }, note_number_details: { type: Object, default: () => ({}) } });
const { can } = usePermissions();
const filters = ref({ global: { value: null, matchMode: 'contains' } });
const expandedRows = ref({});
const deletingId = ref(null);
const deleteNote = async note => {
    const result = await Swal.fire({
        title: 'Delete Note?',
        text: `Delete ${note.full_number} and revert its accounting entries?`,
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Delete', confirmButtonColor: '#ef4444',
    });
    if (!result.isConfirmed) return;
    deletingId.value = note.id;
    router.delete(route('crdrnote.destroy', { note: note.encrypted_id }), {
        preserveScroll: true,
        onSuccess: () => { expandedRows.value = {}; },
        onError: errors => Swal.fire('Unable to delete', errors.delete || Object.values(errors)[0] || 'Failed to delete the note.', 'error'),
        onFinish: () => { deletingId.value = null; },
    });
};
const canEdit = note => can('CRDRNOTE.UPDATE') && String(note.status).toLowerCase() === 'approved' && !!note.source_document;
const toggleEdit = note => {
    if (!canEdit(note)) return;
    expandedRows.value = expandedRows.value[note.id] ? {} : { [note.id]: true };
};
const print = note => window.open(route('print.document', { module: 'invoices', id: note.encrypted_id, action: 'view' }), '_blank');
</script>

<template>
    <div class="bg-white dark:bg-slate-800 shadow-xl rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
    <BaseDataTable :value="notes" dataKey="id" v-model:expandedRows="expandedRows" v-model:filters="filters" :globalFilterFields="['full_number', 'invoice_label', 'ref_title', 'partner.legal_name', 'notes']" paginator :rows="30" stripedRows showSearch showSerial heading="Credit / Debit Note Directory" headingIcon="DocumentTextIcon" class="p-datatable-sm" @row-click="toggleEdit($event.data)">
        <Column field="full_number" header="Ref #" sortable><template #body="{ data }"><span class="text-sm font-semibold text-indigo-800">{{ data.full_number }}</span></template></Column>
        <Column field="invoice_label" header="Type" sortable><template #body="{ data }"><Tag :value="data.invoice_label" :severity="data.invoice_type === 'credit_note' ? 'success' : 'warn'" class="!text-[9px] !uppercase" /></template></Column>
        <Column field="invoice_date" header="Date" sortable><template #body="{ data }">{{ entityLocaleDate(data.invoice_date) }}</template></Column>
        <Column field="ref_title" header="Invoice / Bill" sortable />
        <Column field="partner.legal_name" header="Partner" sortable />
        <Column field="notes" header="Reason" />
        <Column field="total_amount" header="Amount" sortable class="text-right font-semibold"><template #body="{ data }">₹ {{ Number(data.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</template></Column>
        <!-- <Column field="status" header="Status"><template #body="{ data }"><Tag :value="data.status" :severity="['cancelled', 'void'].includes(String(data.status).toLowerCase()) ? 'danger' : String(data.status).toLowerCase() === 'approved' ? 'info' : String(data.status).toLowerCase() === 'paid' ? 'success' : 'secondary'" class="!text-[9px] !uppercase" /></template></Column> -->
        <Column header="Actions"><template #body="{ data }">
            <!-- <Button icon="pi pi-pencil" text rounded title="Edit Note" v-if="can('CRDRNOTE.UPDATE')" :disabled="!canEdit(data)" @click.stop="toggleEdit(data)" /> -->
            <Button icon="pi pi-print" text rounded title="Print Note" @click.stop="print(data)" />
            <Button v-if="can('CRDRNOTE.DELETE')" icon="pi pi-trash" severity="danger" text rounded title="Delete Note and Revert Accounting" :loading="deletingId === data.id" :disabled="deletingId !== null" @click.stop="deleteNote(data)" />
        </template></Column>
        <template #expansion="{ data }">
            <BaseExpansionPanel :title="data.full_number">
                <div class="max-w-6xl mx-auto mt-4">
                    <NoteEditForm :key="data.id" :note="data" :taxes="taxes" :units="units" :products="products" :mixdesign="mixdesign" :note_number_details="note_number_details" @saved="expandedRows = {}" @cancel="expandedRows = {}" />
                </div>
            </BaseExpansionPanel>
        </template>
    </BaseDataTable>
    </div>
</template>
