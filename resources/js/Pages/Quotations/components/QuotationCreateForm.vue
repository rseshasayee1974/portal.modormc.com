<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
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
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import BaseSelectQuickAdd from '@/Components/Base/BaseSelectQuickAdd.vue';
import BaseAutoComplete from '@/Components/Base/BaseAutoComplete.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import axios from 'axios';

interface QuotationItemPayload {
    id?: number | null;
    mix_design_id: number | null;
    quantity: number;
    tax_id: number | null;
    rate: number;
    uom_id: number | null, // Added
    notes: null | string;
    // Calculated fields strictly for the new schema
    // tax_amount: number;
    untaxed_amount: number;
    amount_total: number;
    concrete_pump: number | null;
    pump_rate: number;
    pump_rates: { concrete_pump: string; pump_rate: number }[];
}

const props = withDefaults(defineProps<{
    patrons: { id: number; legal_name: string }[];
    sites: any[];
    unitOptions : {id: number, unit_code: string}[];
    mixDesigns: { id: number; title: string; code?: string; rate?: number; unit_id?: number }[];
    taxes: { id: number; tax_name?: string; tax_rate?: number }[];
    units?: { id: number; name: string }[]; // Falling back if missing
    instant_customer: number | boolean;
    salesExecutives?: { id: number; label: string; value: number }[];
    concretePumpOptions?: { label: string; value: number }[];
    pumpTypeOptions?: { label: string; value: string }[];
    pumpRates?: any[];
}>(), {
    pumpRates: () => [],
});
// console.log(props.taxes);
const isOpen = ref(true);

const page = usePage();
const customSettings = page.props.custom_settings as any;
const addPouringRatesToTotal = customSettings?.batching?.add_pouring_rates_to_total == 1;

const getDefaultValidityDate = (quoteDateStr: string) => {
    if (!quoteDateStr) return null;
    const days = Number(customSettings?.batching?.quote_validity ?? 5);
    const date = new Date(quoteDateStr);
    date.setUTCDate(date.getUTCDate() + (isNaN(days) || days <= 0 ? 5 : days));
    return date.toISOString().substring(0, 10);
};

const initialQuoteDate = new Date().toISOString().substring(0, 10);

const form = useForm({
    patron_id: null as number | null,
    site_id: null as number | null,
    sales_executive_id: null as number | null,
    new_site_name: '' as string,
    is_new_site: false,
    is_tax_inclusive: false,
    quote_date: initialQuoteDate,
    validity_date: getDefaultValidityDate(initialQuoteDate),
    notes: '' as string,
    status: 1,
    adjustment: 0,
    // Header totals
    amount_untaxed: 0,
    // tax_amount: 0,
    amount_tax: 0,
    amount_total: 0,
    items: [createNewItem()] as QuotationItemPayload[],
});

watch(() => form.quote_date, (newVal) => {
    if (newVal) {
        form.validity_date = getDefaultValidityDate(newVal);
    }
});

function createNewItem(): QuotationItemPayload {
    const defaultUomId = props.unitOptions?.find(u => u.unit_code === 'CBM')?.id 
                      || props.unitOptions?.[0]?.id 
                      || null;
                      
    const defaultTaxId = props.taxes?.[0]?.id || null;

    return {
        id: null,
        mix_design_id: null,
        quantity: 1,
        tax_id: null,
        uom_id: defaultUomId, 
        rate: 0,
        tax_amount: 0,
        notes: '',
        untaxed_amount: 0,
        amount_total: 0,
        concrete_pump: null,
        pump_rate: 0,
        pump_rates: [],
    };
}

// Options Computeds
const patronOptions = computed(() => props.patrons.map(p => ({ label: p.legal_name, value: p.id })));
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
const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));
const mixDesignOptions = computed(() => props.mixDesigns.map(p => ({ 
    label: `${p.title}${p.code ? ` (${p.code})` : ''}`, 
    value: p.id,
    price: p.rate 
})));
const unitOptions = computed(() => (props.unitOptions || []).map(u => ({ label: u.unit_code, value: u.id })));
const taxOptions = computed(() => props.taxes?.map(t => ({ label: t.tax_name, value: t.id })) || []);

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



const siteSuggestions = ref<any[]>([]);
const searchSites = (event: any) => {
    const query = (event.query || '').toString().toLowerCase();
    siteSuggestions.value = props.sites
        .filter(s => s.name.toLowerCase().includes(query) || (s.code && s.code.toLowerCase().includes(query)))
        .map(s => ({ label: `${s.name}${s.code ? ` [${s.code}]` : ''}`, value: Number(s.id) }));
};

