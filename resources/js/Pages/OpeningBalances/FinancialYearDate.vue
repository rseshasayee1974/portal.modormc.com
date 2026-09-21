<script setup>
import { computed, watch } from 'vue';
import { currentOpeningBalanceDate } from '@/Utils/openingBalanceDate';

const props = defineProps({ modelValue: String, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
watch(() => [props.modelValue, props.disabled], () => {
    if (!props.disabled && !/^\d{4}-04-01$/.test(props.modelValue || '')) {
        emit('update:modelValue', currentOpeningBalanceDate(props.modelValue || undefined));
    }
}, { immediate: true });
const selectedYear = computed(() => (props.modelValue || currentOpeningBalanceDate()).slice(0, 4));
const years = computed(() => {
    const current = Number(currentOpeningBalanceDate().slice(0, 4));
    return [...new Set([Number(selectedYear.value), ...Array.from({ length: 22 }, (_, i) => current + 1 - i)])].sort((a, b) => b - a);
});
</script>

<template>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">
        Financial Year
        <select :value="selectedYear" :disabled="disabled" @change="emit('update:modelValue', `${$event.target.value}-04-01`)"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm disabled:opacity-60 dark:bg-gray-800 dark:border-gray-600">
            <option v-for="year in years" :key="year" :value="year">{{ year }}–{{ year + 1 }}</option>
        </select>
    </label>
    <p class="mt-2 text-xs text-slate-500">Opening balance date: <strong class="font-mono">{{ modelValue }}</strong>. Financial years start on April 1.</p>
</template>
