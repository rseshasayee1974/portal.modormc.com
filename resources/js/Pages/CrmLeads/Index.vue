<!--
Author: ragul-onemodo
Created: 2026-10-09 12:33:52 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref, nextTick } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Swal from 'sweetalert2';
import axios from 'axios';
import { UserGroupIcon, QueueListIcon, ViewColumnsIcon, ClockIcon, CheckCircleIcon, ChartBarIcon, BanknotesIcon, TrashIcon, ArrowTopRightOnSquareIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { usePermissions } from '@/Composables/usePermissions';
import LeadForm from './LeadForm.vue';
import LeadDetails from './LeadDetails.vue';
import LeadEditModal from './LeadEditModal.vue';

const props = defineProps({ leads: Array, owners: Array, customers: Array, options: Object, metrics: Object });
const { can } = usePermissions();
const formVersion = ref(0), selectedLead = ref(null), detailsElement = ref(null), mode = ref('List');
const status = ref(null), source = ref(null), owner = ref(null), overdueOnly = ref(false), boardSearch = ref('');
const selected = ref([]), filters = ref({ global: { value: null, matchMode: 'contains' } });
const bulk = useForm({ ids: [], assigned_to: null });
const transitionError = ref(''), movingId = ref(null);
const editingLead = ref(null), editVisible = ref(false), editLoadingId = ref(null), detailsVersion = ref(0);
const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(Number(value || 0));
const dateTime = value => value ? new Date(value).toLocaleString('en-IN', { timeZone: 'Asia/Kolkata', dateStyle: 'medium', timeStyle: 'short' }) : '—';
const overdue = lead => lead.next_follow_up && lead.status !== 'Unqualified' && !['Won', 'Lost'].includes(lead.deal?.stage) && new Date(lead.next_follow_up) < new Date();
const rows = computed(() => props.leads.filter(lead => (!status.value || lead.status === status.value) && (!source.value || lead.source === source.value) && (!owner.value || lead.assigned_to === owner.value) && (!overdueOnly.value || overdue(lead))));
const boardRows = computed(() => rows.value.filter(lead => `${lead.lead_number} ${lead.contact_name} ${lead.company_name || ''} ${lead.phone || ''}`.toLowerCase().includes(boardSearch.value.toLowerCase())));
const boardStages = computed(() => mode.value === 'Deals' ? props.options.deal_stages : props.options.statuses);
const stageRows = stage => boardRows.value.filter(lead => mode.value === 'Deals' ? lead.deal?.stage === stage : lead.status === stage);
const cards = computed(() => [
    { label: 'Total Leads', value: props.metrics.total, icon: UserGroupIcon, color: 'bg-indigo-50 text-indigo-600' },
    { label: 'Qualified', value: props.metrics.qualified, icon: CheckCircleIcon, color: 'bg-cyan-50 text-cyan-600' },
    { label: 'Overdue Follow-ups', value: props.metrics.overdue, icon: ClockIcon, color: 'bg-rose-50 text-rose-600' },
    { label: 'Conversion Rate', value: `${props.metrics.conversion_rate}%`, icon: ChartBarIcon, color: 'bg-emerald-50 text-emerald-600' },
    { label: 'Open Deal Value', value: money(props.metrics.pipeline_value), icon: BanknotesIcon, color: 'bg-amber-50 text-amber-600' },
]);
const severity = value => ({ New: 'info', Contacted: 'warn', Qualified: 'success', Converted: 'success', Unqualified: 'danger' }[value] || 'secondary');
const open = async id => { selectedLead.value = id; await nextTick(); detailsElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
const created = () => { formVersion.value++; };
const editLoadedLead = lead => { editingLead.value = lead; editVisible.value = true; };
const edit = async id => {
    if (!can('CRM_LEAD.UPDATE') || editLoadingId.value) return;
    editLoadingId.value = id;
    try {
        const response = await axios.get(route('crm.leads.show', id));
        editLoadedLead(response.data.lead);
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Unable to open lead', text: error.response?.data?.message || 'Please try again.' });
    } finally { editLoadingId.value = null; }
};
const edited = () => { detailsVersion.value++; };
const assign = () => {
    bulk.ids = selected.value.map(lead => lead.id);
    bulk.post(route('crm.leads.assign'), { preserveScroll: true, onSuccess: () => { selected.value = []; bulk.reset(); } });
};
const remove = async lead => {
    const answer = await Swal.fire({ title: `Delete ${lead.lead_number}?`, text: 'This removes the lead from the active CRM list.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' });
    if (answer.isConfirmed) router.delete(route('crm.leads.destroy', lead.id), { preserveScroll: true, onSuccess: () => { if (selectedLead.value === lead.id) selectedLead.value = null; } });
};
const move = (event, stage) => {
    event.preventDefault();
    if (!can('CRM_LEAD.UPDATE') || movingId.value) return;
    const lead = props.leads.find(item => item.id === Number(event.dataTransfer.getData('text/plain')));
    if (!lead) return;
    if (mode.value !== 'Deals' && (lead.converted_at || stage === 'Converted')) { transitionError.value = 'Open a qualified lead and use Convert Lead to create the customer and deal.'; return; }
    if ((mode.value === 'Deals' && stage === 'Lost') || (mode.value !== 'Deals' && stage === 'Unqualified')) { transitionError.value = 'Open the lead to enter a reason before choosing this stage.'; open(lead.id); return; }
    const data = mode.value === 'Deals' ? { ...lead.deal, stage } : { ...lead, status: stage };
    movingId.value = lead.id; transitionError.value = '';
    router.put(route(mode.value === 'Deals' ? 'crm.leads.deal' : 'crm.leads.update', lead.id), data, { preserveScroll: true, onError: errors => { transitionError.value = Object.values(errors).join(' '); }, onFinish: () => { movingId.value = null; } });
};
</script>

<template>
    <AppLayout title="CRM Leads">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="CRM Leads" />
        <div class="crm-workspace  mx-auto px-4  space-y-4">
            
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div v-for="card in cards" :key="card.label" class="crm-panel p-4 flex items-start gap-3"><div class="rounded-lg p-2" :class="card.color"><component :is="card.icon" class="w-5 h-5" /></div><div><p class="text-xs text-slate-500">{{ card.label }}</p><p class="text-lg font-bold text-slate-900 mt-1">{{ card.value }}</p></div></div>
            </div>
            <section v-if="can('CRM_LEAD.CREATE')" class="crm-panel p-5 space-y-5">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-indigo-600 p-3 text-white">
                        <UserGroupIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-indigo-600 tracking-widest uppercase">Customer Relationships</p>
                        <h1 class="text-2xl font-bold text-slate-900">Leads</h1>
                    </div>
                </div>
                <LeadForm :key="formVersion" :owners="owners" :options="options" @saved="created" @cancel="created" />
            </section>
            <div v-if="selectedLead" ref="detailsElement" class="scroll-mt-6"><LeadDetails :key="`${selectedLead}-${detailsVersion}`" :leadId="selectedLead" :owners="owners" :customers="customers" :options="options" @edit="editLoadedLead" @close="selectedLead = null" /></div>
            <section class="crm-panel overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 justify-between items-center">
                    <div class="flex gap-1 rounded-lg bg-slate-100 p-1"><button v-for="item in ['List', 'Lead Pipeline', 'Deals']" :key="item" class="crm-button !border-0 !shadow-none" :class="mode === item ? '!bg-white !text-indigo-600' : '!bg-transparent !text-slate-500'" @click="mode = item"><QueueListIcon v-if="item === 'List'" class="w-4 h-4" /><ViewColumnsIcon v-else class="w-4 h-4" />{{ item }}</button></div>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600"><input type="checkbox" v-model="overdueOnly" class="rounded border-slate-300 text-indigo-600" />Overdue follow-ups only</label>
                </div>
                <div class="grid sm:grid-cols-3 gap-3 p-4 bg-slate-50/60">
                    <BaseSelect v-model="status" :options="options.statuses" placeholder="All lead statuses" showClear />
                    <BaseSelect v-model="source" :options="options.sources" placeholder="All sources" showClear />
                    <BaseSelect v-model="owner" :options="owners" optionLabel="label" optionValue="value" placeholder="All owners" showClear />
                </div>
                <form v-if="can('CRM_LEAD.ASSIGN') && selected.length && mode === 'List'" @submit.prevent="assign" class="flex flex-wrap items-center gap-3 border-y border-indigo-100 bg-indigo-50 p-4"><span class="text-sm font-semibold">{{ selected.length }} selected</span><BaseSelect v-model="bulk.assigned_to" :options="owners" optionLabel="label" optionValue="value" placeholder="Assign to owner" :error="bulk.errors.assigned_to" /><button class="crm-primary" :disabled="bulk.processing || !bulk.assigned_to">Assign Leads</button><span class="crm-error">{{ bulk.errors.ids }}</span></form>
                <BaseDataTable v-if="mode === 'List'" :value="rows" v-model:selection="selected" v-model:filters="filters" dataKey="id" :globalFilterFields="['lead_number', 'contact_name', 'company_name', 'phone', 'email', 'project_name', 'requirement', 'owner.username']" paginator :rows="30" showSearch stripedRows @click="edit(data.id)">
                    <Column v-if="can('CRM_LEAD.ASSIGN')" selectionMode="multiple" headerStyle="width:3rem" />
                    <Column field="lead_number" header="Lead" sortable><template #body="{ data }"><button class="text-indigo-700 font-bold text-xs hover:underline" @click="open(data.id)">{{ data.lead_number }}</button><p class="text-xs text-slate-400 mt-1">{{ data.enquiry_date }}</p></template></Column>
                    <Column field="contact_name" header="Contact / Company" sortable><template #body="{ data }"><button class="text-left font-semibold text-slate-800" @click="open(data.id)">{{ data.contact_name }}</button><p class="text-xs text-slate-500">{{ data.company_name }}</p></template></Column>
                    <Column field="phone" header="Phone / Email"><template #body="{ data }"><p class="text-xs">{{ data.phone || '—' }}</p><p class="text-xs text-slate-400">{{ data.email }}</p></template></Column>
                    <Column field="source" header="Source" sortable />
                    <Column field="status" header="Status" sortable><template #body="{ data }"><Tag :value="data.status" :severity="severity(data.status)" /><p v-if="data.deal" class="text-xs text-slate-500 mt-1">{{ data.deal.stage }}</p></template></Column>
                    <Column field="priority" header="Priority" sortable><template #body="{ data }"><span class="text-xs font-semibold" :class="data.priority === 'High' ? 'text-rose-600' : 'text-slate-500'">{{ data.priority }}</span></template></Column>
                    <Column field="owner.username" header="Owner" sortable><template #body="{ data }">{{ data.owner?.username || 'Unassigned' }}</template></Column>
                    <Column field="next_follow_up" header="Next Follow-up" sortable><template #body="{ data }"><span class="text-xs" :class="overdue(data) ? 'text-rose-600 font-semibold' : 'text-slate-500'">{{ dateTime(data.next_follow_up) }}</span></template></Column>
                    <Column field="expected_value" header="Value" sortable><template #body="{ data }">{{ money(data.expected_value) }}</template></Column>
                    <Column header="Actions">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <button class="crm-button !p-2" aria-label="Open lead" @click="open(data.id)">
                                    <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                                </button>
                                <button v-if="can('CRM_LEAD.UPDATE')" class="crm-button !p-2 !text-indigo-600" aria-label="Edit lead" :disabled="!!editLoadingId" @click="edit(data.id)">
                                    <PencilSquareIcon class="w-4 h-4" :class="{ 'animate-pulse': editLoadingId === data.id }" />
                                </button>
                                <button v-if="can('CRM_LEAD.DELETE') && !data.converted_at" class="crm-button !p-2 !text-rose-600" aria-label="Delete lead" @click="remove(data)"><TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </template>
                    </Column>
                    <template #empty>No leads match these filters.</template>
                </BaseDataTable>
                <div v-else class="p-4 space-y-4">
                    <label class="sr-only" for="crm-board-search">Search pipeline</label><input id="crm-board-search" v-model="boardSearch" class="crm-textarea max-w-sm" placeholder="Search leads, companies or phone…" />
                    <p v-if="transitionError" role="alert" class="crm-error">{{ transitionError }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-3">
                        <section v-for="stage in boardStages" :key="stage" class="rounded-xl bg-slate-50 border border-slate-200 p-3 min-h-48" @dragover.prevent @drop="move($event, stage)">
                            <div class="flex justify-between items-center gap-2 mb-3"><h3 class="text-xs font-bold text-slate-700">{{ stage }}</h3><span class="rounded-full bg-white text-xs font-bold px-2 py-1 text-slate-500">{{ stageRows(stage).length }}</span></div>
                            <article v-for="lead in stageRows(stage)" :key="lead.id" :draggable="can('CRM_LEAD.UPDATE') && (mode === 'Deals' || !lead.converted_at)" @dragstart="$event.dataTransfer.setData('text/plain', lead.id)" class="bg-white rounded-lg border border-slate-200 shadow-sm p-3 mb-3" :class="{ 'opacity-50': movingId === lead.id }">
                                <button class="text-left w-full" @click="open(lead.id)"><p class="text-[10px] font-semibold text-indigo-500">{{ lead.lead_number }}</p><h4 class="text-sm font-bold text-slate-900 mt-1">{{ mode === 'Deals' ? lead.deal.name : lead.contact_name }}</h4><p class="text-xs text-slate-500 truncate">{{ lead.company_name || lead.project_name }}</p><p class="text-sm font-bold text-slate-700 mt-3">{{ money(mode === 'Deals' ? lead.deal.expected_value : lead.expected_value) }}</p><div class="flex justify-between gap-2 text-xs mt-3"><span class="text-slate-500">{{ lead.owner?.username || 'Unassigned' }}</span><span :class="lead.priority === 'High' ? 'text-rose-600' : 'text-slate-400'">{{ lead.priority }}</span></div><p v-if="lead.next_follow_up" class="text-[10px] mt-2" :class="overdue(lead) ? 'text-rose-600' : 'text-slate-400'">{{ dateTime(lead.next_follow_up) }}</p></button>
                            </article>
                            <p v-if="!stageRows(stage).length" class="text-xs text-slate-400 text-center py-8">No {{ mode === 'Deals' ? 'deals' : 'leads' }}</p>
                        </section>
                    </div>
                </div>
            </section>
            <div class="grid md:grid-cols-2 gap-4">
                <section class="crm-panel p-5"><h2 class="text-sm font-bold text-slate-800 mb-4">Lead Sources</h2><div v-for="(count, name) in metrics.by_source" :key="name" class="flex justify-between py-2 border-b border-slate-100 text-sm"><span class="text-slate-500">{{ name }}</span><span class="font-bold">{{ count }}</span></div><p v-if="!leads.length" class="text-xs text-slate-400">Source performance appears after adding leads.</p></section>
                <section class="crm-panel p-5"><h2 class="text-sm font-bold text-slate-800 mb-4">Salesperson Performance</h2><div v-for="(result, name) in metrics.by_owner" :key="name" class="flex justify-between py-2 border-b border-slate-100 text-sm"><span class="text-slate-500">{{ name }}</span><span class="font-semibold">{{ result.total }} leads · {{ result.converted }} converted</span></div><p v-if="!leads.length" class="text-xs text-slate-400">Assign leads to track salesperson performance.</p></section>
            </div>
        </div>
        <LeadEditModal v-model:visible="editVisible" :lead="editingLead" :owners="owners" :options="options" @saved="edited" />
    </AppLayout>
</template>

<style>
.crm-workspace .crm-panel { background: white; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 2px 6px rgb(15 23 42 / 3%); }
.crm-workspace .crm-button, .crm-workspace .crm-primary { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 13px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid #e2e8f0; background: white; color: #475569; }
.crm-workspace .crm-button:hover { background: #f8fafc; }
.crm-workspace .crm-primary { background: #4f46e5; border-color: #4f46e5; color: white; }
.crm-workspace button:disabled { opacity: .5; cursor: wait; }
.crm-workspace .crm-label { display: block; font-size: 12px; font-weight: 600; color: #475569; }
.crm-workspace .crm-textarea { display: block; width: 100%; padding: 10px 12px; margin-top: 6px; font-size: 13px; font-weight: 400; border: 1px solid #cbd5e1; border-radius: 8px; background: white; color: #0f172a; }
.crm-workspace .crm-textarea:focus { outline: 2px solid #c7d2fe; border-color: #6366f1; }
.crm-workspace .crm-error { display: block; color: #dc2626; font-size: 12px; margin-top: 4px; }
</style>
