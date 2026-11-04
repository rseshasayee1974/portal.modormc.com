<!--
Author: Cipher
Created: 2026-11-04 12:25:19 Asia/Calcutta (UTC+05:30)
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps<{ personnel: any }>();
const emit = defineEmits<{ saved: [enabled: boolean] }>();
const page = usePage();
const users = computed(() => (page.props.mobile_link_users as any[] ?? []).map(user => ({
    ...user, label: `${user.username} (${user.email})`,
})));
const userId = ref<number | null>(props.personnel.user_id ? Number(props.personnel.user_id) : null);
const enabled = ref(Boolean(props.personnel.user?.is_otp_enabled));
const saving = ref(false);
const error = ref('');
const message = ref('');
watch(userId, id => {
    enabled.value = Boolean(users.value.find(user => Number(user.id) === Number(id))?.is_otp_enabled);
    error.value = '';
    message.value = '';
});
async function save() {
    saving.value = true;
    error.value = '';
    message.value = '';
    try {
        const response = await axios.put(route('personnel.user-link', props.personnel.id), {
            user_id: userId.value, enabled: userId.value ? enabled.value : false,
        });
        message.value = response.data.message;
        emit('saved', Boolean(response.data.data.enabled));
        router.reload({ only: ['users', 'unlinkedPersonnel', 'mobile_link_users'], preserveScroll: true });
    } catch (e: any) {
        error.value = e.response?.data?.errors?.user_id?.[0]
            ?? e.response?.data?.message ?? 'Unable to save the user link.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div v-if="page.props.can_manage_user_links" class="grid w-full min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
        <BaseSelect v-model="userId" :options="users" optionLabel="label" optionValue="id"
            label="Linked user" placeholder="Select a user for this personnel" showClear :disabled="saving" class="min-w-0 w-full" />
        <div class="flex flex-wrap items-center gap-3 md:pt-5">
            <ToggleSwitch v-model="enabled" :inputId="`personnel-mobile-access-${personnel.id}`" :disabled="!userId || saving" />
            <label :for="`personnel-mobile-access-${personnel.id}`">Enable mobile app access</label>
        </div>
        <p class="md:col-span-2 text-xs text-gray-500">Changing the link signs out existing mobile sessions.</p>
        <p v-if="error" class="md:col-span-2 text-sm text-red-600" role="alert">{{ error }}</p>
        <p v-if="message" class="md:col-span-2 text-sm text-green-700" role="status">{{ message }}</p>
        <div class="md:col-span-2">
            <BaseButton type="button" label="Save User Link" :loading="saving" @click="save" />
        </div>
    </div>
</template>
