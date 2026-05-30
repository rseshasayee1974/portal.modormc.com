<script setup>
import { UsersIcon } from '@heroicons/vue/24/outline';

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
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Plant Headcount</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.transactions.length }} Employees</span>
                </div>
                <UsersIcon class="w-5 h-5 text-slate-400" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="5%">#</th>
                        <th class="py-3 px-4" width="25%">Employee Name</th>
                        <th class="py-3 px-4" width="20%">Role / Employee Type</th>
                        <th class="py-3 px-4 text-center" width="15%">Joining Date</th>
                        <th class="py-3 px-4 text-center" width="10%">Status</th>
                        <th class="py-3 px-4" width="15%">Email</th>
                        <th class="py-3 px-4 text-center" width="10%">Phone</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                        <td class="py-3 px-4 font-bold text-slate-800">{{ row.name }}</td>
                        <td class="py-3 px-4">
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                {{ row.employee_type }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.joining_date }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#e2f0d9] text-[#385723] border border-[#c5e0b4]">
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-600 text-xs">{{ row.email }}</td>
                        <td class="py-3 px-4 text-center text-slate-600 text-xs">{{ row.phone }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
