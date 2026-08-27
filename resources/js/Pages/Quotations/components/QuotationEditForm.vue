<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
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
import RecipePopover from '@/Components/Base/RecipePopover.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { calculateLineItemTotals } from '@/composables/useLineItemCalculation';

interface QuotationItemPayload {
    id?: number | null;
    mix_design_id: number | null;
    quantity: number;
    tax_id: number | null;
    rate: number;
    uom_id: null | number;
    tax_amount: number | null;
    untaxed_amount: number;
    amount_total: number;
    concrete_pump?: number | string | null;
    pump_rate?: number;
    pump_rates: { concrete_pump: string | number; pump_rate: number }[];
}

const props = defineProps<{
    quotation: any;
    patrons: { id: number; legal_name: string }[];
    sites: { id: number; name: string }[];
    mixDesigns: { id: number; title: string; code?: string; rate?: number }[];
    taxes: { id: number; title?: string; tax_name?: string; rate?: number; tax_rate?: number }[];
    unitOptions?: { id: number; unit_code: string }[];
    salesExecutives?: { id: number; label: string; value: number }[];
    pumpTypeOptions?: { label: string; value: string }[];
    pumpRates?: any[];
}>();

const emit = defineEmits<{
    (e: 'updated'): void;
}>();

const page = usePage();
const customSettings = page.props.custom_settings as any;



const getDefaultValidityDate = (quoteDateStr: string): string | null => {
    if (!quoteDateStr) return null;
    const days = Number(customSettings?.batching?.quote_validity ?? 5);
    const date = new Date(quoteDateStr);
    date.setUTCDate(date.getUTCDate() + (isNaN(days) || days <= 0 ? 5 : days));
    return date.toISOString().substring(0, 10);
};

const isLocked = computed(() => [2, 3].includes(Number(props.quotation.status)));

const statusOptions = [
    { label: 'Draft', value: 0 },
    { label: 'Sent', value: 1 },
    { label: 'Accepted', value: 2 },
    { label: 'Rejected', value: 3 },
];
// console.log(props.quotation.concrete_pump,'dsa');
// Resolve legacy string values ('pump','manual','boom') to machine IDs
const resolveConcretePump = (raw: any) => {
    if (raw === null || raw === undefined || raw === '') return null;
    const asNum = Number(raw);
    if (!isNaN(asNum) && asNum > 0) return asNum; // already a machine ID
    // Legacy string: find matching option by label substring
    const match = (props.pumpTypeOptions || []).find(
        (o: any) => String(o.label).toLowerCase().includes(String(raw).toLowerCase())
    );
    return match ? match.value : null;
};

const form = useForm({
    patron_id: props.quotation.patron_id ?? null,
    site_id: props.quotation.site_id ?? null,
    sales_executive_id: props.quotation.sales_executive_id ?? null,
    is_tax_inclusive: props.quotation.is_tax_inclusive ? true : false,
    quote_date: props.quotation.quote_date ? String(props.quotation.quote_date).substring(0, 10) : new Date().toISOString().substring(0, 10),
    validity_date: props.quotation.validity_date ? String(props.quotation.validity_date).substring(0, 10) : null,
    notes: props.quotation.notes ?? '',
    status: Number(props.quotation.status ?? 0),
    adjustment: Number(props.quotation.adjustment || 0),
    // Header totals
    amount_untaxed: Number(props.quotation.amount_untaxed || 0),
    amount_tax: Number(props.quotation.tax_amount || 0),
    amount_total: Number(props.quotation.amount_total || 0),
    items: (props.quotation.items || []).map((item: any) => {
        return {
            id: item.id ?? null,
            mix_design_id: item.mix_design_id ?? null,
            uom_id: item.uom_id ?? null,
            quantity: Number(item.quantity || 0),
            tax_id: item.tax_id ?? null,
            rate: Number(item.rate || 0),
            tax_amount: Number(item.tax_amount || 0),
            untaxed_amount: Number(item.untaxed_amount || 0),
            amount_total: Number(item.amount_total || 0),
            concrete_pump: item.concrete_pump ?? null,
            pump_rate: Number(item.pump_rate || 0),
        };
    }) as QuotationItemPayload[],
});

