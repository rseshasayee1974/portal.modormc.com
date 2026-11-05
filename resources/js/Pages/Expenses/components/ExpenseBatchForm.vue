<!--
Author: ragul-onemodo
Created: 2026-11-05 09:59:23 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { APP_LOCALE } from "@/Utils/locale";
import { entityToday } from "@/Utils/entityDateTime";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseDatePicker from "@/Components/Base/BaseDatePicker.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseInputNumber from "@/Components/Base/BaseInputNumber.vue";

declare const route: any;
defineProps<{
    expenseTypes: any[];
    employees: any[];
    ledgers: any[];
    machines: any[];
    patrons: any[];
}>();
const emit = defineEmits<{ saved: [] }>();
const toast = useToast();
let nextKey = 0;
const newRow = () => ({
    key: ++nextKey,
    expense_type_id: null as number | null,
    ref_no: "",
    amount: null as number | null,
    vendor_id: null as number | null,
    machine_id: null as number | null,
    made_by_personnel_id: null as number | null,
    note: "",
});
const form = useForm({
    date: entityToday() as string | Date,
    paid_through: null as number | null,
    expenses: [newRow()],
});
const errors = computed(() => form.errors as Record<string, string>);
const total = computed(
    () =>
        form.expenses.reduce(
            (sum, row) => sum + Math.round(Number(row.amount || 0) * 100),
            0,
        ) / 100,
);
const money = (value: number) =>
    new Intl.NumberFormat(APP_LOCALE, {
        style: "currency",
        currency: "INR",
    }).format(value);
const fieldError = (index: number, field: string) =>
    errors.value[`expenses.${index}.${field}`];
const commonError = (field: string) =>
    Object.entries(errors.value).find(([key]) =>
        key.endsWith(`.${field}`),
    )?.[1];
const addRow = () => {
    if (!form.processing && form.expenses.length < 100)
        form.expenses.push(newRow());
};
const removeRow = (index: number) => {
    if (form.processing || form.expenses.length === 1) return;
    form.expenses.splice(index, 1);
    form.clearErrors();
};
const reset = () => {
    form.reset();
    form.expenses = [newRow()];
    form.clearErrors();
};
const submit = () => {
    if (form.processing) return;
    const count = form.expenses.length;
    form.transform((data) => {
        const date =
            data.date instanceof Date
                ? `${data.date.getFullYear()}-${String(data.date.getMonth() + 1).padStart(2, "0")}-${String(data.date.getDate()).padStart(2, "0")}`
                : data.date;
        return {
            expenses: data.expenses.map(({ key, ...row }) => ({
                ...row,
                date,
                paid_through: data.paid_through,
            })),
        };
    }).post(route("expenses.store"), {
        preserveScroll: true,
        onSuccess: () => {
            form.expenses = [newRow()];
            form.clearErrors();
            toast.add({
                severity: "success",
                summary: `${count} expense${count === 1 ? "" : "s"} saved and posted`,
                life: 2500,
            });
            emit("saved");
        },
    });
};
</script>

