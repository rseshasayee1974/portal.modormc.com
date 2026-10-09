<!--
Author: ragul-onemodo
Created: 2026-10-09 13:18:29 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref } from 'vue';
import Dialog from 'primevue/dialog';
import { PencilSquareIcon, UserCircleIcon, BuildingOfficeIcon, ClipboardDocumentListIcon, DocumentTextIcon, XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline';
import LeadForm from './LeadForm.vue';

const props = defineProps({ visible: Boolean, lead: Object, owners: Array, options: Object });
const emit = defineEmits(['update:visible', 'saved']);
const editor = ref(null);
const processing = computed(() => !!editor.value?.processing);
const visible = computed({ get: () => props.visible, set: value => { if (!processing.value) emit('update:visible', value); } });
const formId = 'crm-lead-modal-form';
const owner = computed(() => props.owners.find(item => item.value === props.lead?.assigned_to)?.label || 'Unassigned');
const sections = [
    { key: 'contact', label: 'Contact Information', icon: UserCircleIcon },
    { key: 'qualification', label: 'Lead Qualification', icon: ClipboardDocumentListIcon },
    { key: 'project', label: 'Project & Requirements', icon: BuildingOfficeIcon },
    { key: 'notes', label: 'Address & Notes', icon: DocumentTextIcon },
];
const activeSection = ref('contact');
const navigate = key => { activeSection.value = key; editor.value?.scrollToSection(key); };
const saved = () => { emit('update:visible', false); emit('saved'); };
</script>

<template>
    <Dialog v-model:visible="visible" modal blockScroll :draggable="false" :closable="!processing" :closeOnEscape="!processing" :dismissableMask="false" class="crm-workspace crm-lead-edit-dialog" aria-label="Edit CRM lead" @show="activeSection = 'contact'">
        <template #header>
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 shrink-0 flex items-center justify-center rounded-xl bg-indigo-600 text-white"><PencilSquareIcon class="w-5 h-5" /></div>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">CRM / Leads / {{ lead?.lead_number }}</p><h2 class="text-lg font-bold text-slate-900 truncate">Edit Lead <span class="hidden sm:inline font-normal text-slate-400 mx-1">/</span><span class="hidden sm:inline text-sm font-medium text-slate-500">{{ lead?.contact_name }}</span></h2></div>
            </div>
        </template>
        <template #closebutton="{ closeCallback }"><button type="button" class="crm-button !p-2" aria-label="Close lead editor" @click="closeCallback"><XMarkIcon class="w-5 h-5" /></button></template>
        <div class="crm-edit-workspace">
            
            <main class="crm-edit-main">
                <div class="crm-edit-form-card">
                    <p v-if="lead?.converted_at" class="rounded-lg border border-emerald-100 bg-emerald-50 text-emerald-800 text-xs p-3 mb-5">This lead has been converted. Its status remains Converted; sales progress is managed through the deal.</p>
                    <LeadForm v-if="visible && lead" ref="editor" :key="lead.id" :lead="lead" :owners="owners" :options="options" :formId="formId" modal @saved="saved" />
                </div>
            </main>
        </div>
        <template #footer>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <p class="text-xs" :class="Object.keys(editor?.errors || {}).length ? 'text-rose-600 font-medium' : 'text-slate-400'" role="status">{{ Object.keys(editor?.errors || {}).length ? 'Please correct the highlighted fields before saving.' : 'Changes are applied when you save.' }}</p>
                <div class="flex gap-3 ml-auto"><button type="button" class="crm-button" :disabled="processing" @click="visible = false">Cancel</button><button type="submit" :form="formId" class="crm-primary !px-6" :disabled="processing || !lead"><CheckIcon class="w-4 h-4" />{{ processing ? 'Saving…' : 'Save Changes' }}</button></div>
            </div>
        </template>
    </Dialog>
</template>

<style>
.crm-lead-edit-dialog.p-dialog { width: min(1100px, calc(100vw - 48px)) !important; height: min(82dvh, 820px) !important; max-height: calc(100dvh - 48px) !important; margin: 0 !important; border: 1px solid #e2e8f0 !important; border-radius: 14px !important; overflow: hidden; background: #fff; }
.crm-lead-edit-dialog .p-dialog-header { flex-shrink: 0; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; background: #fff; }
.crm-lead-edit-dialog .p-dialog-content { flex: 1; min-height: 0; padding: 0 !important; overflow: hidden; }
.crm-lead-edit-dialog .p-dialog-footer { flex-shrink: 0; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #fff; box-shadow: 0 -3px 12px rgb(15 23 42 / 3%); }
.crm-edit-workspace { display: flex; height: 100%; min-height: 0; }
.crm-edit-sidebar { width: 220px; flex-shrink: 0; display: flex; flex-direction: column; border-right: 1px solid #e2e8f0; background: #f8fafc; overflow-y: auto; }
.crm-edit-nav { display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px; border-radius: 8px; color: #64748b; font-size: 12px; font-weight: 600; text-align: left; }
.crm-edit-nav:hover { background: #eef2ff; }
.crm-edit-nav.is-active { color: #4338ca; background: #e0e7ff; }
.crm-edit-main { flex: 1; min-width: 0; overflow-y: auto; background: #f1f5f9; padding: 18px; scroll-behavior: smooth; }
.crm-edit-form-card { max-width: 1120px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
.crm-edit-section-title { display: flex; gap: 12px; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-top: 18px; scroll-margin-top: 24px; }
.crm-edit-section-title:first-child { margin-top: 0; }
.crm-edit-section-title > span { color: #6366f1; background: #eef2ff; border-radius: 8px; font-size: 12px; font-weight: 700; padding: 9px; }
.crm-edit-section-title h3 { color: #0f172a; font-size: 14px; font-weight: 700; }
.crm-edit-section-title p { color: #94a3b8; font-size: 11px; margin-top: 3px; }
@media (max-width: 767px) { .crm-lead-edit-dialog.p-dialog { width: calc(100vw - 24px) !important; height: 90dvh !important; max-height: calc(100dvh - 24px) !important; } .crm-edit-sidebar { display: none; } .crm-edit-main { padding: 12px; } .crm-edit-form-card { padding: 18px; } .crm-lead-edit-dialog .p-dialog-header, .crm-lead-edit-dialog .p-dialog-footer { padding: 12px 16px; } }
</style>
