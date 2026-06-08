<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon,
    BuildingOfficeIcon,
    UserCircleIcon,
    ClockIcon,
    GlobeAltIcon,
    DocumentTextIcon,
    LinkIcon,
    ClipboardDocumentIcon,
    ArrowsRightLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    log: {
        id: number;
        plant_id: number | null;
        plant: { id: number; name: string } | null;
        transaction_type: string;
        reference_type: string | null;
        reference_id: number | null;
        reference: any | null;
        log_from: string | number;
        log_to: string | number;
        user: { id: number; name: string; email: string } | null;
        remarks: string | null;
        ip_address: string | null;
        created_at: string;
    };
}>();

const showRawReference = ref(false);

function formatDate(value: string): string {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
}

const changeDetail = computed(() => {
    const f = parseFloat(props.log.log_from as string) || 0;
    const t = parseFloat(props.log.log_to as string) || 0;
    const diff = t - f;
    const isPositive = diff > 0;
    const isNeutral = diff === 0;
    
    return {
        value: diff,
        formatted: isNeutral ? 'No Change' : (isPositive ? `+${diff.toFixed(4)}` : diff.toFixed(4)),
        isPositive,
        isNeutral
    };
});

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

const referenceProperties = computed(() => {
    if (!props.log.reference) return [];
    
    // Filter out internal and common Eloquent attributes for cleaner display
    const hideList = ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
    return Object.entries(props.log.reference)
        .filter(([key, val]) => !hideList.includes(key) && val !== null && typeof val !== 'object')
        .map(([key, val]) => ({
            label: key.replace(/_/g, ' ').toUpperCase(),
            value: val
        }));
});

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
    const actionMatch = trimRemarks.match(/^(Updated|Created|Deleted):\s*(.*)$/s);
    if (!actionMatch) {
        return { action: 'Custom', raw: trimRemarks, changes: [] };
    }
    
    const action = actionMatch[1] as 'Created' | 'Updated' | 'Deleted';
    const content = actionMatch[2].trim();
    
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
        Created: 'bg-emerald-50 text-emerald-705 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900',
        Updated: 'bg-amber-50 text-amber-705 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900',
        Deleted: 'bg-rose-50 text-rose-705 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900',
    };
    return map[action] ?? 'bg-slate-50 text-slate-655 border-slate-200 dark:bg-slate-805 dark:text-slate-400 dark:border-slate-700';
}

function formatFieldLabel(field: string): string {
    return field.replace(/_/g, ' ');
}
</script>

