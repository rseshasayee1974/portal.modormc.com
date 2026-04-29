<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import Swal from 'sweetalert2';

const props = withDefaults(defineProps<{
    dispatch?: any;
    workOrders?: any[];
    batches?: any[];
    trucks?: any[];
    drivers?: any[];
}>(), {
    dispatch: () => ({}),
    workOrders: () => [],
    batches: () => [],
    trucks: () => [],
    drivers: () => [],
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const form = useForm({
    work_order_id: props.dispatch?.work_order_id ?? null,
    batch_id: props.dispatch?.batch_id ?? null,
    truck_id: props.dispatch?.truck_id ?? null,
    driver_id: props.dispatch?.driver_id ?? null,
    dispatch_time: props.dispatch?.dispatch_time ? new Date(props.dispatch.dispatch_time) : new Date(),
    delivered_qty: Number(props.dispatch?.delivered_qty ?? 0),
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
    })).put(route('dispatches.update', props.dispatch?.id), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Dispatch updated',
                timer: 2200,
                showConfirmButton: false,
            });
            emit('saved');
        },
    });
};
</script>

<template>
    <div class="rounded-lg border border-emerald-100 bg-emerald-50/40 p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800">Modify Dispatch Record</h3>
                <p class="text-[10px] text-emerald-600 opacity-75">Adjusting records for dispatch entry #{{ dispatch.id }}</p>
            </div>
            <span class="rounded bg-emerald-100 px-2 py-1 font-mono text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200">
                {{ dispatch.work_order?.order_no || '-' }}
            </span>
        </div>

        <div class="grid grid-cols-12 gap-5">
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

        <div class="mt-5 flex justify-end gap-3 border-t border-emerald-100 pt-4">
            <Button label="Discard Changes" text severity="secondary" size="small" @click="emit('cancel')" />
            <Button 
                label="Update Record" 
                icon="pi pi-check" 
                severity="success" 
                size="small"
                :loading="form.processing" 
                @click="submit" 
            />
        </div>
    </div>
</template>
