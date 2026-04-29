<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import FileUpload from 'primevue/fileupload';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';

const props = defineProps<{
    user: any;
    mode: 'edit' | 'view';
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number }>;
    userGroups: Array<{ label: string; value: number }>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated'): void;
}>();

const toast = useToast();

type EntityAssignment = { entity_id: number | null; plant_id: number | null; role_id: number | null };

const form = ref({
    username: '', email: '', mobile: '', password: '',
    is_active: true, is_otp_enabled: false,
    entity_users: [] as EntityAssignment[],
    profile_photo: null as File | null,
    processing: false,
    errors: {} as any,
});

const loading = ref(false);

const loadUser = async () => {
    if (!props.user?.id) {
        console.warn('UserEditForm: No user ID provided');
        return;
    }
    console.log('UserEditForm: loading data for ID', props.user.id);
    loading.value = true;
    try {
        const { data } = await axios.get(route('users.show', props.user.id));
        form.value = {
            username:       data.username || '',
            email:          data.email || '',
            mobile:         data.mobile ?? '',
            password:       '',
            is_active:      Boolean(data.is_active),
            is_otp_enabled: Boolean(data.is_otp_enabled),
            entity_users:   (data.entity_users || []).map((eu: any) => ({ 
                entity_id: eu.entity_id, 
                plant_id:  eu.plant_id,
                role_id:   eu.role_id 
            })),
            profile_photo:  null,
            processing:     false,
            errors:         {},
        };

        // Pre-fetch plants for all assigned entities
        (data.entity_users || []).forEach((eu: any) => {
            if (eu.entity_id) fetchPlants(eu.entity_id);
        });
    } catch (err) {
        console.error('Failed to load user:', err);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load user data' });
    } finally {
        loading.value = false;
    }
};

watch(() => props.user?.id, loadUser, { immediate: true });

const addEntity    = () => form.value.entity_users.push({ entity_id: null, plant_id: null, role_id: null });
const removeEntity = (idx: number) => form.value.entity_users.splice(idx, 1);
const onFileUpload = (event: any) => { form.value.profile_photo = event.files[0]; };

const plantsCache = ref<Record<number, any[]>>({});
const fetchPlants = async (entityId: number | null) => {
    if (!entityId || plantsCache.value[entityId]) return;
    try {
        const { data } = await axios.get(route('plants.by-entity'), { params: { entity_id: entityId } });
        plantsCache.value[entityId] = data;
    } catch (err) {
        console.error('Failed to fetch plants:', err);
    }
};

const handleEntityChange = (assignment: any) => {
    assignment.plant_id = null;
    fetchPlants(assignment.entity_id);
};

const validate = () => {
    const errors: any = {};
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!form.value.email) {
        errors.email = ['Email is required'];
    } else if (!emailRegex.test(form.value.email)) {
        errors.email = ['Please enter a valid email address'];
    }

    if (!form.value.mobile) {
        errors.mobile = ['Mobile number is required'];
    } else if (!/^\d{10}$/.test(form.value.mobile)) {
        errors.mobile = ['Mobile number must be exactly 10 digits'];
    }

    form.value.errors = errors;
    return Object.keys(errors).length === 0;
};

const submit = async () => {
    if (!validate()) return;

    form.value.processing = true;
    form.value.errors = {};

    const fd = new FormData();
    fd.append('_method',        'PUT');
    fd.append('username',       form.value.username);
    fd.append('email',          form.value.email);
    fd.append('mobile',         form.value.mobile);
    // fd.append('role_id',        form.value.entity_users[0].role_id);
    if (form.value.password) fd.append('password', form.value.password);
    fd.append('is_active',      form.value.is_active      ? '1' : '0');
    fd.append('is_otp_enabled', form.value.is_otp_enabled ? '1' : '0');
    form.value.entity_users.forEach((eu, i) => {
        if (eu.entity_id) fd.append(`entity_users[${i}][entity_id]`, eu.entity_id.toString());
        if (eu.plant_id)  fd.append(`entity_users[${i}][plant_id]`,  eu.plant_id.toString());
        if (eu.role_id)   fd.append(`entity_users[${i}][role_id]`,   eu.role_id.toString());
    });
    if (form.value.profile_photo) fd.append('profile_photo_path', form.value.profile_photo);

    try {
        await axios.post(route('users.update', props.user.id), fd);
        toast.add({ severity: 'success', summary: 'Updated', detail: 'User updated successfully', life: 3000 });
        emit('updated');
    } catch (err: any) {
        if (err.response?.status === 422) {
            form.value.errors = err.response.data.errors;
            toast.add({ severity: 'error', summary: 'Validation Error', detail: 'Please check the form for errors', life: 3000 });
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update user', life: 3000 });
        }
    } finally {
        form.value.processing = false;
    }
};
</script>

