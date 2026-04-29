<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';

const props = defineProps<{
    status: number;
}>();

const errorConfig = computed(() => {
    switch (props.status) {
        case 400: return {
            code: '400',
            title: 'Bad Request',
            description: 'The server could not understand your request. Please check the input and try again.',
            color: 'amber',
        };
        case 403: return {
            code: '403',
            title: 'Access Denied',
            description: "You don't have permission to access this resource. Contact your administrator if you think this is a mistake.",
            color: 'orange',
        };
        case 404: return {
            code: '404',
            title: 'Page Not Found',
            description: "The page you're looking for doesn't exist or has been moved. Let's get you back on track.",
            color: 'blue',
        };
        case 419: return {
            code: '419',
            title: 'Session Expired',
            description: 'Your session has expired. Please refresh the page and try again.',
            color: 'purple',
        };
        case 429: return {
            code: '429',
            title: 'Too Many Requests',
            description: "You've made too many requests in a short time. Please wait a moment before trying again.",
            color: 'red',
        };
        case 500: return {
            code: '500',
            title: 'Server Error',
            description: 'An unexpected error occurred on our servers. Our team has been notified and is working on a fix.',
            color: 'red',
        };
        case 503: return {
            code: '503',
            title: 'Service Unavailable',
            description: 'The service is temporarily unavailable for maintenance. Please check back shortly.',
            color: 'teal',
        };
        default: return {
            code: String(props.status),
            title: 'Something Went Wrong',
            description: 'An unexpected error occurred. Please try again or contact support.',
            color: 'blue',
        };
    }
});

const goBack = () => {
    window.history.back();
};

const goToDashboard = () => {
    window.location.href = '/dashboard';
};

// Fake sidebar nav items for the ERP layout context
const sidebarItems = [
    { icon: 'home', label: 'Dashboard' },
    { icon: 'chart', label: 'Analytics' },
    { icon: 'briefcase', label: 'Entities' },
    { icon: 'users', label: 'Users' },
    { icon: 'cog', label: 'Settings' },
];
</script>

