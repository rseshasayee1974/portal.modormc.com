<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import axios from 'axios';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { PlusCircleIcon, DocumentTextIcon, TrashIcon, PlusIcon, CalculatorIcon } from '@heroicons/vue/24/outline';
import RecipePopover from '@/Components/Base/RecipePopover.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import { calculateLineItemTotals } from '@/composables/useLineItemCalculation';

const props = withDefaults(defineProps<{
    patrons?: any[];
    sites?: any[];
    quotations?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    concretePumpOptions?: any[];
    taxes?: any[];
    pumpTypeOptions?: { label: string; value: string }[];
    pumpRates?: any[];
}>(), {
    patrons: () => [],
    sites: () => [],
    quotations: () => [],
    mixDesigns: () => [],
    salesExecutives: () => [],
    concretePumpOptions: () => [],
    taxes: () => [],
    pumpTypeOptions: () => [],
    pumpRates: () => [],
});

const form = useForm({
    prefix: null as string | null,
    reference: null as string | null,
    customer_po_reference: null as string | null,
    quotation_id: null as number | null,
    patron_id: null as number | null,
    status: 0 as number | null,
    site_id: null as number | null,
    sales_executive_id: null as number | null,
    is_tax_inclusive: false,
    order_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        { mix_design_id: null as number | null, quantity: null as number | null, rate: null as number | null, tax_id: null as number | null, tax_amount: 0, concrete_pump: null as number | null, pump_rate: 0, pump_rates: [] }
    ] as Array<{ mix_design_id: number | null, quantity: number | null, rate: number | null, tax_id: number | null, tax_amount: number, concrete_pump: number | null, pump_rate: number, pump_rates: any[] }>,
});

// Watch quotation selection to auto-fill patron, site, and sales executive
watch(() => form.quotation_id, (newVal) => {
    excludedMixDesignPumpRates.value = [];
    if (newVal) {
        const quote = props.quotations.find((q) => Number(q.id) === Number(newVal));
        if (quote) {
            form.patron_id = quote.patron_id;
            form.site_id = quote.site_id;
            form.sales_executive_id = quote.sales_executive_id;
            form.is_tax_inclusive = quote.is_tax_inclusive ? true : false;
            const quoteItems = quote.items || [];
            form.items = quoteItems.map((item: any) => {
                const savedPumpRate = (item.pump_rates || item.pumpRates || []).find((pr: any) => Number(pr.pump_rate) > 0);
                return {
                    mix_design_id: item.mix_design_id,
                    quantity: Number(item.quantity),
                    rate: Number(item.rate),
                    tax_id: item.tax_id ?? null,
                    tax_amount: Number(item.tax_amount ?? 0),
                    concrete_pump: savedPumpRate ? Number(savedPumpRate.concrete_pump) : null,
                    pump_rate: savedPumpRate ? Number(savedPumpRate.pump_rate) : 0,
                    pump_rates: [],
                };
            });
        }
    } else {
        form.patron_id = null;
        form.site_id = null;
        form.sales_executive_id = null;
        form.is_tax_inclusive = false;
        form.items = [{ mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, concrete_pump: null, pump_rate: 0, pump_rates: [] }];
    }
});

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
    const resolved = resolvePumpRatesLocally(form.patron_id, form.site_id);
    
    if (item.concrete_pump) {
        const matched = resolved.find((r: any) => String(r.concrete_pump) === String(item.concrete_pump));
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
            item.concrete_pump = Number(matched.concrete_pump);
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

watch([() => form.patron_id, () => form.site_id], resolveAllItemsPumpRates);

const addItem = () => {
    const newItem = { mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, concrete_pump: null, pump_rate: 0, pump_rates: [] };
    resolveItemPumpRate(newItem, true);
    form.items.push(newItem);
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

// Filter sites by selected patron
const filteredSites = computed(() => {
    return (props.sites || []).filter((s: any) => {
        if (!s) return false;
        return !form.patron_id || (Array.isArray(s.patron_id) ? s.patron_id.includes(form.patron_id) : s.patron_id === form.patron_id);
    });
});

const selectedQuotation = computed(() => {
    return props.quotations.find(q => Number(q.id) === Number(form.quotation_id));
});

const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));

const mixDesignOptions = computed(() => {
    return (props.mixDesigns || []).map(p => ({
        label: p.design_name ? `${p.design_name}${p.design_code ? ` (${p.design_code})` : ''}` : p.title || '',
        value: p.id
    }));
});

