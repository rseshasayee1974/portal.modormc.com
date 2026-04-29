<script setup lang="ts">
import SiteFormFields from './SiteFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    siteId: number;
    form: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    errors?: any;
    processing?: boolean;
}>();

const emit = defineEmits(['submit', 'cancel']);
</script>

<template>
    <div class="site-edit-panel  bg-slate-50/50 dark:bg-slate-900/30 border-y border-slate-100 dark:border-slate-800">
        <div class="max-w-7xl mx-auto">
            <!-- <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 flex items-center justify-center border border-indigo-100 dark:border-indigo-800">
                        <i class="pi pi-pencil text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight leading-none mb-1">Modify Node Parameters</h4>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none">ID: SITE-{{ siteId.toString().padStart(4, '0') }}</p>
                    </div>
                </div>
            </div> -->

            <div class="bg-white dark:bg-slate-800 p-4 rounded-[5px] border border-slate-200/60 dark:border-slate-700 shadow-xl shadow-slate-100/50 dark:shadow-none transition-all duration-300">
                <SiteFormFields 
                    :form="form" 
                    :plants="plants"
                    :site-types="siteTypes"
                    :is-privileged="isPrivileged"
                    :errors="errors"
                />

                <div class="pt-4">
                    <BaseFormActions 
                        :loading="processing"
                        mode="update"
                        @update="$emit('submit')"
                        @reset="$emit('cancel')"
                        cancelLabel="Cancel"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.site-edit-panel {
    animation: slideIn 0.4s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
