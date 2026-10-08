<!--
Author: ragul-onemodo
Created: 2026-10-07 18:31:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import NoteForm from './NoteForm.vue';
import NoteList from './NoteList.vue';
import { usePermissions } from '@/Composables/usePermissions';

defineProps({ notes: { type: Array, default: () => [] }, documents: { type: Array, default: () => [] } });
const { can } = usePermissions();
const showCreate = ref(false);
const editNote = ref(null);
</script>

<template>
    <AppLayout title="Credit / Debit Notes">
        <template #header><ModuleSubTopNav /></template>
        <Head title="Credit / Debit Notes" />
        <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
            <div class="flex justify-between items-center"><h1 class="text-xl font-semibold">Credit / Debit Notes</h1><Button v-if="can('CRDRNOTE.CREATE')" label="Create Note" icon="pi pi-plus" @click="showCreate = true" /></div>
            <NoteList :notes="notes" @edit="editNote = $event" />
        </div>
        <Dialog v-model:visible="showCreate" modal header="Create Credit / Debit Note" :style="{ width: '60rem', maxWidth: '95vw' }">
            <NoteForm v-if="showCreate" :documents="documents" @saved="showCreate = false" @cancel="showCreate = false" />
        </Dialog>
        <Dialog :visible="!!editNote" @update:visible="value => { if (!value) editNote = null; }" modal :header="'Edit ' + (editNote?.invoice_label || 'Note')" :style="{ width: '60rem', maxWidth: '95vw' }">
            <NoteForm v-if="editNote" :key="editNote.id" :note="editNote" :documents="documents" @saved="editNote = null" @cancel="editNote = null" />
        </Dialog>
    </AppLayout>
</template>