// Auto-update validity_date when quote_date changes
watch(() => form.quote_date, (newVal) => {
    if (newVal) {
        form.validity_date = getDefaultValidityDate(newVal);
    }
});

const patronOptions = computed(() => props.patrons.map((p) => ({ label: p.legal_name, value: p.id })));
const siteOptions = computed(() => {
    return (props.sites || [])
        .filter((s: any) => {
            if (!s) return false;
            const isSelected = s.id === form.site_id;
            const matchesPatron = !form.patron_id || (Array.isArray(s.patron_id) ? s.patron_id.includes(form.patron_id) : s.patron_id === form.patron_id);
            return isSelected || matchesPatron;
        })
        .map((s: any) => ({ label: s.name, value: s.id }));
});

const pumpTypeOptions = computed(() =>
    (props.pumpTypeOptions || []).map((s: any) => ({ label: s.label, value: s.value }))
);
const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));
const unitOptions = computed(() => (props.unitOptions || []).map(u => ({ label: u.unit_code, value: u.id })));
const mixDesignOptions = computed(() =>
    props.mixDesigns.map((p) => ({ label: `${p.title}`, value: p.id }))
);
const taxOptions = computed(() =>
    props.taxes.map((t) => ({
        label: `${t.tax_name ?? t.title ?? 'Tax'} `,
        value: t.id,
        rate: Number(t.tax_rate ?? t.rate ?? 0),
    }))
);

const resolvePumpRatesLocally = (customerId: number | null, siteId: number | null) => {
    const activeRates = props.pumpRates || [];
    const scoredRates = activeRates.map(rate => {
        let score = 0;
        if (rate.customer_id !== null && Number(rate.customer_id) === Number(customerId)) {
            if (siteId !== null && Number(rate.site_id) === Number(siteId)) {
                score = 3;
            } else if (rate.site_id === null || rate.site_id === undefined) {
                score = 2;
            }
        } else if (rate.customer_id === null || rate.customer_id === undefined) {
            score = 1;
        }
        return { ...rate, score };
    }).filter(rate => rate.score > 0);

    const resolved: Record<string, any> = {};
    scoredRates.forEach(rate => {
        const type = rate.concrete_pump;
        if (!resolved[type] || resolved[type].score < rate.score) {
            resolved[type] = rate;
        }
    });

    return Object.values(resolved).sort((a: any, b: any) => b.score - a.score);
};

const resolveItemPumpRate = (item: any, isDropdownChange = false) => {
    if (!item.mix_design_id) return;
    const resolved = resolvePumpRatesLocally(form.patron_id, form.site_id);
    
    if (item.concrete_pump) {
        const matched = resolved.find((r: any) => String(r.concrete_pump).toLowerCase() === String(item.concrete_pump).toLowerCase());
        if (matched) {
            if (isDropdownChange) {
                item.pump_rate = Number(matched.rate || matched.pump_rate || 0);
            }
        } else {
            if (isDropdownChange) {
                item.pump_rate = 0;
            }
        }
    } else {
        if (resolved.length > 0) {
            const matched = resolved[0];
            item.concrete_pump = matched.concrete_pump;
            item.pump_rate = Number(matched.rate || matched.pump_rate || 0);
        } else {
            item.concrete_pump = null;
            item.pump_rate = 0;
        }
    }
};

const resolveAllItemsPumpRates = () => {
    for (const item of form.items) {
        item.concrete_pump = null;
        resolveItemPumpRate(item, true);
    }
};

// Auto pump rate resolution on Edit Form disabled per requirement (enable later if needed):
// watch([() => form.patron_id, () => form.site_id], resolveAllItemsPumpRates);

const excludedMixDesignPumpRates = ref<number[]>([]);

const uniqueSelectedMixDesignIds = computed(() => {
    const ids = new Set<number>();
    form.items.forEach(item => {
        if (item.mix_design_id && !excludedMixDesignPumpRates.value.includes(Number(item.mix_design_id))) {
            ids.add(Number(item.mix_design_id));
        }
    });
    return Array.from(ids);
});


console.log('quote',props);

