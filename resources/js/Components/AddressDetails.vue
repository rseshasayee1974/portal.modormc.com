<!--
Author: ragul-onemodo
Created: 2026-11-13 09:53:15 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

const props = withDefaults(defineProps<{
    uppercase?: boolean;
    requireDistrict?: boolean; requireLine1?: boolean; readonly?: boolean; lookupStateBy?: string; areaLabel?: string; states: any[]; stateLabel?: string; stateValue?: string; errorPrefix?: string; errors?: Record<string, any>;
}>(), { areaLabel: 'Address Line 2 (Area/Locality)', stateLabel: 'label', stateValue: 'value', errorPrefix: 'address_' });
const state = defineModel<number | string | null>('state');
const city = defineModel<string | null>('city');
const zipcode = defineModel<string | null>('zipcode');
const line1 = defineModel<string>('line1');
const line2 = defineModel<string>('line2');
const districts = ref<string[]>([]);
const locations = ref<{ zipcode: string; area: string }[]>([]);
const zipcodes = computed(() => [...new Set(locations.value.map(row => row.zipcode))]);
const areas = computed(() => [...new Set(locations.value
    .filter(row => row.zipcode === zipcode.value && row.area)
    .map(row => row.area))]);
const loadingDistricts = ref(false);
function lookupOption(value: string | null | undefined, options: string[]) {
    return props.uppercase && value
        ? options.find(option => option.toUpperCase() === value.toUpperCase()) ?? value
        : value;
}
const selectedDistrict = computed(() => lookupOption(city.value, districts.value));
const selectedArea = computed(() => lookupOption(line2.value, areas.value));
const loadingZipcodes = ref(false);
const error = (key: string) => props.errors?.[props.errorPrefix + key];

const lookupId = computed(() => props.lookupStateBy
    ? props.states.find(option => option[props.stateValue] === state.value)?.[props.lookupStateBy]
    : state.value);

watch(lookupId, async (id, _, onCleanup) => {
    let current = true;
    onCleanup(() => { current = false; });
    districts.value = [];
    loadingDistricts.value = !!id;
    if (!id) return;
    try {
        const { data } = await axios.get(`/master/statecodes/${id}/districts`);
        if (current) districts.value = data;
    } catch {
        // District can be entered manually when the lookup is unavailable.
    } finally {
        if (current) loadingDistricts.value = false;
    }
}, { immediate: true });

watch([lookupId, selectedDistrict, districts], async ([id, district, options], _, onCleanup) => {
    let current = true;
    onCleanup(() => { current = false; });
    locations.value = [];
    loadingZipcodes.value = false;
    if (!id || !district || !options.includes(district)) return;
    loadingZipcodes.value = true;
    try {
        const { data } = await axios.get(`/master/statecodes/${id}/zipcodes`, { params: { district } });
        if (current) locations.value = data.filter((row: { zipcode?: string }) => row.zipcode)
            .map((row: { zipcode: string; area?: string }) => ({ zipcode: String(row.zipcode).trim(), area: row.area?.trim() || '' }));
    } catch {
        // Keep manual pincode entry available.
    } finally {
        if (current) loadingZipcodes.value = false;
    }
}, { immediate: true });

function changeDistrict(value: string | null) {
    if (city.value !== value) {
        zipcode.value = '';
        line2.value = '';
    }
    city.value = value;
}
function changeZipcode(value: string | null) {
    if (zipcode.value !== value) line2.value = '';
    zipcode.value = value;
}
function changeState(value: number | string | null) {
    if (state.value !== value) {
        city.value = '';
        zipcode.value = '';
        line2.value = '';
    }
    state.value = value;
}
</script>

<template>
    <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-12" :class="{ 'uppercase-address': uppercase }">
        <BaseSelect :disabled="readonly" class="lg:col-span-2" label="State" :modelValue="state" @update:modelValue="changeState"
            :options="states" :optionLabel="stateLabel" :optionValue="stateValue" placeholder="Select state" :error="error('state_id')" />
        <BaseSelect v-if="districts.length || loadingDistricts" class="lg:col-span-2" :required="requireDistrict" label="District"
            :modelValue="selectedDistrict" @update:modelValue="changeDistrict" :options="districts" :disabled="readonly || loadingDistricts"
            placeholder="Select district" :error="error('city')" />
        <BaseInput :disabled="readonly" v-else class="lg:col-span-2" :required="requireDistrict" label="District" v-model="city" :error="error('city')" />
        <BaseSelect v-if="zipcodes.length || loadingZipcodes || (districts.length && !city)" class="lg:col-span-2"
            label="Zipcode / Pincode" :modelValue="zipcode" @update:modelValue="changeZipcode" :options="zipcodes" :disabled="readonly || loadingZipcodes || !city"
            placeholder="Select pincode" :error="error('zipcode')" />
        <BaseInput :disabled="readonly" v-else class="lg:col-span-2" label="Zipcode / Pincode" v-model="zipcode" :error="error('zipcode')" />
        <BaseInput :disabled="readonly" class="lg:col-span-2" :required="requireLine1" label="Address Line 1" v-model="line1" placeholder="Street, building or door number" :error="error('line_1')" />
        <BaseSelect :disabled="readonly" v-if="areas.length" class="lg:col-span-2" :label="areaLabel" :modelValue="selectedArea" @update:modelValue="line2 = $event"
            :options="selectedArea && !areas.includes(selectedArea) ? [selectedArea, ...areas] : areas" placeholder="Select area" :error="error('line_2')" />
        <BaseInput :disabled="readonly" v-else class="lg:col-span-2" :label="areaLabel" v-model="line2" placeholder="Area or locality" :error="error('line_2')" />
        
    </div>
</template>

<style scoped>
.uppercase-address :deep(input),
.uppercase-address :deep(.p-select-label) {
    text-transform: uppercase;
}
</style>
