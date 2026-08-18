<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import Swal from 'sweetalert2';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import { usePermissions } from '@/Composables/usePermissions';
import { TrashIcon, PlusIcon, CalculatorIcon } from '@heroicons/vue/24/outline';
import RecipePopover from '@/Components/Base/RecipePopover.vue';
import { calculateLineItemTotals } from '@/composables/useLineItemCalculation';
const props = withDefaults(defineProps<{
    customerPO?: any;
    quotations?: any[];
    patrons?: any[];
    sites?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    taxes?: any[];
    pumpTypeOptions?: { label: string; value: string }[];
    pumpRates?: any[];
}>(), {
    customerPO: () => ({}),
    quotations: () => [],   
    patrons: () => [],
    sites: () => [],
    mixDesigns: () => [],
    salesExecutives: () => [],
    taxes: () => [],
    pumpTypeOptions: () => [],
    pumpRates: () => [],
});


const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();
const { isAdmin , isSuperAdmin , isSassOwner} = usePermissions();
const admin = isAdmin.value || isSuperAdmin.value || isSassOwner.value;
const form = useForm({
    prefix: props.customerPO?.prefix ?? 'CPO',
    reference: props.customerPO?.reference ?? '',
    customer_po_reference: props.customerPO?.customer_po_reference ?? '',
    quotation_id: props?.customerPO?.quotation_id ?? null,
    patron_id: props.customerPO?.patron_id ?? null,
    site_id: props.customerPO?.site_id ?? null,
    sales_executive_id: props.customerPO?.sales_executive_id ?? null,
    concrete_pump: props.customerPO?.concrete_pump ?? null,
    pump_rate: Number(props.customerPO?.pump_rate || 0),
    is_tax_inclusive: props.customerPO?.is_tax_inclusive ? true : false,
    order_date: props.customerPO?.order_date ?? '',
    status: props.customerPO?.status ?? 1,
    notes: props.customerPO?.notes ?? '',
<<<<<<< HEAD
    items: [] as Array<{ id: number | null, mix_design_id: number | null, quantity: number | null, rate: number | null, tax_id: number | null, tax_amount: number | null, concrete_pump?: string | number | null, pump_rate?: number, pump_rates: Array<{ concrete_pump: string, pump_rate: number }> }>,
=======
    items: [] as Array<{ id: number | null, mix_design_id: number | null, quantity: number | null, rate: number | null, tax_id: number | null, tax_amount: number | null, concrete_pump?: number | string | null, pump_rate?: number | null }>,
>>>>>>> refs/remotes/origin/main
    mix_design_id: null as number | null,
    quantity: null as number | null,
    rate: null as number | null,
    tax_id: null as number | null,
    tax_amount: null as number | null,
    pump_rates: (props.pumpTypeOptions || []).map(pt => ({ concrete_pump: pt.value, pump_rate: 0 })) as Array<{ concrete_pump: string, pump_rate: number }>,
});

// Pre-fill items from sales order or quotation items
const itemsList = props.customerPO?.items || props.customerPO?.quotation?.items || [];
form.items = itemsList.map((item: any) => {
    return {
        id: props.customerPO?.items?.some((i: any) => i.id === item.id) ? item.id : null,
        mix_design_id: item.mix_design_id,
        quantity: Number(item.quantity),
        rate: Number(item.rate),
        tax_id: item.tax_id ?? null,
        tax_amount: item.tax_amount !== null ? Number(item.tax_amount) : 0,
<<<<<<< HEAD
        concrete_pump: item.concrete_pump ?? (savedPumpRate ? savedPumpRate.concrete_pump : null),
        pump_rate: item.pump_rate !== null && item.pump_rate !== undefined ? Number(item.pump_rate) : (savedPumpRate ? Number(savedPumpRate.pump_rate) : 0),
        pump_rates: [],
=======
        concrete_pump: item.concrete_pump ?? null,
        pump_rate: Number(item.pump_rate || 0),
>>>>>>> refs/remotes/origin/main
    };
});

if (form.items.length === 0 && !form.quotation_id) {
    form.items.push({ id: null, mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, concrete_pump: null, pump_rate: 0 });
}

