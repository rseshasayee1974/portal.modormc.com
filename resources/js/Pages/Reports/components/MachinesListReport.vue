<script setup>
import { TruckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div>
        <!-- KPI Block Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Active Fleet</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.transactions.length }} Vehicles</span>
                </div>
                <TruckIcon class="w-5 h-5 text-slate-400" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="5%">#</th>
                        <th class="py-3 px-4 text-center" width="20%">Registration</th>
                        <th class="py-3 px-4" width="25%">Vehicle Model</th>
                        <th class="py-3 px-4 text-center" width="15%">Vehicle Type</th>
                        <th class="py-3 px-4 text-center" width="10%">Make Year</th>
                        <th class="py-3 px-4 text-right" width="10%">Capacity</th>
                        <th class="py-3 px-4" width="15%">Owner</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                        <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.registration }}</td>
                        <td class="py-3 px-4 text-slate-800">{{ row.vehicle_model }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                {{ row.vehicle_type }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.make_year }}</td>
                        <td class="py-3 px-4 text-right font-bold text-slate-700">{{ row.capacity }}</td>
                        <td class="py-3 px-4 text-slate-600 italic">{{ row.owner }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
