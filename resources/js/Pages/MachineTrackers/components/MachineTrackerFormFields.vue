<script setup lang="ts">
import { computed } from 'vue';
import { APP_LOCALE } from '@/Utils/locale';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import type { OptionItem } from '../types';
import { calculateRunningKm } from '../types';

const props = defineProps<{
    form: any;
    machineOptions: OptionItem[];
    shiftOptions: OptionItem[];
    operatorOptions: OptionItem[];
    errors?: any;
}>();

const reading = (field: string): number | null => {
    const value = props.form[field];
    if (value === null || value === undefined || String(value).trim() === '') return null;
    const number = Number(value);
    return Number.isFinite(number) && number >= 0 ? number : null;
};

const difference = (start: string, end: string): number | null => {
    const first = reading(start);
    const last = reading(end);
    return first !== null && last !== null ? last - first : null;
};

const distance = computed(() => difference('odometer_start', 'odometer_end'));
const hours = computed(() => difference('hourmeter_start', 'hourmeter_end'));
const energy = computed(() => difference('eb_start', 'eb_close'));
const refillDistance = computed(() => difference('last_fuel_filled_km', 'fuel_filled_km'));

const consumed = computed(() => {
    const openingFuel = reading('opening_hsd');
    const closingFuel = reading('closing_hsd');
    const fuel = reading('fuel');
    if (openingFuel === null || closingFuel === null) return null;
    return openingFuel + (fuel ?? 0) - closingFuel;
});

const elapsed = computed(() => {
    const opening = props.form.opening ? new Date(props.form.opening).getTime() : NaN;
    const closing = props.form.closing ? new Date(props.form.closing).getTime() : NaN;
    return Number.isFinite(opening) && Number.isFinite(closing)
        ? (closing - opening) / 3_600_000 : null;
});

const refillMileage = computed(() => {
    const fuel = reading('fuel');
    const dist = refillDistance.value;
    return fuel !== null && fuel > 0 && dist !== null && dist >= 0
        ? dist / fuel : null;
});

const fuelPrice = computed(() => {
    const fuel = reading('fuel');
    const amt = reading('amount');
    return fuel !== null && fuel > 0 && amt !== null
        ? amt / fuel : null;
});

