<!--
Author: Cipher
Created: 2026-10-23 17:12:21 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps({ modelValue: String, inputClass: String });
const emit = defineEmits(['update:modelValue', 'input', 'blur']);
const visible = ref(false);

function update(event) {
    emit('update:modelValue', event.target.value);
    emit('input', event);
}
</script>

<template>
    <div class="relative" :class="$attrs.class">
        <input
            v-bind="$attrs"
            :class="['p-password-input', inputClass]"
            :type="visible ? 'text' : 'password'"
            :value="modelValue"
            @input="update"
            @blur="emit('blur', $event)"
        >
        <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-teal-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-500"
            :aria-label="visible ? 'Hide password' : 'Show password'"
            :aria-pressed="visible"
            :aria-controls="$attrs.id"
            @click="visible = !visible"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="visible" d="m3 3 18 18" />
            </svg>
        </button>
    </div>
</template>
