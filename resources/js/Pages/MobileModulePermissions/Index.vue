<!--
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import MobileModulePermissions from '@/Pages/Users/MobileModulePermissions.vue';

const props = defineProps<{ users: Array<{ id: number; username: string; email: string }>; plantId: number; plantName: string }>();
const selectedId = ref<number | null>(props.users[0]?.id ?? null);
const selectedUser = computed(() => props.users.find(user => user.id === selectedId.value));
const userOptions = computed(() => props.users.map(user => ({
    id: user.id,
    label: `${user.username} — ${user.email}`,
})));
</script>

<template>
    <AppLayout title="Mobile App Permissions">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
            <BaseCard title="Select User" :subtitle="`Manage mobile access for plant ${plantName}.`">
                <BaseSelect v-model="selectedId" :options="userOptions" optionLabel="label" optionValue="id"
                    label="User" placeholder="Choose a user" filter :disabled="!users.length" />
                <p v-if="!users.length" class="mt-3 text-sm text-gray-500">No users are assigned to this plant.</p>
            </BaseCard>

            <MobileModulePermissions v-if="selectedUser" :key="`${plantId}-${selectedId}`" :user-id="selectedUser.id" />
        </div>
    </AppLayout>
</template>