<template>
    <Head :title="`${errorConfig.code} - ${errorConfig.title}`" />

    <div class="erp-shell">
        <!-- ── TOP NAVBAR ── -->
        <header class="erp-topbar">
            <div class="erp-topbar__logo">
                <ApplicationMark class="h-8 w-auto brightness-125" />
                <span class="erp-topbar__brand">ModorRMC ERP</span>
            </div>

            <div class="erp-topbar__right">
                <div class="erp-topbar__pill">
                    <span class="erp-topbar__dot"></span>
                    <span>Workspace</span>
                </div>
                <div class="erp-topbar__avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" />
                    </svg>
                </div>
            </div>
        </header>

        <div class="erp-body">
            <!-- ── SIDEBAR ── -->
            <aside class="erp-sidebar">
                <nav class="erp-sidebar__nav">
                    <div
                        v-for="item in sidebarItems"
                        :key="item.label"
                        class="erp-sidebar__item"
                    >
                        <!-- Home -->
                        <svg v-if="item.icon === 'home'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <!-- Chart -->
                        <svg v-else-if="item.icon === 'chart'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        <!-- Briefcase -->
                        <svg v-else-if="item.icon === 'briefcase'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                        </svg>
                        <!-- Users -->
                        <svg v-else-if="item.icon === 'users'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <!-- Cog -->
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </div>
                </nav>

                <div class="erp-sidebar__footer">
                    <div class="erp-sidebar__footer-badge">
                        <span class="erp-sidebar__footer-dot"></span>
                        v4.0 Portal
                    </div>
                </div>
            </aside>

            <!-- ── MAIN CONTENT ── -->
            <main class="erp-main">
                <!-- Decorative grid background -->
                <div class="erp-bg-grid" aria-hidden="true"></div>

                <!-- Floating decorative blobs -->
                <div class="erp-blob erp-blob--1" aria-hidden="true"></div>
                <div class="erp-blob erp-blob--2" aria-hidden="true"></div>

                <div class="erp-error-card">
                    <!-- Illustration -->
                    <div class="erp-illustration" aria-hidden="true">
                        <!-- Animated SVG illustration -->
                        <svg viewBox="0 0 320 220" fill="none" xmlns="http://www.w3.org/2000/svg" class="erp-illustration__svg">
                            <!-- Ground shadow -->
                            <ellipse cx="160" cy="205" rx="90" ry="10" fill="#e2e8f0" />

                            <!-- Monitor body -->
                            <rect x="75" y="60" width="170" height="118" rx="10" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/>
                            <!-- Monitor screen bezel -->
                            <rect x="83" y="68" width="154" height="96" rx="6" fill="#1e3a5f"/>

                            <!-- Screen content: error code -->
                            <text x="160" y="108" text-anchor="middle" font-family="monospace" font-size="32" font-weight="700" fill="#fff" opacity="0.15">{{ errorConfig.code }}</text>

                            <!-- Blinking cursor lines on screen -->
                            <rect x="100" y="80" width="80" height="5" rx="2.5" fill="#3b638e" opacity="0.6"/>
                            <rect x="100" y="92" width="54" height="5" rx="2.5" fill="#3b638e" opacity="0.4"/>
                            <rect x="100" y="115" width="42" height="5" rx="2.5" fill="#3b638e" opacity="0.3"/>
                            <rect x="100" y="127" width="60" height="5" rx="2.5" fill="#3b638e" opacity="0.2"/>

                            <!-- Screen error icon box -->
                            <rect x="170" y="78" width="52" height="52" rx="8" fill="#243d57"/>
                            <!-- X icon -->
                            <line x1="182" y1="90" x2="210" y2="118" stroke="#f87171" stroke-width="3.5" stroke-linecap="round"/>
                            <line x1="210" y1="90" x2="182" y2="118" stroke="#f87171" stroke-width="3.5" stroke-linecap="round"/>

                            <!-- Floating warning badge -->
                            <g class="erp-float-badge">
                                <rect x="198" y="50" width="50" height="22" rx="11" fill="#fef3c7" stroke="#fbbf24" stroke-width="1.5"/>
                                <text x="223" y="65" text-anchor="middle" font-family="sans-serif" font-size="9" font-weight="600" fill="#b45309">{{ errorConfig.code }}</text>
                            </g>

                            <!-- Monitor stand -->
                            <rect x="148" y="178" width="24" height="18" rx="2" fill="#cbd5e1"/>
                            <!-- Monitor base -->
                            <rect x="128" y="194" width="64" height="8" rx="4" fill="#94a3b8"/>

                            <!-- Floating dots -->
                            <circle cx="58" cy="88" r="5" fill="#bfdbfe" class="erp-dot-1"/>
                            <circle cx="44" cy="110" r="3.5" fill="#93c5fd" class="erp-dot-2"/>
                            <circle cx="268" cy="95" r="4" fill="#bfdbfe" class="erp-dot-3"/>
                            <circle cx="280" cy="120" r="3" fill="#60a5fa" class="erp-dot-4"/>

                            <!-- Small document icon top left -->
                            <g opacity="0.5">
                                <rect x="30" y="130" width="30" height="38" rx="4" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="1.5"/>
                                <line x1="36" y1="142" x2="54" y2="142" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="36" y1="150" x2="50" y2="150" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="36" y1="158" x2="53" y2="158" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>
                            </g>

                            <!-- Search icon right -->
                            <g opacity="0.5" class="erp-search-icon">
                                <circle cx="272" cy="148" r="14" fill="#e0f2fe" stroke="#bae6fd" stroke-width="1.5"/>
                                <circle cx="270" cy="146" r="6" fill="none" stroke="#7dd3fc" stroke-width="2"/>
                                <line x1="274.5" y1="150.5" x2="279" y2="155" stroke="#38bdf8" stroke-width="2" stroke-linecap="round"/>
                            </g>
                        </svg>
                    </div>

                    <!-- Error code badge -->
                    <div class="erp-error-badge">
                        <span class="erp-error-badge__pulse"></span>
                        <span class="erp-error-badge__code">{{ errorConfig.code }}</span>
                    </div>

                    <!-- Headline -->
                    <h1 class="erp-error-title">{{ errorConfig.title }}</h1>

                    <!-- Description -->
                    <p class="erp-error-desc">{{ errorConfig.description }}</p>

                    <!-- Divider -->
                    <div class="erp-divider"></div>

                    <!-- Action Buttons -->
                    <div class="erp-actions">
                        <button id="btn-go-back" class="erp-btn erp-btn--secondary" @click="goBack">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                            </svg>
                            Go Back
                        </button>
                        <button id="btn-go-dashboard" class="erp-btn erp-btn--primary" @click="goToDashboard">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
                            </svg>
                            Go to Dashboard
                        </button>
                    </div>

                    <!-- Help hint -->
                    <p class="erp-hint">
                        Need help? Contact
                        <a href="mailto:support@modormines.com" class="erp-hint__link">support@modormines.com</a>
                    </p>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/* ────────────────────────────────────────────
   SHELL LAYOUT
──────────────────────────────────────────── */
.erp-shell {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background: #f1f5f9;
    font-family: 'Roboto', 'Inter', system-ui, sans-serif;
}

