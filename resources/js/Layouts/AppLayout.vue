<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { 
    HomeIcon, 
    Square3Stack3DIcon, 
    CreditCardIcon, 
    Cog6ToothIcon, 
    SwatchIcon, 
    ClipboardDocumentListIcon, 
    IdentificationIcon, 
    BriefcaseIcon, 
    ChartBarIcon,
    BellIcon,
    UserCircleIcon,
    ArrowUpRightIcon,
    ArrowDownLeftIcon,
    ClockIcon,
    DocumentTextIcon,
    ArrowDownOnSquareIcon,
    ArrowUpOnSquareIcon,
    ChartPieIcon,
    CogIcon,
    UserPlusIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    PaintBrushIcon,BuildingLibraryIcon,TruckIcon,ShoppingCartIcon
} from '@heroicons/vue/24/outline';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';


const IconMap = {
    HomeIcon, 
    Square3Stack3DIcon, 
    CreditCardIcon, 
    Cog6ToothIcon, 
    SwatchIcon, 
    ClipboardDocumentListIcon, 
    IdentificationIcon, 
    BriefcaseIcon, 
    ChartBarIcon, 
    BellIcon, 
    UserCircleIcon, 
    ArrowUpRightIcon, 
    ArrowDownLeftIcon, 
    ClockIcon, 
    DocumentTextIcon, 
    ArrowDownOnSquareIcon, 
    ArrowUpOnSquareIcon, 
    ChartPieIcon, 
    CogIcon, 
    UserPlusIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    PaintBrushIcon,BuildingLibraryIcon,TruckIcon,ShoppingCartIcon
};

const page = usePage();
const { can, isSuperAdmin } = usePermissions();
const activeEntity = computed(() => page.props.active_entity);

// Dynamically source top navigation from database menus
const visibleNav = computed(() => {
    const menus = page.props.menus?.top_nav || [];
    return menus.filter(item => !item.permission_name || can(item.permission_name));
});

