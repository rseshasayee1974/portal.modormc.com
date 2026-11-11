<!--
Author: ragul-onemodo
Created: 2026-11-11 18:18:42 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref, watch } from 'vue';

const props = defineProps({ type: String, accountId: [Number, String], number: [String, Number], noteId: Number });
const preview = ref(null);
const error = ref('');
watch(() => [props.type, props.accountId, props.number, props.noteId], ([type, account, number, note], _, cleanup) => {
    preview.value = null;
    error.value = '';
    const controller = new AbortController();
    const timer = setTimeout(async () => {
        const params = { note_type: type };
        if (account) params.account_id = account;
        if (number) params.invoice_number = number;
        if (note) params.note_id = note;
        try {
            const response = await fetch(route('crdrnote.reference-preview', params, false), {
                signal: controller.signal, headers: { Accept: 'application/json' }, cache: 'no-store',
            });
            const data = await response.json();
            if (controller.signal.aborted) return;
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Reference preview unavailable.');
            preview.value = data;
        } catch (exception) {
            if (!controller.signal.aborted) error.value = exception.message || 'Reference preview unavailable.';
        }
    }, 180);
    cleanup(() => { clearTimeout(timer); controller.abort(); });
}, { immediate: true });
</script>

<template>
    <div class="md:col-span-4" aria-live="polite">
        <dl class="grid grid-cols-2 gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
            <div><dt class="text-xs text-slate-600">Prefix</dt><dd class="break-all font-medium">{{ preview?.prefix || '—' }}</dd></div>
            <div><dt class="text-xs text-slate-600">Full reference number</dt><dd class="break-all font-semibold">{{ preview?.full_number || (error ? '—' : 'Loading…') }}</dd></div>
        </dl>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
