<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { entityToday } from '@/Utils/entityDateTime';
import { useForm } from '@inertiajs/vue3';

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    expenseTypes: any[];
    employees: any[];
    ledgers: any[];
    machines: any[];
    patrons: any[];
    expense?: any;
}>();
const emit = defineEmits<{ saved: [] }>();

const toast = useToast();


const form = useForm({
    ref_no: props.expense?.ref_no ?? '',
    expense_type_id: props.expense?.expense_type_id ?? null,
    paid_through: props.expense?.paid_through ?? null,
    amount: Number(props.expense?.amount ?? 0),
    date: props.expense?.date?.slice(0, 10) ?? entityToday(),
    vendor_id: props.expense?.vendor_id ?? null,
    machine_id: props.expense?.machine_id ?? null,
    made_by_personnel_id: props.expense?.made_by_personnel_id ?? null,
    note: props.expense?.note ?? '',
});


const submit = () => {
    if (form.processing) return;
    form.submit(props.expense ? 'put' : 'post', props.expense ? route('expenses.update', props.expense.id) : route('expenses.store'), {
        onSuccess: () => {
            form.reset();
            toast.add({ severity: 'success', summary: props.expense ? 'Expense and accounting updated' : 'Expense saved and posted', life: 1500 });
            emit('saved');
        },
    });
};

</script>

<template>
    <form class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" @submit.prevent="submit">
        <div class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#E6EEF6] text-[#3B638E]"><i class="pi pi-wallet" aria-hidden="true" /></span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">{{ expense ? 'Edit expense' : 'Record expense' }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ expense ? 'Update the expense details and save to update accounting.' : 'Enter expense details and save to post accounting.' }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 border-b border-slate-200 p-5 sm:grid-cols-2 lg:grid-cols-5">
            <BaseDatePicker v-model="form.date" label="Expense Date" :error="form.errors.date" :disabled="form.processing" required />
            <BaseSelect v-model="form.paid_through" label="Payment Source / Ledger" :options="ledgers" optionLabel="label" optionValue="value" placeholder="Select ledger" :error="form.errors.paid_through" :disabled="form.processing" filter required />
        </div>
        <p v-if="form.errors.expense" role="alert" class="mx-5 mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ form.errors.expense }}</p>
        <div class="expense-row px-3 py-4">
            <BaseInput class="w-[60px]" v-model="form.ref_no" label="Ref #" :error="form.errors.ref_no" :disabled="form.processing" :placeholder="expense ? 'Keep if blank' : 'Auto if blank'" />
            <BaseSelect class="min-w-0" v-model="form.expense_type_id" label="Category" :options="expenseTypes" optionLabel="label" optionValue="value" placeholder="Select category" :error="form.errors.expense_type_id" :disabled="form.processing" filter required />
            <BaseInputNumber class="min-w-0" v-model="form.amount" label="Amount" :min="0.01" :maxFractionDigits="2" placeholder="0.00" :error="form.errors.amount" :disabled="form.processing" required />
            <BaseSelect class="min-w-0" v-model="form.vendor_id" label="Payee" :options="patrons" optionLabel="label" optionValue="value" placeholder="Optional" :error="form.errors.vendor_id" :disabled="form.processing" filter showClear />
            <BaseSelect class="min-w-0" v-model="form.machine_id" label="Asset" :options="machines" optionLabel="label" optionValue="value" placeholder="Optional" :error="form.errors.machine_id" :disabled="form.processing" filter showClear />
            <BaseSelect class="min-w-0" v-model="form.made_by_personnel_id" label="Made By" :options="employees" optionLabel="label" optionValue="value" placeholder="Select employee" :error="form.errors.made_by_personnel_id" :disabled="form.processing" filter showClear />
            <BaseInput class="min-w-0" v-model="form.note" label="Notes" placeholder="What was this expense for?" :error="form.errors.note" :disabled="form.processing" />
        </div>
        <div class="border-t border-slate-200 px-5 py-4 text-right">
            <p class="text-xs text-slate-500">Total amount</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-slate-800">{{ new Intl.NumberFormat(APP_LOCALE, { style: 'currency', currency: 'INR' }).format(Number(form.amount || 0)) }}</p>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4">
            <button type="button" @click="form.reset(); form.clearErrors()" :disabled="form.processing" class="rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 disabled:opacity-50">Reset</button>
            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-[#3B638E] px-5 py-2.5 text-xs font-semibold text-white hover:bg-[#2A4869] focus-visible:ring-2 focus-visible:ring-[#3B638E] focus-visible:ring-offset-2 disabled:opacity-50">
                <i :class="form.processing ? 'pi pi-spinner pi-spin' : 'pi pi-check'" aria-hidden="true" />
                {{ form.processing ? 'Saving…' : expense ? 'Save Changes' : 'Save Expense' }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.expense-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1.25fr) minmax(0, .8fr) minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, 1.1fr) minmax(0, 1.3fr);
    gap: .5rem;
    align-items: start;
}
.expense-row :deep(label) { font-size: .7rem; }
.expense-row :deep(.p-inputtext),
.expense-row :deep(.p-select-label) { padding-inline: .5rem; font-size: .75rem; }
:deep(input.p-inputtext),
:deep(.p-select),
:deep(.p-datepicker-dropdown) {
    height: 36px;
    min-height: 36px;
    box-sizing: border-box;
}
.expense-row :deep(.p-inputtext),
.expense-row :deep(.p-inputnumber),
.expense-row :deep(.p-select) { width: 100%; min-width: 0; max-width: 100%; }
:deep(.p-select-label) { display: flex; align-items: center; padding-block: 0; min-width: 0; }
</style>
