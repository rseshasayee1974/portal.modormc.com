<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import Swal from 'sweetalert2';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import { usePermissions } from '@/Composables/usePermissions';
import { TrashIcon } from '@heroicons/vue/24/outline';
const props = withDefaults(defineProps<{
    customerPO?: any;
    quotations?: any[];
    patrons?: any[];
    sites?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    concretePumpOptions?: any[];
    taxes?: any[];
    pumpTypeOptions?: { label: string; value: string }[];
}>(), {
    customerPO: () => ({}),
    quotations: () => [],   
    patrons: () => [],
    sites: () => [],
    mixDesigns: () => [],
    salesExecutives: () => [],
    concretePumpOptions: () => [],
    taxes: () => [],
    pumpTypeOptions: () => [],
});


const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();
const { isAdmin , isSuperAdmin} = usePermissions();
const admin = isAdmin.value || isSuperAdmin.value;
const form = useForm({
    prefix: props.customerPO?.prefix ?? 'CPO',
    reference: props.customerPO?.reference ?? '',
    quotation_id: props?.customerPO?.quotation_id ?? null,
    patron_id: props.customerPO?.patron_id ?? null,
    site_id: props.customerPO?.site_id ?? null,
    sales_executive_id: props.customerPO?.sales_executive_id ?? null,
    // concrete_pump: props.customerPO?.concrete_pump !== null ? (isNaN(Number(props.customerPO.concrete_pump)) ? props.customerPO.concrete_pump : Number(props.customerPO.concrete_pump)) : null,
    is_tax_inclusive: props.customerPO?.is_tax_inclusive ? true : false,
    order_date: props.customerPO?.order_date ?? '',
    status: props.customerPO?.status ?? 1,
    notes: props.customerPO?.notes ?? '',
    items: [] as Array<{ id: number | null, mix_design_id: number | null, quantity: number | null, rate: number | null, tax_id: number | null, tax_amount: number | null, pump_rates: Array<{ pump_type: string, pump_rate: number }> }>,
    mix_design_id: null as number | null,
    quantity: null as number | null,
    rate: null as number | null,
    tax_id: null as number | null,
    tax_amount: null as number | null,
    pump_rates: (props.pumpTypeOptions || []).map(pt => ({ pump_type: pt.value, pump_rate: 0 })) as Array<{ pump_type: string, pump_rate: number }>,
});

// Pre-fill items from sales order or quotation items
const itemsList = props.customerPO?.items || props.customerPO?.quotation?.items || [];
form.items = itemsList.map((item: any) => ({
    id: props.customerPO?.items?.some((i: any) => i.id === item.id) ? item.id : null,
    mix_design_id: item.mix_design_id,
    quantity: Number(item.quantity),
    rate: Number(item.rate),
    tax_id: item.tax_id ?? null,
    tax_amount: item.tax_amount !== null ? Number(item.tax_amount) : 0,
    pump_rates: (props.pumpTypeOptions || []).map(pt => {
        const saved = (item.pump_rates || item.pumpRates || []).find((pr: any) => pr.pump_type === pt.value);
        return { pump_type: pt.value, pump_rate: saved ? Number(saved.pump_rate) : 0 };
    }),
}));

if (form.items.length === 0 && !form.quotation_id) {
    form.items.push({ id: null, mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, pump_rates: (props.pumpTypeOptions || []).map(pt => ({ pump_type: pt.value, pump_rate: 0 })) });
}

