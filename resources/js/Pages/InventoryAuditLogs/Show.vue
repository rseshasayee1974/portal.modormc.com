<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ClipboardDocumentListIcon,
    ClockIcon,
    UserCircleIcon,
    ServerStackIcon,
    ArrowLeftIcon,
    GlobeAltIcon,
    BuildingOfficeIcon,
    AdjustmentsHorizontalIcon,
    DocumentTextIcon,
    TableCellsIcon,
    CodeBracketIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    log: {
        id: number;
        plant_id?: number | null;
        plant?: { id: number; name: string } | null;
        transaction_type: string;
        reference_type?: string | null;
        reference_id?: number | string | null;
        table_name?: string | null;
        reference?: any;
        log_from?: any;
        log_to?: any;
        user?: { id: number; name: string; email: string } | null;
        remarks?: string | null;
        ip_address?: string | null;
        created_at?: string | null;
    };
}>();

const activeTab = ref<'visual' | 'raw'>('visual');
const showOnlyModified = ref(true);
const searchFieldQuery = ref('');

// ── Formatting Helpers ────────────────────────────────────────────────────────
function formatDate(value?: string | null): string {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
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

function getActionBadgeClass(action: string): string {
    const map: Record<string, string> = {
        Created: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900',
        Updated: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900',
        Deleted: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900',
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

// ── Payload Parsers ───────────────────────────────────────────────────────────
const parsedLogFrom = computed(() => {
    if (!props.log) return null;
    try {
        const val = props.log.log_from;
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
    if (!props.log) return null;
    try {
        const val = props.log.log_to;
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

interface ParsedRemark {
    action: 'Created' | 'Updated' | 'Deleted' | 'Custom';
    targetEntity?: string;
    targetTable?: string;
    targetId?: string;
    raw: string;
    changes: Array<{
        field: string;
        oldVal?: string;
        newVal?: string;
    }>;
}

const parsedRemarks = computed<ParsedRemark>(() => {
    const remarks = props.log?.remarks;
    if (!remarks) {
        return { action: 'Custom', raw: '', changes: [] };
    }
    
    const trimRemarks = remarks.trim();
    let action: 'Created' | 'Updated' | 'Deleted' | 'Custom' = 'Custom';
    let targetEntity = '';
    let targetTable = '';
    let targetId = '';
    let content = trimRemarks;
    
    const actionMatch = trimRemarks.match(/^(Updated|Created|Deleted)(?:\s+([A-Za-z0-9_]+))?(?:\s*\(([^)]+)\))?(?:\s*#(\d+))?:\s*(.*)$/s);
    if (actionMatch) {
        action = actionMatch[1] as 'Created' | 'Updated' | 'Deleted';
        targetEntity = actionMatch[2] || '';
        targetTable = actionMatch[3] || '';
        targetId = actionMatch[4] || '';
        content = actionMatch[5].trim();
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
        targetEntity,
        targetTable,
        targetId,
        raw: trimRemarks,
        changes
    };
});
</script>

<template>
    <AppLayout title="Audit Log Details">
        <Head :title="`Audit Log #${log.id}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('inventory-audit-logs.index')"
                        class="p-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-slate-700 transition-all shadow-sm"
                        title="Back to Audit Logs"
                    >
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.24em] text-indigo-600 dark:text-indigo-400">Audit Trail Analysis</span>
                            <span class="text-xs font-mono font-bold text-slate-400">#{{ log.id }}</span>
                        </div>
                        <h1 class="text-xl font-black tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            Inventory Audit Log Details
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span 
                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-black uppercase tracking-wider"
                        :class="badgeClass(log.transaction_type)"
                    >
                        {{ log.transaction_type }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Top Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Log ID & Date -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center shrink-0">
                        <ClockIcon class="w-6 h-6 text-indigo-500" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Logged Timestamp</p>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" :title="formatDate(log.created_at)">
                            {{ formatDate(log.created_at) }}
                        </p>
                    </div>
                </div>

                <!-- Responsible Actor / Operator -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-violet-50 dark:bg-violet-950/40 flex items-center justify-center shrink-0">
                        <UserCircleIcon class="w-6 h-6 text-violet-500" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Responsible Operator</p>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" :title="log.user?.name || 'System'">
                            {{ log.user ? log.user.name : 'System Automated' }}
                        </p>
                        <p v-if="log.user?.email" class="text-[10px] text-slate-400 font-mono truncate" :title="log.user.email">
                            {{ log.user.email }}
                        </p>
                    </div>
                </div>

                <!-- Plant / Facility Scope -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center shrink-0">
                        <BuildingOfficeIcon class="w-6 h-6 text-emerald-500" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Plant / Facility</p>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">
                            {{ log.plant ? log.plant.name : 'Global (Unscoped)' }}
                        </p>
                    </div>
                </div>

                <!-- Network Origin (IP Address) -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/40 flex items-center justify-center shrink-0">
                        <GlobeAltIcon class="w-6 h-6 text-amber-500" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">IP Origin</p>
                        <p class="text-xs font-mono font-bold text-slate-800 dark:text-slate-100 truncate">
                            {{ log.ip_address || '127.0.0.1' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Transition / Delta Display -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4">
                    {{ isNumericValue(log.log_from) && isNumericValue(log.log_to) ? 'Quantity Level Shift' : 'Payload Change Overview' }}
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <template v-if="isNumericValue(log.log_from) && isNumericValue(log.log_to)">
                        <div class="bg-slate-50 dark:bg-slate-950/40 p-5 rounded-2xl text-center border border-slate-200/80 dark:border-slate-800">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Starting Level (From)</p>
                            <p class="text-2xl font-black text-slate-700 dark:text-slate-300 font-mono">{{ parseFloat(log.log_from).toFixed(4) }}</p>
                        </div>
                        <div class="flex flex-col items-center justify-center">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Net Delta</span>
                            <div 
                                class="px-4 py-1.5 rounded-xl text-sm font-black border shadow-xs"
                                :class="[
                                    getChange(log.log_from, log.log_to).isNeutral 
                                        ? 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' 
                                        : (getChange(log.log_from, log.log_to).isPositive 
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900' 
                                            : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900')
                                ]"
                            >
                                {{ getChange(log.log_from, log.log_to).formatted }}
                            </div>
                        </div>
                        <div class="bg-indigo-50/40 dark:bg-indigo-950/30 p-5 rounded-2xl text-center border border-indigo-100 dark:border-indigo-900/50">
                            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-1">Ending Level (To)</p>
                            <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 font-mono">{{ parseFloat(log.log_to).toFixed(4) }}</p>
                        </div>
                    </template>
                    <template v-else>
                        <div class="bg-slate-50 dark:bg-slate-950/40 p-5 rounded-2xl text-center border border-slate-200/80 dark:border-slate-800">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Pre-Save State</p>
                            <p class="text-sm font-mono text-slate-600 dark:text-slate-300 font-bold">Fields: {{ countJSONFields(log.log_from) }}</p>
                        </div>
                        <div class="flex flex-col items-center justify-center">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Modified Attributes</span>
                            <div class="px-4 py-1.5 rounded-xl text-xs font-black bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900">
                                {{ allFieldsDiff.filter(item => item.isModified).length }} fields updated
                            </div>
                        </div>
                        <div class="bg-indigo-50/40 dark:bg-indigo-950/30 p-5 rounded-2xl text-center border border-indigo-100 dark:border-indigo-900/50">
                            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-1">Post-Save State</p>
                            <p class="text-sm font-mono text-indigo-600 dark:text-indigo-400 font-bold">Fields: {{ countJSONFields(log.log_to) }}</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Main Diff & Payload Breakdown -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-slate-100 tracking-tight flex items-center gap-2">
                            <TableCellsIcon class="w-5 h-5 text-indigo-500" />
                            Attribute Diffs & Payload Audit
                        </h3>
                        <p class="text-xs text-slate-400 font-medium">Detailed attribute-by-attribute comparison of the audit snapshot.</p>
                    </div>

                    <div v-if="parsedLogFrom || parsedLogTo" class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl self-start sm:self-auto">
                        <button
                            @click="activeTab = 'visual'"
                            class="px-4 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5"
                            :class="activeTab === 'visual' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        >
                            <TableCellsIcon class="w-4 h-4" />
                            Visual Diff
                        </button>
                        <button
                            @click="activeTab = 'raw'"
                            class="px-4 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5"
                            :class="activeTab === 'raw' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        >
                            <CodeBracketIcon class="w-4 h-4" />
                            Raw JSON
                        </button>
                    </div>
                </div>

                <!-- Remarks Banner if custom remarks exist -->
                <div v-if="log.remarks" class="p-4 bg-slate-50 dark:bg-slate-950/40 rounded-xl border-l-4 border-indigo-500 border-t border-r border-b border-slate-200/60 dark:border-slate-800 space-y-1">
                    <p class="text-[9px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Activity Remarks / System Log</p>
                    <p class="text-xs text-slate-700 dark:text-slate-300 font-semibold italic">
                        "{{ log.remarks }}"
                    </p>
                </div>

                <div v-if="parsedLogFrom || parsedLogTo">
                    <!-- Visual Diff Tab -->
                    <div v-if="activeTab === 'visual'" class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <input
                                v-model="searchFieldQuery"
                                type="text"
                                placeholder="Search field or value..."
                                class="px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-700 dark:text-slate-300 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:max-w-xs"
                            />
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    v-model="showOnlyModified"
                                    type="checkbox"
                                    class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                                />
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Show modified attributes only</span>
                            </label>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800">
                                        <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Field Name</th>
                                        <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Original Value</th>
                                        <th class="px-2 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center"></th>
                                        <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Updated Value</th>
                                        <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                                    <tr 
                                        v-for="(item, idx) in filteredDiffFields" 
                                        :key="idx"
                                        class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors"
                                    >
                                        <td class="px-4 py-2.5 text-xs font-bold text-slate-800 dark:text-slate-200 capitalize font-mono">{{ formatFieldLabel(item.field) }}</td>
                                        <td class="px-4 py-2.5 text-xs text-rose-600 dark:text-rose-400 font-medium">
                                            <span 
                                                v-if="item.changeType !== 'added' && item.from !== null && item.from !== undefined"
                                                :class="item.isModified ? 'line-through decoration-rose-300/40 bg-rose-50 dark:bg-rose-950/20 px-2 py-0.5 rounded border border-rose-200/60 dark:border-rose-900/40 font-mono' : 'font-mono text-slate-400'"
                                            >
                                                {{ item.from === '' ? '[empty]' : item.from }}
                                            </span>
                                            <span v-else class="text-slate-400 italic font-mono">-</span>
                                        </td>
                                        <td class="px-2 py-2.5 text-xs text-slate-400 text-center font-bold">
                                            <span v-if="item.isModified">→</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                                            <span 
                                                v-if="item.changeType !== 'removed' && item.to !== null && item.to !== undefined"
                                                :class="item.isModified ? 'bg-emerald-50 dark:bg-emerald-950/20 px-2 py-0.5 rounded border border-emerald-200/60 dark:border-emerald-900/40 font-mono' : 'font-mono text-slate-700 dark:text-slate-300 font-medium'"
                                            >
                                                {{ item.to === '' ? '[empty]' : item.to }}
                                            </span>
                                            <span v-else class="text-slate-400 italic font-mono">-</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <span 
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider border"
                                                :class="[
                                                    item.changeType === 'modified' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900' :
                                                    item.changeType === 'added' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900' :
                                                    item.changeType === 'removed' ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900' :
                                                    'bg-slate-50 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'
                                                ]"
                                            >
                                                {{ item.changeType }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredDiffFields.length === 0">
                                        <td colspan="5" class="px-4 py-8 text-center text-xs text-slate-400 italic">No matching changes found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Raw JSON Tab -->
                    <div v-else-if="activeTab === 'raw'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Pre-Save State (Raw JSON)</p>
                            <pre class="bg-slate-950 text-slate-100 text-xs p-4 rounded-xl max-h-96 overflow-auto font-mono border border-slate-800 shadow-inner">{{ JSON.stringify(parsedLogFrom || {}, null, 2) }}</pre>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-2">Post-Save State (Raw JSON)</p>
                            <pre class="bg-slate-950 text-slate-100 text-xs p-4 rounded-xl max-h-96 overflow-auto font-mono border border-slate-800 shadow-inner">{{ JSON.stringify(parsedLogTo || {}, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
                <!-- Structured Remarks Changes Fallback -->
                <div v-else-if="parsedRemarks.changes.length > 0">
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800">
                                    <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Field Name</th>
                                    <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Original Value</th>
                                    <th class="px-2 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center"></th>
                                    <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Updated Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                                <tr 
                                    v-for="(change, idx) in parsedRemarks.changes" 
                                    :key="idx"
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors"
                                >
                                    <td class="px-4 py-2.5 text-xs font-bold text-slate-800 dark:text-slate-200 capitalize font-mono">{{ formatFieldLabel(change.field) }}</td>
                                    <td class="px-4 py-2.5 text-xs text-rose-600 dark:text-rose-400 font-medium">
                                        <span v-if="change.oldVal !== undefined" class="line-through decoration-rose-300/40 bg-rose-50 dark:bg-rose-950/20 px-2 py-0.5 rounded border border-rose-200/60 dark:border-rose-900/40 font-mono">{{ change.oldVal }}</span>
                                        <span v-else class="text-slate-400 italic font-mono">-</span>
                                    </td>
                                    <td class="px-2 py-2.5 text-xs text-slate-400 text-center font-bold">
                                        <span v-if="change.oldVal !== undefined && change.newVal !== undefined">→</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                                        <span v-if="change.newVal !== undefined" class="bg-emerald-50 dark:bg-emerald-950/20 px-2 py-0.5 rounded border border-emerald-200/60 dark:border-emerald-900/40 font-mono">{{ change.newVal }}</span>
                                        <span v-else class="text-slate-400 italic font-mono">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="text-xs text-slate-400 italic py-6 text-center">
                    No structured attribute diffs available for this record.
                </div>
            </div>

            <!-- Polymorphic Target & Loaded Reference Model Record -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                <h3 class="text-base font-black text-slate-900 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <ServerStackIcon class="w-5 h-5 text-indigo-500" />
                    Polymorphic Database Target Record
                </h3>

                <div v-if="log.reference_type" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-950/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Model Class</span>
                            <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">{{ log.reference_type }}</span>
                        </div>
                        <div v-if="log.table_name || parsedRemarks.targetTable">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Database Table</span>
                            <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">{{ log.table_name || parsedRemarks.targetTable }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Target Record ID</span>
                            <span class="font-mono text-xs font-black text-indigo-600 dark:text-indigo-400">#{{ log.reference_id }}</span>
                        </div>
                    </div>

                    <!-- Target Model Record State if fetched by controller -->
                    <div v-if="log.reference" class="space-y-2">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Current Target Model Database State</p>
                        <pre class="bg-slate-950 text-slate-100 text-xs p-4 rounded-xl max-h-80 overflow-auto font-mono border border-slate-800 shadow-inner">{{ JSON.stringify(log.reference, null, 2) }}</pre>
                    </div>
                </div>
                <div v-else class="text-xs text-slate-400 italic py-2">
                    No morphable database target reference attached to this inventory audit record.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
