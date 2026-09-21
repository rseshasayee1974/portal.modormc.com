<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import axios from 'axios';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{ form: any; errors?: any; readonly?: boolean }>();
const fields = ['country', 'state', 'district', 'city', 'zipcode'];
const labels: Record<string, string> = { 
    country: 'Country', 
    state: 'State / Province', 
    district: 'District', 
    city: 'City / Locality', 
    zipcode: 'Postal / ZIP Code' 
};
const lists = ref<Record<string, string[]>>({});
const loading = ref(false);
const error = ref('');
let generation = 0;

const options = (field: string) => [...new Set([...(lists.value[field] || []), props.form[field]].filter(Boolean))].map(value => ({ label: value, value }));

const change = (field: string, value: string | null) => {
    props.form[field] = value || '';
    for (const child of fields.slice(fields.indexOf(field) + 1)) props.form[child] = '';
};

async function loadOptions() {
    const request = ++generation;
    loading.value = true;
    error.value = '';
    lists.value = {};
    try {
        const { data } = await axios.get(route('sites.address-options'), { params: {
            country: props.form.country || undefined,
            state: props.form.state || undefined,
            district: props.form.district || undefined,
            city: props.form.city || undefined,
        } });
        if (request === generation) lists.value = data;
    } catch {
        if (request === generation) error.value = 'Unable to load address options. Please retry.';
    } finally {
        if (request === generation) loading.value = false;
    }
}

watch(() => [props.form.country, props.form.state, props.form.district, props.form.city], loadOptions, { immediate: true });
onUnmounted(() => { generation++; });
</script>

<template>
    <div class="col-span-full space-y-2">
        <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
            <BaseSelect 
                v-for="(field, index) in fields" 
                :key="field" 
                :modelValue="form[field]"
                @update:modelValue="change(field, $event)" 
                :label="labels[field]" 
                :options="options(field)"
                optionLabel="label" 
                optionValue="value" 
                filter 
                showClear 
                :error="errors?.[field]"
                :disabled="readonly || loading || (index > 0 && !form[fields[index - 1]])"
                :placeholder="loading ? 'Loading…' : `Select ${labels[field]}`"
                :hint="!loading && !error && (index === 0 || form[fields[index - 1]]) && !options(field).length ? 'No options in master' : undefined" 
            />
        </div>

        <div v-if="error" role="alert" class="p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-700 dark:text-rose-300 flex items-center justify-between">
            <span>{{ error }}</span>
            <button 
                type="button" 
                @click="loadOptions" 
                class="inline-flex items-center gap-1 font-bold text-rose-700 hover:text-rose-900 underline"
            >
                <ArrowPathIcon class="w-3.5 h-3.5" />
                <span>Retry</span>
            </button>
        </div>
    </div>
</template>
