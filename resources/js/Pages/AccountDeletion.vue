<!--
Author: Cipher
Created: 2026-11-10 10:08:02 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';

const page = usePage();
const showConfirmation = ref(false);
const requestSubmitted = computed(() => Boolean(page.props.flash?.deletion_request_submitted));
const form = useForm({
    account_email: '',
    account_identifier: '',
    additional_details: '',
    confirm_deletion: false,
});

watch(requestSubmitted, (submitted) => {
    if (submitted) {
        showConfirmation.value = true;
        form.reset();
    }
}, { immediate: true });

function submitRequest() {
    form.post('/account-deletion', { preserveScroll: true });
}
</script>

<template>

    <Head title="Account Deletion" />

    <main
        class="min-h-screen bg-slate-100 px-4 py-10 font-sans text-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:px-6">
        <BaseCard
            class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
            <div class="h-1.5 bg-gradient-to-r from-indigo-600 via-indigo-500 to-teal-400"></div>
            <div class="p-6 sm:p-10">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">MODORMC
                    · Onemodo Technologies</p>
                <h1 class="mt-3 text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">Account and Data Deletion
                </h1>
                <p class="mt-4 text-slate-600 dark:text-slate-300">This page lets MODORMC users request deletion of
                    their app account and associated personal data from Onemodo Technologies Pvt. Ltd. You do not need
                    to sign in or reinstall the app to submit a request.</p>

                <h2 class="mt-8 text-xl font-semibold">Submit an account deletion request</h2>
                <form class="mt-4 space-y-5" @submit.prevent="submitRequest">
                    <BaseInput v-model="form.account_email" label="Email address associated with your account"
                        type="email" autocomplete="email" required :error="form.errors.account_email"
                        input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />

                    <BaseInput v-model="form.account_identifier" label="Username or other account identifier (optional)"
                        maxlength="255" :error="form.errors.account_identifier"
                        input-class="!rounded-lg !border-slate-300 !bg-white !text-slate-900 !shadow-sm dark:!border-slate-700 dark:!bg-slate-950 dark:!text-white" />

                    <BaseField label="Additional details (optional)" :error="form.errors.additional_details">
                        <template #default="{ inputId }">
                            <textarea :id="inputId" v-model="form.additional_details" rows="3" maxlength="2000"
                                class="w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </template>
                    </BaseField>

                    <label class="flex items-start gap-3">
                        <input v-model="form.confirm_deletion" type="checkbox" required
                            class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700" />
                        <span class="text-slate-700 dark:text-slate-300">I request deletion of my MODORMC account
                            and associated personal data.</span>
                    </label>
                    <p v-if="form.errors.confirm_deletion" class="-mt-4 text-sm text-rose-700 dark:text-rose-300">{{
                        form.errors.confirm_deletion }}</p>

                    <BaseButton type="submit" variant="filled" severity="primary" :loading="form.processing"
                        :disabled="form.processing" label="Submit deletion request"
                        class="!border-0 !bg-indigo-600 !px-5 !py-3 !font-semibold !text-white hover:!bg-indigo-700" />
                </form>

                <h2 class="mt-8 text-xl font-semibold text-slate-900 dark:text-white">What happens next</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300">We will verify the request to protect account
                    security. After verification, we will delete the account and associated personal data, except data
                    we must retain for legal, security, fraud prevention, or accounting purposes. Any retained data will
                    be restricted and kept only for the applicable retention period. Account deletion is different from
                    simply deactivating an account.</p>

                <h2 class="mt-8 text-xl font-semibold text-slate-900 dark:text-white">Need help?</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300">For questions, contact <a
                        class="font-medium text-indigo-600 underline hover:text-indigo-700 dark:text-indigo-400"
                        href="mailto:sayee@onemodo.com">sayee@onemodo.com</a>. See the <a
                        class="font-medium text-indigo-600 underline hover:text-indigo-700 dark:text-indigo-400"
                        href="/privacy-policy#account-deletion">Account deletion and data requests</a> section of our
                    Privacy Policy for more information.</p>
            </div>
        </BaseCard>

        <Teleport to="body">
            <div v-if="showConfirmation"
                class="fixed inset-0 z-[9999] flex min-h-screen items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
                @keydown.esc="showConfirmation = false" @click.self="showConfirmation = false">

                <section role="dialog" aria-modal="true" aria-labelledby="confirmation-title"
                    aria-describedby="confirmation-message"
                    class="w-full max-w-md rounded-2xl border border-teal-200 bg-white p-6 text-center shadow-2xl dark:border-teal-900 dark:bg-slate-900 sm:p-8">

                    <!-- Icon -->
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300"
                        aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12 4 4L19 6" />
                        </svg>
                    </div>

                    <!-- Text -->
                    <h2 id="confirmation-title" class="mt-5 text-xl font-bold text-slate-900 dark:text-white">
                        Request received
                    </h2>
                    <p id="confirmation-message" role="status"
                        class="mt-3 leading-6 text-slate-600 dark:text-slate-300">
                        Your MODORMC account deletion request has been received. Our team will verify the request
                        and contact you if more information is needed.
                    </p>

                    <!-- Button -->
                    <BaseButton type="button" label="Close" variant="filled" severity="primary"
                        class="mt-6 !w-full !border-0 !bg-indigo-600 !px-8 !py-3 !font-semibold !text-white hover:!bg-indigo-700 sm:!w-auto"
                        @click="showConfirmation = false" />

                </section>
            </div>
        </Teleport>
    </main>
</template>
