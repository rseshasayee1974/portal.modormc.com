<!--
Author: ragul-onemodo
Created: 2026-11-25 11:24:00 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BellIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const page = usePage();
const count = ref(0);
const onNotificationsPage = computed(() => page.component === 'Notifications/Index');
const unreadCount = computed(() => onNotificationsPage.value ? page.props.feed?.unread_count || 0 : count.value);
let timer;
let request;

async function refresh() {
    request?.abort();
    if (onNotificationsPage.value) return;
    request = new AbortController();
    try {
        const { data } = await axios.get(route('notifications.feed'), { signal: request.signal });
        count.value = data.unread_count;
    } catch {
        // Keep the last successful count until the next refresh.
    }
}

watch(() => [page.props.active_entity?.id, page.props.active_plant_id, page.component], () => {
    count.value = 0;
    refresh();
});
onMounted(() => {
    refresh();
    timer = setInterval(refresh, 60000);
});
onUnmounted(() => {
    clearInterval(timer);
    request?.abort();
});
</script>

<template>
    <Link :href="route('notifications.index')" :aria-label="`Notifications, ${unreadCount} unread`"
        title="Notifications" class="relative mr-1 inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-blue-200 hover:bg-white/10 hover:text-amber-300 focus-visible:ring-2">
        <BellIcon class="h-6 w-6 md:h-7 md:w-7" aria-hidden="true" />
        <span v-if="unreadCount" class="absolute right-0 top-0 rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">
            {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
    </Link>
</template>
