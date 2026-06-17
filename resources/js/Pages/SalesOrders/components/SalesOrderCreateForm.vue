<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { PlusCircleIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = withDefaults(defineProps<{
    patrons?: any[];
    sites?: any[];
    quotations?: any[];
    mixDesigns?: any[];
}>(), {
    patrons: () => [],
    sites: () => [],
    quotations: () => [],
    mixDesigns: () => [],
});

const form = useForm({
    quotation_id: null as number | null,
    patron_id: null as number | null,
    site_id: null as number | null,
    order_date: new Date().toISOString().split('T')[0],
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
        form.patron_id = null;
        form.site_id = null;
        form.mix_design_id = null;
        form.quantity = null;
        form.rate = null;
    }
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
        <div class="grid grid-cols-12 gap-5 p-5">
            <!-- Source Quotation -->
            <div class="col-span-12 md:col-span-4">
                <BaseSelect
                    v-model="form.quotation_id"
                    :options="quotationOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    label="Source Quotation (Optional)"
                    placeholder="Select Quotation"
                    :error="form.errors.quotation_id"
                />
            </div>

            <!-- Customer (Patron) -->
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

            <!-- Site -->
            <div class="col-span-12 md:col-span-4">
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
                <span v-if="form.quotation_id" class="text-[10px] text-indigo-600 mt-1 block font-medium">Locked to Quotation Site</span>
            </div>

            <!-- Order Date -->
            <div class="col-span-12 md:col-span-4">
                <BaseDatePicker
                fluid
                 hourFormat="24"
                    v-model="form.order_date"
                    label="Order Date"
                    :error="form.errors.order_date"
                />
            </div>

            <!-- Mix Design, Quantity, Rate (Only for Direct Sales Order) -->
            <template v-if="!form.quotation_id">
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
