<template>
    <Head title="Modo Portal | Select Workspace" />

    <main class="min-h-screen bg-[#F8FAFC] font-outfit text-slate-900 selection:bg-indigo-100 overflow-hidden flex flex-col">
        <!-- Modern Decorative Background -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden">
            <div class="absolute -top-[10%] -left-[10%] size-[600px] bg-indigo-500/5 blur-[120px] rounded-full animate-pulse"></div>
            <div class="absolute top-[20%] -right-[5%] size-[500px] bg-blue-500/5 blur-[100px] rounded-full [animation-delay:2s]"></div>
        </div>

        <!-- Premium Header -->
        <header class="relative z-50 flex items-center justify-between px-10 py-6 bg-white/70 backdrop-blur-xl border-b border-slate-200">
            <div class="flex items-center gap-12">
                <!-- Brand -->
                <div class="flex items-center gap-4 group cursor-pointer">
                    <div class="size-11 flex items-center justify-center rounded-2xl bg-indigo-600 shadow-[0_10px_25px_-5px_rgba(79,70,229,0.4)] group-hover:scale-105 transition-transform duration-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-white">
                            <path d="M12.378 1.602a.75.75 0 00-.756 0L3 6.632l9 5.25 9-5.25-8.622-5.03zM21.75 7.93l-9 5.25v9l8.628-5.032a.75.75 0 00.372-.648V7.93zM11.25 22.18v-9l-9-5.25v8.57a.75.75 0 00.372.648l8.628 5.033z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-lg font-black tracking-tight text-slate-900 leading-none">MODO</span>
                        <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest mt-0.5">Portal Hub</span>
                    </div>
                </div>

                <!-- Step Progress -->
                <nav class="hidden md:flex items-center gap-10">
                    <div class="flex items-center gap-3">
                        <div :class="[step === 'entity' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'bg-slate-100 text-slate-400', 'size-7 rounded-full flex items-center justify-center text-xs font-black transition-all']">1</div>
                        <span :class="[step === 'entity' ? 'text-slate-900' : 'text-slate-400', 'text-[11px] font-black uppercase tracking-widest transition-colors']">Organization</span>
                    </div>
                    <div class="w-10 h-px bg-slate-200"></div>
                    <div class="flex items-center gap-3">
                        <div :class="[step === 'plant' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'bg-slate-100 text-slate-400', 'size-7 rounded-full flex items-center justify-center text-xs font-black transition-all']">2</div>
                        <span :class="[step === 'plant' ? 'text-slate-900' : 'text-slate-400', 'text-[11px] font-black uppercase tracking-widest transition-colors']">Facility Access</span>
                    </div>
                </nav>
            </div>

            <div class="flex items-center gap-8">
                <!-- User Profile -->
                <div class="flex items-center gap-4 py-1.5 pl-1.5 pr-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="size-9 rounded-xl bg-white shadow-sm flex items-center justify-center border border-slate-200">
                        <span class="text-xs font-black text-indigo-600">{{ initials(userName) }}</span>
                    </div>
                    <div class="hidden sm:block leading-none">
                        <p class="text-xs font-black text-slate-900 tracking-tight">{{ userName }}</p>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Authorized User</p>
                    </div>
                </div>

                <button @click="logout" class="group size-11 rounded-2xl bg-white border border-slate-200 hover:border-rose-200 hover:bg-rose-50 transition-all duration-300 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-5 text-slate-400 group-hover:text-rose-500 transition-colors">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Main Workspace Hub -->
        <div class="relative z-40 flex-1 flex flex-col lg:flex-row overflow-hidden">
            
            <!-- Sidebar: Navigation List -->
            <aside class="w-full lg:w-[420px] flex flex-col bg-white border-r border-slate-200 shadow-[10px_0_30px_rgba(0,0,0,0.02)]">
                <div class="p-8 space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Your Organizations</h3>
                        <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-[10px] font-black text-indigo-600">
                            {{ filteredEntities.length }} Total
                        </span>
                    </div>

                    <!-- Search -->
                    <div class="relative group">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="absolute left-5 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input 
                            v-model="entitySearch" 
                            type="text" 
                            placeholder="Find organization..." 
                            class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 pl-14 pr-6 text-sm font-bold text-slate-900 placeholder:text-slate-300 focus:ring-2 focus:ring-indigo-500/10 transition-all shadow-sm"
                        />
                    </div>
                </div>

                <!-- Entity Scrollable List -->
                <div class="flex-1 overflow-y-auto scrollbar-hide px-6 pb-8 space-y-3">
                    <div 
                        v-for="eu in filteredEntities" 
                        :key="eu.entity_id"
                        @click="selectEntity(eu)"
                        class="group relative p-4 rounded-[1.5rem] transition-all duration-500 cursor-pointer overflow-hidden border"
                        :class="[selectedEntityId === eu.entity_id ? 'bg-indigo-600 shadow-xl shadow-indigo-200 border-indigo-700' : 'bg-white hover:bg-slate-50 border-slate-200 hover:border-slate-300']"
                    >
                        <div class="relative flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <div :class="[selectedEntityId === eu.entity_id ? 'bg-white/20' : 'bg-slate-50 border border-slate-200', 'size-14 rounded-2xl flex items-center justify-center p-2 transition-colors']">
                                    <img v-if="eu.entity_logo" :src="`/storage/${eu.entity_logo}`" class="size-full object-contain" />
                                    <span v-else :class="[selectedEntityId === eu.entity_id ? 'text-white' : 'text-slate-400', 'text-lg font-black']">{{ initials(eu.entity_name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h4 :class="[selectedEntityId === eu.entity_id ? 'text-white' : 'text-slate-900', 'text-sm font-black truncate tracking-tight uppercase']">{{ eu.entity_name }}</h4>
                                    <p :class="[selectedEntityId === eu.entity_id ? 'text-indigo-100' : 'text-slate-400', 'text-[10px] font-bold uppercase tracking-widest mt-1']">{{ eu.role_name }}</p>
                                </div>
                            </div>
                            
                            <button @click.stop="openEntityDetails(eu)" :class="[selectedEntityId === eu.entity_id ? 'bg-white/20 text-white' : 'bg-slate-50 border border-slate-200 text-slate-300 group-hover:text-slate-600', 'p-2.5 rounded-xl transition-all']">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content: Facility Grid -->
            <section class="flex-1 flex flex-col bg-[#FCFDFF]">
                <!-- Panel Header -->
                <div class="px-10 py-8 flex flex-wrap items-center justify-between gap-10 border-b border-slate-100 bg-white/30">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <span class="text-[11px] font-black text-indigo-500 uppercase tracking-[0.2em]">Select Facility</span>
                            <div class="size-1.5 bg-indigo-500 rounded-full animate-pulse"></div>
                        </div>
                        <h2 class="text-xl md:text-xl  text-slate-900   uppercase ">
                            {{ selectedEntityName ? selectedEntityName : 'Choose Organization' }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="group flex items-center gap-3 cursor-pointer py-3 px-5 rounded-2xl bg-white border border-slate-200 shadow-sm hover:border-indigo-300 transition-all">
                            <div class="relative w-10 h-5 bg-slate-100 rounded-full border border-slate-300 transition-colors group-hover:border-indigo-400">
                                <input type="checkbox" v-model="setAsDefault" class="peer sr-only" />
                                <div class="absolute inset-y-0.5 left-0.5 w-4 bg-white shadow-sm rounded-full transition-all peer-checked:left-5 peer-checked:bg-indigo-600"></div>
                            </div>
                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest group-hover:text-slate-900 transition-colors">Set as Default</span>
                        </label>

                        <div class="relative group w-full md:w-[320px]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="absolute left-5 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input 
                                v-model="plantSearch" 
                                type="text" 
                                placeholder="Search facilities..." 
                                class="w-full bg-white border border-slate-200 rounded-2xl py-4 pl-14 pr-6 text-sm font-bold text-slate-900 placeholder:text-slate-300 focus:ring-2 focus:ring-indigo-500/10 transition-all shadow-sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center space-y-8 animate-in fade-in duration-700">
                    <div class="relative size-20">
                        <div class="absolute inset-0 border-4 border-slate-100 rounded-full"></div>
                        <div class="absolute inset-0 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <div class="text-center space-y-1">
                        <p class="text-[11px] font-black uppercase tracking-[0.4em] text-indigo-500">Mapping Hubs</p>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Synchronizing available nodes...</p>
                    </div>
                </div>

                <div v-else-if="filteredPlants.length > 0" class="flex-1 overflow-y-auto p-10 scrollbar-hide bg-slate-50/30">
                    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 animate-in fade-in slide-in-from-bottom-8 duration-700">
                        <button 
                            v-for="plant in filteredPlants" 
                            :key="plant.id"
                            @click="selectPlant(plant)"
                            class="group relative h-48 flex flex-col justify-between p-8 rounded-[2.5rem] bg-white border border-slate-200 hover:border-indigo-500 hover:shadow-[0_20px_50px_rgba(79,70,229,0.08)] transition-all duration-500 text-left overflow-hidden shadow-sm"
                            :class="{ 'border-indigo-600 ring-2 ring-indigo-500/10 shadow-2xl': defaults.plant_id === plant.id }"
                        >
                            <div class="flex items-start justify-between">
                                <div class="size-14 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white group-hover:scale-110 transition-all duration-500 shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819" />
                                    </svg>
                                </div>
                                <button @click.stop="openPlantDetails(plant)" class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-300 hover:text-slate-600 transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="space-y-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ plant.code || 'SYS-FACILITY' }}</span>
                                <h4 class="text-xl font-black text-slate-900 tracking-tight uppercase group-hover:text-indigo-600 transition-colors">{{ plant.name }}</h4>
                            </div>

                            <div v-if="plant.is_main" class="absolute bottom-8 right-8">
                                <div class="px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-[9px] font-black text-emerald-600 uppercase tracking-widest shadow-sm">
                                    Master Unit
                                </div>
                            </div>

                            <div v-if="switchingPlantId === plant.id" class="absolute inset-0 bg-white/90 backdrop-blur-md flex items-center justify-center z-20">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="size-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600">Accessing...</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-12">
                    <div class="size-40 bg-white border border-slate-200 rounded-[3rem] flex items-center justify-center mb-10 shadow-sm relative group">
                        <div class="absolute inset-4 bg-slate-50 border border-slate-100 rounded-[2rem] animate-pulse"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 text-slate-200 relative z-10">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <h4 class="text-2xl font-black text-slate-900 mb-3 uppercase tracking-tighter italic">Selection Required</h4>
                    <p class="text-slate-400 text-[11px] font-bold max-w-xs mx-auto uppercase tracking-widest leading-relaxed">Choose an organization from the sidebar to map available facilities and operational nodes.</p>
                </div>
            </section>
        </div>

        <!-- Details Overlay (SaaS Modal) -->
        <div v-if="detailModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/40 backdrop-blur-md animate-in fade-in duration-500">
            <div class="bg-white rounded-[3.5rem] shadow-[0_50px_100px_-20px_rgba(0,0,0,0.15)] max-w-2xl w-full overflow-hidden animate-in zoom-in-95 duration-500 border border-slate-200">
                <div class="p-12 space-y-12">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-6">
                            <div class="size-20 bg-indigo-50 rounded-[2rem] flex items-center justify-center border border-indigo-100 shadow-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-10 text-indigo-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </div>
                            <div class="space-y-1">
                                <h3 class="text-3xl font-black text-slate-900 tracking-tighter uppercase italic leading-none">{{ detailModal.title }} Details</h3>
                                <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.4em]">Internal System Record</p>
                            </div>
                        </div>
                        <button @click="detailModal.show = false" class="size-14 rounded-[1.5rem] bg-slate-50 border border-slate-200 hover:bg-rose-50 text-slate-300 hover:text-rose-500 transition-all duration-500 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div v-for="(val, label) in detailModal.data" :key="label" class="p-8 rounded-[2rem] bg-slate-50 border border-slate-200 group hover:bg-white hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-500/5 transition-all duration-500">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 group-hover:text-indigo-500 transition-colors">{{ label }}</p>
                            <p class="text-base font-black text-slate-900 uppercase tracking-tight">{{ val || 'NOT_FOUND' }}</p>
                        </div>
                    </div>

                    <button @click="detailModal.show = false" class="w-full py-7 bg-slate-900 hover:bg-indigo-600 rounded-[2.5rem] text-xs font-black uppercase tracking-[0.5em] text-white shadow-xl hover:shadow-indigo-500/30 transition-all duration-500 border border-slate-800">
                        Close Profile
                    </button>
                </div>
            </div>
        </div>

    </main>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

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
        address?: string;
        phone?: string;
        email?: string;
    }>;
    defaults: {
        entity_id: number | null;
        plant_id: number | null;
    };
}>();

// ──────────────────────────────
// Search & Filter
// ──────────────────────────────
const entitySearch = ref('');
const plantSearch  = ref('');

const filteredEntities = computed(() => {
    if (!entitySearch.value) return props.entityAccess;
    const q = entitySearch.value.toLowerCase();
    return props.entityAccess.filter(e => 
        e.entity_name.toLowerCase().includes(q) || 
        e.entity_alias?.toLowerCase().includes(q) || 
        e.entity_id.toString().includes(q)
    );
});

const filteredPlants = computed(() => {
    if (!plantSearch.value) return availablePlants.value;
    const q = plantSearch.value.toLowerCase();
    return availablePlants.value.filter(p => 
        p.name.toLowerCase().includes(q) || 
        p.code?.toLowerCase().includes(q) ||
        p.id.toString().includes(q)
    );
});

// ──────────────────────────────
// Detail Modal
// ──────────────────────────────
const detailModal = ref({
    show: false,
    title: '',
    data: {} as Record<string, any>
});

const openEntityDetails = (eu: any) => {
    detailModal.value = {
        show: true,
        title: 'Node',
        data: {
            'Identity': eu.entity_name,
            'Global Alias': eu.entity_alias,
            'Privilege Level': eu.role_name,
            'Sequence ID': eu.entity_id,
            'Operational HQ': eu.address,
            'Contact Vector': eu.phone || eu.email
        }
    };
};

const openPlantDetails = (p: any) => {
    detailModal.value = {
        show: true,
        title: 'Facility',
        data: {
            'Module Name': p.name,
            'Access Code': p.code,
            'Node ID': p.id,
            'Protocol': p.is_main ? 'MASTER_LINK' : 'STANDARD_UNIT',
            'Connection': 'ENCRYPTED'
        }
    };
};

// ──────────────────────────────
// Selection Logic
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

const setAsDefault = ref(false);

const userName = computed(() => {
    const user = page.props.auth?.user;
    return user?.name || user?.username || 'User';
});

const initials = (name: string) => name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');

const selectEntity = async (eu: typeof props.entityAccess[0]) => {
    if (isLoading.value) return;
    if (selectedEntityId.value === eu.entity_id) return;

    errorMessage.value = '';
    isLoading.value         = true;
    switchingEntityId.value = eu.entity_id;

    try {
        const { data } = await axios.post('/context/selectentity', { entity_id: eu.entity_id });

        selectedEntityId.value   = eu.entity_id;
        selectedEntityName.value = eu.entity_name;
        availablePlants.value    = data.plants ?? [];
        step.value = 'plant';
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Handshake failed.';
    } finally {
        isLoading.value         = false;
        switchingEntityId.value = null;
    }
};

const selectPlant = async (plant: { id: number }) => {
    if (isLoading.value) return;
    await confirmPlant(plant.id);
};

const confirmPlant = async (plantId: number) => {
    errorMessage.value = '';
    isLoading.value        = true;
    switchingPlantId.value = plantId;

    try {
        await axios.post('/context/selectplant', { 
            plant_id: plantId,
            set_as_default: setAsDefault.value
        });
        window.location.href = '/dashboard';
    } catch (error: any) {
        errorMessage.value = error.response?.data?.error || error.response?.data?.message || 'Uplink failed.';
        isLoading.value        = false;
        switchingPlantId.value = null;
    }
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100;300;400;500;700;900&display=swap');

.font-outfit {
    font-family: 'Outfit', sans-serif;
}

/* Light Theme Optimization */
:global(body) {
    background-color: #F8FAFC;
}

/* Scrollbar Styles */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}
.animate-shake {
    animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
}

/* Entrance Animations */
.animate-in {
    animation-fill-mode: both;
}
</style>
