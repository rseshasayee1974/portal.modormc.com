<!--
Author: ragul-onemodo
Created: 2026-11-06 10:11:50 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed } from 'vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { useLineItemCalculation } from '@/Composables/useLineItemCalculation';
const props = defineProps({ form: Object, taxes: Array, products: Array, units: Array, localErrors: Object, getFilteredProducts: Function });
const emit = defineEmits(['product-change', 'quantity-blur', 'remove']);
const unitOptions = computed(() => (props.units || []).map(unit => ({ ...unit, label: unit.unit_code })));
const unitLabel = value => unitOptions.value.find(unit => String(unit.id) === String(value))?.label || 'Select UOM';
const error = (index, field) => props.form.errors['lines.' + index + '.' + field] || props.localErrors?.lineErrors?.[index]?.[field];
const { calculateLineItemTotals } = useLineItemCalculation();
const rows = computed(() => props.form.lines.map((line, index) => {
    const rate = Number(props.taxes.find(tax => Number(tax.id) === Number(line.tax_id))?.rate || 0);
    const amounts = calculateLineItemTotals({
        quantity: Number(line.quantity_issued || 0), rate: Number(line.unit_price || 0), pump_rate: 0,
        taxRate: rate, isTaxInclusive: props.form.is_tax_inclusive,
        discount: Number(line.discount || 0), discountType: line.discount_type,
        roundAmounts: false,
    });
    const gross = Number(line.quantity_issued || 0) * Number(line.unit_price || 0);
    const invalidDiscount = Number(line.discount || 0) > (line.discount_type === '%' ? 100 : gross);
    return {
        line, index, rate, gross, invalidDiscount, discount: amounts.discountAmount,
        base: amounts.untaxedAmount, tax: amounts.taxAmount, total: amounts.amountTotal
    };
}));
const totals = computed(() => rows.value.filter(row => row.line.product_id).reduce((sum, row) => {
    for (const key of ['discount', 'base', 'tax', 'total']) sum[key] += row[key];
    return sum;
}, { discount: 0, base: 0, tax: 0, total: 0 }));
const money = value => Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 12 });
</script>
<template>
    <section class="stock-items min-w-0 max-w-full space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <p class="text-xs text-slate-500">Discount applies before tax.
            </p>
            <label class="flex cursor-pointer items-center gap-3 text-sm">
                <span>Price includes tax</span>
                <ToggleSwitch v-model="form.is_tax_inclusive" aria-label="Price includes tax"
                    :disabled="form.processing" />
                <span class="text-xs text-slate-500">{{ form.is_tax_inclusive ? 'Tax inclusive' : 'Tax exclusive'
                }}</span>
            </label>
        </div>
        <div
            class="w-full min-w-0 max-w-full overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700"
        >
            <table class="w-full min-w-[900px] table-fixed text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 dark:bg-slate-800">
                    <tr>
                        <th style="width: 25%">Product *</th>
                        <th style="width: 12%">UOM *</th>
                        <th style="width: 9%">Qty Issued *</th>
                        <th style="width: 10%">Unit Price</th>
                        <th style="width: 10%">Tax</th>
                        <th style="width: 10%">Discount Type</th>
                        <th style="width: 8%">Discount</th>
                        <th style="width: 11%" class="text-right">
                            Line Total
                        </th>
                        <th style="width: 5%">
                            <span class="sr-only">Action</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr
                        v-for="row in rows"
                        :key="row.index"
                        class="align-top hover:bg-slate-50/50 dark:hover:bg-slate-800/50"
                    >
                        <td class="p-3">
                            <BaseSelect :disabled="form.processing"
                                v-model="row.line.product_id"
                                :options="getFilteredProducts(row.index)"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select product"
                                filter
                                :error="error(row.index, 'product_id')"
                                @change="emit('product-change', row.index)"
                            />
                            <span
                                v-if="row.line.stock_qty != null"
                                class="mt-1 block text-[10px] text-emerald-600"
                                >Stock: {{ money(row.line.stock_qty) }}
                                {{ row.line.unit_code }}</span
                            >
                            <BaseInput :disabled="form.processing"                                 v-model="row.line.notes"
                                placeholder="Remarks"
                                class="mt-2"
                                :error="error(row.index, 'notes')"
                            />
                        </td>
                        <td class="p-3">
                            <BaseSelect :disabled="form.processing"
                                v-model="row.line.units"
                                :options="unitOptions"
                                optionLabel="label"
                                optionValue="id"
                                placeholder="UOM"
                                :showClear="false"
                                class="uom-select"
                                :error="error(row.index, 'units')"
                            >
                                <template #value="{ value }"
                                    ><span
                                        class="whitespace-normal break-words leading-tight"
                                        >{{ unitLabel(value) }}</span
                                    ></template
                                >
                            </BaseSelect>
                        </td>
                        <td class="p-3">
                            <BaseInput :disabled="form.processing"                                 v-model="row.line.quantity_issued"
                                placeholder="0.00"
                                :error="error(row.index, 'quantity_issued')"
                                @blur="emit('quantity-blur', row.index)"
                            />
                            <span
                                v-if="
                                    row.line.product_id &&
                                    Number(row.line.quantity_issued) >
                                        Number(row.line.stock_qty || 0)
                                "
                                class="mt-1 block text-[10px] text-red-500"
                                >Exceeds stock</span
                            >
                        </td>
                        <td class="p-3">
                            <BaseInputNumber :disabled="form.processing"
                                v-model="row.line.unit_price"
                                :min="0"
                                :minFractionDigits="2"
                                :maxFractionDigits="4"
                                :error="error(row.index, 'unit_price')"
                            />
                        </td>
                        <td class="p-3">
                            <BaseSelect :disabled="form.processing"
                                v-model="row.line.tax_id"
                                :options="taxes"
                                optionLabel="label"
                                optionValue="id"
                                placeholder="No tax"
                                showClear
                                :error="error(row.index, 'tax_id')"
                            />
                        </td>
                        <td class="p-3">
                            <BaseSelect :disabled="form.processing"
                                v-model="row.line.discount_type"
                                :options="[
                                    { label: 'Amount', value: 'amount' },
                                    { label: 'Percentage', value: '%' },
                                ]"
                                optionLabel="label"
                                optionValue="value"
                                :error="error(row.index, 'discount_type')"
                            />
                        </td>
                        <td class="p-3">
                            <BaseInputNumber :disabled="form.processing"
                                v-model="row.line.discount"
                                :min="0"
                                :max="
                                    row.line.discount_type === '%'
                                        ? 100
                                        : undefined
                                "
                                :minFractionDigits="2"
                                :maxFractionDigits="2"
                                :error="error(row.index, 'discount') || (row.invalidDiscount ? 'Discount cannot exceed the line amount or 100%.' : '')"
                            />
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            <strong
                                class="block py-2 text-sm text-indigo-600"
                                >{{ money(row.total) }}</strong
                            ><span class="block text-[10px] text-slate-500"
                                >Taxable: {{ money(row.base) }}</span
                            ><span class="block text-[10px] text-slate-500"
                                >Tax: {{ money(row.tax) }}</span
                            >
                        </td>
                        <td class="p-3">
                            <button
                                type="button"
                                @click="emit('remove', row.index)"
                                :aria-label="'Remove item ' + (row.index + 1)"
                                class="rounded-lg p-2 text-red-600 hover:bg-red-50"
                            >
                                <i class="pi pi-trash" aria-hidden="true" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="9" class="p-8 text-center text-slate-500">
                            No issued items. Use Add Row to add an item.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            class="flex flex-wrap justify-end gap-6 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800"
        >
            <span>Discount: {{ money(totals.discount) }}</span
            ><span>Taxable: {{ money(totals.base) }}</span
            ><span>Tax: {{ money(totals.tax) }}</span
            ><strong>Total Amount: {{ money(totals.total) }}</strong>
        </div>
        <p v-if="form.errors.lines" class="text-sm text-red-600">
            {{ form.errors.lines }}
        </p>
        <p
            v-if="form.errors.bill_number || form.errors.billed_date"
            class="text-sm text-red-600"
        >
            {{ form.errors.bill_number || form.errors.billed_date }}
        </p>
    </section>
</template>

<style scoped>
.stock-items th {
    padding: 10px 5px;
}
.stock-items td {
    padding: 8px 5px;
    overflow-wrap: anywhere;
}
.stock-items :deep(.p-inputnumber),
.stock-items :deep(.p-select),
.stock-items :deep(input) {
    min-width: 0;
    width: 100%;
    max-width: 100%;
}
.stock-items :deep(.p-inputtext),
.stock-items :deep(.p-select-label) {
    padding: 8px 6px;
    font-size: 11px;
}
.stock-items :deep(.p-select-dropdown) {
    width: 22px;
    flex-shrink: 0;
}
.stock-items :deep(.uom-select .p-select-label) {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
}
</style>
