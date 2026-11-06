<!--
Author: ragul-onemodo
Created: 2026-11-06 10:17:45 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { useForm } from '@inertiajs/vue3';
import { entityToday } from '@/Utils/entityDateTime';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import RecordAttachments from '@/Components/Base/RecordAttachments.vue';
const props = defineProps({ products: Array, vendors: Array, units: Array, vehicles: Array });
const emptyLine = () => ({ product_id: null, uom_id: null, received_qty: 1, unit_price: 0, truck_id: null });
const form = useForm({ entry_mode: 'direct', vendor_id: null, received_date: entityToday(), inward_no: '', items: [emptyLine()], attachments: [] });
const productChanged = line => { line.uom_id = props.products.find(p => p.id === line.product_id)?.unit_id ?? null; };
const submit = () => form.post(route('inwards.store'), { preserveScroll: true, onSuccess: () => { form.reset(); form.items = [emptyLine()]; } });
</script>
<template>
    <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <div><h2 class="text-lg font-semibold">Direct Stock In</h2><p class="text-sm text-slate-500">Receive stock without a Purchase Order. Quantities are added to the selected plant and unit.</p></div>
        <div class="grid gap-4 md:grid-cols-3">
            <BaseSelect v-model="form.vendor_id" :options="vendors" optionLabel="legal_name" optionValue="id" label="Vendor / Partner" filter required :error="form.errors.vendor_id" />
            <BaseInput v-model="form.received_date" type="date" label="Received Date" required :error="form.errors.received_date" />
            <BaseInput v-model="form.inward_no" label="Inward Number" placeholder="Auto-generated if blank" :error="form.errors.inward_no" />
        </div>
        <div v-for="(line,index) in form.items" :key="index" class="grid gap-3 rounded-lg border border-slate-100 p-3 md:grid-cols-3">
            <BaseSelect v-model="line.product_id" :options="products" optionLabel="title" optionValue="id" label="Product" filter required @update:modelValue="productChanged(line)" :error="form.errors['items.'+index+'.product_id']" />
            <BaseSelect v-model="line.uom_id" :options="units" optionLabel="label" optionValue="value" label="Unit" required :error="form.errors['items.'+index+'.uom_id']" />
            <BaseInputNumber v-model="line.received_qty" label="Received Quantity" :min="0.01" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors['items.'+index+'.received_qty']" />
            <BaseInputNumber v-model="line.unit_price" label="Unit Price" :min="0" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors['items.'+index+'.unit_price']" />
            <BaseSelect v-model="line.truck_id" :options="vehicles" optionLabel="label" optionValue="value" label="Truck (optional)" filter showClear :error="form.errors['items.'+index+'.truck_id']" />
            <div class="flex items-end justify-between gap-3"><span class="text-sm">Amount: {{ (Number(line.received_qty || 0) * Number(line.unit_price || 0)).toFixed(2) }}</span><button v-if="form.items.length > 1" type="button" class="text-sm text-red-600" @click="form.items.splice(index,1)">Remove</button></div>
        </div>
        <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
        <button type="button" class="text-sm font-semibold text-indigo-600" @click="form.items.push(emptyLine())">+ Add Product</button>
        <RecordAttachments v-model="form.attachments" :errors="form.errors" />
        <div class="flex justify-end"><button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Saving…' : 'Save Direct Stock In' }}</button></div>
    </form>
</template>