<template>
    <AppLayout title="Audit Log Details">
        <Head title="Audit Log Details" />
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-4">
                    <button
                        @click="router.visit(route('inventory-audit-logs.index'))"
                        class="p-2.5 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:border-slate-850 dark:hover:bg-slate-800 rounded-xl transition-all shadow-sm group"
                    >
                        <ArrowLeftIcon class="w-4 h-4 text-slate-600 dark:text-slate-400 group-hover:-translate-x-0.5 transition-transform" />
                    </button>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Log Entry Analysis</p>
                        <h1 class="text-xl font-black tracking-tight text-slate-900 dark:text-slate-100">Audit Log #{{ log.id }}</h1>
                    </div>
                </div>
                <div>
                    <span
                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-black uppercase tracking-wider shadow-sm"
                        :class="badgeClass(log.transaction_type)"
                    >
                        {{ log.transaction_type }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Details Cards: metadata/operator -->
                <div class="space-y-6 lg:col-span-1">
                    <!-- General details card -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 shadow-sm p-6 space-y-5">
                        <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 border-b border-slate-100 dark:border-slate-800 pb-3">General Metadata</h3>
                        
                        <div class="space-y-4">
                            <!-- Timestamp -->
                            <div class="flex gap-3">
                                <ClockIcon class="w-5 h-5 text-slate-400 flex-shrink-0" />
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Timestamp</p>
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ formatDate(log.created_at) }}</p>
                                </div>
                            </div>
                            
                            <!-- Plant Scope -->
                            <div class="flex gap-3">
                                <BuildingOfficeIcon class="w-5 h-5 text-slate-400 flex-shrink-0" />
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Plant / Location</p>
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ log.plant ? log.plant.name : 'Global / Multi-Plant Context' }}</p>
                                </div>
                            </div>

                            <!-- IP Address -->
                            <div class="flex gap-3">
                                <GlobeAltIcon class="w-5 h-5 text-slate-400 flex-shrink-0" />
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Origin IP Address</p>
                                    <p class="text-sm font-semibold text-slate-650 dark:text-slate-350 font-mono">{{ log.ip_address || '127.0.0.1 (Local System)' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operator card -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 shadow-sm p-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">Responsible Actor</h3>
                        <div v-if="log.user" class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center text-indigo-650 dark:text-indigo-400 text-sm font-black shadow-inner">
                                {{ log.user.name[0].toUpperCase() }}
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-850 dark:text-slate-100">{{ log.user.name }}</p>
                                <p class="text-xs text-slate-400">{{ log.user.email }}</p>
                                <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-tighter mt-0.5">Database User #{{ log.user.id }}</p>
                            </div>
                        </div>
                        <div v-else class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 text-sm font-black">
                                SYS
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-250">System Event</p>
                                <p class="text-xs text-slate-400">Automated Background Engine</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Details Panel: Quantity shift & Reference details -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Stock Shift Panel -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 shadow-sm p-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 border-b border-slate-100 dark:border-slate-800 pb-3 mb-6">Quantity / Stock Delta</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 relative items-center">
                            <!-- From Quantity -->
                            <div class="bg-slate-50/60 dark:bg-slate-950/30 p-5 rounded-2xl border border-slate-100 dark:border-slate-900 text-center shadow-inner">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Starting Level</p>
                                <p class="text-2xl font-black text-slate-650 dark:text-slate-400">{{ parseFloat(log.log_from as string).toFixed(4) }}</p>
                            </div>

                            <!-- Shift Delta -->
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1">
                                    <ArrowsRightLeftIcon class="w-3.5 h-3.5" />
                                    Transaction Delta
                                </span>
                                <div
                                    class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-sm font-black tracking-wide shadow-sm border"
                                    :class="[
                                        changeDetail.isNeutral 
                                            ? 'bg-slate-100 text-slate-650 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' 
                                            : (changeDetail.isPositive 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50' 
                                                : 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50')
                                    ]"
                                >
                                    {{ changeDetail.formatted }}
                                </div>
                            </div>

                            <!-- To Quantity -->
                            <div class="bg-indigo-50/30 dark:bg-indigo-950/10 p-5 rounded-2xl border border-indigo-100/30 dark:border-slate-850 text-center">
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-1">Ending Level</p>
                                <p class="text-3xl font-black text-indigo-650 dark:text-indigo-300">{{ parseFloat(log.log_to as string).toFixed(4) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks Panel (The Main Highlight) -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 shadow-sm p-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
                            <div class="flex items-center gap-2">
                                <DocumentTextIcon class="w-5 h-5 text-indigo-500" />
                                <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Remarks & Activity Details</h3>
                            </div>
                            <span 
                                v-if="parseRemarks(log.remarks).action !== 'Custom'"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border"
                                :class="getActionBadgeClass(parseRemarks(log.remarks).action)"
                            >
                                {{ parseRemarks(log.remarks).action }}
                            </span>
                        </div>

                        <div v-if="log.remarks">
                            <div v-if="parseRemarks(log.remarks).action !== 'Custom'" class="space-y-4">
                                <div class="overflow-hidden rounded-xl border border-slate-150 dark:border-slate-800 bg-slate-50/20 dark:bg-slate-900/10">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800">
                                                <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Field</th>
                                                <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Original Value</th>
                                                <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider"></th>
                                                <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">New Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                                            <tr 
                                                v-for="(change, idx) in parseRemarks(log.remarks).changes" 
                                                :key="idx"
                                                class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                                            >
                                                <td class="px-4 py-3 text-xs font-bold text-slate-700 dark:text-slate-300 capitalize font-mono">{{ formatFieldLabel(change.field) }}</td>
                                                <td class="px-4 py-3 text-xs text-rose-600 dark:text-rose-400 font-medium">
                                                    <span v-if="change.oldVal !== undefined" class="line-through decoration-rose-300/40 bg-rose-50/50 dark:bg-rose-950/10 px-2.5 py-1 rounded border border-rose-100/50 dark:border-rose-950/20 font-mono">{{ change.oldVal }}</span>
                                                    <span v-else class="text-slate-355 italic">-</span>
                                                </td>
                                                <td class="px-2 py-3 text-xs text-slate-400 text-center font-bold">
                                                    <span v-if="change.oldVal !== undefined && change.newVal !== undefined">→</span>
                                                </td>
                                                <td class="px-4 py-3 text-xs text-emerald-650 dark:text-emerald-450 font-bold">
                                                    <span v-if="change.newVal !== undefined" class="bg-emerald-50/50 dark:bg-emerald-950/10 px-2.5 py-1 rounded border border-emerald-100/50 dark:border-emerald-950/20 font-mono">{{ change.newVal }}</span>
                                                    <span v-else class="text-slate-355 italic">-</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-else class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed font-semibold italic bg-slate-50/50 dark:bg-slate-950/20 border-l-4 border-indigo-500 p-4 rounded-r-xl">
                                "{{ log.remarks }}"
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-400 italic">
                            No remarks or documentation notes are attached to this log entry.
                        </div>
                    </div>

                    <!-- Reference Information Panel -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-850 shadow-sm p-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
                            <div class="flex items-center gap-2">
                                <LinkIcon class="w-5 h-5 text-indigo-500" />
                                <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Polymorphic database target</h3>
                            </div>
                            <button
                                v-if="log.reference"
                                @click="showRawReference = !showRawReference"
                                class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-indigo-500 transition-colors"
                            >
                                {{ showRawReference ? 'Show Simple View' : 'Inspect Raw Data' }}
                            </button>
                        </div>

                        <div v-if="log.reference_type">
                            <div class="mb-4">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Model / Entity Type: </span>
                                <span class="font-mono text-sm font-black text-slate-800 dark:text-slate-200">{{ log.reference_type }}</span>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest ml-4">Record ID: </span>
                                <span class="font-mono text-sm font-black text-slate-850 dark:text-indigo-400">#{{ log.reference_id }}</span>
                            </div>

                            <!-- Raw reference JSON -->
                            <div v-if="showRawReference || !referenceProperties.length" class="mt-4">
                                <pre class="bg-slate-950 text-slate-100 text-xs p-4 rounded-xl max-h-96 overflow-auto font-mono leading-relaxed border border-slate-850 shadow-inner">{{ JSON.stringify(log.reference || {}, null, 2) }}</pre>
                            </div>
                            
                            <!-- Filtered Properties list -->
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3 bg-slate-50/50 dark:bg-slate-950/20 p-4 rounded-xl border border-slate-100 dark:border-slate-850">
                                <div 
                                    v-for="prop in referenceProperties" 
                                    :key="prop.label" 
                                    class="flex flex-col py-1.5 border-b border-slate-100/50 dark:border-slate-900"
                                >
                                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ prop.label }}</span>
                                    <span class="text-xs font-semibold text-slate-755 dark:text-slate-300 font-mono break-all mt-0.5">{{ prop.value }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-slate-400 italic py-6 text-center">
                            <ClipboardDocumentIcon class="w-10 h-10 text-slate-250 mx-auto mb-2" />
                            No polymorphic reference linked to this inventory audit log entry.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
