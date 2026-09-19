<script setup>
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
    return isNaN(num) ? '0' : num.toLocaleString('en-IN');
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
                <table class="w-full text-left border-collapse min-w-[1200px]">
                    <thead>
                        <tr
                            class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                            <th class="py-3 px-3 text-center" width="3%">#</th>
                            <th class="py-3 px-3 text-center" width="10%">Date & Time</th>
                            <th class="py-3 px-3" width="13%">Machine / Vehicle</th>
                            <!-- <th class="py-3 px-3 text-center" width="7%">Shift</th> -->
                            <th class="py-3 px-3" width="12%">Operator</th>
                            <th class="py-3 px-3 text-center" width="12%">Odometer (KM | Mileage)</th>
                            <th class="py-3 px-3 text-center" width="12%">Hourmeter (Hrs | Mileage)</th>
                            <th class="py-3 px-3 text-center" width="9%">EB Units</th>
                            <th class="py-3 px-3 text-right" width="7%">Fuel (L)</th>
                            <th class="py-3 px-3 text-right" width="8%">Cost (₹)</th>
                            <th class="py-3 px-3" width="8%">Pump Name</th>
                            <th class="py-3 px-3" width="9%">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                        <tr v-if="!paginatedList.length">
                            <td colspan="12" class="py-8 text-center text-slate-400">
                                No machine tracker logs found for the selected filter criteria.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in paginatedList" :key="row.id || idx"
                            class="hover:bg-slate-50/80 transition-all">
                            <td class="py-2.5 px-3 text-center text-slate-400">{{ (sanitizedPage - 1) * perPage + idx +
                                1 }}</td>
                            <td class="py-2.5 px-3 text-center font-mono text-[10px] whitespace-nowrap">
                                <span class="block font-bold text-slate-800">{{ row.date_only || row.date }}</span>
                                <span v-if="row.time_only" class="block text-[9px] text-slate-400 font-normal mt-0.5">{{ row.time_only }}</span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="font-bold text-slate-800 block">{{ row.machine_registration }}</span>
                                <span class="text-[10px] text-slate-400 block font-normal">{{ row.machine_model
                                    }}</span>
                            </td>
                            <!-- <td class="py-2.5 px-3 text-center">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 inline-block border border-slate-200">
                                    {{ row.shift_label }}
                                </span>
                            </td> -->
                            <td class="py-2.5 px-3 text-slate-800 font-bold">{{ row.operator_name }}</td>

                            <!-- Odometer -->
                            <td class="py-2.5 px-3 text-center font-mono text-[10px]">
                                <div class="text-slate-400">{{ formatNum(row.odometer_start) }} &rarr; {{
                                    formatNum(row.odometer_end) }}</div>
                                <div class="font-bold text-slate-800">
                                    +{{ formatNum(row.odometer_diff) }} KM
                                    <span v-if="row.odo_mileage > 0"
                                        class="text-[9px] text-emerald-700 font-semibold bg-emerald-50 px-1 py-0.5 rounded ml-1 whitespace-nowrap">
                                        {{ row.odo_mileage }} KM/L
                                    </span>
                                </div>
                            </td>

                            <!-- Hourmeter -->
                            <td class="py-2.5 px-3 text-center font-mono text-[10px]">
                                <div class="text-slate-400">{{ formatNum(row.hourmeter_start) }} &rarr; {{
                                    formatNum(row.hourmeter_end) }}</div>
                                <div class="font-bold text-slate-800">
                                    +{{ formatNum(row.hourmeter_diff) }} Hrs
                                    <span v-if="row.hm_mileage > 0"
                                        class="text-[9px] text-indigo-700 font-semibold bg-indigo-50 px-1 py-0.5 rounded ml-1 whitespace-nowrap">
                                        {{ row.hm_mileage }} Hrs/L
                                    </span>
                                </div>
                            </td>

                            <!-- EB Units -->
                            <td class="py-2.5 px-3 text-center font-mono text-[10px]">
                                <div class="text-slate-400">{{ formatNum(row.eb_start) }} &rarr; {{
                                    formatNum(row.eb_close) }}</div>
                                <div class="font-bold text-slate-800">+{{ formatNum(row.eb_units) }} U</div>
                            </td>

                            <!-- Fuel -->
                            <td class="py-2.5 px-3 text-right font-bold text-slate-800">
                                {{ row.fuel > 0 ? `${formatNum(row.fuel)} ` : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-bold text-slate-800">
                                {{ row.fuel_amount > 0 ? formatCurrency(row.fuel_amount) : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-left font-normal text-slate-600">
                                {{ row.pump_name && row.pump_name !== '-' ? row.pump_name : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-left font-normal text-slate-500 italic">
                                {{ row.notes && row.notes !== '-' ? row.notes : '-' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="trackerList.length > 0"
                        class="bg-[#f2f4f7] font-bold text-xs text-[#1d2d3e] border-t border-slate-300">
                        <tr class="text-[0.65rem]">
                            <td colspan="5"
                                class="py-3 px-3 text-center uppercase tracking-wider font-bold text-[#1d2d3e]">Grand
                                Total</td>
                            <td class="py-3 px-3 text-center font-black">
                                +{{ formatNum(reportData.total_km_run) }} KM
                                <span v-if="reportData.avg_km_per_liter > 0"
                                    class="text-[10px] text-emerald-800 font-bold block">
                                    Avg {{ reportData.avg_km_per_liter }} KM/L
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-black">
                                +{{ formatNum(reportData.total_hours_run) }} Hrs
                                <span v-if="reportData.avg_hrs_per_liter > 0"
                                    class="text-[10px] text-indigo-800 font-bold block">
                                    Avg {{ reportData.avg_hrs_per_liter }} Hrs/L
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-black">+{{ formatNum(reportData.total_eb_units) }} U
                            </td>
                            <td class="py-3 px-3 text-right font-black">{{ formatNum(reportData.total_fuel_liters) }} L
                            </td>
                            <td class="py-3 px-3 text-right font-black">{{ formatCurrency(reportData.total_fuel_amount
                                || 0) }}</td>
                            <td colspan="2" class="py-3 px-3"></td>
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
