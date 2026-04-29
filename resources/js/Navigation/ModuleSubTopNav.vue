<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

const page = usePage();
const { can } = usePermissions();

// Check if a parent menu item is active based on current URL or its children
const isParentActive = (parent: any) => {
    const currentUrl = page.url.toLowerCase();
    
    // Check self
    if (parent.alias && currentUrl.startsWith('/' + parent.alias.toLowerCase())) return true;
    if (parent.link && parent.link !== '#' && currentUrl.startsWith('/' + parent.link.toLowerCase())) return true;
    
    // Check children (sidebar_nav stores relationship)
    const children = (page.props as any).menus?.sidebar_nav?.[parent.id] || [];
    return children.some((child: any) => 
        (child.alias && currentUrl.startsWith('/' + child.alias.toLowerCase())) ||
        (child.link && child.link !== '#' && currentUrl.startsWith('/' + child.link.toLowerCase()))
    );
};

// Resolved active parent top-nav item
const activeParent = computed(() => {
    const topNav = (page.props as any).menus?.top_nav || [];
    return topNav.find((parent: any) => isParentActive(parent)) ?? null;
});

// Child menu items of the active parent
const subNavItems = computed(() => {
    if (!activeParent.value) return [];
    const sidebarNav = (page.props as any).menus?.sidebar_nav || {};
    const children = sidebarNav[activeParent.value.id] || [];

    return children
        .filter((child: any) => !child.permission_name || can(child.permission_name))
        .sort((a: any, b: any) => (a.ordering || 0) - (b.ordering || 0));
});

const isSubMenuActive = (item: any): boolean => {
    const currentUrl = page.url.toLowerCase();
    if (item.link && item.link !== '#' && currentUrl.startsWith('/' + item.link.toLowerCase())) return true;
    if (item.alias && currentUrl.startsWith('/' + item.alias.toLowerCase())) return true;
    return false;
};
</script>

<template>
    <!-- Simple Module Sub-Top Navigation -->
    <div v-if="activeParent" class="flex items-center no-scrollbar h-10 px-4 md:px-8 m-1 dark:bg-gray-800  border-gray-200 dark:border-gray-700">
        <!-- Module Name Label -->
        <div class="flex items-center shrink-0 pr-4 mr-4 border-r border-gray-300 dark:border-gray-600">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                {{ activeParent.title }}
            </span>
        </div>

        <!-- Sub-Menu Links -->
        <div class="flex items-center gap-4 overflow-x-auto no-scrollbar h-full">
            <Link
                v-for="item in subNavItems"
                :key="item.id"
                :href="item.link === '#' ? '#' : (item.link.startsWith('/') ? item.link : '/' + item.link)"
                :class="[
                    isSubMenuActive(item)
                        ? 'text-primary-600 bg-yellow-300 px-2   rounded-full font-bold border-b-2 border-primary-500'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 border-b-2 border-transparent',
                    'h-full flex items-center text-[11px] uppercase tracking-wide whitespace-nowrap transition-colors duration-150'
                ]"
            >
                {{ item.title }}
            </Link>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