if (itemsList.length === 1) {
    const item = itemsList[0];
    form.mix_design_id = item.mix_design_id;
    form.quantity = Number(item.quantity);
    form.rate = Number(item.rate);
    form.tax_id = item.tax_id ?? null;
    form.tax_amount = item.tax_amount !== null ? Number(item.tax_amount) : 0;
<<<<<<< HEAD
    const savedPumpRate = (item.pump_rates || item.pumpRates || []).find((pr: any) => pr.concrete_pump !== null && pr.concrete_pump !== undefined && pr.concrete_pump !== '');
    form.concrete_pump = item.concrete_pump ?? (savedPumpRate ? savedPumpRate.concrete_pump : (props.customerPO?.concrete_pump ?? null));
    form.pump_rate = item.pump_rate !== null && item.pump_rate !== undefined ? Number(item.pump_rate) : (savedPumpRate ? Number(savedPumpRate.pump_rate) : Number(props.customerPO?.pump_rate || 0));
=======
    form.concrete_pump = item.concrete_pump ?? null;
    form.pump_rate = Number(item.pump_rate || 0);
>>>>>>> refs/remotes/origin/main
}

// Watch quotation selection to auto-fill patron, site, and sales executive
watch(() => form.quotation_id, (newVal) => {
    if (newVal) {
        const quote = props.quotations.find((q) => Number(q.id) === Number(newVal));
        if (quote) {
            form.patron_id = quote.patron_id;
            form.site_id = quote.site_id;
            form.sales_executive_id = quote.sales_executive_id;
            form.is_tax_inclusive = quote.is_tax_inclusive ? true : false;
            const quoteItems = quote.items || [];
            form.items = quoteItems.map((item: any) => {
                return {
                    id: null,
                    mix_design_id: item.mix_design_id,
                    quantity: Number(item.quantity),
                    rate: Number(item.rate),
                    tax_id: item.tax_id ?? null,
                    tax_amount: Number(item.tax_amount ?? 0),
<<<<<<< HEAD
                    concrete_pump: item.concrete_pump ?? (savedPumpRate ? savedPumpRate.concrete_pump : null),
                    pump_rate: item.pump_rate !== null && item.pump_rate !== undefined ? Number(item.pump_rate) : (savedPumpRate ? Number(savedPumpRate.pump_rate) : 0),
                    pump_rates: [],
=======
                    concrete_pump: item.concrete_pump ?? null,
                    pump_rate: Number(item.pump_rate || 0),
>>>>>>> refs/remotes/origin/main
                };
            });
            if (quoteItems.length === 1) {
                form.mix_design_id = quoteItems[0].mix_design_id;
                form.quantity = Number(quoteItems[0].quantity);
                form.rate = Number(quoteItems[0].rate);
                form.tax_id = quoteItems[0].tax_id ?? null;
                form.tax_amount = Number(quoteItems[0].tax_amount ?? 0);
                form.concrete_pump = quoteItems[0].concrete_pump ?? null;
                form.pump_rate = Number(quoteItems[0].pump_rate || 0);
            }
        }
    } else {
        form.items = [{ id: null, mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, concrete_pump: null, pump_rate: 0 }];
        form.mix_design_id = null;
        form.quantity = null;
        form.rate = null;
        form.tax_id = null;
        form.tax_amount = null;
        form.pump_rates = (props.pumpTypeOptions || []).map(pt => ({ concrete_pump: pt.value, pump_rate: 0 }));
        form.sales_executive_id = null;
        form.is_tax_inclusive = false;
    }
});

