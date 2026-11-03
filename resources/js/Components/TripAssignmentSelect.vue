<!--
Author: ragul-onemodo
Created: 2026-11-03 16:00:33 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = withDefaults(defineProps<{
    modelValue: number | null | undefined;
    options?: any[];
    kind: 'truck' | 'driver';
    label?: string;
    error?: string | string[];
    disabled?: boolean;
    required?: boolean;
}>(), { options: () => [], disabled: false, required: false });
const emit = defineEmits(['update:modelValue']);
const { can } = usePermissions();
const added = ref<any[]>([]);
const creating = ref(false);
const createError = ref('');
const choices = computed(() => [...props.options, ...added.value.filter(option => !props.options.some(item => Number(item.id) === Number(option.id)))]);
const createOption = async (name: string) => {
    if (creating.value || props.disabled) return;
    creating.value = true;
    createError.value = '';
    try {
        const { data } = await axios.post(route('batches.trip-options.store'), { kind: props.kind, name });
        added.value = [...added.value.filter(option => option.id !== data.option.id), data.option];
        emit('update:modelValue', data.option.id);
        router.reload({ only: ['trucks', 'drivers'] });
    } catch (error: any) {
        createError.value = error.response?.data?.errors?.name?.[0] || error.response?.data?.message || 'Unable to add this trip assignment.';
    } finally {
        creating.value = false;
    }
};
</script>

<template>
    <BaseCreatableSelect :modelValue="modelValue" @update:modelValue="emit('update:modelValue', $event)"
        :options="choices" :optionLabel="kind === 'truck' ? 'registration' : 'label'" optionValue="id"
        :label="label || (kind === 'truck' ? 'Truck' : 'Driver')" :required="required" :disabled="disabled" :showClear="!required"
        :error="createError || error" :creating="creating" :canCreate="can('BATCH.CREATE') || can('BATCHES.CREATE')"
        :placeholder="kind === 'truck' ? 'Select or add truck number' : 'Select or add driver name'"
          @create="createOption" />
</template>
