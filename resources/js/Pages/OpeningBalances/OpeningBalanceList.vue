<script setup>
import { computed, ref, watch } from 'vue';
import Column from 'primevue/column';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import OpeningBalanceEditForm from './OpeningBalanceEditForm.vue';
import { PencilSquareIcon, PlusIcon, ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    batch: Object,
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'create']);

const batchList = computed(() => props.batch ? [props.batch] : []);

// Default to open the expanded row if a batch exists
const expandedRows = ref(props.batch?.id ? { [props.batch.id]: true } : {});

watch(() => props.batch, (b) => {
    if (b?.id) {
        expandedRows.value = { [b.id]: true };
    }
}, { deep: true });

const totals = computed(() => (props.batch?.lines ?? []).reduce((sum, line) => {
    sum[line.side === 'Dr' ? 'debit' : 'credit'] += Math.round(Number(line.amount) * 100);
    return sum;
}, { debit: 0, credit: 0 }));

const money = cents => (cents / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function toggleRow(id) {
    if (expandedRows.value[id]) {
        delete expandedRows.value[id];
        expandedRows.value = { ...expandedRows.value };
    } else {
        expandedRows.value = { [id]: true };
    }
}
</script>

<template>
    <section class="space-y-6" aria-label="Opening balances list">
        <div class="bg-white dark:bg-slate-900 shadow-xl shadow-slate-200/40 dark:shadow-none rounded-2xl border border-slate-100 dark:border-slate-800 overflow-hidden">
            <BaseDataTable
                :value="batchList"
                v-model:expandedRows="expandedRows"
                dataKey="id"
                heading="Opening Balances Setup"
                headingIcon="ClockIcon"
                class="opening-balance-table"
            >
                <!-- Expander Column with Custom Chevron Icon -->
                <Column expander style="width: 3.5rem">
                    <template #rowtogglericon="slotProps">
                        <ChevronDownIcon v-if="slotProps.expanded" class="w-4 h-4 text-indigo-600" />
                        <ChevronRightIcon v-else class="w-4 h-4 text-slate-400" />
                    </template>
                </Column>

                <!-- Cutover Date -->
                <Column header="Cutover Date" field="cutover_date">
                    <template #body="slotProps">
                        <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-200">
                            {{ slotProps.data.cutover_date?.slice(0, 10) }}
                        </span>
                    </template>
                </Column>

                <!-- Status -->
                <Column header="Status" field="status">
                    <template #body="slotProps">
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider whitespace-nowrap"
                            :class="slotProps.data.status === 'POSTED' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300'"
                        >
                            {{ slotProps.data.status === 'POSTED' ? 'Posted · Locked' : 'Draft Setup' }}
                        </span>
                    </template>
                </Column>

                <!-- Total Balances -->
                <Column header="Total Balances">
                    <template #body="slotProps">
                        <span class="font-mono text-xs text-slate-600 dark:text-slate-300">
                            {{ slotProps.data.lines?.length || 0 }} rows
                        </span>
                    </template>
                </Column>

                <!-- Debit -->
                <Column header="Debit (₹)">
                    <template #body>
                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                            ₹ {{ money(totals.debit) }}
                        </span>
                    </template>
                </Column>

                <!-- Credit -->
                <Column header="Credit (₹)">
                    <template #body>
                        <span class="font-mono text-xs font-bold text-purple-600 dark:text-purple-400">
                            ₹ {{ money(totals.credit) }}
                        </span>
                    </template>
                </Column>

                <!-- Action / Control -->
                <Column header="Action" align="right" style="width: 140px">
                    <template #body="slotProps">
                        <BaseButton
                            variant="outlined"
                            severity="primary"
                            size="small"
                            :label="expandedRows[slotProps.data.id] ? 'Hide Form' : (slotProps.data.status === 'POSTED' ? 'View / Correct' : 'Edit Form')"
                            @click.stop="toggleRow(slotProps.data.id)"
                        >
                            <template #icon>
                                <PencilSquareIcon class="w-4 h-4" />
                            </template>
                        </BaseButton>
                    </template>
                </Column>

                <!-- ── Table List Row Expand to Show Edit Form ── -->
                <template #expansion="slotProps">
                    <div class="bg-slate-50/40 dark:bg-slate-950/60 border-t border-slate-100 dark:border-slate-800">
                        <OpeningBalanceEditForm
                            :key="slotProps.data.id"
                            :batch="slotProps.data"
                            :ledgers="ledgers"
                            :patrons="patrons"
                            :history="history"
                            @saved="$emit('saved', $event)"
                            @close="toggleRow(slotProps.data.id)"
                        />
                    </div>
                </template>

                <!-- Empty State -->
                <template #empty>
                    <div class="py-14 text-center text-slate-400 space-y-3">
                        <p class="text-sm font-semibold">No opening balance setup exists for this plant yet.</p>
                        <BaseButton
                            variant="filled"
                            severity="primary"
                            size="small"
                            label="Create Opening Balances"
                            class="inline-flex"
                            @click="$emit('create')"
                        >
                            <template #icon>
                                <PlusIcon class="w-4 h-4 mr-1.5" />
                            </template>
                        </BaseButton>
                    </div>
                </template>
            </BaseDataTable>
        </div>
    </section>
</template>
