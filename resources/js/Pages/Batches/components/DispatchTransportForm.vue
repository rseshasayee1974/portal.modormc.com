<script setup lang="ts">
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { TruckIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    modelValue: any;
    trucks: any[];
    transporters: any[];
    sites: any[];
    personnel: any[];
    errors: any;
}>();
</script>

<template>
    <div class="space-y-2 px-4 pb-2">
        <div class="flex items-center gap-2 border-b border-slate-100">
            <TruckIcon class="h-5 w-5 text-blue-500" />
            <h3 class="text-sm font-bold text-slate-700">Transport & Logistics</h3>
        </div>

        <div class="grid grid-cols-12 gap-2">
            <!-- Machine & Operator -->
            <div class="col-span-12 md:col-span-12 grid grid-cols-1 ">
                <div class="grid grid-cols-6 gap-2">
                <BaseSelect v-model="modelValue.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Vehicle" :error="errors.truck_id" />
                <BaseSelect v-model="modelValue.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter" :error="errors.transport_id" />
                <BaseSelect v-model="modelValue.driver_id" :options="personnel" optionLabel="legal_name" optionValue="id" filter label="Driver" :error="errors.driver_id" />
                <!-- <BaseSelect v-model="modelValue.cleaner_id" :options="personnel" optionLabel="legal_name" optionValue="id" filter label="Cleaner" /> -->
            <!-- </div> -->

            <!-- Route Info -->
            <!-- <div class="col-span-12 md:col-span-6 grid grid-cols-2 gap-4"> -->
                <BaseInput v-model="modelValue.receiver_name" label="Receiver Name" />
                <BaseInput v-model="modelValue.receive_mobile" label="Receiver Mobile" />
            <!-- </div>

            <div class="col-span-12 md:col-span-3 "> -->
                <BaseInput v-model="modelValue.note" label="Notes" />
                </div>
            </div>
        </div>
    </div>
</template>
