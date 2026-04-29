<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';

// ──────────────────────────────
// Props
// ──────────────────────────────
const props = defineProps<{
    entityAccess: Array<{
        entity_id: number;
        entity_name: string;
        entity_alias?: string;
        entity_logo?: string;
        role_name: string;
        is_active: boolean;
    }>;
}>();

// ──────────────────────────────
// State
// ──────────────────────────────
const step = ref<'entity' | 'plant'>('entity');
const errorMessage = ref('');
const page = usePage();

const isLoading         = ref(false);
const switchingEntityId = ref<number | null>(null);
const switchingPlantId  = ref<number | null>(null);

const selectedEntityId   = ref<number | null>(null);
const selectedEntityName = ref<string>('');
const availablePlants    = ref<Array<{
    id: number;
    name: string;
    code: string | null;
    is_main: boolean;
}>>([]);

const userName = computed(() => {
    const user = page.props.auth?.user;
    return user?.name || user?.username || 'User';
});

const stepTitle = computed(() => step.value === 'entity' ? 'Choose your workspace' : 'Choose active plant');
const stepDescription = computed(() => step.value === 'entity'
    ? 'Select the company or entity you want to work in for this session.'
    : `Select a plant for ${selectedEntityName.value}.`);

const activeEntity = computed(() => props.entityAccess.find((entity) => entity.is_active));

const initials = (name: string) => name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');

// ──────────────────────────────
// Step 1 — pick entity
// ──────────────────────────────
const selectEntity = async (eu: typeof props.entityAccess[0]) => {
    if (isLoading.value) return;

    errorMessage.value = '';
    isLoading.value         = true;
    switchingEntityId.value = eu.entity_id;

    try {
        const { data } = await axios.post('/context/selectentity', { entity_id: eu.entity_id });

        selectedEntityId.value   = eu.entity_id;
        selectedEntityName.value = eu.entity_name;
        availablePlants.value    = data.plants ?? [];

        if (availablePlants.value.length === 1) {
            // Auto-select the only plant
            await confirmPlant(availablePlants.value[0].id);
        } else {
            step.value = 'plant';
        }
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Unable to switch workspace. Please try again.';
    } finally {
        isLoading.value         = false;
        switchingEntityId.value = null;
    }
};

// ──────────────────────────────
// Step 2 — pick plant
// ──────────────────────────────
const selectPlant = async (plant: { id: number }) => {
    if (isLoading.value) return;
    await confirmPlant(plant.id);
};

const confirmPlant = async (plantId: number) => {
    errorMessage.value = '';
    isLoading.value        = true;
    switchingPlantId.value = plantId;

    try {
        await axios.post('/context/selectplant', { plant_id: plantId });
        // Hard-navigate so all Inertia shared props reload with new context
        window.location.href = '/dashboard';
    } catch (error: any) {
        errorMessage.value = error.response?.data?.error || error.response?.data?.message || 'Unable to switch plant. Please try again.';
        isLoading.value        = false;
        switchingPlantId.value = null;
    }
};

