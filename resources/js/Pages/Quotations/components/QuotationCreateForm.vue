<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import BaseSelectQuickAdd from '@/Components/Base/BaseSelectQuickAdd.vue';
import BaseAutoComplete from '@/Components/Base/BaseAutoComplete.vue';
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
      uom_id: null, // Added
    // Calculated fields strictly for the new schema
    tax_amount: number;
    untaxed_amount: number;
    amount_total: number;
}

const props = defineProps<{
    patrons: { id: number; legal_name: string }[];
    sites: { id: number; name: string }[];
    unitOptions : {id: number, unit_code: string}[];
    mixDesigns: { id: number; title: string; code?: string; rate?: number; unit_id?: number }[];
    taxes: { id: number; tax_name?: string; tax_rate?: number }[];
    units?: { id: number; name: string }[]; // Falling back if missing
    instant_customer: number | boolean;
}>();
// console.log(props.taxes);
const isOpen = ref(true);

const form = useForm({
    patron_id: null as number | null,
    site_id: null as number | null,
    new_site_name: '' as string,
    is_new_site: false,
    quote_date: new Date().toISOString().substring(0, 10),
    validity_date: null as string | null,
    status: 0,
    adjustment: 0,
    // Header totals
    amount_untaxed: 0,
    tax_amount: 0,
    amount_tax: 0,
    amount_total: 0,
    items: [createNewItem()] as QuotationItemPayload[],
});

function createNewItem(): QuotationItemPayload {
    return {
        id: null,
        mix_design_id: null,
        quantity: 1,
        tax_id: null,
        uom_id: null, 
        rate: 0,
        tax_amount: 0,
        untaxed_amount: 0,
        amount_total: 0
    };
}

// Options Computeds
const patronOptions = computed(() => props.patrons.map(p => ({ label: p.legal_name, value: p.id })));
const siteOptions = computed(() => props.sites.map(s => ({ label: s.name, value: s.id })));
const mixDesignOptions = computed(() => props.mixDesigns.map(p => ({ 
    label: `${p.title}${p.code ? ` (${p.code})` : ''}`, 
    value: p.id,
    price: p.rate 
})));
const unitOptions = computed(() => (props.unitOptions || []).map(u => ({ label: u.unit_code, value: u.id })));
const taxOptions = computed(() => props.taxes?.map(t => ({ label: t.tax_name, value: t.id })) || []);

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
        const untaxed = rate * qty;
        
        // Find line tax rate
        const tax = props.taxes.find(t => t.id === item.tax_id);
        const taxRate = tax ? Number(tax.tax_rate) : 0;
        const lineTax = (untaxed * taxRate) / 100;

        // Update Item Internal State (for SQL Insertion)
        item.untaxed_amount = Number(untaxed.toFixed(2));
        item.tax_amount = Number(lineTax.toFixed(2));
        item.amount_total = Number((untaxed + lineTax).toFixed(2));

        totalUntaxed += untaxed;
        totalTax += lineTax;
    });

    form.amount_untaxed = Number(totalUntaxed.toFixed(2));
    form.tax_amount = Number(totalTax.toFixed(2)); // Both fields as per SQL
    form.amount_tax = Number(totalTax.toFixed(2));
    form.amount_total = Number((totalUntaxed + totalTax + Number(form.adjustment || 0)).toFixed(2));
};

// Deep watch for any changes in items or adjustment
watch(() => [form.items, form.adjustment], calculateTotals, { deep: true, immediate: true });

const onMixDesignChange = (index: number) => {
    const item = form.items[index];
    const design = props.mixDesigns.find(p => p.id === item.mix_design_id);
    if (design) {
        if (!item.rate) item.rate = Number(design.rate || 0);
        if (!item.uom_id && design.unit_id) item.uom_id = design.unit_id;
    }
};

const addItem = () => form.items.push(createNewItem());
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
            timer: 3000 
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
    form.transform((data) => ({
        ...data,
        site_id: data.is_new_site ? null : (data.site_id ? Number(data.site_id) : null),
        new_site_name: data.is_new_site ? data.new_site_name : null,
        patron_id: data.patron_id ? Number(data.patron_id) : null,
        quote_date: data.quote_date ? new Date(data.quote_date).toISOString().substring(0, 10) : null,
        validity_date: data.validity_date ? new Date(data.validity_date).toISOString().substring(0, 10) : null,
    })).post(route('quotations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ icon: 'success', title: 'Success', text: 'Quotation created successfully!' });
            form.reset();
            form.is_new_site = false;
            form.items = [createNewItem()];
        }
    });
};
</script>

<template>
    <div class="quotation-card" :class="{ 'quotation-card--active': isOpen }">
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
                    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-6 items-end">
                        <BaseCreatableSelect 
                            v-if="isInstantCustomerEnabled"
                            v-model="form.patron_id" 
                            :options="patronOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            label="Customer" 
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
                        />
                        
                        <div class="relative">
                            <div v-if="!form.is_new_site">
                                <BaseSelectQuickAdd
                                    v-model="form.site_id" 
                                    :options="siteOptions" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    label="Project Site" 
                                    placeholder="Select Project Site" 
                                    addLabel="Create New Site"
                                    @add="form.is_new_site = true"
                                />
                            </div>
                            <div v-else class="flex flex-col gap-1 w-full relative">
                                <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest pl-1">New Site Name</label>
                                <div class="flex gap-2 w-full">
                                    <input 
                                        v-model="form.new_site_name" 
                                        type="text" 
                                        class="flex-1 rounded-lg border-slate-200 text-sm py-2 px-3 focus:ring-4 focus:ring-indigo-100 transition-all" 
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
                                <button type="button" @click="form.is_new_site = false" class="absolute -top-1 -right-0 text-[10px] font-bold text-rose-500 hover:underline uppercase">Back</button>
                            </div>
                        </div>

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
                                Line Items
                            </h3>
                            
                        </div>

                        <div class="rounded-md border border-slate-200 shadow-sm overflow-hidden">
                            <table class="w-full">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr class="text-[10px] uppercase font-bold text-slate-500">
                                        <th class="px-4 py-3 text-left w-64">Mix Design</th>
                                         <th class="px-4 py-3 text-center w-24">UOM</th>
                                        <th class="px-4 py-3 text-center w-24">QTY</th>
                                        <th class="px-4 py-3 text-center w-32">Rate</th>
                                        <th class="px-4 py-3 text-center w-40">Tax </th>
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
                                        <td class="p-3">
                                            <BaseSelect 
                                                v-model="item.mix_design_id" 
                                                :options="mixDesignOptions" 
                                                optionLabel="label"
                                                optionValue="value"
                                                placeholder="Search design..." 
                                                filter 
                                                @update:modelValue="onMixDesignChange(index)"
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
                                                 
                                            />
                                        </td>
                                        <td class="p-3">
                                            <BaseInputNumber v-model="item.quantity" :min="1" />
                                        </td>
                                        <td class="p-3">
                                            <BaseInputNumber v-model="item.rate" prefix="₹" />
                                        </td>
                                        <td class="p-3">
                                            <BaseSelect v-model="item.tax_id" :options="taxOptions" optionLabel="label" optionValue="value" placeholder="None" clearable />
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

                    <!-- Footer Summary -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest pl-1">Internal Notes / Terms</label>
                            <textarea placeholder="Specify any additional conditions..." class="w-full h-32 rounded-2xl border-slate-200 text-sm focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 transition-all p-4" />
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
