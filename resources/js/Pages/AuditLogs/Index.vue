<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { FunnelIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
    filters: Object,
    actionTypes: Array,
    modules: Array,
    users: Array,
});

const form = reactive({
    search: props.filters.search || '',
    action_type: props.filters.action_type || '',
    module_name: props.filters.module_name || '',
    user_id: props.filters.user_id || '',
    entity_type: props.filters.entity_type || '',
    trace_id: props.filters.trace_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const expandedId = ref(null);

const rows = computed(() => props.logs.data || []);

function applyFilters() {
    router.get(route('settings.audit-logs'), form, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    form.search = '';
    form.action_type = '';
    form.module_name = '';
    form.user_id = '';
    form.entity_type = '';
    form.trace_id = '';
    form.date_from = '';
    form.date_to = '';
    applyFilters();
}

function toggleRow(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

function pretty(value) {
    return JSON.stringify(value || {}, null, 2);
}

function badgeClass(type) {
    const map = {
        CREATE: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        UPDATE: 'bg-sky-50 text-sky-700 border-sky-200',
        DELETE: 'bg-rose-50 text-rose-700 border-rose-200',
        SOFT_DELETE: 'bg-rose-50 text-rose-700 border-rose-200',
        RESTORE: 'bg-lime-50 text-lime-700 border-lime-200',
        LOGIN: 'bg-violet-50 text-violet-700 border-violet-200',
        LOGOUT: 'bg-slate-100 text-slate-700 border-slate-200',
        SYSTEM_EVENT: 'bg-amber-50 text-amber-700 border-amber-200',
        API_CALL: 'bg-cyan-50 text-cyan-700 border-cyan-200',
    };

    return map[type] || 'bg-slate-100 text-slate-700 border-slate-200';
}

function formatDate(value) {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout title="Audit Logs">
        <div class="min-h-screen bg-slate-50">
            <div class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
                <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">Enterprise Audit Trail</p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Activity Logs</h1>
                            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                                Search entity history, trace API activity, and inspect full before/after changes from one place.
                            </p>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">
                            <FunnelIcon class="size-4" />
                            {{ logs.total }} records
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            Search
                            <div class="flex items-center rounded-2xl border border-slate-200 bg-white px-3">
                                <MagnifyingGlassIcon class="size-4 text-slate-400" />
                                <input v-model="form.search" class="w-full border-0 bg-transparent px-2 py-3 text-sm focus:ring-0" placeholder="Description, entity, URL" />
                            </div>
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            Action
                            <select v-model="form.action_type" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0">
                                <option value="">All actions</option>
                                <option v-for="type in actionTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            Module
                            <select v-model="form.module_name" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0">
                                <option value="">All modules</option>
                                <option v-for="module in modules" :key="module" :value="module">{{ module }}</option>
                            </select>
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            User
                            <select v-model="form.user_id" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0">
                                <option value="">All users</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.label }}</option>
                            </select>
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            Entity Type
                            <input v-model="form.entity_type" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0" placeholder="App\\Models\\Entity" />
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            Trace ID
                            <input v-model="form.trace_id" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0" placeholder="Trace correlation" />
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            From
                            <input v-model="form.date_from" type="date" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0" />
                        </label>

                        <label class="space-y-2 text-sm font-semibold text-slate-600">
                            To
                            <input v-model="form.date_to" type="date" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm focus:border-slate-400 focus:ring-0" />
                        </label>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-bold text-white hover:bg-slate-800" @click="applyFilters">
                            Apply Filters
                        </button>
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 hover:border-slate-300 hover:text-slate-900" @click="resetFilters">
                            Reset
                        </button>
                    </div>
                </section>

                <section class="mt-6 overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">
                                    <th class="px-4 py-4">Action</th>
                                    <th class="px-4 py-4">Module</th>
                                    <th class="px-4 py-4">Entity</th>
                                    <th class="px-4 py-4">User</th>
                                    <th class="px-4 py-4">Trace</th>
                                    <th class="px-4 py-4">When</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template v-for="row in rows" :key="row.id">
                                    <tr class="cursor-pointer hover:bg-slate-50" @click="toggleRow(row.id)">
                                        <td class="px-4 py-4">
                                            <div class="inline-flex rounded-full border px-3 py-1 text-xs font-bold" :class="badgeClass(row.action_type)">
                                                {{ row.action_type }}
                                            </div>
                                            <p class="mt-2 max-w-sm text-sm text-slate-600">{{ row.description }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm font-semibold text-slate-700">{{ row.module_name }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            <div>{{ row.entity_type || 'N/A' }}</div>
                                            <div class="text-xs text-slate-400">#{{ row.entity_id || 'N/A' }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            <div>{{ row.user?.name || 'System' }}</div>
                                            <div class="text-xs text-slate-400">{{ row.user?.email || row.ip_address || 'N/A' }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-xs text-slate-500">{{ row.trace_id || 'N/A' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-600">{{ formatDate(row.created_at) }}</td>
                                    </tr>
                                    <tr v-if="expandedId === row.id" class="bg-slate-50">
                                        <td colspan="6" class="px-4 py-5">
                                            <div class="grid gap-4 xl:grid-cols-3">
                                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Changed Fields</p>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        <span v-for="field in row.changed_fields || []" :key="field" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                            {{ field }}
                                                        </span>
                                                        <span v-if="!(row.changed_fields || []).length" class="text-sm text-slate-400">No field diff stored.</span>
                                                    </div>
                                                    <dl class="mt-4 space-y-2 text-sm text-slate-600">
                                                        <div><span class="font-semibold text-slate-800">Request:</span> {{ row.request_method || 'N/A' }}</div>
                                                        <div><span class="font-semibold text-slate-800">Status:</span> {{ row.response_status || 'N/A' }}</div>
                                                        <div><span class="font-semibold text-slate-800">Client:</span> {{ row.browser || 'Unknown' }} / {{ row.operating_system || 'Unknown' }}</div>
                                                    </dl>
                                                </div>

                                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Old Values</p>
                                                    <pre class="mt-3 max-h-80 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100">{{ pretty(row.old_values) }}</pre>
                                                </div>

                                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">New Values</p>
                                                    <pre class="mt-3 max-h-80 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100">{{ pretty(row.new_values || row.metadata) }}</pre>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!rows.length">
                                    <td colspan="6" class="px-4 py-16 text-center text-sm text-slate-500">
                                        No audit logs found for the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-4 text-sm text-slate-600">
                        <div>
                            Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of {{ logs.total }} records
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="link in logs.links"
                                :key="link.label"
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold"
                                :class="link.active ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600'"
                                :disabled="!link.url"
                                v-html="link.label"
                                @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
