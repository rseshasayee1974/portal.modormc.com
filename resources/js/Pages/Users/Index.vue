<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import UserCreateForm from './UserCreateForm.vue';
import UserList from './UserList.vue';

const props = defineProps<{
    users: any;
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number }>;
    userGroups: Array<{ label: string; value: number }>;
    filters: { search?: string };
}>();

const onCreated = () => router.reload({ only: ['users'] });
</script>

<template>
    <AppLayout title="Users">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6 flex flex-col gap-6">

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
