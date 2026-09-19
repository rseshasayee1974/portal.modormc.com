<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import OpeningBalanceFormFields from './OpeningBalanceFormFields.vue';
import { ShieldExclamationIcon, CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    batch: { type: Object, required: true },
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'close', 'busy']);

const initialLines = JSON.parse(JSON.stringify(props.batch?.lines ?? []));
if (!initialLines.length && props.batch?.status !== 'POSTED') {
    initialLines.push({
        account_id: '',
        partner_id: '',
        side: 'Dr',
        amount: '',
        reference: ''
    });
}

const form = reactive({
    version: props.batch?.version ?? 0,
    cutover_date: props.batch?.cutover_date?.slice(0, 10) ?? '',
    clearing_account_id: props.batch?.clearing_account_id ?? '',
    notes: props.batch?.notes ?? '',
    lines: initialLines,
});

const posted = ref(props.batch?.status === 'POSTED');
const busy = ref(false);
const errors = ref([]);
const message = ref('');
const saved = ref(JSON.stringify(form));
const dirty = computed(() => JSON.stringify(form) !== saved.value);

const reason = ref('');
const reversing = ref(false);
const confirming = ref(false);

const cents = value => Math.round(Number(value || 0) * 100);
const debit = computed(() => form.lines.reduce((sum, l) => sum + (l.side === 'Dr' ? cents(l.amount) : 0), 0));
const credit = computed(() => form.lines.reduce((sum, l) => sum + (l.side === 'Cr' ? cents(l.amount) : 0), 0));
const difference = computed(() => debit.value - credit.value);

