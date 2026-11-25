<!--
Author: ragul-onemodo
Created: 2026-11-25 11:24:00 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BellIcon,
    CheckIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
    TruckIcon,
    BanknotesIcon,
    ShoppingBagIcon,
    CubeIcon,
    CalendarDaysIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const isOpen = ref(false);
const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const roleName = ref('');
const activeCategory = ref('all');
const containerRef = ref(null);
let pollTimer = null;

const categoryLabels = {
    all: 'All',
    sales: 'Sales & CRM',
    operations: 'Operations',
    fleet: 'Fleet',
    finance: 'Finance',
    hr: 'HR',
    general: 'System',
};

const availableCategories = computed(() => {
    const cats = new Set(notifications.value.map(n => n.category || 'general'));
    return ['all', ...Array.from(cats)];
});

const filteredNotifications = computed(() => {
    if (activeCategory.value === 'all') return notifications.value;
    return notifications.value.filter(n => (n.category || 'general') === activeCategory.value);
});

const displayRole = computed(() => roleName.value || page.props.user_role || 'User');

const fetchNotifications = async () => {
    if (!page.props.auth?.user) return;
    loading.value = true;
    try {
        const { data } = await axios.get(route('notifications.feed'));
        notifications.value = data.notifications || [];
        unreadCount.value = Number(data.unread_count || 0);
        roleName.value = data.role_name || '';
        if (!availableCategories.value.includes(activeCategory.value)) {
            activeCategory.value = 'all';
        }
    } catch (e) {
        // Keep silent if session expired or network briefly unavailable
    } finally {
        loading.value = false;
    }
};

const toggleOpen = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
};

const markReadAndOpen = async (item) => {
    if (!item.is_read) {
        item.is_read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        try {
            await axios.post(route('notifications.read', { id: item.id }));
        } catch (e) {
            // Non-blocking
        }
    }
    isOpen.value = false;
    if (item.url) {
        router.visit(item.url);
    }
};

const markAllAsRead = async () => {
    if (unreadCount.value === 0) return;
    notifications.value.forEach(item => { item.is_read = true; });
    unreadCount.value = 0;
    try {
        await axios.post(route('notifications.read-all'));
    } catch (e) {
        await fetchNotifications();
    }
};

const categoryIcon = (category, severity) => {
    if (severity === 'danger' || severity === 'warning') return ExclamationTriangleIcon;
    switch (category) {
        case 'sales': return ShoppingBagIcon;
        case 'operations': return CubeIcon;
        case 'fleet': return TruckIcon;
        case 'finance': return BanknotesIcon;
        case 'hr': return CalendarDaysIcon;
        default: return InformationCircleIcon;
    }
};

const badgeColor = (severity, category) => {
    if (severity === 'danger') return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    if (severity === 'warning') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
    switch (category) {
        case 'sales': return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300';
        case 'operations': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'fleet': return 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300';
        case 'finance': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300';
        default: return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
};

const handleClickOutside = (e) => {
    if (isOpen.value && containerRef.value && !containerRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
    }
};

watch(() => page.props.active_plant_id, () => {
    fetchNotifications();
});

onMounted(() => {
    fetchNotifications();
    pollTimer = setInterval(fetchNotifications, 60000);
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    document.removeEventListener('mousedown', handleClickOutside);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div ref="containerRef" class="relative mr-1 sm:mr-2">
        <button
            type="button"
            @click="toggleOpen"
            class="relative p-2 text-blue-200 hover:text-amber-300 transition-colors focus:outline-none rounded-full hover:bg-white/10"
            aria-label="Notifications"
            title="Notifications"
        >
            <BellIcon class="h-6 w-6 md:h-7 md:w-7" aria-hidden="true" />
            <span
                v-if="unreadCount > 0"
                class="absolute top-1 right-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full ring-2 ring-[#1e3a5f]"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="transform opacity-0 scale-95 -translate-y-1"
            enter-to-class="transform opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="transform opacity-100 scale-100 translate-y-0"
            leave-to-class="transform opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 sm:w-96 rounded-xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-700 z-[70] overflow-hidden"
            >
                <!-- Header -->
                <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Notifications</h3>
                            <span
                                v-if="unreadCount > 0"
                                class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"
                            >
                                {{ unreadCount }} new
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                            Role: <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ displayRole }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            @click="markAllAsRead"
                            class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-md transition-colors"
                            title="Mark all as read"
                        >
                            <CheckIcon class="w-3.5 h-3.5" />
                            <span>Mark all</span>
                        </button>
                        <button
                            type="button"
                            @click="fetchNotifications"
                            class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-md hover:bg-slate-200/60 dark:hover:bg-slate-700 transition-colors"
                            title="Refresh notifications"
                        >
                            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                        </button>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div
                    v-if="availableCategories.length > 1"
                    class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 flex items-center gap-1.5 overflow-x-auto no-scrollbar bg-white dark:bg-slate-900"
                >
                    <button
                        v-for="cat in availableCategories"
                        :key="cat"
                        type="button"
                        @click="activeCategory = cat"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap transition-colors"
                        :class="activeCategory === cat
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        {{ categoryLabels[cat] || cat }}
                    </button>
                </div>

                <!-- Notification List -->
                <div class="max-h-[380px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-if="loading && notifications.length === 0" class="py-10 text-center text-xs text-slate-400">
                        Loading notifications...
                    </div>

                    <div v-else-if="filteredNotifications.length === 0" class="py-10 px-4 text-center">
                        <BellIcon class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600 mb-2" />
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">No notifications</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">You are all caught up for your role.</p>
                    </div>

                    <button
                        v-for="item in filteredNotifications"
                        :key="item.id"
                        type="button"
                        @click="markReadAndOpen(item)"
                        class="w-full text-left px-4 py-3 flex items-start gap-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        :class="{ 'bg-indigo-50/40 dark:bg-indigo-950/20': !item.is_read }"
                    >
                        <div
                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                            :class="badgeColor(item.severity, item.category)"
                        >
                            <component :is="categoryIcon(item.category, item.severity)" class="w-4 h-4" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                                    :class="badgeColor(item.severity, item.category)"
                                >
                                    {{ item.type }}
                                </span>
                                <span class="text-[10px] text-slate-400 shrink-0">{{ item.time_label }}</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 mt-1 truncate">
                                {{ item.title }}
                            </p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                                {{ item.message }}
                            </p>
                        </div>

                        <span
                            v-if="!item.is_read"
                            class="w-2 h-2 rounded-full bg-indigo-600 shrink-0 mt-2"
                            title="Unread"
                        />
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
