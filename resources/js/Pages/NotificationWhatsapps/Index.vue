<!--
Author: ragul-onemodo
Created: 2026-11-06 14:44:46 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Link, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { 
    ChatBubbleLeftRightIcon, 
    BuildingOffice2Icon, 
    DevicePhoneMobileIcon, 
    UserIcon, 
    EnvelopeIcon 
} from '@heroicons/vue/24/outline';

// PrimeVue
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

interface Plant {
    id: number;
    name: string;
}

interface UserItem {
    id: number;
    name: string;
    mobile: string | null;
    role_ids: number[];
}

interface RoleItem {
    id: number;
    name: string;
}

interface WhatsappSetting {
    id: number;
    plant_id: number;
    type: string;
    role_id: number | null;
    user_id: number | null;
    mobile: string | null;
    status: boolean;
    user?: { id: number; username: string; mobile: string | null } | null;
    role?: { id: number; name: string } | null;
}

const props = defineProps<{
    settings: WhatsappSetting[];
    plant: Plant;
    roles: RoleItem[];
    users: UserItem[];
}>();

const toast = useToast();
const { can } = usePermissions();

const editingId = ref<number | null>(null);
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const mode = ref<'user' | 'mobile'>('user');

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const form = useForm({
    type: 'Dispatch',
    role_id: null as number | null,
    user_id: null as number | null,
    mobile: '',
    status: true,
});

const filteredUsers = computed(() => {
    if (!form.role_id) return [];
    const list = props.users
        .filter(user => user.role_ids && user.role_ids.some(id => Number(id) === Number(form.role_id)))
        .map(user => ({
            id: user.id,
            name: user.name,
            mobile: user.mobile,
            label: `${user.name} — ${user.mobile || 'No mobile number'}`
        }));

    if (editingId.value && form.user_id && !list.some(u => Number(u.id) === Number(form.user_id))) {
        const currentSetting = props.settings.find(s => s.id === editingId.value);
        if (currentSetting?.user) {
            list.unshift({
                id: currentSetting.user.id,
                name: currentSetting.user.username,
                mobile: currentSetting.user.mobile,
                label: `${currentSetting.user.username} — ${currentSetting.user.mobile || 'No mobile number'}`
            });
        }
    }
    return list;
});

const selectedUser = computed(() => {
    return props.users.find(u => Number(u.id) === Number(form.user_id));
});

const handleRoleChange = () => {
    form.user_id = null;
};

const openCreateModal = () => {
    modalMode.value = 'create';
    editingId.value = null;
    mode.value = 'user';
    form.reset();
    form.clearErrors();
    form.type = 'Dispatch';
    form.status = true;
    showModal.value = true;
};

const openEditModal = (item: WhatsappSetting) => {
    modalMode.value = 'edit';
    editingId.value = item.id;
    mode.value = item.user_id ? 'user' : 'mobile';
    form.clearErrors();
    form.type = item.type || 'Dispatch';
    form.role_id = item.role_id ?? null;
    form.user_id = item.user_id ?? null;
    form.mobile = item.mobile || '';
    form.status = Boolean(item.status);
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    form.transform(data => ({
        type: data.type || 'Dispatch',
        role_id: mode.value === 'user' && data.role_id ? Number(data.role_id) : null,
        user_id: mode.value === 'user' && data.user_id ? Number(data.user_id) : null,
        mobile: mode.value === 'mobile' ? (data.mobile || '').trim() : null,
        status: Boolean(data.status),
    }));

    if (modalMode.value === 'edit' && editingId.value) {
        form.put(route('notificationwhatsapps.update', editingId.value), {
            onSuccess: (page) => {
                const flash = (page.props as any).flash?.success;
                toast.add({ severity: 'success', summary: 'Success', detail: flash || 'WhatsApp recipient updated.', life: 3000 });
                closeModal();
            },
        });
    } else {
        form.post(route('notificationwhatsapps.store'), {
            onSuccess: (page) => {
                const flash = (page.props as any).flash?.success;
                toast.add({ severity: 'success', summary: 'Success', detail: flash || 'WhatsApp recipients saved.', life: 3000 });
                closeModal();
            },
        });
    }
};

