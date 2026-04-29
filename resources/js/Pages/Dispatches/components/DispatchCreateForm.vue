<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { PlusCircleIcon } from '@heroicons/vue/24/outline';

const props = withDefaults(defineProps<{
    workOrders?: any[];
    batches?: any[];
    trucks?: any[];
    drivers?: any[];
}>(), {
    workOrders: () => [],
    batches: () => [],
    trucks: () => [],
    drivers: () => [],
});

const form = useForm({
    work_order_id: null as number | null,
    batch_id: null as number | null,
    truck_id: null as number | null,
    driver_id: null as number | null,
    dispatch_time: new Date(),
    delivered_qty: 0,
});

const filteredBatches = computed(() => {
    if (!form.work_order_id) return [];
    return props.batches.filter((batch: any) => Number(batch.work_order_id) === Number(form.work_order_id));
});

const submit = () => {
    // Basic frontend validation
    if (!form.work_order_id || !form.batch_id || !form.truck_id || form.delivered_qty <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Please fill all required fields correctly.',
        });
        return;
    }

    form.transform((data) => ({
        ...data,
        dispatch_time: data.dispatch_time ? new Date(data.dispatch_time).toISOString().slice(0, 19).replace('T', ' ') : null,
    })).post(route('dispatches.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Dispatch created',
                timer: 2200,
                showConfirmButton: false,
            });
            form.reset();
            form.clearErrors();
            form.dispatch_time = new Date();
        },
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3">
            <div class="flex items-center gap-2.5">
                <div class="rounded-lg bg-emerald-100 p-1.5 text-emerald-700 ring-1 ring-emerald-200">
                    <PlusCircleIcon class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">New Dispatch Entry</h2>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-5 p-5">
            <div class="col-span-12 md:col-span-6">
                <BaseSelect
                    v-model="form.work_order_id"
                    :options="workOrders"
                    optionLabel="order_no"
                    optionValue="id"
                    filter
                    label="Work Order"
                    required
                    :error="form.errors.work_order_id"
                    @change="form.batch_id = null"
                />
            </div>
            <div class="col-span-12 md:col-span-6">
                <BaseSelect
                    v-model="form.batch_id"
                    :options="filteredBatches"
                    :optionLabel="opt => `Batch #${opt.batch_no} (${opt.batch_size} m³)`"
                    optionValue="id"
                    filter
                    label="Batch"
                    required
                    :disabled="!form.work_order_id"
                    :error="form.errors.batch_id"
                />
            </div>
            <div class="col-span-12 md:col-span-4">
                <BaseSelect
                    v-model="form.truck_id"
                    :options="trucks"
                    optionLabel="registration"
                    optionValue="id"
                    filter
                    label="Truck / Vehicle"
                    required
                    :error="form.errors.truck_id"
                />
            </div>
            <div class="col-span-12 md:col-span-4">
                <BaseSelect
                    v-model="form.driver_id"
                    :options="drivers"
                    optionLabel="legal_name"
                    optionValue="id"
                    filter
                    label="Driver (Optional)"
                    :error="form.errors.driver_id"
                />
            </div>
            <div class="col-span-12 md:col-span-4">
                <BaseInputNumber
                    v-model="form.delivered_qty"
                    label="Delivered Quantity (m³)"
                    :minFractionDigits="3"
                    required
                    :error="form.errors.delivered_qty"
                />
            </div>
            <div class="col-span-12 md:col-span-4">
                <BaseDatePicker 
                    v-model="form.dispatch_time" 
                    label="Dispatch Time"
                    showTime 
                    hourFormat="24" 
                    required
                    :error="form.errors.dispatch_time"
                />
            </div>
        </div>

        <div class="flex justify-end bg-slate-50 px-5 py-3 border-t border-slate-100">
            <Button 
                label="Submit Dispatch" 
                icon="pi pi-check" 
                severity="success"
                class="p-button-sm"
                :loading="form.processing" 
                @click="submit" 
            />
        </div>
    </div>
</template>