/**
 * Core Calculation Engine
 * Synchronizes all line item totals and header aggregates
 */
const calculateTotals = () => {
    let totalUntaxed = 0;
    let totalTax = 0;

    form.items.forEach(item => {
        const rate = Number(item.rate || 0);
        const qty = Number(item.quantity || 0);
        const pumpRate = Number(item.pump_rate || 0);

        // Pump charge: flat rate when enabled, per-m³ when disabled
        const pumpCharge = addPouringRatesToTotal ? pumpRate : pumpRate * qty;
        
        // Find line tax rate
        const tax = props.taxes.find(t => t.id === item.tax_id);
        const taxRate = tax ? Number(tax.tax_rate ?? tax.rate ?? 0) : 0;

        let untaxed = 0;
        let lineTax = 0;
        let lineTotal = 0;

        if (addPouringRatesToTotal) {
            // Flat rate mode: Tax is calculated only on (qty * rate). Pump rate is added directly to total afterwards without tax.
            if (form.is_tax_inclusive) {
                const materialTotal = rate * qty;
                const materialTax = materialTotal - (materialTotal / (1 + taxRate / 100));
                lineTax = materialTax;
                untaxed = (materialTotal - materialTax) + pumpCharge;
                lineTotal = materialTotal + pumpCharge;
            } else {
                const materialUntaxed = rate * qty;
                const materialTax = (materialUntaxed * taxRate) / 100;
                lineTax = materialTax;
                untaxed = materialUntaxed + pumpCharge;
                lineTotal = materialUntaxed + materialTax + pumpCharge;
            }
        } else {
            // Per m³ mode: Pump charge is taxed alongside the mix rate.
            if (form.is_tax_inclusive) {
                lineTotal = rate * qty + pumpCharge;
                lineTax = lineTotal - (lineTotal / (1 + taxRate / 100));
                untaxed = lineTotal - lineTax;
            } else {
                untaxed = rate * qty + pumpCharge;
                lineTax = (untaxed * taxRate) / 100;
                lineTotal = untaxed + lineTax;
            }
        }

        // Update Item Internal State (for SQL Insertion)
        item.untaxed_amount = Number(untaxed.toFixed(2));
        item.tax_amount = Number(lineTax.toFixed(2));
        item.amount_total = Number(lineTotal.toFixed(2));

        totalUntaxed += untaxed;
        totalTax += lineTax;
    });

    form.amount_untaxed = Number(totalUntaxed.toFixed(2));
    form.tax_amount = Number(totalTax.toFixed(2)); // Both fields as per SQL
    form.amount_tax = Number(totalTax.toFixed(2));
    form.amount_total = Number((totalUntaxed + totalTax + Number(form.adjustment || 0)).toFixed(2));
};

