<script setup lang="ts">
import { ClockIcon } from '@heroicons/vue/24/outline';
import MachineTrackerFormFields from './MachineTrackerFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import type { OptionItem } from '../types';

defineProps<{
    form: any;
    machineOptions: OptionItem[];
    shiftOptions: OptionItem[];
    operatorOptions: OptionItem[];
    errors?: any;
    processing?: boolean;
}>();

defineEmits<{
    (e: 'save'): void;
    (e: 'reset'): void;
}>();
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300">
        <div class="p-6 md:p-8">
            <!-- Header -->
            <div class="flex items-center gap-4 mb-8 pb-5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 border border-indigo-100/50 dark:border-indigo-800/50 shadow-sm">
                    <ClockIcon class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                        Log Daily Machine Sheet
                    </h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                        Log shift details, runtime meters, energy logs, and fuel refills
                    </p>
                </div>
            </div>

            <!-- Form Body -->
            <form @submit.prevent="$emit('save')" class="flex flex-col gap-6">
                <MachineTrackerFormFields
                    :form="form"
                    :machineOptions="machineOptions"
                    :shiftOptions="shiftOptions"
                    :operatorOptions="operatorOptions"
                    :errors="errors || form.errors"
                />

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                    <BaseFormActions
                        :loading="processing || form.processing"
                        mode="add"
                        addLabel="Save Tracker Log"
                        resetLabel="Reset"
                        @add="$emit('save')"
                        @reset="$emit('reset')"
                    />
                </div>
            </form>
        </div>
    </div>
</template>
