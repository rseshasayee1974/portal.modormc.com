<script setup lang="ts">
import { PencilSquareIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import MachineTrackerFormFields from './MachineTrackerFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import type { OptionItem, MachineTracker } from '../types';

defineProps<{
    tracker: MachineTracker;
    form: any;
    machineOptions: OptionItem[];
    shiftOptions: OptionItem[];
    operatorOptions: OptionItem[];
    errors?: any;
    processing?: boolean;
}>();

defineEmits<{
    (e: 'submit'): void;
    (e: 'cancel'): void;
}>();
</script>

<template>
    <div class="machine-tracker-edit-panel bg-slate-50/80 dark:bg-slate-900/60 p-4 md:p-6 rounded-2xl border border-indigo-200/70 dark:border-indigo-900/50 shadow-inner m-2 transition-all duration-300">
        <!-- Panel Header -->
        <div class="flex items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-200/80 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-200 dark:shadow-none">
                    <PencilSquareIcon class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs md:text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                            Modify Tracker Log #{{ tracker.id }}
                        </h3>
                        <span v-if="tracker.machine?.registration" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                            {{ tracker.machine.registration }}
                        </span>
                    </div>
                    <p class="text-[9px] md:text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                        Update runtime meters, energy logs, and fuel refills inline
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="$emit('cancel')"
                class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-700 transition-all active:scale-95"
                title="Cancel Edit"
            >
                <XMarkIcon class="w-5 h-5" />
            </button>
        </div>

        <!-- Form Fields -->
        <form @submit.prevent="$emit('submit')" class="flex flex-col gap-6">
            <MachineTrackerFormFields
                :form="form"
                :machineOptions="machineOptions"
                :shiftOptions="shiftOptions"
                :operatorOptions="operatorOptions"
                :errors="errors || form.errors"
            />

            <!-- Form Actions -->
            <div class="pt-4 border-t border-slate-200/80 dark:border-slate-800">
                <BaseFormActions
                    :loading="processing || form.processing"
                    mode="update"
                    updateLabel="Update Tracker Log"
                    cancelLabel="Cancel"
                    @update="$emit('submit')"
                    @reset="$emit('cancel')"
                />
            </div>
        </form>
    </div>
</template>

<style scoped>
.machine-tracker-edit-panel {
    animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