<template>
    <div class="bg-blue-50/40 dark:bg-gray-800/60 border-t border-blue-100 dark:border-gray-700 px-6 py-5">

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center gap-3 py-8 text-gray-500">
            <i class="pi pi-spin pi-spinner text-2xl" />
            <span class="text-sm">Loading user data…</span>
        </div>

        <div v-else class="grid grid-cols-12 gap-4 animate-in fade-in duration-300">
            <!-- Header -->
            <!-- <div class="col-span-12 flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <i :class="mode === 'view' ? 'pi pi-eye text-gray-400' : 'pi pi-user-edit text-blue-500'" class="text-lg" />
                    <span class="text-sm font-bold uppercase tracking-widest text-gray-600 dark:text-gray-300">
                        {{ mode === 'view' ? 'User Details' : 'Edit User' }}
                    </span>
                </div>
                <BaseButton icon="pi pi-times" text rounded severity="secondary"  @click="$emit('close')" />
            </div> -->

            <!-- Single-page form grid -->
            <div class="col-span-12 grid grid-cols-12 gap-4">

                <!-- Profile Photo (edit only) -->
                

                <!-- Basic Fields -->
                <div class="col-span-12 md:col-span-3">
                    <BaseInput v-model="form.username" label="Username" :disabled="mode === 'view'" :error="form.errors.username" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput v-model="form.email" label="Email Address" :disabled="mode === 'view' || mode === 'edit'" :error="form.errors.email" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput v-model="form.mobile" label="Mobile Number" :disabled="mode === 'view'" :error="form.errors.mobile" maxlength="10" />
                </div>

                <!-- Password (edit only) -->
                <div v-if="mode === 'edit'" class="col-span-12 md:col-span-3">
                    <BaseInput v-model="form.password" type="password" label="New Password" placeholder="Leave blank to keep current" :error="form.errors.password" />
                </div>

                <!-- Toggles -->
                <div class="col-span-12 md:col-span-2 flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Status</label>
                    <div class="flex items-center gap-2 mt-2">
                        <ToggleSwitch v-model="form.is_active" :disabled="mode === 'view'" />
                        <!-- <span class="text-sm">Active Account</span> -->
                    </div>
                </div>
                <div class="col-span-12 md:col-span-1 flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Enable OTP</label>
                    <div class="flex items-center gap-2 mt-2">
                        <ToggleSwitch v-model="form.is_otp_enabled" :disabled="mode === 'view'" />
                        <!-- <span class="text-sm">Enable OTP</span> -->
                    </div>
                </div>
                <div v-if="mode === 'edit'" class="col-span-12 md:col-span-3">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Profile Photo</label>
                    <FileUpload
                        mode="basic"
                        :name="`photo_${user.id}[]`"
                        accept="image/*"
                        :maxFileSize="1000000"
                        @select="onFileUpload"
                        auto
                    />
                </div>

                <div class="col-span-12">
                    <div class="flex items-center justify-between mb-3 border-b border-gray-100 dark:border-gray-700 pb-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Entity & Plant Access</span>
                        <BaseButton v-if="mode === 'edit'" label="Add Assignment" icon="pi pi-plus" class="bg-indigo-50" text  @click="addEntity" />
                    </div>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="(assignment, idx) in form.entity_users"
                            :key="idx"
                            class="grid grid-cols-12 gap-3 items-end p-3 border border-gray-100 dark:border-gray-700 rounded-lg bg-white/60 dark:bg-gray-700/40"
                        >
                            <div class="col-span-1 flex items-center justify-center">
                                <span class="text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                            </div>
                            <div class="col-span-11 md:col-span-3">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Entity</label>
                                <BaseSelect v-model="assignment.entity_id" :options="entities" optionLabel="label" optionValue="value" placeholder="Entity" filter :disabled="mode === 'view'" @update:modelValue="handleEntityChange(assignment)" />
                            </div>
                            <div class="col-span-11 md:col-span-3">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Plant</label>
                                <BaseSelect v-model="assignment.plant_id" :options="plantsCache[assignment.entity_id as number] || []" optionLabel="label" optionValue="value" placeholder="Plant (Optional)" filter :disabled="mode === 'view'" />
                            </div>
                            <div class="col-span-11 md:col-span-4">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Role</label>
                                <BaseSelect v-model="assignment.role_id" :options="userGroups" optionLabel="label" optionValue="value" placeholder="Role" filter :disabled="mode === 'view'" />
                            </div>
                            <div class="col-span-1 flex justify-end">
                                <BaseActionButton v-if="mode === 'edit'" icon="pi pi-times" severity="danger" tooltip="Remove Assignment" @click="removeEntity(idx)" />
                            </div>
                        </div>
                        <p v-if="form.entity_users.length === 0" class="text-xs text-gray-400 italic">No entity access assigned.</p>
                    </div>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="col-span-12 flex justify-end gap-2 pt-4 border-t border-gray-100 dark:border-gray-700 mt-4">
                <BaseButton label="Close" text severity="secondary" @click="$emit('close')" />
                <BaseButton
                    v-if="mode === 'edit'"
                    label="Update User"
                    icon="pi pi-check"
                    :loading="form.processing"
                    @click="submit"
                />
            </div>
        </div>
    </div>
</template>
