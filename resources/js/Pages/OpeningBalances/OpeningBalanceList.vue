<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import Column from 'primevue/column';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Edit from './Edit.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { ArrowDownTrayIcon, ScaleIcon, UserGroupIcon, DocumentTextIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    records: { type: Array, default: () => [] },
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'removed']);
const { can } = usePermissions();
const canManage = computed(() => can('OPENING_BALANCE.UPDATE') || can('OPENING_BALANCE.DELETE'));
const expandedRows = ref({});
const editBusy = ref(false);
const first = ref(0);
const rows = ref(30);
const tableVersion = ref(0);
const filterTab = ref('all');
const sideFilter = ref('all');
const filters = ref({ global: { value: null, matchMode: FilterMatchMode.CONTAINS } });
const name = (id, list, field) => list.find(item => Number(item.id) === Number(id))?.[field] || '#' + id;
const ledgerName = id => name(id, props.ledgers, 'title');
const patronName = id => name(id, props.patrons, 'legal_name');
const money = value => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const totalPatronsCount = computed(() => props.records.filter(record => !!record.patron_id).length);
const totalLedgersCount = computed(() => props.records.filter(record => !record.patron_id).length);

const tableData = computed(() => {
    return (props.records || []).map(r => {
        const line = r.lines?.[0] || {};
        const isPatron = !!r.patron_id;
        const targetName = isPatron ? patronName(r.patron_id) : ledgerName(r.account_id);
        const lName = ledgerName(r.account_id);
        const amt = Number(line.amount || 0);

        return {
            ...r,
            is_patron: isPatron,
            target_name: targetName,
            ledger_name: lName,
            side: line.side || 'Dr',
            amount: amt,
            reference: line.reference || '',
            date_formatted: r.cutover_date ? r.cutover_date.slice(0, 10) : '',
        };
    }).filter(r => {
        if (filterTab.value === 'patron' && !r.is_patron) return false;
        if (filterTab.value === 'ledger' && r.is_patron) return false;
        if (sideFilter.value !== 'all' && r.side !== sideFilter.value) return false;
        return true;
    });
});

function updateExpandedRows(next) {
    if (editBusy.value || !canManage.value) return;
    const keys = Object.keys(next || {});
    const id = keys.find(key => !expandedRows.value[key]) || keys[0];
    expandedRows.value = id ? { [id]: true } : {};
}

function toggleRow(id) {
    if (editBusy.value || !canManage.value) return;
    expandedRows.value = expandedRows.value[id] ? {} : { [id]: true };
}

async function open(record) {
    if (editBusy.value || !canManage.value) return;
    filterTab.value = 'all';
    sideFilter.value = 'all';
    filters.value.global.value = null;
    tableVersion.value++;
    await nextTick();
    const index = props.records.findIndex(item => Number(item.id) === Number(record.id));
    first.value = Math.floor(Math.max(0, index) / rows.value) * rows.value;
    expandedRows.value = { [record.id]: true };
    await nextTick();
    document.getElementById('opening-balance-edit-' + record.id)?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
}

function saved(record, previousId) {
    expandedRows.value = {};
    emit('saved', record, previousId);
}

function removed(id) {
    expandedRows.value = {};
    emit('removed', id);
}

watch([filterTab, sideFilter, () => filters.value.global.value, rows], () => { first.value = 0; });
watch(() => props.records, records => {
    if (!records.some(record => expandedRows.value[record.id])) expandedRows.value = {};
    if (first.value >= records.length) first.value = 0;
});
defineExpose({ open });
watch(canManage, allowed => { if (!allowed) expandedRows.value = {}; });
</script>

