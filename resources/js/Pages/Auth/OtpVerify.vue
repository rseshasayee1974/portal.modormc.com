<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({
    email: String,
    mobile: String,
});

const digits = ref(['', '', '', '', '', '']);
const inputRefs = ref([]);
const email = ref(props.email || localStorage.getItem('pending_verification_email') || '');
const loading = ref(false);
const resending = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const cooldown = ref(30);
let timer = null;

const otpValue = computed(() => digits.value.join(''));
const isComplete = computed(() => otpValue.value.length === 6);
const destination = computed(() => props.mobile || email.value || 'your email');

const startTimer = () => {
    if (timer) {
        clearInterval(timer);
    }

    cooldown.value = 30;
    timer = setInterval(() => {
        cooldown.value -= 1;

        if (cooldown.value <= 0 && timer) {
            clearInterval(timer);
            timer = null;
        }
    }, 1000);
};

onMounted(() => {
    startTimer();
    nextTick(() => inputRefs.value[0]?.focus());
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const setInputRef = (element, index) => {
    if (element) {
        inputRefs.value[index] = element.$el?.querySelector('input') || element;
    }
};

const resetOtp = () => {
    digits.value = ['', '', '', '', '', ''];
    nextTick(() => inputRefs.value[0]?.focus());
};

const onDigitInput = (index, event) => {
    errorMessage.value = '';
    const value = event.target.value.replace(/\D/g, '').slice(-1);
    digits.value[index] = value;

    if (value && index < 5) {
        inputRefs.value[index + 1]?.focus();
    }
};

const onDigitKeydown = (index, event) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputRefs.value[index - 1]?.focus();
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        inputRefs.value[index - 1]?.focus();
    }

    if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault();
        inputRefs.value[index + 1]?.focus();
    }
};

const onPaste = (event) => {
    event.preventDefault();
    const pasted = event.clipboardData?.getData('text')?.replace(/\D/g, '').slice(0, 6) || '';

    pasted.split('').forEach((digit, index) => {
        digits.value[index] = digit;
    });

    nextTick(() => {
        inputRefs.value[Math.min(pasted.length, 5)]?.focus();
    });
};

const verifyOtp = async () => {
    errorMessage.value = '';
    successMessage.value = '';

    if (!email.value) {
        errorMessage.value = 'Enter your email before verifying the code.';
        return;
    }

    if (!isComplete.value || loading.value) {
        return;
    }

    loading.value = true;

    try {
        const { data } = await axios.post('/api/verify-otp', {
            email: email.value,
            otp: otpValue.value,
        });

        if (data.token) {
            localStorage.setItem('auth_token', data.token);
            localStorage.removeItem('pending_verification_email');
            axios.defaults.headers.common.Authorization = `Bearer ${data.token}`;
        }

        successMessage.value = data.message || 'Email verified successfully.';
        window.setTimeout(() => {
            window.location.href = data.redirect_to || '/dashboard';
        }, 450);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || error.response?.data?.errors?.otp?.[0] || 'The verification code is invalid or expired.';
        resetOtp();
    } finally {
        loading.value = false;
    }
};

const resendOtp = async () => {
    if (cooldown.value > 0 || resending.value || !email.value) {
        return;
    }

    resending.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const { data } = await axios.post('/api/send-otp', { email: email.value });
        localStorage.setItem('pending_verification_email', email.value);
        successMessage.value = data.message || 'A new verification code has been sent.';
        startTimer();
        resetOtp();
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Unable to resend the code. Please try again.';
    } finally {
        resending.value = false;
    }
};
</script>

