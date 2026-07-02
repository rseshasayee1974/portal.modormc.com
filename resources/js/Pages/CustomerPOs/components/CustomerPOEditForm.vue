<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Swal from 'sweetalert2';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import { usePermissions } from '@/Composables/usePermissions';

const props = withDefaults(defineProps<{
    customerPO?: any;
    quotations?: any[];
    patrons?: any[];
    sites?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    concretePumpOptions?: any[];
}>(), {
    customerPO: () => ({}),
    quotations: () => [],   
    patrons: () => [],
    sites: () => [],
    mixDesigns: () => [],
    salesExecutives: () => [],
    concretePumpOptions: () => [],
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const { isAdmin } = usePermissions();

const form = useForm({
    prefix: props.customerPO?.prefix ?? 'CPO',
    reference: props.customerPO?.reference ?? '',
    quotation_id: props?.customerPO?.quotation_id ?? null,
    patron_id: props.customerPO?.patron_id ?? null,
    site_id: props.customerPO?.site_id ?? null,
    sales_executive_id: props.customerPO?.sales_executive_id ?? null,
    concrete_pump: props.customerPO?.concrete_pump ?? null,
    order_date: props.customerPO?.order_date ?? '',
    status: props.customerPO?.status ?? 1,
    items: [] as Array<{ mix_design_id: number | null, quantity: number | null, rate: number | null }>,
    mix_design_id: null as number | null,
    quantity: null as number | null,
    rate: null as number | null,
});

// Pre-fill items from sales order or quotation items
const itemsList = props.customerPO?.items || props.customerPO?.quotation?.items || [];
form.items = itemsList.map((item: any) => ({
    mix_design_id: item.mix_design_id,
    quantity: Number(item.quantity),
    rate: Number(item.rate),
}));

if (form.items.length === 0 && !form.quotation_id) {
    form.items.push({ mix_design_id: null, quantity: null, rate: null });
}

if (itemsList.length === 1) {
    const item = itemsList[0];
    form.mix_design_id = item.mix_design_id;
    form.quantity = Number(item.quantity);
    form.rate = Number(item.rate);
}

// Watch quotation selection to auto-fill patron, site, and sales executive
watch(() => form.quotation_id, (newVal) => {
    if (newVal) {
        const quote = props.quotations.find((q) => Number(q.id) === Number(newVal));
        if (quote) {
            form.patron_id = quote.patron_id;
            form.site_id = quote.site_id;
            form.sales_executive_id = quote.sales_executive_id;
            const quoteItems = quote.items || [];
            form.items = quoteItems.map((item: any) => ({
                mix_design_id: item.mix_design_id,
                quantity: Number(item.quantity),
                rate: Number(item.rate),
            }));
            if (quoteItems.length === 1) {
                form.mix_design_id = quoteItems[0].mix_design_id;
                form.quantity = Number(quoteItems[0].quantity);
                form.rate = Number(quoteItems[0].rate);
            }
        }
    } else {
        form.items = [{ mix_design_id: null, quantity: null, rate: null }];
        form.mix_design_id = null;
        form.quantity = null;
        form.rate = null;
        form.sales_executive_id = null;
    }
});

const addItem = () => {
    form.items.push({ mix_design_id: null, quantity: null, rate: null });
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
// Filter sites by selected patron and unloading type
const filteredSites = computed(() => {
    return (props.sites || []).filter((s: any) => {
        const matchesPatron = !form.patron_id || s.patron_id === form.patron_id;
        const matchesType = s.type === 'unloading';
        return matchesType && matchesPatron;
    });
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
    
    // Check form items list
    if (form.items && form.items.length) {
        form.items.forEach(item => {
            if (item.mix_design_id) {
                ids.add(Number(item.mix_design_id));
            }
        });
    }
    
    // Check form single item
    if (form.mix_design_id) {
        ids.add(Number(form.mix_design_id));
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
            <div class="col-span-12 md:col-span-1">
                <BaseInput
                    v-model="form.prefix"
                    label="PO Prefix"
                    placeholder="e.g. CPO"
                    :error="form.errors.prefix"
                />
            </div>
            <div class="col-span-12 md:col-span-1">
                <BaseInput
                    v-model="form.reference"
                    label="PO Number / Ref"
                    placeholder="Auto-generated if blank"
                    :error="form.errors.reference"
                />
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
                        <span class="text-xs font-bold uppercase tracking-wide text-indigo-800">Mix Design Items</span>
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
                        <div class="col-span-12 md:col-span-5">
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
                        <div class="col-span-4 md:col-span-2">
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
                        <div class="col-span-4 md:col-span-2">
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
                    
                        <!-- Amount -->
                        <div class="col-span-3 md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700">Amount</label>
                            <div class="h-8 flex items-center text-sm font-semibold text-indigo-700">
                                ₹{{ ((Number(item.quantity) || 0) * (Number(item.rate) || 0)).toFixed(2) }}
                            </div>
                        </div>
                    
                        <!-- Delete -->
                        <div class="col-span-1 flex justify-end pt-6">
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
                <div class="col-span-12 md:col-span-5 mt-2 border-t border-gray-200 pt-4">
                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-800">Item Details</span>
                </div>

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
                    />
                </div>

                <div class="col-span-12 md:col-span-1">
                    <BaseInput
                        type="number"
                        step="0.001"
                        v-model="form.quantity"
                        label="Qty (m³)"
                        placeholder="0.000"
                        :error="form.errors.quantity"
                    />
                </div>

                <div class="col-span-12 md:col-span-1">
                    <BaseInput
                        type="number"
                        step="0.01"
                        v-model="form.rate"
                        label="Rate (₹)"
                        placeholder="0.00"
                        :error="form.errors.rate"
                    />
                </div>
            </template>

            <!-- Quotation-linked Customer PO with multiple items -->
            <template v-else>
                <div class="col-span-12 md:col-span-5 mt-2 border-t border-gray-200 pt-4">
                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-800">Mix Design Items (Loaded from Quotation)</span>
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
        </div>

        <div class="mt-5 flex justify-end gap-2 border-t border-gray-200 pt-4">
            <BaseFormActions
                :disabled="props.customerPO.has_salesorders && !isAdmin"
                mode="update"
                updateLabel="Update Customer PO"
                :loading="form.processing"
                @submit="submit"
                @cancel="emit('cancel')"
            />
        </div>
    </div>
</template>