<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = reactive({ email: '', password: '', remember: true });
const touched = reactive({ email: false, password: false });

const serverErrors = ref({});
const authError    = ref('');
const loading      = ref(false);
const submitted    = ref(false);
const showPwd      = ref(false);
const emailFocused = ref(false);
const pwdFocused   = ref(false);

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const clientErrors = computed(() => ({
    email: !form.email
        ? 'Email is required.'
        : !emailPattern.test(form.email) ? 'Enter a valid email.' : '',
    password: !form.password ? 'Password is required.' : '',
}));

const fieldError = (f) => {
    const e = serverErrors.value[f];
    if (Array.isArray(e) && e.length) return e[0];
    if (typeof e === 'string') return e;
    return (submitted.value || touched[f]) ? clientErrors.value[f] : '';
};

const isValid = computed(() => !clientErrors.value.email && !clientErrors.value.password);

const quickFill = () => {
    form.email = 'demo@modomines.com';
    form.password = 'password';
    form.remember = true;
    serverErrors.value = {};
    authError.value = '';
};

const submit = () => {
    submitted.value = true;
    serverErrors.value = {};
    authError.value = '';
    if (!isValid.value || loading.value) return;

    router.post(route('login'), {
        email: form.email, password: form.password, remember: form.remember,
    }, {
        preserveScroll: true,
        onStart:  ()  => { loading.value = true; },
        onError:  (e) => { serverErrors.value = e; authError.value = e.email || e.password || 'Login failed.'; },
        onFinish: ()  => { loading.value = false; form.password = ''; },
    });
};

/* ─── Inline style objects (bypass global CSS !important rules) ─── */
function inputStyle(focused, hasError) {
    return {
        display:          'block',
        width:            '100%',
        height:           '50px',
        borderRadius:     '10px',
        border:           hasError
            ? '2px solid #f43f5e'
            : focused
                ? '2px solid #14b8a6'
                : '1.5px solid rgba(148,163,184,0.30)',
        backgroundColor:  '#0f1f35',      /* deep navy — clearly visible */
        color:            '#f8fafc',      /* bright white */
        WebkitTextFillColor: '#f8fafc',
        caretColor:       '#14b8a6',
        fontSize:         '15px',
        fontWeight:       '500',
        paddingLeft:      '48px',
        paddingRight:     '16px',
        outline:          'none',
        boxSizing:        'border-box',
        boxShadow:        focused
            ? '0 0 0 4px rgba(20,184,166,0.18), inset 0 2px 6px rgba(0,0,0,0.5)'
            : 'inset 0 2px 6px rgba(0,0,0,0.4)',
        transition:       'border 0.2s, box-shadow 0.2s',
        fontFamily:       'inherit',
        lineHeight:       'normal',
        letterSpacing:    '0.01em',
    };
}
function inputPwdStyle(focused, hasError) {
    return { ...inputStyle(focused, hasError), paddingRight: '48px' };
}
</script>