<template>
    <Head title="Verify your email" />

    <main class="min-h-screen bg-[#f7f5ef] text-slate-950 transition-colors duration-500 dark:bg-[#080a12] dark:text-white">
        <section class="grid min-h-screen lg:grid-cols-[0.9fr_1.1fr]">
            <aside class="relative hidden overflow-hidden bg-slate-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_24%_22%,rgba(20,184,166,.34),transparent_30%),radial-gradient(circle_at_88%_72%,rgba(99,102,241,.28),transparent_32%),linear-gradient(140deg,#020617,#111827_56%,#0f172a)]" />
                <div class="absolute inset-0 opacity-[0.13] bg-[linear-gradient(rgba(255,255,255,.2)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.2)_1px,transparent_1px)] bg-[size:48px_48px]" />

                <div class="relative z-10 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-sm font-black tracking-tight text-slate-950">MM</div>
                    <div>
                        <p class="text-sm font-semibold">Modo Mines</p>
                        <p class="text-xs text-white/50">Identity Checkpoint</p>
                    </div>
                </div>

                <div class="relative z-10">
                    <div class="mb-8 flex h-24 w-24 items-center justify-center rounded-[2rem] border border-white/10 bg-white/10 backdrop-blur-xl">
                        <i class="pi pi-shield text-4xl text-teal-200" aria-hidden="true" />
                    </div>
                    <h1 class="max-w-lg text-5xl font-semibold leading-[1.02] tracking-[-0.045em]">
                        One quick check before your workspace opens.
                    </h1>
                    <p class="mt-5 max-w-md text-base leading-7 text-slate-300">
                        Verification keeps plant operations, billing and master data protected from unauthorized access.
                    </p>
                </div>

                <div class="relative z-10 grid grid-cols-3 gap-3">
                    <div class="rounded-3xl border border-white/10 bg-white/[0.08] p-4 backdrop-blur">
                        <p class="text-xs text-white/50">Step</p>
                        <p class="mt-2 text-2xl font-semibold">2/2</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-white/[0.08] p-4 backdrop-blur">
                        <p class="text-xs text-white/50">Code</p>
                        <p class="mt-2 text-2xl font-semibold">6</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-white/[0.08] p-4 backdrop-blur">
                        <p class="text-xs text-white/50">Expiry</p>
                        <p class="mt-2 text-2xl font-semibold">5m</p>
                    </div>
                </div>
            </aside>

            <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-10">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_10%,rgba(20,184,166,.18),transparent_30%),radial-gradient(circle_at_8%_88%,rgba(99,102,241,.14),transparent_34%)]" />

                <div class="relative w-full max-w-[500px] animate-[verify-panel_650ms_cubic-bezier(.16,1,.3,1)_both]">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black tracking-tight text-white dark:bg-white dark:text-slate-950">MM</div>
                        <div>
                            <p class="text-sm font-semibold">Modo Mines</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Identity Checkpoint</p>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/80 bg-white/95 p-6 shadow-[0_24px_80px_rgba(15,23,42,.14)] ring-1 ring-slate-900/[0.03] backdrop-blur dark:border-white/10 dark:bg-white/[0.06] dark:shadow-black/40 sm:p-8">
                        <div class="mb-7">
                            <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-200">
                                <i class="pi pi-key text-xl" aria-hidden="true" />
                            </div>
                            <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">Secure verification</p>
                            <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 dark:text-white">
                                Verify your email
                            </h1>
                            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Enter the 6-digit code sent to <span class="font-semibold text-slate-800 dark:text-slate-100">{{ destination }}</span>.
                            </p>
                        </div>

                        <label for="verification-email" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-100">Email</label>
                        <InputText
                            id="verification-email"
                            v-model="email"
                            type="email"
                            autocomplete="email"
                            aria-label="Verification email"
                            class="mb-5 w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3.5 text-[15px] text-slate-950 shadow-inner shadow-slate-200/40 transition-all duration-200 placeholder:text-slate-400 focus:border-teal-400 focus:bg-white focus:shadow-[0_0_0_4px_rgba(20,184,166,.14)] focus:ring-0 dark:border-white/10 dark:bg-white/[0.07] dark:text-white dark:shadow-none"
                            placeholder="name@company.com"
                        />

                        <div v-if="errorMessage" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
                            {{ errorMessage }}
                        </div>

                        <div v-if="successMessage" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200" role="status">
                            {{ successMessage }}
                        </div>

                        <div class="mb-7 grid grid-cols-6 gap-2 sm:gap-3" aria-label="One-time password" @paste="onPaste">
                            <input
                                v-for="(digit, index) in digits"
                                :key="index"
                                :ref="(element) => setInputRef(element, index)"
                                v-model="digits[index]"
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                autocomplete="one-time-code"
                                :aria-label="`OTP digit ${index + 1}`"
                                :disabled="loading"
                                class="h-[52px] min-w-0 rounded-2xl border bg-slate-50/80 text-center text-xl font-semibold text-slate-950 shadow-inner shadow-slate-200/40 outline-none transition-all duration-200 focus:border-teal-400 focus:bg-white focus:shadow-[0_0_0_4px_rgba(20,184,166,.14)] disabled:opacity-60 dark:bg-white/[0.07] dark:text-white dark:shadow-none sm:h-14"
                                :class="errorMessage ? 'border-rose-300 dark:border-rose-300/60' : digit ? 'border-teal-400 dark:border-teal-300/70' : 'border-slate-200 dark:border-white/10'"
                                @input="onDigitInput(index, $event)"
                                @keydown="onDigitKeydown(index, $event)"
                            />
                        </div>

                        <Button
                            type="button"
                            :disabled="!isComplete || loading"
                            :loading="loading"
                            label="Verify and continue"
                            aria-label="Verify OTP"
                            class="w-full rounded-2xl border-0 bg-slate-950 px-5 py-3.5 text-[15px] font-semibold text-white shadow-xl shadow-slate-900/20 transition-all duration-200 hover:-translate-y-0.5 hover:bg-slate-800 focus:ring-2 focus:ring-teal-400 focus:ring-offset-2 disabled:translate-y-0 disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 dark:focus:ring-offset-slate-950"
                            @click="verifyOtp"
                        />

                        <div class="mt-6 rounded-2xl bg-slate-50 px-4 py-3 text-center text-sm text-slate-600 dark:bg-white/[0.05] dark:text-slate-300">
                            <span v-if="cooldown > 0">You can resend in <span class="font-mono font-semibold text-slate-950 dark:text-white">{{ cooldown }}s</span></span>
                            <Button v-else type="button" text :loading="resending" label="Resend Code" class="px-2 py-1 text-sm font-semibold text-teal-700 hover:text-teal-600 dark:text-teal-300" @click="resendOtp" />
                        </div>

                        <div class="mt-7 text-center">
                            <Link href="/login" class="text-sm font-semibold text-slate-500 transition hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus:ring-offset-slate-950">
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
@keyframes verify-panel {
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
