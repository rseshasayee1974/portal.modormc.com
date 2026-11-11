<!--
Author: Cipher
Created: 2026-11-11 12:40:19 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

const toast = useToast();
const issueTypeOptions = [
    { label: 'Report a bug', value: 'bug' },
    { label: 'App is not working / technical issue', value: 'technical_issue' },
    { label: 'I need help or have a difficulty using the app', value: 'usage_difficulty' },
    { label: 'Account or sign-in help', value: 'account' },
    { label: 'Other question or feedback', value: 'other' },
];
const form = useForm({
    name: '',
    email: '',
    issue_type: 'bug',
    subject: '',
    message: '',
    app_version: '',
    device_details: '',
    steps_to_reproduce: '',
});

function submit() {
    form.post('/support', {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            const flash = responsePage.props.flash || {};

            if (flash.support_request_notification_error) {
                toast.add({
                    severity: 'warn',
                    summary: 'Request saved',
                    detail: 'Your request is saved, but the email alert could not be sent. You can also contact noreply@modormc.com directly.',
                    life: 7000,
                });
            } else if (flash.support_request_submitted) {
                toast.add({
                    severity: 'success',
                    summary: 'Request received',
                    detail: 'Your message has been recieved and we will get back to you soon.',
                    life: 5000,
                });
            }

            form.reset();
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            toast.add({
                severity: 'error',
                summary: 'Check your request',
                detail: Array.isArray(firstError) ? firstError[0] : (firstError || 'Please review the form and try again.'),
                life: 6000,
            });
        },
    });
}
</script>

<template>

    <Head title="Support and Contact" />

    <Toast position="top-right" />

    <main
        class="min-h-screen bg-slate-100 px-4 py-10 font-sans text-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:px-6">
        <BaseCard :padded="false"
            class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
            <div class="h-1.5 bg-gradient-to-r from-indigo-600 via-indigo-500 to-teal-400"></div>
            <div class="p-6 sm:p-10">
                <div class="mb-4 flex justify-center">
                    <img src="/assets/modormc_logo_v1.0.png" alt="MODORMC" class="h-14 w-auto object-contain" />
                </div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">MODORMC ·
                    Onemodo Technologies</p>
                <h1 class="mt-3 text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">Support and Contact Us
                </h1>
                <p class="mt-4 text-slate-600 dark:text-slate-300">
                    Report a bug, tell us what is not working, or describe any difficulty using the MODORMC app. Our
                    support team will review your message and follow up using the email address you provide.
                </p>

                <div
                    class="mt-6 rounded-xl border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950/50">
                    <p class="font-medium text-slate-800 dark:text-slate-100">Email support</p>
                    <a class="mt-1 inline-block font-semibold text-indigo-700 underline hover:text-indigo-800 dark:text-indigo-300"
                        href="mailto:noreply@modormc.com">noreply@modormc.com</a>
                </div>

                <h2 class="mt-8 text-xl font-semibold text-slate-900 dark:text-white">Send a support request</h2>
                <form class="mt-4 space-y-5" @submit.prevent="submit">
                    <BaseInput v-model="form.name" label="Your name" autocomplete="name" required maxlength="120"
                        :error="form.errors.name"
                        input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />

                    <BaseSelect v-model="form.issue_type" label="What do you need help with?" required
                        :options="issueTypeOptions" option-label="label" option-value="value" :filter="false"
                        :error="form.errors.issue_type" placeholder="Choose a support topic" />

                    <BaseInput v-model="form.email" label="Email address" type="email" autocomplete="email" required
                        maxlength="255" :error="form.errors.email"
                        input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />

                    <BaseInput v-model="form.subject" label="Subject" required maxlength="160"
                        :error="form.errors.subject"
                        input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <BaseInput v-model="form.app_version" label="App version (optional)"
                            placeholder="For example, 1.2.0" maxlength="80" :error="form.errors.app_version"
                            input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />
                        <BaseInput v-model="form.device_details" label="Device and iOS version (optional)"
                            placeholder="For example, iPhone 15, iOS 18" maxlength="160"
                            :error="form.errors.device_details"
                            input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />
                    </div>

                    <BaseField label="Describe the bug, issue, or difficulty" required :error="form.errors.message"
                        hint="What happened? What were you trying to do? Include any error message you saw.">
                        <template #default="{ inputId }">
                            <textarea :id="inputId" v-model="form.message" rows="5" required minlength="10"
                                maxlength="5000"
                                placeholder="What happened? What were you trying to do? Include any error message you saw."
                                class="w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </template>
                    </BaseField>

                    <BaseField label="Steps to reproduce or other details (optional)"
                        :error="form.errors.steps_to_reproduce">
                        <template #default="{ inputId }">
                            <textarea :id="inputId" v-model="form.steps_to_reproduce" rows="3" maxlength="3000"
                                placeholder="What steps should we follow to see the issue?"
                                class="w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </template>
                    </BaseField>

                    <BaseButton type="submit" variant="filled" severity="primary" :loading="form.processing"
                        :disabled="form.processing" label="Send message"
                        class="!border-0 !bg-indigo-600 !px-5 !py-3 !font-semibold !text-white hover:!bg-indigo-700" />
                </form>

                <p
                    class="mt-8 border-t border-slate-200 pt-5 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    For account and personal data deletion requests, visit <a
                        class="font-medium text-indigo-600 underline hover:text-indigo-700 dark:text-indigo-400"
                        href="/account-deletion">Account and Data Deletion</a>.
                </p>
            </div>
        </BaseCard>
    </main>
</template>
