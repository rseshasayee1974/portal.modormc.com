<script setup lang="ts">
import SiteFormFields from './SiteFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { PencilSquareIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    siteId: number;
    form: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    errors?: any;
    processing?: boolean;
    patrons: any[];
}>();

const emit = defineEmits(['submit', 'cancel']);
</script>

<template>
    <div class="site-edit-panel bg-slate-100/70 dark:bg-slate-900/60 border-y border-indigo-100 dark:border-slate-800">
        <div class="max-w-7xl mx-auto bg-white dark:bg-slate-800 rounded-2xl border border-indigo-200/80 dark:border-indigo-900/60 shadow-lg overflow-hidden">
            
            <!-- Panel Header -->
            <div class="px-6 py-3.5 bg-gradient-to-r from-indigo-50/90 via-blue-50/40 to-white dark:from-indigo-950/40 dark:to-slate-800 border-b border-indigo-100/80 dark:border-indigo-900/50 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                        <PencilSquareIcon class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                                Modify Logistic Node: {{ form.name || 'Site' }}
                            </h3>
                            <span v-if="form.code" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                                {{ form.code }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                :class="form.status === 'Active' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                                {{ form.status }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">
                            Node ID: #SITE-{{ siteId.toString().padStart(4, '0') }} | Update site configuration, address mapping, and geo-coordinates
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                    title="Close Edit Panel"
                >
                    <XMarkIcon class="w-4 h-4" />
                </button>
            </div>

            <!-- Panel Body -->
            <form @submit.prevent="$emit('submit')" class="p-6 space-y-6">
                <SiteFormFields 
                    :form="form" 
                    :plants="plants"
                    :site-types="siteTypes"
                    :is-privileged="isPrivileged"
                    :errors="errors"
                    :patrons="patrons"
                />

                <!-- Panel Actions -->
                <div class="pt-4 border-t border-slate-200/70 dark:border-slate-700/60 flex items-center justify-end">
                    <BaseFormActions 
                        :loading="processing"
                        mode="update"
                        updateLabel="Save Changes"
                        submitIcon="pi pi-check"
                        cancelLabel="Cancel"
                        cancelIcon="pi pi-times"
                        @update="$emit('submit')"
                        @cancel="$emit('cancel')"
                    />
                </div>
            </form>

        </div>
    </div>
</template>

<style scoped>
.site-edit-panel {
    animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