// Deep watch for any changes in items, adjustment, or tax-inclusive status
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
    // if (!item.mix_design_id) return;
    const resolved = resolvePumpRatesLocally(form.patron_id, form.site_id);
    
    if (item.concrete_pump) {
        const matched = resolved.find((r: any) => String(r.concrete_pump) === String(item.concrete_pump));
        if (matched) {
            if (isDropdownChange) {
                item.pump_rate = Number(matched.rate || matched.pump_rate || 0);
            }
        } else {
            if (isDropdownChange) {
                // item.concrete_pump = null;
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

const onMixDesignChange = (index: number) => {
    const item = form.items[index];
    const design = props.mixDesigns.find(p => p.id === item.mix_design_id);
    if (design) {
        if (!item.rate) item.rate = Number(design.rate || 0);
        if (!item.uom_id && design.unit_id) item.uom_id = design.unit_id;
        
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

const addItem = () => {
    const newItem = createNewItem();
    resolveItemPumpRate(newItem, true);
    form.items.push(newItem);
};
const removeItem = (index: number) => {
    if (form.items.length > 1) form.items.splice(index, 1);
    else Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'At least one item required' });
};

const isCreatingSite = ref(false);
const quickAddSite = async () => {
    if (!form.new_site_name) return;
    isCreatingSite.value = true;
    try {
        const response = await axios.post(route('sites.store'), {
            name: form.new_site_name,
            type: 'unloading', // Default as per controller
            status: 'Active',
            is_active: true
        });
        
        // Site created, now we need to update the sites list and select it.
        // Since sites come from props, we might need a local ref copy of sites or just wait for Inertia reload if it happened.
        // However, SiteController@store redirects back, so props.sites will refresh.
        // To be safe and immediate, we'll try to find the site in the response if possible, 
        // or just rely on the controller's logic (if it returns JSON).
        
        // Let's assume SiteController was updated to return JSON for AJAX.
        // If not, we'll suggest refreshing or handle the redirect.
        
        Swal.fire({ icon: 'success', title: 'Site Created', text: `Site "${form.new_site_name}" is now available.`, timer: 1500, showConfirmButton: false });
        
        const newSiteId = response.data.site.id;
        
        form.is_new_site = false;
        form.new_site_name = '';
        
        // Reload only the sites prop and select the new one once updated
        router.reload({ 
            only: ['sites'],
            onSuccess: () => {
                form.site_id = newSiteId;
            }
        });
        
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Failed to create site' });
    } finally {
        isCreatingSite.value = false;
    }
};

const isCreatingPatron = ref(false);
const handleCreatePatron = async (name: string) => {
    isCreatingPatron.value = true;
    try {
        const response = await axios.post(route('patrons.store'), {
            legal_name: name,
            patron_type: ['Customer'],
            operational_status: 'active',
            status: true,
            displayed: true,
        });
        
        const newPatron = response.data.patron;
        
        Swal.fire({ 
            toast: true, 
            position: 'top-end', 
            icon: 'success', 
            title: `Customer "${name}" created.`, 
            showConfirmButton: false, 
            timer: 1500 
        });

        router.reload({
            only: ['patrons'],
            onSuccess: () => {
                form.patron_id = newPatron.id;
            }
        });
    } catch (error: any) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Failed to create customer' });
    } finally {
        isCreatingPatron.value = false;
    }
};

const isInstantCustomerEnabled = computed(() => Number(props.instant_customer) === 1);

const submit = () => {
    form.clearErrors();

    let hasErrors = false;

    // Header Validations
    if (!form.patron_id) {
        form.setError('patron_id', 'Customer is required.');
        hasErrors = true;
    }

    if (!form.is_new_site && !form.site_id) {
        form.setError('site_id', 'Project Site is required.');
        hasErrors = true;
    }

    if (form.is_new_site && !form.new_site_name.trim()) {
        form.setError('new_site_name', 'Site Name is required.');
        hasErrors = true;
    }

    // if (!form.sales_executive_id) {
    //     form.setError('sales_executive_id', 'Sales Executive is required.');
    //     hasErrors = true;
    // }

    if (!form.quote_date) {
        form.setError('quote_date', 'Quote Date is required.');
        hasErrors = true;
    }

   

    // Item Validations
    form.items.forEach((item, index) => {

        if (!item.mix_design_id) {
            form.setError(
                `items.${index}.mix_design_id`,
                'Mix Design is required.'
            );
            hasErrors = true;
        }

        if (!item.uom_id) {
            form.setError(
                `items.${index}.uom_id`,
                'UOM is required.'
            );
            hasErrors = true;
        }

        if (!item.quantity || Number(item.quantity) <= 0) {
            form.setError(
                `items.${index}.quantity`,
                'Quantity must be greater than zero.'
            );
            hasErrors = true;
        }

        if (!item.rate || Number(item.rate) <= 0) {
            form.setError(
                `items.${index}.rate`,
                'Rate must be greater than zero.'
            );
            hasErrors = true;
        }

    });

    if (form.items.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Quotation',
            text: 'Please add at least one line item.'
        });
        return;
    }

  if (hasErrors) {
    const first = Object.entries(form.errors).find(([_, value]) => value);

    Swal.fire({
        icon: 'warning',
        title: 'Validation Error',
        html: first
            ? `<b>${first[1]}</b>`
            : 'Please correct the highlighted fields.'
    });

    return;
}

    form.transform(data => ({
        ...data,
        site_id: data.is_new_site
            ? null
            : (data.site_id ? Number(data.site_id) : null),

        patron_id: data.patron_id
            ? Number(data.patron_id)
            : null,

        sales_executive_id: data.sales_executive_id
            ? Number(data.sales_executive_id)
            : null,

        concrete_pump: null,
        pump_rate: 0,

        quote_date: data.quote_date
            ? new Date(data.quote_date).toISOString().substring(0, 10)
            : null,

        validity_date: data.validity_date
            ? new Date(data.validity_date).toISOString().substring(0, 10)
            : null,

        items: data.items.map((item: any) => ({
            ...item,
            pump_rates: item.concrete_pump 
                ? [{ concrete_pump: item.concrete_pump, pump_rate: item.pump_rate }]
                : []
        }))
    }))
    .post(route('quotations.store'), {
        preserveScroll: true,

        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Quotation created successfully!'
            });

            form.reset();

            form.items = [createNewItem()];
            form.is_new_site = false;
        },
    });
};
</script>

