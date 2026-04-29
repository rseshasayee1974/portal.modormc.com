<script setup lang="ts">
import BaseButton from './BaseButton.vue';

interface Props {
    icon: string;
    severity?: 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'help' | 'danger' | 'contrast';
    text?: boolean;
    rounded?: boolean;
    tooltip?: string;
    disabled?: boolean;
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    severity: 'secondary',
    text: true,
    rounded: true,
    disabled: false,
    loading: false
});

defineEmits(['click']);
</script>

<template>
    <BaseButton
        :icon="icon"
        :severity="severity"
        :text="text"
        :rounded="rounded"
        :disabled="disabled"
        :loading="loading"
        v-tooltip="tooltip"
        class="!p-2"
        @click="$emit('click', $event)"
    />
</template>

<style scoped>
:deep(.p-button.p-button-icon-only) {
    width: 2.25rem;
    height: 2.25rem;
}
</style>