const addItem = () => {
    form.items.push({ id: null, mix_design_id: null, quantity: null, rate: null, tax_id: null, tax_amount: 0, pump_rates: (props.pumpTypeOptions || []).map(pt => ({ concrete_pump: pt.value, pump_rate: 0 })) });
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

const page = usePage();
const customSettings = page.props.custom_settings as any;
const addPouringRatesToTotal = customSettings?.batching?.add_pouring_rates_to_total == 1;

const resolveSinglePumpRate = (isDropdownChange = false) => {
    const resolved = resolvePumpRatesLocally(form.patron_id, form.site_id);
    if (form.concrete_pump) {
        const matched = resolved.find((r: any) => String(r.concrete_pump).toLowerCase() === String(form.concrete_pump).toLowerCase());
        if (matched) {
            if (isDropdownChange) {
                form.pump_rate = Number(matched.rate || matched.pump_rate || 0);
            }
        } else {
            if (isDropdownChange) {
                form.pump_rate = 0;
            }
        }
    } else {
        if (resolved.length > 0) {
            const matched = resolved[0];
            form.concrete_pump = matched.concrete_pump;
            form.pump_rate = Number(matched.rate || matched.pump_rate || 0);
        } else {
            form.concrete_pump = null;
            form.pump_rate = 0;
        }
    }
};

// Auto pump rate resolution on Edit Form disabled per requirement (enable later if needed):
/*
watch(() => form.concrete_pump, () => {
    resolveSinglePumpRate(true);
});
watch(() => form.patron_id, () => {
    form.concrete_pump = null;
    resolveSinglePumpRate(true);
});
watch(() => form.site_id, () => {
    form.concrete_pump = null;
    resolveSinglePumpRate(true);
});
*/

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

// console.log('sdfsdf',props.customerPO);

// Watch form items and is_tax_inclusive status to dynamically update tax_amount
watch(() => [form.items, form.is_tax_inclusive], ([newItems, isTaxInclusive]) => {
    if (newItems) {
        (newItems as any).forEach((item: any) => {
            const qty = Number(item.quantity || 0);
            const rate = Number(item.rate || 0);
            const pumpRate = Number(item.pump_rate || 0);
            const pumpCharge = addPouringRatesToTotal ? pumpRate : pumpRate * qty;
            const tax = props.taxes?.find(t => Number(t.id) === Number(item.tax_id));
            const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

            if (isTaxInclusive) {
                const total = qty * rate + pumpCharge;
                item.tax_amount = Number((total - (total / (1 + taxRate / 100))).toFixed(2));
            } else {
                const untaxed = qty * rate + pumpCharge;
                item.tax_amount = Number(((untaxed * taxRate) / 100).toFixed(2));
            }
        });
    }
}, { deep: true, immediate: true });

// Watch single item fields and is_tax_inclusive status to dynamically update tax_amount
watch(() => [form.quantity, form.rate, form.tax_id, form.pump_rate, form.is_tax_inclusive], () => {
    if (form.quantity !== null && form.rate !== null) {
        const qty = Number(form.quantity || 0);
        const rate = Number(form.rate || 0);
        const pumpRate = Number(form.pump_rate || 0);
        const pumpCharge = addPouringRatesToTotal ? pumpRate : pumpRate * qty;
        const tax = props.taxes?.find(t => Number(t.id) === Number(form.tax_id));
        const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

        if (form.is_tax_inclusive) {
            const total = qty * rate + pumpCharge;
            form.tax_amount = Number((total - (total / (1 + taxRate / 100))).toFixed(2));
        } else {
            const untaxed = qty * rate + pumpCharge;
            form.tax_amount = Number(((untaxed * taxRate) / 100).toFixed(2));
        }
    }
}, { deep: true, immediate: true });

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
        resolveItemPumpRate(item, true);
    }
    resolveSinglePumpRate(true);
};

watch([() => form.patron_id, () => form.site_id], resolveAllItemsPumpRates);

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

