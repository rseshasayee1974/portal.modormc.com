<!--
Author: ragul-onemodo
Created: 2026-11-06 10:11:50 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed } from 'vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
const props = defineProps({ form: Object, taxes: Array, products: Array });
const round = value => Math.round((value + Number.EPSILON) * 100) / 100;
const rows = computed(() => props.form.lines.map((line, index) => {
    const gross = round(Number(line.quantity_issued || 0) * Number(line.unit_price || 0));
    const discount = round(line.discount_type === '%' ? gross * Number(line.discount || 0) / 100 : Number(line.discount || 0));
    const net = Math.max(0, gross - discount);
    const rate = Number(props.taxes.find(tax => tax.id === line.tax_id)?.rate || 0);
    const base = round(props.form.is_tax_inclusive ? net / (1 + rate / 100) : net);
    const tax = round(props.form.is_tax_inclusive ? net - base : base * rate / 100);
    return { line, index, discount, base, tax, total: round(base + tax) };
}).filter(row => row.line.product_id));
const totals = computed(() => rows.value.reduce((sum, row) => {
    for (const key of ['discount', 'base', 'tax', 'total']) sum[key] = round(sum[key] + row[key]);
    return sum;
}, { discount: 0, base: 0, tax: 0, total: 0 }));
const money = value => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>
<template>
    <section class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold">Stock Out Pricing</h3>
            <BaseSelect v-model="form.is_tax_inclusive" label="Price includes tax" :options="[{label:'Tax exclusive',value:false},{label:'Tax inclusive',value:true}]" optionLabel="label" optionValue="value" />
        </div>
        <p class="text-xs text-slate-500">Discount applies to quantity × unit price before calculating tax. Inclusive prices already contain tax.</p>
        <div v-for="row in rows" :key="row.index" class="space-y-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
            <p class="font-semibold text-sm">{{ products.find(p => p.value === row.line.product_id)?.label || 'Item' }} · Qty {{ row.line.quantity_issued }}</p>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <BaseInputNumber v-model="row.line.unit_price" label="Unit Price" :min="0" :minFractionDigits="2" :maxFractionDigits="4" :error="form.errors['lines.' + row.index + '.unit_price']" />
                <BaseSelect v-model="row.line.tax_id" label="Tax" :options="taxes" optionLabel="label" optionValue="id" placeholder="No tax" showClear :error="form.errors['lines.' + row.index + '.tax_id']" />
                <BaseSelect v-model="row.line.discount_type" label="Discount Type" :options="[{label:'Amount',value:'amount'},{label:'Percentage',value:'%'}]" optionLabel="label" optionValue="value" />
                <BaseInputNumber v-model="row.line.discount" label="Discount" :min="0" :max="row.line.discount_type === '%' ? 100 : undefined" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors['lines.' + row.index + '.discount']" />
            </div>
            <p class="text-sm">Taxable: {{ money(row.base) }} · Tax: {{ money(row.tax) }} · <strong>Total: {{ money(row.total) }}</strong></p>
        </div>
        <div class="flex flex-wrap justify-end gap-6 text-sm"><span>Discount: {{ money(totals.discount) }}</span><span>Taxable: {{ money(totals.base) }}</span><span>Tax: {{ money(totals.tax) }}</span><strong>Total Amount: {{ money(totals.total) }}</strong></div>
        <p v-if="form.errors.lines" class="text-sm text-red-600">{{ form.errors.lines }}</p>
        <p v-if="form.errors.bill_number || form.errors.billed_date" class="text-sm text-red-600">{{ form.errors.bill_number || form.errors.billed_date }}</p>
    </section>
</template>
