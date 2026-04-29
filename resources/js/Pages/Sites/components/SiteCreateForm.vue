<script setup lang="ts">
import SiteFormFields from './SiteFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    form: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
}>();

const emit = defineEmits(['save', 'reset']);
</script>

<template>
    <div class="site-create-form p-8 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-lg shadow-slate-200/50 dark:shadow-none animate-in fade-in slide-in-from-top-4 duration-500">
        <div class="flex items-center justify-between mb-8 border-b border-slate-300 dark:border-slate-700">
            <div class="flex items-center gap-4 pb-3">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-200 dark:shadow-none">
                    <i class="pi pi-map-marker text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight">Register Logistic Node</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Define new operational sites and loading bays</p>
                </div>
            </div>
        </div>

        <SiteFormFields 
            :form="form" 
            :plants="plants"
            :site-types="siteTypes"
            :is-privileged="isPrivileged"
            :errors="form.errors"
        />

        <div class="mt-2 ">
            <BaseFormActions 
                :loading="form.processing"
                mode="add"
                @add="$emit('save')"
                @reset="$emit('reset')"
            />
        </div>
    </div>
</template>