const taxOptions = computed(() => (props.taxes || []).map(t => ({
    label: t.tax_name ? `${t.tax_name} (${t.tax_rate}%)` : `Tax (${t.tax_rate}%)`,
    value: t.id
})));

// Watch form items and is_tax_inclusive status to dynamically update tax_amount
watch(() => [form.items, form.is_tax_inclusive], ([newItems, isTaxInclusive]) => {
    if (newItems) {
        (newItems as any).forEach((item: any) => {
            const qty = Number(item.quantity || 0);
            const rate = Number(item.rate || 0);
            const tax = props.taxes?.find(t => Number(t.id) === Number(item.tax_id));
            const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

            if (isTaxInclusive) {
                const total = qty * rate;
                item.tax_amount = Number((total - (total / (1 + taxRate / 100))).toFixed(2));
            } else {
                const untaxed = qty * rate;
                item.tax_amount = Number(((untaxed * taxRate) / 100).toFixed(2));
            }
        });
    }
}, { deep: true, immediate: true });

const excludedMixDesignPumpRates = ref<number[]>([]);

const uniqueSelectedMixDesignIds = computed(() => {
    const ids = new Set<number>();
    form.items.forEach(item => {
        if (item.mix_design_id && !excludedMixDesignPumpRates.value.includes(Number(item.mix_design_id))) {
            ids.add(Number(item.mix_design_id));
        }
    });
    // For quotation-linked, load items from quotation
    if (form.quotation_id) {
        const quote = props.quotations.find(q => Number(q.id) === Number(form.quotation_id));
        if (quote && quote.items) {
            quote.items.forEach((item: any) => {
                if (item.mix_design_id && !excludedMixDesignPumpRates.value.includes(Number(item.mix_design_id))) {
                    ids.add(Number(item.mix_design_id));
                }
            });
        }
    }
    return Array.from(ids);
});

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

const page = usePage();
const calculatedTotals = computed(() => {
    let subtotal = 0;
    let taxAmount = 0;

    const processItems = (itemsList: any[]) => {
        itemsList.forEach((item: any) => {
            const tax = props.taxes?.find(t => Number(t.id) === Number(item.tax_id));
            const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

            const res = calculateLineItemTotals({
                quantity: Number(item.quantity || 0),
                rate: Number(item.rate || 0),
                pump_rate: Number(item.pump_rate || 0),
                taxRate,
                isTaxInclusive: Boolean(form.is_tax_inclusive),
            });

            subtotal += res.materialUntaxed + res.pumpCharge;
            taxAmount += res.materialTax;
        });
    };

    if (form.quotation_id && selectedQuotation.value) {
        // Quotation-linked PO items
        processItems(selectedQuotation.value.items || []);
    } else {
        // Direct PO items
        processItems(form.items || []);
    }

    return {
        subtotal: Number(subtotal.toFixed(2)),
        tax: Number(taxAmount.toFixed(2)),
        total: Number((subtotal + taxAmount).toFixed(2))
    };
});

const onMixDesignChange = (index: number) => {
    const item = form.items[index];
    const design = props.mixDesigns?.find((p: any) => p.id === item.mix_design_id);
    if (design) {
        if (!item.rate) item.rate = Number(design.rate || 0);
        
        // Remove from excluded list if re-selected/changed
        const designId = Number(item.mix_design_id);
        excludedMixDesignPumpRates.value = excludedMixDesignPumpRates.value.filter(id => id !== designId);

        resolveItemPumpRate(item, true);
    }
};



// Quotation dropdown options with labels
const quotationOptions = computed(() => {
    // Filter out quotations that have an active sales order
    const list = props.quotations.filter((q) => !q.is_customer_po || Number(q.is_customer_po) !== 1);
    return [
        { label: 'None', value: null },
        ...list.map((q) => {
            const patronName = props.patrons.find((p) => Number(p.id) === Number(q.patron_id))?.legal_name || 'Unknown';
            return {
                label: q.reference ? `${q.reference} - ${patronName} (₹${Number(q.amount_total || 0).toLocaleString('en-IN')})` : `Draft - ${patronName}`,
                value: q.id,
            };
        }),
    ];
});

