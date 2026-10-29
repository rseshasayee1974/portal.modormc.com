<script setup>
import { APP_LOCALE } from '@/Utils/locale';
import { ref, computed, watch } from 'vue';
import { TruckIcon, WrenchScrewdriverIcon, FireIcon, CurrencyRupeeIcon, BoltIcon, ClockIcon } from '@heroicons/vue/24/outline';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

const perPage = ref(25);
const currentPage = ref(1);

const trackerList = computed(() => props.reportData?.transactions || []);
const columnGroups = [
    { label: 'Log Details', fields: { date: 'Date', machine_registration: 'Machine / Vehicle', operator_name: 'Operator' } },
    { label: 'Odometer', fields: { odometer_start: 'Start (KM)', odometer_end: 'End (KM)', odometer_diff: 'KM Run', odo_mileage: 'Mileage (KM/L)' } },
    { label: 'Hourmeter', fields: { hourmeter_start: 'Start (Hrs)', hourmeter_end: 'End (Hrs)', hourmeter_diff: 'Hours Run', hm_mileage: 'Mileage (Hrs/L)' } },
    { label: 'Electricity', fields: { eb_start: 'EB Start', eb_close: 'EB Close', eb_units: 'Consumption (Units)' } },
    { label: 'Fuel', fields: { last_fuel_filled_km: 'Last Fuel Filled (KM)', fuel_filled_km: 'Fuel Filled (KM)', fuel_distance: 'Distance (KM)', fuel: 'Filled (L)', fuel_amount: 'Cost (₹)' } },
    { label: 'Remarks', fields: { pump_name: 'Pump Name', notes: 'Notes' } },
];
const reportColumns = computed(() => columnGroups.flatMap(group => Object.entries(group.fields)));
const calculatedFields = ['odometer_diff', 'odo_mileage', 'hourmeter_diff', 'hm_mileage', 'eb_units', 'fuel_distance'];
const numericFields = ['odometer_start', 'odometer_end', 'hourmeter_start', 'hourmeter_end', 'eb_start', 'eb_close', 'last_fuel_filled_km', 'fuel_filled_km', 'fuel', 'fuel_amount', ...calculatedFields];
const columnTotals = computed(() => ({
    odometer_diff: props.reportData.total_km_run,
    odo_mileage: props.reportData.avg_km_per_liter,
    hourmeter_diff: props.reportData.total_hours_run,
    hm_mileage: props.reportData.avg_hrs_per_liter,
    eb_units: props.reportData.total_eb_units,
    fuel: props.reportData.total_fuel_liters,
    fuel_amount: props.reportData.total_fuel_amount,
}));
const cellValue = (field, value) => field === 'fuel_amount' ? formatCurrency(value || 0)
    : numericFields.includes(field) ? (value === null || value === undefined || value === '' ? '—' : formatNum(value)) : value ?? '-';
const totalRows = computed(() => trackerList.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalRows.value / perPage.value)));

const sanitizedPage = computed(() => {
    if (currentPage.value < 1) return 1;
    if (currentPage.value > totalPages.value) return totalPages.value;
    return currentPage.value;
});

const paginatedList = computed(() => {
    const start = (sanitizedPage.value - 1) * perPage.value;
    return trackerList.value.slice(start, start + perPage.value);
});

watch(trackerList, () => {
    currentPage.value = 1;
});

const formatNum = (val) => {
    if (val === null || val === undefined || val === '') return '0';
    const num = Number(val);
    return isNaN(num) ? '0' : num.toLocaleString(APP_LOCALE);
};
</script>

