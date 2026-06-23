<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    defaultLayout: {
        type: String,
        default: 'centered'
    }
});

const layout = ref(localStorage.getItem('login_layout') || props.defaultLayout || 'centered');

const setLayout = (val) => {
    layout.value = val;
    localStorage.setItem('login_layout', val);
};

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

    <div class="relative min-h-screen w-full bg-slate-900 transition-colors duration-500">
        
        <!-- Interactive Layout Switcher Widget -->
        <div class="fixed top-6 right-6 z-50 flex items-center gap-1 rounded-full border border-slate-300/20 bg-white/10 p-1 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-slate-950/40">
            <button 
                type="button" 
                class="rounded-full px-3.5 py-1.5 text-xs font-black transition-all duration-200"
                :class="layout === 'split' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-200 hover:bg-white/5'"
                @click="setLayout('split')"
            >
                Split View
            </button>
            <button 
                type="button" 
                class="rounded-full px-3.5 py-1.5 text-xs font-black transition-all duration-200"
                :class="layout === 'centered' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-200 hover:bg-white/5'"
                @click="setLayout('centered')"
            >
                Centered View
            </button>
        </div>

        <!-- ========================================== -->
        <!-- OPTION A: Centered-Card Layout             -->
        <!-- ========================================== -->
        <main v-if="layout === 'centered'" class="relative min-h-screen w-full overflow-hidden flex items-center justify-center px-4 py-16 sm:px-6 lg:px-8 bg-slate-950">
            <!-- Full-bleed background visual with dark overlays -->
            <div class="absolute inset-0 z-0 select-none pointer-events-none">
                <img 
                    src="/onemodo_truck_login.png" 
                    alt="Industrial Logistics" 
                    class="h-full w-full object-cover opacity-35 grayscale"
                />
                <!-- Gradient overlays to ensure readability and depth -->
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/80 via-slate-950/50 to-slate-950/90"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(99,102,241,0.15),transparent_60%)]"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(20,184,166,0.1),transparent_40%)]"></div>
            </div>

            <!-- Inner Content Area -->
            <div class="relative z-10 w-full max-w-[440px] flex flex-col items-center">
                
                <!-- Header/Branding -->
                <div class="mb-8 flex flex-col items-center text-center animate-[auth-panel_650ms_cubic-bezier(.16,1,.3,1)_both]">
                    <img 
                        src="/assets/modormc_logo_v1.png" 
                        alt="ModoRMC Logo" 
                        class="h-14 w-auto object-contain opacity-100 mb-6 drop-shadow-[0_2px_8px_rgba(255,255,255,0.15)]"
                    />
                    
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-[10px] font-black uppercase tracking-widest text-teal-300 backdrop-blur-md mb-4">
                        <span class="size-2 bg-teal-400 rounded-full animate-pulse"></span>
                        Next-Gen RMC Management
                    </div>
                    
                    <h1 class="text-3xl sm:text-4xl font-black leading-[1.15] tracking-tighter text-white">
                        Intelligence in every <span class="bg-gradient-to-r from-indigo-400 to-teal-300 bg-clip-text text-transparent italic">mix.</span>
                    </h1>
                    <p class="mt-3 text-sm text-slate-400 max-w-sm">
                        Access the ModoRMC enterprise control plane
                    </p>
                </div>

                <!-- Glassmorphism Login Card -->
                <div class="w-full rounded-[2rem] border border-white/10 bg-slate-900/60 p-6 shadow-[0_24px_80px_rgba(0,0,0,0.6)] backdrop-blur-xl sm:p-8 animate-[auth-panel_750ms_cubic-bezier(.16,1,.3,1)_both]">
                    <div v-if="status" class="mb-5 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-300" role="status">
                        {{ status }}
                    </div>

                    <div v-if="authError" class="mb-5 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-300" role="alert">
                        {{ authError }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit" novalidate>
                        <div>
                            <label for="email-centered" class="mb-2 block text-xs font-bold text-slate-400 uppercase tracking-wider">Work Email</label>
                            <div class="group relative">
                                <div class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-500 transition-colors group-focus-within:text-teal-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                    </svg>
                                </div>
                                <InputText
                                    id="email-centered"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="username"
                                    autofocus
                                    :aria-invalid="Boolean(fieldError('email'))"
                                    aria-describedby="email-centered-error"
                                    class="w-full rounded-2xl border !h-11 bg-white/[0.03] backdrop-blur-sm py-4 !pl-12 pr-4 text-[15px] font-medium text-white shadow-sm transition-all duration-300 placeholder:text-slate-500 focus:border-teal-400 focus:bg-slate-950/40 focus:shadow-[0_0_0_4px_rgba(20,184,166,0.15)] focus:ring-0"
                                    :class="fieldError('email') ? 'border-rose-500' : 'border-white/10'"
                                    placeholder="name@company.com"
                                    @blur="touched.email = true"
                                    @input="serverErrors.email = null; authError = ''"
                                />
                            </div>
                            <p v-if="fieldError('email')" id="email-centered-error" class="mt-2 text-xs font-bold text-rose-400 uppercase tracking-tight">{{ fieldError('email') }}</p>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label for="password-centered" class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Password</label>
                                <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-bold text-teal-400 uppercase tracking-widest transition hover:text-teal-300 focus:outline-none">
                                    Forgot?
                                </Link>
                            </div>
                            <div class="group relative">
                                <div class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-500 transition-colors group-focus-within:text-teal-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                </div>
                                <Password
                                    id="password-centered"
                                    v-model="form.password"
                                    :feedback="false"
                                    toggleMask
                                    autocomplete="current-password"
                                    :aria-invalid="Boolean(fieldError('password'))"
                                    aria-describedby="password-centered-error"
                                    class="block w-full"
                                    inputClass="w-full rounded-2xl !h-11 border bg-white/[0.03] backdrop-blur-sm py-4 !pl-12 pr-12 text-[15px] font-medium text-white shadow-sm transition-all duration-300 placeholder:text-slate-500 focus:border-teal-400 focus:bg-slate-950/40 focus:shadow-[0_0_0_4px_rgba(20,184,166,0.15)] focus:ring-0"
                                    :class="fieldError('password') ? '[&_.p-password-input]:border-rose-500' : '[&_.p-password-input]:border-white/10'"
                                    placeholder="Your security key"
                                    @blur="touched.password = true"
                                    @input="serverErrors.password = null; authError = ''"
                                />
                            </div>
                            <p v-if="fieldError('password')" id="password-centered-error" class="mt-2 text-xs font-bold text-rose-400 uppercase tracking-tight">{{ fieldError('password') }}</p>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-300 select-none">
                                <Checkbox v-model="form.remember" inputId="remember-centered" binary aria-label="Remember me" class="border-white/10 bg-white/5" />
                                <span>Keep me signed in</span>
                            </label>
                            <button type="button" class="rounded-full bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-teal-400" @click="quickFillDemo">
                                Demo login
                            </button>
                        </div>

                        <Button
                            type="submit"
                            :disabled="!isValid || loading"
                            :loading="loading"
                            label="Secure Login"
                            aria-label="Secure Login"
                            class="w-full rounded-2xl border-0 bg-gradient-to-r from-teal-500 to-indigo-600 px-5 py-3.5 text-[15px] font-semibold text-white shadow-xl shadow-teal-500/10 hover:shadow-indigo-500/20 transition-all duration-200 hover:-translate-y-0.5 focus:ring-2 focus:ring-teal-400 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:translate-y-0 disabled:opacity-50"
                        />
                    </form>
                </div>

                <!-- Footer / Support Info -->
                <p class="mt-8 text-center text-xs text-slate-500">
                    Protected by enterprise-grade encryption. Sanctum secured session.
                </p>
            </div>
        </main>

        <!-- ========================================== -->
        <!-- OPTION B: Split-Screen Layout              -->
        <!-- ========================================== -->
        <main v-else-if="layout === 'split'" class="min-h-screen bg-[#f7f5ef] text-slate-950 transition-colors duration-500 dark:bg-[#080a12] dark:text-white">
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
                            </div>
                            <div class="flex flex-col leading-tight">
                                <span class="text-xl font-black tracking-tighter text-white uppercase italic">
                                    <img 
                                        src="/assets/modormc_logo_v1.png" 
                                        alt="Industrial Logistics" 
                                        class="h-16 w-full object-cover opacity-100 transition-all duration-1000"
                                    />
                                </span>
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

                <div class="relative flex min-h-screen items-center justify-center bg-[#d4d6dc] px-4 py-12 sm:px-6 lg:px-10 dark:bg-[#0f172a]">
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
                        </div>

                        <p class="mt-5 text-center text-xs text-slate-500 dark:text-slate-500">
                            Sanctum protected access with secure token sessions.
                        </p>
                    </div>
                </div>
            </section>
        </main>

    </div>
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
