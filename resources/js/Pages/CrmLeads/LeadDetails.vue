<!--
Author: ragul-onemodo
Created: 2026-10-09 12:31:49 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { XMarkIcon, PencilSquareIcon, PaperClipIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ leadId: Number, owners: Array, customers: Array, options: Object });
const emit = defineEmits(['close', 'edit']);
const { can } = usePermissions();
const lead = ref(null), loading = ref(false), error = ref(''), tab = ref('Overview');
const quotations = ref([]), salesOrders = ref([]);
let loadVersion = 0;
const activity = useForm({ type: 'Call', subject: '', description: '', due_at: null, assigned_to: null });
const conversion = useForm({ customer_id: null });
const dealForm = useForm({ stage: '', expected_value: 0, expected_close_date: null, lost_reason: '', quotation_id: null, sales_order_id: null });
const upload = useForm({ file: null });
const uploadVersion = ref(0);
const load = async () => {
    const version = ++loadVersion;
    loading.value = true; error.value = '';
    try {
        const response = await axios.get(route('crm.leads.show', props.leadId));
        if (version !== loadVersion) return;
        lead.value = response.data.lead;
        quotations.value = response.data.quotations; salesOrders.value = response.data.sales_orders;
        activity.assigned_to = lead.value.assigned_to;
        if (lead.value.deal) Object.keys(dealForm.data()).forEach(key => { dealForm[key] = lead.value.deal[key]; });
    } catch (exception) {
        if (version === loadVersion) error.value = exception.response?.data?.message || 'Unable to load this lead.';
    } finally { if (version === loadVersion) loading.value = false; }
};
watch(() => props.leadId, () => { lead.value = null; tab.value = 'Overview'; activity.reset(); conversion.reset(); load(); }, { immediate: true });
const refreshOptions = { preserveScroll: true, onSuccess: () => load() };
const saveActivity = () => activity.post(route('crm.leads.activity', props.leadId), { ...refreshOptions, onSuccess: () => { activity.reset('subject', 'description', 'due_at'); load(); } });
const complete = id => router.patch(route('crm.leads.complete', { lead: props.leadId, activity: id }), {}, refreshOptions);
const convert = () => conversion.post(route('crm.leads.convert', props.leadId), refreshOptions);
const saveDeal = () => dealForm.put(route('crm.leads.deal', props.leadId), refreshOptions);
const uploadFile = () => upload.post(route('crm.leads.attach', props.leadId), { ...refreshOptions, forceFormData: true, onSuccess: () => { upload.reset(); uploadVersion.value++; load(); } });
const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(value || 0));
const dateTime = value => value ? new Date(value).toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' }) : '—';
const outstandingTasks = computed(() => lead.value?.activities.filter(item => !item.completed_at && item.due_at) || []);
const tabs = computed(() => ['Overview', 'Activities', 'Attachments', ...(lead.value?.deal ? ['Deal'] : [])]);
</script>

