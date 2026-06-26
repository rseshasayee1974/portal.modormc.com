<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import {
    ClipboardDocumentListIcon,
    ClockIcon,
    UserCircleIcon,
    ServerStackIcon,
    ArrowPathIcon,
    GlobeAltIcon,
    EyeIcon,
    BuildingOfficeIcon,
    AdjustmentsHorizontalIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    logs: {
        data: any[];
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
    transactionTypes: string[];
    referenceTypes: string[];
    users: { id: number; label: string }[];
    filters: {
        transaction_type?: string;
        reference_type?: string;
        reference_id?: string | number;
        user_id?: string | number;
        date_from?: string;
        date_to?: string;
    };
}>();

// ── Modal State & Details ─────────────────────────────────────────────────────
const expandedRows = ref({});
const selectedLog = ref<any>(null);
const showDetailModal = ref(false);
const activeTab = ref('visual');
const showOnlyModified = ref(true);
const searchFieldQuery = ref('');

const openDetailModal = (log: any) => {
    selectedLog.value = log;
    showDetailModal.value = true;
};

const parsedLogFrom = computed(() => {
    if (!selectedLog.value) return null;
    try {
        const val = selectedLog.value.log_from;
        if (typeof val === 'string' && val.trim().startsWith('{')) {
            return JSON.parse(val);
        }
        if (val && typeof val === 'object') {
            return val;
        }
    } catch (e) {}
    return null;
});

const parsedLogTo = computed(() => {
    if (!selectedLog.value) return null;
    try {
        const val = selectedLog.value.log_to;
        if (typeof val === 'string' && val.trim().startsWith('{')) {
            return JSON.parse(val);
        }
        if (val && typeof val === 'object') {
            return val;
        }
    } catch (e) {}
    return null;
});

const allFieldsDiff = computed(() => {
    const fromObj = parsedLogFrom.value || {};
    const toObj = parsedLogTo.value || {};
    const keys = Array.from(new Set([...Object.keys(fromObj), ...Object.keys(toObj)]));
    
    return keys.map(key => {
        const fromVal = fromObj[key];
        const toVal = toObj[key];
        
        const hasFrom = key in fromObj;
        const hasTo = key in toObj;
        
        let changeType = 'unchanged';
        if (hasFrom && !hasTo) {
            changeType = 'removed';
        } else if (!hasFrom && hasTo) {
            changeType = 'added';
        } else if (JSON.stringify(fromVal) !== JSON.stringify(toVal)) {
            changeType = 'modified';
        }
        
        return {
            field: key,
            from: fromVal,
            to: toVal,
            changeType,
            isModified: changeType !== 'unchanged'
        };
    }).sort((a, b) => {
        if (a.changeType !== 'unchanged' && b.changeType === 'unchanged') return -1;
        if (a.changeType === 'unchanged' && b.changeType !== 'unchanged') return 1;
        return a.field.localeCompare(b.field);
    });
});

const filteredDiffFields = computed(() => {
    return allFieldsDiff.value.filter(item => {
        if (showOnlyModified.value && item.changeType === 'unchanged') {
            return false;
        }
        if (searchFieldQuery.value.trim()) {
            const q = searchFieldQuery.value.toLowerCase();
            return item.field.toLowerCase().includes(q) || 
                   String(item.from).toLowerCase().includes(q) || 
                   String(item.to).toLowerCase().includes(q);
        }
        return true;
    });
});

// ── Local Filter State ────────────────────────────────────────────────────────
const filterForm = ref({
    transaction_type: props.filters?.transaction_type || null,
    reference_type: props.filters?.reference_type || null,
    reference_id: props.filters?.reference_id || null,
    user_id: props.filters?.user_id || null,
    date_from: props.filters?.date_from || null,
    date_to: props.filters?.date_to || null,
});

// ── Search & Filter Debounce ──────────────────────────────────────────────────
let filterTimer: ReturnType<typeof setTimeout> | null = null;
const applyFilters = () => {
    router.get(route('inventory-audit-logs.index'), {
        transaction_type: filterForm.value.transaction_type || undefined,
        reference_type: filterForm.value.reference_type || undefined,
        reference_id: filterForm.value.reference_id || undefined,
        user_id: filterForm.value.user_id || undefined,
        date_from: filterForm.value.date_from || undefined,
        date_to: filterForm.value.date_to || undefined,
        page: 1, // Reset page on filter change
    }, { preserveState: true, preserveScroll: true });
};

watch(filterForm, () => {
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        applyFilters();
    }, 400);
}, { deep: true });

const clearFilters = () => {
    filterForm.value = {
        transaction_type: null,
        reference_type: null,
        reference_id: null,
        user_id: null,
        date_from: null,
        date_to: null,
    };
};

const toggleShowAll = (rowData: any) => {
    rowData.showAllChanges = !rowData.showAllChanges;
};

const handlePageChange = (event: any) => {
    router.get(route('inventory-audit-logs.index'), {
        transaction_type: filterForm.value.transaction_type || undefined,
        reference_type: filterForm.value.reference_type || undefined,
        reference_id: filterForm.value.reference_id || undefined,
        user_id: filterForm.value.user_id || undefined,
        date_from: filterForm.value.date_from || undefined,
        date_to: filterForm.value.date_to || undefined,
        page: event.page + 1,
    }, { preserveState: true, preserveScroll: true });
};

