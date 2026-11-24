<script setup>
defineProps({ reportData: { type: Object, required: true } });
</script>

<template>
    <div class="overflow-x-auto rounded border border-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th v-for="(label, index) in reportData.columns.headers" :key="label"
                        class="px-4 py-3" :class="index < 2 ? 'text-left' : 'text-right'">{{ label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in reportData.transactions" :key="index" class="border-t">
                    <td v-for="(field, col) in reportData.columns.fields" :key="field"
                        class="px-4 py-3" :class="col < 2 ? 'text-left' : 'text-right tabular-nums'">{{ row[field] }}</td>
                </tr>
                <tr v-if="!reportData.transactions.length"><td :colspan="reportData.columns.fields.length" class="p-4 text-center">No stock records for this plant.</td></tr>
            </tbody>
        </table>
    </div>
</template>