<template>
    <Head title="Log in" />

    <main class="lc-root">
        <!-- Background -->
        <div class="lc-bg" aria-hidden="true">
            <img src="/onemodo_truck_login.png" alt="" class="lc-bg-img" />
            <div class="lc-bg-dark"></div>
            <div class="lc-bg-glow-top"></div>
            <div class="lc-bg-glow-btm"></div>
        </div>

        <!-- Centre column -->
        <div class="lc-col">

            <!-- ── Branding ── -->
            <header class="lc-header">
                <img src="/assets/modormc_logo_v1.png" alt="ModoRMC" class="lc-logo" />
                <div class="lc-pill">
                    <span class="lc-dot"></span>
                    Next-Gen RMC Management
                </div>
                <h1 class="lc-h1">
                    Intelligence in every&nbsp;<span class="lc-gradient">mix.</span>
                </h1>
                <p class="lc-tagline">Access the ModoRMC enterprise control plane</p>
            </header>

            <!-- ── Login Card ── -->
            <section class="lc-card">

                <!-- Server / auth status -->
                <div v-if="status"    class="lc-notice lc-notice--ok">{{ status }}</div>
                <div v-if="authError" class="lc-notice lc-notice--err">{{ authError }}</div>

                <form @submit.prevent="submit" novalidate>

                    <!-- Email -->
                    <div class="lc-field" :class="{ 'lc-field--err': fieldError('email') }">
                        <label for="lc-email" class="lc-label">Work Email</label>
                        <div class="lc-inp-wrap">
                            <span class="lc-inp-icon" :class="{ 'lc-inp-icon--on': emailFocused }">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.8" width="18" height="18">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                                </svg>
                            </span>
                            <input
                                id="lc-email"
                                v-model="form.email"
                                type="email"
                                style="color:white !important;padding-left:35px !important"
                                autocomplete="username"
                                autofocus
                                placeholder="name@company.com"
                                :style="inputStyle(emailFocused, !!fieldError('email'))"
                                @focus="emailFocused = true"
                                @blur="emailFocused = false; touched.email = true"
                                @input="serverErrors.email = null; authError = ''"
                            />
                        </div>
                        <p v-if="fieldError('email')" class="lc-err-msg">{{ fieldError('email') }}</p>
                    </div>

                    <!-- Password -->
                    <div class="lc-field lc-field--mt" :class="{ 'lc-field--err': fieldError('password') }">
                        <div class="lc-label-row">
                            <label for="lc-password" class="lc-label">Password</label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="lc-forgot">
                                Forgot?
                            </Link>
                        </div>
                        <div class="lc-inp-wrap">
                            <span class="lc-inp-icon" :class="{ 'lc-inp-icon--on': pwdFocused }">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.8" width="18" height="18">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                                </svg>
                            </span>
                            <input
                                id="lc-password"
                                v-model="form.password"
                                :type="showPwd ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                :style="inputPwdStyle(pwdFocused, !!fieldError('password'))"
                                @focus="pwdFocused = true"
                                @blur="pwdFocused = false; touched.password = true"
                                @input="serverErrors.password = null; authError = ''"
                            />
                            <button type="button" class="lc-eye"
                                    :aria-label="showPwd ? 'Hide' : 'Show'"
                                    @click="showPwd = !showPwd">
                                <!-- eye open -->
                                <svg v-if="!showPwd" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.8" width="18" height="18">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                <!-- eye closed -->
                                <svg v-else viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.8" width="18" height="18">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                        <p v-if="fieldError('password')" class="lc-err-msg">{{ fieldError('password') }}</p>
                    </div>

                    <!-- Remember / Demo -->
                    <div class="lc-row">
                        <label class="lc-remember">
                            <input type="checkbox" v-model="form.remember" class="lc-chk" />
                            <span>Keep me signed in</span>
                        </label>
                        <button type="button" class="lc-demo-btn" @click="quickFill">
                            Demo login
                        </button>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="lc-submit" :disabled="!isValid || loading">
                        <svg v-if="loading" class="lc-spin" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" width="18" height="18">
                            <path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/>
                        </svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                        {{ loading ? 'Signing in…' : 'Secure Login' }}
                    </button>

                </form>
            </section>

            <p class="lc-foot">Protected by enterprise-grade encryption &middot; Sanctum session</p>
        </div>
    </main>
</template>

<style scoped>
/* ─── Reset & root ────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.lc-root {
    position: relative;
    min-height: 100svh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem 3rem;
    background: #050d1a;
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    overflow: hidden;
}

/* ─── Background ──────────────────────────────────────────── */
.lc-bg { position: absolute; inset: 0; pointer-events: none; user-select: none; z-index: 0; }

.lc-bg-img {
    width: 100%; height: 100%;
    object-fit: cover;
    opacity: 0.22;
    filter: grayscale(70%) brightness(0.6) blur(1px);
}
.lc-bg-dark {
    position: absolute; inset: 0;
    background: linear-gradient(160deg, rgba(5,13,26,.92) 0%, rgba(5,13,26,.55) 50%, rgba(5,13,26,.95) 100%);
}
.lc-bg-glow-top {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 70% 45% at 50% 0%, rgba(79,70,229,.22) 0%, transparent 70%);
}
.lc-bg-glow-btm {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 35% at 15% 100%, rgba(20,184,166,.14) 0%, transparent 60%);
}

/* ─── Centre column ───────────────────────────────────────── */
.lc-col {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 460px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    animation: lc-rise 550ms cubic-bezier(.16,1,.3,1) both;
}

/* ─── Branding header ─────────────────────────────────────── */
.lc-header {
    text-align: center;
    margin-bottom: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
}

.lc-logo {
    height: 56px;
    width: auto;
    object-fit: contain;
    margin-bottom: 1.25rem;
    filter: drop-shadow(0 4px 16px rgba(255,255,255,0.10));
}

.lc-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 16px;
    border-radius: 999px;
    border: 1px solid rgba(20,184,166,0.30);
    background: rgba(20,184,166,0.08);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #2dd4bf;
    margin-bottom: 1.1rem;
}
.lc-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #14b8a6;
    animation: lc-pulse 2.2s ease-in-out infinite;
    flex-shrink: 0;
}

