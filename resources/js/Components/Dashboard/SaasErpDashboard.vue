<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import axios from 'axios';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import ProgressBar from 'primevue/progressbar';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';

const ApexChart = defineAsyncComponent({
    loader: () => import('vue3-apexcharts'),
    loadingComponent: Skeleton,
    delay: 120,
});

const props = defineProps({
    apiKey: { type: String, default: '' },
    plan: { type: String, default: 'growth' },
    canManageBilling: { type: Boolean, default: false },
    entityId: { type: [Number, String], default: null },
    plantId: { type: [Number, String, null], default: null },
    showApiPanel: { type: Boolean, default: false },
});

const loading = ref(false);
const apiKey = ref(props.apiKey);
const apiError = ref('');
const dashboard = ref({
    total_tokens: 0,
    total_cost: 0,
    total_requests: 0,
    usage_percent: 0,
    module_breakdown: [],
    daily_usage: [],
});

const entityId = computed(() => Number(props.entityId || 0));
const plantId = computed(() => props.plantId === null || props.plantId === '' ? null : Number(props.plantId));
const authHeader = computed(() => ({ Authorization: `Bearer ${apiKey.value}` }));

const fallbackDailyUsage = [
    { date: 'Mon', tokens: 8200, requests: 142 },
    { date: 'Tue', tokens: 12800, requests: 188 },
    { date: 'Wed', tokens: 16400, requests: 221 },
    { date: 'Thu', tokens: 15100, requests: 204 },
    { date: 'Fri', tokens: 19800, requests: 257 },
    { date: 'Sat', tokens: 11200, requests: 164 },
    { date: 'Sun', tokens: 9300, requests: 128 },
];

const fallbackModules = [
    { module: 'Dispatch', tokens: 18400, requests: 260, cost: 12.48 },
    { module: 'Batching', tokens: 14200, requests: 210, cost: 9.84 },
    { module: 'Billing', tokens: 9600, requests: 146, cost: 7.18 },
    { module: 'Inventory', tokens: 7800, requests: 118, cost: 5.42 },
    { module: 'Finance', tokens: 6200, requests: 92, cost: 4.74 },
];

const operations = [
    { label: 'Open dispatches', value: '42', change: '+12%', icon: 'pi-truck', tone: 'teal' },
    { label: 'Concrete produced', value: '1,284 m3', change: '+8.4%', icon: 'pi-chart-line', tone: 'indigo' },
    { label: 'Invoices pending', value: '18', change: '-6%', icon: 'pi-file-check', tone: 'amber' },
    { label: 'Receivables', value: 'INR 12.8L', change: '+3.1%', icon: 'pi-wallet', tone: 'rose' },
];

const dispatchQueue = [
    { ticket: 'DSP-1048', customer: 'Panchshil Realty', site: 'Tower C', grade: 'M30', qty: '18 m3', status: 'In Transit', severity: 'info' },
    { ticket: 'DSP-1049', customer: 'Altlogica Infra', site: 'Metro Pier 12', grade: 'M25', qty: '12 m3', status: 'Batching', severity: 'warning' },
    { ticket: 'DSP-1050', customer: 'OneModo Projects', site: 'Warehouse A', grade: 'M40', qty: '24 m3', status: 'Scheduled', severity: 'secondary' },
    { ticket: 'DSP-1051', customer: 'Densenet Tech', site: 'IT Park', grade: 'M20', qty: '9 m3', status: 'Delivered', severity: 'success' },
];

const materialLevels = [
    { name: 'Cement', value: 68, color: 'bg-indigo-500' },
    { name: '10mm Aggregate', value: 54, color: 'bg-teal-500' },
    { name: '20mm Aggregate', value: 76, color: 'bg-emerald-500' },
    { name: 'Sand', value: 39, color: 'bg-amber-500' },
];

const hasLiveUsage = computed(() => dashboard.value.daily_usage?.length || dashboard.value.module_breakdown?.length);
const dailyUsage = computed(() => hasLiveUsage.value ? dashboard.value.daily_usage : fallbackDailyUsage);
const moduleBreakdown = computed(() => hasLiveUsage.value ? dashboard.value.module_breakdown : fallbackModules);

const totals = computed(() => {
    const requests = Number(dashboard.value.total_requests || 0);
    const tokens = Number(dashboard.value.total_tokens || 0);
    const cost = Number(dashboard.value.total_cost || 0);

    return {
        requests: requests || 1488,
        tokens: tokens || 87500,
        cost: cost || 39.66,
        usagePercent: Number(dashboard.value.usage_percent || 0) || 67,
    };
});

