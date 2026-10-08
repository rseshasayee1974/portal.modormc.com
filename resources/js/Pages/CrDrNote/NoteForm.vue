<!--
Author: ragul-onemodo
Created: 2026-10-07 18:31:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import { entityLocaleDate } from '@/Utils/entityDateTime';
import { useNoteItems } from './useNoteItems';

const props = defineProps({ documents: { type: Array, default: () => [] }, taxes: { type: Array, default: () => [] }, units: { type: Array, default: () => [] }, products: { type: Array, default: () => [] }, mixdesign: { type: Array, default: () => [] }, note_number_details: { type: Object, default: () => ({}) } });
const emit = defineEmits(['saved', 'cancel']);
const defaults = { credit_note: 'Sales Return / Invoice Adjustment', debit_note: 'Purchase Return / Bill Adjustment' };
const reasons = {
    credit_note: ['Sales Return', 'Invoice Adjustment', 'Goods Returned by Customer', 'Excess Billing', 'Rate / Price Adjustment', 'Discount Adjustment', 'Tax Correction', 'Invoice Cancellation / Correction'],
    debit_note: ['Purchase Return', 'Bill Adjustment', 'Goods Returned to Supplier', 'Short Billing by Supplier', 'Rate / Price Adjustment', 'Additional Charges', 'Tax Correction', 'Bill Correction'],
};
const types = [{ label: 'Credit Note', value: 'credit_note' }, { label: 'Debit Note', value: 'debit_note' }];
const form = useForm({ items: [], is_tax_inclusive: false, source_id: null, invoice_number: '', note_type: 'credit_note', note_date: new Date(), reason: defaults.credit_note });
const reasonOptions = computed(() => [...new Set([defaults[form.note_type], ...reasons[form.note_type]])]
    .map(value => ({ label: value, value })));
const selectedPartnerId = ref(null);
const selectedAccountId = ref(null);
const partnerId = document => document.partner_id ?? document.partner?.id ?? null;
const accountId = document => document.account_id ?? document.account?.id ?? null;
const eligibleDocuments = computed(() => props.documents
    .filter(document => (form.note_type === 'credit_note' ? ['invoice', 'sales'] : ['bill', 'purchase'])
        .includes(String(document.invoice_type).toLowerCase()))
    .filter(document => !(document.adjustment_notes || []).length));
const dropdownOptions = (documents, getId, getLabel) => [...new Map(documents
    .filter(document => getId(document) !== null && getLabel(document))
    .map(document => [getId(document), { label: getLabel(document), value: getId(document) }])).values()]
    .sort((a, b) => a.label.localeCompare(b.label));
const partnerOptions = computed(() => dropdownOptions(eligibleDocuments.value, partnerId, document => document.partner?.legal_name));
const accountOptions = computed(() => dropdownOptions(eligibleDocuments.value, accountId, document => document.account?.title));
const sourceOptions = computed(() => eligibleDocuments.value
    .filter(document => selectedPartnerId.value === null || partnerId(document) === selectedPartnerId.value)
    .filter(document => selectedAccountId.value === null || accountId(document) === selectedAccountId.value)
    .map(document => ({ label: document.full_number + ' — ' + (document.partner?.legal_name || ''), value: document.id })));
