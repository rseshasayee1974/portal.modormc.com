<!--
Author: ragul-onemodo
Created: 2026-11-26 15:23:11 Asia/Calcutta (UTC+05:30)
-->
<script setup>
defineProps({ reportData: { type: Object, required: true } });
const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
</script>

<template>
    <section class="rounded-lg border bg-white p-4">
        <header class="flex items-center justify-between gap-4 border-b pb-3">
            <img src="/assets/modormc_logo_v1.png" alt="ModoRMC" class="h-auto max-h-10 max-w-[30%]" />
            <div class="min-w-0 break-words text-right text-sm">
                <p class="font-semibold">{{ reportData.plant_details.name }}</p>
                <p v-if="reportData.plant_details.address">{{ reportData.plant_details.address }}</p>
                <p v-if="reportData.plant_details.gstin">GSTIN: {{ reportData.plant_details.gstin }}</p>
            </div>
        </header>
        <h3 class="mt-3 font-semibold">{{ reportData.patron_name }} · As of {{ reportData.as_of }}</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100">
                    <tr><th v-for="label in reportData.columns.headers" :key="label" class="p-2">{{ label }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="invoice in reportData.transactions" :key="invoice.id" class="border-b">
                        <td v-for="field in reportData.columns.fields" :key="field" class="p-2"
                            :class="{ 'text-right tabular-nums': field.endsWith('_amount') || field === 'days_overdue' }">
                            {{ field.endsWith('_amount') && invoice[field] != null ? currency.format(invoice[field]) : invoice[field] }}
                        </td>
                    </tr>
                    <tr v-if="!reportData.transactions.length"><td :colspan="reportData.columns.fields.length" class="p-4 text-center">No open {{ reportData.outstanding_group === 'bill' ? 'bills' : reportData.outstanding_group === 'vendor' ? 'vendor balances' : 'invoices' }} as of this date.</td></tr>
                </tbody>
                <tfoot><tr class="font-semibold"><th :colspan="reportData.columns.fields.indexOf('balance_amount')" class="p-2">Total outstanding</th>
                    <td v-for="(amount, field) in reportData.columns.totals" :key="field" class="p-2 text-right tabular-nums">{{ currency.format(amount) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </section>
</template>