if (itemsList.length === 1) {
    const item = itemsList[0];
    form.mix_design_id = item.mix_design_id;
    form.quantity = Number(item.quantity);
    form.rate = Number(item.rate);
    form.tax_id = item.tax_id ?? null;
    form.tax_amount = item.tax_amount !== null ? Number(item.tax_amount) : 0;
    form.pump_rates = (props.pumpTypeOptions || []).map(pt => {
        const saved = (item.pump_rates || item.pumpRates || []).find((pr: any) => pr.pump_type === pt.value);
        return { pump_type: pt.value, pump_rate: saved ? Number(saved.pump_rate) : 0 };
    });
}

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
            form.items = quoteItems.map((item: any) => ({
                id: null,
                mix_design_id: item.mix_design_id,
                quantity: Number(item.quantity),
                rate: Number(item.rate),
                tax_id: item.tax_id ?? null,
                tax_amount: Number(item.tax_amount ?? 0),
                pump_rates: (props.pumpTypeOptions || []).map(pt => {
                    const saved = (item.pump_rates || item.pumpRates || []).find((pr: any) => pr.pump_type === pt.value);
                    return { pump_type: pt.value, pump_rate: saved ? Number(saved.pump_rate) : 0 };
                }),
            }));
            if (quoteItems.length === 1) {
                form.mix_design_id = quoteItems[0].mix_design_id;
                form.quantity = Number(quoteItems[0].quantity);
                form.rate = Number(quoteItems[0].rate);
                form.tax_id = quoteItems[0].tax_id ?? null;
                form.tax_amount = Number(quoteItems[0].tax_amount ?? 0);
                form.pump_rates = (props.pumpTypeOptions || []).map(pt => {
                    const saved = (quoteItems[0].pump_rates || quoteItems[0].pumpRates || []).find((pr: any) => pr.pump_type === pt.value);
                    return { pump_type: pt.value, pump_rate: saved ? Number(saved.pump_rate) : 0 };
                });
            }
        }
    } else {
        form.items = [{ id: null, mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, pump_rates: (props.pumpTypeOptions || []).map(pt => ({ pump_type: pt.value, pump_rate: 0 })) }];
        form.mix_design_id = null;
        form.quantity = null;
        form.rate = null;
        form.tax_id = null;
        form.tax_amount = null;
        form.pump_rates = (props.pumpTypeOptions || []).map(pt => ({ pump_type: pt.value, pump_rate: 0 }));
        form.sales_executive_id = null;
        form.is_tax_inclusive = false;
    }
});

const addItem = () => {
    form.items.push({ id: null, mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, pump_rates: (props.pumpTypeOptions || []).map(pt => ({ pump_type: pt.value, pump_rate: 0 })) });
};


