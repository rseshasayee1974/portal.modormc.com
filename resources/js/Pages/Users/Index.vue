<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import UserCreateForm from './UserCreateForm.vue';
import UserList from './UserList.vue';
import { ref } from 'vue';
import Button from 'primevue/button';
import PersonnelUserLink from './PersonnelUserLink.vue';

const props = defineProps<{
    users: any;
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number }>;
    userGroups: Array<{ label: string; value: number }>;
    filters: { search?: string };
    canManageMobileAccess?: boolean;
    unlinkedPersonnel?: any[];
}>();

const onCreated = () => router.reload({ only: ['users', 'unlinkedPersonnel', 'mobile_link_users'] });
const selectedPersonnel = ref<any>(null);
</script>

<template>
    <AppLayout title="Users">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6 flex flex-col gap-6">

            <!-- Create Form -->
            <UserCreateForm :selectedPersonnel="selectedPersonnel" :unlinkedPersonnel="unlinkedPersonnel ?? []"
                :entities="entities" :plants="plants" :userGroups="userGroups" @created="onCreated" />

            <!-- <section v-if="unlinkedPersonnel?.length" class="rounded-xl border bg-white p-5 dark:bg-slate-800">
                <h2 class="font-semibold">Personnel without login accounts</h2>
                <p class="mt-1 text-sm text-gray-500">Create a login for an existing personnel record, or link an existing user and enable mobile access.</p>
                <div v-for="person in unlinkedPersonnel" :key="person.id" class="grid gap-4 border-b py-4 md:grid-cols-2">
                    <div>
                        <p class="font-semibold">{{ person.first_name }} {{ person.last_name }} · {{ person.employee_code }}</p>
                        <p class="text-sm text-gray-500">{{ person.email || 'No login account' }}</p>
                        <Button type="button" label="Create Login" outlined class="mt-2" @click="selectedPersonnel = person" />
                    </div>
                    <PersonnelUserLink :personnel="person" />
                </div>
            </section> -->

            <!-- Users Table (with inline row-expansion edit form) -->
            <UserList :users="users" :filters="filters" :entities="entities" :plants="plants"
                :userGroups="userGroups" />

        </div>
    </AppLayout>
</template>
