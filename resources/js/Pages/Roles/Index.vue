<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import RoleCreateForm from './RoleCreateForm.vue';
import RoleEditForm from './RoleEditForm.vue';
import RoleList from './RoleList.vue';

const props = defineProps<{
    roles: any;
    groupedPermissions: Record<string, any[]>;
    filters: { search?: string };
}>();

const editingRoleId = ref<number | null>(null);
const showEditModal = ref(false);

const onCreated = () => {
    router.reload({ only: ['roles'] });
};

const onUpdated = () => {
    router.reload({ only: ['roles'] });
};

const openEditModal = (role: any) => {
    editingRoleId.value = role.id;
    showEditModal.value = true;
};
</script>

<template>
    <AppLayout title="Roles Management">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6 flex flex-col gap-6">
            <!-- Create Form -->
            <RoleCreateForm 
                :groupedPermissions="groupedPermissions"
                @created="onCreated"
            />

            <!-- List Table -->
            <RoleList 
                :roles="roles"
                :filters="filters"
                @edit="openEditModal"
            />

            <!-- Edit Modal -->
            <RoleEditForm 
                v-model:visible="showEditModal"
                :roleId="editingRoleId"
                :groupedPermissions="groupedPermissions"
                @updated="onUpdated"
            />
        </div>
    </AppLayout>
</template>
