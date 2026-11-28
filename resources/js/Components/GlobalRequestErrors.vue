<!--
Author: ragul-onemodo
Created: 2026-11-10 11:08:08 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ initialPage: Object });
const messages = ref([]);
const removers = [];
let interceptor;
let failedRequest;

function flatten(value, path = []) {
    if (typeof value === 'string') {
        const field = path.flatMap(key => key.split('.')).map(key => /^\d+$/.test(key)
            ? String(Number(key) + 1)
            : key.replace(/_/g, ' ').replace(/([a-z])([A-Z])/g, '$1 $2')
                .replace(/^./, letter => letter.toUpperCase())).join(' ');
        return value.trim() ? [field ? field + ': ' + value : value] : [];
    }
    if (Array.isArray(value)) return value.flatMap(item => flatten(item, path));
    if (value && typeof value === 'object') return Object.entries(value)
        .flatMap(([key, item]) => flatten(item, [...path, key]));
    return [];
}

function show(value, config) {
    messages.value = [...new Set(flatten(value))];
    failedRequest = config;
}

function showResponse(response, config) {
    const status = response?.status;
    const data = response?.data;
    const errors = flatten(data?.errors);
    if (errors.length) return show(errors, config);
    // Prefer the API's message and keep raw HTML and debug exceptions out of the page.
    const detail = !data?.exception && !data?.trace &&
        [data?.message, data?.error].find(value => typeof value === 'string' && value.trim());
    let message = detail || (status ? status + ' ' + (response.statusText || 'Request failed')
        : 'No response received. Check your connection and try again.');
    if (data?.error_reference) message += ' (Reference ' + data.error_reference + ')';
    show(message, config);
}

function showPageErrors(page) {
    show([page?.props?.errors, page?.props?.flash?.error]);
}

onMounted(() => {
    showPageErrors(props.initialPage);
    removers.push(
        router.on('start', () => show([])),
        router.on('error', event => show(event.detail.errors)),
        router.on('success', event => showPageErrors(event.detail.page)),
        router.on('httpException', event => {
            if (event.detail.response?.status >= 400) {
                event.preventDefault();
                showResponse(event.detail.response);
            }
        }),
    );
    interceptor = axios.interceptors.response.use(response => {
        if (failedRequest && failedRequest.method === response.config.method &&
            failedRequest.url === response.config.url) show([]);
        return response;
    }, error => {
        if (!axios.isCancel(error)) showResponse(error.response, error.config);
        return Promise.reject(error);
    });
});

onUnmounted(() => {
    removers.forEach(remove => remove());
    if (interceptor !== undefined) axios.interceptors.response.eject(interceptor);
});
</script>

<template>
    <section v-if="messages.length" role="alert" aria-label="Request errors"
        class="border-l-4 border-red-600 bg-red-50 px-4 py-2 text-sm text-red-900">
        <ul class="mx-auto max-w-7xl space-y-1 break-words">
            <li v-for="message in messages" :key="message">{{ message }}</li>
        </ul>
    </section>
</template>
