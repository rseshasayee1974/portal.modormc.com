<!--
Author: ragul-onemodo
Created: 2026-11-06 14:44:46 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import { usePermissions } from '@/Composables/usePermissions';
import Swal from 'sweetalert2';
const props = defineProps({ settings: Array, plant: Object, roles: Array, users: Array });
const { can } = usePermissions();
const editing = ref(null);
const mode = ref('user');
const form = useForm({ type: 'Dispatch', role_id: null, user_id: null, mobile: '', status: true });
const users = computed(() => props.users.filter(user => user.role_ids.some(id => Number(id) === Number(form.role_id)))
    .map(user => ({ ...user, label: `${user.name} — ${user.mobile || 'No mobile number'}` })));
const selectedUser = computed(() => props.users.find(user => user.id === form.user_id));
function reset() { editing.value = null; mode.value = 'user'; form.reset(); form.clearErrors(); }
function edit(row) {
    reset(); editing.value = row.id; mode.value = row.user_id ? 'user' : 'mobile';
    Object.assign(form, { type: row.type, role_id: row.role_id, user_id: row.user_id, mobile: row.mobile || '', status: row.status });
}
function save() {
    form.transform(data => ({ ...data, user_id: mode.value === 'user' ? data.user_id : null,
        role_id: mode.value === 'user' ? data.role_id : null, mobile: mode.value === 'mobile' ? data.mobile : null }));
    if (editing.value) form.put(route('notificationwhatsapps.update', editing.value), { onSuccess: reset });
    else form.post(route('notificationwhatsapps.store'), { onSuccess: reset });
}
async function remove(row) {
    const result = await Swal.fire({ title: 'Remove WhatsApp recipient?', text: 'This recipient will no longer receive configured notifications.', icon: 'warning', showCancelButton: true });
    if (result.isConfirmed) form.delete(route('notificationwhatsapps.destroy', row.id));
}
</script>
<template>
    <AppLayout title="WhatsApp Notifications">
        <template #header><ModuleSubTopNav /></template>
        <div class="space-y-5 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold">WhatsApp Notifications</h1><p class="text-sm text-slate-500">{{ plant.name }}</p></div><Link :href="route('notificationemails.index')" class="text-indigo-600">Notification Emails</Link></div>
            <p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900">Select a role, then add users individually. Only saved recipients receive messages; selecting a role does not include everyone. Dispatch messages use the existing approved template and Send WhatsApp action.</p>
            <p class="text-sm text-slate-500">For plants without any Dispatch WhatsApp configuration, the existing customer-mobile recipient is used. Once configured, only active configured recipients are used.</p>
            <form v-if="can(editing ? 'SETTING.UPDATE' : 'SETTING.CREATE')" @submit.prevent="save" class="space-y-4 rounded-xl border bg-white p-5 dark:bg-slate-900">
                <h2 class="font-semibold">{{ editing ? 'Edit recipient' : 'Add recipient' }}</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <BaseSelect v-model="form.type" label="Notification Type" :options="[{label:'Dispatch',value:'Dispatch'}]" optionLabel="label" optionValue="value" :error="form.errors.type" />
                    <BaseSelect v-model="mode" label="Recipient Source" :options="[{label:'Linked user',value:'user'},{label:'Mobile number',value:'mobile'}]" optionLabel="label" optionValue="value" />
                    <BaseSelect v-model="form.status" label="Status" :options="[{label:'Active',value:true},{label:'Inactive',value:false}]" optionLabel="label" optionValue="value" :error="form.errors.status" />
                    <template v-if="mode === 'user'">
                        <BaseSelect v-model="form.role_id" label="Role" :options="roles" optionLabel="name" optionValue="id" required :error="form.errors.role_id" @change="form.user_id = null" />
                        <BaseSelect v-model="form.user_id" label="Select User" :options="users" optionLabel="label" optionValue="id" required :disabled="!form.role_id" :error="form.errors.user_id" />
                        <BaseInput :modelValue="selectedUser?.mobile || ''" label="Linked Mobile Number" readonly hint="Uses the current mobile number saved on the user." />
                    </template>
                    <BaseInput v-else v-model="form.mobile" :label="editing ? 'Mobile Number' : 'Mobile Numbers (comma separated)'" placeholder="9876543210, +919876543211" required :error="form.errors.mobile" class="md:col-span-3" hint="Indian 10-digit numbers receive country code 91. Include country code for international numbers." />
                </div>
                <p v-if="form.errors.mobile && mode === 'user'" class="text-sm text-red-600">{{ form.errors.mobile }}</p>
                <div class="flex gap-3"><button :disabled="form.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-white disabled:opacity-50">{{ editing ? 'Update' : 'Add Recipient' }}</button><button v-if="editing" type="button" @click="reset" class="px-4 py-2">Cancel</button></div>
            </form>
            <BaseDataTable :value="settings" :rows="30" paginator dataKey="id" heading="Configured WhatsApp Recipients" showSearch :globalFilterFields="['type','mobile','user.username','role.name']">
                <Column field="type" header="Notification Type" />
                <Column header="Role"><template #body="{data}">{{ data.role?.name || 'Direct mobile' }}</template></Column>
                <Column header="User"><template #body="{data}">{{ data.user?.username || '—' }}</template></Column>
                <Column header="Mobile Number"><template #body="{data}">{{ data.user_id ? (data.user?.mobile || 'Unavailable') : data.mobile }}</template></Column>
                <Column header="Status"><template #body="{data}">{{ data.status ? 'Active' : 'Inactive' }}</template></Column>
                <Column header="Actions"><template #body="{data}"><div class="flex gap-3"><button v-if="can('SETTING.UPDATE')" @click="edit(data)" class="text-indigo-600">Edit</button><button v-if="can('SETTING.DELETE')" @click="remove(data)" class="text-red-600">Remove</button></div></template></Column>
            </BaseDataTable>
        </div>
    </AppLayout>
</template>