const journalDate = computed(() => {
    if (!form.cutover_date) return '—';
    const date = new Date(`${form.cutover_date}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() - 1);
    return Number.isNaN(date.getTime()) ? '—' : date.toISOString().slice(0, 10);
});

watch(() => props.batch, (newBatch) => {
    if (newBatch) {
        form.version = newBatch.version ?? 0;
        form.cutover_date = newBatch.cutover_date?.slice(0, 10) ?? '';
        form.clearing_account_id = newBatch.clearing_account_id ?? '';
        form.notes = newBatch.notes ?? '';
        form.lines = JSON.parse(JSON.stringify(newBatch.lines ?? []));
        posted.value = newBatch.status === 'POSTED';
        saved.value = JSON.stringify(form);
    }
}, { deep: true });

function showError(error) {
    errors.value = Object.entries(error.response?.data?.errors ?? {}).flatMap(([key, values]) =>
        (Array.isArray(values) ? values : [values]).map(value => {
            const match = key.match(/^lines\.(\d+)\./);
            return match ? `Row ${Number(match[1]) + 1}: ${value}` : value;
        }));
    if (!errors.value.length) {
        errors.value = [error.response?.data?.message ?? 'Unable to complete this action. Please try again.'];
    }
}

async function run(action) {
    busy.value = true;
    emit('busy', true);
    errors.value = [];
    message.value = '';
    try {
        await action();
    } catch (error) {
        showError(error);
    } finally {
        busy.value = false;
        emit('busy', false);
    }
}

function saveDraft() {
    run(async () => {
        const isLineBlank = l => !l.account_id && (!l.amount || Number(l.amount) === 0) && (!l.partner_id || l.partner_id === '') && !l.reference;
        const activeLines = form.lines.filter(l => !isLineBlank(l));
        const payloadLines = activeLines.length ? activeLines : form.lines;

        if (payloadLines.some(line => line.partner_id === '')) {
            errors.value = ['Select a patron on every Patron balances row.'];
            return;
        }

        const payload = {
            ...form,
            lines: payloadLines
        };

        const { data } = await axios.put(route('opening-balances.save'), payload);
        form.version = data.batch.version;
        form.lines = data.batch.lines;
        saved.value = JSON.stringify(form);
        message.value = 'Draft saved. Review the totals, then post opening balances.';
        emit('saved', data.batch);
    });
}

function post() {
    run(async () => {
        const { data } = await axios.post(route('opening-balances.post'), { version: form.version });
        acceptBatch(data.batch, 'Opening balances posted successfully.');
    });
}

function reverse() {
    run(async () => {
        const { data } = await axios.post(route('opening-balances.reverse'), {
            version: form.version,
            reason: reason.value
        });
        acceptBatch(data.batch, 'Reversal posted. Update the draft and post the corrected balances.');
    });
}

function acceptBatch(batch, notice) {
    Object.assign(form, {
        version: batch.version,
        cutover_date: batch.cutover_date?.slice(0, 10) ?? '',
        clearing_account_id: batch.clearing_account_id ?? '',
        notes: batch.notes ?? '',
        lines: JSON.parse(JSON.stringify(batch.lines ?? [])),
    });
    posted.value = batch.status === 'POSTED';
    saved.value = JSON.stringify(form);
    confirming.value = false;
    reversing.value = false;
    reason.value = '';
    message.value = notice;
    emit('saved', batch);
    router.reload({ only: ['history'] });
}
</script>

<template>
    <div class="opening-balance-edit-panel bg-slate-50/70 dark:bg-slate-900/40 p-4 sm:p-6 rounded-2xl border border-indigo-200/80 dark:border-indigo-900/60 shadow-inner space-y-6">
        <!-- Panel Header Bar -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800 dark:text-slate-100">
                        {{ posted ? 'View Posted Opening Balances' : 'Edit Opening Balances Setup' }}
                    </h3>
                    <span
                        class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                        :class="posted ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300'"
                    >
                        {{ posted ? 'Posted · Locked' : 'Draft Setup' }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ posted ? 'Posted balances are locked. Initiate a reversal to reopen and correct balances.' : 'Modify setup draft, check totals, and post when balanced.' }}
                </p>
            </div>

            <button
                type="button"
                @click="$emit('close')"
                class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-200/60 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-all active:scale-95"
                title="Collapse Panel"
            >
                <XMarkIcon class="w-5 h-5" />
            </button>
        </div>

        <!-- Error Notice -->
        <div v-if="errors.length" role="alert" class="rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-900/60 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-1">
                <ShieldExclamationIcon class="w-5 h-5 text-rose-600" />
                <p class="font-bold text-sm">Please check these details</p>
            </div>
            <ul class="list-disc pl-7 text-xs space-y-1">
                <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
            </ul>
        </div>

        <!-- Success Notice -->
        <div v-if="message" role="status" class="rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-900/60 p-4 shadow-sm flex items-center gap-2 text-sm font-semibold">
            <CheckCircleIcon class="w-5 h-5 text-emerald-600 shrink-0" />
            <span>{{ message }}</span>
        </div>

        <!-- Form Fields Component -->
        <OpeningBalanceFormFields
            :form="form"
            :ledgers="ledgers"
            :patrons="patrons"
            :posted="posted"
            :busy="busy"
            @busy="busy = $event; $emit('busy', $event)"
            @import-applied="message = 'CSV rows applied to the form. Review and save draft.'"
        />

        <!-- ── Posting / Correction Actions ── -->
        <BaseCard>
            <div v-if="!posted" class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <BaseButton
                        variant="outlined"
                        severity="primary"
                        :disabled="busy || !form.lines.length"
                        :loading="busy"
                        label="Save Draft"
                        @click="saveDraft"
                    />

                    <BaseButton
                        variant="filled"
                        severity="success"
                        :disabled="busy || dirty || !form.version || (difference !== 0 && !form.clearing_account_id)"
                        label="Review & Post Opening Balances"
                        @click="confirming = true"
                    />

                    <span v-if="dirty" class="text-xs text-amber-600 font-medium">
                        * Save draft changes before posting.
                    </span>
                </div>

                <!-- Confirmation Box -->
                <div v-if="confirming && !posted" class="rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 text-slate-800 dark:text-slate-200 border border-indigo-200 dark:border-indigo-900/60 p-5 space-y-4 shadow-sm">
                    <p class="text-xs font-semibold">
                        Post {{ form.lines.length }} opening rows dated <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ journalDate }}</strong>?
                        This will generate the opening journal entry and update patron and general ledger reports.
                    </p>
                    <div class="flex items-center gap-2">
                        <BaseButton
                            variant="filled"
                            severity="success"
                            size="small"
                            :disabled="busy || dirty"
                            label="Confirm & Post Balances"
                            @click="post"
                        />
                        <BaseButton
                            variant="outlined"
                            severity="secondary"
                            size="small"
                            :disabled="busy"
                            label="Cancel"
                            @click="confirming = false"
                        />
                    </div>
                </div>
            </div>

            <!-- Posted State & Reversal -->
            <div v-if="posted" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                            Balances Are Posted & Locked
                        </h4>
                        <p class="text-xs text-slate-400 mt-0.5">
                            To correct these balances, initiate a reversal to reopen the draft.
                        </p>
                    </div>

                    <BaseButton
                        variant="outlined"
                        severity="warn"
                        size="small"
                        :disabled="busy"
                        label="Correct Opening Setup"
                        @click="reversing = !reversing"
                    />
                </div>

                <div v-if="reversing" class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        Reversal removes this setup's effect from historical balances using the original opening date. The original journal remains in the audit history.
                    </p>

                    <BaseInput
                        v-model="reason"
                        label="Reason for Correction"
                        placeholder="Provide reason for reversing and updating opening setup..."
                        required
                    />

                    <BaseButton
                        variant="filled"
                        severity="danger"
                        size="small"
                        :disabled="busy || reason.trim().length < 5"
                        label="Reverse and Reopen Draft"
                        @click="reverse"
                    />
                </div>
            </div>
        </BaseCard>

        <!-- ── Audit History Card ── -->
        <BaseCard v-if="history.length" title="Posting Audit History" subtitle="Previous journals and reversals related to this plant's opening setup">
            <div class="divide-y divide-slate-100 dark:border-slate-800">
                <div v-for="entry in history" :key="entry.id" class="py-3 text-xs flex flex-col gap-1">
                    <div class="flex justify-between items-center gap-3">
                        <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ entry.voucher_number }}</strong>
                        <span class="text-slate-400 font-mono">
                            {{ entry.voucher_date?.slice(0, 10) }} · ₹ {{ Number(entry.total_debit).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                        </span>
                    </div>
                    <p class="text-slate-500">{{ entry.narration }}</p>
                </div>
            </div>
        </BaseCard>
    </div>
</template>

<style scoped>
.opening-balance-edit-panel {
    animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