.lc-h1 {
    font-size: clamp(1.85rem, 5vw, 2.5rem);
    font-weight: 900;
    letter-spacing: -.04em;
    line-height: 1.08;
    color: #f8fafc;
    margin-bottom: .65rem;
}
.lc-gradient {
    font-style: italic;
    background: linear-gradient(90deg, #818cf8 0%, #22d3ee 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.lc-tagline {
    font-size: .875rem;
    color: #64748b;
    letter-spacing: .01em;
}

/* ─── Card ────────────────────────────────────────────────── */
.lc-card {
    width: 100%;
    border-radius: 20px;
    /* Strong contrast card background */
    background: linear-gradient(145deg, #0d1b2e 0%, #111827 100%);
    border: 1px solid rgba(255,255,255,0.09);
    box-shadow:
        0 0 0 1px rgba(255,255,255,0.04) inset,
        0 8px 32px rgba(0,0,0,0.5),
        0 32px 80px rgba(0,0,0,0.45);
    padding: 2rem 2rem 2.25rem;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
}

/* ─── Notices ─────────────────────────────────────────────── */
.lc-notice {
    margin-bottom: 1rem;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: .875rem;
    font-weight: 500;
}
.lc-notice--ok  { border: 1px solid rgba(16,185,129,.25); background: rgba(16,185,129,.10); color: #6ee7b7; }
.lc-notice--err { border: 1px solid rgba(244, 63, 94,.30); background: rgba(244,63,94,.08);  color: #fda4af; }

/* ─── Field wrapper ───────────────────────────────────────── */
.lc-field     { display: flex; flex-direction: column; }
.lc-field--mt { margin-top: 1.1rem; }

.lc-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: #7c8fa8;
    margin-bottom: 8px;
    display: block;
}
.lc-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.lc-forgot {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #14b8a6;
    text-decoration: none;
    transition: color .15s;
}
.lc-forgot:hover { color: #2dd4bf; }

/* ─── Input wrapper (icon + native input) ─────────────────── */
.lc-inp-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.lc-inp-icon {
    position: absolute;
    left: 14px;
    color: #475569;
    pointer-events: none;
    z-index: 2;
    display: flex;
    align-items: center;
    transition: color .2s;
}
.lc-inp-icon--on { color: #14b8a6; }

/* NOTE: All <input> styles applied via :style binding in <template>
   to guarantee they override app.css global  background: transparent !important */

.lc-eye {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    padding: 5px;
    cursor: pointer;
    color: #475569;
    display: flex;
    align-items: center;
    z-index: 3;
    transition: color .2s;
    border-radius: 6px;
}
.lc-eye:hover { color: #94a3b8; }

.lc-err-msg {
    margin-top: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #fb7185;
}

/* ─── Remember & Demo row ─────────────────────────────────── */
.lc-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 1.1rem;
}

.lc-remember {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .875rem;
    color: #94a3b8;
    cursor: pointer;
    user-select: none;
}
.lc-chk {
    width: 16px; height: 16px;
    accent-color: #14b8a6;
    cursor: pointer;
    border-radius: 4px !important;
}
.lc-demo-btn {
    padding: 6px 14px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.05);
    font-size: .75rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s;
}
.lc-demo-btn:hover {
    background: rgba(255,255,255,0.10);
    color: #cbd5e1;
    border-color: rgba(255,255,255,0.18);
}

/* ─── Submit button ───────────────────────────────────────── */
.lc-submit {
    width: 100%;
    margin-top: 1.4rem;
    border: none;
    border-radius: 12px;
    padding: 14px 20px;
    background: linear-gradient(135deg, #0d9488 0%, #4f46e5 100%);
    font-size: .9375rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: .025em;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    box-shadow:
        0 4px 20px rgba(13,148,136,.30),
        0 2px 8px rgba(0,0,0,.35);
    transition: transform .18s, box-shadow .18s, opacity .18s;
    position: relative;
    overflow: hidden;
}
.lc-submit::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.10) 0%, transparent 60%);
    pointer-events: none;
}
.lc-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow:
        0 8px 32px rgba(79,70,229,.40),
        0 4px 12px rgba(0,0,0,.35);
}
.lc-submit:active:not(:disabled) { transform: translateY(0); }
.lc-submit:disabled { opacity: .4; cursor: not-allowed; }

/* ─── Spinner ─────────────────────────────────────────────── */
.lc-spin {
    animation: lc-spin .65s linear infinite;
    flex-shrink: 0;
}

/* ─── Footer ──────────────────────────────────────────────── */
.lc-foot {
    margin-top: 1.75rem;
    font-size: .72rem;
    color: #1e3a5f;
    letter-spacing: .03em;
    text-align: center;
}

/* ─── Animations ──────────────────────────────────────────── */
@keyframes lc-rise {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes lc-spin {
    to { transform: rotate(360deg); }
}
@keyframes lc-pulse {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: .4; transform: scale(.8); }
}
</style>
