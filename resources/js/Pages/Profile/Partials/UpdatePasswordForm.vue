<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Button from 'primevue/button';
import { LockClosedIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('userpassword.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
            }

            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <form @submit.prevent="updatePassword">
        <div class="flex flex-col gap-8">
            <!-- Header section for form -->
            <div class="flex items-center justify-between border-b border-slate-50 pb-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Security Credentials</h3>
                    <p class="text-sm text-slate-500">Ensure your account is using a long, random password to stay secure.</p>
                </div>
                <div class="size-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                    <ShieldCheckIcon class="size-5" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 max-w-md">
                <div class="space-y-1">
                    <BaseInput 
                        v-model="form.current_password" 
                        type="password"
                        label="Current Password" 
                        placeholder="••••••••"
                        required
                        :error="form.errors.current_password"
                    />
                </div>

                <div class="space-y-1">
                    <BaseInput 
                        v-model="form.password" 
                        type="password"
                        label="New Password" 
                        placeholder="••••••••"
                        required
                        :error="form.errors.password"
                    />
                </div>

                <div class="space-y-1">
                    <BaseInput 
                        v-model="form.password_confirmation" 
                        type="password"
                        label="Confirm New Password" 
                        placeholder="••••••••"
                        required
                        :error="form.errors.password_confirmation"
                    />
                </div>
            </div>

            <!-- Action Area -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-50 mt-4">
                <ActionMessage :on="form.recentlySuccessful" class="text-emerald-600 font-bold text-sm">
                    Password updated successfully
                </ActionMessage>

                <Button 
                    type="submit"
                    label="Change Password" 
                    icon="pi pi-lock"
                    severity="warning"
                    class="shadow-lg shadow-amber-100"
                    :loading="form.processing"
                    :disabled="form.processing"
                />
            </div>
        </div>
    </form>
</template>


