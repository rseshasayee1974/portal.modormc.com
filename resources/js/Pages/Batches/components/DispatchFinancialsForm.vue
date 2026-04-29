<script setup lang="ts">
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { BanknotesIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    modelValue: any;
    taxes: any[];
    errors: any;
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
            <BanknotesIcon class="h-5 w-5 text-emerald-500" />
            <h3 class="text-sm font-bold text-slate-700">Financial Tracking</h3>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <!-- Loading/Product Financials -->
            <div class="col-span-12 md:col-span-4 space-y-4 rounded-xl border border-slate-100 p-4">
                <h4 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Loading / Product</h4>
                <div class="space-y-3">
                    <BaseInputNumber v-model="modelValue.load_units" label="Units" />
                    <BaseInputNumber v-model="modelValue.load_rate" label="Rate" :minFractionDigits="2" />
                    <BaseSelect v-model="modelValue.load_tax_id" :options="taxes" optionLabel="name" optionValue="id" label="Tax Group" />
                    <div class="mt-2 rounded bg-emerald-50 p-2 text-right">
                        <span class="text-[10px] uppercase text-emerald-600 font-bold block">Subtotal</span>
                        <span class="text-lg font-black text-emerald-700">₹ {{ ((modelValue.load_units || 0) * (modelValue.load_rate || 0)).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Unloading Financials -->
            <div class="col-span-12 md:col-span-4 space-y-4 rounded-xl border border-slate-100 p-4">
                <h4 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Unloading</h4>
                <div class="space-y-3">
                    <BaseInputNumber v-model="modelValue.unload_units" label="Units" />
                    <BaseInputNumber v-model="modelValue.unload_rate" label="Rate" :minFractionDigits="2" />
                    <BaseSelect v-model="modelValue.unload_tax_id" :options="taxes" optionLabel="name" optionValue="id" label="Tax Group" />
                    <div class="mt-2 rounded bg-emerald-50 p-2 text-right">
                        <span class="text-[10px] uppercase text-emerald-600 font-bold block">Subtotal</span>
                        <span class="text-lg font-black text-emerald-700">₹ {{ ((modelValue.unload_units || 0) * (modelValue.unload_rate || 0)).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Transport Financials -->
            <div class="col-span-12 md:col-span-4 space-y-4 rounded-xl border border-slate-100 p-4">
                <h4 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Transport</h4>
                <div class="space-y-3">
                    <BaseInputNumber v-model="modelValue.transport_units" label="Units" />
                    <BaseInputNumber v-model="modelValue.transport_rate" label="Rate" :minFractionDigits="2" />
                    <BaseSelect v-model="modelValue.transport_tax_id" :options="taxes" optionLabel="name" optionValue="id" label="Tax Group" />
                    <div class="mt-2 rounded bg-emerald-50 p-2 text-right">
                        <span class="text-[10px] uppercase text-emerald-600 font-bold block">Subtotal</span>
                        <span class="text-lg font-black text-emerald-700">₹ {{ ((modelValue.transport_units || 0) * (modelValue.transport_rate || 0)).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Adjustments -->
            <div class="col-span-12 grid grid-cols-1 md:grid-cols-4 gap-4 rounded-xl bg-slate-50 p-4 border border-slate-100">
                <BaseInputNumber v-model="modelValue.pass_amount" label="Pass Amount" :minFractionDigits="2" />
                <BaseInputNumber v-model="modelValue.discount_amount" label="Discount" :minFractionDigits="2" />
                <BaseInputNumber v-model="modelValue.transport_expenses" label="Transport Exp." :minFractionDigits="2" />
                <BaseInputNumber v-model="modelValue.adjustment_amount" label="Adjustment" :minFractionDigits="2" />
            </div>
        </div>
    </div>
</template>
