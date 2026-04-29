<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const touched = reactive({
    email: false,
});

const submitted = ref(false);
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const clientError = computed(() => {
    if (!form.email) return 'Email is required.';
    if (!emailPattern.test(form.email)) return 'Enter a valid email address.';
    return '';
});

const fieldError = computed(() => {
    return (submitted.value || touched.email) ? (form.errors.email || clientError.value) : '';
});

const submit = () => {
    submitted.value = true;
    if (clientError.value) return;
    
    form.post(route('password.email'), {
        onFinish: () => submitted.value = false,
    });
};
</script>

<template>
    <Head title="Forgot Password" />

    <main class="min-h-screen bg-[#f7f5ef] text-slate-950 transition-colors duration-500 dark:bg-[#080a12] dark:text-white">
        <section class="grid min-h-screen lg:grid-cols-[1.1fr_0.9fr]">
            <!-- Left Side: Immersive Industry Visual -->
            <aside class="relative hidden overflow-hidden lg:block">
                <div class="absolute inset-0 bg-slate-900">
                    <img 
                        src="/onemodo_truck_login.png" 
                        alt="Industrial Logistics" 
                        class="h-full w-full object-cover opacity-60 grayscale hover:grayscale-0 transition-all duration-1000"
                    />
                    <!-- Overlay Gradient -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0a0c14] via-transparent to-transparent"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-950/40 via-transparent to-transparent"></div>
                </div>

                <!-- Branding & Tagline -->
                <div class="relative z-10 flex h-full flex-col justify-between p-12">
                    <div class="flex items-center gap-3">
                        <div class="relative group">
                            <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-teal-400 rounded-xl blur opacity-25 group-hover:opacity-75 transition duration-1000"></div>
                            <div class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-white text-xl font-black tracking-tighter text-slate-950 shadow-2xl">
                                O
                            </div>
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="text-xl font-black tracking-tighter text-white uppercase italic">onemodo<span class="text-indigo-400">.com</span></span>
                            <span class="text-[9px] font-bold tracking-[0.3em] text-slate-400 uppercase">Enterprise Ops</span>
                        </div>
                    </div>

                    <div class="max-w-xl">
                        <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-[10px] font-black uppercase tracking-widest text-teal-300 backdrop-blur-md">
                            <span class="size-2 bg-teal-400 rounded-full animate-pulse"></span>
                            Access Recovery
                        </div>
                        <h1 class="text-6xl font-black leading-[0.95] tracking-tighter text-white">
                            Restore Your <br/>
                            <span class="bg-gradient-to-r from-indigo-400 to-teal-300 bg-clip-text text-transparent italic">Workspace.</span>
                        </h1>
                        <p class="mt-6 max-w-md text-lg font-medium text-slate-300 leading-relaxed">
                            Enterprise-grade security protocols ensure your account remains protected during recovery.
                        </p>
                    </div>

                    <div class="flex items-center gap-8">
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-white">2FA</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Secure Layer</span>
                        </div>
                        <div class="h-8 w-px bg-white/10"></div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-white">ENCRYPTED</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">End-to-End</span>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="relative flex min-h-screen items-center justify-center bg-[#d4d6dc] px-4 py-12 sm:px-6 lg:px-10 dark:bg-[#0a0c14]">
                <!-- Background Decoration -->
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="absolute top-[10%] right-[10%] size-[40%] rounded-full bg-indigo-600/10 blur-[100px]"></div>
                    <div class="absolute bottom-[10%] left-[10%] size-[30%] rounded-full bg-teal-500/5 blur-[80px]"></div>
                </div>

                <div class="relative w-full max-w-[440px] animate-[auth-panel_650ms_cubic-bezier(.16,1,.3,1)_both]">
                    <!-- Mobile Branding -->
                    <div class="mb-10 flex items-center justify-center gap-3 lg:hidden">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-xl font-black text-slate-950">O</div>
                        <span class="text-2xl font-black tracking-tighter text-white italic uppercase">onemodo<span class="text-indigo-400">.com</span></span>
                    </div>

                    <div class="rounded-[2rem] border border-white/80 bg-white/95 p-6 shadow-[0_24px_80px_rgba(15,23,42,.14)] ring-1 ring-slate-900/[0.03] backdrop-blur dark:border-white/10 dark:bg-white/[0.06] dark:shadow-black/40 sm:p-8">
                        <div class="mb-7">
                            <Link :href="route('login')" class="group inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-500 hover:text-indigo-600 transition-colors mb-6">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3.5 transition-transform group-hover:-translate-x-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                                </svg>
                                Back to Sign in
                            </Link>
                            <h2 class="text-3xl font-black tracking-tighter text-slate-950 dark:text-white">
                                Reset Password
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400 font-medium">
                                Forgot your password? No problem. Enter your email address and we'll send you a secure link to reset it.
                            </p>
                        </div>

                        <div v-if="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                            {{ status }}
                        </div>

                        <form class="space-y-6" @submit.prevent="submit" novalidate>
                            <div>
                                <label for="email" class="mb-2 block text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Work Email</label>
                                <div class="group relative">
                                    <div class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-indigo-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <InputText
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="username"
                                        autofocus
                                        class="w-full rounded-2xl !h-10 border bg-white/40 backdrop-blur-sm py-4 !pl-12 pr-4 text-[15px] font-medium text-slate-950 shadow-sm transition-all duration-300 placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:shadow-[0_0_0_4px_rgba(99,102,241,.14)] focus:ring-0 dark:bg-white/[0.07] dark:text-white dark:shadow-none dark:focus:bg-white/[0.09]"
                                        :class="fieldError ? 'border-rose-300 dark:border-rose-300/60' : 'border-slate-200/50 dark:border-white/10'"
                                        placeholder="name@company.com"
                                        @blur="touched.email = true"
                                    />
                                </div>
                                <p v-if="fieldError" class="mt-2 text-xs font-bold text-rose-600 uppercase tracking-tight">{{ fieldError }}</p>
                            </div>

                            <Button
                                type="submit"
                                :disabled="form.processing"
                                :loading="form.processing"
                                label="Send Reset Link"
                                class="w-full rounded-2xl border-0 bg-slate-950 px-5 py-4 text-[15px] font-black text-white shadow-xl shadow-slate-900/20 transition-all duration-200 hover:-translate-y-0.5 hover:bg-slate-800 focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 disabled:translate-y-0 disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 dark:focus:ring-offset-slate-950"
                            />
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
@keyframes auth-panel {
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

