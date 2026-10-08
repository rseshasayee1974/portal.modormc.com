<!--
Author: ragul-onemodo
Created: 2026-10-07 17:52:24 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = defineProps({ document: { type: Object, required: true } });
const visible = ref(false);
const defaultReasons = {
    credit_note: 'Sales Return / Invoice Adjustment',
    debit_note: 'Purchase Return / Bill Adjustment',
};
const reasons = {
    credit_note: ['Sales Return', 'Invoice Adjustment', 'Goods Returned by Customer', 'Excess Billing',
        'Rate / Price Adjustment', 'Discount Adjustment', 'Tax Correction', 'Invoice Cancellation / Correction'],
    debit_note: ['Purchase Return', 'Bill Adjustment', 'Goods Returned to Supplier', 'Short Billing by Supplier',
        'Rate / Price Adjustment', 'Additional Charges', 'Tax Correction', 'Bill Correction'],
};
const form = useForm({ note_type: 'credit_note', note_date: null, reason: defaultReasons.credit_note });
const reasonOptions = computed(() => [defaultReasons[form.note_type], ...reasons[form.note_type]]
    .map(reason => ({ label: reason, value: reason })));
watch(() => form.note_type, type => { form.reason = defaultReasons[type]; });
const options = computed(() => [{ label: 'Credit Note', value: 'credit_note' }, { label: 'Debit Note', value: 'debit_note' }]
    .filter(option => !(props.document.adjustment_notes || []).some(note => String(note.invoice_type).toLowerCase() === option.value)));
watch(options, remaining => {
    if (!remaining.length) visible.value = false;
    else if (!remaining.some(option => option.value === form.note_type)) form.note_type = remaining[0].value;
});
const available = computed(() => ['approved', 'paid'].includes(String(props.document.status).toLowerCase()));
const reduces = computed(() => ['bill', 'purchase'].includes(String(props.document.invoice_type).toLowerCase())
    ? form.note_type === 'debit_note' : form.note_type === 'credit_note');
const open = () => {
    if (!available.value || !options.value.length) return;
    form.reset();
    form.note_type = options.value[0].value;
    form.reason = defaultReasons[form.note_type];
    form.clearErrors();
    form.note_date = new Date();
    visible.value = true;
};
const generate = () => {
    if (form.processing || !options.value.some(option => option.value === form.note_type)) return;
    form.transform(data => {
        if (typeof data.note_date === 'string') return data;
        const date = data.note_date instanceof Date ? data.note_date : new Date(data.note_date);
        return { ...data, note_date: Number.isNaN(date.getTime()) ? ''
            : `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` };
    }).post(route('invoices.notes.store', props.document.encrypted_id), {
        preserveScroll: true, onSuccess: () => { visible.value = false; },
    });
};
const print = note => window.open(route('print.document', { module: 'invoices', id: note.encrypted_id, action: 'view' }), '_blank');
</script>

<template>
    <div @click.stop>
        <Button v-if="options.length" :label="options.length === 1 ? options[0].label : 'Credit / Debit Note'" icon="pi pi-file-edit" text size="small" :disabled="!available" @click="open" />
        <div v-for="note in document.adjustment_notes || []" :key="note.id">
            <Button :label="`${note.invoice_label}: ${note.full_number}`" icon="pi pi-print" text size="small" @click="print(note)" />
        </div>
        <Dialog v-model:visible="visible" modal header="Generate Credit / Debit Note" :style="{ width: '30rem' }">
            <form class="flex flex-col gap-4" @submit.prevent="generate">
                <p>Against {{ document.full_number }}. This generates a note for the full document amount of {{ Number(document.total_amount).toFixed(2) }}.</p>
                <BaseSelect v-model="form.note_type" label="Note Type" :options="options" optionLabel="label" optionValue="value" />
                <small v-if="form.errors.note_type" class="text-red-600">{{ form.errors.note_type }}</small>
                <BaseDatePicker v-model="form.note_date" label="Note Date" />
                <small v-if="form.errors.note_date" class="text-red-600">{{ form.errors.note_date }}</small>
                <BaseSelect v-model="form.reason" label="Reason" required :options="reasonOptions" optionLabel="label" optionValue="value" />
                <small v-if="form.errors.reason" class="text-red-600">{{ form.errors.reason }}</small>
                <p class="text-sm">This note {{ reduces ? 'reduces' : 'increases' }} the {{ ['bill', 'purchase'].includes(String(document.invoice_type).toLowerCase()) ? 'supplier payable' : 'customer receivable' }} in the ledger.</p>
                <Button type="submit" label="Generate Note" :loading="form.processing" />
            </form>
        </Dialog>
    </div>
</template>
