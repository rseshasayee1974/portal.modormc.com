<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';
import {
    ShieldCheckIcon,
    ClockIcon,
    UserCircleIcon,
    ServerStackIcon,
} from '@heroicons/vue/24/outline';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Column from 'primevue/column';

const props = defineProps<{
    logs: any;          // paginated Inertia collection
    actionTypes: string[];
    modules: string[];
    users: { id: number; label: string }[];
}>();

// ── PrimeVue client-side filters ─────────────────────────────────────────────
const filters = ref({
    global:      { value: null, matchMode: FilterMatchMode.CONTAINS },
    action_type: { value: null, matchMode: FilterMatchMode.EQUALS },
    module_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
    description: { value: null, matchMode: FilterMatchMode.CONTAINS },
    'user.name': { value: null, matchMode: FilterMatchMode.CONTAINS },
    trace_id:    { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// ── Flattened data for table ──────────────────────────────────────────────────
const rows = computed(() =>
    (props.logs?.data ?? []).map((row: any) => ({
        ...row,
        user_name:  row.user?.name  ?? 'System',
        user_email: row.user?.email ?? row.ip_address ?? 'N/A',
    }))
);

// ── Action badge colours ──────────────────────────────────────────────────────
function badgeClass(type: string): string {
    const map: Record<string, string> = {
        CREATE:           'bg-emerald-50 text-emerald-700 border-emerald-200',
        UPDATE:           'bg-sky-50 text-sky-700 border-sky-200',
        DELETE:           'bg-rose-50 text-rose-700 border-rose-200',
        SOFT_DELETE:      'bg-rose-50 text-rose-700 border-rose-200',
        RESTORE:          'bg-lime-50 text-lime-700 border-lime-200',
        LOGIN:            'bg-violet-50 text-violet-700 border-violet-200',
        LOGOUT:           'bg-slate-100 text-slate-700 border-slate-200',
        ASSIGN:           'bg-amber-50 text-amber-700 border-amber-200',
        UNASSIGN:         'bg-orange-50 text-orange-700 border-orange-200',
        SYSTEM_EVENT:     'bg-amber-50 text-amber-700 border-amber-200',
        API_CALL:         'bg-cyan-50 text-cyan-700 border-cyan-200',
        EXPORT:           'bg-indigo-50 text-indigo-700 border-indigo-200',
        IMPORT:           'bg-purple-50 text-purple-700 border-purple-200',
        UPLOAD:           'bg-teal-50 text-teal-700 border-teal-200',
        DOWNLOAD:         'bg-blue-50 text-blue-700 border-blue-200',
        PAYMENT:          'bg-green-50 text-green-700 border-green-200',
        APPROVE:          'bg-emerald-50 text-emerald-700 border-emerald-200',
        REJECT:           'bg-red-50 text-red-700 border-red-200',
        PASSWORD_CHANGE:  'bg-pink-50 text-pink-700 border-pink-200',
        PERMISSION_CHANGE:'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
        ROLE_CHANGE:      'bg-violet-50 text-violet-700 border-violet-200',
    };
    return map[type] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

function formatDate(value: string): string {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function pretty(value: any): string {
    return JSON.stringify(value ?? {}, null, 2);
}

// ── Dropdown option helpers ───────────────────────────────────────────────────
const actionOptions = computed(() =>
    props.actionTypes.map(t => ({ label: t, value: t }))
);

const moduleOptions = computed(() =>
    props.modules.map(m => ({ label: m, value: m }))
);

const userOptions = computed(() =>
    props.users.map(u => ({ label: u.label, value: u.label.split(' (')[0] }))
);
</script>

<template>
    <AppLayout title="Audit Logs">
        <template #header>
            <div class="flex items-center gap-4">
                <div class="p-3 bg-indigo-50 rounded-2xl">
                    <ShieldCheckIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Enterprise Audit Trail</p>
                    <h1 class="text-xl font-black tracking-tight text-slate-900">Activity Logs</h1>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">

            <!-- ── Stats strip ─────────────────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <ServerStackIcon class="w-5 h-5 text-indigo-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Records</p>
                        <p class="text-2xl font-black text-slate-800">{{ logs.total }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <ClockIcon class="w-5 h-5 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">This Page</p>
                        <p class="text-2xl font-black text-slate-800">{{ rows.length }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center">
                        <UserCircleIcon class="w-5 h-5 text-violet-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Users</p>
                        <p class="text-2xl font-black text-slate-800">{{ users.length }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                        <ShieldCheckIcon class="w-5 h-5 text-amber-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Modules</p>
                        <p class="text-2xl font-black text-slate-800">{{ modules.length }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Custom filter bar above table ──────────────────── -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Filter Logs</p>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <BaseInput
                        v-model="filters.global.value"
                        label="Global Search"
                        placeholder="Search description, entity, URL…"
                    />
                    <BaseSelect
                        v-model="filters.action_type.value"
                        :options="actionOptions"
                        option-label="label"
                        option-value="value"
                        label="Action Type"
                        placeholder="All Actions"
                        :show-clear="true"
                    />
                    <BaseSelect
                        v-model="filters.module_name.value"
                        :options="moduleOptions"
                        option-label="label"
                        option-value="value"
                        label="Module"
                        placeholder="All Modules"
                        :show-clear="true"
                    />
                    <BaseSelect
                        v-model="filters['user.name'].value"
                        :options="userOptions"
                        option-label="label"
                        option-value="value"
                        label="User"
                        placeholder="All Users"
                        :show-clear="true"
                    />
                </div>
            </div>

            <!-- ── DataTable ───────────────────────────────────────── -->
            <BaseDataTable
                :value="rows"
                :filters="filters"
                @update:filters="filters = $event"
                :global-filter-fields="['action_type','module_name','description','user_name','trace_id','entity_type']"
                filter-display="menu"
                heading="Activity Logs"
                heading-icon="ShieldCheckIcon"
                :paginator="true"
                :rows="20"
                :rows-per-page-options="[20, 50, 100]"
                data-key="id"
                :show-search="false"
                :striped-rows="true"
            >
                <!-- Action Type -->
                <Column field="action_type" header="Action" sortable style="min-width:160px">
                    <template #body="{ data }">
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider"
                            :class="badgeClass(data.action_type)"
                        >{{ data.action_type }}</span>
                    </template>
                    <template #filter="{ filterModel, filterCallback }">
                        <BaseSelect
                            :model-value="filterModel.value"
                            :options="actionOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Any action"
                            :show-clear="true"
                            @update:model-value="filterModel.value = $event; filterCallback()"
                        />
                    </template>
                </Column>

                <!-- Description -->
                <Column field="description" header="Description" sortable style="min-width:260px">
                    <template #body="{ data }">
                        <p class="text-sm text-slate-700 leading-snug max-w-xs truncate" :title="data.description">
                            {{ data.description }}
                        </p>
                    </template>
                    <template #filter="{ filterModel, filterCallback }">
                        <BaseInput
                            :model-value="filterModel.value"
                            placeholder="Search description"
                            @update:model-value="filterModel.value = $event; filterCallback()"
                        />
                    </template>
                </Column>

                <!-- Module -->
                <Column field="module_name" header="Module" sortable style="min-width:140px">
                    <template #body="{ data }">
                        <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg">
                            {{ data.module_name }}
                        </span>
                    </template>
                    <template #filter="{ filterModel, filterCallback }">
                        <BaseSelect
                            :model-value="filterModel.value"
                            :options="moduleOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Any module"
                            :show-clear="true"
                            @update:model-value="filterModel.value = $event; filterCallback()"
                        />
                    </template>
                </Column>

                <!-- Entity -->
                <Column field="entity_type" header="Entity" style="min-width:180px">
                    <template #body="{ data }">
                        <div class="text-xs text-slate-600 font-mono leading-tight">
                            <div class="truncate max-w-[160px]" :title="data.entity_type">{{ data.entity_type || 'N/A' }}</div>
                            <div class="text-slate-400">#{{ data.entity_id || 'N/A' }}</div>
                        </div>
                    </template>
                </Column>

                <!-- User -->
                <Column field="user_name" header="User" sortable style="min-width:160px">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-[10px] font-black">
                                {{ (data.user_name ?? 'S')[0].toUpperCase() }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-700">{{ data.user_name }}</div>
                                <div class="text-[10px] text-slate-400">{{ data.user_email }}</div>
                            </div>
                        </div>
                    </template>
                    <template #filter="{ filterModel, filterCallback }">
                        <BaseInput
                            :model-value="filterModel.value"
                            placeholder="User name"
                            @update:model-value="filterModel.value = $event; filterCallback()"
                        />
                    </template>
                </Column>

                <!-- Trace ID -->
                <Column field="trace_id" header="Trace" style="min-width:130px">
                    <template #body="{ data }">
                        <span class="text-[10px] text-slate-400 font-mono">{{ data.trace_id ? data.trace_id.slice(0, 12) + '…' : 'N/A' }}</span>
                    </template>
                    <template #filter="{ filterModel, filterCallback }">
                        <BaseInput
                            :model-value="filterModel.value"
                            placeholder="Trace ID"
                            @update:model-value="filterModel.value = $event; filterCallback()"
                        />
                    </template>
                </Column>

                <!-- Timestamp -->
                <Column field="created_at" header="When" sortable style="min-width:160px">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-600">{{ formatDate(data.created_at) }}</span>
                    </template>
                </Column>

                <!-- Expansion: diff panel -->
                <template #expansion="{ data }">
                    <div class="p-4 grid grid-cols-1 xl:grid-cols-3 gap-4">
                        <!-- Meta -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-3">Request Info</p>
                            <dl class="space-y-2 text-sm text-slate-600">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 w-28">Method:</span>
                                    <span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded">{{ data.request_method ?? 'N/A' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 w-28">Status:</span>
                                    <span :class="['font-mono text-xs px-2 py-0.5 rounded', data.response_status < 400 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700']">
                                        {{ data.response_status ?? 'N/A' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 w-28">IP:</span>
                                    <span class="text-xs text-slate-500">{{ data.ip_address ?? 'N/A' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 w-28">Browser:</span>
                                    <span class="text-xs text-slate-500">{{ data.browser ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 w-28">OS:</span>
                                    <span class="text-xs text-slate-500">{{ data.operating_system ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 w-28">Trace:</span>
                                    <span class="font-mono text-[10px] text-slate-400 break-all">{{ data.trace_id ?? 'N/A' }}</span>
                                </div>
                            </dl>
                            <div v-if="(data.changed_fields ?? []).length" class="mt-4 pt-4 border-t border-slate-100">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Changed Fields</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="field in data.changed_fields"
                                        :key="field"
                                        class="rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 px-2 py-0.5 text-[10px] font-bold"
                                    >{{ field }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Old Values -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-400 mb-3">Old Values</p>
                            <pre class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100 leading-relaxed">{{ pretty(data.old_values) }}</pre>
                        </div>

                        <!-- New Values -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-500 mb-3">New Values</p>
                            <pre class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100 leading-relaxed">{{ pretty(data.new_values ?? data.metadata) }}</pre>
                        </div>
                    </div>
                </template>
            </BaseDataTable>
        </div>
    </AppLayout>
</template>
