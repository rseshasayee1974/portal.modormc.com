<!--
Author: Cipher
Created: 2026-11-10 13:02:46 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';

defineProps<{
    form: any;
    editingId: number;
    machineOptions: Array<{ label: string; value: number }>;
    serviceTypes: Array<{ label: string; value: number }>;
    serviceStatuses: Array<{ label: string; value: number }>;
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
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-800 dark:text-slate-100">Edit Service Log</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Update the service schedule, readings, and notes.</p>
            </div>
            <span class="rounded-full border border-indigo-200 bg-white px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:border-indigo-800 dark:bg-slate-900 dark:text-indigo-300">
                Entry #{{ editingId }}
            </span>
        </header>

        <form class="flex flex-col gap-5 p-5" @submit.prevent="emit('submit')">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-12">
                <div class="field-group col-span-12 md:col-span-4">
                    <label class="field-label">Machine / Vehicle *</label>
                    <BaseSelect v-model="form.truck_id" :options="machineOptions" optionLabel="label"
                        optionValue="value" placeholder="Select Asset" :error="form.errors.truck_id" />
                </div>
                <div class="field-group col-span-12 md:col-span-4">
                    <label class="field-label">Service Type *</label>
                    <BaseSelect v-model="form.service_type" :options="serviceTypes" optionLabel="label"
                        optionValue="value" :error="form.errors.service_type" />
                </div>
                <div class="field-group col-span-12 md:col-span-4">
                    <label class="field-label">Service Date</label>
                    <DatePicker v-model="form.service_date" dateFormat="yy-mm-dd" class="!h-10 !w-full"
                        :invalid="Boolean(form.errors.service_date)" />
                    <p v-if="form.errors.service_date" class="mt-1 text-xs text-red-600">{{ form.errors.service_date }}</p>
                </div>
                <div class="field-group col-span-6 md:col-span-3">
                    <label class="field-label">Last Service Km *</label>
                    <BaseInputNumber v-model="form.last_service_km" placeholder="0" :error="form.errors.last_service_km" />
                </div>
                <div class="field-group col-span-6 md:col-span-3">
                    <label class="field-label">Next Service Km *</label>
                    <BaseInputNumber v-model="form.next_service_km" placeholder="0" :error="form.errors.next_service_km" />
                </div>
                <div class="field-group col-span-6 md:col-span-3">
                    <label class="field-label">Current Running Km *</label>
                    <BaseInputNumber v-model="form.current_running_km" placeholder="0" :error="form.errors.current_running_km" />
                </div>
                <div class="field-group col-span-6 md:col-span-3">
                    <BaseInput v-model="form.service_hr_km" label="Service Hr/Km" placeholder="Hr/Km value"
                        :error="form.errors.service_hr_km" />
                </div>
                <div class="field-group col-span-12 md:col-span-9">
                    <BaseInput v-model="form.notes" label="Service Notes" placeholder="Record maintenance description"
                        :error="form.errors.notes" />
                </div>
                <div class="field-group col-span-12 md:col-span-3">
                    <label class="field-label">Status</label>
                    <BaseSelect v-model="form.status" :options="serviceStatuses" optionLabel="label"
                        optionValue="value" :error="form.errors.status" />
                </div>
            </div>

            <footer class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="button" class="flex h-10 items-center gap-2 rounded-xl bg-slate-100 px-4 text-xs font-bold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700" @click="emit('cancel')">
                    <XMarkIcon class="h-4 w-4" /> Cancel
                </button>
                <button type="submit" :disabled="form.processing"
                    class="flex h-10 items-center gap-2 rounded-xl bg-indigo-600 px-5 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <CheckIcon v-if="!form.processing" class="h-4 w-4 stroke-[3]" />
                    <span v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    {{ form.processing ? 'Saving…' : 'Update Service Log' }}
                </button>
            </footer>
        </form>
    </section>
</template>
