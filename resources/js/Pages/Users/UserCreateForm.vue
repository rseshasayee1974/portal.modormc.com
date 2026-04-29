<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import FileUpload from 'primevue/fileupload';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';

const props = defineProps<{
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number }>;
    userGroups: Array<{ label: string; value: number }>;
}>();

const emit = defineEmits<{ (e: 'created'): void }>();

const toast = useToast();
type EntityAssignment = { entity_id: number | null; plant_id: number | null; role_id: number | null };

const getDefaultEntityId = () => props.entities[0]?.value ?? null;
const createDefaultAssignment = (): EntityAssignment => ({
    entity_id: getDefaultEntityId(),
    plant_id: null,
    role_id: null,
});

const form = ref({
    username: '',
    email: '',
    mobile: '',
    password: '',
    is_active: true,
    is_otp_enabled: false,
    entity_users: [createDefaultAssignment()] as EntityAssignment[],
    profile_photo: null as File | null,
    processing: false,
    errors: {} as any,
});

const reset = () => {
    form.value = {
        username: '', email: '', mobile: '', password: '',
        is_active: true, is_otp_enabled: false, 
        entity_users: [createDefaultAssignment()],
        profile_photo: null, processing: false, errors: {},
    };
    form.value.entity_users.forEach((assignment) => {
        void applyDefaultPlant(assignment);
    });
};

const addEntity    = () => {
    const assignment = createDefaultAssignment();
    form.value.entity_users.push(assignment);
    void applyDefaultPlant(assignment);
};
const removeEntity = (idx: number) => form.value.entity_users.splice(idx, 1);
const onFileUpload = (event: any) => { form.value.profile_photo = event.files[0]; };

const plantsCache = ref<Record<number, any[]>>({});
const fetchPlants = async (entityId: number | null) => {
    if (!entityId) return [];
    if (plantsCache.value[entityId]) return plantsCache.value[entityId];
    try {
        const { data } = await axios.get(route('plants.by-entity'), { params: { entity_id: entityId } });
        plantsCache.value[entityId] = data;
        return data;
    } catch (err) {
        console.error('Failed to fetch plants:', err);
        return [];
    }
};

const applyDefaultPlant = async (assignment: EntityAssignment) => {
    if (!assignment.entity_id) return;
    const plants = await fetchPlants(assignment.entity_id);
    assignment.plant_id = plants[0]?.value ?? null;
};

const handleEntityChange = (assignment: EntityAssignment) => {
    assignment.plant_id = null;
    void applyDefaultPlant(assignment);
};

onMounted(() => {
    form.value.entity_users.forEach((assignment) => {
        void applyDefaultPlant(assignment);
    });
});

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

    if (!form.value.password) {
        errors.password = ['Password is required'];
    } else if (form.value.password.length < 8) {
        errors.password = ['Password must be at least 8 characters'];
    }

    const assignments = form.value.entity_users.filter(eu => eu.entity_id || eu.role_id);
    if (assignments.length === 0) {
        toast.add({ severity: 'error', summary: 'Required', detail: 'At least one Entity & Role assignment is mandatory' });
        return false;
    }

    const incomplete = assignments.find(eu => !eu.entity_id || !eu.role_id);
    if (incomplete) {
        toast.add({ severity: 'error', summary: 'Required', detail: 'Both Entity and Role must be selected for each assignment' });
        return false;
    }

    form.value.errors = errors;
    return Object.keys(errors).length === 0;
};

const submit = async () => {
    if (!validate()) return;
    
    form.value.processing = true;
    form.value.errors = {};

    const fd = new FormData();
    fd.append('username',       form.value.username);
    fd.append('email',          form.value.email);
    fd.append('mobile',         form.value.mobile);
    fd.append('password', form.value.password);
    fd.append('is_active',      form.value.is_active      ? '1' : '0');
    fd.append('is_otp_enabled', form.value.is_otp_enabled ? '1' : '0');

    // Only send complete assignments
    form.value.entity_users
        .filter(eu => eu.entity_id && eu.role_id)
        .forEach((eu, i) => {
            fd.append(`entity_users[${i}][entity_id]`, eu.entity_id!.toString());
            if (eu.plant_id)  fd.append(`entity_users[${i}][plant_id]`,  eu.plant_id.toString());
            fd.append(`entity_users[${i}][role_id]`,   eu.role_id!.toString());
        });
    if (form.value.profile_photo) fd.append('profile_photo_path', form.value.profile_photo);

    try {
        await axios.post(route('users.store'), fd);
        toast.add({ severity: 'success', summary: 'Created', detail: 'User created successfully', life: 5000 });
        reset();
        emit('created');
    } catch (err: any) {
        if (err.response?.status === 422) {
            form.value.errors = err.response.data?.errors || {};
            const firstMessage =
                Object.values(form.value.errors)?.flat?.()?.[0] ||
                err.response.data?.message ||
                'Validation error';
            toast.add({ severity: 'error', summary: 'Validation', detail: firstMessage, life: 5000 });
            console.error('users.store validation failed:', err.response.data);
        } else {
            console.error('users.store failed:', err);
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to create user', life: 5000 });
        }
    } finally {
        form.value.processing = false;
    }
};
</script>

