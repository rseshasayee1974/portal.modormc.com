<script setup lang="ts">
import { computed, watch, type PropType } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import type { Discount, DiscountOptions } from '../types';

const props = defineProps({
    journals: { type: Array as PropType<DiscountOptions['journals']>, required: true },
    accounts: { type: Array as PropType<DiscountOptions['accounts']>, required: true },
    partners: { type: Array as PropType<DiscountOptions['partners']>, required: true },
    discount: Object as PropType<Discount>,
    readonly: Boolean,
});
const emit = defineEmits<{ saved: []; cancel: [] }>();
const today = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
const values = () => ({
    primary_type: props.discount?.primary_type ?? 'Sales',
    value_type: props.discount?.value_type ?? 'amount',
    value: props.discount ? Number(props.discount.value) : null as number | null,
    amount: props.discount ? Number(props.discount.amount) : null as number | null,
    journal_id: props.discount?.journal_id ?? null as number | null,
    account_id: props.discount?.account_id ?? null as number | null,
    partner_id: props.discount?.partner_id ?? null as number | null,
    move_id: props.discount?.move_id ?? null as number | null,
    date: props.discount?.date?.slice(0, 10) ?? today(),
    note: props.discount?.note ?? '',
    status: props.discount?.status ?? 1,
});
const form = useForm(values());
watch(() => props.discount, () => { form.defaults(values()); form.reset(); form.clearErrors(); });
watch([() => form.value_type, () => form.value], () => {
    if (form.value_type === 'amount') form.amount = form.value;
});
const journalOptions = computed(() => props.journals.map(j => ({ value: j.id, label: `${j.voucher_number} · ${j.voucher_type}` })));
const reset = () => { form.reset(); form.clearErrors(); };
const submit = () => {
    if (props.readonly || form.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.discount) reset();
            emit('saved');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: props.discount ? 'Discount updated' : 'Discount created', timer: 1600, showConfirmButton: false });
        },
    };
    form.transform(data => ({ ...data, amount: data.value_type === 'amount' ? data.value : data.amount }));
    if (props.discount) form.put(route('discounts.update', props.discount.id), options);
    else form.post(route('discounts.store'), options);
};
</script>

<template>
    <form @submit.prevent="submit" class="rounded-lg border border-slate-200 bg-white p-5 space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-800">{{ discount ? `Discount #${discount.id}` : 'Create Discount' }}</h2>
                <p class="text-xs text-slate-500 mt-1">Record a sales or purchase discount for this plant.</p>
            </div>
            <span v-if="discount" class="text-xs text-slate-400">{{ discount.date?.slice(0, 10) }}</span>
        </div>
        <fieldset :disabled="readonly || form.processing" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <BaseSelect :disabled="readonly || form.processing" v-model="form.primary_type" label="Transaction type" :options="['Sales', 'Purchase']" :error="form.errors.primary_type" required />
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
                <input v-model="form.date" type="date" required class="w-full rounded border-slate-300 text-sm" />
                <p v-if="form.errors.date" class="text-xs text-red-600 mt-1">{{ form.errors.date }}</p>
            </div>
            <BaseSelect :disabled="readonly || form.processing" v-model="form.partner_id" label="Partner" :options="partners" optionLabel="legal_name" optionValue="id" filter placeholder="Select partner" :error="form.errors.partner_id" required />
            <BaseSelect :disabled="readonly || form.processing" v-model="form.journal_id" label="Journal entry" :options="journalOptions" optionLabel="label" optionValue="value" filter placeholder="Select journal" :error="form.errors.journal_id" required />
            <BaseSelect :disabled="readonly || form.processing" v-model="form.account_id" label="Discount account" :options="accounts" optionLabel="title" optionValue="id" filter showClear placeholder="Select ledger (optional)" :error="form.errors.account_id" />
            <BaseSelect :disabled="readonly || form.processing" v-model="form.value_type" label="Discount type" :options="[{ label: 'Fixed amount', value: 'amount' }, { label: 'Percentage', value: 'percent' }]" optionLabel="label" optionValue="value" :error="form.errors.value_type" required />
            <BaseInputNumber :disabled="readonly || form.processing" v-model="form.value" :label="form.value_type === 'percent' ? 'Discount percentage' : 'Discount value'" :suffix="form.value_type === 'percent' ? '%' : undefined" :min="0.01" :max="form.value_type === 'percent' ? 100 : undefined" :maxFractionDigits="2" :error="form.errors.value" required />
            <BaseInputNumber :disabled="readonly || form.processing" v-model="form.amount" label="Discount amount" :readonly="form.value_type === 'amount'" :min="0.01" :minFractionDigits="2" :maxFractionDigits="2" :hint="form.value_type === 'percent' ? 'Enter the calculated discount amount.' : undefined" :error="form.errors.amount" required />
            <BaseInputNumber :disabled="readonly || form.processing" v-model="form.move_id" label="Move reference (optional)" :min="1" :max="2147483647" :maxFractionDigits="0" :useGrouping="false" :error="form.errors.move_id" />
            <BaseSelect :disabled="readonly || form.processing" v-model="form.status" label="Status" :options="[{ label: 'Active', value: 1 }, { label: 'Inactive', value: 0 }]" optionLabel="label" optionValue="value" :error="form.errors.status" required />
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Note</label>
                <textarea v-model="form.note" rows="2" maxlength="10000" class="w-full rounded border-slate-300 text-sm" placeholder="Reason or supporting details"></textarea>
                <p v-if="form.errors.note" class="text-xs text-red-600">{{ form.errors.note }}</p>
            </div>
        </fieldset>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <BaseButton v-if="discount" label="Close" severity="secondary" variant="text" @click="emit('cancel')" />
            <BaseButton v-else label="Clear" severity="secondary" variant="text" @click="reset" :disabled="form.processing" />
            <BaseButton v-if="!readonly" type="submit" :label="discount ? 'Update Discount' : 'Save Discount'" icon="pi pi-check" variant="filled" :loading="form.processing" />
        </div>
    </form>
</template>
