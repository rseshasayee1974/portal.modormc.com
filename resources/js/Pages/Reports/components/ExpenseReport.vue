<!--
Author: ragul-onemodo
Created: 2026-11-05 11:17:31 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed } from 'vue';
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleDate } from '@/Utils/entityDateTime';
const props = defineProps<{ reportData: any }>();
const rows = computed(() => props.reportData?.transactions ?? []);
const summary = computed(() => props.reportData?.summary ?? { count: 0, total: 0, posted: 0, unposted: 0 });
const money = (value: number) => new Intl.NumberFormat(APP_LOCALE, { style: 'currency', currency: 'INR' }).format(Number(value || 0));
const date = (value: string) => value ? entityLocaleDate(value, APP_LOCALE, { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <section class="space-y-5 text-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-[#3B638E] px-6 py-5 text-white">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15"><i class="pi pi-wallet text-xl" aria-hidden="true" /></span>
                <div><p class="text-[10px] font-semibold uppercase tracking-widest text-blue-100">Accounting &amp; Finance</p><h2 class="mt-1 text-xl font-semibold">Expense Report</h2></div>
            </div>
            <div class="text-sm"><p class="text-xs text-blue-100">Reporting period</p><p class="mt-1 font-medium">{{ date(reportData?.period?.start) }} — {{ date(reportData?.period?.end) }}</p></div>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-[#CBD5E1] bg-white p-4"><p class="text-xs text-slate-500">Total expenses</p><p class="mt-2 text-xl font-bold tabular-nums text-[#3B638E]">{{ money(summary.total) }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Expense entries</p><p class="mt-2 text-xl font-bold tabular-nums">{{ summary.count }}</p></div>
            <div class="rounded-xl bg-[#E6EEF6] p-4"><p class="text-xs text-[#3B638E]">Posted to accounting</p><p class="mt-2 text-xl font-bold tabular-nums text-[#2A4869]">{{ money(summary.posted) }}</p></div>
            <div class="rounded-xl bg-[#FDF3EC] p-4"><p class="text-xs text-amber-800">Not posted</p><p class="mt-2 text-xl font-bold tabular-nums text-amber-900">{{ money(summary.unposted) }}</p></div>
        </div>
        <div v-if="reportData?.categories?.length" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Expense by category</h3>
            <div class="flex flex-wrap gap-2"><div v-for="category in reportData.categories" :key="category.name" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs"><span class="text-slate-600">{{ category.name }}</span><strong class="ml-3 tabular-nums text-[#3B638E]">{{ money(category.amount) }}</strong></div></div>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3"><h3 class="text-sm font-semibold">Expense register</h3><span class="text-xs text-slate-500">{{ rows.length }} records</span></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#E6EEF6] text-[#2A4869]"><tr><th class="px-3 py-3">#</th><th v-for="(label, index) in reportData.headers" :key="label" class="px-3 py-3 font-semibold" :class="reportData.fields[index] === 'amount' ? 'text-right' : ''">{{ label }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, index) in rows" :key="row.id" class="align-top even:bg-slate-50/60 hover:bg-[#E6EEF6]/40">
                            <td class="px-3 py-3 text-slate-400">{{ index + 1 }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ date(row.date) }}</td>
                            <td class="px-3 py-3 font-semibold text-[#3B638E]">{{ row.reference }}</td>
                            <td class="px-3 py-3 font-medium">{{ row.category }}</td><td class="px-3 py-3">{{ row.payee }}</td>
                            <td class="px-3 py-3">{{ row.payment_ledger }}</td><td class="px-3 py-3">{{ row.asset }}</td>
                            <td class="px-3 py-3">{{ row.made_by }}</td><td class="min-w-40 whitespace-pre-line break-words px-3 py-3 text-slate-500">{{ row.notes || '—' }}</td>
                            <td class="px-3 py-3"><span class="whitespace-nowrap rounded-full px-2 py-1 text-[10px] font-semibold" :class="row.accounting === 'Posted' ? 'bg-[#E6EEF6] text-[#3B638E]' : 'bg-[#FDF3EC] text-amber-800'">{{ row.accounting }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-semibold tabular-nums">{{ money(row.amount) }}</td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="11" class="px-5 py-12 text-center text-slate-500">No expenses found for the selected period and filters.</td></tr>
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50"><tr><td colspan="10" class="px-4 py-4 text-right font-semibold">Total expense amount</td><td class="whitespace-nowrap px-3 py-4 text-right font-bold tabular-nums text-[#3B638E]">{{ money(summary.total) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
    </section>
</template>