const submit = () => {
    if (!form.quotation_id) {
        // Direct Customer PO: validate items
        let hasError = false;
        form.clearErrors();
        
        if (!form.patron_id) {
            form.setError('patron_id', 'Customer is required.');
            hasError = true;
        }
        if (!form.site_id) {
            form.setError('site_id', 'Loading Site is required.');
            hasError = true;
        }

        form.items.forEach((item, idx) => {
            if (!item.mix_design_id) {
                form.setError(`items.${idx}.mix_design_id` as any, 'Mix Design is required.');
                hasError = true;
            }
            if (!item.quantity || Number(item.quantity) <= 0) {
                form.setError(`items.${idx}.quantity` as any, 'Quantity must be greater than 0.');
                hasError = true;
            }
            if (!item.rate || Number(item.rate) < 0) {
                form.setError(`items.${idx}.rate` as any, 'Rate cannot be negative.');
                hasError = true;
            }
        });

        if (hasError) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'Please fill the required fields in the form.',
                timer: 3000,
                showConfirmButton: false,
            });
            return;
        }
    }

    form.transform((data) => ({
        ...data,
        concrete_pump: null,
        pump_rate: 0,
        items: data.items.map((item: any) => ({
            ...item,
            pump_rates: item.concrete_pump 
                ? [{ concrete_pump: item.concrete_pump, pump_rate: item.pump_rate }]
                : []
        }))
    })).post(route('customer-po.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Customer PO created successfully.',
                timer: 1500,
                showConfirmButton: false,
            });
            form.reset();
            form.clearErrors();
            form.order_date = new Date().toISOString().split('T')[0];
        },
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
        <!-- Card Header -->
        <div class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="rounded-lg bg-indigo-100 dark:bg-indigo-950/50 p-1.5 text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-200 dark:ring-indigo-900/30">
                    <PlusCircleIcon class="h-4 w-4" />
                </div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Create Customer PO</h2>
            </div>
        </div>

