<!--
Author: ragul-onemodo
Created: 2026-11-28 15:23:30 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { APP_LOCALE } from '@/Utils/locale';
import { ChevronDownIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

defineProps({ reportData: { type: Object, required: true } });
const decimals = new Intl.NumberFormat(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const money = new Intl.NumberFormat(APP_LOCALE, { style: 'currency', currency: 'INR' });
const detailFields = ['driver', 'vendor', 'pump', 'payment', 'receipt', 'bill', 'odometer', 'hours'];
const reading = (value) => value == null || value === '' ? '—' : decimals.format(value);
</script>

<template>
    <section class="fuel-report min-w-0 text-slate-900" aria-label="Fuel log results">
        <dl class="metrics">
            <div class="metric bg-slate-900 text-white">
                <dt class="text-slate-200">Total fuel cost</dt>
                <dd>{{ money.format(reportData.totals.amount) }}</dd>
            </div>
            <div class="metric border border-teal-200 bg-teal-50">
                <dt class="text-teal-800">Fuel issued</dt>
                <dd>{{ decimals.format(reportData.totals.quantity) }} <span class="text-base font-medium">L</span></dd>
            </div>
            <div class="metric border border-slate-200 bg-white">
                <dt class="text-slate-600">Refuels</dt>
                <dd>{{ reportData.transactions.length.toLocaleString(APP_LOCALE) }}</dd>
            </div>
        </dl>

        <div v-if="reportData.transactions.length" class="mt-4 overflow-hidden rounded-lg border border-slate-200">
            <div class="column-head entry-grid bg-slate-50 text-slate-600" aria-hidden="true">
                <span>Machine</span><span>Refuel date</span>
                <span class="text-right">Quantity</span><span class="text-right">Rate / L</span>
                <span class="text-right">Amount</span>
            </div>
            <details v-for="(row, index) in reportData.transactions" :key="[row.date, row.machine, index].join('-')" class="entry">
                <summary class="entry-grid cursor-pointer bg-white hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-600">
                    <span class="machine font-semibold">{{ row.machine || '—' }}</span>
                    <span class="date text-slate-600">{{ row.date }}</span>
                    <span class="value"><span class="mobile-label">Quantity</span>{{ reading(row.quantity) }} L</span>
                    <span class="value"><span class="mobile-label">Rate / L</span>{{ money.format(row.rate) }}</span>
                    <span class="value amount font-semibold"><span class="mobile-label">Amount</span>{{ money.format(row.amount) }}</span>
                    <ChevronDownIcon class="chevron h-4 w-4 text-slate-600" aria-hidden="true" />
                    <span class="sr-only">Entry details</span>
                </summary>
                <dl class="detail-grid bg-slate-50">
                    <div v-for="key in detailFields" :key="key">
                        <dt class="text-xs font-medium text-slate-600">{{ reportData.columns[key] }}</dt>
                        <dd class="mt-1 break-words text-sm">{{ ['odometer', 'hours'].includes(key) ? reading(row[key]) : row[key] || '—' }}</dd>
                    </div>
                </dl>
            </details>
        </div>
        <div v-else class="mt-4 rounded-lg border border-dashed border-slate-300 py-8 text-center">
            <ClipboardDocumentListIcon class="mx-auto mb-2 size-6 text-slate-500" aria-hidden="true" />
            <p class="text-sm font-semibold">No refuels in this selection</p>
            <p class="mt-1 text-sm text-slate-600">Try another date range, machine, or driver.</p>
        </div>
    </section>
</template>

<style scoped>
.fuel-report { container-type: inline-size; font-variant-numeric: tabular-nums; }
.metrics { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: .75rem; }
.metric { min-width: 0; border-radius: .65rem; padding: 1rem 1.2rem; }
.metric dt { font-size: .8125rem; font-weight: 600; }
.metric dd { margin-top: .4rem; font-size: clamp(1.25rem, 3.4cqw, 1.875rem); font-weight: 650; line-height: 1.2; overflow-wrap: anywhere; }
.entry-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.25fr) repeat(2, minmax(0, .75fr)) minmax(0, 1fr) 1rem; gap: .75rem; align-items: center; padding: .8rem 1rem; }
.column-head { font-size: .75rem; font-weight: 600; }
.entry { border-top: 1px solid #e2e8f0; }
summary { list-style: none; font-size: .875rem; }
summary::-webkit-details-marker { display: none; }
.machine, .value { overflow-wrap: anywhere; }
.value { text-align: right; }
.mobile-label { display: none; }
.entry[open] .chevron { transform: rotate(180deg); }
.detail-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .9rem; padding: 1rem; }
@container (max-width: 42rem) {
    .metrics { grid-template-columns: 1fr 1fr; gap: .5rem; }
    .metric { padding: .85rem; }
    .metric:first-child { grid-column: 1/-1; }
    .column-head { display: none; }
    summary.entry-grid { grid-template-columns: repeat(3,minmax(0,1fr)); gap: .3rem .6rem; padding: 1rem; }
    .machine, .date { grid-column: 1/3; }
    .chevron { grid-column: 3; grid-row: 1; justify-self: end; }
    .value { grid-row: 3; text-align: left; }
    .mobile-label { display: block; margin: .6rem 0 .2rem; font-size: .75rem; font-weight: 500; color: #475569; }
    .detail-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
}
</style>