<template>
    <form
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        @submit.prevent="submit"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#E6EEF6] text-[#3B638E]"
                    ><i class="pi pi-wallet" aria-hidden="true"
                /></span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">
                        Record expenses
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Add multiple rows and save together. Enter a reference
                        per row, or leave blank for automatic numbering.
                    </p>
                </div>
            </div>
            <span
                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600"
                >{{ form.expenses.length }}
                {{ form.expenses.length === 1 ? "expense" : "expenses" }}</span
            >
        </div>

        <div
            class="grid grid-cols-1 gap-4 border-b border-slate-200 p-5 sm:grid-cols-2 lg:grid-cols-5"
        >
            <BaseDatePicker
                v-model="form.date"
                label="Expense Date"
                :error="commonError('date')"
                :disabled="form.processing"
                required
            />
            <BaseSelect
                v-model="form.paid_through"
                label="Payment Source / Ledger"
                :options="ledgers"
                optionLabel="label"
                optionValue="value"
                placeholder="Select ledger"
                :error="commonError('paid_through')"
                :disabled="form.processing"
                filter
                required
            />
            <!-- <p class="self-end pb-2 text-xs text-slate-500">The date and payment source apply to every row below.</p> -->
        </div>

        <div
            v-if="errors.expenses || errors.expense || errors.expense_type_id"
            role="alert"
            class="mx-5 mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700"
        >
            {{ errors.expenses || errors.expense || errors.expense_type_id }}
        </div>
        <div class="expense-rows divide-y divide-slate-200">
            <div
                v-for="(row, index) in form.expenses"
                :key="row.key"
                class="expense-row min-w-0 px-3 py-3"
            >
                <span
                    class="mt-8 text-center text-xs font-semibold text-slate-400"
                    >{{ index + 1 }}</span
                >
                <BaseInput
                    class="w-[60px]"
                    label="Ref #"
                    v-model="row.ref_no"
                    :aria-label="`Expense ${index + 1} ref #`"
                    placeholder="Auto if blank"
                    :error="fieldError(index, 'ref_no')"
                    :disabled="form.processing"
                />
                <BaseSelect
                    class="min-w-0"
                    label="Category"
                    v-model="row.expense_type_id"
                    :aria-label="`Expense ${index + 1} category`"
                    :options="expenseTypes"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Select category"
                    :error="fieldError(index, 'expense_type_id')"
                    :disabled="form.processing"
                    filter
                    required
                />
                <BaseInputNumber
                    class="min-w-0"
                    label="Amount"
                    v-model="row.amount"
                    :aria-label="`Expense ${index + 1} amount`"
                    :min="0.01"
                    :maxFractionDigits="2"
                    :error="fieldError(index, 'amount')"
                    :disabled="form.processing"
                    placeholder="0.00"
                    required
                />
                <BaseSelect
                    class="min-w-0"
                    label="Payee"
                    v-model="row.vendor_id"
                    :aria-label="`Expense ${index + 1} payee`"
                    :options="patrons"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Optional"
                    :error="fieldError(index, 'vendor_id')"
                    :disabled="form.processing"
                    filter
                    showClear
                />
                <BaseSelect
                    class="min-w-0"
                    label="Asset"
                    v-model="row.machine_id"
                    :aria-label="`Expense ${index + 1} asset`"
                    :options="machines"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Optional"
                    :error="fieldError(index, 'machine_id')"
                    :disabled="form.processing"
                    filter
                    showClear
                />
                <BaseSelect class="min-w-0" v-model="row.made_by_personnel_id" label="Made By"
                    :options="employees" optionLabel="label" optionValue="value" placeholder="Select employee"
                    :error="fieldError(index, 'made_by_personnel_id')" :disabled="form.processing" filter showClear />
                <BaseInput
                     class="min-w-0"
                    label="Notes"
                    v-model="row.note"
                    :aria-label="`Expense ${index + 1} notes`"
                    placeholder="What was this expense for?"
                    :error="fieldError(index, 'note')"
                    :disabled="form.processing"
                />
                <button
                    type="button"
                    @click="removeRow(index)"
                    :disabled="form.processing || form.expenses.length === 1"
                    :aria-label="`Remove expense row ${index + 1}`"
                    title="Remove row"
                    class="mt-6 flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 focus-visible:ring-2 focus-visible:ring-rose-500 disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <i class="pi pi-trash" aria-hidden="true" />
                </button>
            </div>
        </div>
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-4"
        >
            <button
                type="button"
                @click="addRow"
                :disabled="form.processing || form.expenses.length >= 100"
                class="inline-flex items-center gap-2 rounded-lg border border-[#CBD5E1] px-4 py-2 text-xs font-semibold text-[#3B638E] hover:bg-[#E6EEF6] focus-visible:ring-2 focus-visible:ring-[#3B638E] disabled:opacity-50"
            >
                <i class="pi pi-plus" aria-hidden="true" /> Add Row
            </button>
            <div class="text-right">
                <p class="text-xs text-slate-500">Total amount</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-slate-800">
                    {{ money(total) }}
                </p>
            </div>
        </div>
        <div
            class="flex justify-end gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4"
        >
            <button
                type="button"
                @click="reset"
                :disabled="form.processing"
                class="rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 disabled:opacity-50"
            >
                Reset
            </button>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 rounded-lg bg-[#3B638E] px-5 py-2.5 text-xs font-semibold text-white hover:bg-[#2A4869] focus-visible:ring-2 focus-visible:ring-[#3B638E] focus-visible:ring-offset-2 disabled:opacity-50"
            >
                <i
                    :class="
                        form.processing
                            ? 'pi pi-spinner pi-spin'
                            : 'pi pi-check'
                    "
                    aria-hidden="true"
                />{{
                    form.processing
                        ? "Saving…"
                        : `Save ${form.expenses.length === 1 ? "Expense" : "Expenses"}`
                }}
            </button>
        </div>
    </form>
</template>

<style scoped>
:deep(input.p-inputtext),
:deep(.p-select),
:deep(.p-datepicker-dropdown) {
    height: 36px;
    min-height: 36px;
    box-sizing: border-box;
}
:deep(.p-select-label) {
    display: flex;
    align-items: center;
    padding-block: 0;
}
.expense-row {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1.25fr) minmax(0, 0.8fr) minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, 1.1fr) minmax(0, 1.3fr) auto;
    gap: 0.5rem;
    align-items: start;
}
.expense-row :deep(label) {
    font-size: 0.7rem;
}
.expense-row :deep(.p-inputtext),
.expense-row :deep(.p-select-label) {
    padding-inline: 0.5rem;
    font-size: 0.75rem;
}
.expense-rows :deep(.p-inputtext),
.expense-rows :deep(.p-inputnumber),
.expense-rows :deep(.p-select) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}
.expense-rows :deep(.p-select-label) {
    min-width: 0;
}
</style>
