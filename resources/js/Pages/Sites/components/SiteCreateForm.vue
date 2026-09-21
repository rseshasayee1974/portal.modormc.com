<script setup lang="ts">
import SiteFormFields from './SiteFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { MapPinIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    form: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    patrons: any[];
}>();

const emit = defineEmits(['save', 'reset']);
</script>

<template>
    <div class="site-create-form bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden transition-all duration-300">
        <!-- Card Header with Gradient Badge -->
        <div class="px-6 py-4 bg-gradient-to-r from-slate-50/90 via-indigo-50/20 to-white dark:from-slate-900/60 dark:to-slate-800/60 border-b border-slate-200/80 dark:border-slate-700 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center shadow-xs">
                    <MapPinIcon class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                        Register Logistic Node
                    </h2>
                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                        Define operational discharge sites, batching facilities, and customer unloading bays
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="$emit('reset')"
                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-lg transition-colors"
                title="Reset all form fields to default"
            >
                <ArrowPathIcon class="w-3.5 h-3.5" />
                <span>Reset</span>
            </button>
        </div>

        <!-- Form Body -->
        <form @submit.prevent="$emit('save')" class="p-6 space-y-6">
            <SiteFormFields 
                :form="form" 
                :plants="plants"
                :site-types="siteTypes"
                :is-privileged="isPrivileged"
                :errors="form.errors"
                :patrons="patrons"
            />

            <!-- Form Actions -->
            <div class="pt-4 border-t border-slate-200/70 dark:border-slate-700/60 flex items-center justify-end">
                <BaseFormActions 
                    :loading="form.processing"
                    mode="add"
                    addLabel="Register Site"
                    submitIcon="pi pi-plus"
                    cancelLabel="Clear Form"
                    cancelIcon="pi pi-refresh"
                    @add="$emit('save')"
                    @cancel="$emit('reset')"
                />
            </div>
        </form>
    </div>
</template>
