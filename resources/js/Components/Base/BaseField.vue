<script setup lang="ts">
import { computed, useAttrs } from 'vue';

type ErrorValue = string | string[] | null | undefined;

const props = withDefaults(
    defineProps<{
        label?: string;
        forId?: string;
        required?: boolean;
        error?: ErrorValue;
        hint?: string;
        disabled?: boolean;
        class?: string;
    }>(),
    { required: false }
);

defineSlots<{
    default(props: { invalid: boolean; inputId?: string }): any;
}>();

const attrs = useAttrs();

const errorText = computed(() => {
    if (!props.error) return '';
    if (Array.isArray(props.error)) return props.error[0] ?? '';
    return props.error;
});

const invalid = computed(() => Boolean(errorText.value));
const inputId = computed(() => props.forId ?? (attrs.id as string | undefined));
</script>

<template>
    <div :class="['flex flex-col gap-1', props.class]">
        <label
            v-if="label"
            :for="inputId"
            class="text-[10px] font-bold uppercase tracking-widest text-gray-700"
            :class="{ 'opacity-60': disabled }"
        >
            {{ label }} <span v-if="required" class="text-red-500">*</span>
        </label>

        <slot :invalid="invalid" :inputId="inputId" />

        <p v-if="hint && !invalid" class="text-[11px] text-gray-700">
            {{ hint }}
        </p>
        <p v-if="invalid" class="text-[11px] text-red-600">
            {{ errorText }}
        </p>
    </div>
</template>

