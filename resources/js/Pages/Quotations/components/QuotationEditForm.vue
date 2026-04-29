<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { 
    ShoppingCartIcon, 
    TrashIcon, 
    PlusIcon, 
    CalculatorIcon,
    CalendarIcon,
    UserIcon,
    MapPinIcon
} from '@heroicons/vue/24/outline';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

interface QuotationItemPayload {
    id?: number | null;
    mix_design_id: number | null;
    quantity: number;
    tax_id: number | null;
    rate: number;
    uom_id: null | number;
    tax_amount: number;
    untaxed_amount: number;
    amount_total: number;
}

const props = defineProps<{
    quotation: any;
    patrons: { id: number; legal_name: string }[];
    sites: { id: number; name: string }[];
    mixDesigns: { id: number; title: string; code?: string; rate?: number }[];
    taxes: { id: number; title?: string; tax_name?: string; rate?: number; tax_rate?: number }[];
    unitOptions?: { id: number; unit_code: string }[];
}>();

const emit = defineEmits<{
    (e: 'updated'): void;
}>();

const isLocked = computed(() => [2, 3].includes(Number(props.quotation.status)));

const statusOptions = [
    { label: 'Draft', value: 0 },
    { label: 'Sent', value: 1 },
    { label: 'Accepted', value: 2 },
    { label: 'Rejected', value: 3 },
];

const form = useForm({
    patron_id: props.quotation.patron_id ?? null,
    site_id: props.quotation.site_id ?? null,
    quote_date: props.quotation.quote_date ? String(props.quotation.quote_date).substring(0, 10) : new Date().toISOString().substring(0, 10),
    validity_date: props.quotation.validity_date ? String(props.quotation.validity_date).substring(0, 10) : null,
    status: Number(props.quotation.status ?? 0),
    adjustment: Number(props.quotation.adjustment || 0),
    // Header totals
    amount_untaxed: Number(props.quotation.amount_untaxed || 0),
    amount_tax: Number(props.quotation.tax_amount || 0),
    amount_total: Number(props.quotation.amount_total || 0),
    items: (props.quotation.items || []).map((item: any) => ({
        id: item.id ?? null,
        mix_design_id: item.mix_design_id ?? null,
        uom_id: item.uom_id ?? null,
        quantity: Number(item.quantity || 0),
        tax_id: item.tax_id ?? null,
        rate: Number(item.rate || 0),
        tax_amount: Number(item.tax_amount || 0),
        untaxed_amount: Number(item.untaxed_amount || 0),
        amount_total: Number(item.amount_total || 0),
    })) as QuotationItemPayload[],
});

const patronOptions = computed(() => props.patrons.map((p) => ({ label: p.legal_name, value: p.id })));
const siteOptions = computed(() => props.sites.map((s) => ({ label: s.name, value: s.id })));
const unitOptions = computed(() => (props.unitOptions || []).map(u => ({ label: u.unit_code, value: u.id })));
const mixDesignOptions = computed(() =>
    props.mixDesigns.map((p) => ({ label: `${p.title}${p.code ? ` (${p.code})` : ''}`, value: p.id }))
);
const taxOptions = computed(() =>
    props.taxes.map((t) => ({
        label: `${t.tax_name ?? t.title ?? 'Tax'} `,
        value: t.id,
        rate: Number(t.tax_rate ?? t.rate ?? 0),
    }))
);

const calculateTotals = () => {
    let totalUntaxed = 0;
    let totalTax = 0;

    form.items.forEach(item => {
        const rate = Number(item.rate || 0);
        const qty = Number(item.quantity || 0);
        const untaxed = rate * qty;
        
        const tax = props.taxes.find(t => t.id === item.tax_id);
        const taxRate = tax ? Number(tax.tax_rate ?? tax.rate ?? 0) : 0;
        const lineTax = (untaxed * taxRate) / 100;

        item.untaxed_amount = Number(untaxed.toFixed(2));
        item.tax_amount = Number(lineTax.toFixed(2));
        item.amount_total = Number((untaxed + lineTax).toFixed(2));

        totalUntaxed += untaxed;
        totalTax += lineTax;
    });

    form.amount_untaxed = Number(totalUntaxed.toFixed(2));
    form.tax_amount = Number(totalTax.toFixed(2));
    form.amount_tax = Number(totalTax.toFixed(2));
    form.amount_total = Number((totalUntaxed + totalTax + Number(form.adjustment || 0)).toFixed(2));
};