const onSingleMixDesignChange = () => {
    const design = props.mixDesigns?.find((p: any) => p.id === form.mix_design_id);
    if (design) {
        if (!form.rate) form.rate = Number(design.rate || 0);
        
        // Remove from excluded list if re-selected/changed
        const designId = Number(form.mix_design_id);
        excludedMixDesignPumpRates.value = excludedMixDesignPumpRates.value.filter(id => id !== designId);

        resolveSinglePumpRate();
    }
};

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
        const tax = props.taxes?.find(t => Number(t.id) === Number(form.tax_id));
        const taxRate = tax ? Number(tax.tax_rate || 0) : 0;

        const res = calculateLineItemTotals({
            quantity: Number(form.quantity || 0),
            rate: Number(form.rate || 0),
            pump_rate: Number(form.pump_rate || 0),
            taxRate,
            isTaxInclusive: Boolean(form.is_tax_inclusive),
        });

        subtotal = res.materialUntaxed + res.pumpCharge;
        taxAmount = res.materialTax;
    } else {
        // Multi item mode
        (form.items || []).forEach((item: any) => {
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
    }

    return {
        subtotal: Number(subtotal.toFixed(2)),
        tax: Number(taxAmount.toFixed(2)),
        total: Number((subtotal + taxAmount).toFixed(2))
    };
});



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
    form.transform((data) => {
        const isSingleItem = !data.quotation_id && data.items.length <= 1;
        const isSingleQuote = data.quotation_id && (props.customerPO?.quotation?.items?.length === 1 || props.customerPO?.items?.length === 1);
        
        let concrete_pump = null;
        let pump_rate = 0;
        
        if (isSingleItem || isSingleQuote) {
            concrete_pump = data.concrete_pump;
            pump_rate = data.pump_rate;
        }
        
        return {
            ...data,
            concrete_pump: concrete_pump,
            pump_rate: pump_rate,
            items: data.items.map((item: any) => ({
                ...item,
                concrete_pump: item.concrete_pump ?? null,
                pump_rate: Number(item.pump_rate || 0),
<<<<<<< HEAD
                pump_rates: item.concrete_pump 
                    ? [{ concrete_pump: item.concrete_pump, pump_rate: Number(item.pump_rate || 0) }]
                    : []
=======
>>>>>>> refs/remotes/origin/main
            }))
        };
    }).put(route('customer-po.update', customerPOId), {
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

            <!-- Customer PO Reference -->
            <div class="col-span-12 md:col-span-1">
                <BaseInput
                    v-model="form.customer_po_reference"
                    label="Customer PO Ref No"
                    placeholder="Customer's PO / order reference"
                    :error="form.errors.customer_po_reference"
                />
            </div>

            <!-- Direct Sales Order: Show multi-item list -->
            <template v-if="!form.quotation_id">
                <div class="col-span-12 md:col-span-5 border-t border-gray-200 pt-4 mt-2">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2">
                                                            <CalculatorIcon class="w-3.5 h-3.5" />

                            Estimation Details
                        </span>
                            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                                <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_edit_1" :disabled="!!form.quotation_id" class="peer hidden" />
                                <label for="is_tax_inclusive_po_edit_1" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
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
                                        <BaseInputNumber v-model="item.quantity" :min="0.001" :minFractionDigits="3" />
                                    </td>
                                    <td class="p-3">
                                        <BaseInputNumber v-model="item.rate" prefix="₹" :minFractionDigits="2" />
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
                                            :options="props.pumpTypeOptions || []"
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
                                        <button
                                            type="button"
                                            @click="removeItem(idx)"
                                            class="p-1.5 text-slate-300 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-all"
                                            :disabled="form.items.length === 1 || !canDeleteCustomerPO(customerPO)"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                        <div class="col-span-12 md:col-span-2">
                            <BaseSelect
                                v-model="form.mix_design_id"
                                :options="mixDesignOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                label="Mix Design"
                                placeholder="Select Mix Design"
                                :error="form.errors.mix_design_id"
                                @update:modelValue="onSingleMixDesignChange"
                            />
                        </div>

                        <!-- Quantity -->
                        <div class="col-span-6 md:col-span-1">
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
                        <div class="col-span-6 md:col-span-1">
                            <BaseInput
                                type="number"
                                step="0.01"
                                v-model="form.rate"
                                label="Rate (₹)"
                                placeholder="0.00"
                                :error="form.errors.rate"
                            />
                        </div>

                        <!-- Pump Type -->
                        <div class="col-span-6 md:col-span-2">
                            <BaseSelect
                                v-model="form.concrete_pump"
                                :options="props.pumpTypeOptions || []"
                                optionLabel="label"
                                optionValue="value"
                                label="Pump Type"
                                placeholder="Select Type"
                                showClear
                                @update:modelValue="resolveSinglePumpRate(true)"
                            />
                        </div>

                        <!-- Pump Rate -->
                        <div class="col-span-6 md:col-span-1">
                            <BaseInput
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="form.pump_rate"
                                label="Pump Rate (₹)"
                                placeholder="0.00"
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
                                ₹{{ calculateLineItemTotals({ quantity: Number(form.quantity || 0), rate: Number(form.rate || 0), pump_rate: Number(form.pump_rate || 0), taxRate: (props.taxes?.find(t => Number(t.id) === Number(form.tax_id))?.tax_rate || 0), isTaxInclusive: Boolean(form.is_tax_inclusive) }).amountTotal.toFixed(2) }}
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
                    </span>
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/50 rounded-xl px-3 py-1 shadow-sm font-normal">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Tax Inclusive Rates</span>
                        <input type="checkbox" v-model="form.is_tax_inclusive" id="is_tax_inclusive_po_edit_3" :disabled="!!form.quotation_id" class="peer hidden" />
                        <label for="is_tax_inclusive_po_edit_3" class="relative w-9 h-5 bg-slate-200 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px]"></label>
                    </div>
                </div>
                
                <div class="col-span-12 md:col-span-5">
                    <div class="overflow-x-auto rounded-xl border border-indigo-50/50 bg-indigo-50/10 p-4">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-indigo-100 uppercase tracking-wider text-[10px] font-bold text-indigo-700">
                                    <th class="p-2">Mix Design</th>
                                    <th class="p-2 text-right">Quantity</th>
                                    <th class="p-2 text-right">Rate</th>
                                    <th class="p-2 text-center">Tax</th>
                                    <th class="p-2 text-center">Pump Type</th>
                                    <th class="p-2 text-right">Pump Rate</th>
                                    <th class="p-2 text-right">Tax Amt</th>
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
                                    <td class="p-2 text-center font-mono">
                                        {{ props.taxes?.find(t => Number(t.id) === Number(item.tax_id)) ? `${props.taxes.find(t => Number(t.id) === Number(item.tax_id)).tax_name} (${props.taxes.find(t => Number(t.id) === Number(item.tax_id)).tax_rate}%)` : '-' }}
                                    </td>
                                    <td class="p-2 text-center font-mono">
                                        {{ props.concretePumpOptions?.find(opt => Number(opt.value) === Number(item.concrete_pump))?.label || '-' }}
                                    </td>
                                    <td class="p-2 text-right font-mono">
                                        {{ item.pump_rate ? `₹${Number(item.pump_rate).toLocaleString('en-IN', { minimumFractionDigits: 2 })}` : '-' }}
                                    </td>
                                    <td class="p-2 text-right font-mono">
                                        ₹{{ calculateLineItemTotals({ quantity: Number(item.quantity || 0), rate: Number(item.rate || 0), pump_rate: Number(item.pump_rate || 0), taxRate: (props.taxes?.find(t => Number(t.id) === Number(item.tax_id))?.tax_rate || 0), isTaxInclusive: Boolean(form.is_tax_inclusive) }).taxAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="p-2 text-right font-mono font-bold text-indigo-900">
                                        ₹{{ calculateLineItemTotals({ quantity: Number(item.quantity || 0), rate: Number(item.rate || 0), pump_rate: Number(item.pump_rate || 0), taxRate: (props.taxes?.find(t => Number(t.id) === Number(item.tax_id))?.tax_rate || 0), isTaxInclusive: Boolean(form.is_tax_inclusive) }).amountTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>


            <!-- ── Pump Rates per Mix Design ── -->
            <!-- <div v-if="uniqueSelectedMixDesignIds.length && pumpTypeOptions && pumpTypeOptions.length" class="col-span-12 md:col-span-5 mt-4 rounded-xl border border-indigo-200 bg-indigo-100/30 p-5 shadow-sm">
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
                                    <template v-if="form.quotation_id && (customerPO?.quotation?.items?.length === 1 || customerPO?.items?.length === 1)">
                                        <template v-if="Number(form.mix_design_id) === Number(designId)">
                                            <tr v-for="pr in form.pump_rates" :key="pr.concrete_pump" class="hover:bg-indigo-50/20 transition-colors p-1">
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
                                    <template v-else>
                                        <template v-for="item in form.items" :key="String(item.mix_design_id) + '-cpo-pr-edit'">
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
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div> -->

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
                :disabled="props.customerPO.has_salesorders && !admin"
                mode="update"
                updateLabel="Update Customer PO"
                :loading="form.processing"
                @submit="submit"
                @cancel="emit('cancel')"
            />
        </div>
    </div>
</template>