const isTopMenuActive = (item) => {
    const currentUrl = page.url.toLowerCase();
    
    if (item.alias && currentUrl.startsWith('/' + item.alias.toLowerCase())) return true;
    if (item.link && item.link !== '#' && currentUrl.startsWith('/' + item.link.toLowerCase())) return true;

    const children = page.props.menus?.sidebar_nav?.[item.id] || [];
    for (const child of children) {
        if (child.alias && currentUrl.startsWith('/' + child.alias.toLowerCase())) return true;
        if (child.link && child.link !== '#' && currentUrl.startsWith('/' + child.link.toLowerCase())) return true;
    }
    
    return false;
};
console.log(page.props.auth.user);
defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('currentteam.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

const switchEntity = async (entityId) => {
    try {
        await axios.post('/context/selectentity', { entity_id: entityId });
        // Hard navigate so Inertia shared props (active_entity) are refreshed with the new role
        window.location.href = '/dashboard';
    } catch (e) {
        console.error('Entity switch failed', e);
    }
};

// --- Session Idle Timeout Logic ---
const IDLE_WARN_TIME = 15 * 60 * 1000; // 5 minutes
const IDLE_LOGOUT_TIME = 20 * 60 * 1000; // 20 minutes
const CHECK_INTERVAL = 1000; // 1 second

const lastActivity = ref(Date.now());
const showTimeoutModal = ref(false);
const remainingTime = ref(0);
let idleInterval = null;

const resetTimer = async () => {
    lastActivity.value = Date.now();
    if (showTimeoutModal.value) {
        showTimeoutModal.value = false;
        // Ping server to refresh session lifetime on backend
        try {
            await axios.get(route('session.ping'));
        } catch (e) {
            console.error('Session refresh failed', e);
        }
    }
};

const checkIdleTime = () => {
    const now = Date.now();
    const idleDuration = now - lastActivity.value;

    if (idleDuration >= IDLE_LOGOUT_TIME) {
        logout();
        clearInterval(idleInterval);
    } else if (idleDuration >= IDLE_WARN_TIME) {
        showTimeoutModal.value = true;
        remainingTime.value = Math.floor((IDLE_LOGOUT_TIME - idleDuration) / 1000);
    } else {
        showTimeoutModal.value = false;
    }
};

onMounted(() => {
    const events = [ 'keypress' ];
    events.forEach(event => window.addEventListener(event, resetTimer));

    idleInterval = setInterval(checkIdleTime, CHECK_INTERVAL);
});

onUnmounted(() => {
    const events = [ 'keypress' ];
    events.forEach(event => window.removeEventListener(event, resetTimer));
    
    if (idleInterval) clearInterval(idleInterval);
});
</script>

<template>
    <div>
        <Head :title="title" />

                        <Banner />
                        <Toast />
                        <ConfirmDialog />

                        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
                            <nav class="bg-customBlue-600 dark:bg-customBlue-800 border-b border-customBlue-800 w-full z-50 shadow-md">
                                <!-- ... nav content ... -->
                                <div class="w-full px-2 sm:px-4 lg:px-8">
                                    <div class="flex items-center justify-between h-16 md:h-20">
                                        <!-- Desktop Logo -->
                                        <div class="hidden md:flex w-64 shrink-0 items-center mr-2 pl-2">
                                            <Link :href="route('dashboard')" class="flex items-center gap-3 group">
                                                <div v-if="activeEntity?.entity_logo" class="h-10 w-10 flex items-center justify-center bg-white rounded-lg p-1 shadow-sm transition-transform group-hover:scale-105">
                                                    <img
                                                        :src="`/storage/${activeEntity.entity_logo}`"
                                                        alt="Logo"
                                                        class="h-full w-full object-contain"
                                                    />
                                                </div>
                                                <ApplicationMark v-else class="transition-transform group-hover:scale-105" />
                                                
                                                <div class="flex flex-col leading-tight">
                                                    <span class="text-lg font-black tracking-tighter text-white uppercase italic group-hover:text-amber-300 transition-colors">
                                                        {{ activeEntity?.entity_name || 'onemodo' }}<span v-if="!activeEntity?.entity_name" class="text-indigo-400">.com</span>
                                                    </span>
                                                    <span class="text-[8px] font-bold tracking-[0.25em] text-blue-300 uppercase opacity-70 group-hover:opacity-100 transition-opacity">
                                                        Enterprise Ops
                                                    </span>
                                                </div>
                                            </Link>
                                        </div>

                                        <!-- Scrollable Center Navigation Items -->
                                        <div class="flex-1 overflow-x-auto no-scrollbar">
                                            <div class="flex items-stretch justify-evenly min-w-max md:min-w-0 h-full">
                                                <Link
                                                    v-for="item in visibleNav"
                                                    :key="item.id"
                                                    :href="item.link === '#' ? '#' : (item.link.startsWith('/') ? item.link : '/' + item.link)"
                                                    :class="[
                                                        isTopMenuActive(item)
                                                            ? 'border-b-4 border-amber-400 text-amber-400 bg-white/10'
                                                            : 'border-b-4 border-transparent text-blue-200 hover:text-amber-300 hover:bg-white/10 hover:border-amber-300/50',
                                                        'flex flex-col items-center justify-center py-2 px-3 md:px-5 min-w-[3.5rem] md:min-w-[5rem] group focus:outline-none transition-all duration-200 cursor-pointer'
                                                    ]"
                                                >
                                                    <component
                                                        :is="IconMap[item.icon] || IconMap['HomeIcon']"
                                                        :class="[
                                                            isTopMenuActive(item)
                                                                ? 'text-amber-400'
                                                                : 'text-blue-300 group-hover:text-amber-300',
                                                            'h-6 w-6 md:h-7 md:w-7 mb-0.5 transition-colors duration-200 shrink-0'
                                                        ]"
                                                        aria-hidden="true"
                                                    />
                                                    <span
                                                        :class="[
                                                            isTopMenuActive(item)
                                                                ? 'text-amber-400 font-bold'
                                                                : 'text-blue-200 group-hover:text-amber-300 font-semibold',
                                                            'text-[9px] md:text-[10px] uppercase tracking-wide whitespace-nowrap transition-colors duration-200'
                                                        ]"
                                                    >{{ item.title }}</span>
                                                </Link>
                                            </div>
                                        </div>

                                        <div class="flex items-center shrink-0 ml-2">
                                            <!-- Entity Switcher Dropdown -->
                                            <!-- <div v-if="$page.props.user_entities && $page.props.user_entities.length > 0" class="ms-2 relative">
                                                <Dropdown align="right" width="64">
                                                    <template #trigger>
                                                        <button type="button" class="inline-flex items-center gap-2 px-2 py-1 rounded-lg text-xs font-medium border border-blue-400/40 bg-white/10 text-blue-100 hover:border-amber-400/60 hover:text-amber-300 transition-all duration-150 focus:outline-none">
                                                            <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0" />
                                                            <span class="max-w-[100px] truncate hidden sm:block">{{ $page.props.active_entity?.entity_name ?? 'Workspace' }}</span>
                                                            <svg class="w-3.5 h-3.5 text-blue-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                            </svg>
                                                        </button>
                                                    </template>
                                                    <template #content>
                                                        <div class="w-64 py-1">
                                                            <div class="px-4 py-2 text-xs text-gray-400 font-semibold uppercase tracking-wide border-b border-gray-100 dark:border-gray-700">
                                                                Switch Workspace
                                                            </div>
                                                            <div class="max-h-[250px] overflow-y-auto">
                                                                <button
                                                                    v-for="eu in $page.props.user_entities"
                                                                    :key="eu.entity_id"
                                                                    @click="switchEntity(eu.entity_id)"
                                                                    class="w-full text-left flex items-center gap-3 px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                                                    :class="eu.is_active ? 'text-pastelbeaver-600 dark:text-pastelbeaver-400 bg-pastelbeaver-50 dark:bg-pastelbeaver-900/20' : 'text-gray-700 dark:text-gray-300'"
                                                                >
                                                                    <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                                        <img v-if="eu.entity_logo" :src="`/storage/${eu.entity_logo}`" class="w-full h-full object-contain p-0.5" />
                                                                        <span v-else class="text-sm font-bold text-gray-500">{{ eu.entity_name.charAt(0) }}</span>
                                                                    </div>
                                                                    <div class="flex-1 min-w-0">
                                                                        <p class="font-medium truncate">{{ eu.entity_name }}</p>
                                                                        <p class="text-xs text-gray-400 truncate">{{ eu.role_name }}</p>
                                                                    </div>
                                                                    <svg v-if="eu.is_active" class="w-4 h-4 text-pastelbeaver-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </Dropdown>
                                            </div> -->

                                            <!-- Notification Bell -->
                                            <button class="relative p-2 text-blue-200 hover:text-amber-300 transition-colors mr-1 sm:mr-2 focus:outline-none rounded-full">
                                                <BellIcon class="h-6 w-6 md:h-7 md:w-7" aria-hidden="true" />
                                                <span class="absolute top-1.5 right-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-500 rounded-full ring-2 ring-[#1e3a5f]">
                                                    0
                                                </span>
                                            </button>
                                            <div class="ms-3 relative">
                                                <!-- Teams Dropdown -->
                                                <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                                                    <template #trigger>
                                                        <span class="inline-flex rounded-md">
                                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                                <img v-if="$page.props.jetstream.managesProfilePhotos" class="size-6 rounded-full object-cover me-2 focus:border-pastelbeaver-400" :src="'/storage/'+$page.props.auth.user.profile_photo_path" :alt="$page.props.auth.user.username">
                                                                <svg v-else class="me-2 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                                </svg>
                                                                {{ $page.props.auth.user.current_team.name }}

                                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                                </svg>
                                                            </button>
                                                        </span>
                                                    </template>

                                                    <template #content>
                                                        <div class="w-60">
                                                            <!-- Team Management -->
                                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                                Manage Team
                                                            </div>

                                                            <!-- Team Settings -->
                                                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                                Team Settings
                                                            </DropdownLink>

                                                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                                Create New Team
                                                            </DropdownLink>

                                                            <!-- Team Switcher -->
                                                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                                <div class="border-t border-gray-200 dark:border-gray-600" />

                                                                <div class="block px-4 py-2 text-xs text-gray-400">
                                                                    Switch Teams
                                                                </div>

                                                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                                    <form @submit.prevent="switchToTeam(team)">
                                                                        <DropdownLink as="button">
                                                                            <div class="flex items-center">
                                                                                <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                </svg>

                                                                                <div>{{ team.name }}</div>
                                                                            </div>
                                                                        </DropdownLink>
                                                                    </form>
                                                                </template>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </Dropdown>
                                            </div>

                                            <!-- Settings Dropdown -->
                                            <div class="ms-3 relative">
                                                <Dropdown align="right" width="72">
                                                    <template #trigger>
                                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex items-center gap-2 p-1 pr-3 text-sm bg-white/10 hover:bg-white/20 border border-white/10 rounded-full transition-all duration-300 group focus:outline-none">
                                                            <div class="relative">
                                                                <img class="size-8 rounded-full object-cover border-2 border-indigo-400/50 group-hover:border-indigo-400" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.username">
                                                                <div class="absolute bottom-0 right-0 size-2.5 bg-emerald-500 border-2 border-[#1e293b] rounded-full"></div>
                                                            </div>
                                                            <div class="flex flex-col items-start leading-tight">
                                                                <span class="text-xs font-bold text-white tracking-tight">{{ $page.props.auth.user.username }}</span>
                                                                <span class="text-[9px] text-indigo-200 font-medium uppercase tracking-widest">Active</span>
                                                            </div>
                                                            <svg class="size-3.5 text-indigo-300 group-hover:text-white transition-colors ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </button>

                                                        <button v-else type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-white/10 text-indigo-100 hover:bg-white/20 hover:text-white border border-white/5 transition-all duration-200 focus:outline-none">
                                                            <div class="size-6 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-300">
                                                                {{ $page.props.auth.user.username.charAt(0).toUpperCase() }}
                                                            </div>
                                                            {{ $page.props.auth.user.username }}
                                                            <svg class="size-4 text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </button>
                                                    </template>

                                                    <template #content>
                                                        <!-- User Branding Header -->
                                                        <div class="px-5 py-5 border-b border-slate-100/60 mb-2">
                                                            <div class="flex items-center gap-3">
                                                                <div class="size-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-black text-lg shadow-sm border border-indigo-100">
                                                                    {{ $page.props.auth.user.username.charAt(0).toUpperCase() }}
                                                                </div>
                                                                <div class="flex flex-col min-w-0">
                                                                    <span class="text-[15px] font-bold text-slate-800 leading-tight truncate">{{ $page.props.auth.user.username }}</span>
                                                                    <span class="text-[11px] text-slate-500 font-medium mt-0.5 truncate">{{ $page.props.auth.user.email }}</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Primary Actions -->
                                                        <div class="px-2 space-y-1">
                                                            <DropdownLink :href="route('profile.show')" class="group !rounded-xl !py-3 !px-4">
                                                                <div class="flex items-center gap-4"> 
                                                                    <div class="text-slate-500 group-hover:text-indigo-600 transition-colors">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                                        </svg>
                                                                    </div>
                                                                    <span class="text-[14px] font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Edit profile</span>
                                                                </div>
                                                            </DropdownLink>

                                                            <DropdownLink :href="route('profile.show')" class="group !rounded-xl !py-3 !px-4">
                                                                <div class="flex items-center gap-4"> 
                                                                    <div class="text-slate-500 group-hover:text-indigo-600 transition-colors">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1 1 15 0 7.5 7.5 0 0 1-15 0Z" />
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                                                        </svg>
                                                                    </div>
                                                                    <span class="text-[14px] font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Account settings</span>
                                                                </div>
                                                            </DropdownLink>

                                                            <!-- Switch Context Options -->
                                                            <div class="border-t border-slate-50 my-2 mx-4" />

                                                            <DropdownLink :href="route('entity-context.index')" v-if="$page.props.user_role === 'Platform Admin' || $page.props.user_role === 'Saas Owner'" class="group !rounded-xl !py-3 !px-4 bg-indigo-50/30 hover:bg-indigo-50">
                                                                <div class="flex items-center justify-between w-full">
                                                                    <div class="flex items-center gap-4"> 
                                                                        <div class="text-indigo-600">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="flex flex-col">
                                                                            <span class="text-[14px] font-bold text-indigo-700 leading-tight">Switch Entity</span>
                                                                            <span class="text-[11px] text-indigo-400 font-medium truncate max-w-[120px]">{{ $page.props.active_entity?.entity_name || 'Select Entity' }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3 text-indigo-300">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                                    </svg>
                                                                </div>
                                                            </DropdownLink>

                                                            <DropdownLink :href="route('entity-context.index')" v-if="$page.props.plants_count > 1" class="group !rounded-xl !py-3 !px-4 hover:bg-slate-50">
                                                                <div class="flex items-center justify-between w-full">
                                                                    <div class="flex items-center gap-4"> 
                                                                        <div class="text-slate-500 group-hover:text-indigo-600 transition-colors">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6h1.5m-1.5 3h1.5m-1.5 3h1.5M9 16.5h1.5m3 0h1.5" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="flex flex-col">
                                                                            <span class="text-[14px] font-medium text-slate-700 group-hover:text-slate-900 transition-colors leading-tight">Switch Plant</span>
                                                                            <span class="text-[11px] text-slate-400 font-medium truncate max-w-[120px]">{{ $page.props.active_plant?.plant_name || 'Select Plant' }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3 text-slate-300">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                                    </svg>
                                                                </div>
                                                            </DropdownLink>
                                                        </div>

                                                        <div class="border-t border-slate-100 my-2 mx-4" />

                                                        <!-- Authentication -->
                                                        <div class="px-2 pb-2">
                                                            <form @submit.prevent="logout">
                                                                <DropdownLink as="button" class="group !rounded-xl !py-4 !px-4">
                                                                    <div class="flex items-center gap-4 w-full">
                                                                        <div class="text-slate-500 group-hover:text-rose-600 transition-colors">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                                                            </svg>
                                                                        </div>
                                                                        <span class="text-[14px] font-bold text-slate-700 group-hover:text-rose-600 transition-colors">Sign out</span>
                                                                    </div>
                                                                </DropdownLink>
                                                            </form>
                                                        </div>
                                                    </template>
                                                </Dropdown>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </nav>

                            <!-- Page Heading -->
                            <header v-if="$slots.header" class=" dark:bg-gray-800 ">
                                <div class="max-w-7xl mx-auto  ">
                                    <slot name="header" />
                                </div>
                            </header>

                             <main class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                                <slot />
                            </main>
                        </div>

                        <!-- Session Timeout Modal -->
                        <div v-if="showTimeoutModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-300">
                            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-100 dark:border-gray-700 transform animate-in zoom-in-95 duration-300">
                                <div class="p-8 text-center">
                                    <div class="mx-auto w-20 h-20 bg-amber-50 dark:bg-amber-900/20 rounded-full flex items-center justify-center mb-6">
                                        <ClockIcon class="h-10 w-10 text-amber-500 animate-pulse" />
                                    </div>
                                    
                                    <h3 class="text-2xl font-black text-slate-800 dark:text-white tracking-tight mb-2">Session Expiring Soon</h3>
                                    <p class="text-slate-500 dark:text-gray-400 text-sm mb-8 leading-relaxed">
                                        You've been inactive for a while. For your security, you will be automatically logged out in:
                                    </p>

                                    <div class="inline-flex items-center justify-center bg-slate-50 dark:bg-gray-900 px-6 py-4 rounded-2xl mb-8 border border-slate-100 dark:border-gray-700">
                                        <span class="text-4xl font-mono font-black text-indigo-600 dark:text-indigo-400">
                                            {{ Math.floor(remainingTime / 60) }}:{{ String(remainingTime % 60).padStart(2, '0') }}
                                        </span>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <button 
                                            @click="resetTimer"
                                            class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-indigo-200 dark:shadow-none active:scale-[0.98]"
                                        >
                                            Keep Me Logged In
                                        </button>
                                        <button 
                                            @click="logout"
                                            class="w-full py-3 text-slate-400 hover:text-rose-500 font-bold text-sm transition-colors uppercase tracking-widest"
                                        >
                                            Logout Now
                                        </button>
                                    </div>
                                </div>
                                <div class="h-1.5 w-full bg-slate-100 dark:bg-gray-700 overflow-hidden">
                                    <div 
                                        class="h-full bg-indigo-500 transition-all duration-1000 linear"
                                        :style="{ width: (remainingTime / (10 * 60) * 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>

    </div>
</template>

