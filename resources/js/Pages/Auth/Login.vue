<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = reactive({
    email: '',
    password: '',
    remember: true,
});

const touched = reactive({
    email: false,
    password: false,
});

const serverErrors = ref({});
const authError = ref('');
const loading = ref(false);
const submitted = ref(false);

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const clientErrors = computed(() => ({
    email: !form.email
        ? 'Email is required.'
        : !emailPattern.test(form.email)
            ? 'Enter a valid email address.'
            : '',
    password: !form.password ? 'Password is required.' : '',
}));

const fieldError = (field) => {
    const error = serverErrors.value[field];

    if (Array.isArray(error) && error.length) {
        return error[0];
    }

    if (typeof error === 'string') {
        return error;
    }

    return (submitted.value || touched[field]) ? clientErrors.value[field] : '';
};

const isValid = computed(() => !clientErrors.value.email && !clientErrors.value.password);

const quickFillDemo = () => {
    form.email = 'demo@modomines.com';
    form.password = 'password';
    form.remember = true;
    serverErrors.value = {};
    authError.value = '';
};

const submit = async () => {
    submitted.value = true;
    serverErrors.value = {};
    authError.value = '';

    if (!isValid.value || loading.value) {
        return;
    }

    router.post(route('login'), {
        email: form.email,
        password: form.password,
        remember: form.remember,
    }, {
        preserveScroll: true,
        onStart: () => {
            loading.value = true;
        },
        onError: (errors) => {
            serverErrors.value = errors;
            authError.value = errors.email || errors.password || '';
        },
        onFinish: () => {
            loading.value = false;
            form.password = '';
        },
    });
};

