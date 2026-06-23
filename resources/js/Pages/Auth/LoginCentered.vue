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

    <main class="relative min-h-screen w-full overflow-hidden bg-slate-950 flex items-center justify-center px-4 py-16 sm:px-6 lg:px-8">
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
                        <label for="email" class="mb-2 block text-xs font-bold text-slate-400 uppercase tracking-wider">Work Email</label>
                        <div class="group relative">
                            <div class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-500 transition-colors group-focus-within:text-teal-400">
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
                                class="w-full rounded-2xl border !h-11 bg-white/[0.03] backdrop-blur-sm py-4 !pl-12 pr-4 text-[15px] font-medium text-white shadow-sm transition-all duration-300 placeholder:text-slate-500 focus:border-teal-400 focus:bg-slate-950/40 focus:shadow-[0_0_0_4px_rgba(20,184,166,0.15)] focus:ring-0"
                                :class="fieldError('email') ? 'border-rose-500' : 'border-white/10'"
                                placeholder="name@company.com"
                                @blur="touched.email = true"
                                @input="serverErrors.email = null; authError = ''"
                            />
                        </div>
                        <p v-if="fieldError('email')" id="email-error" class="mt-2 text-xs font-bold text-rose-400 uppercase tracking-tight">{{ fieldError('email') }}</p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Password</label>
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
                                id="password"
                                v-model="form.password"
                                :feedback="false"
                                toggleMask
                                autocomplete="current-password"
                                :aria-invalid="Boolean(fieldError('password'))"
                                aria-describedby="password-error"
                                class="block w-full"
                                inputClass="w-full rounded-2xl !h-11 border bg-white/[0.03] backdrop-blur-sm py-4 !pl-12 pr-12 text-[15px] font-medium text-white shadow-sm transition-all duration-300 placeholder:text-slate-500 focus:border-teal-400 focus:bg-slate-950/40 focus:shadow-[0_0_0_4px_rgba(20,184,166,0.15)] focus:ring-0"
                                :class="fieldError('password') ? '[&_.p-password-input]:border-rose-500' : '[&_.p-password-input]:border-white/10'"
                                placeholder="Your security key"
                                @blur="touched.password = true"
                                @input="serverErrors.password = null; authError = ''"
                            />
                        </div>
                        <p v-if="fieldError('password')" id="password-error" class="mt-2 text-xs font-bold text-rose-400 uppercase tracking-tight">{{ fieldError('password') }}</p>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-300 select-none">
                            <Checkbox v-model="form.remember" inputId="remember" binary aria-label="Remember me" class="border-white/10 bg-white/5" />
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
