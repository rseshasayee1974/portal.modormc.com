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
    quotation_id: null as number | null,
    patron_id: null as number | null,
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
    return props.sites;
});

const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));

// Quotation dropdown options with labels
const quotationOptions = computed(() => {
    // Filter out quotations that have an active sales order
    const list = props.quotations.filter((q) => !q.is_salesorder || Number(q.is_salesorder) !== 1);
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
    form.post(route('salesorders.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Sales Order created successfully.',
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
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Create Sales Order</h2>
            </div>
        </div>

       <!-- Form Body -->
<!-- Form Body -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-5 p-5">

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

    <!-- Loading Site -->
    <div>
        <BaseSelect
            v-model="form.site_id"
            :options="filteredSites"
            optionLabel="name"
            optionValue="id"
            filter
            label="Loading Site"
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
            class="col-span-full p-4"
        >
            <div class="grid grid-cols-12 gap-2 items-end">

                <!-- Mix Design -->
                <div class="col-span-12 lg:col-span-5">
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
                <div class="col-span-12 md:col-span-3">
                    <BaseInput
                        v-model="item.quantity"
                        type="number"
                        step="0.001"
                        label="Quantity (m³)"
                        placeholder="Quantity"
                        :error="form.errors[`items.${idx}.quantity`]"
                    />
                </div>

                <!-- Rate -->
                <div class="col-span-12 md:col-span-3">
                    <BaseInput
                        v-model="item.rate"
                        type="number"
                        step="0.01"
                        label="Rate (₹)"
                        placeholder="Rate"
                        :error="form.errors[`items.${idx}.rate`]"
                    />
                </div>

                <!-- Delete -->
                <div class="col-span-12 md:col-span-1 flex justify-center">
                    <Button
                        icon="pi pi-trash"
                        severity="danger"
                        rounded
                        text
                        :disabled="form.items.length === 1"
                        @click="removeItem(idx)"
                    />
                </div>

            </div>
        </div>

    </template>

</div>

        <!-- Action Button -->
        <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30 px-4 py-3">
            <Button
                label="Create Sales Order"
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