<template>
    <BaseCard class="text-sm">
        <template #header>
            <div class="flex items-center gap-2">
                <i class="pi pi-user-plus text-indigo-500" />
                <span class="text-md font-semibold uppercase  text-gray-800 dark:text-gray-100">
                    Add New User
                </span>
            </div>
        </template>

        <div class="grid grid-cols-12 gap-4 py-4">

           

            <!-- Basic Fields -->
            <div class="col-span-12 md:col-span-3">
                <BaseInput v-model="form.username" label="Username" :error="form.errors.username" />
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseInput v-model="form.email" label="Email Address" :error="form.errors.email" />
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseInput v-model="form.mobile" label="Mobile Number" :error="form.errors.mobile" maxlength="10" />
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseInput v-model="form.password" type="password" label="Password" :error="form.errors.password" />
            </div>
          
            <!-- Toggles -->
            <div class="col-span-12 md:col-span-2 flex flex-col gap-2">
                <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Is Active</label>
                <div class="flex items-center gap-2 mt-2">
                    <ToggleSwitch v-model="form.is_active" />
                    <!-- <span class="text-sm">Active</span> -->
                </div>
            </div>
            <div class="col-span-12 md:col-span-1 flex flex-col gap-2">
                <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Enable OTP</label>
                <div class="flex items-center gap-2 mt-2">
                    <ToggleSwitch v-model="form.is_otp_enabled" />
                    <!-- <span class="text-sm">Enable OTP</span> -->
                </div>
            </div>
 <!-- Profile Photo -->
            <div class="col-span-12 md:col-span-3">
                <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Profile Photo</label>
                <FileUpload mode="basic" name="create_photo[]" accept="image/*" :maxFileSize="1000000" @select="onFileUpload" auto />
            </div>
            <!-- Entity Access Section -->
            <div class="col-span-12">
                <div class="flex items-center justify-between mb-3 border-b border-gray-100 dark:border-gray-700 pb-2">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Entity & Plant Access</span>
                    <BaseButton label="Add Assignment" icon="pi pi-plus" text  @click="addEntity" />
                </div>
                <div class="flex flex-col gap-3">
                    <div
                        v-for="(assignment, idx) in form.entity_users"
                        :key="idx"
                        class="grid grid-cols-12 gap-3 items-end p-3 border border-gray-100 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-700/30"
                    >
                        <div class="col-span-1 flex items-center justify-center">
                            <span class="text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                        </div>
                        <div class="col-span-11 md:col-span-3">
                            <label class="text-[10px] font-bold uppercase text-gray-400">Entity</label>
                            <BaseSelect v-model="assignment.entity_id" :options="entities" optionLabel="label" optionValue="value" placeholder="Entity" filter @update:modelValue="handleEntityChange(assignment)" />
                        </div>
                        <div class="col-span-11 md:col-span-3">
                            <label class="text-[10px] font-bold uppercase text-gray-400">Plant</label>
                            <BaseSelect v-model="assignment.plant_id" :options="plantsCache[assignment.entity_id as number] || []" optionLabel="label" optionValue="value" placeholder="Plant (Optional)" filter />
                        </div>
                        <div class="col-span-11 md:col-span-4">
                            <label class="text-[10px] font-bold uppercase text-gray-400">Role</label>
                            <BaseSelect v-model="assignment.role_id" :options="userGroups" optionLabel="label" optionValue="value" placeholder="Role" filter />
                        </div>
                        <div class="col-span-1 flex justify-end">
                            <BaseActionButton icon="pi pi-times" severity="danger" tooltip="Remove Assignment" @click="removeEntity(idx)" />
                        </div>
                    </div>
                    <p v-if="form.entity_users.length === 0" class="text-xs text-gray-400 italic">No entity access assigned yet.</p>
                </div>
            </div>

        </div>

        <!-- Footer Actions -->
        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700 mt-2">
            <BaseButton label="Reset" text severity="secondary" @click="reset" />
            <BaseButton label="Save User" icon="pi pi-check" :loading="form.processing" @click="submit" />
        </div>
    </BaseCard>
</template>