const deleteItem = (item: WhatsappSetting) => {
    Swal.fire({
        title: 'Remove WhatsApp recipient?',
        text: 'This recipient will no longer receive configured notifications.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('notificationwhatsapps.destroy', item.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Removed', detail: 'WhatsApp recipient removed.', life: 2500 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="WhatsApp Notifications">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="p-6 space-y-4">
            <!-- Navigation Actions & Plant Context Banner -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300 shadow-xs">
                        <BuildingOffice2Icon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Active Plant: <strong>{{ plant.name }}</strong></span>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can('NOTIFICATION_EMAIL.VIEW')" :href="route('notificationemails.index')">
                        <Button label="Notification Emails" icon="pi pi-envelope" severity="secondary" size="small" outlined />
                    </Link>
                </div>
            </div>

            <!-- Informational Banner -->
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-4 text-sm text-emerald-900 shadow-xs dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/60 rounded-lg text-emerald-700 dark:text-emerald-300 shrink-0">
                        <ChatBubbleLeftRightIcon class="w-5 h-5" />
                    </div>
                    <div class="space-y-1">
                        <p class="font-semibold text-emerald-950 dark:text-emerald-200">
                            Automated WhatsApp Dispatch Notifications for {{ plant.name }}
                        </p>
                        <p class="text-xs text-emerald-800 dark:text-emerald-400 leading-relaxed">
                            Select a role, then add users individually. Only saved recipients receive messages; selecting a role does not include everyone. Dispatch messages use the existing approved template and Send WhatsApp action.
                        </p>
                        <p class="text-xs text-emerald-700/90 dark:text-emerald-500">
                            For plants without any configured recipients, the customer mobile number is used as fallback. Once recipients are configured, only active entries receive dispatch alerts.
                        </p>
                    </div>
                </div>
            </div>

            <!-- BaseDataTable -->
            <BaseDataTable
                :value="props.settings"
                showSerial
                :rows="30"
                showSearch
                :showAdvancedFilter="false"
                :rowsPerPageOptions="[30, 50, 100, 200]"
                v-model:filters="filters"
                :globalFilterFields="['type', 'role.name', 'user.username', 'mobile']"
                class="text-xs"
            >
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-3 p-2">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl border border-emerald-100 dark:border-emerald-800">
                                <ChatBubbleLeftRightIcon class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 uppercase tracking-tight">
                                    WhatsApp Recipients
                                </h2>
                                <p class="text-xs text-slate-500">
                                    Configure dispatch notification recipients for {{ plant.name }}
                                </p>
                            </div>
                        </div>
                        <Button 
                            v-if="can('NOTIFICATION_WHATSAPP.CREATE')" 
                            label="New Recipient" 
                            icon="pi pi-plus" 
                            @click="openCreateModal" 
                            severity="success" 
                        />
                    </div>
                </template>

                <Column field="type" header="Type" sortable style="min-width: 110px">
                    <template #body="{ data }">
                        <span class="font-semibold text-slate-800 dark:text-slate-200">
                            {{ data.type }}
                        </span>
                    </template>
                </Column>

                <Column header="Source" style="min-width: 130px">
                    <template #body="{ data }">
                        <Tag 
                            :value="data.user_id ? 'Linked User' : 'Direct Mobile'" 
                            :severity="data.user_id ? 'info' : 'secondary'" 
                            rounded 
                            class="text-[11px] font-semibold"
                        />
                    </template>
                </Column>

                <Column field="role.name" header="Role" sortable style="min-width: 140px">
                    <template #body="{ data }">
                        <span v-if="data.role?.name" class="font-medium text-slate-700 dark:text-slate-300">
                            {{ data.role.name }}
                        </span>
                        <span v-else class="text-slate-400 italic">Direct mobile</span>
                    </template>
                </Column>

                <Column field="user.username" header="User" sortable style="min-width: 150px">
                    <template #body="{ data }">
                        <div v-if="data.user?.username" class="flex items-center gap-1.5 font-medium text-slate-800 dark:text-slate-200">
                            <UserIcon class="w-3.5 h-3.5 text-slate-400" />
                            <span>{{ data.user.username }}</span>
                        </div>
                        <span v-else class="text-slate-400">—</span>
                    </template>
                </Column>

                <Column header="Mobile Number" style="min-width: 160px">
                    <template #body="{ data }">
                        <div class="flex items-center gap-1.5 font-mono text-slate-700 dark:text-slate-300">
                            <DevicePhoneMobileIcon class="w-3.5 h-3.5 text-slate-400" />
                            <span>{{ data.user_id ? (data.user?.mobile || 'Unavailable') : data.mobile }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="status" header="Status" sortable style="min-width: 110px">
                    <template #body="{ data }">
                        <Tag 
                            :value="data.status ? 'Active' : 'Inactive'" 
                            :severity="data.status ? 'success' : 'danger'" 
                            rounded 
                        />
                    </template>
                </Column>

                <Column header="Actions" class="text-right" style="width: 110px">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1.5">
                            <Button 
                                v-if="can('NOTIFICATION_WHATSAPP.UPDATE')" 
                                icon="pi pi-pencil" 
                                text 
                                rounded 
                                size="small" 
                                @click="openEditModal(data)" 
                                severity="info" 
                                title="Edit Recipient"
                            />
                            <Button 
                                v-if="can('NOTIFICATION_WHATSAPP.DELETE')" 
                                icon="pi pi-trash" 
                                text 
                                rounded 
                                size="small" 
                                @click="deleteItem(data)" 
                                severity="danger" 
                                title="Remove Recipient"
                            />
                        </div>
                    </template>
                </Column>

                <template #empty>
                    <div class="py-12 text-center text-slate-500 dark:text-slate-400">
                        <ChatBubbleLeftRightIcon class="w-12 h-12 mx-auto mb-3 text-slate-300 dark:text-slate-600" />
                        <p class="text-base font-medium">No WhatsApp recipients configured yet</p>
                        <p class="text-xs text-slate-400 mt-1">
                            Dispatch notifications will fall back to customer mobile numbers until recipients are added.
                        </p>
                        <Button 
                            v-if="can('NOTIFICATION_WHATSAPP.CREATE')" 
                            label="Add First Recipient" 
                            icon="pi pi-plus" 
                            size="small" 
                            severity="success" 
                            class="mt-4" 
                            @click="openCreateModal" 
                        />
                    </div>
                </template>
            </BaseDataTable>
        </div>

        <!-- Recipient Dialog Modal -->
        <Dialog 
            v-model:visible="showModal" 
            modal 
            :header="modalMode === 'edit' ? 'EDIT WHATSAPP RECIPIENT' : 'NEW WHATSAPP RECIPIENT'" 
            :style="{ width: '600px' }"
            :closable="!form.processing"
        >
            <div class="grid grid-cols-2 gap-4 py-3">
                <!-- Recipient Source Toggle -->
                <div class="col-span-2 flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Recipient Source</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="mode = 'user'"
                            :class="[
                                'flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg border transition-all cursor-pointer',
                                mode === 'user' 
                                    ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-500 dark:text-emerald-300 shadow-xs'
                                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300'
                            ]"
                        >
                            <UserIcon class="w-4 h-4" />
                            <span>Linked ERP User</span>
                        </button>
                        <button
                            type="button"
                            @click="mode = 'mobile'"
                            :class="[
                                'flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg border transition-all cursor-pointer',
                                mode === 'mobile' 
                                    ? 'bg-emerald-50 border-emerald-500 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-500 dark:text-emerald-300 shadow-xs'
                                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300'
                            ]"
                        >
                            <DevicePhoneMobileIcon class="w-4 h-4" />
                            <span>Direct Mobile Number(s)</span>
                        </button>
                    </div>
                </div>

                <!-- Notification Type -->
                <div class="col-span-2 sm:col-span-1 flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Notification Type</label>
                    <BaseSelect 
                        v-model="form.type" 
                        :options="[{ label: 'Dispatch', value: 'Dispatch' }]" 
                        optionLabel="label" 
                        optionValue="value" 
                        fluid 
                    />
                    <small v-if="form.errors.type" class="text-xs text-red-500">{{ form.errors.type }}</small>
                </div>

                <!-- Status -->
                <div class="col-span-2 sm:col-span-1 flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Status</label>
                    <div class="flex items-center gap-2.5 mt-2">
                        <ToggleSwitch v-model="form.status" />
                        <span class="text-sm font-medium" :class="form.status ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                            {{ form.status ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <small v-if="form.errors.status" class="text-xs text-red-500">{{ form.errors.status }}</small>
                </div>

                <!-- User Mode Fields -->
                <template v-if="mode === 'user'">
                    <div class="col-span-2 sm:col-span-1 flex flex-col gap-1.5">
                        <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Role <span class="text-red-500">*</span></label>
                        <BaseSelect 
                            v-model="form.role_id" 
                            :options="props.roles" 
                            optionLabel="name" 
                            optionValue="id" 
                            placeholder="Select Role" 
                            fluid 
                            filter 
                            @change="handleRoleChange" 
                        />
                        <small v-if="form.errors.role_id" class="text-xs text-red-500">{{ form.errors.role_id }}</small>
                    </div>

                    <div class="col-span-2 sm:col-span-1 flex flex-col gap-1.5">
                        <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">User <span class="text-red-500">*</span></label>
                        <BaseSelect 
                            v-model="form.user_id" 
                            :options="filteredUsers" 
                            optionLabel="label" 
                            optionValue="id" 
                            placeholder="Select User" 
                            :disabled="!form.role_id" 
                            fluid 
                            filter 
                        />
                        <small v-if="form.errors.user_id" class="text-xs text-red-500">{{ form.errors.user_id }}</small>
                    </div>

                    <div class="col-span-2 flex flex-col gap-1.5">
                        <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Linked Mobile Number</label>
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg border bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 text-sm">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-slate-400" />
                            <span :class="selectedUser?.mobile ? 'font-mono text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 italic'">
                                {{ selectedUser?.mobile || (form.user_id ? 'No mobile number registered for this user' : 'Select a user above') }}
                            </span>
                        </div>
                        <small class="text-xs text-slate-400">Uses the mobile number registered on the user's account profile.</small>
                        <small v-if="form.errors.mobile" class="text-xs text-red-500 font-medium">{{ form.errors.mobile }}</small>
                    </div>
                </template>

                <!-- Direct Mobile Mode Fields -->
                <template v-else>
                    <div class="col-span-2 flex flex-col gap-1.5">
                        <template v-if="modalMode === 'create'">
                            <label for="notification-whatsapp-mobiles" class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                                Mobile Numbers <span class="text-red-500">*</span>
                            </label>
                            <Textarea 
                                id="notification-whatsapp-mobiles" 
                                v-model="form.mobile" 
                                rows="3" 
                                fluid
                                placeholder="9876543210, 9876543211, +919876543212" 
                                class="w-full text-sm"
                            />
                            <small class="text-xs text-slate-500">
                                Enter up to 50 mobile numbers separated by commas, semicolons, or new lines. Indian 10-digit numbers automatically receive prefix +91.
                            </small>
                        </template>
                        <template v-else>
                            <label for="notification-whatsapp-mobile" class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                                Mobile Number <span class="text-red-500">*</span>
                            </label>
                            <BaseInput 
                                id="notification-whatsapp-mobile" 
                                v-model="form.mobile" 
                                fluid 
                                placeholder="9876543210" 
                                hint="Indian 10-digit numbers receive country code 91. Include country code for international numbers." 
                            />
                        </template>
                        <small v-if="form.errors.mobile" class="text-xs text-red-500 font-medium">{{ form.errors.mobile }}</small>
                    </div>
                </template>
            </div>

            <template #footer>
                <div class="flex gap-2 justify-end mt-4">
                    <Button label="Cancel" text severity="secondary" @click="closeModal" />
                    <Button 
                        v-if="can(editingId ? 'NOTIFICATION_WHATSAPP.UPDATE' : 'NOTIFICATION_WHATSAPP.CREATE')" 
                        :label="modalMode === 'edit' ? 'Update Recipient' : 'Save Recipient'" 
                        icon="pi pi-check" 
                        severity="success" 
                        :loading="form.processing" 
                        @click="submit" 
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