<template>
    <div class="no-print quotation-card" :class="{ 'quotation-card--active': isOpen }">
        <!-- Premium Header -->
        <button @click="isOpen = !isOpen" class="w-full flex items-center justify-between p-5 hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-indigo-50 rounded-xl">
                    <ShoppingCartIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div class="text-left">
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">New Quotation</h2>
                    <p class="text-[10px] text-slate-400 font-medium tracking-tight">Generate professional estimates for customers</p>
                </div>
            </div>
            <BaseButton :icon="isOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" variant="text" size="small" />
        </button>

        <Transition name="slide-fade">
            <div v-show="isOpen" class="p-6 border-t border-slate-100 bg-white">
                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Form Header Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 items-end">
                        <BaseCreatableSelect 
                            v-if="isInstantCustomerEnabled"
                            v-model="form.patron_id" 
                            :options="patronOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            label="Customer" 
                            :error="form.errors.patron_id"
                            required 
                            placeholder="Select Customer" 
                            :creating="isCreatingPatron"
                            @create="handleCreatePatron"
                        />
                        <BaseSelect 
                            v-else
                            v-model="form.patron_id" 
                            :options="patronOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            label="Customer" 
                            required 
                            placeholder="Select Customer" 
                            filter
                            :error="form.errors.patron_id"

                        />
                        
                        <div class="relative">
                            <div v-if="!form.is_new_site">
                                <BaseSelectQuickAdd
                                    v-model="form.site_id" 
                                    :options="siteOptions" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    label="Unloading Site" 
                                    placeholder="Select Unloading Site" 
                                    addLabel="Create New Site" required
                                    :error="form.errors.site_id"
                                    @add="form.is_new_site = true"
                                />
                            </div>
                            <div v-else class="flex flex-col gap-1 w-full">
                                <div class="flex justify-between items-center pl-1">
                                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">New Site Name</label>
                                    <button type="button" @click="form.is_new_site = false" class="text-[10px] font-bold text-rose-500 hover:underline uppercase">Back</button>
                                </div>
                                <div class="flex gap-2 w-full">
                                    <input 
                                        v-model="form.new_site_name" 
                                        type="text" 
                                        class="flex-1 min-w-0 rounded-lg border-slate-200 text-sm py-2 px-3 focus:ring-4 focus:ring-indigo-100 transition-all" 
                                        placeholder="Enter Site Name..." 
                                    />
                                    <button 
                                        type="button" 
                                        @click="quickAddSite"
                                        :disabled="isCreatingSite || !form.new_site_name"
                                        class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                                    >
                                        {{ isCreatingSite ? 'SAVING...' : 'SAVE' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <BaseSelect 
                            v-model="form.sales_executive_id" 
                            :options="salesExecutiveOptions" 
                            optionLabel="label" 
                            optionValue="value"
                            label="Sales Executive" 
                            placeholder="Select Sales Executive" 
                            filter
                            :error="form.errors.sales_executive_id"
                        />
 
                        <BaseDatePicker v-model="form.quote_date" label="Quote Date" required />
                        <BaseDatePicker v-model="form.validity_date" label="Valid Until" />
                        <BaseSelect 
                            v-model="form.status" 
                            :options="[{ label: 'Draft', value: 0 }, { label: 'Active', value: 1 }]" 
                            optionLabel="label"
                            optionValue="value"
                            label="Initial Status" 
                        />
                    </div>

                    <!-- Items Table -->
                    <div class="mt-2">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2">
                                <CalculatorIcon class="w-3.5 h-3.5" />
                                Estimation Details
                            </h3>
                                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                                    <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_create" class="peer hidden" />
                                    <label for="is_tax_inclusive_create" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                                </div>
                        </div>

                        <div class="rounded-md border border-slate-200 shadow-sm overflow-visible" @click.self="activeRecipePopover = null">
                            <table class="w-full" @click.self="activeRecipePopover = null">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr class="text-[10px] uppercase font-bold text-slate-500">
                                        <th class="px-4 py-3 text-left w-64">Mix Design</th>
                                        <th class="px-4 py-3 text-center w-24">QTY</th>
                                        <th class="px-4 py-3 text-center w-24">UOM</th>
                                        <th class="px-4 py-3 text-center w-32">Rate</th>
                                        <th class="px-4 py-3 text-center w-40">Tax </th>
                                        <th class="px-4 py-3 text-center w-40">Pump Type</th>
                                        <th class="px-4 py-3 text-center w-32">Pump Rate</th>
                                        <th class="px-4 py-3 text-right w-36">Total (Incl. Tax)</th>
                                        <th class="px-4 py-3 w-12">
                                            <button type="button" @click="addItem" class="text-indigo-600 font-bold text-[10px] uppercase hover:text-indigo-700 flex items-center gap-1">
                                                <PlusIcon class="w-5 h-5 m-1 border shadow-sm hover:bg-indigo-100 border-gray-400 rounded-md" />
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="(item, index) in form.items" :key="index" class="group hover:bg-slate-50/50 transition-colors">
                                       <td class="relative p-2 max-w-[260px] overflow-visible">
    <div class="flex items-center gap-2">

        <!-- Mix Design -->
        <div class="flex-1 min-w-0">
            <BaseSelect
                v-model="item.mix_design_id"
                :options="mixDesignOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Search design..."
                filter
                class="w-full"
                @update:modelValue="onMixDesignChange(index)"
            />
        </div>

        <!-- Info Button Popover -->
        <RecipePopover :mixDesignId="item.mix_design_id" :mixDesigns="props.mixDesigns" />

    </div>
</td><td class="p-3">
                                            <BaseInputNumber v-model="item.quantity" :min="1" />
                                        </td>
                                        <td class="p-2">
                                            <BaseSelect
                                                v-model="item.uom_id"
                                                :options="unitOptions"
                                                optionLabel="label" 
                                                optionValue="value"
                                                placeholder="UOM" 
                                                filter
                                            />
                                        </td>
                                        
                                        <td class="p-3">
                                            <BaseInputNumber v-model="item.rate" prefix="₹" />
                                        </td>
                                           <td class="p-3">
                                            <BaseSelect v-model="item.tax_id" :options="taxOptions" optionLabel="label" optionValue="value" placeholder="None" clearable />
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
                                                :minFractionDigits="2"
                                            />
                                        </td>
                                     
                                        <td class="p-3 text-right font-bold text-slate-800 text-sm w-30">
                                            <span class="">₹ {{ Number(item.amount_total).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                        </td>
                                        <td class="p-3 text-center">
                                            <button @click="removeItem(index)" class="p-1.5 text-slate-300 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-all">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Pump Rates per Mix Design ── -->
                    <!-- <div v-if="uniqueSelectedMixDesignIds.length && pumpTypeOptions && pumpTypeOptions.length" class="mt-4 rounded-xl border border-indigo-200 bg-indigo-100/30 p-5 shadow-sm ">
    <div class="flex items-center gap-2 mb-4">
        <span class="text-[11px] font-black text-indigo-700 uppercase tracking-[0.18em]">⚙ Operation Charges per Mix Design</span>
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
                        <template v-for="item in form.items" :key="item.mix_design_id + '-pumprow'">
                            <template v-if="Number(item.mix_design_id) === Number(designId)">
                                <tr v-for="(pr, pi) in item.pump_rates" :key="pr.concrete_pump" class="hover:bg-indigo-50/20 transition-colors p-1">
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

                    <!-- Footer Summary -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest pl-1">Internal Notes / Terms</label>
                            <textarea v-model="form.notes" placeholder="Specify any additional conditions..." class="w-full h-32 rounded-2xl border-slate-200 text-sm focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 transition-all p-4" />
                            <!-- Recipe Details removed – now shown as inline popover per row -->

                        </div>

                        <div class="bg-indigo-50/30 rounded-md p-8 border border-indigo-100/50 shadow-inner">
                            <div class="space-y-4">
                                <div class="flex justify-between items-center text-[12px] font-medium text-slate-600">
                                    <span>Subtotal (Untaxed)</span>
                                    <span class="font-bold">₹ {{ Number(form.amount_untaxed).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[12px] font-medium text-emerald-600">
                                    <span>Total Taxes (+)</span>
                                    <span class="font-bold">₹ {{ Number(form.amount_tax).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex justify-between items-center gap-6">
                                    <span class="text-[12px] font-medium text-slate-600">Adjustment (+/-)</span>
                                    <BaseInputNumber v-model="form.adjustment" class="!w-32" />
                                </div>
                                <div class="h-px bg-indigo-200/50 mt-4 mb-2"></div>
                                <div class="flex justify-between items-between">
                                        <span class="text-[13px] font-semibold text-indigo-600  tracking-[0.15em]">Grand Total</span>
                                        <span class="text-lg font-black text-slate-900 tracking-tighter">
                                            ₹ {{ Number(form.amount_total).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </span>
                                   
                                </div>
                                <!-- <div class="flex justify-between items-end"> -->
                                   
                                    <BaseFormActions 
                                        label="Create Quote" 
                                        :loading="form.processing" 
                                        @submit="submit" 
                                        @reset="form.reset()" 
                                    />
                                <!-- </div> -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </Transition>
    </div>
</template>

<style scoped>

</style>
