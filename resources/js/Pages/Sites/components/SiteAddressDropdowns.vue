<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import AddressDetails from '@/Components/AddressDetails.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';

const props = defineProps<{ form: any; errors?: any; readonly?: boolean }>();
const states = ref<{ id: number; state_name: string }[]>([]);
const stateOptions = computed(() => {
    const name = props.form.state;
    return name && !states.value.some(option => option.state_name === name)
        ? [...states.value, { id: null, state_name: name }] : states.value;
});
watch(() => props.form.country, async (country, _, onCleanup) => {
    let current = true;
    onCleanup(() => { current = false; });
    states.value = [];
    try {
        const { data } = await axios.get(route('sites.address-options'), { params: { country } });
        if (current) states.value = data.state_options;
    } catch {
        // Existing site address values stay editable if regional lookups are unavailable.
    }
}, { immediate: true });

</script>

<template>
    <div class="space-y-4">
        <AddressDetails :states="stateOptions" stateLabel="state_name" stateValue="state_name" lookupStateBy="id"
            :readonly="readonly" areaLabel="City / Locality" errorPrefix=""
            :errors="{ state_id: errors?.state, city: errors?.district, zipcode: errors?.zipcode, line_1: errors?.site_address_1, line_2: errors?.city }"
            v-model:state="form.state" v-model:city="form.district" v-model:zipcode="form.zipcode"
            v-model:line1="form.site_address_1" v-model:line2="form.city" />
        <BaseInput class="lg:max-w-lg" v-model="form.site_address_2" label="Address Line 2"
            placeholder="Building, phase or landmark" :disabled="readonly" :error="errors?.site_address_2" />
    </div>
</template>