/* ────────────────────────────────────────────
   TOP NAVBAR
──────────────────────────────────────────── */
.erp-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    padding: 0 28px;
    background: linear-gradient(90deg, #182a3b 0%, #243d57 60%, #2f5073 100%);
    box-shadow: 0 2px 12px rgba(15, 26, 38, 0.3);
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}

.erp-topbar__logo {
    display: flex;
    align-items: center;
    gap: 10px;
}

.erp-topbar__brand {
    font-size: 0.875rem;
    font-weight: 600;
    color: #bfdbfe;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.erp-topbar__right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.erp-topbar__pill {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    color: #bfdbfe;
    font-size: 0.75rem;
    font-weight: 500;
}

.erp-topbar__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fbbf24;
    box-shadow: 0 0 6px #fbbf24;
    flex-shrink: 0;
}

.erp-topbar__avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    border: 1.5px solid rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #bfdbfe;
    cursor: pointer;
    transition: background 0.2s;
}
.erp-topbar__avatar:hover { background: rgba(255,255,255,0.18); }
.erp-topbar__avatar svg { width: 18px; height: 18px; }

/* ────────────────────────────────────────────
   BODY (sidebar + main)
──────────────────────────────────────────── */
.erp-body {
    display: flex;
    flex: 1;
    overflow: hidden;
}

/* ────────────────────────────────────────────
   SIDEBAR
──────────────────────────────────────────── */
.erp-sidebar {
    width: 200px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 2px 0 8px rgba(0,0,0,0.04);
}

.erp-sidebar__nav {
    padding: 20px 0 12px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.erp-sidebar__item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    margin: 0 8px;
    border-radius: 8px;
    color: #94a3b8;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: not-allowed;
    user-select: none;
    transition: background 0.15s;
}
.erp-sidebar__item svg {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    opacity: 0.5;
}

/* First item gets a subtle "active-like" muted style */
.erp-sidebar__item:first-child {
    color: #64748b;
    background: #f8fafc;
}

.erp-sidebar__footer {
    padding: 16px 20px;
    border-top: 1px solid #f1f5f9;
}

.erp-sidebar__footer-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 500;
}

.erp-sidebar__footer-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 5px rgba(34,197,94,0.5);
}

/* ────────────────────────────────────────────
   MAIN CONTENT
──────────────────────────────────────────── */
.erp-main {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
    overflow: hidden;
}

/* Dot grid background */
.erp-bg-grid {
    position: absolute;
    inset: 0;
    background-image:
        radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 28px 28px;
    opacity: 0.45;
    pointer-events: none;
}

/* Decorative blobs */
.erp-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    pointer-events: none;
    z-index: 0;
}
.erp-blob--1 {
    width: 340px;
    height: 340px;
    top: -80px;
    right: -60px;
    background: radial-gradient(circle, rgba(59,99,142,0.12) 0%, transparent 70%);
}
.erp-blob--2 {
    width: 260px;
    height: 260px;
    bottom: -60px;
    left: 80px;
    background: radial-gradient(circle, rgba(96,165,250,0.10) 0%, transparent 70%);
}

