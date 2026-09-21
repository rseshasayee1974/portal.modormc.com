<script setup>
import { currentOpeningBalanceDate } from '@/Utils/openingBalanceDate';
import { reactive, ref, computed } from 'vue';
import axios from 'axios';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import OpeningBalanceFormFields from './OpeningBalanceFormFields.vue';
import { ShieldExclamationIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
});

const emit = defineEmits(['created', 'busy']);

const form = reactive({
    version: 0,
    cutover_date: currentOpeningBalanceDate(),
    clearing_account_id: '',
    notes: '',
    lines: [
        {
            account_id: '',
            partner_id: '',
            side: 'Dr',
            amount: '',
            reference: ''
        }
    ],
});

const busy = ref(false);
const errors = ref([]);
const message = ref('');

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
        message.value = 'Draft setup created successfully.';
        emit('created', data.batch);
    });
}
</script>

<template>
    <section aria-label="Create opening balances" class="space-y-6">
        <BaseCard
            title="Create Opening Balances"
            subtitle="Enter initial ledger and patron balances or import from CSV, then save your setup draft."
            class="shadow-sm"
        >
            <!-- Error Notice -->
            <div v-if="errors.length" role="alert" class="rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-900/60 p-4 shadow-sm mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <ShieldExclamationIcon class="w-5 h-5 text-rose-600" />
                    <p class="font-bold text-sm">Please check these details</p>
                </div>
                <ul class="list-disc pl-7 text-xs space-y-1">
                    <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
                </ul>
            </div>

            <!-- Success Notice -->
            <div v-if="message" role="status" class="rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-900/60 p-4 shadow-sm flex items-center gap-2 text-sm font-semibold mb-6">
                <CheckCircleIcon class="w-5 h-5 text-emerald-600 shrink-0" />
                <span>{{ message }}</span>
            </div>

            <!-- Form Fields -->
            <OpeningBalanceFormFields
                :form="form"
                :ledgers="ledgers"
                :patrons="patrons"
                :posted="false"
                :busy="busy"
                @busy="busy = $event; $emit('busy', $event)"
                @import-applied="message = 'CSV rows applied to the form. Review and save draft.'"
            />

            <!-- Save Action Button -->
            <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center gap-3">
                <BaseButton
                    variant="filled"
                    severity="primary"
                    :disabled="busy || !form.lines.length"
                    :loading="busy"
                    label="Save Initial Setup Draft"
                    @click="saveDraft"
                />
            </div>
        </BaseCard>
    </section>
</template>
