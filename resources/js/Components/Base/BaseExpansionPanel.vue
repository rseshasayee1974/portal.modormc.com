<script setup lang="ts">
defineProps<{
    title?: string;
    icon?: string;
}>();
</script>

<template>
    <div class="expansion-panel">
        <div class="expansion-label">
            <i :class="[icon || 'pi pi-pen-to-square', 'text-indigo-500 text-xs']"></i>
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                Editing — <strong class="text-indigo-600">{{ title }}</strong>
            </span>
        </div>
        <div class="expansion-content">
            <slot />
        </div>
    </div>
</template>

<style scoped>


.expansion-panel { padding: 20px 24px; background: linear-gradient(135deg, #eef2ff 0%, #f0f9ff 100%); border-left: 4px solid #6366f1; border-top: 1px solid #c7d2fe; border-bottom: 1px solid #c7d2fe; }
.expansion-label { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 600; color: #4f46e5; letter-spacing: 0.02em; }
</style>
