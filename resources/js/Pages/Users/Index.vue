<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import UserCreateForm from './UserCreateForm.vue';
import UserList from './UserList.vue';
import { ref } from 'vue';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Button from 'primevue/button';
import Message from 'primevue/message';

const props = defineProps<{
    users: any;
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number }>;
    userGroups: Array<{ label: string; value: number }>;
    filters: { search?: string };
    canManageMobileAccess?: boolean;
}>();

const onCreated = () => router.reload({ only: ['users'] });
const mobileEmail = ref('');
const mobileSaving = ref(false);
const mobileMessage = ref('');
const mobileError = ref('');
const setMobileAccess = async (enabled: boolean) => {
    mobileSaving.value = true;
    mobileMessage.value = '';
    mobileError.value = '';
    try {
        const response = await axios.put(route('users.mobile-access'), { email: mobileEmail.value.trim(), enabled });
        mobileMessage.value = `${mobileEmail.value.trim()}: ${response.data.message}`;
        router.reload({ only: ['users'] });
    } catch (error: any) {
        const errors = error.response?.data?.errors;
        const emailError = errors?.email;
        mobileError.value = (Array.isArray(emailError) ? emailError[0] : emailError) || error.response?.data?.message || 'Unable to update mobile access.';
    } finally {
        mobileSaving.value = false;
    }
};
</script>

<template>
    <AppLayout title="Users">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6 flex flex-col gap-6">

            <section v-if="canManageMobileAccess" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-white">Mobile App Access</h2>
                <p class="mt-1 text-sm text-slate-500">Grant or remove access by user email, regardless of role. Removing access signs out existing mobile sessions.</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <BaseInput class="w-full sm:max-w-md" v-model="mobileEmail" label="User Email" type="email" placeholder="user@example.com" :disabled="mobileSaving" />
                    <Button type="button" label="Grant Access" :loading="mobileSaving" :disabled="mobileSaving || !mobileEmail.trim()" @click="setMobileAccess(true)" />
                    <Button type="button" label="Remove Access" severity="danger" outlined :disabled="mobileSaving || !mobileEmail.trim()" @click="setMobileAccess(false)" />
                </div>
                <Message v-if="mobileMessage" class="mt-3" severity="success" :closable="false">{{ mobileMessage }}</Message>
                <Message v-if="mobileError" class="mt-3" severity="error" :closable="false">{{ mobileError }}</Message>
            </section>

            <!-- Create Form -->
            <UserCreateForm
                :entities="entities"
                :plants="plants"
                :userGroups="userGroups"
                @created="onCreated"
            />

            <!-- Users Table (with inline row-expansion edit form) -->
            <UserList
                :users="users"
                :filters="filters"
                :entities="entities"
                :plants="plants"
                :userGroups="userGroups"
            />

        </div>
    </AppLayout>
</template>
