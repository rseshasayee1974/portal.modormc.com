<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Swal from 'sweetalert2';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = withDefaults(defineProps<{
    salesOrder?: any;
    quotations?: any[];
    patrons?: any[];
    sites?: any[];
    mixDesigns?: any[];
}>(), {
    salesOrder: () => ({}),
    quotations: () => [],   
    patrons: () => [],
    sites: () => [],
    mixDesigns: () => [],
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();
 

const form = useForm({
    quotation_id: props.salesOrder?.quotation_id ?? null,
    patron_id: props.salesOrder?.patron_id ?? null,
    site_id: props.salesOrder?.site_id ?? null,
    order_date: props.salesOrder?.order_date ?? '',
    status: props.salesOrder?.status ?? 1,
    mix_design_id: null as number | null,
    quantity: null as number | null,
    rate: null as number | null,
});

// Watch quotation selection to auto-fill patron and site
watch(() => form.quotation_id, (newVal) => {
    if (newVal) {
        const quote = props.quotations.find((q) => Number(q.id) === Number(newVal));
        if (quote) {
            form.patron_id = quote.patron_id;
            form.site_id = quote.site_id;
        }
    } else {
        form.mix_design_id = null;
        form.quantity = null;
        form.rate = null;
    }
});

// Pre-fill item details if there is a single item
if (props.salesOrder?.quotation?.items && props.salesOrder.quotation.items.length === 1) {
    const item = props.salesOrder.quotation.items[0];
    form.mix_design_id = item.mix_design_id;
    form.quantity = Number(item.quantity);
    form.rate = Number(item.rate);
}

const isSingleItemOrDirect = computed(() => {
    return !props.salesOrder?.quotation || (props.salesOrder.quotation.items && props.salesOrder.quotation.items.length === 1);
});


// Filter sites by selected patron
const filteredSites = computed(() => {
    return props.sites;
});

// Quotation dropdown options with labels
const quotationOptions = computed(() => {
    return [
        { label: 'None (Direct Sales Order)', value: null },
        ...props.quotations.map((q) => {
            const patronName = props.patrons.find((p) => Number(p.id) === Number(q.patron_id))?.legal_name || 'Unknown';
            return {
                label: `${q.reference || 'Draft'} - ${patronName} (₹${Number(q.amount_total || 0).toLocaleString('en-IN')})`,
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
                    v-model="form.patron_id"
                    :options="patrons"
                    optionLabel="legal_name"
                    optionValue="id"
                    filter
                    label="Customer"
                    placeholder="Select Customer"
                    :error="form.errors.patron_id"
                    :disabled="!!form.quotation_id"
                />
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
                    :error="form.errors.site_id"
                    :disabled="!!form.quotation_id"
                />
            </div>

            <div class="col-span-12 md:col-span-4">
                <BaseInput
                    type="date"
                    v-model="form.order_date"
                    label="Order Date"
                    :error="form.errors.order_date"
                />
            </div>

            <div class="col-span-12 md:col-span-4">
                <BaseSelect
                    v-model="form.status"
                    :options="[
                        { label: 'Draft', value: 0 },
                        { label: 'Confirmed', value: 1 },
                        { label: 'Partial Dispatch', value: 2 },
                        { label: 'Completed', value: 3 }
                    ]"
                    optionLabel="label"
                    optionValue="value"
                    label="Status"
                    placeholder="Select Status"
                    :error="form.errors.status"
                />
            </div>

            <!-- Conditional Mix Design fields -->
            <template v-if="isSingleItemOrDirect">
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