<!-- Form Body -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-5 p-5">

    <!-- Customer -->
    <div>
        <BaseSelect
            v-model="form.patron_id"
            :options="patrons"
            optionLabel="legal_name"
            optionValue="id"
            filter
            label="Customer"
            placeholder="Select Customer"
            :disabled="!!form.quotation_id"
            :error="form.errors.patron_id"
        />

        <p
            v-if="form.quotation_id"
            class="mt-1 text-xs text-indigo-600"
        >
            Locked to quotation customer
        </p>
    </div>

    

    <!-- Unloading Site -->
    <div>
        <BaseSelect
            v-model="form.site_id"
            :options="filteredSites"
            optionLabel="name"
            optionValue="id"
            filter
            label="Unloading Site"
            placeholder="Select Site"
            :disabled="!!form.quotation_id"
            :error="form.errors.site_id"
        />

        <p
            v-if="form.quotation_id"
            class="mt-1 text-xs text-indigo-600"
        >
            Locked to quotation site
        </p>
    </div>
    <!-- Sales Executive -->
    <div>
        <BaseSelect
            v-model="form.sales_executive_id"
            :options="salesExecutiveOptions"
            optionLabel="label"
            optionValue="value"
            filter
            label="Sales Executive"
            placeholder="Select Sales Executive"
            :error="form.errors.sales_executive_id"
        />
    </div>


    <!-- Order Date -->
    <div>
        <BaseDatePicker
            v-model="form.order_date"
            label="Order Date"
            fluid
            hourFormat="24"
            :error="form.errors.order_date"
        />
    </div>

    <!-- Customer PO Reference -->
    <div>
        <BaseInput
            v-model="form.customer_po_reference"
            label="Customer PO Ref No"
            placeholder="Customer's PO / order reference"
            :error="form.errors.customer_po_reference"
        />
    </div>
         <div class="col-span-12 md:col-span-1">
                <BaseSelect
                    v-model="form.status"
                    :options="[
                        { label: 'Draft', value: 0 },
                        { label: 'Confirmed', value: 1 },
                        { label: 'Completed', value: 2 }
                    ]"
                    optionLabel="label"
                    optionValue="value"
                    label="Status"
                    placeholder="Select Status"
                    :error="form.errors.status"
                />
            </div>
    <!-- Mix Design Section -->
    <template v-if="!form.quotation_id">
        <div class="col-span-full  pt-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2">
                                                    <CalculatorIcon class="w-3.5 h-3.5" />

                    Estimation Details
                </h3>
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                        <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_create" class="peer hidden" />
                        <label for="is_tax_inclusive_po_create" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                    </div>
            </div>

            <div class="rounded-md border border-slate-200 shadow-sm overflow-visible">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-[10px] uppercase font-bold text-slate-500">
                            <th class="px-4 py-3 text-left w-64">Mix Design</th>
                            <th class="px-4 py-3 text-center w-24">QTY (m³)</th>
                            <th class="px-4 py-3 text-center w-32">Rate (₹)</th>
                            <th class="px-4 py-3 text-center w-40">Tax</th>
                            <th class="px-4 py-3 text-center w-40">Pump Type</th>
                            <th class="px-4 py-3 text-center w-32">Pump Rate (₹)</th>
                            <th class="px-4 py-3 text-right w-36">Total (Incl. Tax)</th>
                            <th class="px-4 py-3 w-12">
                                <button type="button" @click="addItem" class="text-indigo-600 font-bold text-[10px] uppercase hover:text-indigo-700 flex items-center gap-1">
                                    <PlusIcon class="w-5 h-5 m-1 border shadow-sm hover:bg-indigo-100 border-gray-400 rounded-md" />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="(item, idx) in form.items" :key="idx" class="group hover:bg-slate-50/50 transition-colors">
                            <td class="relative p-2 max-w-[260px] overflow-visible">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <BaseSelect
                                            v-model="item.mix_design_id"
                                            :options="mixDesignOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Search design..."
                                            filter
                                            @update:modelValue="onMixDesignChange(idx)"
                                            class="w-full"
                                        />
                                    </div>
                                    <!-- Info Button Popover -->
                                    <RecipePopover :mixDesignId="item.mix_design_id" :mixDesigns="props.mixDesigns" />
                                </div>
                            </td>
                            <td class="p-3">
                                <BaseInputNumber v-model="item.quantity" :min="0.001" :minFractionDigits="3" placeholder="0.00" />
                            </td>
                            <td class="p-3">
                                <BaseInputNumber v-model="item.rate" prefix="₹" :minFractionDigits="2" placeholder="0.00"/>
                            </td>
                              <td class="p-3">
                                <BaseSelect
                                    v-model="item.tax_id"
                                    :options="taxOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="None"
                                    clearable
                                />
                            </td>
                            <td class="p-2">
                                <BaseSelect
                                    v-model="item.concrete_pump"
                                    :options="props.concretePumpOptions || []"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Type"
                                    showClear
                                    @update:modelValue="resolveItemPumpRate(item, true)"
                                />
                            </td>
                            <td class="p-2">
                                <BaseInputNumber
                                    v-model="item.pump_rate"
                                    prefix="₹"
                                    :minFractionDigits="2"
                                />
                            </td>
                          
                            <td class="p-3 text-right font-bold text-slate-800 text-sm">
                                <span>₹ {{ calculateLineItemTotals({ quantity: Number(item.quantity || 0), rate: Number(item.rate || 0), pump_rate: Number(item.pump_rate || 0), taxRate: (props.taxes?.find(t => Number(t.id) === Number(item.tax_id))?.tax_rate || 0), isTaxInclusive: Boolean(form.is_tax_inclusive) }).amountTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                            </td>
                            <td class="p-3 text-center">
                                <button type="button" @click="removeItem(idx)" class="p-1.5 text-slate-300 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-all" :disabled="form.items.length === 1">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
    <template v-else>
        <div class="col-span-full border-t pt-3">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800 flex items-center gap-4">
                    Mix Design Items (Loaded from Quotation)
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                        <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_create_2" :disabled="!!form.quotation_id" class="peer hidden" />
                        <label for="is_tax_inclusive_po_create_2" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                    </div>
                </h3>
            </div>
            <div class="overflow-x-auto rounded-xl border border-indigo-50/50 bg-indigo-50/10 p-4">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-indigo-100 uppercase tracking-wider text-[10px] font-bold text-indigo-700">
                            <th class="p-2">Mix Design</th>
                            <th class="p-2 text-right">Quantity</th>
                            <th class="p-2 text-right">Rate</th>
                            <th class="p-2 text-right">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in selectedQuotation?.items" :key="item.id" class="border-b border-indigo-50/50 last:border-0 font-medium text-slate-700">
                            <td class="p-2 text-slate-900 font-semibold font-bold">
                                {{ props.mixDesigns.find(d => Number(d.id) === Number(item.mix_design_id))?.title || props.mixDesigns.find(d => Number(d.id) === Number(item.mix_design_id))?.design_name || item.mix_design?.design_name || item.mix_design?.title || '-' }}
                            </td>
                            <td class="p-2 text-right font-mono">{{ Number(item.quantity).toFixed(3) }} m³</td>
                            <td class="p-2 text-right font-mono">₹{{ Number(item.rate).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                            <td class="p-2 text-right font-mono font-bold text-indigo-900">₹{{ Number(item.amount_total || (item.quantity * item.rate)).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </template>



    <!-- ── Pump Rates per Mix Design ── -->
    <!-- <div v-if="uniqueSelectedMixDesignIds.length && pumpTypeOptions && pumpTypeOptions.length" class="col-span-full mt-4 rounded-xl border border-indigo-200 bg-indigo-100/30 p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="text-[11px] font-black text-indigo-700 uppercase tracking-[0.18em]">⚙ Operation Charges per Mix Design</span>
            <span class="text-[10px] text-indigo-500 font-medium">(enter rate per m³ for each operation type)</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 md:grid-cols-3 gap-3 w-full items-start">
            <div 
                v-for="designId in uniqueSelectedMixDesignIds" 
                :key="designId" 
                class="w-full flex flex-col"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-800 uppercase tracking-wider border border-indigo-100/50 shadow-sm">
                        {{ props.mixDesigns.find(d => Number(d.id) === Number(designId))?.title || props.mixDesigns.find(d => Number(d.id) === Number(designId))?.design_name || '-' }}
                    </span>
                    <button 
                        type="button" 
                        @click="removePumpRatesForDesign(designId)"
                        class="text-rose-500 hover:text-rose-700 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 hover:underline mr-1"
                    >
                        <TrashIcon class="w-3.5 h-3.5" /> Remove
                    </button>
                </div>

                <div class="w-full rounded-lg border border-indigo-100 bg-white overflow-hidden shadow-sm">
                    <table class="w-full text-xs table-fixed">
                        <thead>
                            <tr class="bg-indigo-50/60 border-b border-indigo-100 text-[10px] uppercase font-bold text-indigo-700 tracking-wider">
                                <th class="px-4 py-2.5 text-left w-1/2">Operation Type</th>
                                <th class="px-4 py-2.5 text-right w-1/2">Rate (₹ / m³)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-50/50">
                            <template v-for="item in form.items" :key="String(item.mix_design_id) + '-cpo-pr'">
                                <template v-if="Number(item.mix_design_id) === Number(designId)">
                                    <tr v-for="pr in item.pump_rates" :key="pr.concrete_pump" class="hover:bg-indigo-50/20 transition-colors p-1">
                                        <td class="px-4 py-0 font-medium text-slate-700 truncate">{{ props.pumpTypeOptions?.find(opt => String(opt.value) === String(pr.concrete_pump))?.label || pr.concrete_pump }}</td>
                                        <td class="px-1 py-3 text-right">
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

    <!-- Totals & Summary Block -->
    <div class="col-span-full flex flex-col md:flex-row justify-between items-start gap-8 mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
        <!-- Notes / Terms on the left -->
        <div class="w-full md:flex-1 space-y-2">
            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest pl-1">Internal Notes / Terms</label>
            <textarea v-model="form.notes" placeholder="Specify any additional conditions..." class="w-full h-32 rounded-2xl border-slate-200 text-sm focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 transition-all p-4" />
        </div>

        <!-- Totals Card -->
        <div class="w-full md:w-96 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl p-4 space-y-3 shadow-sm">
            <div class="flex justify-between items-center text-[12px] font-medium text-slate-600 dark:text-slate-400">
                <span>Subtotal (Untaxed)</span>
                <span class="font-bold">₹ {{ calculatedTotals.subtotal.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
            </div>
            <div class="flex justify-between items-center text-[12px] font-medium text-slate-600 dark:text-slate-400 mb-2">
                <span>Total Taxes (+)</span>
                <span class="font-bold">₹ {{ calculatedTotals.tax.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
            </div>
            <div class="flex justify-between items-center border-t border-slate-100 dark:border-slate-800 pt-3">
                <span class="text-[13px] font-semibold text-indigo-600 dark:text-indigo-400 tracking-[0.15em]">Grand Total</span>
                <span class="text-lg font-black text-slate-900 dark:text-white tracking-tighter">
                    ₹ {{ calculatedTotals.total.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                </span>
            </div>
        </div>
    </div>

</div>

        <!-- Action Button -->
        <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30 px-4 py-3">
            <Button
                label="Create Customer PO"
                icon="pi pi-check"
                :loading="form.processing"
                @click="submit"
                class="p-button-indigo"
            />
        </div>
    </div>
</template>

<style scoped>
</style>
