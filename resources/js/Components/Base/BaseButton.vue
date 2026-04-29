<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';

type Severity =
    | 'primary'
    | 'secondary'
    | 'success'
    | 'info'
    | 'warn'
    | 'danger'
    | 'help'
    | 'contrast';

type Variant = 'outlined' | 'filled' | 'text';

const props = withDefaults(
    defineProps<{
        label?: string;
        icon?: string;
        severity?: Severity;
        variant?: Variant;
        rounded?: boolean;
        size?: 'small' | 'large';
        loading?: boolean;
        disabled?: boolean;
        type?: 'button' | 'submit' | 'reset';
        class?: string;
    }>(),
    {
        severity: 'primary',
        variant: 'outlined',
        rounded: false,
        size: 'small',
        loading: false,
        disabled: false,
        type: 'button',
    }
);

/**
 * 🎨 Severity Style Map
 * You can tweak this to match your design system (Tailwind-based)
 */
const severityClasses: Record<Severity, string> = {
    primary: 'border-primary text-primary hover:bg-primary/10',
    secondary: 'border-gray-400 text-gray-700 hover:bg-gray-100',
    success: 'border-green-500 text-green-600 hover:bg-green-50',
    info: 'border-blue-500 text-blue-600 hover:bg-blue-50',
    warn: 'border-yellow-500 text-yellow-600 hover:bg-yellow-50',
    danger: 'border-red-500 text-red-600 hover:bg-red-50',
    help: 'border-purple-500 text-purple-600 hover:bg-purple-50',
    contrast: 'border-black text-black hover:bg-gray-200',
};

/**
 * 🎨 Filled variant styles
 */
const filledClasses: Record<Severity, string> = {
    primary: 'bg-primary text-white hover:bg-primary/90',
    secondary: 'bg-gray-600 text-white hover:bg-gray-700',
    success: 'bg-green-500 text-white hover:bg-green-600',
    info: 'bg-blue-500 text-white hover:bg-blue-600',
    warn: 'bg-yellow-500 text-black hover:bg-yellow-600',
    danger: 'bg-red-500 text-white hover:bg-red-600',
    help: 'bg-purple-500 text-white hover:bg-purple-600',
    contrast: 'bg-black text-white hover:bg-gray-800',
};

/**
 * 🎨 Text variant styles
 */
const textClasses: Record<Severity, string> = {
    primary: 'text-primary hover:bg-primary/10',
    secondary: 'text-gray-700 hover:bg-gray-100',
    success: 'text-green-600 hover:bg-green-50',
    info: 'text-blue-600 hover:bg-blue-50',
    warn: 'text-yellow-600 hover:bg-yellow-50',
    danger: 'text-red-600 hover:bg-red-50',
    help: 'text-purple-600 hover:bg-purple-50',
    contrast: 'text-black hover:bg-gray-200',
};

/**
 * 🔥 Final computed classes
 */
const buttonClass = computed(() => {
    const base = 'rounded-xl shadow-sm transition-all';

    if (props.variant === 'filled') {
        return [base, filledClasses[props.severity], props.class];
    }

    if (props.variant === 'text') {
        return [base, 'bg-transparent border-none', textClasses[props.severity], props.class];
    }

    // default = outlined
    return [base, 'border bg-transparent', severityClasses[props.severity], props.class];
});
</script>

<template>
    <Button
        :label="label"
        :icon="icon"
        :severity="severity"
        :rounded="rounded"
        :size="size"
        :loading="loading"
        :disabled="disabled"
        :type="type"
        :outlined="variant === 'outlined'"
        :text="variant === 'text'"
        :class="buttonClass"
    >
        <template v-if="$slots.default" #default>
            <slot />
        </template>

        <template v-if="$slots.icon" #icon>
            <slot name="icon" />
        </template>
    </Button>
</template>