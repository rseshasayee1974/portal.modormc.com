<script setup>
import { InboxIcon } from '@heroicons/vue/24/outline';

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
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Inward Quantity</span>
                    <span class="text-lg font-black text-[#1d2d3e] mt-1 block">{{ reportData.total_quantity }}</span>
                </div>
                <InboxIcon class="w-5 h-5 text-slate-400" />
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto border border-slate-200 rounded">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                        <th class="py-3 px-4 text-center" width="5%">#</th>
                        <th class="py-3 px-4 text-center" width="12%">Received Date</th>
                        <th class="py-3 px-4 text-center" width="12%">Inward No</th>
                        <th class="py-3 px-4 text-center" width="12%">PO No</th>
                        <th class="py-3 px-4" width="20%">Supplier / Vendor</th>
                        <th class="py-3 px-4" width="15%">Product</th>
                        <th class="py-3 px-4 text-right" width="12%">Quantity</th>
                        <th class="py-3 px-4 text-center" width="12%">Truck No</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                        <td class="py-3 px-4 text-center text-slate-400">{{ idx + 1 }}</td>
                        <td class="py-3 px-4 text-center text-slate-500">{{ row.date }}</td>
                        <td class="py-3 px-4 text-center font-bold text-slate-750">{{ row.inward_no }}</td>
                        <td class="py-3 px-4 text-center font-bold text-slate-800">{{ row.po_number }}</td>
                        <td class="py-3 px-4 text-slate-800">{{ row.vendor_name }}</td>
                        <td class="py-3 px-4 font-bold text-slate-800">{{ row.product_name }}</td>
                        <td class="py-3 px-4 text-right font-black text-[#1d2d3e] bg-slate-50/55">
                            {{ row.quantity }} <span class="text-[10px] text-slate-400">{{ row.uom }}</span>
                        </td>
                        <td class="py-3 px-4 text-center text-slate-600">{{ row.truck_no }}</td>
                    </tr>
                    <tr class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs">
                        <td colspan="6" class="py-3.5 px-4 text-center text-[#1d2d3e] uppercase">Total Goods Inward</td>
                        <td class="py-3.5 px-4 text-right text-[#1d2d3e] font-black">{{ reportData.total_quantity }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
