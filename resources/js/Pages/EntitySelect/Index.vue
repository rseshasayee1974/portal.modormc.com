<template>
    <Head title="Modo Portal | Select Workspace" />

    <main class="min-h-screen bg-[#f0f3f6] font-outfit text-slate-900 selection:bg-amber-200/60 flex flex-col">
        <header class="relative z-10 border-b border-white/40 bg-[#f0f3f6]/80 backdrop-blur-xl">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[var(--workspace-ink)] text-white shadow-[0_18px_45px_-22px_rgba(15,23,42,0.75)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                            <path d="M12.378 1.602a.75.75 0 0 0-.756 0L3 6.632l9 5.25 9-5.25-8.622-5.03ZM21.75 7.93l-9 5.25v9l8.628-5.032a.75.75 0 0 0 .372-.648V7.93ZM11.25 22.18v-9l-9-5.25v8.57a.75.75 0 0 0 .372.648l8.628 5.033Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-black uppercase tracking-[0.35em] text-[var(--workspace-accent)]">Modo Portal</p>
                        <h1 class="truncate text-base font-black tracking-tight text-slate-950 sm:text-lg">Choose your workspace</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="hidden items-center gap-3 rounded-2xl border border-white/70 bg-white/80 px-2.5 py-2 shadow-[0_12px_35px_-24px_rgba(15,23,42,0.85)] sm:flex">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[var(--workspace-soft)] text-sm font-black text-[var(--workspace-ink)]">
                            {{ initials(userName) }}
                        </div>
                        <p class="max-w-[12rem] truncate text-sm font-bold text-slate-900">{{ userName }}</p>
                    </div>

                    <button
                        type="button"
                        @click="logout"
                        class="inline-flex items-center gap-2 rounded-2xl border border-white/70 bg-white/85 px-3 py-2 text-sm font-semibold text-slate-600 shadow-[0_12px_35px_-24px_rgba(15,23,42,0.85)] transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                        title="Log out"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <span class="hidden sm:inline">Log out</span>
                    </button>
                </div>
            </div>
        </header>

        <section class="relative z-10 mx-auto flex w-full max-w-5xl flex-1 px-5 py-6 sm:px-6 lg:px-8 lg:py-10">
            <div class="w-full rounded-[32px] bg-[#f0f3f6] shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#d1d9e6]">
                <div class="border-b border-slate-200/80 px-6 py-5 sm:px-8">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full bg-[var(--workspace-soft)] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-[var(--workspace-ink)]">
                                {{ step === 'entity' ? 'Step 1 of 2' : 'Step 2 of 2' }}
                            </span>
                            <span class="inline-flex rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
                                {{ step === 'entity' ? `${entityCount} organizations` : `${plantCount} facilities` }}
                            </span>
                        </div>

                        <div>
                            <h2 class="text-2xl font-black tracking-tight text-slate-950 sm:text-[2rem]">
                                {{ step === 'entity' ? 'Select an organization' : 'Select a facility' }}
                            </h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                {{ stepDescription }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6 sm:px-8 sm:py-7">
                    <div v-if="errorMessage" class="mb-5 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mt-0.5 size-5 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <p>{{ errorMessage }}</p>
                    </div>

                    <div v-if="step === 'entity'" class="space-y-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <label class="relative block w-full lg:max-w-md">
                                <span class="sr-only">Search organizations</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607 0 7.5 7.5 0 0 0 10.607 0Z" />
                                </svg>
                                <input
                                    v-model="entitySearch"
                                    type="text"
                                    placeholder="Search organization"
                                    class="w-full rounded-2xl border-none bg-[#f0f3f6] shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] py-3 pl-11 pr-4 text-sm font-medium text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-2 focus:ring-amber-500/20"
                                />
                            </label>

                            <div class="flex items-center justify-between gap-3 rounded-2xl border-none bg-[#f0f3f6] shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] px-4 py-3 text-sm text-slate-500 lg:min-w-[220px]">
                                <span class="font-semibold">Showing {{ filteredEntities.length }} of {{ entityCount }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Organizations</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <button
                                v-for="eu in filteredEntities"
                                :key="eu.entity_id"
                                type="button"
                                class="group relative block w-full overflow-hidden rounded-[26px] border-none p-5 text-left transition-all duration-300"
                                :class="[
                                    eu.is_suspended !== 0
                                        ? 'bg-rose-50/80 shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6]'
                                        : 'bg-[#f0f3f6] shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] hover:-translate-y-1 hover:shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#cbd5e1]',
                                    selectedEntityId === eu.entity_id ? 'ring-2 ring-[var(--workspace-accent)]/20 shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6]' : ''
                                ]"
                                @click="selectEntity(eu)"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 items-start gap-4">
                                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                                            <img v-if="eu.entity_logo" :src="`/storage/${eu.entity_logo}`" :alt="eu.entity_name" class="size-full object-contain p-2" />
                                            <span v-else class="text-base font-black text-[var(--workspace-ink)]">{{ initials(eu.entity_name) }}</span>
                                        </div>

                                        <div class="min-w-0 space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4
                                                    class="truncate text-base font-black tracking-tight"
                                                    :class="eu.is_suspended !== 0 ? 'text-slate-500' : 'text-slate-950'"
                                                >
                                                    {{ eu.entity_name }}
                                                </h4>
                                                <span
                                                    v-if="eu.is_active"
                                                    class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700"
                                                >
                                                    Active now
                                                </span>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                                                <span>{{ eu.role_name }}</span>
                                                <span v-if="eu.entity_alias" class="text-slate-300">•</span>
                                                <span v-if="eu.entity_alias">{{ eu.entity_alias }}</span>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    v-if="eu.is_suspended === -1"
                                                    class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700"
                                                >
                                                    Maintenance hold
                                                </span>
                                                <span
                                                    v-else-if="eu.is_suspended === 1"
                                                    class="inline-flex rounded-full border border-rose-200 bg-rose-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-rose-700"
                                                >
                                                    Inactive
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500"
                                                >
                                                    Ready to enter
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 sm:pl-3" @click.stop>
                                        <button
                                            type="button"
                                            @click="toggleSuspension(eu)"
                                            class="inline-flex size-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                            :title="eu.is_suspended !== 0 ? 'Reactivate organization' : 'Suspend organization'"
                                        >
                                            <svg v-if="eu.is_suspended !== 0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            @click="openEntityDetails(eu)"
                                            class="inline-flex size-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700"
                                            title="View organization details"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9h.008v.008H12V9Zm0 3v3.75" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="switchingEntityId === eu.entity_id" class="absolute inset-0 flex items-center justify-center bg-white/85 backdrop-blur-sm">
                                    <div class="inline-flex items-center gap-3 rounded-full bg-white px-4 py-2 text-sm font-semibold text-[var(--workspace-ink)] shadow-lg">
                                        <div class="size-4 animate-spin rounded-full border-2 border-[var(--workspace-accent)] border-t-transparent"></div>
                                        Loading facilities...
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div v-if="filteredEntities.length === 0" class="rounded-[26px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center">
                            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607 0 7.5 7.5 0 0 0 10.607 0Z" />
                                </svg>
                            </div>
                            <h4 class="mt-4 text-base font-black tracking-tight text-slate-900">No organizations found</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Try a different name, alias, or ID to find the right workspace.</p>
                        </div>
                    </div>

                    <div v-else class="space-y-5">
                        <div class="flex flex-col gap-4 rounded-[28px] border border-slate-200/80 bg-white/75 p-5 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0">
                                <button
                                    type="button"
                                    @click="goToEntityStep"
                                    class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.22em] text-slate-500 transition hover:text-[var(--workspace-ink)]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                                    </svg>
                                    Change organization
                                </button>
                                <h4 class="mt-3 truncate text-xl font-black tracking-tight text-slate-950">{{ selectedEntityName }}</h4>
                            </div>

                            <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-[var(--workspace-soft)] px-4 py-3 md:min-w-[260px]">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Remember this facility</p>
                                    <p class="text-xs leading-5 text-slate-500">Save it as your default workspace.</p>
                                </div>
                                <div class="relative shrink-0">
                                    <input id="default-toggle" v-model="setAsDefault" type="checkbox" class="peer sr-only" />
                                    <div class="h-7 w-12 rounded-full bg-slate-300 transition peer-checked:bg-[var(--workspace-accent)]"></div>
                                    <div class="absolute left-1 top-1 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></div>
                                </div>
                            </label>
                        </div>

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <label class="relative block w-full lg:max-w-md">
                                <span class="sr-only">Search facilities</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607 0 7.5 7.5 0 0 0 10.607 0Z" />
                                </svg>
                                <input
                                    v-model="plantSearch"
                                    type="text"
                                    placeholder="Search facility"
                                    class="w-full rounded-2xl border-none bg-[#f0f3f6] shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] py-3 pl-11 pr-4 text-sm font-medium text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-2 focus:ring-amber-500/20"
                                />
                            </label>

                            <div class="flex items-center justify-between gap-3 rounded-2xl border-none bg-[#f0f3f6] shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] px-4 py-3 text-sm text-slate-500 lg:min-w-[220px]">
                                <span class="font-semibold">Showing {{ filteredPlants.length }} of {{ plantCount }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Facilities</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <button
                                v-for="plant in filteredPlants"
                                :key="plant.id"
                                type="button"
                                class="group relative block w-full overflow-hidden rounded-[26px] border-none p-5 text-left transition-all duration-300"
                                :class="[
                                    plant.is_active === -1
                                        ? 'bg-rose-50/80 shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6]'
                                        : 'bg-[#f0f3f6] shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] hover:-translate-y-1 hover:shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#cbd5e1]',
                                    defaults.plant_id === plant.id ? 'ring-2 ring-[var(--workspace-accent)]/20' : ''
                                ]"
                                @click="selectPlant(plant)"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 items-start gap-4">
                                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                                            <img v-if="plant.logo_path" :src="`/storage/${plant.logo_path}`" :alt="plant.name" class="size-full object-contain p-2" />
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6 text-[var(--workspace-ink)]">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819" />
                                            </svg>
                                        </div>

                                        <div class="min-w-0 space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4
                                                    class="truncate text-base font-black tracking-tight"
                                                    :class="plant.is_active === -1 ? 'text-slate-500' : 'text-slate-950'"
                                                >
                                                    {{ plant.name }}
                                                </h4>
                                                <span
                                                    v-if="defaults.plant_id === plant.id"
                                                    class="inline-flex rounded-full border border-[var(--workspace-accent)]/20 bg-[var(--workspace-soft)] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--workspace-ink)]"
                                                >
                                                    Default
                                                </span>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                                                <span>{{ plant.code || 'Facility' }}</span>
                                                <span v-if="plant.is_main" class="text-slate-300">•</span>
                                                <span v-if="plant.is_main">Main unit</span>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    v-if="plant.is_active === -1"
                                                    class="inline-flex rounded-full border border-rose-200 bg-rose-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-rose-700"
                                                >
                                                    Inactive
                                                </span>
                                                <span
                                                    v-else-if="plant.is_main"
                                                    class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700"
                                                >
                                                    Primary facility
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500"
                                                >
                                                    Ready to enter
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 sm:pl-3" @click.stop>
                                        <button
                                            type="button"
                                            @click="openPlantDetails(plant)"
                                            class="inline-flex size-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700"
                                            title="View facility details"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9h.008v.008H12V9Zm0 3v3.75" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="switchingPlantId === plant.id" class="absolute inset-0 flex items-center justify-center bg-white/88 backdrop-blur-sm">
                                    <div class="inline-flex items-center gap-3 rounded-full bg-white px-4 py-2 text-sm font-semibold text-[var(--workspace-ink)] shadow-lg">
                                        <div class="size-4 animate-spin rounded-full border-2 border-[var(--workspace-accent)] border-t-transparent"></div>
                                        Opening dashboard...
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div v-if="filteredPlants.length === 0" class="rounded-[26px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center">
                            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 21V7.5A2.25 2.25 0 0 1 6.75 5.25h10.5A2.25 2.25 0 0 1 19.5 7.5V21M9 10.5h6M9 14.25h6M9 18h6" />
                                </svg>
                            </div>
                            <h4 class="mt-4 text-base font-black tracking-tight text-slate-900">No facilities found</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                {{ plantCount === 0 ? 'No active facilities are assigned to this organization yet.' : 'Try a different facility name or code.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="detailModal.show" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
            <div class="w-full max-w-xl overflow-hidden rounded-[30px] border border-white/70 bg-white shadow-[0_32px_100px_-42px_rgba(15,23,42,0.8)]">
                <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">{{ detailModal.title }}</p>
                            <h3 class="mt-1 text-xl font-black tracking-tight text-slate-950">{{ detailModal.name }}</h3>
                        </div>
                        <button
                            type="button"
                            @click="detailModal.show = false"
                            class="inline-flex size-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6">
                    <div class="flex items-center gap-4">
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                            <img v-if="detailModal.logo" :src="detailModal.logo" :alt="detailModal.name" class="size-full object-contain p-2" />
                            <span v-else class="text-lg font-black text-[var(--workspace-ink)]">{{ initials(detailModal.name) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-black tracking-tight text-slate-950">{{ detailModal.name }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ detailModal.type === 'entity' ? detailModal.role : `Facility code: ${detailModal.code}` }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">System ID</p>
                            <p class="mt-2 text-base font-black text-slate-950">{{ detailModal.id }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">
                                {{ detailModal.type === 'entity' ? 'Alias' : 'Type' }}
                            </p>
                            <p class="mt-2 text-sm font-bold text-slate-900">
                                {{ detailModal.type === 'entity' ? detailModal.alias : detailModal.is_main ? 'Primary facility' : 'Standard facility' }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Address</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ detailModal.address || 'No address available.' }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Phone</p>
                                <p class="mt-2 text-sm font-semibold text-slate-700">{{ detailModal.phone || 'N/A' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Email</p>
                                <p class="mt-2 break-all text-sm font-semibold text-slate-700">{{ detailModal.email || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 bg-slate-50/70 px-6 py-4">
                    <button
                        type="button"
                        @click="detailModal.show = false"
                        class="rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
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

const stepDescription = computed(() => {
    return step.value === 'entity'
        ? 'Choose the organization you want to enter.'
        : 'Choose the facility you want to open.';
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
:global(:root) {
    --workspace-bg: #f0f3f6;
    --workspace-card: rgba(255, 255, 255, 0.82);
    --workspace-ink: #0f172a;
    --workspace-accent: #c2410c;
    --workspace-soft: #ffedd5;
}

.font-outfit {
    font-family: 'Outfit', sans-serif;
}

:global(body) {
    background-color: var(--workspace-bg);
}
</style>
