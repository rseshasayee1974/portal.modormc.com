<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        padded?: boolean;
        class?: string;
        headerClass?: string;
        bodyClass?: string;
    }>(),
    { padded: true }
);
</script>

<template>
    <section
        :class="[
            'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm',
            props.class,
        ]"
    >
        <header v-if="title || subtitle || $slots.header" :class="['p-4 border-b border-gray-100 dark:border-gray-700', headerClass]">
            <slot name="header">
                <div class="flex flex-col gap-1">
                    <h2 v-if="title" class="text-sm font-black uppercase tracking-tight text-gray-800 dark:text-gray-100">
                        {{ title }}
                    </h2>
                    <p v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ subtitle }}
                    </p>
                </div>
            </slot>
        </header>

        <div :class="[padded ? 'p-4' : null, bodyClass]">
            <slot />
        </div>
    </section>
</template>

