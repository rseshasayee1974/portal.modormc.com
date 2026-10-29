<!--
Author: ragul-onemodo
Created: 2026-10-17 14:27:57 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

const props = defineProps({ workspace: Object, permissions: Object, actorId: Number });
const data = ref(props.workspace);
const period = ref(props.workspace.period);
const busy = ref(false);
const message = ref('');
const failed = ref(false);
const search = ref('');
const file = ref(null);
const importPreview = ref(null);
const inboundFile = ref(null);
const tab = ref('books');
const classification = ref(null);
const review = ref(null);
const reason = ref('');
const checks = ref({});
const ruleVersion = ref('');
const ruleReason = ref('');
const adjustmentRef = ref('');
const tallyCompany = ref('');
const selectedSnapshot = ref(null);
const heads = ['taxable', 'igst', 'cgst', 'sgst', 'cess'];
const rowLabels = {
    '3.1.a': 'Outward taxable supplies', '3.1.b': 'Zero-rated outward supplies', '3.1.c': 'Nil / exempt outward supplies',
    '3.1.d': 'Inward reverse-charge liability', '3.1.e': 'Non-GST outward supplies',
    '3.1.1.i': 'ECO operator liability', '3.1.1.ii': 'Supplier through ECO disclosure',
    '4A1': 'ITC: import of goods', '4A2': 'ITC: import of services', '4A3': 'ITC: domestic RCM',
    '4A4': 'ITC: ISD', '4A5': 'ITC: other gross credit / reclaim', '4B1': 'Permanent ITC reversal',
    '4B2': 'Temporary ITC reversal', '4D1': 'Reclaimed ITC disclosure', '4D2': 'Time / POS restricted ITC disclosure',
    '5.exempt.inter': 'Exempt / nil / composition inward: interstate', '5.exempt.intra': 'Exempt / nil / composition inward: intrastate',
    '5.non_gst.inter': 'Non-GST inward: interstate', '5.non_gst.intra': 'Non-GST inward: intrastate',
    '5.1.interest': 'Interest', '5.1.late_fee': 'Late fees', '4C': 'Net ITC (4A less 4B)',
};
const url = (name, extra = {}) => route('gst-compliance.' + name, { period: period.value, ...extra, plant_id: data.value.plant_id });
const money = value => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format((value || 0) / 100);
const latestFacts = computed(() => {
    const map = {};
    for (const fact of data.value.classifications) map[fact.source_key] = fact;
    return map;
});
const inboundLatest = computed(() => {
    const map = {};
    for (const record of data.value.inbound || []) map[record.remote_guid] = record;
    return Object.values(map);
});
const ledgerNames = computed(() => Object.fromEntries((data.value.ledgers || []).map(l => [l.id, l.title])));
const latestReview = (type, id) => [...data.value.reviews].reverse().find(r => r.target_type === type && r.target_id === id);
const filteredBooks = computed(() => data.value.books.filter(b => `${b.number} ${b.partner_name} ${b.source_key}`.toLowerCase().includes(search.value.toLowerCase())).slice(0, 200));
const latestSnapshot = computed(() => selectedSnapshot.value || data.value.snapshots.at(-1));
const exceptions = computed(() => data.value.matches.filter(m => m.status !== 'exact').length);
const refresh = async () => {
    data.value = (await axios.get(url('data'))).data;
    selectedSnapshot.value = null;
};
async function run(fn, success) {
    if (busy.value) return;
    busy.value = true; message.value = ''; failed.value = false;
    try { await fn(); await refresh(); message.value = success; }
    catch (error) {
        failed.value = true;
        const payload = error.response?.data;
        message.value = Object.values(payload?.errors || {}).flat().join(' ') || payload?.message || error.message || 'Request failed. Please retry.';
    } finally { busy.value = false; }
}
function changePeriod() {
    classification.value = null; review.value = null;
    run(async () => {}, 'Period loaded.');
}
function allocation(amounts = {}) {
    return { row: '', values: Object.fromEntries(heads.map(h => [h, ((amounts[h] || 0) / 100).toFixed(2)])),
        pos: '', recipient: 'registered', advance: false, liability_period: period.value };
}
function editBook(book) {
    const fact = latestFacts.value[book.source_key];
    classification.value = { source_key: book.source_key, source_hash: book.hash, reason: fact?.data.reason || '',
        supplier_document: book.direction === 'purchase' ? (fact?.data.supplier_document || { number: '', date: book.date, supplier_gstin: book.supplier_gstin, kind: 'INV', reverse_charge: false }) : null,
        allocations: fact ? fact.data.allocations.map(a => ({ ...allocation(a.amounts), ...a, values: allocation(a.amounts).values })) : [allocation(book.amounts)] };
}
function addAdjustment() {
    if (!adjustmentRef.value.trim()) return;
    classification.value = { source_key: 'adjustment:' + adjustmentRef.value.trim(), reason: '', allocations: [allocation()] };
}
function paise(value) {
    const match = /^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/.exec(String(value).trim());
    if (!match) throw new Error('Enter amounts with at most two decimal places.');
    return (match[1] ? -1 : 1) * (Number(match[2]) * 100 + Number((match[3] || '').padEnd(2, '0')));
}
async function saveClassification() {
    await run(async () => {
        const form = classification.value;
        const allocations = form.allocations.map(a => ({ row: a.row,
            amounts: Object.fromEntries(heads.map(h => [h, paise(a.values[h])])),
            ...(a.pos ? { pos: a.pos, recipient: a.recipient } : {}), advance: a.advance, liability_period: a.liability_period }));
        await axios.post(url('classifications'), { source_key: form.source_key, source_hash: form.source_hash, reason: form.reason, allocations,
            ...(form.supplier_document?.number?.trim() ? { supplier_document: form.supplier_document } : {}) });
        classification.value = null;
    }, 'Classification saved for independent review.');
}
function openReview(type, record, decision = 'include') {
    review.value = { type, record, decision }; reason.value = ''; checks.value = {};
}
async function saveReview() {
    await run(async () => {
        await axios.post(url('review', { type: review.value.type, id: review.value.record.id }),
            { decision: review.value.decision, reason: reason.value, checklist: checks.value });
        review.value = null;
    }, 'Review recorded.');
}
async function upload() {
    if (!file.value) return;
    await run(async () => {
        const form = new FormData(); form.append('file', file.value);
        await axios.post(url('imports'), form);
    }, 'Statement revision imported. Matching does not grant ITC entitlement.');
}
async function previewImport() {
    if (!file.value) return;
    await run(async () => {
        const form = new FormData(); form.append('file', file.value);
        importPreview.value = (await axios.post(url('preview'), form)).data;
    }, 'Preview complete. Review every error and compare totals with the original statement.');
}
async function createRule() {
    await run(async () => {
        await axios.post(url('rules'), { version: ruleVersion.value, reason: ruleReason.value });
        ruleVersion.value = ''; ruleReason.value = '';
    }, 'Rule version created. A different user must review its sources and effective dates.');
}
async function stageInbound() {
    if (!inboundFile.value) return;
    await run(async () => {
        const payload = JSON.parse(await inboundFile.value.text());
        await axios.post(url('inbound'), { ...payload, period: period.value });
    }, 'Tally voucher staged. A different user with Tally posting permission must review its mapping and amounts.');
}
</script>