const kpiCards = computed(() => [
    { label: 'AI/API Requests', value: totals.value.requests.toLocaleString(), helper: 'This month', icon: 'pi-bolt', tone: 'indigo' },
    { label: 'Tokens Used', value: totals.value.tokens.toLocaleString(), helper: `${totals.value.usagePercent}% of plan`, icon: 'pi-sparkles', tone: 'teal' },
    { label: 'Estimated Cost', value: `$${totals.value.cost.toFixed(2)}`, helper: props.plan || 'growth', icon: 'pi-dollar', tone: 'emerald' },
    { label: 'Plant Health', value: '94%', helper: 'Stable operations', icon: 'pi-shield', tone: 'amber' },
]);

const seriesUsage = computed(() => [
    {
        name: 'Requests',
        data: dailyUsage.value.map((row) => Number(row.requests || 0)),
    },
    {
        name: 'Tokens',
        data: dailyUsage.value.map((row) => Math.round(Number(row.tokens || 0) / 100)),
    },
]);

const usageChartOptions = computed(() => ({
    chart: {
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'Inter, system-ui, sans-serif',
    },
    stroke: { curve: 'smooth', width: 3 },
    colors: ['#0f766e', '#4f46e5'],
    dataLabels: { enabled: false },
    grid: { borderColor: '#e5e7eb', strokeDashArray: 5 },
    xaxis: {
        categories: dailyUsage.value.map((row) => row.date),
        labels: { style: { colors: '#64748b' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { labels: { style: { colors: '#64748b' } } },
    legend: { position: 'top', horizontalAlign: 'right' },
    tooltip: { theme: 'light' },
}));

const moduleSeries = computed(() => moduleBreakdown.value.map((row) => Number(row.requests || row.tokens || 0)));
const moduleChartOptions = computed(() => ({
    chart: { fontFamily: 'Inter, system-ui, sans-serif' },
    labels: moduleBreakdown.value.map((row) => row.module),
    colors: ['#0f766e', '#4f46e5', '#f59e0b', '#10b981', '#e11d48'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Modules',
                        formatter: () => String(moduleBreakdown.value.length),
                    },
                },
            },
        },
    },
}));

const loadDashboard = async () => {
    if (!entityId.value || !apiKey.value) {
        return;
    }

    loading.value = true;
    apiError.value = '';

    try {
        const res = await axios.get('/api/dashboard', {
            headers: authHeader.value,
            params: { entity_id: entityId.value, plant_id: plantId.value },
        });

        dashboard.value = res.data;
    } catch (error) {
        apiError.value = error.response?.data?.message || 'Unable to load live SaaS usage data.';
    } finally {
        loading.value = false;
    }
};

const regenerateApiKey = async () => {
    if (!props.canManageBilling || !apiKey.value) {
        return;
    }

    const res = await axios.post('/api/auth/regenerate-key', {}, { headers: authHeader.value });
    apiKey.value = res.data.api_key;
    await loadDashboard();
};

const toneClass = (tone) => ({
    teal: 'bg-teal-50 text-teal-700 ring-teal-100 dark:bg-teal-400/10 dark:text-teal-200 dark:ring-teal-400/20',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-100 dark:bg-indigo-400/10 dark:text-indigo-200 dark:ring-indigo-400/20',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-400/10 dark:text-emerald-200 dark:ring-emerald-400/20',
    amber: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/20',
    rose: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-400/10 dark:text-rose-200 dark:ring-rose-400/20',
}[tone] || 'bg-slate-50 text-slate-700 ring-slate-100 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10');

onMounted(loadDashboard);
</script>

