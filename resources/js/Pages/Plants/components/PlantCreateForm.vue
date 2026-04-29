<script setup lang="ts">
import PlantFormFields from './PlantFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    form: any;
    entities: any[];
    addressTypes: any[];
    contactTypes: any[];
    states: any[];
    errors?: any;
    processing?: boolean;
}>();

const emit = defineEmits(['submit', 'reset']);
</script>

<template>
    <div class="bg-white dark:bg-slate-800 rounded-lg p-6 shadow-sm border border-slate-200/60 dark:border-slate-700/50">
        <!-- Form Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 shadow-inner">
                <BuildingOffice2Icon class="w-6 h-6" />
            </div>
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100 leading-none uppercase tracking-tight">Register New Plant</h2>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1.5 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                    Operational Unit Setup
                </p>
            </div>
        </div>

        <form @submit.prevent="emit('submit')">
            <PlantFormFields
                :form="form"
                :entities="entities"
                :addressTypes="addressTypes"
                :contactTypes="contactTypes"
                :states="states"
                :errors="errors"
            />

            <BaseFormActions
                submit-label="Create Plant"
                submit-icon="pi pi-plus"
                :loading="processing"
                :show-cancel="false"
                @submit="emit('submit')"
            />
        </form>
    </div>
</template>
