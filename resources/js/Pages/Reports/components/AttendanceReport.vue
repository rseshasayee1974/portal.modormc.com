<!--
Author: Cipher
Created: 2026-10-27 15:37:34 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
const props = defineProps({ reportData: { type: Object, required: true } });
const columns = computed(() => Object.entries(props.reportData.columns || {}));
const summaries = computed(() => [
    ['Employees', props.reportData.total_employees], ['Attendance Records', props.reportData.total_records],
    ['Worked Hours', props.reportData.totals?.worked_hours], ['Overtime Hours', props.reportData.totals?.overtime_hours],
    ['Late Hours', props.reportData.totals?.late_hours],
]);
const format = value => typeof value === 'number' ? value.toLocaleString('en-IN', { maximumFractionDigits: 2 }) : value ?? '—';
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div v-for="[label, value] in summaries" :key="label" class="rounded border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ label }}</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ format(value) }}</p>
            </div>
        </div>
        <BaseDataTable :value="reportData.transactions || []" dataKey="id" paginator :rows="25" :showSerial="true">
            <Column v-for="[field, label] in columns" :key="field" :field="field" :header="label" sortable>
                <template #body="{ data }"><span
                        :class="field.endsWith('_hours') ? 'block text-right tabular-nums' : ''">{{ format(data[field])
                        }}</span></template>
                <template #footer>{{ field in (reportData.totals || {}) ? format(reportData.totals[field]) : ''
                    }}</template>
            </Column>
            <template #empty>No attendance records match the selected filters.</template>
        </BaseDataTable>
    </div>
</template>
