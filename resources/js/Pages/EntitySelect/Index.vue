<template>
    <Head title="Modo Portal | Select Workspace" />

    <main class="min-h-screen bg-[#f8fafc] font-outfit text-slate-800 selection:bg-indigo-100 selection:text-indigo-900 flex flex-col relative overflow-hidden">
        <!-- Polygonal Background Pattern Overlay (Top-Left) -->
        <div class="absolute top-0 left-0 w-[50%] h-[35%] opacity-[0.04] pointer-events-none z-0">
            <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <polygon points="0,0 40,0 0,60" fill="currentColor"/>
                <polygon points="40,0 80,0 50,50" fill="currentColor"/>
                <polygon points="0,60 50,50 15,90" fill="currentColor"/>
                <polygon points="50,50 80,0 100,30" fill="currentColor"/>
            </svg>
        </div>

        <!-- Polygonal Background Pattern Overlay (Top-Right) -->
        <div class="absolute top-0 right-0 w-[50%] h-[35%] opacity-[0.04] pointer-events-none z-0">
            <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <polygon points="100,0 60,0 100,60" fill="currentColor"/>
                <polygon points="60,0 20,0 50,50" fill="currentColor"/>
                <polygon points="100,60 50,50 85,90" fill="currentColor"/>
                <polygon points="50,50 20,0 0,30" fill="currentColor"/>
            </svg>
        </div>

        <!-- Header -->
        <header class="relative z-10 border-b border-slate-100 bg-white/70 backdrop-blur-xl">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <div class="flex items-center gap-3">
                    <!-- 9-dot Zoho-like launcher icon -->
                    <div class="grid grid-cols-3 gap-1 size-10 items-center justify-center p-1.5 rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm">
                        <span class="size-2 rounded-full bg-red-500"></span>
                        <span class="size-2 rounded-full bg-blue-500"></span>
                        <span class="size-2 rounded-full bg-green-500"></span>
                        <span class="size-2 rounded-full bg-yellow-500"></span>
                        <span class="size-2 rounded-full bg-purple-500"></span>
                        <span class="size-2 rounded-full bg-pink-500"></span>
                        <span class="size-2 rounded-full bg-teal-500"></span>
                        <span class="size-2 rounded-full bg-indigo-500"></span>
                        <span class="size-2 rounded-full bg-slate-500"></span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-indigo-600">Modo Portal</p>
                        <h1 class="text-base font-extrabold tracking-tight text-slate-900">Choose your workspace</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-2.5 rounded-2xl border border-slate-100 bg-white px-3 py-2 shadow-sm sm:flex">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-600">
                            {{ initials(userName) }}
                        </div>
                        <p class="max-w-[10rem] truncate text-xs font-bold text-slate-700">{{ userName }}</p>
                    </div>

                    <button
                        type="button"
                        @click="logout"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-100 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 shadow-sm hover:border-red-200 hover:bg-red-50 hover:text-red-600 transition duration-300"
                        title="Log out"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <span class="hidden sm:inline uppercase tracking-wider">Log out</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <section class="relative z-10 mx-auto w-full max-w-6xl flex-1 px-6 py-10 flex flex-col justify-center">
            <!-- Breadcrumbs / Steps Indicator -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-10">
                <div class="flex items-center gap-3">
                    <button
                        v-if="step === 'plant'"
                        type="button"
                        @click="goToEntityStep"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-widest text-slate-500 hover:text-indigo-600 bg-white border border-slate-100 rounded-full shadow-sm hover:shadow-md transition duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Organizations
                    </button>

                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">
                        {{ step === 'entity' ? 'Organizations' : `${selectedEntityName} / Facilities` }}
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Remember selection toggle (only in Plant step) -->
                    <label v-if="step === 'plant'" class="flex items-center gap-3 bg-white border border-slate-100 rounded-2xl px-4 py-2 shadow-sm cursor-pointer select-none">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Remember selection</span>
                        <div class="relative shrink-0">
                            <input id="default-toggle" v-model="setAsDefault" type="checkbox" class="peer sr-only" />
                            <div class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-indigo-600"></div>
                            <div class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607 0 7.5 7.5 0 0 0 10.607 0Z" />
                        </svg>
                        <input
                            v-if="step === 'entity'"
                            v-model="entitySearch"
                            type="text"
                            placeholder="Search organization..."
                            class="w-full rounded-2xl border border-slate-100 bg-white py-2 pl-10 pr-4 text-xs font-bold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                        />
                        <input
                            v-else
                            v-model="plantSearch"
                            type="text"
                            placeholder="Search facility..."
                            class="w-full rounded-2xl border border-slate-100 bg-white py-2 pl-10 pr-4 text-xs font-bold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                        />
                    </div>
                </div>
            </div>

            <!-- Error message banner -->
            <div v-if="errorMessage" class="mb-8 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 max-w-4xl mx-auto w-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="mt-0.5 size-5 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <p class="font-semibold">{{ errorMessage }}</p>
            </div>

            <!-- Zoho-style Apps Grid -->
            <!-- Step 1: Organizations -->
            <div v-if="step === 'entity'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-8 justify-center">
                <div
                    v-for="(eu, index) in filteredEntities"
                    :key="eu.entity_id"
                    class="group relative flex flex-col items-center justify-center p-6 bg-transparent border border-transparent rounded-[24px] transition-all duration-300 hover:bg-slate-100/60 hover:border-slate-200/50 hover:-translate-y-1 cursor-pointer select-none"
                    :class="[
                        eu.is_suspended !== 0 ? 'opacity-65' : '',
                        selectedEntityId === eu.entity_id ? 'bg-indigo-50/40 border-indigo-100/60 shadow-[0_4px_20px_-10px_rgba(79,70,229,0.15)]' : ''
                    ]"
                    @click="selectEntity(eu)"
                >
                    <!-- Small hovering utility actions at top-right of the card -->
                    <div class="absolute top-2 right-2 flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20" @click.stop>
                        <button
                            type="button"
                            @click="toggleSuspension(eu)"
                            class="inline-flex size-7 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-red-600 hover:bg-red-50 hover:border-red-100 shadow-sm transition"
                            :title="eu.is_suspended !== 0 ? 'Reactivate' : 'Suspend'"
                        >
                            <svg v-if="eu.is_suspended !== 0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="openEntityDetails(eu)"
                            class="inline-flex size-7 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-100 shadow-sm transition"
                            title="Details"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Organization logo or outline vector icon -->
                    <div class="size-16 flex items-center justify-center rounded-2xl transition-transform duration-300 group-hover:scale-110 mb-3 select-none pointer-events-none">
                        <img v-if="eu.entity_logo" :src="`/storage/${eu.entity_logo}`" :alt="eu.entity_name" class="size-full object-contain p-1" />
                        <div v-else class="size-full flex items-center justify-center text-slate-700" v-html="getWorkspaceIcon(index)"></div>
                    </div>

                    <!-- Label -->
                    <h3 class="text-[13px] font-medium text-slate-700 tracking-wide text-center group-hover:text-slate-900 w-full truncate mb-0.5">
                        {{ eu.entity_name }}
                    </h3>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wide text-center w-full truncate mb-2">
                        {{ eu.entity_alias || eu.role_name }}
                    </p>

                    <!-- Maintenance / Inactive markers -->
                    <span v-if="eu.is_suspended !== 0" class="px-2 py-0.5 rounded-full bg-red-50 border border-red-100 text-[8px] font-black uppercase tracking-wider text-red-600 scale-90">
                        Suspended
                    </span>
                    <span v-else-if="eu.is_active" class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-100 text-[8px] font-black uppercase tracking-wider text-emerald-600 scale-90">
                        Active
                    </span>

                    <!-- Loading overlay for specific card -->
                    <div v-if="switchingEntityId === eu.entity_id" class="absolute inset-0 flex items-center justify-center bg-white/95 backdrop-blur-sm rounded-[24px] z-10">
                        <div class="size-6 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent"></div>
                    </div>
                </div>
            </div>

            <!-- Empty state Organizations -->
            <div v-if="step === 'entity' && filteredEntities.length === 0" class="max-w-md mx-auto rounded-[32px] border border-dashed border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607 0 7.5 7.5 0 0 0 10.607 0Z" />
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-900 tracking-tight">No organizations found</h4>
                <p class="mt-2 text-xs leading-5 text-slate-500">We couldn't find any organization matching "{{ entitySearch }}".</p>
            </div>

            <!-- Step 2: Facilities -->
            <div v-if="step === 'plant'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-8 justify-center">
                <div
                    v-for="(plant, index) in filteredPlants"
                    :key="plant.id"
                    class="group relative flex flex-col items-center justify-center p-6 bg-transparent border border-transparent rounded-[24px] transition-all duration-300 hover:bg-slate-100/60 hover:border-slate-200/50 hover:-translate-y-1 cursor-pointer select-none"
                    :class="[
                        plant.is_active === -1 ? 'opacity-65' : '',
                        defaults.plant_id === plant.id ? 'bg-indigo-50/40 border-indigo-100/60 shadow-[0_4px_20px_-10px_rgba(79,70,229,0.15)]' : ''
                    ]"
                    @click="selectPlant(plant)"
                >
                    <!-- Small hovering utility actions at top-right of the card -->
                    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20" @click.stop>
                        <button
                            type="button"
                            @click="openPlantDetails(plant)"
                            class="inline-flex size-7 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-100 shadow-sm transition"
                            title="Details"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Facility logo or outline building icon -->
                    <div class="size-16 flex items-center justify-center rounded-2xl transition-transform duration-300 group-hover:scale-110 mb-3 select-none pointer-events-none">
                        <img v-if="plant.logo_path" :src="`/storage/${plant.logo_path}`" :alt="plant.name" class="size-full object-contain p-1" />
                        <div v-else class="size-full flex items-center justify-center text-slate-700" v-html="getWorkspaceIcon(index + 3)"></div>
                    </div>

                    <!-- Label -->
                    <h3 class="text-[13px] font-medium text-slate-700 tracking-wide text-center group-hover:text-slate-900 w-full truncate mb-0.5">
                        {{ plant.name }}
                    </h3>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wide text-center w-full truncate mb-2">
                        {{ plant.code || 'SYS-FACILITY' }}
                    </p>

                    <!-- Default / Inactive markers -->
                    <span v-if="defaults.plant_id === plant.id" class="px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-100 text-[8px] font-black uppercase tracking-wider text-indigo-600 scale-90">
                        Default
                    </span>
                    <span v-else-if="plant.is_active === -1" class="px-2 py-0.5 rounded-full bg-red-50 border border-red-100 text-[8px] font-black uppercase tracking-wider text-red-600 scale-90">
                        Inactive
                    </span>

                    <!-- Loading overlay for specific card -->
                    <div v-if="switchingPlantId === plant.id" class="absolute inset-0 flex items-center justify-center bg-white/95 backdrop-blur-sm rounded-[24px] z-10">
                        <div class="size-6 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent"></div>
                    </div>
                </div>
            </div>

            <!-- Empty state Facilities -->
            <div v-if="step === 'plant' && filteredPlants.length === 0" class="max-w-md mx-auto rounded-[32px] border border-dashed border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 21V7.5A2.25 2.25 0 0 1 6.75 5.25h10.5A2.25 2.25 0 0 1 19.5 7.5V21M9 10.5h6M9 14.25h6M9 18h6" />
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-900 tracking-tight">No facilities found</h4>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    {{ plantCount === 0 ? 'No active facilities are assigned to this organization.' : 'Try a different facility name or code.' }}
                </p>
            </div>
        </section>

        <!-- Premium Workspace Details Modal -->
        <div v-if="detailModal.show" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/20 p-4 backdrop-blur-md">
            <div class="w-full max-w-md overflow-hidden rounded-[36px] bg-white/90 border border-white/60 shadow-[0_30px_70px_-20px_rgba(15,23,42,0.3)]">
                <div class="px-6 pt-8 pb-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">{{ detailModal.title }} details</p>
                            <h3 class="text-xl font-extrabold tracking-tight text-slate-950 mt-1">{{ detailModal.name }}</h3>
                        </div>
                        <button
                            type="button"
                            @click="detailModal.show = false"
                            class="inline-flex size-9 items-center justify-center rounded-2xl border border-slate-100 bg-white text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4 space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-100 bg-slate-50">
                            <img v-if="detailModal.logo" :src="detailModal.logo" :alt="detailModal.name" class="size-full object-contain p-1.5" />
                            <span v-else class="text-lg font-black text-indigo-600">{{ initials(detailModal.name) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-extrabold tracking-tight text-slate-900 truncate">{{ detailModal.name }}</p>
                            <p class="text-xs text-slate-500 mt-0.5 truncate">
                                {{ detailModal.type === 'entity' ? detailModal.role : `Facility code: ${detailModal.code}` }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">System ID</p>
                            <p class="text-sm font-extrabold text-slate-800 mt-1">#{{ detailModal.id }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">
                                {{ detailModal.type === 'entity' ? 'Alias' : 'Status' }}
                            </p>
                            <p class="text-sm font-extrabold text-slate-800 mt-1 truncate">
                                {{ detailModal.type === 'entity' ? detailModal.alias : detailModal.is_main ? 'Primary facility' : 'Standard facility' }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-2xl border border-slate-100 bg-white px-4 py-3">
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Registered Address</p>
                            <p class="text-xs leading-5 text-slate-600 mt-1.5">{{ detailModal.address || 'No registered address data.' }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-slate-100 bg-white px-4 py-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Phone</p>
                                <p class="text-xs font-semibold text-slate-600 mt-1.5">{{ detailModal.phone || 'Not available' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-white px-4 py-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Email</p>
                                <p class="text-xs font-semibold text-slate-600 mt-1.5 break-all">{{ detailModal.email || 'Not available' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 bg-slate-50/50 px-6 py-4 mt-6">
                    <button
                        type="button"
                        @click="detailModal.show = false"
                        class="rounded-2xl bg-slate-900 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800 transition"
                    >
                        Close
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

interface EntityAccess {
    entity_id: number;
    entity_name: string;
    entity_alias?: string;
    entity_logo?: string;
    role_name: string;
    is_active: boolean;
    is_suspended: number;
    address?: string;
    phone?: string;
    email?: string;
}

interface PlantOption {
    id: number;
    name: string;
    code: string | null;
    is_main: boolean;
    is_active: number;
    email_address?: string | null;
    mobile_number?: string | null;
    logo_path?: string | null;
}

type DetailType = 'entity' | 'plant';
type StepKey = 'entity' | 'plant';

const props = defineProps<{
    entityAccess: EntityAccess[];
    defaults: {
        entity_id: number | null;
        plant_id: number | null;
    };
}>();

const page = usePage();
const step = ref<StepKey>('entity');
const entitySearch = ref('');
const plantSearch = ref('');
const errorMessage = ref('');
const isLoading = ref(false);
const switchingEntityId = ref<number | null>(null);
const switchingPlantId = ref<number | null>(null);
const selectedEntityId = ref<number | null>(props.defaults.entity_id ?? null);
const selectedEntityName = ref('');
const availablePlants = ref<PlantOption[]>([]);
const setAsDefault = ref(false);

const detailModal = ref({
    show: false,
    type: 'entity' as DetailType,
    title: '',
    name: '',
    logo: null as string | null,
    alias: '',
    id: 0,
    role: '',
    address: '',
    phone: '',
    email: '',
    code: '',
    is_main: false,
});

// Outlined SVG paths (like Zoho-style brand outline icons)
const iconList = [
    // 1. Chart / Analytics
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 52H52" stroke="#475569" stroke-width="3" stroke-linecap="round"/>
        <path d="M16 52V20" stroke="#475569" stroke-width="3" stroke-linecap="round"/>
        <path d="M16 40L28 24L40 34L52 14" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M42 14H52V24" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`,
    // 2. Books
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M18 10H38C46 10 46 26 38 26C48 26 48 48 38 48H18V10Z" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="30" cy="18" r="4.5" fill="#ef4444"/>
        <circle cx="30" cy="38" r="4.5" fill="#1fa851"/>
        <line x1="18" y1="26" x2="34" y2="26" stroke="#2d7bf4" stroke-width="3"/>
    </svg>`,
    // 3. Calendar
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="12" y="16" width="40" height="36" rx="6" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="22" y1="10" x2="22" y2="18" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="42" y1="10" x2="42" y2="18" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="12" y1="26" x2="52" y2="26" stroke="#2d7bf4" stroke-width="3"/>
        <circle cx="32" cy="38" r="5" fill="#f4b301"/>
    </svg>`,
    // 4. Connect / Network
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="14" r="7" stroke="#2d7bf4" stroke-width="3"/>
        <circle cx="16" cy="42" r="7" stroke="#ef4444" stroke-width="3"/>
        <circle cx="48" cy="42" r="7" stroke="#f4b301" stroke-width="3"/>
        <line x1="27.5" y1="19.5" x2="20.5" y2="36.5" stroke="#475569" stroke-width="3"/>
        <line x1="36.5" y1="19.5" x2="43.5" y2="36.5" stroke="#475569" stroke-width="3"/>
        <line x1="23" y1="42" x2="41" y2="42" stroke="#1fa851" stroke-width="3" stroke-dasharray="2 2"/>
    </svg>`,
    // 5. Creator
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="10" y="10" width="30" height="30" rx="6" stroke="#2d7bf4" stroke-width="3.5"/>
        <rect x="24" y="24" width="30" height="30" rx="6" stroke="#ef4444" stroke-width="3.5"/>
        <circle cx="40" cy="40" r="3.5" fill="#f4b301"/>
    </svg>`,
    // 6. CRM
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M22 22C12 22 12 42 22 42C28 42 36 22 42 22C52 22 52 42 42 42C36 42 28 22 22 22Z" stroke="#2d7bf4" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M32 32L38 38" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
        <path d="M32 32L26 26" stroke="#1fa851" stroke-width="3" stroke-linecap="round"/>
    </svg>`,
    // 7. Desk / Phone
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 12H24L28 22L22 26C26 34 30 38 38 42L42 36L52 40V48C52 50.2 50.2 52 48 52C26 52 12 38 12 16C12 13.8 13.8 12 16 12Z" stroke="#1fa851" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M38 16C44 18 48 22 50 28" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
    </svg>`,
    // 8. Inventory
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="20" cy="52" r="4.5" fill="#ef4444"/>
        <circle cx="48" cy="52" r="4.5" fill="#ef4444"/>
        <path d="M6 12H14L22 40H48L56 18H18" stroke="#ef4444" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="30" y1="24" x2="44" y2="24" stroke="#f4b301" stroke-width="3" stroke-linecap="round"/>
    </svg>`,
    // 9. Invoice
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="12" y="10" width="40" height="44" rx="6" stroke="#2d7bf4" stroke-width="3"/>
        <line x1="20" y1="20" x2="44" y2="20" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round"/>
        <line x1="20" y1="30" x2="44" y2="30" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round"/>
        <line x1="20" y1="40" x2="34" y2="40" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round"/>
    </svg>`,
    // 10. Mail
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="10" y="14" width="44" height="36" rx="6" stroke="#2d7bf4" stroke-width="3"/>
        <path d="M10 16L32 32L54 16" stroke="#ef4444" stroke-width="3" stroke-linejoin="round"/>
        <line x1="10" y1="48" x2="24" y2="34" stroke="#2d7bf4" stroke-width="3"/>
        <line x1="54" y1="48" x2="40" y2="34" stroke="#2d7bf4" stroke-width="3"/>
    </svg>`,
    // 11. Meeting
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="20" r="10" stroke="#2d7bf4" stroke-width="3"/>
        <circle cx="20" cy="38" r="10" stroke="#ef4444" stroke-width="3"/>
        <circle cx="44" cy="38" r="10" stroke="#1fa851" stroke-width="3"/>
        <circle cx="32" cy="32" r="4" fill="#f4b301"/>
    </svg>`,
    // 12. Notebook
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="16" y="10" width="36" height="44" rx="6" stroke="#f37021" stroke-width="3"/>
        <line x1="10" y1="18" x2="16" y2="18" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round"/>
        <line x1="10" y1="28" x2="16" y2="28" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round"/>
        <line x1="10" y1="38" x2="16" y2="38" stroke="#2d7bf4" stroke-width="3" stroke-linecap="round"/>
        <circle cx="34" cy="32" r="3.5" fill="#ef4444"/>
    </svg>`,
    // 13. PDF Editor
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M14 10H38L50 22V54H14V10Z" stroke="#ef4444" stroke-width="3" stroke-linejoin="round"/>
        <path d="M38 10V22H50" stroke="#ef4444" stroke-width="3"/>
        <path d="M24 38L42 20" stroke="#2d7bf4" stroke-width="3.5" stroke-linecap="round"/>
        <circle cx="24" cy="38" r="2.5" fill="#2d7bf4"/>
    </svg>`,
    // 14. People
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="32" r="6" stroke="#2d7bf4" stroke-width="3"/>
        <path d="M32 8L48 18V38L32 48L16 38V18L32 8Z" stroke="#1fa851" stroke-width="3" stroke-linejoin="round"/>
        <circle cx="32" cy="8" r="4.5" fill="#ef4444"/>
        <circle cx="48" cy="38" r="4.5" fill="#f4b301"/>
        <circle cx="16" cy="38" r="4.5" fill="#2d7bf4"/>
    </svg>`,
    // 15. Projects
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 32L24 44L48 16" stroke="#1fa851" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M20 38L28 46L52 18" stroke="#2d7bf4" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`,
    // 16. Sheet
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="12" y="12" width="40" height="40" rx="6" stroke="#1fa851" stroke-width="3"/>
        <line x1="12" y1="25" x2="52" y2="25" stroke="#1fa851" stroke-width="3"/>
        <line x1="12" y1="38" x2="52" y2="38" stroke="#1fa851" stroke-width="3"/>
        <line x1="25" y1="12" x2="25" y2="52" stroke="#1fa851" stroke-width="3"/>
        <line x1="38" y1="12" x2="38" y2="52" stroke="#1fa851" stroke-width="3"/>
        <polygon points="42,32 46,30 46,34" fill="#ef4444"/>
    </svg>`,
    // 17. Show
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="10" y="14" width="44" height="32" rx="6" stroke="#ef4444" stroke-width="3"/>
        <path d="M27 22L37 28L27 34V22Z" fill="#2d7bf4"/>
        <line x1="16" y1="50" x2="48" y2="50" stroke="#475569" stroke-width="3.5" stroke-linecap="round"/>
    </svg>`,
    // 18. Sprints
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="36" cy="14" r="5" stroke="#1fa851" stroke-width="3"/>
        <path d="M18 28H30L36 42L46 50" stroke="#1fa851" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M24 42L30 30L42 26" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`,
    // 19. Vault
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="12" y="12" width="40" height="40" rx="8" stroke="#2d7bf4" stroke-width="3"/>
        <path d="M22 32H42M32 22V42M25 25L39 39M39 25L25 39" stroke="#ef4444" stroke-width="3.5" stroke-linecap="round"/>
    </svg>`,
    // 20. WorkDrive
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10 14V50H54V20H28L22 14H10Z" stroke="#2d7bf4" stroke-width="3" stroke-linejoin="round"/>
        <path d="M20 40V32H26V40M30 40V26H36V40M40 40V30H46V40" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
    </svg>`,
    // 21. Writer
    `<svg class="size-16 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 10H38L52 24V54H12V10Z" stroke="#2d7bf4" stroke-width="3" stroke-linejoin="round"/>
        <path d="M38 10V24H52" stroke="#2d7bf4" stroke-width="3"/>
        <line x1="20" y1="32" x2="44" y2="32" stroke="#1fa851" stroke-width="3" stroke-linecap="round"/>
        <line x1="20" y1="40" x2="44" y2="40" stroke="#1fa851" stroke-width="3" stroke-linecap="round"/>
    </svg>`
];

const getWorkspaceIcon = (index: number) => iconList[index % iconList.length];

const isSystemAdmin = computed(() => {
    const role = page.props.user_role as string | undefined;
    return role === 'Super Administrator' || role === 'Saas Owner' || role === 'Platform Admin';
});

const userName = computed(() => {
    const user = page.props.auth?.user as { name?: string; username?: string } | undefined;
    return user?.name || user?.username || 'User';
});

const entityCount = computed(() => props.entityAccess.length);
const plantCount = computed(() => availablePlants.value.length);

const selectedEntity = computed(() => {
    return props.entityAccess.find((item) => item.entity_id === selectedEntityId.value) || null;
});

const filteredEntities = computed(() => {
    if (!entitySearch.value) return props.entityAccess;
    const query = entitySearch.value.toLowerCase();

    return props.entityAccess.filter((entity) =>
        entity.entity_name.toLowerCase().includes(query) ||
        entity.entity_alias?.toLowerCase().includes(query) ||
        entity.entity_id.toString().includes(query)
    );
});

const filteredPlants = computed(() => {
    if (!plantSearch.value) return availablePlants.value;
    const query = plantSearch.value.toLowerCase();

    return availablePlants.value.filter((plant) =>
        plant.name.toLowerCase().includes(query) ||
        plant.code?.toLowerCase().includes(query) ||
        plant.id.toString().includes(query)
    );
});

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

const goToEntityStep = () => {
    step.value = 'entity';
    plantSearch.value = '';
    errorMessage.value = '';
};

const openEntityDetails = (entity: EntityAccess) => {
    detailModal.value = {
        show: true,
        type: 'entity',
        title: 'Organization',
        name: entity.entity_name,
        logo: entity.entity_logo ? `/storage/${entity.entity_logo}` : null,
        alias: entity.entity_alias || 'N/A',
        id: entity.entity_id,
        role: entity.role_name || 'Authorized member',
        address: entity.address || 'No registered address data available.',
        phone: entity.phone || 'N/A',
        email: entity.email || 'N/A',
        code: '',
        is_main: false,
    };
};

const openPlantDetails = (plant: PlantOption) => {
    detailModal.value = {
        show: true,
        type: 'plant',
        title: 'Facility',
        name: plant.name,
        logo: plant.logo_path ? `/storage/${plant.logo_path}` : null,
        alias: '',
        id: plant.id,
        role: '',
        address: 'Facility site address context loaded dynamically.',
        phone: plant.mobile_number || 'N/A',
        email: plant.email_address || 'N/A',
        code: plant.code || 'SYS-FACILITY',
        is_main: plant.is_main || false,
    };
};

const selectEntity = async (entity: EntityAccess) => {
    if (isLoading.value) return;

    if (entity.is_suspended !== 0) {
        let reason = 'This organization is currently suspended.';
        if (entity.is_suspended === -1) {
            reason = 'This organization is suspended due to unpaid server charges or scheduled maintenance.';
        } else if (entity.is_suspended === 1) {
            reason = 'This organization is currently inactive or offline.';
        }

        alert(`Access Restricted:\n${reason}\n\nPlease contact administration to reactivate.`);
        return;
    }

    if (selectedEntityId.value === entity.entity_id && availablePlants.value.length > 0) {
        errorMessage.value = '';
        step.value = 'plant';
        return;
    }

    errorMessage.value = '';
    plantSearch.value = '';
    setAsDefault.value = false;
    isLoading.value = true;
    switchingEntityId.value = entity.entity_id;

    try {
        const { data } = await axios.post('/context/selectentity', { entity_id: entity.entity_id });
        selectedEntityId.value = entity.entity_id;
        selectedEntityName.value = entity.entity_name;
        availablePlants.value = data.plants ?? [];
        step.value = 'plant';
    } catch (error: any) {
        errorMessage.value = error.response?.data?.error || error.response?.data?.message || 'Unable to load facilities for this organization.';
    } finally {
        isLoading.value = false;
        switchingEntityId.value = null;
    }
};

const toggleSuspension = async (entity: EntityAccess) => {
    if (isLoading.value) return;

    if (entity.is_suspended !== 0 && !isSystemAdmin.value) {
        alert('Action Denied:\nOnly a Super Administrator can reactivate a suspended organization.');
        return;
    }

    isLoading.value = true;

    try {
        const { data } = await axios.post('/context/toggle-suspension', { entity_id: entity.entity_id });

        if (data.status === 'success') {
            entity.is_suspended = data.is_suspended;

            if (data.is_suspended !== 0 && selectedEntityId.value === entity.entity_id) {
                selectedEntityId.value = null;
                selectedEntityName.value = '';
                availablePlants.value = [];
                goToEntityStep();
            }
        }
    } catch (error: any) {
        alert(error.response?.data?.error || 'Failed to toggle suspension.');
    } finally {
        isLoading.value = false;
    }
};

const selectPlant = async (plant: PlantOption) => {
    if (isLoading.value) return;

    if (plant.is_active === -1) {
        alert('Access Restricted:\nThis facility is currently inactive or restricted.\n\nPlease contact administration to reactivate.');
        return;
    }

    await confirmPlant(plant.id);
};

const confirmPlant = async (plantId: number) => {
    errorMessage.value = '';
    isLoading.value = true;
    switchingPlantId.value = plantId;

    try {
        await axios.post('/context/selectplant', {
            plant_id: plantId,
            set_as_default: setAsDefault.value,
        });

        window.location.href = '/dashboard';
    } catch (error: any) {
        errorMessage.value = error.response?.data?.error || error.response?.data?.message || 'Unable to open the selected facility.';
        isLoading.value = false;
        switchingPlantId.value = null;
    }
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap');

.font-outfit {
    font-family: 'Outfit', sans-serif;
}
</style>