const formatValue = (value: number | null): string => value === null || !Number.isFinite(value)
    ? '—' : value.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const runningKm = computed(() => calculateRunningKm(props.form.odometer_start, props.form.odometer_end));
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
                    <BaseSelect v-model="form.operator_id" :options="operatorOptions" label="Assigned Driver / Operator"
                        optionLabel="label" optionValue="value" placeholder="Select Driver"
                        :error="form.errors?.operator_id" />
                </div>
                <div>
                    <BaseDatePicker v-model="form.opening" label="Opening Date & Time" :showTime="true" hourFormat="24"
                        placeholder="Select Opening Time" required :error="form.errors?.opening" />
                </div>
                <div>
                    <BaseDatePicker v-model="form.closing" label="Closing Date & Time" :showTime="true" hourFormat="24"
                        placeholder="Select Closing Time" :error="form.errors?.closing" />
                    <p v-if="elapsed !== null" class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Elapsed: {{ formatValue(elapsed) }} hrs
                        <span v-if="elapsed < 0" class="text-rose-500 font-normal text-[10px] ml-1">(Closing &lt;
                            Opening)</span>
                    </p>
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
                    <BaseInputNumber v-model="form.odometer_start" @input="form.odometer_start = $event.value" label="Opening KM" placeholder="0.00" :min="0" :maxFractionDigits="2"
                        :error="form.errors?.odometer_start" />
                    <BaseInputNumber v-model="form.odometer_start" @input="form.odometer_start = $event.value"
                        label="Odometer Start" placeholder="0.00" :error="form.errors?.odometer_start" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.odometer_end" @input="form.odometer_end = $event.value" label="Closing KM" placeholder="0.00" :min="Number(form.odometer_start || 0)" :maxFractionDigits="2"
                        :error="form.errors?.odometer_end" />
                    <p v-if="distance !== null" class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Distance: {{ formatValue(distance) }} km
                        <span v-if="distance < 0" class="text-rose-500 font-normal text-[10px] ml-1">(End &lt;
                            Start)</span>
                    </p>
                </div>
                <div>
                    <BaseInputNumber :modelValue="runningKm" label="Running KM (Automatic)" readonly
                        hint="Closing KM minus Opening KM" :maxFractionDigits="2" />
                </div>
                <div>
                    <BaseInputNumber :modelValue="runningKm" label="Running KM (Automatic)" readonly
                        hint="Closing KM minus Opening KM" :maxFractionDigits="2" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.hourmeter_start" @input="form.hourmeter_start = $event.value"
                        label="Hourmeter Start" placeholder="0.00" :error="form.errors?.hourmeter_start" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.hourmeter_end" @input="form.hourmeter_end = $event.value"
                        label="Hourmeter End" placeholder="0.00" :error="form.errors?.hourmeter_end" />
                    <p v-if="hours !== null" class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Running: {{ formatValue(hours) }} hrs
                        <span v-if="hours < 0" class="text-rose-500 font-normal text-[10px] ml-1">(End &lt;
                            Start)</span>
                    </p>
                </div>
                <div>
                    <BaseInputNumber v-model="form.eb_start" @input="form.eb_start = $event.value" label="EB Start"
                        placeholder="0.00" :error="form.errors?.eb_start" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.eb_close" @input="form.eb_close = $event.value" label="EB Close"
                        placeholder="0.00" :error="form.errors?.eb_close" />
                    <p v-if="energy !== null" class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        EB: {{ formatValue(energy) }} units
                        <span v-if="energy < 0" class="text-rose-500 font-normal text-[10px] ml-1">(Close &lt;
                            Start)</span>
                    </p>
                </div>
                <div>
                    <BaseInputNumber v-model="form.opening_hsd" @input="form.opening_hsd = $event.value"
                        label="Opening HSD" placeholder="0.00" :error="form.errors?.opening_hsd" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.closing_hsd" @input="form.closing_hsd = $event.value"
                        label="Closing HSD" placeholder="0.00" :error="form.errors?.closing_hsd" />
                    <p v-if="consumed !== null" class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Fuel Consumed: {{ formatValue(consumed) }} L
                        <span v-if="consumed < 0" class="text-rose-500 font-normal text-[10px] ml-1">(Check
                            readings)</span>
                    </p>
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
                    <BaseInputNumber v-model="form.fuel" @input="form.fuel = $event.value" label="Fuel Filled (Liters)"
                        placeholder="0.00" :error="form.errors?.fuel" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.amount" @input="form.amount = $event.value" label="Fuel Cost Amount"
                        mode="currency" currency="INR" :locale="APP_LOCALE" placeholder="₹0.00"
                        :error="form.errors?.amount" />
                    <p v-if="fuelPrice !== null"
                        class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Rate: ₹{{ formatValue(fuelPrice) }} / L
                    </p>
                </div>
                <div>
                    <BaseInputNumber v-model="form.last_fuel_filled_km" @input="form.last_fuel_filled_km = $event.value" label="Last Fuel KM (Optional)" :min="0" placeholder="0.00"
                        :error="form.errors?.last_fuel_filled_km" />
                </div>
                <div>
                    <BaseInputNumber v-model="form.fuel_filled_km" @input="form.fuel_filled_km = $event.value"
                        label="Fuel Filled KM" placeholder="0.00" :error="form.errors?.fuel_filled_km" />
                    <div v-if="refillDistance !== null"
                        class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 space-y-0.5">
                        <p>
                            Distance: {{ formatValue(refillDistance) }} km
                            <span v-if="refillDistance < 0" class="text-rose-500 font-normal text-[10px] ml-1">(End &lt;
                                Last)</span>
                        </p>
                        <!-- <p v-if="refillMileage !== null">Mileage: {{ formatValue(refillMileage) }} km/L</p> -->
                    </div>
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