watch(() => [form.items, form.adjustment], calculateTotals, { deep: true, immediate: true });

const formatDate = (date: string | null) => {
    if (!date) return '--';
    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return '--';
    return parsed.toLocaleString('en-IN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function createNewItem(): QuotationItemPayload {
    return {
        id: null,
        mix_design_id: null,
        tax_id: null,
        uom_id: null,
        quantity: 1,
        rate: 0,
        tax_amount: 0,
        untaxed_amount: 0,
        amount_total: 0,
    };
}

const addItem = () => {
    form.items.push(createNewItem());
};

const removeItem = (index: number) => {
    if (form.items.length === 1) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'At least one item is required.', showConfirmButton: false, timer: 2500 });
        return;
    }
    form.items.splice(index, 1);
};

const onMixDesignChange = (index: number) => {
    const item = form.items[index];
    const design = props.mixDesigns.find((p) => p.id === item.mix_design_id);
    if (design) {
        if (!item.rate) item.rate = Number(design.rate || 0);
        if (!item.uom_id && (design as any).unit_id) item.uom_id = (design as any).unit_id;
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        quote_date: data.quote_date ? new Date(data.quote_date).toISOString().substring(0, 10) : null,
        validity_date: data.validity_date ? new Date(data.validity_date).toISOString().substring(0, 10) : null,
    })).put(route('quotations.update', props.quotation.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Quotation updated successfully.', showConfirmButton: false, timer: 2500 });
            emit('updated');
        },
    });
};
</script>

