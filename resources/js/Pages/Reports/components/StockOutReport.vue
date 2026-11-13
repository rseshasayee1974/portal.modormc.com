<!--
Author: Cipher
Created: 2026-11-12 15:17:56 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { formatDate } from '@/Utils/formatters';
import { ArrowUpRightIcon } from '@heroicons/vue/24/outline';

defineProps({ reportData: { type: Object, required: true } });
</script>

<template>
    <div>
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded border border-slate-200 bg-slate-50/30 p-4">
                <div><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Lines
                        issued</span><span class="mt-1 block text-lg font-black text-[#1d2d3e]">{{
                            reportData.transactions?.length || 0 }}</span></div>
                <ArrowUpRightIcon class="h-5 w-5 text-slate-400" />
            </div>
            <div class="rounded border border-slate-200 bg-slate-50/30 p-4">
                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Total quantity
                    out</span>
                <span class="mt-1 block text-lg font-black text-[#1d2d3e]">{{ Number(reportData.total_quantity ||
                    0).toFixed(2) }}</span>
            </div>
        </div>
        <div class="overflow-x-auto rounded border border-slate-200">
            <table class="w-full min-w-[1900px] border-collapse text-left">
                <thead>
                    <tr
                        class="border-b border-slate-200 bg-[#f2f4f7] text-[10px] font-bold uppercase tracking-wider text-slate-600">
                        <th v-for="heading in ['#', 'Issue Date', 'Reference', 'Bill No', 'Bill Date', 'Description', 'Partner', 'Product', 'Quantity', 'Items', 'Unit Price', 'Discount', 'Taxable Amount', 'Tax %', 'Tax Amount', 'Total Amount', 'Issued To', 'Vehicle', 'Odometer', 'Notes']"
                            :key="heading" class="px-3 py-3">{{ heading }}</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700">
                    <tr v-for="(row, idx) in reportData.transactions" :key="`${row.reference_number}-${idx}`"
                        class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3 text-slate-400">{{ idx + 1 }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ formatDate(row.date) }}</td>
                        <td class="px-3 py-3">{{ row.reference_number }}</td>
                        <td class="px-3 py-3">{{ row.bill_number || '—' }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ row.billed_date || '—' }}</td>
                        <td class="px-3 py-3">{{ row.voucher_name }}</td>
                        <td class="px-3 py-3">{{ row.partner_name }}</td>
                        <td class="px-3 py-3">{{ row.product_name }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.quantity || 0).toFixed(2) }} {{ row.uom }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.no_items_issued || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.unit_price || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.discount_amount || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.untaxed_amount || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.tax_rate || 0).toFixed(2) }}%</td>
                        <td class="px-3 py-3 text-right">{{ Number(row.tax_amount || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3 text-right font-bold">{{ Number(row.total_amount || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3">{{ row.issued_to }}</td>
                        <td class="px-3 py-3">{{ row.vehicle_no }}</td>
                        <td class="px-3 py-3 text-right">{{ row.changed_km || '' }}</td>
                        <td class="px-3 py-3">{{ row.notes }}</td>
                    </tr>
                    <tr v-if="!reportData.transactions?.length">
                        <td colspan="20" class="px-4 py-8 text-center text-slate-500">No stock out transactions match
                            these filters.</td>
                    </tr>
                    <tr v-else class="border-t border-slate-300 bg-[#f2f4f7] text-xs font-bold">
                        <td colspan="8" class="px-3 py-3 text-right uppercase">Totals</td>
                        <td class="px-3 py-3 text-right">{{ Number(reportData.total_quantity || 0).toFixed(2) }}</td>
                        <td></td>
                        <td></td>
                        <td class="px-3 py-3 text-right">{{ Number(reportData.total_discount_amount || 0).toFixed(2) }}
                        </td>
                        <td class="px-3 py-3 text-right">{{ Number(reportData.total_untaxed_amount || 0).toFixed(2) }}
                        </td>
                        <td></td>
                        <td class="px-3 py-3 text-right">{{ Number(reportData.total_tax_amount || 0).toFixed(2) }}</td>
                        <td class="px-3 py-3 text-right">{{ Number(reportData.total_amount || 0).toFixed(2) }}</td>
                        <td colspan="4"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
