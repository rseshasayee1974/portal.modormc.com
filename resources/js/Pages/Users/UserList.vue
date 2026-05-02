<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import { useToast } from 'primevue/usetoast';

import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';

import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import UserEditForm from './UserEditForm.vue';

const props = defineProps<{
    users: any;
    filters: { search?: string };
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number }>;
    userGroups: Array<{ label: string; value: number }>;
}>();

const toast        = useToast();
const searchQuery  = ref(props.filters.search || '');
const tableLoading = ref(false);

// ── Row expansion (Map of id -> boolean) ───────────
const expandedRows  = ref<Record<number, boolean>>({});
const expandedModes = ref<Record<number, 'edit' | 'view'>>({});

const isExpanded = (id: number) => !!expandedRows.value[id];

const openRow = (user: any, mode: 'edit' | 'view') => {
    if (isExpanded(user.id) && expandedModes.value[user.id] === mode) {
        closeRow(user.id);
        return;
    }
    expandedRows.value           = { [user.id]: true }; // only one row open at a time
    expandedModes.value[user.id] = mode;
};

const closeRow = (id: number) => {
    delete expandedRows.value[id];
};

// ── Delete ────────────────────────────────────────────────────────────────
const confirmDelete = (id: number) => {
    Swal.fire({
        title: 'Delete User?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!',
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('users.destroy', id), {
                onSuccess: () => toast.add({ severity: 'success', summary: 'Deleted', detail: 'User removed' }),
            });
        }
    });
};

// ── Pagination / search ───────────────────────────────────────────────────
const fetchUsers = (params: any) => {
    router.get(route('users.index'), params, {
        preserveState: true,
        replace: true,
        onStart:  () => (tableLoading.value = true),
        onFinish: () => (tableLoading.value = false),
    });
};

const handleSearch = debounce(() => fetchUsers({ search: searchQuery.value }), 300);
const onPage = (event: any) => fetchUsers({ page: event.page + 1, search: searchQuery.value });

const onUpdated = (userId: number) => {
    closeRow(userId);
    router.reload({ only: ['users'] });
};
</script>

<template>
    <BaseCard class="text-sm">
        <BaseDataTable
            :value="users.data"
            dataKey="id"
            lazy
            paginator
            :rows="users.per_page"
            :totalRecords="users.total"
            :loading="tableLoading"
            :expandedRows="expandedRows"
            @update:expandedRows="expandedRows = $event"
            @page="onPage"
            showSearch showSerial
            heading="User Directory"
            headingIcon="UserGroupIcon"
            showExport
            exportFilename="user-directory-report"
        >
            <template #toolbar>
                <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ users.total }} registered users</span>
                </div>
            </template>
            <!-- Row toggle chevron -->
            <!-- <Column  style="width: 3rem" /> -->

            

            <Column header="User">
                <template #body="slotProps">
                    <div class="flex items-center gap-2">
                        <Avatar
                            :image="slotProps.data.profile_photo_path
                                ? `/storage/${slotProps.data.profile_photo_path}`
                                : `https://ui-avatars.com/api/?name=${slotProps.data.username}&background=random`"
                            shape="circle"
                        />
                        <div class="flex flex-col">
                            <span class="font-bold">{{ slotProps.data.username }}</span>
                            <span class="text-xs text-gray-500">{{ slotProps.data.email }}</span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="mobile" header="Mobile" />

            <Column field="is_otp_enabled" header="OTP">
                <template #body="slotProps">
                    <i v-if="slotProps.data.is_otp_enabled" class="pi pi-shield text-amber-500" />
                    <i v-else class="pi pi-circle text-gray-300" />
                </template>
            </Column>

            <Column field="is_active" header="Status">
                <template #body="slotProps">
                    <Tag
                        :value="slotProps.data.is_active ? 'Active' : 'Inactive'"
                        :severity="slotProps.data.is_active ? 'success' : 'danger'"
                        rounded
                    />
                </template>
            </Column>

            <Column header="Access & Roles">
                <template #body="slotProps">
                    <div class="flex flex-col gap-1">
                        <div 
                            v-for="(access, idx) in (slotProps.data.entity_users || [])" 
                            :key="idx"
                            class="text-[10px] bg-gray-50 dark:bg-gray-800 p-1 rounded border border-gray-100 dark:border-gray-700"
                        >
                            <span class="font-bold text-blue-600 dark:text-blue-400">
                                {{ access.role?.name || 'No Role' }}
                            </span>
                            <span class="text-gray-400 mx-1">@</span>
                            <span class="text-gray-600 dark:text-gray-300">
                                {{ access.entity?.legal_name || 'Unknown Entity' }} 
                                <span v-if="access.plant" class="text-gray-400">({{ access.plant.name }})</span>
                            </span>
                        </div>
                        <span v-if="!slotProps.data.entity_users?.length" class="text-xs text-gray-400 italic">No access assigned</span>
                    </div>
                </template>
            </Column>

            <Column header="Actions" style="width: 140px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <BaseActionButton
                            icon="pi pi-eye" 
                            severity="secondary"
                            tooltip="View Details"
                            @click="openRow(slotProps.data, 'view')"
                        />
                        <BaseActionButton
                            icon="pi pi-pencil" 
                            severity="info"
                            tooltip="Edit User"
                            @click="openRow(slotProps.data, 'edit')"
                        />
                        <BaseDeleteButton
                            :url="route('users.destroy', slotProps.data.id)"
                            title="Delete User?"
                            text="This action cannot be undone."
                            @success="onUpdated(slotProps.data.id)"
                        />
                    </div>
                </template>
            </Column>

            <!-- ── Expansion slot: renders UserEditForm ─────────────── -->
            <template #expansion="slotProps">
                <UserEditForm
                    :user="slotProps.data"
                    :mode="expandedModes[slotProps.data.id] ?? 'edit'"
                    :entities="entities"
                    :plants="plants"
                    :userGroups="userGroups"
                    @close="closeRow(slotProps.data.id)"
                    @updated="onUpdated(slotProps.data.id)"
                />
            </template>

        </BaseDataTable>
    </BaseCard>
</template>