/* ────────────────────────────────────────────
   ERROR CARD
──────────────────────────────────────────── */
.erp-error-card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 560px;
    background: #fff;
    border-radius: 20px;
    box-shadow:
        0 1px 3px rgba(0,0,0,0.04),
        0 8px 32px rgba(15,26,38,0.08),
        0 24px 64px rgba(15,26,38,0.05);
    border: 1px solid #e2e8f0;
    padding: 48px 48px 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    animation: erp-card-in 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes erp-card-in {
    from { opacity: 0; transform: translateY(24px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ────────────────────────────────────────────
   ILLUSTRATION
──────────────────────────────────────────── */
.erp-illustration {
    width: 100%;
    max-width: 320px;
    margin-bottom: 8px;
}

.erp-illustration__svg {
    width: 100%;
    height: auto;
}

/* Floating animation for the badge on the SVG */
.erp-float-badge {
    animation: erp-float 3s ease-in-out infinite;
    transform-origin: 223px 61px;
}

/* Floating dots */
.erp-dot-1 { animation: erp-float 2.8s ease-in-out infinite; }
.erp-dot-2 { animation: erp-float 3.4s ease-in-out 0.4s infinite; }
.erp-dot-3 { animation: erp-float 3.1s ease-in-out 0.2s infinite; }
.erp-dot-4 { animation: erp-float 2.6s ease-in-out 0.7s infinite; }
.erp-search-icon { animation: erp-float 3.6s ease-in-out 0.5s infinite; }

@keyframes erp-float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-6px); }
}

/* ────────────────────────────────────────────
   BADGE
──────────────────────────────────────────── */
.erp-error-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 14px;
    border-radius: 999px;
    background: linear-gradient(135deg, #e8f1ff 0%, #dbeafe 100%);
    border: 1px solid #bfdbfe;
    margin-bottom: 16px;
}

.erp-error-badge__pulse {
    position: relative;
    width: 8px;
    height: 8px;
    flex-shrink: 0;
}
.erp-error-badge__pulse::before,
.erp-error-badge__pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: #3b638e;
}
.erp-error-badge__pulse::after {
    animation: erp-pulse 1.8s ease-out infinite;
    background: rgba(59, 99, 142, 0.5);
}

@keyframes erp-pulse {
    0%   { transform: scale(1); opacity: 1; }
    100% { transform: scale(2.8); opacity: 0; }
}

.erp-error-badge__code {
    font-size: 0.7rem;
    font-weight: 700;
    color: #243d57;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-family: 'Roboto Mono', monospace;
}

/* ────────────────────────────────────────────
   TEXT
──────────────────────────────────────────── */
.erp-error-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
    margin: 0 0 12px;
    letter-spacing: -0.02em;
}

.erp-error-desc {
    font-size: 0.9375rem;
    color: #64748b;
    line-height: 1.65;
    margin: 0;
    max-width: 400px;
}

/* ────────────────────────────────────────────
   DIVIDER
──────────────────────────────────────────── */
.erp-divider {
    width: 48px;
    height: 3px;
    border-radius: 2px;
    background: linear-gradient(90deg, #3b638e, #60a5fa);
    margin: 28px auto;
}

/* ────────────────────────────────────────────
   ACTION BUTTONS
──────────────────────────────────────────── */
.erp-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
}

.erp-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 24px;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    letter-spacing: 0.01em;
}

.erp-btn svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.erp-btn--primary {
    background: linear-gradient(135deg, #243d57 0%, #3b638e 100%);
    color: #fff;
    box-shadow: 0 4px 14px rgba(35, 61, 87, 0.28);
}
.erp-btn--primary:hover {
    background: linear-gradient(135deg, #182a3b 0%, #2f5073 100%);
    box-shadow: 0 6px 20px rgba(35, 61, 87, 0.38);
    transform: translateY(-1px);
}
.erp-btn--primary:active { transform: translateY(0); }

.erp-btn--secondary {
    background: #f8fafc;
    color: #475569;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.erp-btn--secondary:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #1e293b;
    transform: translateY(-1px);
}
.erp-btn--secondary:active { transform: translateY(0); }

/* ────────────────────────────────────────────
   HINT
──────────────────────────────────────────── */
.erp-hint {
    margin-top: 24px;
    font-size: 0.8rem;
    color: #94a3b8;
}

.erp-hint__link {
    color: #3b638e;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px dashed #93c5fd;
    transition: color 0.2s, border-color 0.2s;
}
.erp-hint__link:hover {
    color: #182a3b;
    border-color: #3b638e;
}

/* ────────────────────────────────────────────
   RESPONSIVE
──────────────────────────────────────────── */
@media (max-width: 640px) {
    .erp-sidebar { display: none; }
    .erp-error-card { padding: 32px 24px 28px; }
    .erp-error-title { font-size: 1.5rem; }
    .erp-topbar__pill { display: none; }
}
</style>

