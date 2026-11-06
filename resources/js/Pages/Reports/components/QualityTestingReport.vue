<!--
Author: ragul-onemodo
Created: 2026-11-06 10:02:11 Asia/Calcutta (UTC+05:30)
-->
<script setup>
defineProps({ reportData: { type: Object, required: true } });
</script>
<template>
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-xl font-semibold">Quality Testing Report</h2>
        <p class="text-sm text-slate-500">Test counts by age. The date range uses the actual test date, or the scheduled date when no test date is recorded. Each test record counts once; individual cubes are not counted. Retests are shown separately from completed tests.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3 text-left">#</th><th v-for="header in reportData.headers" :key="header" class="p-3 text-left">{{ header }}</th></tr></thead>
                <tbody><tr v-for="(row, index) in reportData.transactions" :key="row.age" class="border-t border-slate-100">
                    <td class="p-3">{{ index + 1 }}</td><td v-for="field in reportData.fields" :key="field" class="p-3 tabular-nums">{{ row[field] }}</td>
                </tr></tbody>
                <tfoot class="border-t bg-slate-50 font-semibold"><tr><td class="p-3"></td><td v-for="field in reportData.fields" :key="field" class="p-3">{{ field === 'age' ? 'Total' : reportData.totals[field] }}</td></tr></tfoot>
            </table>
        </div>
    </section>
</template>