<template>
<section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <BaseDataTable
        :key="tableVersion"
        :expandedRows="expandedRows"
        @update:expandedRows="updateExpandedRows"
        v-model:first="first"
        v-model:rows="rows"
        v-model:filters="filters"
        :globalFilterFields="['target_name', 'ledger_name', 'reference', 'notes', 'date_formatted']"
        :loading="editBusy"
        :value="tableData"
        dataKey="id"
        heading="Opening Balance Register"
        headingIcon="ScaleIcon"
        :showSearch="true"
        :showAdvancedFilter="false"
        :paginator="true"
        :rowsPerPageOptions="[30, 50, 100]"
        :showSerial="true"
        class="opening-balances-table"
    >
        <!-- <Column expander style="width: 3rem" /> -->
        <!-- Custom Toolbar with Type Tabs and Side Filter -->
        <template #toolbar>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Type Tabs -->
                <div class="inline-flex p-0.5 bg-slate-100 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                    <button
                        type="button"
                        @click="filterTab = 'all'"
                        :class="['px-3 py-1 rounded-lg font-bold transition cursor-pointer', filterTab === 'all' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-2xs' : 'text-slate-500']"
                    >
                        All ({{ records.length }})
                    </button>
                    <button
                        type="button"
                        @click="filterTab = 'patron'"
                        :class="['px-3 py-1 rounded-lg font-bold transition cursor-pointer', filterTab === 'patron' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-2xs' : 'text-slate-500']"
                    >
                        Parties ({{ totalPatronsCount }})
                    </button>
                    <button
                        type="button"
                        @click="filterTab = 'ledger'"
                        :class="['px-3 py-1 rounded-lg font-bold transition cursor-pointer', filterTab === 'ledger' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-2xs' : 'text-slate-500']"
                    >
                        Ledgers ({{ totalLedgersCount }})
                    </button>
                </div>

                <!-- Side Filter -->
                <div class="w-36">
                    <BaseSelect
                        v-model="sideFilter"
                        :options="[{label: 'All Sides', value: 'all'}, {label: 'Debit (Dr)', value: 'Dr'}, {label: 'Credit (Cr)', value: 'Cr'}]"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Side"
                    />
                </div>
                <a
                    v-if="can('OPENING_BALANCE.AUDIT_LOG')"
                    :href="route('opening-balances.audit-export')"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/50"
                >
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    Audit Log (CSV)
                </a>
            </div>
        </template>

        <!-- Account / Party Column -->
        <Column field="target_name" header="Party" sortable>
            <template #body="{ data }">
                <div class="flex items-center gap-2.5 py-1">
                    <div
                        :class="[
                            'w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs font-bold',
                            data.is_patron
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                        ]"
                    >
                        <UserGroupIcon v-if="data.is_patron" class="w-3.5 h-3.5" />
                        <DocumentTextIcon v-else class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <strong class="text-slate-900 dark:text-white font-semibold block text-xs">
                            {{ data.target_name }}
                        </strong>
                        <span class="text-[10px] text-slate-400 font-mono">
                            {{ data.active_key || (data.is_patron ? 'Customer / Vendor' : 'General Ledger') }}
                        </span>
                    </div>
                </div>
            </template>
        </Column>

        <!-- Associated Ledger Column -->
        <Column field="ledger_name" header="Ledger" sortable>
            <template #body="{ data }">
                <span class="text-xs text-slate-600 dark:text-slate-300">
                    {{ data.ledger_name }}
                </span>
            </template>
        </Column>

        <!-- Cutover Date Column -->
        <Column field="date_formatted" header="Date" sortable style="width: 120px">
            <template #body="{ data }">
                <span class="text-xs text-slate-500 font-mono whitespace-nowrap">
                    {{ data.date_formatted || '-' }}
                </span>
            </template>
        </Column>

        <!-- Side Column (Dr / Cr) -->
        <Column field="side" header="Side" sortable style="width: 90px" class="text-center">
            <template #body="{ data }">
                <span
                    :class="[
                        'px-2 py-0.5 rounded-md text-[10px] font-bold border uppercase tracking-wider inline-block',
                        data.side === 'Dr'
                            ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'
                    ]"
                >
                    {{ data.side }}
                </span>
            </template>
        </Column>

        <!-- Amount Column -->
        <Column field="amount" header="Amount (₹)" sortable style="width: 150px" class="text-right">
            <template #body="{ data }">
                <span class="font-mono text-xs font-black text-slate-900 dark:text-white whitespace-nowrap">
                    ₹ {{ money(data.amount) }}
                </span>
            </template>
        </Column>

        <!-- Reference & Notes Column -->
        <!-- <Column field="reference" header="Reference & Notes">
            <template #body="{ data }">
                <div class="text-xs text-slate-500 max-w-xs truncate" :title="data.notes || data.reference">
                    <span v-if="data.reference" class="inline-block px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded text-[10px] font-mono text-slate-700 dark:text-slate-300 mr-1">
                        {{ data.reference }}
                    </span>
                    <span>{{ data.notes || '---' }}</span>
                </div>
            </template>
        </Column> -->

        <!-- Status Column -->
        <Column field="status" header="Status" sortable style="width: 110px" class="text-center">
            <template #body="{ data }">
                <span
                    :class="[
                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider inline-block',
                        data.status === 'POSTED'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'
                            : 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800'
                    ]"
                >
                    {{ data.status || 'DRAFT' }}
                </span>
            </template>
        </Column>

        <!-- Action Column -->
        <!-- <Column header="Actions" style="width: 110px" class="text-center">
            <template #body="{ data }">
                <button
                    type="button"
                    @click.stop="toggleRow(data.id)"
                    :disabled="editBusy"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition cursor-pointer"
                    title="Modify or replace this opening balance"
                >
                    <PencilSquareIcon class="w-3.5 h-3.5" />
                    <span>{{ expandedRows[data.id] ? 'Close' : (data.status === 'DRAFT' ? 'Post' : 'Edit') }}</span>
                </button>
            </template>
        </Column> -->

        <template v-if="canManage" #expansion="{ data }">
            <Edit
                :key="data.id + '-' + data.version"
                :record="data"
                :ledgers="ledgers"
                :patrons="patrons"
                @busy="editBusy = $event"
                @close="toggleRow(data.id)"
                @saved="saved"
                @removed="removed"
            />
        </template>
        <template #empty>
            <div class="py-12 text-center text-slate-400">
                <div class="flex flex-col items-center justify-center space-y-2">
                    <ScaleIcon class="w-10 h-10 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                        No opening balances found.
                    </p>
                    <p v-if="can('OPENING_BALANCE.CREATE')" class="text-xs text-slate-400">
                        Use the form above to record an opening balance.
                    </p>
                </div>
            </div>
        </template>
    </BaseDataTable>

    <!-- Summary Footer Bar -->
    <div v-if="tableData.length > 0" class="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4 text-xs font-bold text-slate-700 dark:text-slate-300">
        <span class="text-slate-500 uppercase tracking-wider text-[11px]">
            Showing {{ tableData.length }} of {{ records.length }} total configured balances
        </span>
        <div class="flex items-center gap-4">
            <span>
                Filtered Total:
                <strong class="font-mono text-indigo-700 dark:text-indigo-400 text-sm ml-1">
                    ₹ {{ money(tableData.reduce((sum, r) => sum + Number(r.amount || 0), 0)) }}
                </strong>
            </span>
        </div>
    </div>
</section>
</template>
