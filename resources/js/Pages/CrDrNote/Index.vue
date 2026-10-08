<!--
Author: ragul-onemodo
Created: 2026-10-07 18:31:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head } from '@inertiajs/vue3';
import { DocumentChartBarIcon } from '@heroicons/vue/24/outline';
import NoteForm from './NoteForm.vue';
import NoteList from './NoteList.vue';
import { usePermissions } from '@/Composables/usePermissions';

defineProps({ notes: { type: Array, default: () => [] }, documents: { type: Array, default: () => [] }, taxes: { type: Array, default: () => [] }, units: { type: Array, default: () => [] }, products: { type: Array, default: () => [] }, mixdesign: { type: Array, default: () => [] }, note_number_details: { type: Object, default: () => ({}) } });
const { can } = usePermissions();
const formVersion = ref(0);
const resetEditor = () => { formVersion.value++; };
</script>

<template>
    <AppLayout title="Credit / Debit Notes">
        <template #header><ModuleSubTopNav /></template>
        <Head title="Credit / Debit Notes" />
        <div class="min-h-screen py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
                <section v-if="can('CRDRNOTE.CREATE')" class="create-panel create-panel--open">
                    <div class="create-panel__header !cursor-default">
                        <div class="flex flex-wrap items-center justify-between w-full gap-3">
                            <div class="flex items-center gap-3">
                                <div class="create-panel__icon">
                                    <DocumentChartBarIcon class="w-5 h-5 text-indigo-600" aria-hidden="true" />
                                </div>
                                <h1 class="text-xs font-semibold text-gray-700 uppercase">
                                    Generate Credit / Debit Note
                                </h1>
                            </div>
                        </div>
                    </div>
                    <div class="create-panel__body">
                        <NoteForm :key="`create-${formVersion}`" :documents="documents" :taxes="taxes" :units="units" :products="products" :mixdesign="mixdesign" :note_number_details="note_number_details" @saved="resetEditor" @cancel="resetEditor" />
                    </div>
                </section>
                <section>
                    <NoteList :notes="notes" :documents="documents" :taxes="taxes" :units="units" :products="products" :mixdesign="mixdesign" :note_number_details="note_number_details" />
                </section>
            </div>
        </div>
    </AppLayout>
</template>
