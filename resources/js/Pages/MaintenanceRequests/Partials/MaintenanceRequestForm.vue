<script setup lang="ts">
import { watch } from 'vue';
import { entityDateTime } from '@/Utils/entityDateTime';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import {
    WrenchScrewdriverIcon, PencilSquareIcon, PlusIcon, TrashIcon,
    CheckIcon, XMarkIcon
} from '@heroicons/vue/24/outline';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = defineProps<{
    form: any;
    editingId: number | null;
    machineOptions: Array<{ label: string; value: number }>;
    vendorOptions: Array<{ label: string; value: number }>;
    userOptions: Array<{ label: string; value: number }>;
    taxOptions: Array<{ label: string; value: number; rate?: number }>;
    productOptions: Array<{ label: string; value: number }>;
    unitOptions: Array<{ label: string; value: number }>;
    maintenanceTypes: Array<{ label: string; value: number }>;
    priorityLevels: Array<{ label: string; value: number }>;
    requestStatuses: Array<{ label: string; value: number }>;
}>();

const emit = defineEmits<{
    (e: 'submit'): void;
    (e: 'cancel'): void;
}>();

const addLine = () => {
    props.form.lines.push({
        name: '',
        product_quantity: '1',
        date_planned: entityDateTime(),
        product_uom: null,
        product_id: null,
        description: '',
        price_unit: 0,
        price_subtotal: 0,
        price_total: 0,
        tax_id: null,
        price_tax: 0,
        status: 1,
        priority: 0,
        invoiced_quantity: '0',
        received_quantity: '0',
        received_price: 0,
        partner_id: null,
    });
};

const removeLine = (index: number) => {
    props.form.lines.splice(index, 1);
    calculateFinalTotals();
};

const calculateFinalTotals = () => {
    let untaxed = 0;
    let taxTotal = 0;
    props.form.lines.forEach((line: any) => {
        untaxed += Number(line.price_subtotal || 0);
        taxTotal += Number(line.price_tax || 0);
    });
    props.form.amount_untaxed = Number(untaxed.toFixed(2));
    props.form.amount_tax = Number(taxTotal.toFixed(2));

    const discount = Number(props.form.discount_amount || 0);
    const shipping = Number(props.form.shipping_charges || 0);
    const adjustment = Number(props.form.adjustment || 0);
    const rounding = Number(props.form.rounding_value || 0);

    props.form.amount_total = Number((props.form.amount_untaxed + props.form.amount_tax + shipping - discount + adjustment + rounding).toFixed(2));
};

const calculateLineTotals = (index: number) => {
    const line = props.form.lines[index];
    if (!line) return;
    const qty = parseFloat(line.product_quantity) || 0;
    const unitPrice = parseFloat(line.price_unit as any) || 0;
    const isInclusive = Boolean(props.form.tax_inclusive);

    let taxRate = 0;
    if (line.tax_id) {
        const tax = props.taxOptions.find((t) => t.value === line.tax_id);
        if (tax && tax.rate !== undefined) taxRate = parseFloat(tax.rate as any) || 0;
    }

    const gross = qty * unitPrice;

    if (isInclusive) {
        line.price_subtotal = taxRate > 0 ? Number(((gross * 100) / (100 + taxRate)).toFixed(2)) : Number(gross.toFixed(2));
        line.price_tax = Number((gross - line.price_subtotal).toFixed(2));
        line.price_total = Number(gross.toFixed(2));
    } else {
        line.price_subtotal = Number(gross.toFixed(2));
        line.price_tax = Number((line.price_subtotal * (taxRate / 100)).toFixed(2));
        line.price_total = Number((line.price_subtotal + line.price_tax).toFixed(2));
    }

    calculateFinalTotals();
};

const recalculateAllLines = () => {
    props.form.lines.forEach((_: any, idx: number) => calculateLineTotals(idx));
    calculateFinalTotals();
};

