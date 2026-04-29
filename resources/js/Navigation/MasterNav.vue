<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
    MapIcon, 
    CreditCardIcon, 
    PhoneIcon, 
    GlobeAltIcon, 
    CurrencyDollarIcon, 
    BuildingOfficeIcon,
    ReceiptPercentIcon,
    BanknotesIcon,
    ClipboardDocumentListIcon,
    MapPinIcon,
    ShieldCheckIcon,
    KeyIcon,
    ArrowPathIcon,
    HomeIcon
} from '@heroicons/vue/24/outline';

const IconMap = {
    MapIcon, 
    CreditCardIcon, 
    PhoneIcon, 
    GlobeAltIcon, 
    CurrencyDollarIcon, 
    BuildingOfficeIcon,
    ReceiptPercentIcon,
    BanknotesIcon,
    ClipboardDocumentListIcon,
    MapPinIcon,
    ShieldCheckIcon,
    KeyIcon,
    ArrowPathIcon,
    HomeIcon
};

const page = usePage();

// parent_id 2 is Master
const visibleNav = computed(() => {
    return (page.props as any).menus?.sidebar_nav?.[2] || [];
});

const isSubMenuActive = (item) => {
    const currentUrl = page.url.toLowerCase();
    if (item.alias && currentUrl.startsWith('/' + item.alias.toLowerCase())) return true;
    if (item.link && item.link !== '#' && currentUrl.startsWith('/' + item.link.toLowerCase())) return true;
    return false;
};
</script>

<template>
    <section aria-labelledby="section-2-title">
        <h2 class="sr-only" id="section-2-title">Sidebar Navigation</h2>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow flex flex-col h-full ">
            <nav class="flex-1 px-4 divide-y divide-gray-200 overflow-y-auto" aria-label="Sidebar">
                <div class="px-2 space-y-1 pt-6 pb-6">
                    <Link
                        v-for="item in visibleNav"
                        :key="item.title"
                        :href="item.link === '#' ? '#' : (item.link.startsWith('/') ? item.link : '/' + item.link)"
                        :class="[
                            isSubMenuActive(item)
                                ? 'text-pastelbeaver-500 bg-manatee-50'
                                : 'text-gray-900 hover:text-pastelbeaver-500 hover:bg-manatee-50',
                            'group flex items-center px-2 py-2 text-xs font-semibold rounded-md transition-colors'
                        ]"
                    >
                        <div class="flex items-center">
                            <component
                                :is="IconMap[item.icon] || IconMap['HomeIcon']"
                                :class="[
                                    isSubMenuActive(item)
                                        ? 'textpastelbeaver500'
                                        : 'text-gray-900 group-hover:text-pastelbeaver-500',
                                    'mr-4 flex-shrink-0 h-4 w-4 transition-colors'
                                ]"
                                aria-hidden="true"
                            />
                            <span>{{ item.title }}</span>
                        </div>
                    </Link>
                </div>
            </nav>
        </div>
    </section>
</template>