const calculateTotals = () => {
    let totalUntaxed = 0;
    let totalTax = 0;

    form.items.forEach(item => {
        const tax = props.taxes.find(t => t.id === item.tax_id);
        const taxRate = tax ? Number(tax.tax_rate ?? tax.rate ?? 0) : 0;

        const res = calculateLineItemTotals({
            quantity: Number(item.quantity || 0),
            rate: Number(item.rate || 0),
            pump_rate: Number(item.pump_rate || 0),
            taxRate,
            isTaxInclusive: Boolean(form.is_tax_inclusive),
        });

        item.untaxed_amount = res.untaxedAmount;
        item.tax_amount = res.taxAmount;
        item.amount_total = res.amountTotal;

        totalUntaxed += res.untaxedAmount;
        totalTax += res.taxAmount;
    });

    form.amount_untaxed = Number(totalUntaxed.toFixed(2));
    form.tax_amount = Number(totalTax.toFixed(2));
    form.amount_tax = Number(totalTax.toFixed(2));
    form.amount_total = Number((totalUntaxed + totalTax + Number(form.adjustment || 0)).toFixed(2));
};

watch(() => [form.items, form.adjustment, form.is_tax_inclusive], calculateTotals, { deep: true, immediate: true });

// Reset site selection if it belongs to a different customer
watch(() => form.patron_id, (newPatronId) => {
    if (form.site_id) {
        const site = props.sites.find(s => s.id === form.site_id);
        if (site && site.patron_id && site.patron_id !== newPatronId) {
            form.site_id = null;
        }
    }
});

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
    const defaultUomId = props.unitOptions?.find(u => u.unit_code === 'CBM')?.id 
                      || props.unitOptions?.[0]?.id 
                      || null;

    return {
        id: null,
        mix_design_id: null,
        tax_id: null,
        uom_id: defaultUomId,
        quantity: 1,
        rate: 0,
        tax_amount: 0,
        untaxed_amount: 0,
        amount_total: 0,
        concrete_pump: null,
        pump_rate: 0,
    };
}

const addItem = () => {
    const newItem = createNewItem();
    resolveItemPumpRate(newItem, true);
    form.items.push(newItem);
};

const removeItem = (index: number) => {
    if (form.items.length === 1) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'At least one item is required.', showConfirmButton: false, timer: 1500 });
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
        
        // Remove from excluded list if re-selected/changed
        const designId = Number(item.mix_design_id);
        excludedMixDesignPumpRates.value = excludedMixDesignPumpRates.value.filter(id => id !== designId);

        resolveItemPumpRate(item, true);
    }
};

const removePumpRatesForDesign = (designId: number) => {
    excludedMixDesignPumpRates.value.push(Number(designId));
    // Zero out pump rates for this design in the items
    form.items.forEach(item => {
        if (Number(item.mix_design_id) === Number(designId)) {
            item.pump_rates.forEach(pr => {
                pr.pump_rate = 0;
            });
        }
    });
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        quote_date: data.quote_date ? new Date(data.quote_date).toISOString().substring(0, 10) : null,
        validity_date: data.validity_date ? new Date(data.validity_date).toISOString().substring(0, 10) : null,
        items: data.items.map((item: any) => ({
            ...item,
            concrete_pump: item.concrete_pump ?? null,
            pump_rate: Number(item.pump_rate || 0),
        }))
    })).put(route('quotations.update', props.quotation.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Quotation updated successfully.', showConfirmButton: false, timer: 1500 });
            emit('updated');
        },
    });
};

const sendEmail = () => {
    Swal.fire({
        title: 'Send Quotation?',
        text: 'This will send the quotation PDF to the customer\'s primary contact email.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, send it',
        confirmButtonColor: '#4f46e5'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('quotations.send-email', props.quotation.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Quotation sent successfully.', showConfirmButton: false, timer: 1500 });
                    emit('updated');
                },
            });
        }
    });
};
</script>

