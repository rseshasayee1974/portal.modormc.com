<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Button from 'primevue/button';
import { CameraIcon, TrashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    username: props.user.username,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('userprofileinformation.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('currentuserphoto.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <form @submit.prevent="updateProfileInformation">
        <div class="flex flex-col gap-8">
            <!-- Header section for form -->
            <div class="flex items-center justify-between border-b border-slate-50 pb-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Profile Details</h3>
                    <p class="text-sm text-slate-500">Update your avatar and personal account information.</p>
                </div>
            </div>

            <!-- Avatar Upload Section -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex flex-col sm:flex-row items-center gap-6 p-6 rounded-2xl bg-slate-50/50 border border-slate-100/50">
                <div class="relative group">
                    <input
                        id="photo"
                        ref="photoInput"
                        type="file"
                        class="hidden"
                        @change="updatePhotoPreview"
                    >
                    
                    <!-- Preview -->
                    <div class="size-24 rounded-full ring-4 ring-white shadow-lg overflow-hidden bg-slate-200 flex-shrink-0">
                        <img v-if="!photoPreview" :src="user.profile_photo_url" :alt="user.name" class="size-full object-cover">
                        <div v-else class="size-full bg-cover bg-center" :style="'background-image: url(\'' + photoPreview + '\');'" />
                    </div>

                    <!-- Overlay Button -->
                    <button 
                        type="button"
                        @click="selectNewPhoto"
                        class="absolute inset-0 bg-black/40 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-full"
                    >
                        <CameraIcon class="size-6" />
                    </button>
                </div>

                <div class="flex flex-col gap-2 flex-1 text-center sm:text-left">
                    <span class="text-sm font-bold text-slate-700">Profile Photo</span>
                    <p class="text-xs text-slate-400">JPG, GIF or PNG. Max size of 2MB.</p>
                    <div class="flex items-center gap-2 mt-1 justify-center sm:justify-start">
                        <Button 
                            label="Change Photo" 
                            size="small" 
                            outlined 
                            class="!text-[11px] !py-1"
                            @click="selectNewPhoto"
                        />
                        <Button 
                            v-if="user.profile_photo_path"
                            label="Remove" 
                            severity="danger"
                            size="small" 
                            text
                            class="!text-[11px] !py-1"
                            @click="deletePhoto"
                        />
                    </div>
                </div>
            </div>

            <!-- Main Form Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-1">
                    <BaseInput 
                        v-model="form.username" 
                        label="Display Name" 
                        placeholder="e.g. JohnDoe"
                        required
                        :error="form.errors.username"
                    />
                </div>

                <div class="space-y-1">
                    <BaseInput 
                        v-model="form.email" 
                        type="email"
                        label="Email Address" 
                        placeholder="john@example.com"
                        required
                        :error="form.errors.email"
                    />
                    
                    <!-- Email Verification -->
                    <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null" class="mt-2">
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-amber-50 border border-amber-100">
                             <div class="text-[11px] text-amber-700">
                                Email unverified.
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="font-bold underline ml-1 hover:text-amber-900 transition-colors"
                                    @click.prevent="sendEmailVerification"
                                >
                                    Resend link
                                </Link>
                             </div>
                        </div>
                        <div v-show="verificationLinkSent" class="mt-2 text-[11px] font-bold text-emerald-600 flex items-center gap-1">
                            <CheckCircleIcon class="size-3" />
                            Verification link sent!
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Area -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-50 mt-4">
                <ActionMessage :on="form.recentlySuccessful" class="text-emerald-600 font-bold text-sm">
                    Changes saved successfully
                </ActionMessage>

                <Button 
                    type="submit"
                    label="Update Profile" 
                    icon="pi pi-check"
                    class="shadow-lg shadow-indigo-100"
                    :loading="form.processing"
                    :disabled="form.processing"
                />
            </div>
        </div>
    </form>
</template>


