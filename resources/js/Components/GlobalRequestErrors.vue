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
let dismissTimer;

function flatten(value) {
    if (typeof value === 'string') return value.trim() ? [value] : [];
    if (Array.isArray(value)) return value.flatMap(flatten);
    if (value && typeof value === 'object') return Object.values(value).flatMap(flatten);
    return [];
}

function show(value) {
    const nextMessages = [...new Set(flatten(value))];
    messages.value = nextMessages;

    if (dismissTimer) {
        window.clearTimeout(dismissTimer);
        dismissTimer = undefined;
    }

    if (nextMessages.length) {
        dismissTimer = window.setTimeout(() => {
            messages.value = [];
            dismissTimer = undefined;
        }, 3000);
    }
}

function showResponse(response) {
    const status = response?.status;
    // Keep server exception details and HTML error pages out of the UI.
    if (status >= 500) {
        show('The server could not complete your request. Please try again or contact your administrator.');
    } else if (status === 401 || status === 419) {
        show('Your session has expired. Please sign in again.');
    } else {
        const data = response?.data;
        const errors = flatten(data?.errors);
        show(errors.length ? errors : (typeof data?.message === 'string' ? data.message
            : typeof data?.error === 'string' ? data.error
            : status === 403 ? 'You do not have permission to perform this action.'
            : status === 404 ? 'The requested record could not be found.'
            : status === 422 ? 'Please check the form values and try again.'
            : status === 429 ? 'Too many requests. Please wait a moment and try again.'
            : 'The request could not be completed. Please check your connection and try again.'));
    }
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
        router.on('invalid', event => {
            if (event.detail.response?.status >= 400) {
                event.preventDefault();
                showResponse(event.detail.response);
            }
        }),
    );
    interceptor = axios.interceptors.response.use(response => response, error => {
        if (!axios.isCancel(error)) showResponse(error.response);
        return Promise.reject(error);
    });
});

onUnmounted(() => {
    removers.forEach(remove => remove());
    if (interceptor !== undefined) axios.interceptors.response.eject(interceptor);
    if (dismissTimer) window.clearTimeout(dismissTimer);
});
</script>

<template>
    <Teleport to="body">
        <section v-if="messages.length" role="alert" aria-live="assertive" aria-atomic="true"
            class="fixed inset-x-4 top-4 z-[100000] mx-auto max-w-xl rounded-xl border border-red-300 bg-white p-4 text-red-800 shadow-xl">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold">Please check these errors</h2>
                <button type="button" aria-label="Dismiss errors" @click="show([])"
                    class="rounded px-2 py-1 text-sm font-medium hover:bg-red-50 focus:ring-2 focus:ring-red-500">Dismiss</button>
            </div>
            <ul class="mt-2 max-h-[45vh] list-disc space-y-1 overflow-y-auto break-words pl-5 text-sm">
                <li v-for="message in messages" :key="message">{{ message }}</li>
            </ul>
        </section>
    </Teleport>
</template>