watch(() => props.form.tax_inclusive, recalculateAllLines);
watch(
    () => [props.form.discount_amount, props.form.shipping_charges, props.form.adjustment, props.form.rounding_value],
    calculateFinalTotals
);
</script>

<template>
    <div class="bg-white dark:bg-slate-900 my-6 rounded-2xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300"
        :class="editingId ? 'ring-2 ring-indigo-500 ring-offset-4 dark:ring-offset-slate-950' : ''">
        <!-- Card Header -->
        <div
            class="px-6 py-5 bg-slate-50/50 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                    <WrenchScrewdriverIcon v-if="!editingId" class="w-5 h-5" />
                    <PencilSquareIcon v-else class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider">
                        {{ editingId ? 'Modify Maintenance Request' : 'Register Maintenance Request' }}
                    </h2>
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mt-0.5">
                        Add ticket details, scheduled tasks, items, and service vendors
                    </p>
                </div>
            </div>
            <span v-if="editingId"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                Editing Ticket #{{ editingId }}
            </span>
        </div>

        <div class="p-6">
            <form @submit.prevent="emit('submit')" class="flex flex-col gap-2">
                <!-- 5 Columns Grid Form Layout -->
                <div class="space-y-4">
                    <!-- Row 1: 5 Columns -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div>
                            <BaseInput v-model="form.name" label="Ticket Subject" required
                                placeholder="Maintenance label" :error="form.errors.name" />
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Machine
                                / Asset <span class="text-red-500">*</span></label>
                            <BaseSelect v-model="form.machine_id" :options="machineOptions" required optionLabel="label"
                                optionValue="value" placeholder="Select Asset" :error="form.errors.machine_id" />
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Responsible
                                Handler <span class="text-red-500">*</span></label>
                            <BaseSelect v-model="form.responsible_id" :options="userOptions" required
                                optionLabel="label" optionValue="value" placeholder="Select Person"
                                :error="form.errors.responsible_id" />
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Repair
                                Vendor</label>
                            <BaseSelect v-model="form.repair_vendor_id" :options="vendorOptions" required
                                optionLabel="label" optionValue="value" placeholder="Select Vendor"
                                :error="form.errors.repair_vendor_id" />
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Maintenance
                                Type</label>
                            <BaseSelect v-model="form.maintanence_type" :options="maintenanceTypes" optionLabel="label"
                                optionValue="value" :error="form.errors.maintanence_type" />
                        </div>
                    </div>

                    <!-- Row 2: 5 Columns -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Priority
                                Level</label>
                            <BaseSelect v-model="form.priority" :options="priorityLevels" optionLabel="label"
                                optionValue="value" :error="form.errors.priority" />
                        </div>
                        <div>
                            <BaseInput v-model="form.repair_location" label="Repair Location" required
                                placeholder="Workshop / Site" :error="form.errors.repair_location" />
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Service
                                Km / Hours</label>
                            <BaseInputNumber v-model="form.service_km" placeholder="0.00"
                                :error="form.errors.service_km" />
                        </div>
                        <div>
                            <BaseDatePicker v-model="form.dead_line" label="Deadline Date" required
                                :error="form.errors.dead_line" />
                        </div>
                        <div>
                            <BaseDatePicker v-model="form.start_date" label="Start Date" required
                                :error="form.errors.start_date" />
                        </div>
                    </div>

                    <!-- Row 3: 5 Columns -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div>
                            <BaseDatePicker v-model="form.end_date" label="End Date" required
                                :error="form.errors.end_date" />
                        </div>
                        <div>
                            <BaseInput v-model="form.bill_no" label="Invoice / Bill No" placeholder="Invoice reference"
                                :error="form.errors.bill_no" />
                        </div>
                        <div>
                            <BaseInput v-model="form.order_no" label="PO / Order No" placeholder="PO reference"
                                :error="form.errors.order_no" />
                        </div>
                        <div>
                            <BaseInput v-model="form.max_idle_days" label="Max Idle Days" placeholder="e.g. 3"
                                :error="form.errors.max_idle_days" />
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Ticket
                                Status</label>
                            <BaseSelect v-model="form.status" :options="requestStatuses" optionLabel="label"
                                optionValue="value" :error="form.errors.status" />
                        </div>
                    </div>

                    <!-- Row 4: Problem Details (Full Width) -->
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <BaseInput v-model="form.description" label="Problem Details & Description" required
                                placeholder="Detailed description of machine fault, required repairs, or scope of service..."
                                :error="form.errors.description" />
                        </div>
                    </div>
                </div>

                <!-- Lines Form Section -->
                <div class="mt-1 border-t border-slate-100 dark:border-slate-800 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                                Required Parts & Service Items (Lines)
                            </h3>
                            <p v-if="form.errors.lines" class="text-red-500 text-[10px] mt-1 font-medium">{{
                                form.errors.lines }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <label
                                class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 select-none">
                                <input type="checkbox" v-model="form.tax_inclusive" :true-value="1" :false-value="0"
                                    class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 dark:bg-slate-800 cursor-pointer" />
                                <span>Tax Inclusive Rates</span>
                            </label>
                            <button type="button" @click="addLine"
                                class="flex items-center gap-1.5 px-3.5 py-2 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/60 rounded-xl text-[10px] font-black uppercase tracking-wider transition-colors shadow-sm">
                                <PlusIcon class="w-3.5 h-3.5 stroke-[2.5]" /> Add Part / Service
                            </button>
                        </div>
                    </div>

                    <div v-if="form.lines.length === 0"
                        class="py-10 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl flex flex-col items-center justify-center bg-slate-50/40 dark:bg-slate-900/40">
                        <WrenchScrewdriverIcon class="w-8 h-8 text-slate-300 dark:text-slate-700 mb-2" />
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">No maintenance line
                            items added yet</p>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">Click "Add Part /
                            Service" to list parts, materials, or labor charges.</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr
                                        class="bg-slate-50 dark:bg-slate-800/80 text-[10px] font-black uppercase text-slate-500 tracking-wider border-b border-slate-200 dark:border-slate-800">
                                        <th class="py-2.5 px-2">Item Label</th>
                                        <th class="py-2.5 px-1.5 w-[100px]">Qty</th>
                                        <th class="py-2.5 px-1.5 w-[130px]">Unit Price (₹)</th>
                                        <th class="py-2.5 px-1.5 w-[180px]">Tax Rate</th>
                                        <th class="py-2.5 px-1.5 w-[130px]">Total Price</th>
                                        <th class="py-2.5 px-2 w-[200px]">Vendor / Partner</th>
                                        <th class="py-2.5 px-1 w-[40px] text-center">Remove</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                                    <tr v-for="(line, index) in form.lines" :key="index"
                                        class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="p-1.5">
                                            <BaseInput v-model="line.name" placeholder="Item title" class="!h-8 text-xs"
                                                :error="form.errors[`lines.${index}.name`]" />
                                        </td>
                                        <td class="p-1.5">
                                            <BaseInput v-model="line.product_quantity" placeholder="1"
                                                class="!h-8 text-xs font-mono" @input="calculateLineTotals(index)"
                                                :error="form.errors[`lines.${index}.product_quantity`]" />
                                        </td>
                                        <td class="p-1.5">
                                            <BaseInput v-model="line.price_unit" placeholder="0"
                                                class="!h-8 text-xs font-mono" @input="calculateLineTotals(index)"
                                                :error="form.errors[`lines.${index}.price_unit`]" />
                                        </td>
                                        <td class="p-1.5">
                                            <BaseSelect v-model="line.tax_id" :options="taxOptions" optionLabel="label"
                                                optionValue="value" placeholder="No Tax" class="!h-8 text-xs"
                                                @change="calculateLineTotals(index)"
                                                :error="form.errors[`lines.${index}.tax_id`]" />
                                        </td>
                                        <td class="p-1.5 align-middle">
                                            <span
                                                class="text-xs font-mono font-bold text-slate-700 dark:text-slate-200">
                                                ₹{{ Number(line.price_total || 0).toLocaleString('en-IN',
                                                    { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                            </span>
                                        </td>
                                        <td class="p-1.5">
                                            <BaseSelect v-model="line.partner_id" :options="vendorOptions"
                                                optionLabel="label" optionValue="value" placeholder="Select Vendor"
                                                class="!h-8 text-xs"
                                                :error="form.errors[`lines.${index}.partner_id`]" />
                                        </td>
                                        <td class="p-1.5 text-center align-middle">
                                            <button type="button" @click="removeLine(index)"
                                                class="p-1 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/50 rounded-lg transition-colors"
                                                title="Remove Line">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Financial Summary Box -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                            <div></div>
                            <div
                                class="bg-slate-50/80 dark:bg-slate-800/50 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-3">
                                <div
                                    class="flex justify-between items-center text-xs font-bold text-slate-600 dark:text-slate-300">
                                    <span>Untaxed Subtotal</span>
                                    <span class="font-mono text-sm">₹{{ Number(form.amount_untaxed ||
                                        0).toLocaleString('en-IN', {
                                            minimumFractionDigits: 2, maximumFractionDigits: 2
                                        }) }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                    <span>Taxes Amount (+)</span>
                                    <span class="font-mono text-sm">₹{{ Number(form.amount_tax ||
                                        0).toLocaleString('en-IN', {
                                            minimumFractionDigits: 2, maximumFractionDigits: 2
                                        }) }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center text-xs font-semibold text-slate-600 dark:text-slate-300 gap-4">
                                    <span>Shipping Charges (+)</span>
                                    <BaseInputNumber v-model="form.shipping_charges" placeholder="0.00"
                                        class="!w-32 !h-8 text-xs font-mono" />
                                </div>
                                <div
                                    class="flex justify-between items-center text-xs font-semibold text-rose-600 dark:text-rose-400 gap-4">
                                    <span>Discount Amount (-)</span>
                                    <BaseInputNumber v-model="form.discount_amount" placeholder="0.00"
                                        class="!w-32 !h-8 text-xs font-mono" />
                                </div>
                                <div
                                    class="flex justify-between items-center text-xs font-semibold text-slate-600 dark:text-slate-300 gap-4">
                                    <span>Adjustment (+/-)</span>
                                    <BaseInputNumber v-model="form.adjustment" placeholder="0.00"
                                        class="!w-32 !h-8 text-xs font-mono" />
                                </div>
                                <div
                                    class="flex justify-between items-center text-xs font-semibold text-slate-600 dark:text-slate-300 gap-4">
                                    <span>Rounding Off (+)</span>
                                    <BaseInputNumber v-model="form.rounding_value" placeholder="0.00"
                                        class="!w-32 !h-8 text-xs font-mono" />
                                </div>
                                <div
                                    class="pt-3 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center">
                                    <span
                                        class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-100">Total
                                        Ticket Amount</span>
                                    <span
                                        class="text-base font-black font-mono text-indigo-600 dark:text-indigo-400">₹{{
                                            Number(form.amount_total || 0).toLocaleString('en-IN', {
                                                minimumFractionDigits:
                                                    2, maximumFractionDigits: 2
                                            }) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button v-if="editingId" @click="emit('cancel')" type="button"
                        class="px-5 h-11 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-black text-[10px] uppercase tracking-wider transition-all active:scale-95 flex items-center gap-2">
                        <XMarkIcon class="w-4 h-4" /> Cancel Edit
                    </button>

                    <button type="submit" :disabled="form.processing"
                        class="flex items-center justify-center gap-2 h-11 px-7 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[10px] uppercase tracking-widest shadow-md shadow-indigo-200 dark:shadow-none transition-all duration-200 active:scale-95">
                        <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3]" />
                        <span v-else
                            class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                        {{ editingId ? 'Update Request' : 'Register Request' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