<template>
    <div class="  ">
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid grid-cols-12 gap-4">
                <BaseSelect
                    v-model="form.patron_id"
                    :options="patronOptions"
                    optionLabel="label"
                    optionValue="value"
                    label="Customer"
                    placeholder="Select customer"
                    filter
                    class="col-span-12 md:col-span-3"
                    :error="form.errors.patron_id"
                />
                <BaseSelect
                    v-model="form.site_id"
                    :options="siteOptions"
                    optionLabel="label"
                    optionValue="value"
                    label="Project Site"
                    placeholder="Select site"
                    filter
                    class="col-span-12 md:col-span-3"
                    :error="form.errors.site_id"
                />
                <BaseDatePicker
                    v-model="form.quote_date"
                    label="Quotation Date"
                    class="col-span-12 md:col-span-2"
                    :error="form.errors.quote_date"
                />
                <BaseDatePicker
                    v-model="form.validity_date"
                    label="Validity Date"
                    class="col-span-12 md:col-span-2"
                    :error="form.errors.validity_date"
                    :disabled="isLocked"
                />
                <BaseSelect
                    v-model="form.status"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    label="Status"
                    class="col-span-12 md:col-span-2"
                    :error="form.errors.status"
                    :disabled="isLocked"
                />
            </div>

            <div v-if="isLocked" class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-md flex items-center gap-3 text-sm">
                <i class="pi pi-lock"></i>
                <span v-if="Number(props.quotation.status) === 2">This quotation has been <strong>Accepted</strong> and can no longer be modified.</span>
                <span v-else>This quotation has been <strong>Rejected</strong> and can no longer be modified.</span>
            </div>

            <div class="overflow-x-auto overflow-hidden rounded-md border border-slate-100 bg-white">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead class="bg-slate-50/80 border-y border-slate-100 uppercase tracking-[0.15em] text-[9.5px] font-semibold text-slate-400">
                        <tr>
                            <th class="px-3 py-3" style="width: 420px;">Mix Design</th>
                            <th class="px-3 py-3 text-center" style="width: 120px;">UOM</th>
                            <th class="px-3 py-3 text-center" style="width: 150px;">Qty</th>
                            <th class="px-3 py-3 text-center" style="width: 170px;">Rate</th>
                            <th class="px-3 py-3 text-center" style="width: 170px;">Tax</th>
                            <th class="px-3 py-3 text-right" style="width: 170px;">Line Amount</th>
                            <th class="px-1 py-1" style="width: 50px;">
                                <button v-if="!isLocked" type="button" @click="addItem" class="text-indigo-600 font-bold text-[10px] uppercase hover:text-indigo-700 flex items-center gap-1">
                                                <PlusIcon class="w-5 h-5 m-2 border-1 shadow-sm  hover:bg-indigo-500 bg-indigo-300 border-gray-400 rounded" />
                                            </button>
                                <!-- <BaseButton label="" class="bg-indigo-700" icon="pi pi-plus" variant="text" @click="addItem" /> -->
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(item, index) in form.items" :key="item.id ?? index" class="hover:bg-slate-50/50 transition-colors text-[13px]">
                            <td class="p-2">
                                <BaseSelect
                                    v-model="item.mix_design_id"
                                    :options="mixDesignOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Mix Design"
                                    filter
                                    :error="form.errors[`items.${index}.mix_design_id`]"
                                    @update:modelValue="onMixDesignChange(index)"
                                    :disabled="isLocked"
                                />
                            </td>
                            <td class="p-2">
                                <BaseSelect
                                    v-model="item.uom_id"
                                    :options="unitOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="UOM"
                                    filter
                                    :disabled="isLocked"
                                />
                            </td>
                            <td class="p-2">
                                <BaseInputNumber
                                    v-model="item.quantity"
                                    :minFractionDigits="2"
                                    :error="form.errors[`items.${index}.quantity`]"
                                    :disabled="isLocked"
                                />
                            </td>
                            <td class="p-2">
                                <BaseInputNumber
                                    v-model="item.rate"
                                    :minFractionDigits="2"
                                    :error="form.errors[`items.${index}.rate`]"
                                    :disabled="isLocked"
                                />
                            </td>
                            <td class="p-2">
                                <BaseSelect
                                    v-model="item.tax_id"
                                    :options="taxOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="None"
                                    clearable
                                    :disabled="isLocked"
                                />
                            </td>
                            <td class="p-2 text-right font-mono font-semibold text-slate-800">
                                {{ Number(item.amount_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                            </td>
                            <td class="p-2 text-center" v-if="!isLocked">
                                <button type="button" class="text-slate-300 hover:text-rose-500 transition-colors" @click="removeItem(index)">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                            <td v-else></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-end gap-8">
                <div class="text-[10px] text-slate-400 space-y-1">
                    <div v-if="quotation.creator" class="flex items-center gap-2">
                        <UserIcon class="w-3 h-3" />
                        <span>Created by <span class="font-bold text-slate-600">{{ quotation.creator.username || quotation.creator.name }}</span> on {{ formatDate(quotation.created_at) }}</span>
                    </div>
                    <div v-if="quotation.modifier" class="flex items-center gap-2">
                        <CalendarIcon class="w-3 h-3" />
                        <span>Last modified by <span class="font-bold text-slate-600">{{ quotation.modifier.username || quotation.modifier.name }}</span> on {{ formatDate(quotation.updated_at) }}</span>
                    </div>
                </div>

                <div class="w-full md:w-96 bg-white border border-slate-100 rounded-md p-4 space-y-3">
                      <div class="flex justify-between items-center text-[12px] font-medium text-slate-600">
                                    <span>Subtotal (Untaxed)</span>
                                    <span class="font-bold">₹ {{ Number(form.amount_untaxed).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                    <div class="flex justify-between items-center text-[12px] font-medium text-slate-600 mb-2">
                        <span>Total Taxes (+)</span>
                        <span class="font-bold">₹ {{ Number(form.amount_tax || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                    </div>

                    <div class="flex justify-between items-center gap-3">
                        <span class="text-[11px] font-semibold text-slate-700 uppercase tracking-widest">Adjustment</span>
                        <BaseInputNumber v-model="form.adjustment" :minFractionDigits="2" class="w-28" />
                    </div>

                    <div class="flex justify-between items-between">
                                        <span class="text-[13px] font-semibold text-indigo-600  tracking-[0.15em]">Grand Total</span>
                                        <span class="text-lg font-black text-slate-900 tracking-tighter">
                                            ₹ {{ Number(form.amount_total).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </span>
                                   
                                </div>
                </div>
            </div>

            <div class="flex justify-end" v-if="!isLocked">
                <BaseFormActions
                    label="Update Quotation"
                    :loading="form.processing"
                    @submit="submit"
                    @reset="form.reset()"
                    cancelLabel="Revert"
                />
            </div>
        </form>
    </div>
</template>