<template>
    <div class="  ">
        <form class="space-y-6" @submit.prevent="submit">
       <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
    <BaseSelect
        v-model="form.patron_id"
        :options="patronOptions"
        optionLabel="label"
        optionValue="value"
        label="Customer"
        placeholder="Select customer"
        filter
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
        :error="form.errors.site_id"
    />

    <BaseSelect
        v-model="form.sales_executive_id"
        :options="salesExecutiveOptions"
        optionLabel="label"
        optionValue="value"
        label="Sales Executive"
        placeholder="Select Sales Executive"
        filter
        :error="form.errors.sales_executive_id"
        :disabled="isLocked"
    />

    <BaseDatePicker
        v-model="form.quote_date"
        label="Quotation Date"
        :error="form.errors.quote_date"
    />

    <BaseDatePicker
        v-model="form.validity_date"
        label="Validity Date"
        :error="form.errors.validity_date"
        :disabled="isLocked"
    />

    <BaseSelect
        v-model="form.status"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        label="Status"
        placeholder="Select status"
        :error="form.errors.status"
        :disabled="isLocked"
    />
</div>

            <div v-if="isLocked" class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-md flex items-center gap-3 text-sm">
                <i class="pi pi-lock"></i>
                <span v-if="Number(props.quotation.status) === 2">This quotation has been <strong>Accepted</strong> and can no longer be modified.</span>
                <span v-else>This quotation has been <strong>Rejected</strong> and can no longer be modified.</span>
            </div>

            <div class="flex justify-between items-center mb-4 mt-6">
                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2">
                    <CalculatorIcon class="w-3.5 h-3.5" />
                    Line Items
                </h3>
                <div class="flex items-center gap-3 bg-slate-50 border border-slate-200/50 rounded-xl px-4 py-1.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                    <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_edit" :disabled="isLocked" class="peer hidden" />
                    <label for="is_tax_inclusive_edit" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border border-slate-100 bg-white">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead class="bg-slate-50/80 border-y border-slate-100 uppercase tracking-[0.15em] text-[9.5px] font-semibold text-slate-400">
                        <tr>
                            <th class="px-3 py-3" style="width: 320px;">Mix Design</th>
                            <th class="px-3 py-3 text-center" style="width: 120px;">Qty</th>
                            <th class="px-3 py-3 text-center" style="width: 100px;">UOM</th>
                            <th class="px-3 py-3 text-center" style="width: 140px;">Rate</th>
                            <th class="px-3 py-3 text-center" style="width: 140px;">Tax</th>
                            <th class="px-3 py-3 text-center" style="width: 180px;">Pump Type</th>
                            <th class="px-3 py-3 text-center" style="width: 140px;">Pump Rate</th>
                            <th class="px-3 py-3 text-right" style="width: 140px;">Amount</th>
                            <th class="px-1 py-1" style="width: 50px;">
                                <button v-if="!isLocked" type="button" @click="addItem" class="text-indigo-600 font-bold text-[10px] uppercase hover:text-indigo-700 flex items-center gap-1">
                                                <PlusIcon class="w-5 h-5 m-2 border-1 shadow-sm  hover:bg-indigo-500 bg-indigo-300 border-gray-400 rounded" />
                                            </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(item, index) in form.items" :key="item.id ?? index" class="hover:bg-slate-50/50 transition-colors text-[13px]">
                            <td class="relative p-2 max-w-[260px] overflow-visible">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
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
                                            class="w-full"
                                        />
                                    </div>
                                    <!-- Info Button Popover -->
                                    <RecipePopover :mixDesignId="item.mix_design_id" :mixDesigns="props.mixDesigns" />
                                </div>
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
                            <td class="p-2">
                                <BaseSelect
                                    v-model="item.concrete_pump"
                                    :options="props.pumpTypeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Type"
                                    showClear
                                    @update:modelValue="resolveItemPumpRate(item, true)"
                                    :disabled="isLocked"
                                />
                            </td>
                            <td class="p-2">
                                <BaseInputNumber
                                    v-model="item.pump_rate"
                                    :minFractionDigits="2"
                                    :disabled="isLocked"
                                />
                            </td>
                           
                            <td class="p-2 text-right font-bold text-slate-800 text-sm">
                                <span>₹ {{ Number(item.amount_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
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

            <!-- ── Pump Rates per Mix Design ── -->
            <!-- <div v-if="uniqueSelectedMixDesignIds.length && pumpTypeOptions && pumpTypeOptions.length" class="mt-4 rounded-xl border border-indigo-200 bg-indigo-100/30 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-4">
        <span class="text-[11px] font-black text-indigo-700 uppercase tracking-[0.18em]">⚙ Operation Charges per Mix Design</span>
    </div>

    <div class="flex flex-col lg:flex-row lg:flex-wrap gap-6 w-full items-start">
        <div 
            v-for="designId in uniqueSelectedMixDesignIds" 
            :key="designId" 
            class="w-full lg:w-[calc(50%-12px)] flex flex-col shrink-0"
        >
            <div class="mb-2 flex items-center justify-between">
                <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-800 uppercase tracking-wider border border-indigo-100/50 shadow-sm">
                    {{ props.mixDesigns.find(d => Number(d.id) === Number(designId))?.title || props.mixDesigns.find(d => Number(d.id) === Number(designId))?.design_name || '-' }}
                </span>
                <button 
                    v-if="!isLocked"
                    type="button" 
                    @click="removePumpRatesForDesign(designId)"
                    class="text-rose-500 hover:text-rose-700 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 hover:underline mr-1"
                >
                    <TrashIcon class="w-3.5 h-3.5" /> Remove
                </button>
            </div>

            <div class="w-full rounded-lg border border-indigo-100 bg-white overflow-hidden shadow-sm">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-indigo-50/60 border-b border-indigo-100 text-[10px] uppercase font-bold text-indigo-700 tracking-wider">
                            <th class="px-4 py-2.5 text-left">Operation Type</th>
                            <th class="px-4 py-2.5 text-right w-36">Rate (₹ / m³)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-indigo-50/50">
                        <template v-for="item in form.items" :key="item.mix_design_id + '-pumprow'">
                            <template v-if="Number(item.mix_design_id) === Number(designId)">
                                <tr v-for="(pr, pi) in item.pump_rates" :key="pr.concrete_pump" class="hover:bg-indigo-50/20 transition-colors">
                                    <td class="px-4 py-3 font-medium text-slate-700">{{ props.pumpTypeOptions?.find(opt => String(opt.value) === String(pr.concrete_pump))?.label || pr.concrete_pump }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <BaseInputNumber 
                                            v-model="pr.pump_rate" 
                                            prefix="₹" 
                                            :min="0" 
                                            class="!w-32 ml-auto" 
                                        />
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div> -->

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start mt-6">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest pl-1">Internal Notes / Terms</label>
                        <textarea v-model="form.notes" :disabled="isLocked" placeholder="Specify any additional conditions..." class="w-full h-32 rounded-2xl border-slate-200 text-sm focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 transition-all p-4" />
                    </div>


                </div>

                <div class="w-full md:w-96 bg-white border border-slate-100 rounded-md p-4 space-y-3 ml-auto">
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
                        <BaseInputNumber v-model="form.adjustment"                                     :disabled="isLocked"
 :minFractionDigits="2" class="w-28" />
                    </div>

                    <div class="flex justify-between items-between">
                                        <span class="text-[13px] font-semibold text-indigo-600  tracking-[0.15em]">Grand Total</span>
                                        <span class="text-lg font-black text-slate-900 tracking-tighter">
                                            ₹ {{ Number(form.amount_total).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </span>
                                   
                                </div>
                </div>
            </div>

            <div class="flex justify-between" v-if="!isLocked">
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
                <div class="flex items-center gap-3">
                    <BaseButton
                        label="Send Email"
                        icon="pi pi-envelope"
                        severity="info"
                        class="!bg-sky-600 hover:!bg-sky-700 !border-sky-600 !px-6 !h-10 text-xs font-bold !text-white uppercase tracking-wide shadow-md"
                        @click="sendEmail"
                        :disabled="form.processing"
                    />
                    <BaseFormActions
                        label="Update Quotation"
                        :loading="form.processing"
                        @submit="submit"
                        @reset="form.reset()"
                        cancelLabel="Revert"
                    />
                </div>
            </div>
        </form>
    </div>
</template>