const submitApi = async () => {
    submitted.value = true;
    serverErrors.value = {};
    authError.value = '';

    if (!isValid.value || loading.value) {
        return;
    }

    loading.value = true;

    try {
        const { data } = await window.axios.post('/api/login', {
            email: form.email,
            password: form.password,
            remember: form.remember,
        });

        if (data.token) {
            localStorage.setItem('auth_token', data.token);
            window.axios.defaults.headers.common.Authorization = `Bearer ${data.token}`;
        }

        window.location.href = data.redirect_to || '/dashboard';
    } catch (error) {
        if (error.response?.status === 422) {
            serverErrors.value = error.response.data.errors || {};
            authError.value = error.response.data.message || '';
            return;
        }

        if (error.response?.status === 401) {
            authError.value = error.response.data.message || 'Invalid email or password.';
            return;
        }

        authError.value = 'We could not sign you in right now. Please try again.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Log in" />

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
                            Next-Gen RMC Management
                        </div>
                        <h1 class="text-6xl font-black leading-[0.95] tracking-tighter text-white">
                            Precision In Every <br/>
                            <span class="bg-gradient-to-r from-indigo-400 to-teal-300 bg-clip-text text-transparent italic">Batch.</span>
                        </h1>
                        <p class="mt-6 max-w-md text-lg font-medium text-slate-300 leading-relaxed">
                            The ultimate control plane for concrete production, logistics, and real-time inventory tracking.
                        </p>
                    </div>

                    <div class="flex items-center gap-8">
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-white">24/7</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Real-time Ops</span>
                        </div>
                        <div class="h-8 w-px bg-white/10"></div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-white">100%</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">GST Compliant</span>
                        </div>
                        <div class="h-8 w-px bg-white/10"></div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-white">E-WAY</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Auto Generation</span>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="relative flex min-h-screen items-center justify-center bg-[#d4d6dc] px-4 py-12 sm:px-6 lg:px-10">
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
                            <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">Welcome back</p>
                            <h2 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 dark:text-white">
                                Sign in to continue
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Use your company account to access your ERP workspace.
                            </p>
                        </div>

                        <div v-if="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                            {{ status }}
                        </div>

                        <div v-if="authError" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
                            {{ authError }}
                        </div>

                        <form class="space-y-5" @submit.prevent="submit" novalidate>
                            <div>
                                <label for="email" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">Work Email</label>
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
                                        :aria-invalid="Boolean(fieldError('email'))"
                                        aria-describedby="email-error"
                                        class="w-full rounded-2xl border !h-10 bg-white/40 backdrop-blur-sm py-4 !pl-12 pr-4 text-[15px] font-medium text-slate-900 shadow-sm transition-all duration-300 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:shadow-[0_0_0_4px_rgba(99,102,241,.12)] focus:ring-0 dark:bg-white/[0.05] dark:text-white dark:focus:bg-white/[0.08]"
                                        :class="fieldError('email') ? 'border-rose-400' : 'border-slate-300/50 dark:border-white/10'"
                                        placeholder="name@company.com"
                                        @blur="touched.email = true"
                                        @input="serverErrors.email = null; authError = ''"
                                    />
                                </div>
                                <p v-if="fieldError('email')" id="email-error" class="mt-2 text-xs font-bold text-rose-600 uppercase tracking-tight">{{ fieldError('email') }}</p>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label for="password" class="block text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">Password</label>
                                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-bold text-indigo-600 uppercase tracking-widest transition hover:text-indigo-500 focus:outline-none">
                                        Forgot?
                                    </Link>
                                </div>
                                <div class="group relative">
                                    <div class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-indigo-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                    </div>
                                    <Password
                                        id="password"
                                        v-model="form.password"
                                        :feedback="false"
                                        toggleMask
                                        autocomplete="current-password"
                                        :aria-invalid="Boolean(fieldError('password'))"
                                        aria-describedby="password-error"
                                        class="block w-full"
                                        inputClass="w-full rounded-2xl !h-10 border bg-white/40 backdrop-blur-sm py-4 !pl-12 pr-12 text-[15px] font-medium text-slate-900 shadow-sm transition-all duration-300 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:shadow-[0_0_0_4px_rgba(99,102,241,.12)] focus:ring-0 dark:bg-white/[0.05] dark:text-white dark:focus:bg-white/[0.08]"
                                        :class="fieldError('password') ? '[&_.p-password-input]:border-rose-400' : '[&_.p-password-input]:border-slate-300/50 dark:[&_.p-password-input]:border-white/10'"
                                        placeholder="Your security key"
                                        @blur="touched.password = true"
                                        @input="serverErrors.password = null; authError = ''"
                                    />
                                </div>
                                <p v-if="fieldError('password')" id="password-error" class="mt-2 text-xs font-bold text-rose-600 uppercase tracking-tight">{{ fieldError('password') }}</p>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <Checkbox v-model="form.remember" inputId="remember" binary aria-label="Remember me" />
                                    <span>Keep me signed in</span>
                                </label>
                                <button type="button" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-400 dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/15" @click="quickFillDemo">
                                    Demo login
                                </button>
                            </div>

                            <Button
                                type="submit"
                                :disabled="!isValid || loading"
                                :loading="loading"
                                label="Sign in"
                                aria-label="Sign in"
                                class="w-full rounded-2xl border-0 bg-slate-950 px-5 py-3.5 text-[15px] font-semibold text-white shadow-xl shadow-slate-900/20 transition-all duration-200 hover:-translate-y-0.5 hover:bg-slate-800 focus:ring-2 focus:ring-teal-400 focus:ring-offset-2 disabled:translate-y-0 disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 dark:focus:ring-offset-slate-950"
                            />
                        </form>

                        <!-- <div class="my-7 flex items-center gap-4">
                            <div class="h-px flex-1 bg-slate-200 dark:bg-white/10" />
                            <span class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">or continue with</span>
                            <div class="h-px flex-1 bg-slate-200 dark:bg-white/10" />
                        </div> -->

                        <!-- <div class="grid grid-cols-2 gap-3">
                            <Button type="button" outlined class="rounded-2xl border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:bg-slate-50 dark:border-white/10 dark:bg-white/[0.06] dark:text-slate-200 dark:hover:bg-white/[0.10]" aria-label="Continue with Google">
                                <i class="pi pi-google mr-2" aria-hidden="true" />
                                Google
                            </Button>
                            <Button type="button" outlined class="rounded-2xl border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:bg-slate-50 dark:border-white/10 dark:bg-white/[0.06] dark:text-slate-200 dark:hover:bg-white/[0.10]" aria-label="Continue with GitHub">
                                <i class="pi pi-github mr-2" aria-hidden="true" />
                                GitHub
                            </Button>
                        </div> -->


                    </div>

                    <p class="mt-5 text-center text-xs text-slate-500 dark:text-slate-500">
                        Sanctum protected access with secure token sessions.
                    </p>
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