// ──────────────────────────────
// Logout
// ──────────────────────────────
const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Select Workspace" />

    <main class="relative min-h-screen overflow-hidden bg-[#0a0c14] text-white">
        <!-- Next-Gen Mesh Gradient Background -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[10%] size-[60%] rounded-full bg-indigo-600/20 blur-[120px] animate-pulse"></div>
            <div class="absolute top-[20%] -right-[10%] size-[50%] rounded-full bg-teal-500/15 blur-[100px] animate-bounce" style="animation-duration: 10s;"></div>
            <div class="absolute -bottom-[10%] left-[20%] size-[40%] rounded-full bg-rose-500/10 blur-[80px] animate-pulse" style="animation-duration: 7s;"></div>
            <div class="absolute inset-0 opacity-[0.03] bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
        </div>

        <section class="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between gap-4 mb-12">
                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-gradient-to-r from-indigo-600 to-teal-500 rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                        <div class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-[#161b2c] text-sm font-black tracking-tighter text-white border border-white/10 shadow-2xl">
                            MM
                        </div>
                    </div>
                    <div>
                        <p class="text-base font-black tracking-tight text-white uppercase italic">Modo Mines</p>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">Enterprise Portal</p>
                    </div>
                </div>

                <button @click="logout" class="group relative px-6 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 transition-all duration-300">
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-300 group-hover:text-white">
                        <span>Sign out</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 group-hover:translate-x-1 transition-transform">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                    </div>
                </button>
            </header>

            <div class="flex flex-col flex-1">
                <!-- Page Title Section -->
                <div class="mb-12 text-center space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-[10px] font-black uppercase tracking-[0.3em] text-indigo-400">
                        <span class="size-2 bg-indigo-500 rounded-full animate-ping"></span>
                        Security Gateway
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-black tracking-tighter text-white">
                        {{ step === 'entity' ? 'Choose Workspace' : 'Select Facility' }}
                    </h1>
                    <p class="text-slate-400 max-w-xl mx-auto text-sm font-medium leading-relaxed">
                        {{ stepDescription }}
                    </p>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 max-w-6xl mx-auto w-full pb-12">
                    <div v-if="errorMessage" class="mb-8 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm font-bold text-center">
                        {{ errorMessage }}
                    </div>

                    <template v-if="step === 'entity'">
                        <div v-if="entityAccess.length > 0" class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="eu in entityAccess"
                                :key="eu.entity_id"
                                type="button"
                                :disabled="isLoading"
                                :class="[
                                    eu.is_active
                                        ? 'border-indigo-500/50 bg-indigo-500/5'
                                        : 'border-white/5 bg-white/[0.02] hover:bg-white/[0.05] hover:border-white/10',
                                    'group relative flex flex-col items-center p-10 rounded-[3rem] border transition-all duration-500 hover:-translate-y-2'
                                ]"
                                @click="selectEntity(eu)"
                            >
                                <!-- Glow Effect -->
                                <div :class="[eu.is_active ? 'opacity-40' : 'opacity-0 group-hover:opacity-20', 'absolute -inset-1 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-[3rem] blur-xl transition duration-500']"></div>
                                
                                <div class="relative w-full flex flex-col items-center">
                                    <!-- Active Badge -->
                                    <div v-if="eu.is_active" class="absolute -top-4 -right-4 px-3 py-1 bg-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest text-white shadow-xl">
                                        Active
                                    </div>

                                    <!-- Entity Icon -->
                                    <div class="mb-8">
                                        <div class="size-28 flex items-center justify-center rounded-[2.5rem] bg-[#161b2c] border border-white/10 shadow-2xl group-hover:scale-110 transition-transform duration-500 overflow-hidden">
                                            <img v-if="eu.entity_logo" :src="`/storage/${eu.entity_logo}`" :alt="eu.entity_name" class="size-16 object-contain" />
                                            <span v-else class="text-4xl font-black bg-gradient-to-br from-white to-slate-500 bg-clip-text text-transparent">
                                                {{ initials(eu.entity_name) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Text Content -->
                                    <div class="text-center space-y-2 mb-10 min-h-[80px]">
                                        <h3 class="text-xl font-black text-white leading-tight group-hover:text-indigo-400 transition-colors">
                                            {{ eu.entity_name }}
                                        </h3>
                                        <div class="flex items-center justify-center gap-2">
                                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ eu.entity_alias || 'Global' }}</span>
                                            <div class="size-1 bg-slate-700 rounded-full"></div>
                                            <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">{{ eu.role_name }}</span>
                                        </div>
                                    </div>

                                    <!-- Action Button -->
                                    <div class="w-full">
                                        <div :class="[eu.is_active ? 'bg-indigo-600' : 'bg-white/5 group-hover:bg-indigo-600', 'flex items-center justify-center gap-3 py-4 rounded-2xl transition-all duration-300']">
                                            <span class="text-xs font-black uppercase tracking-widest text-white">Enter Workspace</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="size-4 text-white">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- Loading -->
                                <div v-if="switchingEntityId === eu.entity_id" class="absolute inset-0 flex items-center justify-center bg-indigo-950/80 backdrop-blur-md rounded-[3rem]">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="size-12 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-400">Authenticating</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <div class="mb-8 flex justify-center">
                            <button @click="step = 'entity'" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-400 hover:text-white transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                                </svg>
                                Change Workspace
                            </button>
                        </div>

                        <div v-if="availablePlants.length > 0" class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="plant in availablePlants"
                                :key="plant.id"
                                type="button"
                                :disabled="isLoading"
                                class="group relative flex flex-col items-center p-10 rounded-[3rem] border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] hover:border-teal-500/50 transition-all duration-500 hover:-translate-y-2"
                                @click="selectPlant(plant)"
                            >
                                <div class="absolute -inset-1 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-[3rem] blur-xl opacity-0 group-hover:opacity-20 transition duration-500"></div>

                                <div class="relative w-full flex flex-col items-center">
                                    <div v-if="plant.is_main" class="absolute -top-4 -right-4 px-3 py-1 bg-teal-600 rounded-lg text-[9px] font-black uppercase tracking-widest text-white shadow-xl">
                                        Main Facility
                                    </div>

                                    <div class="mb-8">
                                        <div class="size-28 flex items-center justify-center rounded-[2.5rem] bg-[#161b2c] border border-white/10 shadow-2xl group-hover:scale-110 transition-transform duration-500 overflow-hidden">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-12 text-teal-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819" />
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="text-center space-y-2 mb-10 min-h-[80px]">
                                        <h3 class="text-xl font-black text-white leading-tight group-hover:text-teal-400 transition-colors">
                                            {{ plant.name }}
                                        </h3>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ plant.code || 'Operational Unit' }}</span>
                                    </div>

                                    <div class="w-full">
                                        <div class="bg-white/5 group-hover:bg-teal-600 flex items-center justify-center gap-3 py-4 rounded-2xl transition-all duration-300">
                                            <span class="text-xs font-black uppercase tracking-widest text-white">Start Session</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="size-4 text-white">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="switchingPlantId === plant.id" class="absolute inset-0 flex items-center justify-center bg-teal-950/80 backdrop-blur-md rounded-[3rem]">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="size-12 border-4 border-teal-500 border-t-transparent rounded-full animate-spin"></div>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-teal-400">Loading Facility</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </main>
</template>
