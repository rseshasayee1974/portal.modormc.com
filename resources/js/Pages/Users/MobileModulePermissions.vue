<!--
Author: Cipher
Created: 2026-11-13 10:33:00 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

const props = defineProps<{ userId: number }>();
type ModulePermission = { module: string; actions: string[]; granted: string[] };
const rows = ref<ModulePermission[]>([]);
const busy = ref(false);
const error = ref('');
const toast = useToast();

async function load() {
    if (!props.userId) return;
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/membership/users/${props.userId}/mobile-permissions`);
        rows.value = (data.data.modules || []).map((row: ModulePermission) => ({ ...row, granted: [...row.granted] }));
    } catch {
        error.value = 'Could not load mobile app permissions. Select an active plant and check your access.';
    } finally {
        busy.value = false;
    }
}

async function save() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.put(`/membership/users/${props.userId}/mobile-permissions`, {
            permissions: rows.value.map(({ module, granted }) => ({ module, actions: granted })),
        });
        toast.add({ severity: 'success', summary: 'Saved', detail: data.message || 'Mobile permissions saved.', life: 3000 });
    } catch (e: any) {
        error.value = e?.response?.data?.message || 'Could not save mobile app permissions.';
    } finally {
        busy.value = false;
    }
}

watch(() => props.userId, load, { immediate: true });
</script>

<template>
    <BaseCard title="Module Access"
        subtitle="These permissions apply only in the selected plant and are checked by mobile API requests."
        class="mt-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-xs text-gray-500">Choose the actions this user can perform in each module.</p>
            <BaseButton label="Save Permissions" icon="pi pi-check" variant="filled" :loading="busy" :disabled="busy"
                @click="save" />
        </div>
        <div v-if="error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            {{ error }}
        </div>
        <div v-if="busy && !rows.length"
            class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
            <div v-for="n in 5" :key="n" class="h-16 animate-pulse bg-gray-100 dark:bg-gray-700" />
        </div>
        <div v-else-if="!rows.length && !error"
            class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600">
            No mobile permission modules are configured yet. Add permission keys in the Mobile Permission Catalog.
        </div>
        <div v-else-if="rows.length" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="min-w-[640px]">
                <div
                    class="grid grid-cols-[minmax(180px,1fr)_minmax(0,3fr)_72px] items-center gap-4 bg-gray-50 px-4 py-3 text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                    <span>Module</span>
                    <span>Allowed actions</span>
                    <span class="text-right">Access</span>
                </div>
                <section v-for="(row, index) in rows" :key="row.module"
                    class="grid grid-cols-[minmax(180px,1fr)_minmax(0,3fr)_72px] items-center gap-4 px-4 py-3"
                    :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/70 dark:bg-gray-800/60'">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                        {{ row.module.replaceAll('_', ' ') }}
                    </h3>
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        <label v-for="action in row.actions" :key="action"
                            class="flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <Checkbox v-model="row.granted" :inputId="`${row.module}-${action}`" :value="action"
                                :disabled="busy" />
                            <span>{{ action }}</span>
                        </label>
                        <span v-if="!row.actions.length" class="text-xs text-gray-400">No actions configured</span>
                    </div>
                    <span class="text-right text-[10px] font-semibold tabular-nums text-gray-500 dark:text-gray-400">
                        {{ row.granted.length }}/{{ row.actions.length }}
                    </span>
                </section>
            </div>
        </div>
    </BaseCard>
</template>