const removeItem = (index: number) => {
    if (form.items.length > 1) {
        const completedQty = getCustomerPOCompletedQty(props.customerPO);
        if (completedQty > 0 && isAdmin.value) {
            Swal.fire({
                title: 'Warning: Sales Orders Exist!',
                text: `${getDeleteRestrictionReason(props.customerPO)}. Are you sure you want to proceed?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, remove',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.items.splice(index, 1);
                }
            });
        } else {
            form.items.splice(index, 1);
        }
    }
};

const getCustomerPOCompletedQty = (customerPO: any) => {
    return customerPO?.sales_orders?.reduce((sum: number, wo: any) => sum + Number(wo.total_qty || 0), 0) || 0;
};

const canDeleteCustomerPO = (customerPO: any): boolean => {
    if (!customerPO || !customerPO.id) return true;
    
    const completedQty = getCustomerPOCompletedQty(customerPO);
    
    return completedQty === 0 || isAdmin.value;
};

const getDeleteRestrictionReason = (customerPO: any): string => {
    if (!customerPO) return 'Invalid PO';
    const completedQty = getCustomerPOCompletedQty(customerPO);
    if (completedQty === 0) return '';
    return `Cannot modify — ${completedQty} m³ already allocated to Sales Orders`;
};

// Filter sites by selected patron
const filteredSites = computed(() => {
    return (props.sites || []).filter((s: any) => {
        if (!s) return false;
        return !form.patron_id || (Array.isArray(s.patron_id) ? s.patron_id.includes(form.patron_id) : s.patron_id === form.patron_id);
    });
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

// Watch single item fields and is_tax_inclusive status to dynamically update tax_amount
watch(() => [form.quantity, form.rate, form.tax_id, form.is_tax_inclusive], () => {
    if (form.quantity !== null && form.rate !== null) {
        const qty = Number(form.quantity || 0);
        const rate = Number(form.rate || 0);
        const tax = props.taxes?.find(t => Number(t.id) === Number(form.tax_id));
        const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

        if (form.is_tax_inclusive) {
            const total = qty * rate;
            form.tax_amount = Number((total - (total / (1 + taxRate / 100))).toFixed(2));
        } else {
            const untaxed = qty * rate;
            form.tax_amount = Number(((untaxed * taxRate) / 100).toFixed(2));
        }
    }
}, { deep: true, immediate: true });

const excludedMixDesignPumpRates = ref<number[]>([]);

const uniqueSelectedMixDesignIds = computed(() => {
    const ids = new Set<number>();
    
    // Check form items list
    if (form.items && form.items.length) {
        form.items.forEach(item => {
            if (item.mix_design_id && !excludedMixDesignPumpRates.value.includes(Number(item.mix_design_id))) {
                ids.add(Number(item.mix_design_id));
            }
        });
    }
    
    // Check form single item
    if (form.mix_design_id && !excludedMixDesignPumpRates.value.includes(Number(form.mix_design_id))) {
        ids.add(Number(form.mix_design_id));
    }
    
    return Array.from(ids);
});

const removePumpRatesForDesign = (designId: number) => {
    excludedMixDesignPumpRates.value.push(Number(designId));
    // Zero out pump rates for this design in the items
    if (form.items && form.items.length) {
        form.items.forEach(item => {
            if (Number(item.mix_design_id) === Number(designId)) {
                item.pump_rates.forEach(pr => {
                    pr.pump_rate = 0;
                });
            }
        });
    }
    // Also check single form item pump rates
    if (Number(form.mix_design_id) === Number(designId) && form.pump_rates) {
        form.pump_rates.forEach((pr: any) => {
            pr.pump_rate = 0;
        });
    }
};

const calculatedTotals = computed(() => {
    let subtotal = 0;
    let taxAmount = 0;

    // Determine if it uses the single-item edit mode
    const isSingleItem = !form.quotation_id && form.items.length <= 1;
    const isSingleQuote = form.quotation_id && (props.customerPO?.quotation?.items?.length === 1 || props.customerPO?.items?.length === 1);

    if (isSingleItem || isSingleQuote) {
        // Single item mode
        const qty = Number(form.quantity || 0);
        const rate = Number(form.rate || 0);
        const tax = props.taxes?.find(t => Number(t.id) === Number(form.tax_id));
        const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

        if (form.is_tax_inclusive) {
            const total = qty * rate;
            const lineTax = total - (total / (1 + taxRate / 100));
            taxAmount = lineTax;
            subtotal = total - lineTax;
        } else {
            const lineUntaxed = qty * rate;
            subtotal = lineUntaxed;
            taxAmount = (lineUntaxed * taxRate) / 100;
        }
    } else {
        // Multi item mode
        (form.items || []).forEach((item: any) => {
            const qty = Number(item.quantity || 0);
            const rate = Number(item.rate || 0);
            const tax = props.taxes?.find(t => Number(t.id) === Number(item.tax_id));
            const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

            if (form.is_tax_inclusive) {
                const total = qty * rate;
                const lineTax = total - (total / (1 + taxRate / 100));
                taxAmount += lineTax;
                subtotal += (total - lineTax);
            } else {
                const lineUntaxed = qty * rate;
                subtotal += lineUntaxed;
                taxAmount += (lineUntaxed * taxRate) / 100;
            }
        });
    }

    return {
        subtotal: Number(subtotal.toFixed(2)),
        tax: Number(taxAmount.toFixed(2)),
        total: Number((subtotal + taxAmount).toFixed(2))
    };
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
    
    // Ensure the current sales order's linked quotation is included in the options list
    if (props.customerPO?.quotation) {
        const exists = list.some((q) => Number(q.id) === Number(props.customerPO.quotation.id));
        if (!exists) {
            list.push(props.customerPO.quotation);
        }
    }

    return [
        { label: 'None (Direct Customer PO)', value: null },
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
    const customerPOId = props.customerPO?.id;

    if (!customerPOId) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Unable to update: missing sales order id',
            timer: 1500,
            showConfirmButton: false,
        });
        return;
    }

    if (!form.quotation_id) {
        // Direct Customer PO: validate items
        let hasError = false;
        form.clearErrors();
        
        if (!form.patron_id) {
            form.setError('patron_id', 'Customer is required.');
            hasError = true;
        }
        if (!form.site_id) {
            form.setError('site_id', 'Site is required.');
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
                title: 'Please fix the errors in the form.',
                timer: 3000,
                showConfirmButton: false,
            });
            return;
        }
    }

    const completedQty = getCustomerPOCompletedQty(props.customerPO);
    if (completedQty > 0 && isAdmin.value) {
        Swal.fire({
            title: 'Warning: Sales Orders Exist!',
            text: `${getDeleteRestrictionReason(props.customerPO)}. Are you sure you want to proceed?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Yes, update',
        }).then((result) => {
            if (result.isConfirmed) {
                performSubmit(customerPOId);
            }
        });
    } else {
        performSubmit(customerPOId);
    }
};

const performSubmit = (customerPOId: any) => {
    form.put(route('customer-po.update', customerPOId), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Customer PO updated successfully.',
                timer: 1500,
                showConfirmButton: false,
            });
            emit('saved');
        },
    });
};
</script>