// ── Formatting & Badge Helpers ────────────────────────────────────────────────
function formatDateCompact(value: string): { date: string; time: string } {
    if (!value) return { date: 'N/A', time: '' };
    const d = new Date(value);
    const dateStr = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    const timeStr = d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
    return { date: dateStr, time: timeStr };
}

function formatDate(value: string): string {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function getChange(from: any, to: any) {
    const f = parseFloat(from) || 0;
    const t = parseFloat(to) || 0;
    const diff = t - f;
    const isPositive = diff > 0;
    const isNeutral = diff === 0;
    return {
        value: diff,
        formatted: isNeutral ? '0' : (isPositive ? `+${diff.toFixed(2)}` : diff.toFixed(2)),
        isPositive,
        isNeutral
    };
}

function badgeClass(type: string): string {
    const map: Record<string, string> = {
        INWARD: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900',
        PURCHASE_INWARD: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900',
        OUTWARD: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900',
        DISPATCH: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900',
        ADJUSTMENT: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900',
        STOCK_ADJUSTMENT: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900',
        STOCKTAKE: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900',
        WASTAGE: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900',
        STOCK_EXHAUST: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900',
    };
    return map[type?.toUpperCase()] ?? 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800/40 dark:text-slate-400 dark:border-slate-700';
}

interface ParsedRemark {
    action: 'Created' | 'Updated' | 'Deleted' | 'Custom';
    raw: string;
    changes: Array<{
        field: string;
        oldVal?: string;
        newVal?: string;
    }>;
}

function parseRemarks(remarks: string | null): ParsedRemark {
    if (!remarks) {
        return { action: 'Custom', raw: '', changes: [] };
    }
    
    const trimRemarks = remarks.trim();
    let action: 'Created' | 'Updated' | 'Deleted' | 'Custom' = 'Custom';
    let content = '';
    
    const actionMatch = trimRemarks.match(/^(Updated|Created|Deleted):\s*(.*)$/s);
    if (actionMatch) {
        action = actionMatch[1] as 'Created' | 'Updated' | 'Deleted';
        content = actionMatch[2].trim();
    } else if (trimRemarks.includes('=>')) {
        action = 'Updated';
        content = trimRemarks;
    } else {
        return { action: 'Custom', raw: trimRemarks, changes: [] };
    }
    
    const changes: ParsedRemark['changes'] = [];
    const changeTokens: string[] = [];
    
    let currentToken = '';
    let inQuotes = false;
    for (let i = 0; i < content.length; i++) {
        const char = content[i];
        if (char === "'") {
            inQuotes = !inQuotes;
            currentToken += char;
        } else if (char === ',' && !inQuotes) {
            changeTokens.push(currentToken.trim());
            currentToken = '';
        } else {
            currentToken += char;
        }
    }
    if (currentToken.trim()) {
        changeTokens.push(currentToken.trim());
    }
    
    for (const token of changeTokens) {
        const colonIndex = token.indexOf(':');
        if (colonIndex !== -1) {
            const field = token.substring(0, colonIndex).trim();
            const valuePart = token.substring(colonIndex + 1).trim();
            
            if (action === 'Updated') {
                const arrowIndex = valuePart.indexOf('=>');
                if (arrowIndex !== -1) {
                    let oldVal = valuePart.substring(0, arrowIndex).trim();
                    let newVal = valuePart.substring(arrowIndex + 2).trim();
                    if (oldVal.startsWith("'") && oldVal.endsWith("'")) oldVal = oldVal.slice(1, -1);
                    if (newVal.startsWith("'") && newVal.endsWith("'")) newVal = newVal.slice(1, -1);
                    changes.push({ field, oldVal, newVal });
                } else {
                    let val = valuePart;
                    if (val.startsWith("'") && val.endsWith("'")) val = val.slice(1, -1);
                    changes.push({ field, oldVal: val });
                }
            } else {
                let val = valuePart;
                if (val.startsWith("'") && val.endsWith("'")) val = val.slice(1, -1);
                changes.push({ field, newVal: val });
            }
        }
    }
    
    return {
        action,
        raw: trimRemarks,
        changes
    };
}

function getActionBadgeClass(action: string): string {
    const map: Record<string, string> = {
        Created: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900',
        Updated: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900',
        Deleted: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-450 dark:border-rose-900',
    };
    return map[action] ?? 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
}

function formatFieldLabel(field: string): string {
    return field.replace(/_/g, ' ');
}

function isNumericValue(val: any): boolean {
    if (val === null || val === undefined || val === '') return false;
    if (typeof val === 'string' && (val.trim().startsWith('{') || val.trim().startsWith('['))) {
        return false;
    }
    const num = Number(val);
    return !isNaN(num) && isFinite(num);
}

function countJSONFields(val: any): number {
    if (!val) return 0;
    try {
        const str = typeof val === 'string' ? val.trim() : JSON.stringify(val);
        if (str.startsWith('{') || str.startsWith('[')) {
            const parsed = JSON.parse(str);
            return Object.keys(parsed).length;
        }
    } catch (e) {}
    return 0;
}

interface JsonDiffItem {
    field: string;
    from: any;
    to: any;
}

function getJsonDiff(fromVal: any, toVal: any): JsonDiffItem[] {
    const diffs: JsonDiffItem[] = [];
    
    let fromObj: Record<string, any> = {};
    let toObj: Record<string, any> = {};
    
    try {
        if (fromVal && typeof fromVal === 'string' && (fromVal.trim().startsWith('{') || fromVal.trim().startsWith('['))) {
            fromObj = JSON.parse(fromVal);
        } else if (fromVal && typeof fromVal === 'object') {
            fromObj = fromVal;
        }
    } catch (e) {}
    
    try {
        if (toVal && typeof toVal === 'string' && (toVal.trim().startsWith('{') || toVal.trim().startsWith('['))) {
            toObj = JSON.parse(toVal);
        } else if (toVal && typeof toVal === 'object') {
            toObj = toVal;
        }
    } catch (e) {}
    
    const allKeys = new Set([...Object.keys(fromObj || {}), ...Object.keys(toObj || {})]);
    const ignoreKeys = ['created_at', 'updated_at', 'deleted_at', 'id'];
    
    for (const key of allKeys) {
        if (ignoreKeys.includes(key)) continue;
        
        const fVal = fromObj[key];
        const tVal = toObj[key];
        
        const fStr = fVal !== undefined && fVal !== null ? String(fVal) : '';
        const tStr = tVal !== undefined && tVal !== null ? String(tVal) : '';
        
        if (fStr !== tStr) {
            diffs.push({
                field: key,
                from: fVal !== undefined ? fStr : null,
                to: tVal !== undefined ? tStr : null
            });
        }
    }
    
    return diffs;
}

// ── Dropdown options ─────────────────────────────────────────────────────────
const transactionOptions = computed(() =>
    props.transactionTypes.map(t => ({ label: t, value: t }))
);

const referenceOptions = computed(() =>
    props.referenceTypes.map(r => ({ label: r, value: r }))
);

const userOptions = computed(() =>
    props.users.map(u => ({ label: u.label, value: u.id }))
);

const hasActiveFilters = computed(() => {
    return Object.values(filterForm.value).some(val => val !== null && val !== '');
});
</script>

<template>
    <AppLayout title="Inventory Audit Logs">
        <Head title="Inventory Audit Logs" />
        <template #header>
            <div class="flex items-center gap-4">
                <div class="p-3 bg-indigo-50 rounded-2xl dark:bg-indigo-950/30">
                    <ClipboardDocumentListIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Warehouse & Stock Control</p>
                    <h1 class="text-xl font-black tracking-tight text-slate-900 dark:text-slate-100">Inventory Audit Logs</h1>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Stats strip -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center">
                        <ServerStackIcon class="w-5 h-5 text-indigo-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Audit Logs</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ logs.total }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-center">
                        <ClockIcon class="w-5 h-5 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Logs Displayed</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ logs.data.length }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950/30 flex items-center justify-center">
                        <UserCircleIcon class="w-5 h-5 text-violet-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Active Controllers</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ users.length }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/30 flex items-center justify-center">
                        <AdjustmentsHorizontalIcon class="w-5 h-5 text-amber-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Transaction Types</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ transactionTypes.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 shadow-sm p-5 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Parameters</p>
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="flex items-center gap-1 text-[10px] font-bold text-rose-500 hover:text-rose-600 transition-colors uppercase tracking-widest"
                    >
                        <ArrowPathIcon class="w-3.5 h-3.5" />
                        Reset Filters
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
                    <BaseSelect
                        v-model="filterForm.transaction_type"
                        :options="transactionOptions"
                        option-label="label"
                        option-value="value"
                        label="Transaction Type"
                        placeholder="All Types"
                        :show-clear="true"
                    />
                    <BaseSelect
                        v-model="filterForm.reference_type"
                        :options="referenceOptions"
                        option-label="label"
                        option-value="value"
                        label="Reference Type"
                        placeholder="All References"
                        :show-clear="true"
                    />
                    <BaseInput
                        v-model="filterForm.reference_id"
                        label="Reference ID"
                        placeholder="e.g. 25"
                        type="number"
                    />
                    <BaseSelect
                        v-model="filterForm.user_id"
                        :options="userOptions"
                        option-label="label"
                        option-value="value"
                        label="User / Operator"
                        placeholder="All Users"
                        :show-clear="true"
                    />
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Date From</label>
                        <BaseDatePicker
                            v-model="filterForm.date_from"
                            placeholder="Start date"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Date To</label>
                        <BaseDatePicker
                            v-model="filterForm.date_to"
                            placeholder="End date"
                            class="w-full"
                        />
                    </div>
                </div>
            </div>

            <!-- Table -->
            <BaseDataTable
                v-model:expandedRows="expandedRows"
                :value="logs.data"
                :paginator="true"
                :rows="logs.per_page"
                :totalRecords="logs.total"
                :first="(logs.current_page - 1) * logs.per_page"
                lazy
                heading="Audit Trail Records"
                heading-icon="ClipboardDocumentListIcon"
                data-key="id"
                :striped-rows="true"
                :delete-url="row => route('inventory-audit-logs.destroy', row.id)"
                @page="handlePageChange"
            >
                <!-- <Column expander style="width: 3rem" /> -->

                <!-- When -->
                <Column field="created_at" header="When" style="min-width:115px">
                    <template #body="{ data }">
                        <div class="flex flex-col leading-tight">
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ formatDateCompact(data.created_at).date }}</span>
                            <span class="text-[10px] text-slate-400 font-medium">{{ formatDateCompact(data.created_at).time }}</span>
                            <span class="text-[9px] text-slate-400 font-mono mt-0.5">ID: #{{ data.id }}</span>
                        </div>
                    </template>
                </Column>

                <!-- Transaction Type -->
                <!-- <Column field="transaction_type" header="Transaction" style="min-width:90px">
                    <template #body="{ data }">
                        <span
                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-wider"
                            :class="badgeClass(data.transaction_type)"
                        >
                            {{ data.transaction_type }}
                        </span>
                    </template>
                </Column> -->

                <!-- Reference -->
                <Column header="Reference" style="min-width:95px">
                    <template #body="{ data }">
                        <div v-if="data.reference_type" class="flex flex-col leading-tight">
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 font-mono truncate max-w-[90px]" :title="data.reference_type">
                                {{ data.reference_type }}
                            </span>
                            <span class="text-[9px] text-indigo-500 font-bold">
                                #{{ data.reference_id }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-slate-400 italic">None</span>
                    </template>
                </Column>

                <!-- From Column -->
                <Column field="log_from" header="From" style="min-width:140px">
                    <template #body="{ data }">
                        <div class="flex flex-col leading-tight">
                            <span v-if="isNumericValue(data.log_from)" class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ parseFloat(data.log_from).toFixed(2) }}
                            </span>
                            <div v-else-if="getJsonDiff(data.log_from, data.log_to).length > 0" class="flex flex-col gap-0.5 text-[10px] font-mono leading-none py-1">
                                <div 
                                    v-for="diff in getJsonDiff(data.log_from, data.log_to).slice(0, 3)" 
                                    :key="diff.field"
                                    class="truncate max-w-[130px] text-slate-600 dark:text-slate-400"
                                >
                                    <span class="font-bold text-slate-400 dark:text-slate-500 uppercase tracking-tight text-[8px] mr-1">{{ formatFieldLabel(diff.field) }}:</span>
                                    <span class="text-rose-600 dark:text-rose-400">{{ diff.from === '' || diff.from === null ? '[empty]' : diff.from }}</span>
                                </div>
                                <span 
                                    v-if="getJsonDiff(data.log_from, data.log_to).length > 3" 
                                    class="text-[9px] text-slate-400 italic font-semibold mt-0.5"
                                >
                                    +{{ getJsonDiff(data.log_from, data.log_to).length - 3 }} more
                                </span>
                            </div>
                            <span v-else class="text-xs text-slate-400 italic">
                                {{ data.log_from || '-' }}
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- To Column -->
                <Column field="log_to" header="To" style="min-width:140px">
                    <template #body="{ data }">
                        <div class="flex flex-col leading-tight">
                            <span v-if="isNumericValue(data.log_to)" class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ parseFloat(data.log_to).toFixed(2) }}
                            </span>
                            <div v-else-if="getJsonDiff(data.log_from, data.log_to).length > 0" class="flex flex-col gap-0.5 text-[10px] font-mono leading-none py-1">
                                <div 
                                    v-for="diff in getJsonDiff(data.log_from, data.log_to).slice(0, 3)" 
                                    :key="diff.field"
                                    class="truncate max-w-[130px] text-slate-600 dark:text-slate-400"
                                >
                                    <span class="font-bold text-slate-400 dark:text-slate-500 uppercase tracking-tight text-[8px] mr-1">{{ formatFieldLabel(diff.field) }}:</span>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ diff.to === '' || diff.to === null ? '[empty]' : diff.to }}</span>
                                </div>
                                <span 
                                    v-if="getJsonDiff(data.log_from, data.log_to).length > 3" 
                                    class="text-[9px] text-slate-400 italic font-semibold mt-0.5"
                                >
                                    +{{ getJsonDiff(data.log_from, data.log_to).length - 3 }} more
                                </span>
                            </div>
                            <span v-else class="text-xs text-slate-400 italic">
                                {{ data.log_to || '-' }}
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- User -->
                <Column field="user" header="Operator" style="min-width:110px">
                    <template #body="{ data }">
                        <div v-if="data.user" class="flex items-center gap-1.5">
                            <div class="w-6 h-6 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-[9px] font-black shrink-0">
                                {{ data.user.name ? data.user.name[0].toUpperCase() : 'U' }}
                            </div>
                            <div class="flex flex-col leading-tight min-w-0">
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate max-w-[80px]" :title="data.user.name">{{ data.user.name }}</span>
                                <span class="text-[9px] text-slate-400 truncate max-w-[80px]" :title="data.user.email">{{ data.user.email }}</span>
                            </div>
                        </div>
                        <span v-else class="text-xs text-slate-400 italic">System</span>
                    </template>
                </Column>
                <!-- Remarks / Changes (The Main Highlight) -->
                <Column field="remarks" header="Remarks / Activity Details" style="min-width:280px">
                    <template #body="{ data }">
                        <div v-if="data.remarks" class="flex flex-col gap-1 py-0.5">
                            <div v-if="parseRemarks(data.remarks).action !== 'Custom'" class="flex flex-wrap items-center gap-1">
                                <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider border shrink-0"
                                :class="getActionBadgeClass(parseRemarks(data.remarks).action)"
                                >
                                    {{ parseRemarks(data.remarks).action }}
                                </span>

                                <div class="flex flex-wrap gap-1 items-center">
                                    <template v-for="(change, i) in parseRemarks(data.remarks).changes" :key="i">
                                        <span
                                            v-if="data.showAllChanges || i < 2"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-50 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700 text-[10px]"
                                        >
                                            <span class="font-bold text-slate-500 dark:text-slate-400">{{ formatFieldLabel(change.field) }}:</span>
                                            <span v-if="change.oldVal !== undefined && change.newVal !== undefined" class="inline-flex items-center gap-1">
                                                <span class="text-rose-600 dark:text-rose-400 line-through decoration-rose-300/40 max-w-[65px] truncate" :title="change.oldVal">{{ change.oldVal }}</span>
                                                <span class="text-slate-400 text-[9px]">→</span>
                                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold max-w-[65px] truncate" :title="change.newVal">{{ change.newVal }}</span>
                                            </span>
                                            <span v-else-if="change.newVal !== undefined" class="text-emerald-600 dark:text-emerald-400 font-semibold max-w-[90px] truncate" :title="change.newVal">
                                                {{ change.newVal }}
                                            </span>
                                            <span v-else class="text-rose-600 dark:text-rose-400 line-through decoration-rose-300/40 max-w-[90px] truncate" :title="change.oldVal">
                                                {{ change.oldVal }}
                                            </span>
                                        </span>
                                    </template>

                                    <button
                                        v-if="parseRemarks(data.remarks).changes.length > 2"
                                        @click="toggleShowAll(data)"
                                        class="text-[10px] font-black text-blue-500 hover:text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded hover:bg-blue-50 dark:hover:bg-blue-950/30 transition-colors"
                                    >
                                        {{ data.showAllChanges ? 'Show less' : `+${parseRemarks(data.remarks).changes.length - 2} more` }}
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-[11px] text-slate-600 dark:text-slate-400 leading-normal max-w-sm truncate" :title="data.remarks">
                                {{ data.remarks }}
                            </p>
                        </div>
                        <span v-else class="text-xs text-slate-300 italic">No activity details.</span>
                    </template>
                </Column>


                <!-- Actions
                <Column header="Actions" style="width: 80px; text-align: center">
                    <template #body="{ data }">
                        <div class="flex items-center justify-center gap-1.5">
                            <button
                                @click.stop="openDetailModal(data)"
                                class="p-1.5 rounded-lg bg-slate-50 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-200 text-slate-500 hover:text-indigo-600 dark:bg-slate-800 dark:hover:bg-indigo-950/40 dark:border-slate-700 dark:hover:border-indigo-900 dark:text-slate-400 dark:hover:text-indigo-400 transition-all shadow-sm"
                                title="Inspect Details"
                            >
                                <EyeIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </template>
                </Column> -->

                <!-- Expansion panel -->
                <template #expansion="{ data }">
                    <div class="p-5 bg-slate-50/50 dark:bg-slate-900/50 border-t border-b border-indigo-100/50 dark:border-slate-800">
                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                            <!-- Left Card: Meta Information -->
                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/60 dark:border-slate-800 shadow-sm">
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4">Metadata & Network</p>
                                <div class="space-y-2.5 text-xs text-slate-600 dark:text-slate-400">
                                    <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-900">
                                        <span class="font-bold">Audit ID</span>
                                        <span class="font-mono text-slate-800 dark:text-slate-200">#{{ data.id }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-900">
                                        <span class="font-bold">IP Address</span>
                                        <span class="flex items-center gap-1.5 font-mono text-slate-500">
                                            <GlobeAltIcon class="w-3.5 h-3.5" />
                                            {{ data.ip_address || '127.0.0.1' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-900">
                                        <span class="font-bold">Plant Scoped</span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ data.plant ? data.plant.name : 'N/A (Global)' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1.5">
                                        <span class="font-bold">Operator Email</span>
                                        <span class="text-slate-500 font-mono">{{ data.user ? data.user.email : 'system@modormc.com' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Middle Card: Reference & Action -->
                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/60 dark:border-slate-800 shadow-sm">
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4">Linked Entity</p>
                                <div class="flex flex-col justify-between h-[110px]">
                                    <div v-if="data.reference_type" class="space-y-1">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Polymorphic Target</p>
                                        <p class="text-sm font-black text-slate-700 dark:text-slate-350 font-mono">{{ data.reference_type }}</p>
                                        <p class="text-xs text-slate-500">Database Record ID: <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">#{{ data.reference_id }}</span></p>
                                    </div>
                                    <div v-else class="text-xs text-slate-400 italic py-2">
                                        No morphable database reference linked to this inventory audit entry.
                                    </div>
                                    
                                    <div class="pt-3 border-t border-slate-100 dark:border-slate-900">
                                        <button
                                            @click="router.visit(route('inventory-audit-logs.show', data.id))"
                                            class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/30 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 text-xs font-bold rounded-xl transition-all"
                                        >
                                            <EyeIcon class="w-4 h-4" />
                                            View Audit Details page
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Card: Remarks & Activity Details (The Main Highlight) -->
                            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/60 dark:border-slate-800 shadow-sm flex flex-col justify-between lg:col-span-2">
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Activity Log / Remarks</p>
                                        <span 
                                            v-if="parseRemarks(data.remarks).action !== 'Custom'"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border"
                                            :class="getActionBadgeClass(parseRemarks(data.remarks).action)"
                                        >
                                            {{ parseRemarks(data.remarks).action }} Action
                                        </span>
                                    </div>

                                    <div v-if="data.remarks">
                                        <div v-if="parseRemarks(data.remarks).action !== 'Custom'" class="space-y-2">
                                            <div class="overflow-hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/20 dark:bg-slate-900/10">
                                                <table class="w-full text-left border-collapse">
                                                    <thead>
                                                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800">
                                                            <th class="px-4 py-2 text-[10px] font-black text-slate-400 uppercase tracking-wider">Field</th>
                                                            <th class="px-4 py-2 text-[10px] font-black text-slate-400 uppercase tracking-wider">Original Value</th>
                                                            <th class="px-4 py-2 text-[10px] font-black text-slate-400 uppercase tracking-wider"></th>
                                                            <th class="px-4 py-2 text-[10px] font-black text-slate-400 uppercase tracking-wider">New Value</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                                                        <tr 
                                                            v-for="(change, idx) in parseRemarks(data.remarks).changes" 
                                                            :key="idx"
                                                            class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                                                        >
                                                            <td class="px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 capitalize font-mono">{{ formatFieldLabel(change.field) }}</td>
                                                            <td class="px-4 py-2.5 text-xs text-rose-600 dark:text-rose-400 font-medium">
                                                                <span v-if="change.oldVal !== undefined" class="line-through decoration-rose-300/40 bg-rose-50/50 dark:bg-rose-950/10 px-2 py-0.5 rounded border border-rose-100/50 dark:border-rose-950/20 font-mono">{{ change.oldVal }}</span>
                                                                <span class="text-slate-400 italic">-</span>
                                                            </td>
                                                            <td class="px-2 py-2.5 text-xs text-slate-400 text-center font-bold">
                                                                <span v-if="change.oldVal !== undefined && change.newVal !== undefined">→</span>
                                                            </td>
                                                            <td class="px-4 py-2.5 text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                                                                <span v-if="change.newVal !== undefined" class="bg-emerald-50/50 dark:bg-emerald-950/10 px-2 py-0.5 rounded border border-emerald-100/50 dark:border-emerald-950/20 font-mono">{{ change.newVal }}</span>
                                                                <span class="text-slate-400 italic">-</span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed font-semibold italic bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                                            "{{ data.remarks }}"
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-slate-400 italic py-2">
                                        No remarks or documentation notes are attached to this log entry.
                                    </div>
                                </div>
                                <div class="text-[9px] text-right text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black pt-4">
                                    Logged: {{ formatDate(data.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </BaseDataTable>
        </div>

        <!-- Detailed View Modal -->
        <Dialog 
            v-model:visible="showDetailModal" 
            modal 
            :header="'Audit Log Details #' + (selectedLog?.id || '')" 
            :style="{ width: '80vw', maxWidth: '1000px' }"
            class="premium-dialog"
            :pt="{
                root: { class: 'border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900 shadow-2xl' },
                header: { class: 'p-6 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between' },
                title: { class: 'text-base font-black text-slate-900 dark:text-slate-100 tracking-tight' },
                content: { class: 'p-6 overflow-y-auto max-h-[75vh]' },
                closeButton: { class: 'p-1 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-full text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-all' }
            }"
        >
            <div v-if="selectedLog" class="space-y-6">
                <!-- Top strip: Badge + Time + User -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Transaction details -->
                    <div class="bg-slate-50 dark:bg-slate-950/20 p-4 rounded-xl border border-slate-150 dark:border-slate-800">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1">Transaction type</p>
                        <span 
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-black uppercase tracking-wider"
                            :class="badgeClass(selectedLog.transaction_type)"
                        >
                            {{ selectedLog.transaction_type }}
                        </span>
                    </div>

                    <!-- Timestamp -->
                    <div class="bg-slate-50 dark:bg-slate-950/20 p-4 rounded-xl border border-slate-150 dark:border-slate-800">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1">Logged timestamp</p>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ formatDate(selectedLog.created_at) }}</span>
                    </div>

                    <!-- Operator -->
                    <div class="bg-slate-50 dark:bg-slate-950/20 p-4 rounded-xl border border-slate-150 dark:border-slate-800">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1">Responsible actor</p>
                        <div v-if="selectedLog.user" class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-650 dark:text-indigo-400 text-[9px] font-black">
                                {{ selectedLog.user.name[0].toUpperCase() }}
                            </div>
                            <span class="text-xs font-bold text-slate-850 dark:text-slate-200">{{ selectedLog.user.name }}</span>
                        </div>
                        <span v-else class="text-xs text-slate-400 font-bold italic">System</span>
                    </div>
                </div>

                <!-- Shift summary / deltas -->
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4">
                        {{ isNumericValue(selectedLog.log_from) && isNumericValue(selectedLog.log_to) ? 'Quantity Delta' : 'Payload Transitions' }}
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <template v-if="isNumericValue(selectedLog.log_from) && isNumericValue(selectedLog.log_to)">
                            <div class="bg-slate-50/50 dark:bg-slate-950/10 p-4 rounded-xl text-center border border-slate-100 dark:border-slate-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Starting Level</p>
                                <p class="text-xl font-black text-slate-600 dark:text-slate-450">{{ parseFloat(selectedLog.log_from).toFixed(4) }}</p>
                            </div>
                            <div class="flex flex-col items-center">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Delta</span>
                                <div 
                                    class="px-3 py-1 rounded-lg text-xs font-bold border"
                                    :class="[
                                        getChange(selectedLog.log_from, selectedLog.log_to).isNeutral 
                                            ? 'bg-slate-150 text-slate-650 border-slate-250 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' 
                                            : (getChange(selectedLog.log_from, selectedLog.log_to).isPositive 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900' 
                                                : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-955/20 dark:text-rose-450 dark:border-rose-900')
                                    ]"
                                >
                                    {{ getChange(selectedLog.log_from, selectedLog.log_to).formatted }}
                                </div>
                            </div>
                            <div class="bg-indigo-50/20 dark:bg-indigo-950/10 p-4 rounded-xl text-center border border-indigo-100/20 dark:border-slate-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-indigo-400 mb-0.5">Ending Level</p>
                                <p class="text-2xl font-black text-indigo-600 dark:text-indigo-300">{{ parseFloat(selectedLog.log_to).toFixed(4) }}</p>
                            </div>
                        </template>
                        <template v-else>
                            <div class="bg-slate-50/50 dark:bg-slate-950/10 p-4 rounded-xl text-center border border-slate-100 dark:border-slate-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Pre-Save Payload</p>
                                <p class="text-xs font-mono text-slate-500">Fields logged: {{ countJSONFields(selectedLog.log_from) }}</p>
                            </div>
                            <div class="flex flex-col items-center">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Changes</span>
                                <div class="px-3 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-955/20 dark:text-indigo-400 dark:border-indigo-900">
                                    {{ allFieldsDiff.filter(item => item.isModified).length }} fields changed
                                </div>
                            </div>
                            <div class="bg-indigo-50/20 dark:bg-indigo-950/10 p-4 rounded-xl text-center border border-indigo-100/20 dark:border-slate-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-indigo-400 mb-0.5">Post-Save Payload</p>
                                <p class="text-xs font-mono text-indigo-600 dark:text-indigo-400">Fields logged: {{ countJSONFields(selectedLog.log_to) }}</p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Structured Changes & Tabs -->
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
                    <div class="flex items-center justify-between border-b border-slate-150 dark:border-slate-800 pb-3 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Activity Log & Payload Changes</span>
                        </div>
                        <div v-if="parsedLogFrom || parsedLogTo" class="flex bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg">
                            <button
                                @click="activeTab = 'visual'"
                                class="px-3 py-1 text-xs font-bold rounded-md transition-all"
                                :class="activeTab === 'visual' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'"
                            >
                                Visual Diff
                            </button>
                            <button
                                @click="activeTab = 'raw'"
                                class="px-3 py-1 text-xs font-bold rounded-md transition-all"
                                :class="activeTab === 'raw' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-355'"
                            >
                                Raw JSON
                            </button>
                        </div>
                    </div>

                    <!-- Remarks section inside modal -->
                    <div v-if="selectedLog.remarks" class="mb-4">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Remarks / Explanation</p>
                        <div class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-semibold italic bg-slate-50 dark:bg-slate-950/30 border-l-4 border-indigo-500 p-3 rounded-r-xl">
                            "{{ selectedLog.remarks }}"
                        </div>
                    </div>

                    <div v-if="parsedLogFrom || parsedLogTo">
                        <!-- Visual Diff Tab -->
                        <div v-if="activeTab === 'visual'" class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
                                <input
                                    v-model="searchFieldQuery"
                                    type="text"
                                    placeholder="Filter fields..."
                                    class="px-2.5 py-1 text-xs bg-slate-50 dark:bg-slate-955/40 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-700 dark:text-slate-300 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-full sm:max-w-xs"
                                />
                                <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                    <input
                                        v-model="showOnlyModified"
                                        type="checkbox"
                                        class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 h-3 w-3"
                                    />
                                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Modified only</span>
                                </label>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-slate-150 dark:border-slate-800 bg-slate-50/10 dark:bg-slate-950/10">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-150 dark:border-slate-800">
                                            <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Field</th>
                                            <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Original</th>
                                            <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider"></th>
                                            <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Updated</th>
                                            <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr 
                                            v-for="(item, idx) in filteredDiffFields" 
                                            :key="idx"
                                            class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                                        >
                                            <td class="px-3 py-2 text-xs font-bold text-slate-750 dark:text-slate-300 capitalize font-mono">{{ formatFieldLabel(item.field) }}</td>
                                            <td class="px-3 py-2 text-xs text-rose-650 dark:text-rose-455 font-medium">
                                                <span 
                                                    v-if="item.changeType !== 'added' && item.from !== null && item.from !== undefined"
                                                    :class="item.isModified ? 'line-through decoration-rose-300/40 bg-rose-50/30 dark:bg-rose-950/15 px-2 py-0.5 rounded border border-rose-100/50 dark:border-rose-950/20 font-mono' : 'font-mono text-slate-400'"
                                                >
                                                    {{ item.from === '' ? '[empty]' : item.from }}
                                                </span>
                                                <span v-else class="text-slate-400 italic font-mono">-</span>
                                            </td>
                                            <td class="px-1 py-2 text-xs text-slate-400 text-center font-bold">
                                                <span v-if="item.isModified">→</span>
                                            </td>
                                            <td class="px-3 py-2 text-xs text-emerald-650 dark:text-emerald-400 font-bold">
                                                <span 
                                                    v-if="item.changeType !== 'removed' && item.to !== null && item.to !== undefined"
                                                    :class="item.isModified ? 'bg-emerald-50/30 dark:bg-emerald-950/15 px-2 py-0.5 rounded border border-emerald-100/50 dark:border-emerald-950/20 font-mono' : 'font-mono text-slate-700 dark:text-slate-300 font-medium'"
                                                >
                                                    {{ item.to === '' ? '[empty]' : item.to }}
                                                </span>
                                                <span v-else class="text-slate-400 italic font-mono">-</span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <span 
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider border"
                                                    :class="[
                                                        item.changeType === 'modified' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-955/20 dark:text-amber-400 dark:border-amber-900' :
                                                        item.changeType === 'added' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-955/20 dark:text-emerald-400 dark:border-emerald-900' :
                                                        item.changeType === 'removed' ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-955/20 dark:text-rose-400 dark:border-rose-900' :
                                                        'bg-slate-50 text-slate-450 border-slate-200 dark:bg-slate-800 dark:text-slate-450 dark:border-slate-700'
                                                    ]"
                                                >
                                                    {{ item.changeType }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredDiffFields.length === 0">
                                            <td colspan="5" class="px-3 py-6 text-center text-xs text-slate-400 italic">No matching changes found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Raw JSON Tab -->
                        <div v-else-if="activeTab === 'raw'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Pre-Save State (Raw JSON)</p>
                                <pre class="bg-slate-950 text-slate-100 text-xs p-3 rounded-xl max-h-80 overflow-auto font-mono border border-slate-850 shadow-inner">{{ JSON.stringify(parsedLogFrom || {}, null, 2) }}</pre>
                            </div>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-indigo-405 mb-1.5">Post-Save State (Raw JSON)</p>
                                <pre class="bg-slate-950 text-slate-100 text-xs p-3 rounded-xl max-h-80 overflow-auto font-mono border border-slate-850 shadow-inner">{{ JSON.stringify(parsedLogTo || {}, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>
                    <!-- Fallback to parsed remarks table if JSON payload is empty but we have structured remarks changes -->
                    <div v-else-if="parseRemarks(selectedLog.remarks).changes.length > 0">
                        <div class="overflow-hidden rounded-xl border border-slate-150 dark:border-slate-800 bg-slate-50/10 dark:bg-slate-955/10">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-150 dark:border-slate-800">
                                        <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Field</th>
                                        <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Original Value</th>
                                        <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider"></th>
                                        <th class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Updated Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr 
                                        v-for="(change, idx) in parseRemarks(selectedLog.remarks).changes" 
                                        :key="idx"
                                        class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                                    >
                                        <td class="px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-350 capitalize font-mono">{{ formatFieldLabel(change.field) }}</td>
                                        <td class="px-3 py-2 text-xs text-rose-650 dark:text-rose-455 font-medium">
                                            <span v-if="change.oldVal !== undefined" class="line-through decoration-rose-300/40 bg-rose-50/30 dark:bg-rose-955/15 px-2 py-0.5 rounded border border-rose-100/50 dark:border-rose-950/20 font-mono">{{ change.oldVal }}</span>
                                            <span v-else class="text-slate-400 italic font-mono">-</span>
                                        </td>
                                        <td class="px-1 py-2 text-xs text-slate-400 text-center font-bold">
                                            <span v-if="change.oldVal !== undefined && change.newVal !== undefined">→</span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-emerald-655 dark:text-emerald-450 font-bold">
                                            <span v-if="change.newVal !== undefined" class="bg-emerald-50/30 dark:bg-emerald-955/15 px-2 py-0.5 rounded border border-emerald-100/50 dark:border-emerald-950/20 font-mono">{{ change.newVal }}</span>
                                            <span v-else class="text-slate-400 italic font-mono">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="text-xs text-slate-400 italic py-4 text-center">
                        No payload changes or structured activity details available.
                    </div>
                </div>

                <!-- Linked entity information -->
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-650 dark:text-indigo-405 mb-2">Polymorphic Database Target</p>
                    <div v-if="selectedLog.reference_type" class="flex flex-wrap items-center justify-between gap-4 bg-slate-50 dark:bg-slate-950/20 p-3.5 rounded-xl border border-slate-150 dark:border-slate-800">
                        <div>
                            <span class="text-xs font-black uppercase tracking-widest text-slate-400 mr-2">Target Entity:</span>
                            <span class="font-mono text-sm font-black text-slate-800 dark:text-slate-205 mr-4">{{ selectedLog.reference_type }}</span>
                            <span class="text-xs font-black uppercase tracking-widest text-slate-400 mr-2">Record ID:</span>
                            <span class="font-mono text-sm font-black text-indigo-600 dark:text-indigo-405">#{{ selectedLog.reference_id }}</span>
                        </div>
                    </div>
                    <div v-else class="text-xs text-slate-400 italic py-2">
                        No morphable database target is linked to this inventory audit entry.
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="flex items-center justify-between w-full border-t border-slate-100 dark:border-slate-800 pt-4 mt-6">
                    <!-- Network Meta info (IP) -->
                    <div v-if="selectedLog" class="flex items-center gap-1.5 text-[10px] font-mono text-slate-400 dark:text-slate-550">
                        <GlobeAltIcon class="w-3.5 h-3.5" />
                        IP Origin: {{ selectedLog.ip_address || '127.0.0.1' }}
                        <span class="mx-2">•</span>
                        Plant Scope: {{ selectedLog.plant ? selectedLog.plant.name : 'Global' }}
                    </div>
                    
                    <div class="flex gap-2">
                        <button 
                            @click="showDetailModal = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition-all shadow-sm"
                        >
                            Close
                        </button>
                        <button 
                            v-if="selectedLog"
                            @click="router.visit(route('inventory-audit-logs.show', selectedLog.id))"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5"
                        >
                            <EyeIcon class="w-4 h-4" />
                            Open Dedicated Analysis Page
                        </button>
                    </div>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

