<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';

const props = defineProps({
    status: String,
    email: String,
});

const page = usePage();
const loading = ref(false);
const message = ref('');
const error = ref('');

const userEmail = computed(() => props.email || page.props.auth?.user?.email || localStorage.getItem('pending_verification_email') || '');
const verificationLinkSent = computed(() => props.status === 'verificationlinksent' || Boolean(message.value));

const resendEmail = async () => {
    if (loading.value) {
        return;
    }

    loading.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await axios.post('/api/resend-verification-email', {
            email: userEmail.value,
        });

        message.value = data.message || 'A fresh verification email has been sent.';
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to send the verification email right now.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Email Verification" />

    <main class="min-h-screen bg-[#f7f5ef] text-slate-950 transition-colors duration-500 dark:bg-[#080a12] dark:text-white">
        <section class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
            <aside class="relative hidden overflow-hidden bg-slate-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_24%_18%,rgba(16,185,129,.34),transparent_28%),radial-gradient(circle_at_84%_72%,rgba(99,102,241,.28),transparent_32%),linear-gradient(135deg,#020617,#111827_56%,#0f172a)]" />
                <div class="absolute inset-0 opacity-[0.13] bg-[linear-gradient(rgba(255,255,255,.2)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.2)_1px,transparent_1px)] bg-[size:52px_52px]" />

                <div class="relative z-10 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-sm font-black tracking-tight text-slate-950">MM</div>
                    <div>
                        <p class="text-sm font-semibold">Modo Mines</p>
                        <p class="text-xs text-white/50">Account Activation</p>
                    </div>
                </div>

                <div class="relative z-10 max-w-xl">
                    <div class="mb-8 flex h-24 w-24 items-center justify-center rounded-[2rem] border border-white/10 bg-white/10 backdrop-blur-xl">
                        <i class="pi pi-inbox text-4xl text-emerald-200" aria-hidden="true" />
                    </div>
                    <h1 class="text-5xl font-semibold leading-[1.02] tracking-[-0.045em]">
                        Your secure workspace is waiting.
                    </h1>
                    <p class="mt-5 max-w-md text-base leading-7 text-slate-300">
                        Confirm your email to unlock role-based access, plant context and ERP workflows.
                    </p>
                </div>

                <div class="relative z-10 rounded-[2rem] border border-white/10 bg-white/[0.08] p-5 backdrop-blur-xl">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-300/15 text-emerald-200">
                            <i class="pi pi-check" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">Verification link sent</p>
                            <p class="mt-1 text-xs text-white/50">Usually arrives in less than a minute.</p>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-10">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_8%,rgba(16,185,129,.18),transparent_30%),radial-gradient(circle_at_8%_88%,rgba(99,102,241,.14),transparent_34%)]" />

                <div class="relative w-full max-w-[480px] animate-[mail-panel_650ms_cubic-bezier(.16,1,.3,1)_both]">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black tracking-tight text-white dark:bg-white dark:text-slate-950">MM</div>
                        <div>
                            <p class="text-sm font-semibold">Modo Mines</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Account Activation</p>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/80 bg-white/95 p-6 shadow-[0_24px_80px_rgba(15,23,42,.14)] ring-1 ring-slate-900/[0.03] backdrop-blur dark:border-white/10 dark:bg-white/[0.06] dark:shadow-black/40 sm:p-8">
                        <div class="mb-7">
                            <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-[1.4rem] bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                <i class="pi pi-envelope text-2xl" aria-hidden="true" />
                            </div>
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Email sent</p>
                            <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 dark:text-white">
                                Check your inbox
                            </h1>
                            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                We sent a verification link to
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ userEmail || 'your email address' }}</span>.
                                Open the link to activate your account.
                            </p>
                        </div>

                        <div v-if="verificationLinkSent" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200" role="status">
                            {{ message || 'A new verification link has been sent to your email address.' }}
                        </div>

                        <div v-if="error" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
                            {{ error }}
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <a
                                href="https://mail.google.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3.5 text-sm font-semibold text-white shadow-xl shadow-slate-900/20 transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 dark:focus:ring-offset-slate-950"
                                aria-label="Open Gmail"
                            >
                                <i class="pi pi-google mr-2" aria-hidden="true" />
                                Open Gmail
                            </a>
                            <a
                                href="mailto:"
                                class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 dark:border-white/10 dark:bg-white/[0.06] dark:text-slate-200 dark:hover:bg-white/[0.10] dark:focus:ring-offset-slate-950"
                                aria-label="Open mail app"
                            >
                                <i class="pi pi-send mr-2" aria-hidden="true" />
                                Mail App
                            </a>
                        </div>

                        <div class="mt-7 rounded-2xl bg-slate-50 p-4 dark:bg-white/[0.05]">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Did not receive it?</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                Check spam or resend a fresh verification link.
                            </p>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                                <Button
                                    type="button"
                                    :loading="loading"
                                    label="Resend Email"
                                    class="rounded-xl border-0 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 focus:ring-2 focus:ring-emerald-400"
                                    aria-label="Resend verification email"
                                    @click="resendEmail"
                                />
                                <Link
                                    :href="route('profile.show')"
                                    class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200/70 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-400 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                                >
                                    Change email
                                </Link>
                            </div>
                        </div>

                        <div class="mt-7 border-t border-slate-200 pt-6 text-center dark:border-white/10">
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-sm font-semibold text-slate-500 transition hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus:ring-offset-slate-950"
                            >
                                Back to login
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
@keyframes mail-panel {
    from {
        opacity: 0;
        transform: translateY(22px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
