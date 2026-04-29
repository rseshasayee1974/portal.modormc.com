<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { 
    UserIcon, 
    KeyIcon, 
    ShieldCheckIcon, 
    ComputerDesktopIcon, 
    TrashIcon 
} from '@heroicons/vue/24/outline';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const activeTab = ref('profile');

const tabs = [
    { id: 'profile', name: 'Profile Information', icon: UserIcon, description: 'Personal details and avatar' },
    { id: 'security', name: 'Password Security', icon: KeyIcon, description: 'Update your login credentials' },
    { id: '2fa', name: 'Two-Factor Auth', icon: ShieldCheckIcon, description: 'Add extra layer of security' },
    { id: 'sessions', name: 'Browser Sessions', icon: ComputerDesktopIcon, description: 'Manage active devices' },
    // { id: 'danger', name: 'Danger Zone', icon: TrashIcon, description: 'Delete account and data' },
];
</script>

<template>
    <AppLayout title="Manage Account">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6 lg:py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Page Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Account Settings</h1>
                        <p class="text-slate-500 text-sm mt-1">Manage your personal information, security preferences and active sessions.</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-2">
                         <div class="size-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <UserIcon class="size-5" />
                         </div>
                    </div>
                </div>

                <div class="lg:grid lg:grid-cols-12 lg:gap-x-12">
                    <!-- Sidebar Navigation -->
                    <aside class="py-6 px-2 sm:px-6 lg:py-0 lg:px-0 lg:col-span-3">
                        <nav class="space-y-1">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    activeTab === tab.id
                                        ? 'bg-white shadow-sm ring-1 ring-slate-200 text-indigo-600'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                                    'group flex flex-col items-start px-4 py-3 text-sm font-medium rounded-xl transition-all duration-200 w-full text-left'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="tab.icon"
                                        :class="[
                                            activeTab === tab.id ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500',
                                            'size-5 flex-shrink-0'
                                        ]"
                                    />
                                    <span class="font-bold">{{ tab.name }}</span>
                                </div>
                                <span class="mt-1 text-[11px] font-normal text-slate-400 group-hover:text-slate-500">{{ tab.description }}</span>
                            </button>
                        </nav>
                    </aside>

                    <!-- Content Area -->
                    <div class="space-y-6 sm:px-6 lg:px-0 lg:col-span-9 animate-in fade-in slide-in-from-bottom-2 duration-500">
                        <!-- Profile Form -->
                        <div v-if="activeTab === 'profile'" class="space-y-6">
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                                <div class="p-6 md:p-8">
                                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                                </div>
                            </div>
                        </div>

                        <!-- Password Form -->
                        <div v-if="activeTab === 'security'" class="space-y-6">
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                                <div class="p-6 md:p-8">
                                    <UpdatePasswordForm />
                                </div>
                            </div>
                        </div>

                        <!-- 2FA Form -->
                        <div v-if="activeTab === '2fa'" class="space-y-6">
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                                <div class="p-6 md:p-8">
                                    <TwoFactorAuthenticationForm
                                        :requires-confirmation="confirmsTwoFactorAuthentication"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Sessions Form -->
                        <div v-if="activeTab === 'sessions'" class="space-y-6">
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                                <div class="p-6 md:p-8">
                                    <LogoutOtherBrowserSessionsForm :sessions="sessions" />
                                </div>
                            </div>
                        </div>

                        <!-- Delete User Form -->
                        <div v-if="activeTab === 'danger'" class="space-y-6">
                            <div class="bg-rose-50/30 rounded-2xl shadow-sm border border-rose-100 overflow-hidden">
                                <div class="p-6 md:p-8">
                                    <DeleteUserForm />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom overrides for Jetstream components to fit our new layout */
:deep(.md\:grid-cols-3) {
    grid-template-columns: 1fr !important;
}

:deep(.md\:col-span-2) {
    grid-column: span 1 / span 1 !important;
}

:deep(section > div:first-child) {
    display: none !important; /* Hide the default SectionTitle since we handle it in sidebar */
}

:deep(.shadow) {
    box-shadow: none !important;
}

:deep(.bg-white) {
    background-color: transparent !important;
}

:deep(.p-6) {
    padding: 0 !important;
}
</style>


