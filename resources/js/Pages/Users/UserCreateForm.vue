<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
const page = usePage();
import { useToast } from 'primevue/usetoast';
import FileUpload from 'primevue/fileupload';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';
import Password from 'primevue/password';

const props = defineProps<{
    selectedPersonnel?: any;
    unlinkedPersonnel?: any[];
    entities: Array<{ label: string; value: number }>;
    plants: Array<{ label: string; value: number; entity_id: number }>;
    userGroups: Array<{ label: string; value: number }>;
}>();

const emit = defineEmits<{ (e: 'created'): void }>();

const toast = useToast();
type EntityAssignment = { entity_id: number | null; plant_id: number | null; role_id: number | null };

const createDefaultAssignment = (): EntityAssignment => ({
    entity_id: null,
    plant_id: null,
    role_id: null,
});

const form = ref({
    personnel_id: null as number | null,
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
        personnel_id: null, username: '', email: '', mobile: '', password: '',
        is_active: true, is_otp_enabled: false, 
        entity_users: [createDefaultAssignment()],
        profile_photo: null, processing: false, errors: {},
    };
};

const personnelOptions = computed(() => (props.unlinkedPersonnel ?? []).map(person => ({
    label: `${person.employee_code} · ${person.first_name} ${person.last_name ?? ''}`, value: person.id,
})));
watch(() => props.selectedPersonnel, person => {
    if (person) form.value.personnel_id = person.id;
});
watch(() => form.value.personnel_id, id => {
    const person = props.unlinkedPersonnel?.find(person => Number(person.id) === Number(id));
    if (!person) return;
    form.value.username = `${person.first_name} ${person.last_name ?? ''}`.trim();
    form.value.email = person.email ?? '';
    form.value.mobile = person.mobile ?? '';
    form.value.entity_users = [{ entity_id: person.entity_id, plant_id: person.plant_id, role_id: null }];
});

const addEntity    = () => {
    const assignment = createDefaultAssignment();
    form.value.entity_users.push(assignment);
};
const removeEntity = (idx: number) => form.value.entity_users.splice(idx, 1);
const onFileUpload = (event: any) => { form.value.profile_photo = event.files[0]; };
const photoPreview = ref('');
watch(() => form.value.profile_photo, (file, _, onCleanup) => {
    photoPreview.value = file ? URL.createObjectURL(file) : '';
    const url = photoPreview.value;
    if (url) onCleanup(() => URL.revokeObjectURL(url));
});

const handlePlantChange = (assignment: EntityAssignment) => {
    if (assignment.plant_id) {
        const selectedPlant = props.plants.find(p => p.value === assignment.plant_id);
        if (selectedPlant) {
            assignment.entity_id = selectedPlant.entity_id;
        }
    } else {
        assignment.entity_id = null;
    }
};

const validate = () => {
    const errors: any = {};
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!form.value.email) {
        errors.email = ['Email is required'];
    } else if (!emailRegex.test(form.value.email)) {
        errors.email = ['Please enter a valid email address'];
    }

    // if (!/^\d{10}$/.test(form.value.mobile)) {
    //     errors.mobile = ['Mobile number must be exactly 10 digits'];
    // }

    if (form.value.password && form.value.password.length < 8) {
        errors.password = ['Password must be at least 8 characters'];
    }

    const assignments = form.value.entity_users.filter(eu => eu.plant_id || eu.role_id);
    if (assignments.length === 0) {
        toast.add({ severity: 'error', summary: 'Required', detail: 'At least one Plant & Role assignment is mandatory' });
        return false;
    }

    const incomplete = assignments.find(eu => !eu.plant_id || !eu.role_id);
    if (incomplete) {
        toast.add({ severity: 'error', summary: 'Required', detail: 'Both Plant and Role must be selected for each assignment' });
        return false;
    }

    const seen = new Set();
    for (const eu of assignments) {
        if (eu.entity_id && eu.plant_id) {
            const key = `${eu.entity_id}-${eu.plant_id}`;
            if (seen.has(key)) {
                toast.add({
                    severity: 'warn',
                    summary: 'Duplicate Assignment',
                    detail: 'Each entity and plant can only be assigned once.',
                    life: 3000
                });
                return false;
            }
            seen.add(key);
        }
    }

    form.value.errors = errors;
    return Object.keys(errors).length === 0;
};

const submit = async () => {
    if (!validate()) return;
    
    form.value.processing = true;
    form.value.errors = {};

    const fd = new FormData();
    if (form.value.personnel_id) fd.append('personnel_id', String(form.value.personnel_id));
    fd.append('username',       form.value.username);
    fd.append('email',          form.value.email);
    fd.append('mobile',         form.value.mobile);
    if (form.value.password) {
        fd.append('password', form.value.password);
    }
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
        toast.add({ severity: 'success', summary: 'Created', detail: 'User created successfully', life: 1500 });
        reset();
        emit('created');
    } catch (err: any) {
        if (err.response?.status === 422) {
            form.value.errors = err.response.data?.errors || {};
            const firstMessage =
                Object.values(form.value.errors)?.flat?.()?.[0] ||
                err.response.data?.message ||
                'Validation error';
            toast.add({ severity: 'error', summary: 'Validation', detail: firstMessage, life: 1500 });
            console.error('users.store validation failed:', err.response.data);
        } else {
            console.error('users.store failed:', err);
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to create user', life: 1500 });
        }
    } finally {
        form.value.processing = false;
    }
};
</script>