<template>
    <div v-if="reportData" class="space-y-6">
        <!-- Professional ERP Summary Banner (Mileage Integrated) -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 items-stretch">
            <div
                class="bg-white p-3.5 rounded border border-slate-200 shadow-sm flex justify-between items-center h-full">
                <div class="min-w-0 flex-1 pr-2">
                    <span
                        class="text-[10px] font-black uppercase tracking-wider text-slate-400 block min-h-[20px] leading-tight">Total
                        Logs</span>
                    <p class="text-base text-sm font-black text-slate-800 mt-1 whitespace-nowrap truncate">{{
                        formatNum(reportData.total_entries) }}</p>
                </div>
                <TruckIcon class="w-5 h-5 text-slate-400 shrink-0" />
            </div>

            <div
                class="bg-white p-3.5 rounded border border-slate-200 shadow-sm flex justify-between items-center h-full">
                <div class="min-w-0 flex-1 pr-2">
                    <span
                        class="text-[10px] font-black uppercase tracking-wider text-slate-400 block min-h-[20px] leading-tight">Total
                        KM Run</span>
                    <p class="text-base text-sm font-black text-slate-800 mt-1 whitespace-nowrap truncate">
                        {{ formatNum(reportData.total_km_run) }} <span
                            class="text-xs font-bold text-slate-400">KM</span>
                        <span v-if="reportData.avg_km_per_liter > 0" class="text-xs text-emerald-700 font-bold block">
                            Avg: {{ reportData.avg_km_per_liter }} KM/L
                        </span>
                    </p>
                </div>
                <WrenchScrewdriverIcon class="w-5 h-5 text-slate-400 shrink-0" />
            </div>

            <div
                class="bg-white p-3.5 rounded border border-slate-200 shadow-sm flex justify-between items-center h-full">
                <div class="min-w-0 flex-1 pr-2">
                    <span
                        class="text-[10px] font-black uppercase tracking-wider text-slate-400 block min-h-[20px] leading-tight">Operating
                        Hours</span>
                    <p class="text-base sm:text-lg font-black text-slate-800 mt-1 whitespace-nowrap truncate">
                        {{ formatNum(reportData.total_hours_run) }} <span
                            class="text-xs font-bold text-slate-400">Hrs</span>
                        <span v-if="reportData.avg_hrs_per_liter > 0" class="text-xs text-indigo-700 font-bold block">
                            Avg: {{ reportData.avg_hrs_per_liter }} Hrs/L
                        </span>
                    </p>
                </div>
                <ClockIcon class="w-5 h-5 text-slate-400 shrink-0" />
            </div>

            <div
                class="bg-white p-3.5 rounded border border-slate-200 shadow-sm flex justify-between items-center h-full">
                <div class="min-w-0 flex-1 pr-2">
                    <span
                        class="text-[10px] font-black uppercase tracking-wider text-slate-400 block min-h-[20px] leading-tight">EB
                        Units</span>
                    <p class="text-base sm:text-lg font-black text-slate-800 mt-1 whitespace-nowrap truncate">
                        {{ formatNum(reportData.total_eb_units) }} <span
                            class="text-xs font-bold text-slate-400">U</span>
                    </p>
                </div>
                <BoltIcon class="w-5 h-5 text-slate-400 shrink-0" />
            </div>

            <div
                class="bg-white p-3.5 rounded border border-slate-200 shadow-sm flex justify-between items-center h-full">
                <div class="min-w-0 flex-1 pr-2">
                    <span
                        class="text-[10px] font-black uppercase tracking-wider text-slate-400 block min-h-[20px] leading-tight">Fuel
                        Filled</span>
                    <p class="text-base sm:text-lg font-black text-slate-800 mt-1 whitespace-nowrap truncate">
                        {{ formatNum(reportData.total_fuel_liters) }} <span
                            class="text-xs font-bold text-slate-400">L</span>
                    </p>
                </div>
                <FireIcon class="w-5 h-5 text-slate-400 shrink-0" />
            </div>

            <div
                class="bg-white p-3.5 rounded border border-slate-200 shadow-sm flex justify-between items-center h-full">
                <div class="min-w-0 flex-1 pr-2">
                    <span
                        class="text-[10px] font-black uppercase tracking-wider text-slate-400 block min-h-[20px] leading-tight">Fuel
                        Cost</span>
                    <p class="text-base sm:text-lg font-black text-slate-800 mt-1 whitespace-nowrap truncate">
                        {{ formatCurrency(reportData.total_fuel_amount || 0) }}
                    </p>
                </div>
                <CurrencyRupeeIcon class="w-5 h-5 text-slate-400 shrink-0" />
            </div>
        </div>

        <!-- Machine Tracker Data Table -->
        <div class="bg-white rounded border border-slate-200 shadow-sm">
            <!-- Header Bar -->
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Daily Machine Tracker Log
                        Sheet</h3>
                    <p class="text-[10px] text-slate-400">Machine runtime, odometer, hourmeter, mileage calculation, EB
                        units, fuel consumption, and pump costs</p>
                </div>
                <span class="text-xs font-bold text-slate-600">
                    {{ totalRows }} Entries
                </span>
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[2300px] text-left border-collapse">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-600 border-b border-slate-200">
                        <tr class="border-b border-slate-200 bg-slate-100">
                            <th rowspan="2" scope="col" class="px-3 py-3">#</th>
                            <th v-for="group in columnGroups" :key="group.label" :colspan="Object.keys(group.fields).length"
                                scope="colgroup" class="border-l border-slate-200 px-3 py-2 text-center font-bold">
                                {{ group.label }}
                            </th>
                        </tr>
                        <tr>
                            <th v-for="[field, label] in reportColumns" :key="field" scope="col" class="px-3 py-3"
                                :class="calculatedFields.includes(field) ? 'bg-indigo-50 text-indigo-800 text-right' : numericFields.includes(field) ? 'text-right' : ''">
                                {{ label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <tr v-if="!paginatedList.length">
                            <td :colspan="reportColumns.length + 1" class="py-8 text-center text-slate-400">
                                No machine tracker logs found for the selected filter criteria.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in paginatedList" :key="row.id || idx" class="hover:bg-slate-50">
                            <td class="px-3 py-3 text-slate-400">{{ (sanitizedPage - 1) * perPage + idx + 1 }}</td>
                            <td v-for="[field] in reportColumns" :key="field" class="px-3 py-3"
                                :class="calculatedFields.includes(field) ? 'bg-indigo-50/40 text-right font-semibold tabular-nums text-indigo-800' : numericFields.includes(field) ? 'text-right tabular-nums' : ''">
                                {{ cellValue(field, row[field]) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="trackerList.length" class="border-t border-slate-300 bg-slate-100 text-xs font-bold text-slate-800">
                        <tr>
                            <td class="px-3 py-3">Total / Avg</td>
                            <td v-for="[field] in reportColumns" :key="field" class="px-3 py-3 text-right tabular-nums">
                                {{ field in columnTotals ? cellValue(field, columnTotals[field]) : '' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div v-if="totalPages > 1"
                class="px-4 py-3 bg-[#f8fafc] border-t border-slate-200 flex items-center justify-between">
                <span class="text-xs text-slate-500">
                    Showing {{ (sanitizedPage - 1) * perPage + 1 }} to {{ Math.min(sanitizedPage * perPage, totalRows)
                    }} of {{ totalRows }} records
                </span>
                <div class="flex items-center gap-1">
                    <button @click="currentPage--" :disabled="sanitizedPage <= 1"
                        class="px-2.5 py-1 text-xs font-semibold rounded border bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-slate-700">
                        Prev
                    </button>
                    <span class="text-xs px-2 font-bold text-slate-700">Page {{ sanitizedPage }} of {{ totalPages
                        }}</span>
                    <button @click="currentPage++" :disabled="sanitizedPage >= totalPages"
                        class="px-2.5 py-1 text-xs font-semibold rounded border bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-slate-700">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