const source = computed(() => props.documents.find(document => document.id === form.source_id));
const { amounts, lineTotals, taxOptions, unitOptions, itemOptions, selectItem, removeItem } = useNoteItems(form, () => source.value, () => props.taxes, () => props.units, () => props.products, () => props.mixdesign);
const numberDetails = computed(() => props.note_number_details[form.note_type] || {});
const lines = computed(() => form.items);
const taxInclusive = computed(() => form.is_tax_inclusive);
watch(() => form.note_type, type => {
    form.reason = defaults[type];
    selectedPartnerId.value = null;
    selectedAccountId.value = null;
    form.source_id = null;
});
watch(() => form.source_id, () => {
    if (!source.value) return;
    selectedPartnerId.value = partnerId(source.value);
    selectedAccountId.value = accountId(source.value);
});
watch([selectedPartnerId, selectedAccountId], () => {
    if (source.value && (selectedPartnerId.value !== partnerId(source.value) || selectedAccountId.value !== accountId(source.value))) {
        form.source_id = null;
    }
});
const money = value => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const save = () => {
    form.transform(data => {
        if (!(data.note_date instanceof Date)) return data;
        const date = data.note_date;
        return { ...data, note_date: date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') };
    });
    form.post(route('crdrnote.store'), { preserveScroll: true, onSuccess: () => { form.reset(); emit('saved'); } });
};
</script>

<template>
    <form class="space-y-6 p-1" @submit.prevent="save">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-4">
            <BaseSelect class="md:col-span-2" v-model="form.note_type" label="Note Type" :options="types" optionLabel="label" optionValue="value" :error="form.errors.note_type" required />
            <BaseDatePicker class="sm:col-span-2" v-model="form.note_date" label="Note Date" :error="form.errors.note_date" required  />
            <BaseSelect class="md:col-span-2" v-model="selectedPartnerId" label="Account Name" :options="partnerOptions" optionLabel="label" optionValue="value" placeholder="Select Account Name" filter showClear />
            <BaseSelect class="sm:col-span-2" v-model="form.source_id" :label="form.note_type === 'credit_note' ? 'Against Invoice' : 'Against Bill'" :options="sourceOptions" optionLabel="label" optionValue="value" :placeholder="form.note_type === 'credit_note' ? 'Select Invoice' : 'Select Bill'" filter :error="form.errors.source_id" required />
            
            
            <BaseSelect class="md:col-span-2" v-model="selectedAccountId" label="Ledger Account" :options="accountOptions" optionLabel="label" optionValue="value" placeholder="Select Ledger Account" filter showClear />
            <BaseSelect class="sm:col-span-2 md:col-span-2" v-model="form.reason" label="Reason" :options="reasonOptions" optionLabel="label" optionValue="value" filter :error="form.errors.reason" required />
            <BaseInput class="md:col-span-2" v-model="form.invoice_number" :label="`Note Number (${numberDetails.prefix || ''})`" :placeholder="numberDetails.next_number || 'Auto generated on save'" :error="form.errors.invoice_number" :disabled="form.processing" hint="Leave blank to generate automatically" />
        </div>

        <div>
            <div class="flex flex-wrap justify-between items-center gap-3 mb-3">
                <h3 class="text-xs font-semibold text-slate-600 uppercase flex items-center gap-2"><DocumentTextIcon class="w-4 h-4 text-indigo-500" aria-hidden="true" /> Product / Service Details</h3>
                <div class="inline-flex items-center gap-3 px-3 py-2 rounded-lg border text-xs font-semibold" :class="form.is_tax_inclusive ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-700'">
                    <ToggleSwitch v-model="form.is_tax_inclusive" inputId="note-tax-inclusive-create" aria-label="Tax Inclusive" :disabled="form.processing" />
                    <label for="note-tax-inclusive-create" class="cursor-pointer">Tax Inclusive</label>
                </div>
            </div>
            <p v-if="form.errors.items" class="text-xs text-rose-600 mb-2">{{ form.errors.items }}</p>
            <div class="border border-slate-100 rounded-sm shadow-sm overflow-hidden bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead class="bg-slate-50 border-b border-slate-100 uppercase text-[10px] font-semibold text-slate-500">
                            <tr><th class="px-4 py-3 min-w-[200px]">Product / Service</th><th class="px-3 py-3 w-28 text-center">Qty</th><th class="px-3 py-3 w-24 text-center">UOM</th><th class="px-3 py-3 w-32 text-center">Rate</th><th class="px-3 py-3 text-right">Discount</th><th class="px-3 py-3 text-right">Taxable Amount</th><th class="px-3 py-3 text-right">Tax</th><th class="px-4 py-3 text-right">Net Amount</th><th class="px-2 py-3"><span class="sr-only">Remove Item</span></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="(line, index) in lines" :key="line.id" class="hover:bg-indigo-50/20 text-xs">
                                <td class="p-2"><BaseSelect :modelValue="line.item_id" :options="itemOptions(line)" optionLabel="label" optionValue="value" placeholder="Select product / mix design" :error="form.errors[`items.${index}.item_id`] || form.errors[`items.${index}.item_name`]" :disabled="form.processing" @update:modelValue="selectItem(line, $event)" /></td>
                                <td class="p-2"><BaseInputNumber v-model="line.quantity" :min="0.01" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors[`items.${index}.quantity`]" /></td>
                                <td class="p-2"><BaseSelect v-model="line.uom_id" :options="unitOptions" optionLabel="label" optionValue="value" placeholder="UOM" filter :error="form.errors[`items.${index}.uom_id`]" /></td>
                                <td class="p-2"><BaseInputNumber v-model="line.price_unit" :min="0" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors[`items.${index}.price_unit`]" /></td>
                                <td class="p-2 min-w-[160px]"><div class="flex gap-1"><BaseSelect v-model="line.discount_type" :options="[{ label: '%', value: '%' }, { label: '₹', value: '₹' }]" optionLabel="label" optionValue="value" class="w-16 shrink-0" /><BaseInputNumber v-model="line.discount" :min="0" :error="form.errors[`items.${index}.discount`]" /></div></td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money(lineTotals(line).subtotal) }}</td>
                                <td class="p-2 min-w-[140px]"><BaseSelect v-model="line.tax_id" :options="taxOptions" optionLabel="label" optionValue="value" placeholder="No Tax" filter showClear :error="form.errors[`items.${index}.tax_id`]" /><span class="block mt-1 text-right text-[10px] text-slate-500">{{ money(lineTotals(line).line_tax_amount) }}</span></td>
                                <td class="px-4 py-2 text-right font-semibold text-slate-700 tabular-nums">{{ money(lineTotals(line).line_total) }}</td>
                                <td class="p-2"><Button v-if="lines.length > 1" icon="pi pi-trash" severity="danger" text rounded type="button" title="Remove item" @click="removeItem(index)" /></td>
                            </tr>
                            <tr v-if="!lines.length"><td colspan="9" class="py-10 text-center text-xs text-slate-400">{{ source ? 'No line items on this document.' : 'Select an Invoice / Bill to load its items.' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 px-1">
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-[10px] font-semibold text-slate-500 uppercase mb-3">Source Document</h3>
                    <dl class="space-y-2 text-xs"><div class="flex justify-between gap-3"><dt class="text-slate-500">Reference</dt><dd class="font-semibold text-indigo-600">{{ source?.full_number || '—' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Date</dt><dd class="text-slate-700">{{ source?.invoice_date ? entityLocaleDate(source.invoice_date) : '—' }}</dd></div></dl>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Items are loaded from the source document. Adjust quantity, rate, tax, or discount for this note.</p>
            </div>
            <div class="bg-indigo-50/30 rounded-xl p-4 border border-indigo-100 shadow-inner">
                <div class="space-y-3 text-xs tabular-nums">
                    <div class="flex justify-between"><span class="text-slate-500">Taxable Amount</span><span class="font-semibold text-slate-700">₹ {{ money(amounts.subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Total Tax</span><span class="font-semibold text-slate-700">₹ {{ money(amounts.tax_amount) }}</span></div>
                    <div v-if="Number(amounts.discount_total)" class="flex justify-between"><span class="text-slate-500">Discount</span><span class="font-semibold text-rose-600">− ₹ {{ money(amounts.discount_total) }}</span></div>
                    <div v-if="Number(amounts.shipping_charges)" class="flex justify-between"><span class="text-slate-500">Shipping Charges</span><span>₹ {{ money(amounts.shipping_charges) }}</span></div>
                    <div v-if="Number(amounts.adjustment)" class="flex justify-between"><span class="text-slate-500">Adjustment</span><span>₹ {{ money(amounts.adjustment) }}</span></div>
                    <div class="flex justify-between border-t border-slate-200/50 pt-3"><span class="text-slate-500">Round Off</span><span>₹ {{ money(amounts.round_off) }}</span></div>
                    <div class="flex flex-wrap justify-between items-center gap-2 border-t border-slate-200 pt-4"><span class="text-sm font-semibold text-indigo-700 uppercase">Net Amount</span><span class="text-3xl font-bold text-slate-800 tracking-tight">₹ {{ money(amounts.total_amount) }}</span></div>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button label="Reset" icon="pi pi-refresh" severity="secondary" type="button" :disabled="form.processing" @click="emit('cancel')" />
            <Button label="Generate Note" icon="pi pi-check" type="submit" :loading="form.processing" :disabled="!source || !lines.length || amounts.total_amount <= 0" />
        </div>
    </form>
</template>
