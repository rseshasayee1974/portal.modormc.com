<script setup lang="ts">
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import type { OptionItem } from '../types';

defineProps<{
    form: any;
    machineOptions: OptionItem[];
    shiftOptions: OptionItem[];
    operatorOptions: OptionItem[];
    errors?: any;
}>();
</script>

<template>
    <div class="space-y-6">
        <!-- ── Asset, Shift & Schedule Allocation ── -->
        <div>
            <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                Asset & Schedule Information
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div>
                    <BaseSelect v-model="form.machine_id" :options="machineOptions" label="Machine / Vehicle Asset"
                        required optionLabel="label" optionValue="value" placeholder="Select Asset"
                        :error="form.errors?.machine_id" />
                </div>
                <!-- <div>
                    <BaseSelect v-model="form.shift" :options="shiftOptions" label="Shift" required optionLabel="label"
                        optionValue="value" placeholder="Select Shift" :error="form.errors?.shift" />
                </div> -->
                <div>
                    <BaseSelect v-model="form.operator_id" :options="operatorOptions" label="Assigned Driver"
                        optionLabel="label" optionValue="value" placeholder="Select Driver"
                        :error="form.errors?.operator_id" />
                </div>
                <div>
                    <BaseDatePicker v-model="form.opening" label="Opening Date & Time" :showTime="true" hourFormat="24"
                        placeholder="Select Opening Time" :error="form.errors?.opening" />
                </div>
                <div>
                    <BaseDatePicker v-model="form.closing" label="Closing Date & Time" :showTime="true" hourFormat="24"
                        placeholder="Select Closing Time" :error="form.errors?.closing" />
                </div>
            </div>
        </div>

        <!-- ── Meter & Energy Readings ── -->
        <div class="border-t border-slate-100 dark:border-slate-800/80 pt-5">
            <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                Meter & Energy Readings
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div>
                    <BaseInputNumber v-model="form.odometer_start" label="Odometer Start" placeholder="0.00"
                        :error="form.errors?.odometer_start" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.odometer_end" label="Odometer End" placeholder="0.00"
                        :error="form.errors?.odometer_end" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.hourmeter_start" label="Hourmeter Start" placeholder="0.00"
                        :error="form.errors?.hourmeter_start" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.hourmeter_end" label="Hourmeter End" placeholder="0.00"
                        :error="form.errors?.hourmeter_end" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.eb_start" required label="EB Start" placeholder="0.00"
                        :error="form.errors?.eb_start" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.eb_close" required label="EB Close" placeholder="0.00"
                        :error="form.errors?.eb_close" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.opening_hsd" label="Opening HSD" placeholder="0.00"
                        :error="form.errors?.opening_hsd" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.closing_hsd" label="Closing HSD" placeholder="0.00"
                        :error="form.errors?.closing_hsd" />
                </div>
                <div class="sm:col-span-2 lg:col-span-2">
                    <BaseInput v-model="form.operation_type" label="Operation / Concrete Type"
                        placeholder="E.g. Transport, Excavation, Pouring" :error="form.errors?.operation_type" />
                </div>
            </div>
        </div>

        <!-- ── Fuel & Refill Details ── -->
        <div class="border-t border-slate-100 dark:border-slate-800/80 pt-5">
            <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                Fuel Refills & Consumption
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div>
                    <BaseInputNumber v-model="form.fuel" label="Fuel Filled (Liters)" placeholder="0.00"
                        :error="form.errors?.fuel" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.amount" label="Fuel Cost Amount" mode="currency" currency="INR"
                        locale="en-IN" placeholder="₹0.00" :error="form.errors?.amount" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.last_fuel_filled_km" label="Last Fuel KM" required placeholder="0.00"
                        :error="form.errors?.last_fuel_filled_km" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.fuel_filled_km" label="Fuel Filled KM" placeholder="0.00"
                        :error="form.errors?.fuel_filled_km" />
                </div>
                <div>
                    <BaseInput v-model="form.pump_name" label="Pump Name" placeholder="e.g. Reliance, HPCL"
                        :error="form.errors?.pump_name" />
                </div>
                <div>
                    <BaseInput v-model="form.pump_reading" label="Pump Reading / Receipt"
                        placeholder="e.g. Receipt #12345" :error="form.errors?.pump_reading" />
                </div>
                <div>
                    <BaseDatePicker v-model="form.fuel_filled_on" label="Fuel Refilled On" :showTime="true"
                        hourFormat="24" placeholder="Select Refill Time" :error="form.errors?.fuel_filled_on" />
                </div>
            </div>
        </div>

        <!-- ── Remarks / Notes ── -->
        <div class="border-t border-slate-100 dark:border-slate-800/80 pt-5">
            <BaseInput v-model="form.notes" label="Tracker Notes & Observations"
                placeholder="Log operational issues, site status, maintenance alerts or operator notes..."
                :error="form.errors?.notes" />
        </div>
    </div>
</template>