<template>
    <Head title="GST Reconciliation" />
    <AppLayout title="GST Reconciliation">
        <ModuleSubTopNav />
        <main class="mx-auto max-w-screen-2xl space-y-5 p-4 md:p-7 text-slate-800">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div><h1 class="text-2xl font-bold">GST Reconciliation</h1><p class="text-sm text-slate-500">Plant {{ data.plant_id }} · GSTR-2B matching and independently reviewed GSTR-3B worksheets · {{ data.gstin || 'GSTIN not configured' }}</p></div>
                <div class="flex items-center gap-2"><label for="gst-period" class="text-sm">Tax month</label><input id="gst-period" v-model="period" type="month" :disabled="busy" @change="changePeriod" class="rounded border-slate-300" /><button class="gst-button" :disabled="busy" @click="run(async () => {}, 'Refreshed.')">Refresh</button></div>
            </header>
            <p class="rounded border border-amber-300 bg-amber-50 p-3 text-sm">This is a plant working paper. Consolidate all branches for the GSTIN and compare portal balances before filing. Matching alone never makes ITC eligible.</p>
            <p v-if="message" role="status" :class="failed ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800'" class="rounded p-3">{{ message }}</p>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="gst-card"><p class="text-sm text-slate-500">Book documents</p><strong class="text-2xl">{{ data.books.length }}</strong></div>
                <div class="gst-card"><p class="text-sm text-slate-500">2B revisions</p><strong class="text-2xl">{{ data.imports.length }}</strong></div>
                <div class="gst-card"><p class="text-sm text-slate-500">Matching exceptions</p><strong class="text-2xl">{{ exceptions }}</strong></div>
                <div class="gst-card"><p class="text-sm text-slate-500">Audit history</p><strong>{{ data.audit.valid === null ? 'Checkpoint available' : data.audit.valid ? 'Verified' : 'Investigation required' }}</strong><p class="text-xs">{{ data.audit.sequence }} events</p><button class="text-sm text-blue-700 underline" :disabled="busy" @click="run(async () => { const result = (await axios.get(url('audit'))).data; if (!result.valid) throw new Error('Audit history failed verification. Investigate before approval.'); }, 'Audit history verified. Archive its checkpoint independently.')">Verify history</button></div>
            </div>
            <nav class="flex flex-wrap gap-2" aria-label="GST workspace sections"><button v-for="[id, label] in [['books','Books & classification'],['matching','GSTR-2B matching'],['rules','Rules & reviews'],['worksheet','GSTR-3B worksheet']]" :key="id" class="gst-button" :class="tab === id ? 'bg-blue-100' : ''" @click="tab = id">{{ label }}</button></nav>

            <section v-if="tab === 'books'" class="gst-card space-y-3">
                <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">Approved invoices, bills and notes</h2><input v-model="search" aria-label="Search book documents" placeholder="Search number or party" class="rounded border-slate-300" /></div>
                <p class="text-sm text-slate-500">Classify every current source, then request a separate user's review. Showing up to 200 search results.</p>
                <div class="overflow-auto"><table class="gst-table"><thead><tr><th>Document</th><th>Party</th><th>Type</th><th>Taxable</th><th>GST</th><th>Review</th><th>Action</th></tr></thead><tbody>
                    <tr v-for="book in filteredBooks" :key="book.source_key"><td>{{ book.number }}<small class="block">{{ book.date }}</small><small v-for="issue in book.issues" :key="issue" class="block text-amber-700">{{ issue }}</small></td><td>{{ book.partner_name }}<small class="block">{{ book.supplier_gstin }}</small></td><td>{{ book.direction }} · {{ book.kind }}</td><td>{{ money(book.amounts.taxable) }}</td><td>{{ money(book.amounts.igst + book.amounts.cgst + book.amounts.sgst + book.amounts.cess) }}</td><td>{{ latestFacts[book.source_key] ? (latestFacts[book.source_key].source_hash !== book.hash ? 'Source changed' : latestReview('classification', latestFacts[book.source_key].id)?.decision || 'Pending review') : 'Unclassified' }}</td><td><button v-if="permissions.PREPARE" class="gst-button" :disabled="busy" @click="editBook(book)">Classify</button></td></tr>
                    <tr v-if="!filteredBooks.length"><td colspan="7">No approved book documents for this month.</td></tr>
                </tbody></table></div>
                <div v-if="permissions.PREPARE" class="flex gap-2"><input v-model="adjustmentRef" aria-label="Adjustment reference" placeholder="Evidence reference for manual adjustment" class="min-w-0 flex-1 rounded border-slate-300" /><button class="gst-button" @click="addAdjustment">Add adjustment</button></div>
            </section>

            <section v-if="tab === 'matching'" class="gst-card space-y-3">
                <h2 class="font-semibold">Latest GSTR-2B revision against book purchases</h2>
                <p class="text-sm">Upload normalized statement JSON in the documented Modormc format. Retain the original download and map all sections explicitly. Native portal downloads require an adapter.</p>
                <div v-if="permissions.IMPORT" class="flex flex-wrap gap-3 items-center"><input type="file" accept=".json,application/json" aria-label="Normalized GSTR-2B statement" :disabled="busy" @change="file = $event.target.files[0]; importPreview = null" /><button class="gst-button" :disabled="busy || !file" @click="previewImport">Preview & validate</button><button class="gst-button" :disabled="busy || !file || !importPreview?.valid" @click="upload">Import reviewed statement</button><a :href="url('template')" class="text-blue-700 underline">Download empty template</a></div>
                <div v-if="importPreview" class="space-y-2 rounded border p-3"><p>{{ importPreview.valid ? 'Valid structure' : 'Resolve errors before import' }} · {{ importPreview.document_count || 0 }} valid documents · {{ importPreview.duplicate ? 'Already imported; retry will reuse its record' : 'New statement bytes' }}</p><p v-for="(errors,field) in importPreview.errors" :key="field" class="text-red-800">{{ field }}: {{ errors.join(' ') }}</p><p v-for="warning in importPreview.warnings" :key="warning" class="text-amber-800">{{ warning }}</p><p v-for="d in importPreview.duplicate_rows" :key="d.row" class="text-amber-800">Row {{ d.row }} duplicates row {{ d.duplicate_of }}. Both rows are retained for exception review.</p><p v-for="(v,h) in importPreview.totals" :key="h">{{ h }}: ₹ {{ money(v) }}</p><details><summary>Document preview (up to 200 rows)</summary><p v-for="r in importPreview.rows" :key="r.row">{{ r.row }} · {{ r.supplier_gstin }} · {{ r.number }} · ₹ {{ money(r.amounts.taxable) }}</p></details></div>
                <p v-if="!data.imports.length" class="text-amber-800">No statement imported. The reviewer must verify nil statement or non-applicability.</p>
                <p v-else class="text-sm">Using revision #{{ data.imports.at(-1).id }} · {{ data.imports.at(-1).count }} documents</p>
                <div class="max-h-[32rem] overflow-auto"><table class="gst-table"><thead><tr><th>Document</th><th>Book reference</th><th>Result</th><th>Tax differences (IGST / CGST / SGST / cess)</th></tr></thead><tbody><tr v-for="(match, i) in data.matches" :key="i"><td>{{ match.number }}</td><td>{{ match.source_key || '—' }}</td><td>{{ match.status.replaceAll('_',' ') }}<small v-if="match.candidate_count > 1"> · {{ match.candidate_count }} candidates</small></td><td>{{ ['igst','cgst','sgst','cess'].map(h => money(match.delta[h])).join(' / ') }}</td></tr><tr v-if="!data.matches.length"><td colspan="4">No matching records.</td></tr></tbody></table></div>
            </section>

            <section v-if="tab === 'rules'" class="space-y-4">
                <form v-if="permissions.PREPARE" class="gst-card space-y-3" @submit.prevent="createRule"><h2 class="font-semibold">Create an effective-period rule version</h2><p class="text-sm">The supplied policy covers October 2025–October 2026. A reviewer must verify applicability and sources. Other periods need an updated policy through the API.</p><input v-model="ruleVersion" required maxlength="100" placeholder="Version reference" aria-label="Rule version" class="rounded border-slate-300" /><textarea v-model="ruleReason" required minlength="10" maxlength="4000" placeholder="Applicability and evidence for this rule version" aria-label="Rule evidence" class="block w-full rounded border-slate-300" /><button class="gst-button" :disabled="busy">Create rule for review</button></form>
                <div v-for="rule in [...data.rules].reverse()" :key="rule.id" class="gst-card"><h3 class="font-semibold">{{ rule.version }} · {{ latestReview('rule', rule.id)?.decision || 'Pending review' }}</h3><p class="text-sm">{{ rule.data.policy.from_period }}–{{ rule.data.policy.to_period }} · {{ rule.data.reason }}</p><ul class="my-2 text-sm"><li v-for="source in rule.data.policy.sources" :key="source"><a :href="source" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline">{{ source }}</a></li></ul><button v-if="permissions.REVIEW && rule.created_by !== actorId" class="gst-button" :disabled="busy" @click="openReview('rule',rule)">Review rule</button></div>
                <div class="gst-card space-y-3"><h2 class="font-semibold">Classification decisions</h2><div v-for="fact in Object.values(latestFacts)" :key="fact.id" class="border-b pb-3"><strong>{{ fact.source_key }}</strong><span class="ml-2 text-sm">{{ latestReview('classification',fact.id)?.decision || 'Pending review' }}</span><p class="text-sm">{{ fact.data.reason }}</p><p v-for="(a,i) in fact.data.allocations" :key="i" class="text-sm">{{ a.row }} · {{ rowLabels[a.row] }} · {{ heads.map(h => h + ': ' + money(a.amounts[h])).join(' / ') }}</p><div v-if="permissions.REVIEW && fact.created_by !== actorId" class="mt-2 flex gap-2"><button class="gst-button" :disabled="busy" @click="openReview('classification',fact)">Include after review</button><button class="gst-button" :disabled="busy" @click="openReview('classification',fact,'exclude')">Exclude with reason</button></div></div><p v-if="!data.classifications.length">No classifications recorded.</p></div>
            </section>

            <section v-if="tab === 'worksheet'" class="gst-card space-y-4">
                <div class="space-y-3 rounded border p-3 text-sm"><h3 class="font-semibold">Inbound Tally vouchers</h3><p>Stage a mapped voucher from your Tally export. Staging does not post accounting; an independent authorised user must approve the ledger mapping and amounts. GST treatment is reviewed separately.</p><div v-if="permissions.TALLY_IMPORT" class="flex flex-wrap items-center gap-3"><input type="file" accept=".json,application/json" aria-label="Mapped Tally voucher" :disabled="busy" @change="inboundFile = $event.target.files[0]" /><button class="gst-button" :disabled="busy || !inboundFile" @click="stageInbound">Stage mapped voucher</button></div><div v-for="record in inboundLatest" :key="record.id" class="border-t pt-3"><strong>{{ record.data.narration }}</strong><p>{{ record.remote_guid }} · {{ record.data.date }} · {{ latestReview('inbound',record.id)?.decision === 'include' ? 'Posted journal #' + latestReview('inbound',record.id).data.journal_entry_id : latestReview('inbound',record.id)?.decision || 'Awaiting independent review' }}</p><p>{{ record.data.reason }}</p><p v-for="(line,i) in record.data.lines" :key="i">{{ ledgerNames[line.account_id] || 'Unknown ledger #' + line.account_id }} · Debit {{ money(line.debit) }} · Credit {{ money(line.credit) }}</p><div v-if="permissions.TALLY_POST && record.created_by !== actorId && latestReview('inbound',record.id)?.decision !== 'include'" class="mt-2 flex gap-2"><button class="gst-button" :disabled="busy" @click="openReview('inbound',record)">Review and post</button><button class="gst-button" :disabled="busy" @click="openReview('inbound',record,'exclude')">Exclude</button></div></div><p v-if="!inboundLatest.length">No inbound vouchers staged for this month.</p></div>
                <div v-if="permissions.EXPORT" class="rounded border bg-slate-50 p-3 text-sm"><h3 class="font-semibold">Optional Tally journal exchange</h3><p class="my-2">Export balanced posted journals for a test Tally company. Verify ledger mappings before import. These journal vouchers do not include invoice-specific GST or inventory fields.</p><label>Exact Tally company name <input v-model="tallyCompany" maxlength="200" class="mx-2 rounded border-slate-300" /></label><a v-if="tallyCompany.trim()" :href="url('tally',{company:tallyCompany})" class="gst-button">Download journal XML</a></div>
                <div class="flex flex-wrap items-center gap-3"><h2 class="font-semibold">GSTR-3B preparation</h2><button v-if="permissions.PREPARE" class="gst-button" :disabled="busy" @click="run(() => axios.post(url('snapshots')), 'Worksheet prepared. Resolve blockers before approval.')">Prepare new worksheet</button><select v-if="data.snapshots.length" v-model="selectedSnapshot" aria-label="Worksheet history" class="rounded border-slate-300"><option :value="null">Latest worksheet</option><option v-for="s in [...data.snapshots].reverse()" :key="s.id" :value="s">#{{ s.id }} · {{ s.created_at }}</option></select></div>
                <template v-if="latestSnapshot"><p :class="latestSnapshot.stale ? 'text-red-800' : 'text-slate-600'">Worksheet #{{ latestSnapshot.id }} · {{ latestSnapshot.stale ? 'Source data changed — prepare again' : latestSnapshot.approval ? 'Independently approved at this source version' : 'Awaiting independent approval' }}</p><ul v-if="latestSnapshot.data.blockers.length" class="rounded bg-amber-50 p-3 text-amber-900"><li v-for="b in latestSnapshot.data.blockers" :key="b">{{ b }}</li></ul><div class="overflow-auto"><table class="gst-table"><thead><tr><th>Row</th><th>Description</th><th v-for="h in heads" :key="h">{{ h.toUpperCase() }}</th></tr></thead><tbody><tr v-for="(amounts,row) in latestSnapshot.data.rows" :key="row" :class="row === '4C' ? 'bg-blue-50 font-bold' : ''"><td>{{ row }}</td><td>{{ rowLabels[row] }}</td><td v-for="h in heads" :key="h">{{ money(amounts[h]) }}</td></tr></tbody></table></div><h3 class="font-semibold">Prior-period liability breakup</h3><p v-for="(amounts, month) in latestSnapshot.data.liability_breakup" :key="month" class="text-sm">{{ month }} · {{ heads.map(h => h + ': ' + money(amounts[h])).join(' / ') }}</p><h3 class="font-semibold">Table 3.2 · Interstate recipient classes</h3><p v-for="(r,i) in latestSnapshot.data.table32" :key="i" class="text-sm">POS {{ r.pos }} · {{ r.recipient }} · Taxable {{ money(r.taxable) }} · IGST {{ money(r.igst) }}</p><div class="flex flex-wrap gap-3"><button v-if="permissions.REVIEW && latestSnapshot.created_by !== actorId && !latestSnapshot.stale && !latestSnapshot.approval && !latestSnapshot.data.blockers.length" class="gst-button" :disabled="busy" @click="openReview('snapshot',latestSnapshot)">Review and approve worksheet</button><a v-if="permissions.EXPORT" :href="url('export',{id:latestSnapshot.id})" class="gst-button">Download worksheet and evidence</a></div></template><p v-else>No worksheet prepared yet.</p>
            </section>
        </main>

        <div v-if="classification" class="fixed inset-0 z-50 overflow-auto bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="classification-title"><form class="mx-auto my-8 max-w-5xl space-y-4 rounded-lg bg-white p-5" @submit.prevent="saveClassification"><h2 id="classification-title" class="text-xl font-semibold">Classify {{ classification.source_key }}</h2><p class="text-sm">Select the supported return treatment. Amounts below are rupees. Add separate reversal or reclaim disclosures where required.</p><fieldset v-if="classification.supplier_document" class="grid grid-cols-1 gap-3 rounded border bg-slate-50 p-3 md:grid-cols-3"><legend class="text-sm font-semibold">Supplier document identity for GSTR-2B matching</legend><p class="col-span-full text-sm">Use the supplier invoice or credit-note number, not your internal bill number. Leave it blank for separately evidenced non-2B cases.</p><label class="text-sm">Supplier document number<input v-model="classification.supplier_document.number" maxlength="100" class="block w-full rounded border-slate-300" /></label><label class="text-sm">Supplier document date<input v-model="classification.supplier_document.date" type="date" class="block w-full rounded border-slate-300" /></label><label class="text-sm">Supplier GSTIN<input v-model="classification.supplier_document.supplier_gstin" maxlength="15" class="block w-full rounded border-slate-300" /></label><label class="text-sm">Document kind<select v-model="classification.supplier_document.kind" class="block w-full rounded border-slate-300"><option value="INV">Invoice</option><option value="CN">Supplier credit note</option><option value="DN">Supplier debit note</option></select></label><label class="self-center text-sm"><input v-model="classification.supplier_document.reverse_charge" type="checkbox" /> Reverse charge shown on source</label></fieldset><div v-for="(a,i) in classification.allocations" :key="i" class="space-y-3 rounded border p-3"><label class="block text-sm">GSTR-3B row<select v-model="a.row" required class="mt-1 block w-full rounded border-slate-300"><option value="" disabled>Select treatment</option><option v-for="r in data.rows" :key="r" :value="r">{{ r }} — {{ rowLabels[r] }}</option></select></label><div class="grid grid-cols-2 gap-2 md:grid-cols-5"><label v-for="h in heads" :key="h" class="text-sm">{{ h.toUpperCase() }}<input v-model="a.values[h]" inputmode="decimal" required class="block w-full rounded border-slate-300" /></label></div><div class="flex flex-wrap gap-3 text-sm"><label>Liability month<input v-model="a.liability_period" type="month" required class="ml-1 rounded border-slate-300" /></label><label>POS code<input v-model="a.pos" maxlength="2" pattern="[0-9]{2}" placeholder="e.g. 29" class="ml-1 w-24 rounded border-slate-300" /></label><label>Recipient<select v-model="a.recipient" class="ml-1 rounded border-slate-300"><option value="registered">Registered</option><option value="unregistered">Unregistered</option><option value="composition">Composition</option><option value="uin">UIN</option></select></label><label class="self-center"><input v-model="a.advance" type="checkbox" /> Advance</label><button v-if="classification.allocations.length > 1" type="button" class="text-red-700" @click="classification.allocations.splice(i,1)">Remove row</button></div></div><button type="button" class="gst-button" @click="classification.allocations.push(allocation())">Add treatment row</button><label class="block text-sm">Reason and source evidence<textarea v-model="classification.reason" required minlength="10" maxlength="4000" class="mt-1 block w-full rounded border-slate-300" /></label><div class="flex gap-3"><button class="gst-button" :disabled="busy">Save for review</button><button type="button" class="gst-button" :disabled="busy" @click="classification = null">Cancel</button></div><p v-if="failed" role="alert" class="text-red-800">{{ message }}</p></form></div>
        <div v-if="review" class="fixed inset-0 z-50 overflow-auto bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="review-title"><form class="mx-auto my-8 max-w-2xl space-y-4 rounded-lg bg-white p-5" @submit.prevent="saveReview"><h2 id="review-title" class="text-xl font-semibold">{{ review.decision === 'include' ? 'Review' : 'Exclude' }} {{ review.type }} #{{ review.record.id }}</h2><p class="text-sm">Your decision and evidence will be retained in the audit history.</p><div v-if="review.type === 'snapshot'" class="space-y-2"><label v-for="(label,key) in data.checklist" :key="key" class="flex items-start gap-2 text-sm"><input v-model="checks[key]" type="checkbox" required class="mt-1" />{{ label }}</label></div><label class="block text-sm">Review reason and evidence<textarea v-model="reason" required minlength="10" maxlength="4000" class="mt-1 block w-full rounded border-slate-300" /></label><div class="flex gap-3"><button class="gst-button" :disabled="busy">Record decision</button><button type="button" class="gst-button" :disabled="busy" @click="review = null">Cancel</button></div><p v-if="failed" role="alert" class="text-red-800">{{ message }}</p></form></div>
    </AppLayout>
</template>

<style scoped>
.gst-card { @apply rounded-lg border border-slate-200 bg-white p-4; }
.gst-button { @apply rounded border border-slate-300 px-3 py-2 text-sm font-medium transition hover:bg-slate-100 disabled:cursor-wait disabled:opacity-50; }
.gst-table { @apply w-full text-left text-sm; }
.gst-table th { @apply whitespace-nowrap border-b bg-slate-50 p-3 text-xs uppercase text-slate-500; }
.gst-table td { @apply border-b border-slate-100 p-3 align-top; }
</style>
