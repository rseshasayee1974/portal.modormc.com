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
}>(), {
    salesOrder: () => ({}),
    quotations: () => [],   
    patrons: () => [],
    sites: () => [],
    mixDesigns: () => [],
    salesExecutives: () => [],
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
    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4 text-left">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800">Edit Sales Order</h3>
            <span class="font-mono text-xs font-bold text-amber-600">
                REF # : {{ salesOrder.quotation?.reference || 'Direct Sales Order' }}
            </span>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-4">
                <BaseSelect
                    v-model="form.quotation_id"
                    :options="quotationOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    label="Quotation"
                    placeholder="Select Quotation"
                    :error="form.errors.quotation_id"
                />
            </div>
            <div class="col-span-12 md:col-span-4">
                <BaseSelect
                    v-model="form.sales_executive_id"
                    :options="salesExecutives"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    label="Sales Executive"
                    placeholder="Select Sales Executive"
                    :error="form.errors.sales_executive_id"
                />
            </div>
            <div class="col-span-12 md:col-span-4">
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
                <span v-if="form.quotation_id" class="text-[10px] text-indigo-600 mt-1 block font-medium">Locked to Quotation Customer</span>
            </div>

            <div class="col-span-12 md:col-span-4">
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
                <span v-if="form.quotation_id" class="text-[10px] text-indigo-600 mt-1 block font-medium">Locked to Quotation Site</span>
            </div>

            <div class="col-span-12 md:col-span-4">
                
                <BaseDatePicker
                fluid
                hourFormat="24"
                label="Order Date"
                v-model="form.order_date"
                :error="form.errors.order_date"
                />
            </div>

            <div class="col-span-12 md:col-span-4">
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

            <!-- Conditional Mix Design fields -->
            <!-- Direct Sales Order: Show multi-item list -->
            <template v-if="!form.quotation_id">
                <div class="col-span-12 my-2 border-t border-indigo-100/50 pt-2">
                    <div class="flex items-center justify-between">
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

                <div v-for="(item, idx) in form.items" :key="idx" class="col-span-12 grid grid-cols-12 gap-4 items-end bg-indigo-50/20 p-4 rounded-xl border border-indigo-100/30 relative">
                    <div class="col-span-12 md:col-span-5">
                        <BaseSelect
                            v-model="item.mix_design_id"
                            :options="mixDesigns"
                            optionLabel="design_name"
                            optionValue="id"
                            filter
                            label="Mix Design"
                            placeholder="Select Mix Design"
                            :error="form.errors['items.' + idx + '.mix_design_id']"
                        />
                    </div>

                    <div class="col-span-12 md:col-span-3">
                        <BaseInput
                            type="number"
                            step="0.001"
                            v-model="item.quantity"
                            label="Quantity (m³)"
                            placeholder="Enter Quantity"
                            :error="form.errors['items.' + idx + '.quantity']"
                        />
                    </div>

                    <div class="col-span-12 md:col-span-3">
                        <BaseInput
                            type="number"
                            step="0.01"
                            v-model="item.rate"
                            label="Rate (₹)"
                            placeholder="Enter Rate"
                            :error="form.errors['items.' + idx + '.rate']"
                        />
                    </div>

                    <div class="col-span-12 md:col-span-1 flex justify-center pb-1">
                        <Button
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            rounded
                            :disabled="form.items.length === 1"
                            @click="removeItem(idx)"
                        />
                    </div>
                </div>
            </template>

            <!-- Quotation-linked Sales Order with single item -->
            <template v-else-if="!form.quotation_id && (salesOrder?.quotation?.items?.length === 1 || salesOrder?.items?.length === 1)">
                <div class="col-span-12 my-2 border-t border-indigo-100/50 pt-2">
                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-800">Item Details</span>
                </div>

                <div class="col-span-12 md:col-span-4">
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

                <div class="col-span-12 md:col-span-4">
                    <BaseInput
                        type="number"
                        step="0.001"
                        v-model="form.quantity"
                        label="Quantity (m³)"
                        placeholder="Enter Quantity"
                        :error="form.errors.quantity"
                    />
                </div>

                <div class="col-span-12 md:col-span-4">
                    <BaseInput
                        type="number"
                        step="0.01"
                        v-model="form.rate"
                        label="Rate (₹)"
                        placeholder="Enter Rate"
                        :error="form.errors.rate"
                    />
                </div>
            </template>
        </div>

        <div class="mt-4 border-t border-indigo-100 pt-3">
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
