<!--
Author: Cipher
Created: 2026-11-10 13:10:20 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import { CheckIcon, XMarkIcon, TagIcon } from '@heroicons/vue/24/outline';

defineProps<{
    form: any;
    editingId: number;
}>();

const emit = defineEmits<{
    (event: 'submit'): void;
    (event: 'cancel'): void;
}>();
</script>

<template>
    <section class="m-2 overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm ring-1 ring-indigo-100 dark:border-indigo-900 dark:bg-slate-900 dark:ring-indigo-950">
        <header class="flex items-center justify-between border-b border-indigo-100 bg-indigo-50/60 px-5 py-4 dark:border-indigo-900/60 dark:bg-indigo-950/30">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-800 dark:text-slate-100">Edit Machine Classification</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Update the classification name.</p>
            </div>
            <span class="rounded-full border border-indigo-200 bg-white px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:border-indigo-800 dark:bg-slate-900 dark:text-indigo-300">
                #MT-{{ editingId }}
            </span>
        </header>

        <form class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start" @submit.prevent="emit('submit')">
            <div class="relative min-w-0 flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <TagIcon class="h-4 w-4 text-slate-400" />
                </div>
                <BaseInput v-model="form.name" placeholder="Enter classification name"
                    inputClass="!h-12 !pl-11 !rounded-xl" :error="form.errors.name" />
            </div>
            <div class="flex shrink-0 items-center justify-end gap-2 sm:pt-0.5">
                <button type="button" class="flex h-11 items-center gap-2 rounded-xl bg-slate-100 px-4 text-xs font-bold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700" @click="emit('cancel')">
                    <XMarkIcon class="h-4 w-4" /> Cancel
                </button>
                <button type="submit" :disabled="form.processing"
                    class="flex h-11 items-center gap-2 rounded-xl bg-indigo-600 px-5 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <CheckIcon v-if="!form.processing" class="h-4 w-4 stroke-[3]" />
                    <span v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    {{ form.processing ? 'Saving…' : 'Update' }}
                </button>
            </div>
        </form>
    </section>
</template>
