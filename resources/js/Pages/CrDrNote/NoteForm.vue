<!--
Author: ragul-onemodo
Created: 2026-10-07 18:31:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = defineProps({ documents: { type: Array, default: () => [] }, note: { type: Object, default: null } });
const emit = defineEmits(['saved', 'cancel']);
const defaults = { credit_note: 'Sales Return / Invoice Adjustment', debit_note: 'Purchase Return / Bill Adjustment' };
const reasons = {
    credit_note: ['Sales Return', 'Invoice Adjustment', 'Goods Returned by Customer', 'Excess Billing', 'Rate / Price Adjustment', 'Discount Adjustment', 'Tax Correction', 'Invoice Cancellation / Correction'],
    debit_note: ['Purchase Return', 'Bill Adjustment', 'Goods Returned to Supplier', 'Short Billing by Supplier', 'Rate / Price Adjustment', 'Additional Charges', 'Tax Correction', 'Bill Correction'],
};
const types = [{ label: 'Credit Note', value: 'credit_note' }, { label: 'Debit Note', value: 'debit_note' }];
const form = useForm({ source_id: props.note?.ref_id ?? null, note_type: props.note?.invoice_type ?? 'credit_note',
    note_date: props.note?.invoice_date?.slice(0, 10) ?? new Date(), reason: props.note?.notes ?? defaults.credit_note });
const reasonOptions = computed(() => [...new Set([defaults[form.note_type], ...reasons[form.note_type], ...(props.note?.notes ? [props.note.notes] : [])])]
    .map(value => ({ label: value, value })));
const sourceOptions = computed(() => props.documents
    .filter(document => !(document.adjustment_notes || []).some(note => note.invoice_type === form.note_type))
    .map(document => ({ label: document.invoice_type + ' ' + document.full_number + ' — ' + (document.partner?.legal_name || ''), value: document.id })));
const source = computed(() => props.note?.source_document ?? props.documents.find(document => document.id === form.source_id));
const lines = computed(() => props.note?.items ?? source.value?.items ?? []);
watch(() => form.note_type, type => {
    form.reason = defaults[type];
    if (!props.note && !sourceOptions.value.some(option => option.value === form.source_id)) form.source_id = null;
});
const money = value => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const save = () => {
    form.transform(data => {
        if (!(data.note_date instanceof Date)) return data;
        const date = data.note_date;
        return { ...data, note_date: date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') };
    });
    const options = { preserveScroll: true, onSuccess: () => { if (!props.note) form.reset(); emit('saved'); } };
    if (props.note) form.put(route('crdrnote.update', props.note.encrypted_id), options);
    else form.post(route('crdrnote.store'), options);
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <BaseSelect v-model="form.note_type" label="Note Type" :options="types" optionLabel="label" optionValue="value" :disabled="!!note" :error="form.errors.note_type" required />
            <BaseDatePicker v-model="form.note_date" label="Note Date" :error="form.errors.note_date" required />
            <BaseSelect v-if="!note" v-model="form.source_id" label="Against Invoice / Bill" :options="sourceOptions" optionLabel="label" optionValue="value" :error="form.errors.source_id" required />
            <div v-else class="p-3 rounded bg-slate-50">Against {{ source?.invoice_type }} {{ note.ref_title }}</div>
            <BaseSelect v-model="form.reason" label="Reason" :options="reasonOptions" optionLabel="label" optionValue="value" :error="form.errors.reason" required />
        </div>
        <div v-if="source || note" class="space-y-3">
            <p class="text-sm">Account: {{ source?.partner?.legal_name || note?.partner?.legal_name }}. The note uses the full source document amount. Source and amounts remain fixed when editing.</p>
            <div class="overflow-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-100"><tr><th class="p-2">Item</th><th class="p-2">Qty</th><th class="p-2">UOM</th><th class="p-2">Rate</th><th class="p-2">Taxable Amount</th><th class="p-2">Tax</th></tr></thead>
                    <tbody><tr v-for="line in lines" :key="line.id" class="border-b"><td class="p-2">{{ line.item_name }}</td><td class="p-2">{{ line.quantity }}</td><td class="p-2">{{ line.uom?.unit_code }}</td><td class="p-2">{{ money(line.price_unit) }}</td><td class="p-2">{{ money(line.subtotal) }}</td><td class="p-2">{{ money(line.line_tax_amount) }}</td></tr></tbody>
                </table>
            </div>
            <p class="text-right font-semibold">Net Amount: ₹ {{ money(note?.total_amount ?? source?.total_amount) }}</p>
        </div>
        <div class="flex justify-end gap-2">
            <Button label="Cancel" severity="secondary" type="button" @click="emit('cancel')" />
            <Button :label="note ? 'Save Changes' : 'Create Note'" type="submit" :loading="form.processing" :disabled="!note && !source" />
        </div>
    </form>
</template>