<template>
    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-xl shadow-slate-900/10 sm:p-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(45,212,191,.34),transparent_28%),radial-gradient(circle_at_82%_44%,rgba(99,102,241,.28),transparent_32%)]" />
            <div class="absolute inset-0 opacity-[0.12] bg-[linear-gradient(rgba(255,255,255,.18)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.18)_1px,transparent_1px)] bg-[size:48px_48px]" />

            <div class="relative z-10 grid gap-8 lg:grid-cols-[1.35fr_0.65fr] lg:items-end">
                <div>
                    <Tag value="SaaS ERP Command Center" severity="info" class="mb-5 border border-white/10 bg-white/10 text-teal-100" />
                    <h1 class="max-w-3xl text-3xl font-semibold tracking-[-0.035em] sm:text-4xl lg:text-5xl">
                        Run dispatch, production, finance and SaaS usage from one dashboard.
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        A PrimeVue-powered ERP overview for RMC operations, tenant usage, billing health, material levels and live dispatch execution.
                    </p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/[0.08] p-5 backdrop-blur">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm font-medium text-white/80">Plan usage</p>
                        <span class="text-sm font-semibold text-teal-200">{{ totals.usagePercent }}%</span>
                    </div>
                    <ProgressBar :value="totals.usagePercent" :showValue="false" class="h-2 overflow-hidden rounded-full" />
                    <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                        <span>Current plan: {{ plan }}</span>
                        <span>{{ totals.tokens.toLocaleString() }} tokens</span>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="showApiPanel" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">API access</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use this key for SaaS usage and billing API requests.</p>
                </div>
                <Button label="Regenerate" icon="pi pi-refresh" :disabled="!canManageBilling" :loading="loading" class="rounded-xl" @click="regenerateApiKey" />
            </div>
            <InputText v-model="apiKey" readonly class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 font-mono text-xs dark:border-white/10 dark:bg-white/5 dark:text-white" />
            <p v-if="!canManageBilling" class="mt-2 text-xs text-amber-600 dark:text-amber-300">Only SaaS Owner or Platform Admin can regenerate the API key.</p>
            <p v-if="apiError" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ apiError }}</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="card in kpiCards" :key="card.label" class="overflow-hidden rounded-3xl border border-slate-200 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <template #content>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ card.value }}</p>
                            <p class="mt-2 text-xs font-medium text-slate-400">{{ card.helper }}</p>
                        </div>
                        <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl ring-1', toneClass(card.tone)]">
                            <i :class="['pi', card.icon]" aria-hidden="true" />
                        </div>
                    </div>
                </template>
            </Card>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.45fr_0.85fr]">
            <Card class="rounded-3xl border border-slate-200 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>Usage and request trend</span>
                        <Tag :value="loading ? 'Loading' : 'Live ready'" :severity="loading ? 'warning' : 'success'" />
                    </div>
                </template>
                <template #content>
                    <ApexChart type="area" height="335" :options="usageChartOptions" :series="seriesUsage" />
                </template>
            </Card>

            <Card class="rounded-3xl border border-slate-200 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <template #title>Module workload</template>
                <template #content>
                    <ApexChart type="donut" height="335" :options="moduleChartOptions" :series="moduleSeries" />
                </template>
            </Card>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_1fr]">
            <Card class="rounded-3xl border border-slate-200 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <template #title>Dispatch queue</template>
                <template #content>
                    <DataTable :value="dispatchQueue" dataKey="ticket" stripedRows responsiveLayout="scroll" class="text-sm">
                        <Column field="ticket" header="Ticket" />
                        <Column field="customer" header="Customer" />
                        <Column field="site" header="Site" />
                        <Column field="grade" header="Grade" />
                        <Column field="qty" header="Qty" />
                        <Column field="status" header="Status">
                            <template #body="{ data }">
                                <Tag :value="data.status" :severity="data.severity" />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>

            <div class="space-y-6">
                <Card class="rounded-3xl border border-slate-200 shadow-sm dark:border-white/10 dark:bg-slate-900">
                    <template #title>Operations snapshot</template>
                    <template #content>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div v-for="item in operations" :key="item.label" class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.04]">
                                <div :class="['mb-4 flex h-10 w-10 items-center justify-center rounded-xl ring-1', toneClass(item.tone)]">
                                    <i :class="['pi', item.icon]" aria-hidden="true" />
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ item.label }}</p>
                                <div class="mt-2 flex items-end justify-between gap-3">
                                    <p class="text-2xl font-semibold text-slate-950 dark:text-white">{{ item.value }}</p>
                                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-300">{{ item.change }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card class="rounded-3xl border border-slate-200 shadow-sm dark:border-white/10 dark:bg-slate-900">
                    <template #title>Material stock levels</template>
                    <template #content>
                        <div class="space-y-4">
                            <div v-for="material in materialLevels" :key="material.name">
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ material.name }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ material.value }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                    <div :class="['h-full rounded-full', material.color]" :style="{ width: `${material.value}%` }" />
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </section>
    </div>
</template>