<template>
    <section class="crm-panel" aria-label="Lead details">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5">
            <div><p class="text-xs font-semibold text-indigo-600">{{ lead?.lead_number || 'Lead Details' }}</p><h2 class="text-xl font-bold text-slate-900">{{ lead?.contact_name || 'Loading…' }}</h2><p class="text-sm text-slate-500">{{ lead?.company_name }}</p></div>
            <div class="flex gap-2"><button v-if="lead && can('CRM_LEAD.UPDATE')" class="crm-button" @click="emit('edit', lead)"><PencilSquareIcon class="w-4 h-4" />Edit Lead</button><button class="crm-button" aria-label="Close lead details" @click="emit('close')"><XMarkIcon class="w-5 h-5" /></button></div>
        </div>
        <div v-if="error" class="p-5 crm-error">{{ error }} <button class="underline" @click="load">Retry</button></div>
        <p v-if="loading" class="p-5 text-sm text-slate-500">Loading lead details…</p>
        <div v-if="lead" class="p-5 space-y-6">
                <nav class="flex flex-wrap gap-2 border-b border-slate-100 pb-3" aria-label="Lead sections"><button v-for="item in tabs" :key="item" class="crm-button" :class="{ '!bg-indigo-50 !text-indigo-700 !border-indigo-200': tab === item }" @click="tab = item">{{ item }}<span v-if="item === 'Activities'" class="text-xs">({{ outstandingTasks.length }} open)</span></button></nav>
                <div v-if="tab === 'Overview'" class="space-y-6">
                    <dl class="grid sm:grid-cols-2 lg:grid-cols-5 gap-5 text-sm">
                        <div v-for="[label, value] in [['Status', lead.status], ['Lead Owner', lead.owner?.username], ['Phone', lead.phone], ['Email', lead.email], ['Source', lead.source], ['Priority', lead.priority], ['Project / Site', lead.project_name], ['Expected Value', money(lead.expected_value)], ['Requirement', lead.requirement], ['Estimated Quantity', lead.estimated_quantity], ['Delivery Location', lead.delivery_location], ['Expected Purchase Date', lead.expected_purchase_date], ['Address', lead.address], ['Remarks', lead.remarks]]" :key="label"><dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ label }}</dt><dd class="mt-1 text-slate-800 whitespace-pre-line break-words">{{ value || '—' }}</dd></div>
                    </dl>
                    <p v-if="lead.lost_reason" class="rounded-lg bg-amber-50 p-3 text-sm">Unqualified reason: {{ lead.lost_reason }}</p>
                    <div v-if="lead.converted_at" class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-sm text-emerald-800"><CheckCircleIcon class="inline w-5 h-5 mr-2" />Converted to {{ lead.customer?.legal_name || 'customer' }} on {{ dateTime(lead.converted_at) }}. View the Deal tab to track sales progress.</div>
                    <form v-else-if="lead.status === 'Qualified' && can('CRM_LEAD.CONVERT')" @submit.prevent="convert" class="rounded-xl bg-indigo-50 border border-indigo-100 p-4 space-y-3">
                        <h3 class="font-bold text-indigo-900">Convert Lead</h3>
                        <BaseSelect v-model="conversion.customer_id" :options="customers" optionLabel="label" optionValue="value" label="Existing Customer" placeholder="Create a new customer" showClear :error="conversion.errors.customer_id" />
                        <p class="text-xs text-indigo-700">Leave empty to create a customer from these contact details. Conversion also creates a sales opportunity.</p>
                        <button class="crm-primary" :disabled="conversion.processing">{{ conversion.processing ? 'Converting…' : 'Convert to Customer & Deal' }}</button>
                    </form>
                </div>
                <div v-if="tab === 'Activities'" class="grid lg:grid-cols-5 gap-6">
                    <form v-if="can('CRM_LEAD.UPDATE')" @submit.prevent="saveActivity" class="space-y-4 lg:col-span-1">
                        <h3 class="font-bold text-slate-800">Schedule Follow-up / Add Note</h3>
                        <BaseSelect v-model="activity.type" :options="options.activity_types" label="Activity Type" :error="activity.errors.type" required />
                        <BaseInput v-model="activity.subject" label="Subject" :error="activity.errors.subject" required />
                        <BaseDatePicker v-if="activity.type !== 'Note'" v-model="activity.due_at" label="Due Date & Time" showTime :error="activity.errors.due_at" required />
                        <BaseSelect v-model="activity.assigned_to" :options="owners" optionLabel="label" optionValue="value" label="Assigned To" :disabled="!can('CRM_LEAD.ASSIGN')" :error="activity.errors.assigned_to" />
                        <label class="crm-label">Notes<textarea v-model="activity.description" rows="3" class="crm-textarea" /><span class="crm-error">{{ activity.errors.description }}</span></label>
                        <button class="crm-primary" :disabled="activity.processing">Save Activity</button>
                    </form>
                    <div class="lg:col-span-2 space-y-3">
                        <h3 class="font-bold text-slate-800">Activity Timeline</h3>
                        <article v-for="item in lead.activities" :key="item.id" class="border border-slate-200 rounded-xl p-4">
                            <div class="flex justify-between gap-3"><div><p class="text-xs font-semibold text-indigo-600">{{ item.type }} · {{ item.creator?.username || 'System' }}</p><h4 class="font-semibold mt-1">{{ item.subject }}</h4></div><span class="text-xs text-slate-400">{{ dateTime(item.created_at) }}</span></div>
                            <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ item.description }}</p>
                            <div v-if="item.due_at" class="flex flex-wrap items-center justify-between gap-2 mt-3 text-xs"><span :class="!item.completed_at && new Date(item.due_at) < new Date() ? 'text-rose-600 font-semibold' : 'text-slate-500'">Due: {{ dateTime(item.due_at) }} · {{ item.owner?.username || 'Unassigned' }}</span><span v-if="item.completed_at" class="text-emerald-600">Completed</span><button v-else-if="can('CRM_LEAD.UPDATE')" class="crm-button" @click="complete(item.id)">Mark Complete</button></div>
                        </article>
                        <p v-if="!lead.activities.length" class="text-sm text-slate-500">No activities yet.</p>
                    </div>
                </div>
                <div v-if="tab === 'Attachments'" class="space-y-4">
                    <form v-if="can('CRM_LEAD.UPDATE')" @submit.prevent="uploadFile" class="flex flex-wrap items-center gap-3">
                        <label class="crm-label">Attachment (up to 10 MB)<input :key="uploadVersion" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.doc,.docx,.txt,.csv" @change="upload.file = $event.target.files[0]" class="mt-2 text-sm" required /><span class="crm-error">{{ upload.errors.file }}</span></label>
                        <button class="crm-primary" :disabled="upload.processing || !upload.file">Upload</button>
                    </form>
                    <a v-for="file in lead.attachments" :key="file.id" :href="route('crm.leads.download', { lead: lead.id, attachment: file.id })" class="flex items-center gap-3 border border-slate-200 rounded-xl p-4 hover:bg-slate-50"><PaperClipIcon class="w-5 h-5 text-indigo-500" /><span class="text-sm font-medium">{{ file.name }}</span><span class="text-xs text-slate-400 ml-auto">{{ Math.ceil(file.size / 1024) }} KB</span></a>
                    <p v-if="!lead.attachments.length" class="text-sm text-slate-500">No attachments yet.</p>
                </div>
                <form v-if="tab === 'Deal'" @submit.prevent="saveDeal" class="space-y-4">
                    <h3 class="font-bold text-slate-800">{{ lead.deal.name }}</h3>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <BaseSelect v-model="dealForm.stage" :options="options.deal_stages" label="Deal Stage" :disabled="!can('CRM_LEAD.UPDATE')" :error="dealForm.errors.stage" required />
                        <BaseInput v-model="dealForm.expected_value" type="number" min="0" label="Expected Value (₹)" :disabled="!can('CRM_LEAD.UPDATE')" :error="dealForm.errors.expected_value" required />
                        <BaseDatePicker v-model="dealForm.expected_close_date" label="Expected Close Date" :disabled="!can('CRM_LEAD.UPDATE')" :error="dealForm.errors.expected_close_date" />
                        <BaseSelect v-model="dealForm.quotation_id" :options="quotations" optionLabel="label" optionValue="value" label="Linked Quotation" :disabled="!can('CRM_LEAD.UPDATE')" :error="dealForm.errors.quotation_id" showClear />
                        <BaseSelect v-model="dealForm.sales_order_id" :options="salesOrders" optionLabel="label" optionValue="value" label="Linked Sales Order" :disabled="!can('CRM_LEAD.UPDATE')" :error="dealForm.errors.sales_order_id" showClear />
                    </div>
                    <label v-if="dealForm.stage === 'Lost'" class="crm-label">Lost Reason *<textarea v-model="dealForm.lost_reason" rows="2" required :disabled="!can('CRM_LEAD.UPDATE')" class="crm-textarea" /><span class="crm-error">{{ dealForm.errors.lost_reason }}</span></label>
                    <button v-if="can('CRM_LEAD.UPDATE')" class="crm-primary" :disabled="dealForm.processing">Save Deal</button>
                </form>
        </div>
    </section>
</template>