<template>
    <BaseCard bodyClass="space-y-5" class="text-sm">
        <template #header>
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300">
                    <i class="pi pi-user-plus" aria-hidden="true" />
                </span>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Add new user</h2>
            </div>
        </template>

        <div class="grid items-start gap-5 lg:grid-cols-3">
            <section class="min-w-0 lg:col-span-2">
                <h3 class="mb-4 font-semibold text-slate-900 dark:text-white">Account details</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <BaseInput v-model="form.username" label="Username" required :error="form.errors.username" />
                    <BaseInput v-model="form.email" label="Email address" required :error="form.errors.email" />
                    <BaseInput v-model="form.mobile" label="Mobile number" :error="form.errors.mobile" maxlength="10" />
                    <div>
                        <label for="create-user-password" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Password (optional)</label>
                        <Password v-model="form.password" inputId="create-user-password" toggleMask fluid
                            autocomplete="new-password" :invalid="!!form.errors.password" />
                        <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password[0] }}</small>
                        <p v-else class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to generate and email a password.</p>
                    </div>
                    <div v-if="personnelOptions.length" class="min-w-0 sm:col-span-2">
                        <BaseSelect v-model="form.personnel_id" :options="personnelOptions" optionLabel="label" optionValue="value"
                            label="Existing personnel" placeholder="Select personnel" :error="form.errors.personnel_id" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Link a personnel record to keep its shift and attendance history.</p>
                    </div>
                </div>
            </section>

            <section class="min-w-0 space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                <h3 class="font-semibold text-slate-900 dark:text-white">Profile and sign-in</h3>
                <div class="flex items-center gap-3">
                    <div class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-950">
                        <img v-if="photoPreview" :src="photoPreview" alt="Selected profile photo" class="h-full w-full object-cover" />
                        <i v-else class="pi pi-user" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <FileUpload mode="basic" name="create_photo[]" accept="image/*" :maxFileSize="1000000"
                            chooseLabel="Choose photo" chooseIcon="pi pi-image" :chooseButtonProps="{ size: 'small', severity: 'secondary', outlined: true }"
                            customUpload auto @select="onFileUpload" />
                        <small v-if="form.errors.profile_photo_path" class="text-red-600">{{ form.errors.profile_photo_path[0] }}</small>
                    </div>
                </div>
                <div class="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                    <div class="flex items-center justify-between gap-3">
                        <label for="create-user-active">Active account</label>
                        <ToggleSwitch inputId="create-user-active" v-model="form.is_active" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <label for="create-user-otp">Mobile app / OTP</label>
                        <ToggleSwitch inputId="create-user-otp" v-model="form.is_otp_enabled" :disabled="!page.props.canManageMobileAccess" />
                    </div>
                </div>
            </section>
        </div>

        <section class="border-t border-slate-200 pt-4 dark:border-slate-700">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-slate-900 dark:text-white">Plant access roles</h3>
                <BaseButton label="Add assignment" icon="pi pi-plus" size="small" severity="secondary" @click="addEntity" />
            </div>
            <div class="space-y-2">
                <div v-for="(assignment, idx) in form.entity_users" :key="idx"
                    class="grid items-end gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] dark:border-slate-700">
                    <BaseSelect v-model="assignment.plant_id" :options="plants" optionLabel="label" optionValue="value"
                        label="Plant" placeholder="Select plant" required :error="form.errors[`entity_users.${idx}.plant_id`]"
                        @update:modelValue="handlePlantChange(assignment)" />
                    <BaseSelect v-model="assignment.role_id" :options="userGroups" optionLabel="label" optionValue="value"
                        label="Role" placeholder="Select role" required :error="form.errors[`entity_users.${idx}.role_id`]" />
                    <BaseActionButton icon="pi pi-trash" severity="danger" tooltip="Remove assignment" :aria-label="`Remove assignment ${idx + 1}`"
                        class="justify-self-end" @click="removeEntity(idx)" />
                </div>
                <p v-if="!form.entity_users.length" class="text-xs text-slate-500">No plant access assigned yet.</p>
            </div>
        </section>

        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
            <BaseButton label="Reset" severity="secondary" variant="text" :disabled="form.processing" @click="reset" />
            <BaseButton label="Save user" icon="pi pi-check" variant="filled" :loading="form.processing" @click="submit" />
        </div>
    </BaseCard>
</template>
