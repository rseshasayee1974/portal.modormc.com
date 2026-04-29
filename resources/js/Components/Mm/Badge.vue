<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: string;
    colorMap?: Record<string, string>;
}>();

const defaultColorMap: Record<string, string> = {
    'Weight':   'bg-indigo-50 text-indigo-700 ring-indigo-200',
    'Volume':   'bg-blue-50 text-blue-700 ring-blue-200',
    'Units':    'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'Distance': 'bg-amber-50 text-amber-700 ring-amber-200',
    'Time':     'bg-purple-50 text-purple-700 ring-purple-200',
    'Other':    'bg-slate-100 text-slate-600 ring-slate-200',
    // Status
    'Active':   'bg-green-50 text-green-700 ring-green-200',
    'Inactive': 'bg-red-50 text-red-600 ring-red-200',
};

const classes = computed(() => {
    const map = { ...defaultColorMap, ...(props.colorMap ?? {}) };
    return map[props.value] ?? 'bg-gray-100 text-gray-600 ring-gray-200';
});
</script>

<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase ring-1', classes]">
        {{ value }}
    </span>
</template>