<template>
    <div class="rounded-lg border border-indigo-100 bg-white p-5 text-left">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800">Edit Customer PO</h3>
            <span class="font-mono text-xs font-bold text-amber-600">
                REF # : {{ customerPO.reference || 'Auto-generated' }}
            </span>
        </div>

        <div class="grid grid-cols-12 md:grid-cols-5 gap-x-4 gap-y-3">
            <!-- <div class="col-span-12 md:col-span-1">
                <BaseInput
                    v-model="form.prefix"
                    label="PO Prefix"
                    placeholder="e.g. CPO"
                    :error="form.errors.prefix"
                />
            </div> -->
            <!-- <div class="col-span-12 md:col-span-1">
                <BaseInput
                    v-model="form.reference"
                    label="PO Number / Ref"
                    placeholder="Auto-generated if blank"
                    :error="form.errors.reference"
                />
            </div> -->
            <div class="col-span-12 md:col-span-1">
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
            <!-- <div class="col-span-12 md:col-span-1">
                <BaseSelect
                    v-model="form.concrete_pump"
                    :options="concretePumpOptions"
                    optionLabel="label"
                    optionValue="value"
                    label="Concrete Type"
                    placeholder="Select Concrete Type"
                    :error="form.errors.concrete_pump"
                </div>
                /> -->
            <div class="col-span-12 md:col-span-1">
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
                <span v-if="form.quotation_id" class="text- text-indigo-600 mt-1 block font-medium">Locked to Quotation Customer</span>
            </div>

            <div class="col-span-12 md:col-span-1">
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
                <span v-if="form.quotation_id" class="text- text-indigo-600 mt-1 block font-medium">Locked to Quotation Site</span>
            </div>

            <div class="col-span-12 md:col-span-1">
                <BaseDatePicker
                    fluid
                    hourFormat="24"
                    label="Order Date"
                    v-model="form.order_date"
                    :error="form.errors.order_date"
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

            <!-- Direct Sales Order: Show multi-item list -->
            <template v-if="!form.quotation_id">
                <div class="col-span-12 md:col-span-5 mt-2 border-t border-gray-200 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wide text-indigo-800 flex items-center gap-4">
                            Mix Design Items
                            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                                <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_edit_1" :disabled="!!form.quotation_id" class="peer hidden" />
                                <label for="is_tax_inclusive_po_edit_1" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                            </div>
                        </span>
                        <Button
                            icon="pi pi-plus"
                            label="Add Mix Design"
                            size="small"
                            severity="secondary"
                            text
                            @click="addItem"
                        />
                    </div>
                </div>

                <div class="col-span-12 md:col-span-5 space-y-3">
                    <div 
                        v-for="(item, idx) in form.items" 
                        :key="idx" 
                        class="grid grid-cols-12 gap-3 items-start pb-3"
                    >
                        <!-- Mix Design -->
                        <div class="col-span-12 md:col-span-3">
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
                    
                        <!-- Quantity -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseInput
                                type="number"
                                step="0.001"
                                min="0.001"
                                v-model="item.quantity"
                                label="Qty (m³)"
                                placeholder="0.000"
                                :error="form.errors[`items.${idx}.quantity`]"
                            />
                        </div>
                    
                        <!-- Rate -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseInput
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="item.rate"
                                label="Rate (₹)"
                                placeholder="0.00"
                                :error="form.errors[`items.${idx}.rate`]"
                            />
                        </div>

                        <!-- Tax -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseSelect
                                v-model="item.tax_id"
                                :options="taxOptions"
                                optionLabel="label"
                                optionValue="value"
                                label="Tax"
                                placeholder="None"
                                clearable
                                :error="form.errors[`items.${idx}.tax_id`]"
                            />
                        </div>

                        <!-- Tax Amount -->
                        <div class="col-span-6 md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700">Tax Amt</label>
                            <div class="h-8 flex items-center px-2 text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-md">
                                ₹{{ Number(item.tax_amount || 0).toFixed(2) }}
                            </div>
                        </div>
                    
                        <!-- Amount -->
                        <div class="col-span-10 md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700">Amount</label>
                            <div class="h-8 flex items-center px-2 text-xs font-semibold text-indigo-700 bg-indigo-50 rounded-md">
                                ₹{{ ((Number(item.quantity || 0) * Number(item.rate || 0)) + Number(item.tax_amount || 0)).toFixed(2) }}
                            </div>
                        </div>
                    
                        <!-- Delete -->
                        <div class="col-span-2 md:col-span-1 flex justify-end pt-6">
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                :disabled="form.items.length === 1 || !canDeleteCustomerPO(customerPO)"
                                @click="removeItem(idx)"
                                v-tooltip.top="'Remove'"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- Quotation-linked Customer PO with single item -->
            <template v-else-if="form.quotation_id && (customerPO?.quotation?.items?.length === 1 || customerPO?.items?.length === 1)">
                <div class="col-span-12 md:col-span-5 mt-2 border-t border-gray-200 pt-4 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-800 flex items-center gap-4">
                        Item Details
                        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                            <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_edit_2" :disabled="!!form.quotation_id" class="peer hidden" />
                            <label for="is_tax_inclusive_po_edit_2" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                        </div>
                    </span>
                </div>

                <div class="col-span-12 md:col-span-5">
                    <div class="grid grid-cols-12 gap-3 items-start pb-3">
                        <!-- Mix Design -->
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect
                                v-model="form.mix_design_id"
                                :options="mixDesignOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                label="Mix Design"
                                placeholder="Select Mix Design"
                                :error="form.errors.mix_design_id"
                            />
                        </div>

                        <!-- Quantity -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseInput
                                type="number"
                                step="0.001"
                                v-model="form.quantity"
                                label="Qty (m³)"
                                placeholder="0.000"
                                :error="form.errors.quantity"
                            />
                        </div>

                        <!-- Rate -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseInput
                                type="number"
                                step="0.01"
                                v-model="form.rate"
                                label="Rate (₹)"
                                placeholder="0.00"
                                :error="form.errors.rate"
                            />
                        </div>

                        <!-- Tax -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseSelect
                                v-model="form.tax_id"
                                :options="taxOptions"
                                optionLabel="label"
                                optionValue="value"
                                label="Tax"
                                placeholder="None"
                                clearable
                                :error="form.errors.tax_id"
                            />
                        </div>

                        <!-- Tax Amount -->
                        <div class="col-span-6 md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700">Tax Amt</label>
                            <div class="h-8 flex items-center px-2 text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-md">
                                ₹{{ Number(form.tax_amount || 0).toFixed(2) }}
                            </div>
                        </div>

                        <!-- Total Amount -->
                        <div class="col-span-12 md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700">Total Amount</label>
                            <div class="h-8 flex items-center px-3 text-sm font-semibold text-indigo-700 bg-indigo-50 rounded-md">
                                ₹{{ ((Number(form.quantity || 0) * Number(form.rate || 0)) + Number(form.tax_amount || 0)).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Quotation-linked Customer PO with multiple items -->
            <template v-else>
                <div class="col-span-12 md:col-span-5 mt-2 border-t border-gray-200 pt-4 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-800 flex items-center gap-4">
                        Mix Design Items (Loaded from Quotation)
                        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                            <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_edit_3" :disabled="!!form.quotation_id" class="peer hidden" />
                            <label for="is_tax_inclusive_po_edit_3" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                        </div>
                    </span>
                </div>
                
                <div class="col-span-12 md:col-span-5">
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
                                <tr v-for="item in form.items" :key="item.id" class="border-b border-indigo-50/50 last:border-0 font-medium text-slate-700">
                                    <td class="p-2 text-slate-900 font-semibold font-bold">
                                        {{ mixDesigns.find(d => Number(d.id) === Number(item.mix_design_id))?.title || mixDesigns.find(d => Number(d.id) === Number(item.mix_design_id))?.design_name || '-' }}
                                    </td>
                                    <td class="p-2 text-right font-mono">{{ Number(item.quantity).toFixed(3) }} m³</td>
                                    <td class="p-2 text-right font-mono">₹{{ Number(item.rate).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                                    <td class="p-2 text-right font-mono font-bold text-indigo-900">₹{{ Number(item.quantity * item.rate).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
            <!-- Recipe Details -->
            <div v-if="uniqueSelectedMixDesignIds.length" class="col-span-12 md:col-span-5 mt-4 space-y-3">
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

            <!-- ── Pump Rates per Mix Design ── -->
            <div v-if="uniqueSelectedMixDesignIds.length && pumpTypeOptions && pumpTypeOptions.length" class="col-span-12 md:col-span-5 mt-4 rounded-xl border border-indigo-200 bg-indigo-100/30 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-[11px] font-black text-indigo-700 uppercase tracking-[0.18em]">⚙ Pump Rates per Mix Design</span>
                    <span class="text-[10px] text-indigo-500 font-medium">(enter rate per m³ for each pump type)</span>
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
                                        <th class="px-4 py-2.5 text-left w-1/2">Pump Type</th>
                                        <th class="px-4 py-2.5 text-right w-1/2">Rate (₹ / m³)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-indigo-50/50">
                                    <!-- Single item mode -->
                                    <template v-if="form.quotation_id && (customerPO?.quotation?.items?.length === 1 || customerPO?.items?.length === 1)">
                                        <template v-if="Number(form.mix_design_id) === Number(designId)">
                                            <tr v-for="pr in form.pump_rates" :key="pr.pump_type" class="hover:bg-indigo-50/20 transition-colors p-1">
                                                <td class="px-4 py-0 font-medium text-slate-700 truncate">{{ props.pumpTypeOptions?.find(opt => String(opt.value) === String(pr.pump_type))?.label || pr.pump_type }}</td>
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
                                    <!-- Multi item mode -->
                                    <template v-else>
                                        <template v-for="item in form.items" :key="String(item.mix_design_id) + '-cpo-pr-edit'">
                                            <template v-if="Number(item.mix_design_id) === Number(designId)">
                                                <tr v-for="pr in item.pump_rates" :key="pr.pump_type" class="hover:bg-indigo-50/20 transition-colors p-1">
                                                    <td class="px-4 py-0 font-medium text-slate-700 truncate">{{ props.pumpTypeOptions?.find(opt => String(opt.value) === String(pr.pump_type))?.label || pr.pump_type }}</td>
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
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Totals & Summary Block -->

            <div class="col-span-12 md:col-span-5 flex flex-col md:flex-row justify-between items-start gap-8 mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
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

        <div class="mt-5 flex justify-end gap-2 border-t border-gray-200 pt-4">
            <BaseFormActions
                :disabled="props.customerPO.has_salesorders && !admin "
                mode="update"
                updateLabel="Update Customer PO"
                :loading="form.processing"
                @submit="submit"
                @cancel="emit('cancel')"
            />
        </div>
    </div>
</template>