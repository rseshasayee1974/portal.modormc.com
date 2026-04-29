<script setup lang="ts">
import PlantFormFields from './PlantFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    plantId: number;
    form: any;
    entities: any[];
    addressTypes: any[];
    contactTypes: any[];
    states: any[];
    canEditIdentityOnUpdate?: boolean;
    errors?: any;
    processing?: boolean;
}>();

const emit = defineEmits(['submit', 'cancel']);
</script>

<template>
    <div class="px-8 py-8 bg-amber-50/50 dark:bg-slate-900/20 border-y border-slate-100 dark:border-slate-800 animate-in fade-in duration-500">
        <div class="max-w-6xl mx-auto">
            <!-- Edit Header -->
            <!-- <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400">
                        <PencilSquareIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight">Edit Plant Records</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Ref ID: #{{ plantId }}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 text-[10px] font-black bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-full uppercase tracking-tighter">
                        Live Edit Mode
                    </span>
                </div>
            </div> -->

            <form @submit.prevent="emit('submit')">
                <PlantFormFields
                    :form="form"
                    :entities="entities"
                    :addressTypes="addressTypes"
                    :contactTypes="contactTypes"
                    :states="states"
                    :errors="errors"
                    :plantId="plantId"
                    :canEditIdentityOnUpdate="canEditIdentityOnUpdate"
                />

                <BaseFormActions
                    submit-label="Update Plant"
                    submit-icon="pi pi-save"
                    :loading="processing"
                    @submit="emit('submit')"
                    @cancel="emit('cancel')"
                />
            </form>
        </div>
    </div>
</template>
