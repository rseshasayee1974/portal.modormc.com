<!--
Author: ragul-onemodo
Created: 2026-10-08 11:16:40 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed } from 'vue';
import { DocumentCheckIcon, PaperAirplaneIcon, QrCodeIcon, TruckIcon, DocumentMinusIcon, DocumentPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{ document: any }>();
const indicators = computed(() => {
    const doc = props.document;
    const status = String(doc.status || 'draft').toLowerCase();
    const einvoice = String(doc.einvoice_status || 'pending').toLowerCase();
    const ewayStatus = String(doc.ewaybill_detail?.ewaybill_status || doc.eway_bill_status || '').toLowerCase();
    const ewayCancelled = ['cnl', 'cancelled', 'canceled'].includes(ewayStatus);
    const entries = [
        { key: 'document', icon: DocumentCheckIcon, label: `Document: ${status}`, state: ['cancelled', 'void'].includes(status) ? 'cancelled' : status === 'paid' ? 'success' : status === 'approved' ? 'info' : 'pending' },
        // { key: 'sent', icon: PaperAirplaneIcon, label: doc.is_sent ? 'Sent' : 'Not sent', state: doc.is_sent ? 'success' : 'pending' },
        { key: 'einvoice', icon: QrCodeIcon, label: `E-Invoice: ${einvoice}${doc.einvoice_irn ? ` · IRN ${doc.einvoice_irn}` : ''}`, state: einvoice === 'generated' ? 'success' : einvoice === 'cancelled' ? 'cancelled' : 'pending' },
        { key: 'eway', icon: TruckIcon, label: `E-Way Bill: ${ewayCancelled ? 'Cancelled' : doc.eway_bill_no ? 'Generated' : 'Not generated'}${doc.eway_bill_no ? ` · ${doc.eway_bill_no}` : ''}`, state: ewayCancelled ? 'cancelled' : doc.eway_bill_no ? 'success' : 'pending' },
    ];
    for (const [type, name, icon] of [['credit_note', 'Credit Note', DocumentMinusIcon], ['debit_note', 'Debit Note', DocumentPlusIcon]] as const) {
        const note = (doc.adjustment_notes || []).find((item: any) => String(item.invoice_type).toLowerCase() === type);
        const cancelled = note && ['cancelled', 'void'].includes(String(note.status).toLowerCase());
        entries.push({ key: type, icon, label: `${name}: ${cancelled ? 'Cancelled' : note ? 'Generated' : 'Not generated'}${note ? ` · ${note.full_number}` : ''}`, state: cancelled ? 'cancelled' : note ? 'success' : 'pending' });
    }
    return entries.filter(item => item.key === 'document' || item.state !== 'pending');
});
const colors: Record<string, string> = {
    success: 'bg-emerald-50 text-emerald-600 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-400',
    info: 'bg-blue-50 text-blue-600 ring-blue-200 dark:bg-blue-950 dark:text-blue-400',
    cancelled: 'bg-rose-50 text-rose-600 ring-rose-200 dark:bg-rose-950 dark:text-rose-400',
    pending: 'bg-slate-50 text-slate-400 ring-slate-200 dark:bg-slate-800 dark:text-slate-500',
};
</script>

<template>
    <div class="flex flex-wrap items-center justify-center gap-1.5 min-w-[110px] max-w-[150px] mx-auto">
        <span v-for="item in indicators" :key="item.key" tabindex="0" role="img"
            :aria-label="item.label" :title="item.label" v-tooltip.top="item.label"
            class="inline-flex relative items-center justify-center w-7 h-7 rounded-lg ring-1 ring-inset focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            :class="colors[item.state]" @click.stop>
            <component :is="item.icon" class="w-4 h-4" aria-hidden="true" />
            <span v-if="item.state === 'success'" class="absolute -bottom-0.5 -right-0.5 rounded-full bg-emerald-600 text-white text-[8px] w-3 h-3 flex items-center justify-center" aria-hidden="true">✓</span>
            <span v-else-if="item.state === 'cancelled'" class="absolute -bottom-0.5 -right-0.5 rounded-full bg-rose-600 text-white text-[8px] w-3 h-3 flex items-center justify-center" aria-hidden="true">×</span>
        </span>
    </div>
</template>
