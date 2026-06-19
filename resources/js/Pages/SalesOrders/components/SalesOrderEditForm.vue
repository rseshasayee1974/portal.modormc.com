<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Swal from 'sweetalert2';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';

const props = withDefaults(defineProps<{
    salesOrder?: any;
    quotations?: any[];
    patrons?: any[];
    sites?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    concretePumpOptions?: any[];
}>(), {
    salesOrder: () => ({}),
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
 

const form = useForm({
    quotation_id: props?.salesOrder?.quotation_id ?? null,
    patron_id: props.salesOrder?.patron_id ?? null,
    site_id: props.salesOrder?.site_id ?? null,
    sales_executive_id: props.salesOrder?.sales_executive_id ?? null,
    concrete_pump: props.salesOrder?.concrete_pump ?? null,
    order_date: props.salesOrder?.order_date ?? '',
    status: props.salesOrder?.status ?? 1,
    items: [] as Array<{ mix_design_id: number | null, quantity: number | null, rate: number | null }>,
    mix_design_id: null as number | null,
    quantity: null as number | null,
    rate: null as number | null,
});

// Pre-fill items from sales order or quotation items
const itemsList = props.salesOrder?.items || props.salesOrder?.quotation?.items || [];
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
        form.items.splice(index, 1);
    }
};


// Filter sites by selected patron
const filteredSites = computed(() => {
    return props.sites;
});

const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));

// Quotation dropdown options with labels
const quotationOptions = computed(() => {
    // Filter out quotations that have an active sales order
    const list = props.quotations.filter((q) => !q.is_salesorder || Number(q.is_salesorder) !== 1);
    
    // Ensure the current sales order's linked quotation is included in the options list
    if (props.salesOrder?.quotation) {
        const exists = list.some((q) => Number(q.id) === Number(props.salesOrder.quotation.id));
        if (!exists) {
            list.push(props.salesOrder.quotation);
        }
    }

    return [
        { label: 'None (Direct Sales Order)', value: null },
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
    const salesOrderId = props.salesOrder?.id;

    if (!salesOrderId) {
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
        // Direct Sales Order: validate items
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

    form.put(route('salesorders.update', salesOrderId), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Sales Order updated successfully.',
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
            <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800">Edit Sales Order</h3>
            <span class="font-mono text-xs font-bold text-amber-600">
                REF # : {{ salesOrder.quotation?.reference || 'Direct Sales Order' }}
            </span>
        </div>

        <div class="grid grid-cols-12 md:grid-cols-5 gap-x-4 gap-y-3">
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
                    label="Site"
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
                                :options="mixDesigns"
                                optionLabel="design_name"
                                optionValue="id"
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
                                :disabled="form.items.length === 1"
                                @click="removeItem(idx)"
                                v-tooltip.top="'Remove'"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- Quotation-linked Sales Order with single item -->
            <template v-else-if="form.quotation_id && (salesOrder?.quotation?.items?.length === 1 || salesOrder?.items?.length === 1)">
                <div class="col-span-12 md:col-span-5 mt-2 border-t border-gray-200 pt-4">
                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-800">Item Details</span>
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseSelect
                        v-model="form.mix_design_id"
                        :options="mixDesigns"
                        optionLabel="design_name"
                        optionValue="id"
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
        </div>

        <div class="mt-5 flex justify-end gap-2 border-t border-gray-200 pt-4">
            <BaseFormActions
                :disabled="props.salesOrder.has_workorders"
                mode="update"
                updateLabel="Update Sales Order"
                :loading="form.processing"
                @submit="submit"
                @cancel="emit('cancel')"
            />
        </div>
    </div>
</template>