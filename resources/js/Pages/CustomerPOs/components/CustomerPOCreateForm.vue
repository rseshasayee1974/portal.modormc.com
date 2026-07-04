<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { PlusCircleIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

const props = withDefaults(defineProps<{
    patrons?: any[];
    sites?: any[];
    quotations?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    concretePumpOptions?: any[];
}>(), {
    patrons: () => [],
    sites: () => [],
    quotations: () => [],
    mixDesigns: () => [],
    salesExecutives: () => [],
    concretePumpOptions: () => [],
});

const form = useForm({
    prefix: null as string | null,
    reference: null as string | null,
    quotation_id: null as number | null,
    patron_id: null as number | null,
    status: 0 as number | null,
    site_id: null as number | null,
    sales_executive_id: null as number | null,
    concrete_pump: 'pump' as string | null,
    order_date: new Date().toISOString().split('T')[0],
    items: [
        { mix_design_id: null as number | null, quantity: null as number | null, rate: null as number | null }
    ] as Array<{ mix_design_id: number | null, quantity: number | null, rate: number | null }>,
});

// Watch quotation selection to auto-fill patron, site, and sales executive
watch(() => form.quotation_id, (newVal) => {
    if (newVal) {
        const quote = props.quotations.find((q) => Number(q.id) === Number(newVal));
        if (quote) {
            form.patron_id = quote.patron_id;
            form.site_id = quote.site_id;
            form.sales_executive_id = quote.sales_executive_id;
            form.concrete_pump = quote.concrete_pump;
        }
    } else {
        form.patron_id = null;
        form.site_id = null;
        form.sales_executive_id = null;
        form.concrete_pump = null;
        form.items = [{ mix_design_id: null, quantity: null, rate: null }];
    }
});

const addItem = () => {
    form.items.push({ mix_design_id: null, quantity: null, rate: null });
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

const uniqueSelectedMixDesignIds = computed(() => {
    const ids = new Set<number>();
    form.items.forEach(item => {
        if (item.mix_design_id) {
            ids.add(Number(item.mix_design_id));
        }
    });
    // For quotation-linked, load items from quotation
    if (form.quotation_id) {
        const quote = props.quotations.find(q => Number(q.id) === Number(form.quotation_id));
        if (quote && quote.items) {
            quote.items.forEach((item: any) => {
                if (item.mix_design_id) {
                    ids.add(Number(item.mix_design_id));
                }
            });
        }
    }
    return Array.from(ids);
});

const getMixDesignMaterials = (mixDesignId: number | null) => {
    if (!mixDesignId) return [];
    const design = props.mixDesigns.find(md => Number(md.id) === Number(mixDesignId));
    if (!design || !design.items) return [];
    return design.items.map((it: any) => ({
        id: it.id,
        name: it.product?.title || 'Unknown Material',
        qty: Number(it.actual_quantity || 0),
        uom: it.uom?.unit_code || '',
    }));
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

    form.post(route('customer-po.store'), {
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

    <!-- Concrete Type -->
    <div>
        <BaseSelect
            v-model="form.concrete_pump"
            :options="concretePumpOptions"
            optionLabel="label"
            optionValue="value"
            label="Concrete Type"
            placeholder="Select Concrete Type"
            :error="form.errors.concrete_pump"
        />
    </div>
    <!-- Mix Design Section -->
    <template v-if="!form.quotation_id">

        <div class="col-span-full border-t pt-3">
            <div class="flex items-center justify-between">

                <h3 class="text-sm font-semibold">
                    Mix Design Items
                </h3>

                <BaseButton
                    icon="pi pi-plus"
                    label="Add Item"
                    severity="primary"
                    size="small"
                    @click="addItem"
                />
            </div>
        </div>

        <div
    v-for="(item, idx) in form.items"
    :key="idx"
    class="col-span-full pb-0 last:border-0 last:pb-0"
>
    <div class="grid grid-cols-12 gap-3 items-start">
        <!-- Mix Design - gets 50% of row on md+ -->
        <div class="col-span-12 md:col-span-6">
            <BaseSelect
                v-model="item.mix_design_id"
                :options="mixDesignOptions"
                optionLabel="label"
                optionValue="value"
                filter
                label="Mix Design"
                placeholder="Select Mix Design"
                :error="form.errors[`items.${idx}.mix_design_id`]"
            />
        </div>

        <!-- Quantity - 20% -->
        <div class="col-span-6 md:col-span-2">
            <BaseInput
                v-model="item.quantity"
                type="number"
                step="0.001"
                min="0.001"
                label="Qty (m³)"
                placeholder="0.000"
                :error="form.errors[`items.${idx}.quantity`]"
            />
        </div>

        <!-- Rate - 20% -->
        <div class="col-span-6 md:col-span-2">
            <BaseInput
                v-model="item.rate"
                type="number"
                step="0.01"
                min="0"
                label="Rate (₹)"
                placeholder="0.00"
                :error="form.errors[`items.${idx}.rate`]"
            />
        </div>

        <!-- Amount - 6% calculated -->
        <div class="col-span-10 md:col-span-1">
            <label class="block text-xs font-medium text-gray-700">Amount</label>
            <div class="h-8 flex items-center px-3 text-sm font-semibold text-indigo-700 bg-indigo-50 rounded-md">
                ₹{{ ((item.quantity || 0) * (item.rate || 0)).toFixed(2) }}
            </div>
        </div>

        <!-- Delete - 4% -->
        <div class="col-span-2 md:col-span-1 flex justify-end pt-6">
            <Button
                icon="pi pi-trash"
                severity="danger"
                rounded
                text
                size="small"
                :disabled="form.items.length === 1"
                @click="removeItem(idx)"
                v-tooltip.top="'Remove item'"
            />
        </div>
    </div>
</div>
    </template>
    <template v-else>
        <div class="col-span-full border-t pt-3">
            <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800 mb-3">
                Mix Design Items (Loaded from Quotation)
            </h3>
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

    <!-- Recipe Details -->
    <div v-if="uniqueSelectedMixDesignIds.length" class="col-span-full mt-4 space-y-3">
        <div 
            v-for="designId in uniqueSelectedMixDesignIds" 
            :key="designId"
            class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3 text-left"
        >
            <div class="flex items-center justify-between">
                <label class="text-[10px] font-bold uppercase tracking-[0.1em] text-indigo-500">
                    Recipe Details
                </label>
                <span class="rounded bg-indigo-100 px-2 py-1 text-[10px] font-bold text-indigo-700">
                    {{ props.mixDesigns.find(d => Number(d.id) === Number(designId))?.title || props.mixDesigns.find(d => Number(d.id) === Number(designId))?.design_name || '-' }}
                </span>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <div 
                    v-for="item in getMixDesignMaterials(designId)" 
                    :key="item.id" 
                    class="flex items-center gap-2 rounded-md border border-indigo-100 bg-white px-3 py-2"
                >
                    <span class="text-xs text-slate-700">{{ item.name }}</span>
                    <span class="font-semibold text-indigo-600">
                        {{ item.qty }}
                        <span class="text-slate-400 text-[10px]">{{ item.uom }}</span>
                    </span>
                </div>
                <div v-if="!getMixDesignMaterials(designId).length" class="text-xs text-slate-400 italic">
                    No materials configured for this recipe.
                </div>
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
