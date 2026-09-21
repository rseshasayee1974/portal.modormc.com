<script setup lang="ts">
import { ref, computed } from 'vue';
import SiteAddressDropdowns from './SiteAddressDropdowns.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import MultiSelect from 'primevue/multiselect';
import { 
    BuildingOffice2Icon, 
    MapPinIcon, 
    GlobeAltIcon, 
    TagIcon, 
    ShieldCheckIcon,
    ArrowTopRightOnSquareIcon,
    MapIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    form: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    errors?: any;
    readonly?: boolean;
    patrons: any[];
}>();

const patronOptions = computed(() => (props.patrons || []).map(p => ({ label: p.legal_name, value: p.id })));

const activeTab = ref<'basic' | 'address' | 'location'>('basic');

const hasBasicError = computed(() => {
    return Boolean(
        props.errors?.name || 
        props.errors?.code || 
        props.errors?.plant_id || 
        props.errors?.type || 
        props.errors?.patron_id || 
        props.errors?.status
    );
});

const hasAddressError = computed(() => {
    return Boolean(
        props.errors?.site_address_1 || 
        props.errors?.site_address_2 || 
        props.errors?.country || 
        props.errors?.state || 
        props.errors?.district || 
        props.errors?.city || 
        props.errors?.zipcode
    );
});

const hasLocationError = computed(() => {
    return Boolean(props.errors?.latitude || props.errors?.longitude);
});

const tabs = computed(() => [
    { key: 'basic' as const, label: 'General Info', icon: BuildingOffice2Icon, hasError: hasBasicError.value },
    // { key: 'address' as const, label: 'Address & Region', icon: MapPinIcon, hasError: hasAddressError.value },
    { key: 'location' as const, label: 'Geo Coordinates', icon: GlobeAltIcon, hasError: hasLocationError.value },
]);

const isGettingLocation = ref(false);

const getCurrentLocation = () => {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }
    isGettingLocation.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            props.form.latitude = position.coords.latitude.toFixed(6);
            props.form.longitude = position.coords.longitude.toFixed(6);
            isGettingLocation.value = false;
        },
        (error) => {
            console.error('Error obtaining location:', error);
            isGettingLocation.value = false;
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
};

const googleMapsUrl = computed(() => {
    if (!props.form.latitude || !props.form.longitude) return null;
    return `https://www.google.com/maps?q=${props.form.latitude},${props.form.longitude}`;
});
</script>

<template>
    <div class="flex flex-col gap-5 text-xs">
        <!-- Navigation Tab Pills with Status Accents -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 dark:border-slate-700/80 pb-3">
            <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    @click="activeTab = tab.key"
                    class="relative flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-bold text-xs transition-all duration-200"
                    :class="activeTab === tab.key 
                        ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs' 
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-200/50 dark:hover:bg-slate-800/50'"
                >
                    <component :is="tab.icon" class="w-4 h-4 shrink-0" />
                    <span>{{ tab.label }}</span>
                    <span 
                        v-if="tab.hasError" 
                        class="w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-800 animate-pulse"
                        title="Validation errors on this tab"
                    ></span>
                </button>
            </div>

            <!-- Context Hint -->
            <div class="hidden sm:flex items-center gap-2 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                <span>{{ activeTab === 'basic' ? 'Operational node identity & facility mapping' : (activeTab === 'address' ? 'Postal delivery routing & regional master' : 'GPS navigation coordinates & maps') }}</span>
            </div>
        </div>

        <!-- Form Panels -->
        <div class="form-content min-h-[220px]">
            
            <!-- TAB 1: GENERAL INFO -->
            <div v-show="activeTab === 'basic'" class="space-y-4 animate-in fade-in slide-in-from-top-1 duration-200">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    
                    <!-- Site Name -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseInput 
                            v-model="form.name" 
                            label="Site / Destination Name"
                            required
                            placeholder="e.g. Prestige Tech Park - Phase 2" 
                            :error="errors?.name"
                            :disabled="readonly"
                        />
                    </div>

                    <!-- Internal Code -->
                    <div class="col-span-12 sm:col-span-6 md:col-span-3">
                        <BaseInput 
                            v-model="form.code" 
                            label="Site Code"
                            placeholder="e.g. S-001" 
                            :error="errors?.code"
                            :disabled="readonly"
                        />
                    </div>

                    <!-- Site Type -->
                    <div class="col-span-12 sm:col-span-6 md:col-span-3" v-if="isPrivileged">
                        <BaseSelect 
                            v-model="form.type" 
                            label="Concrete / Site Type"
                            required
                            :options="siteTypes.map(t => ({ label: t.toUpperCase() + (t === 'unloading' ? ' (Delivery Site)' : ' (Loading Facility)'), value: t }))" 
                            optionLabel="label" 
                            optionValue="value" 
                            placeholder="Select Site Type"
                            :error="errors?.type"
                            :disabled="readonly || !isPrivileged"
                        />
                    </div>
                    <div class="col-span-12 sm:col-span-6 md:col-span-3" v-else>
                        <BaseInput 
                            label="Site Type"
                            modelValue="UNLOADING (Delivery Site)"
                            disabled
                        />
                    </div>
                    <!-- Street Address Lines -->
                    <!-- <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"> -->
                        <div class="col-span-12 sm:col-span-6 md:col-span-3">
                            <BaseInput 
                                v-model="form.site_address_1" 
                                label="Address Line 1" 
                                placeholder="Plot / Survey number, Building, Street name" 
                                :error="errors?.site_address_1" 
                                :disabled="readonly" 
                            />
                        </div>
                        <div class="col-span-12 sm:col-span-6 md:col-span-3">
                            <BaseInput 
                                v-model="form.site_address_2" 
                                label="Address Line 2 / Locality" 
                                placeholder="Area, Phase, Landmark, Near metro station" 
                                :error="errors?.site_address_2" 
                                :disabled="readonly" 
                            />
                        </div>
                    <!-- </div> -->

                    <!-- Cascading Regional Dropdowns (Country -> State -> District -> City -> Zipcode) -->
                    <!-- <div class="pt-1 border-t border-slate-200/60 dark:border-slate-700/50"> -->
                        <SiteAddressDropdowns 
                            :form="form" 
                            :errors="errors" 
                            :readonly="readonly" 
                        />
                    <!-- </div> -->
                <!-- </div> -->

                    <!-- Parent Facility (Plant) -->
                    <div class="col-span-12 sm:col-span-6 md:col-span-2" v-if="isPrivileged">
                        <BaseSelect 
                            v-model="form.plant_id" 
                            label="Parent Facility (Plant)"
                            required
                            :options="plants" 
                            optionLabel="name" 
                            optionValue="id" 
                            placeholder="Select Plant" 
                            :error="errors?.plant_id"
                            :disabled="readonly"
                            filter
                        />
                    </div>

                    <!-- Associated Customer / Patron -->
                    <div :class="isPrivileged ? 'col-span-12 md:col-span-2' : 'col-span-12 md:col-span-2'">
                        <BaseField label="Associated Customer / Patron" :error="errors?.patron_id">
                            <MultiSelect 
                                v-model="form.patron_id" 
                                :options="patronOptions" 
                                optionLabel="label" 
                                optionValue="value" 
                                placeholder="Select Customer(s) - Optional" 
                                display="chip"
                                class="!w-full !rounded-lg !border-slate-200 dark:!border-slate-700 focus:!ring-indigo-100 font-medium text-xs"
                                :class="{'p-invalid': errors?.patron_id}"
                                :disabled="readonly"
                                filter
                            />
                        </BaseField>
                    </div>

                    <!-- Operational Status Toggle Card -->
                    <div class="col-span-12 sm:col-span-3">
                        <div class="h-full p-3 bg-slate-50/80 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 transition-colors hover:border-indigo-200 dark:hover:border-indigo-800">
                            <div class="flex items-center gap-3">
                                <div 
                                    class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                                    :class="form.status === 'Active' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'"
                                >
                                    <span class="w-2.5 h-2.5 rounded-full" :class="form.status === 'Active' ? 'bg-emerald-500 shadow-sm shadow-emerald-400' : 'bg-slate-400'"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                        {{ form.status === 'Active' ? 'Active Site' : 'Inactive / Archived' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium">
                                        {{ form.status === 'Active' ? 'Visible' : 'Not Visible ' }}
                                    </span>
                                </div>
                            </div>
                            <ToggleSwitch 
                                :modelValue="form.status === 'Active'" 
                                @update:modelValue="form.status = $event ? 'Active' : 'InActive'" 
                                :disabled="readonly"
                            />
                        </div>
                    </div>

                    <!-- Security / Access Restriction Toggle Card -->
                    <div class="col-span-12 sm:col-span-3">
                        <div class="h-full p-3 bg-slate-50/80 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 transition-colors hover:border-indigo-200 dark:hover:border-indigo-800">
                            <div class="flex items-center gap-3">
                                <div 
                                    class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                                    :class="form.is_restricted ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-600' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'"
                                >
                                    <ShieldCheckIcon class="w-4 h-4" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                        {{ form.is_restricted ? 'Restricted Access' : 'Open Site Access' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium">
                                        {{ form.is_restricted ? 'Restricted' : 'Open' }}
                                    </span>
                                </div>
                            </div>
                            <ToggleSwitch 
                                v-model="form.is_restricted" 
                                :disabled="readonly"
                            />
                        </div>
                    </div>

                </div>
            </div>

          

            <!-- TAB 3: GEO COORDINATES & NAVIGATION -->
            <div v-show="activeTab === 'location'" class="space-y-4 animate-in fade-in slide-in-from-top-1 duration-200">
                <div class="p-4 bg-slate-50/70 dark:bg-slate-900/40 rounded-xl border border-slate-200/80 dark:border-slate-800 space-y-4">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-200 dark:border-slate-700/60">
                        <div class="flex items-center gap-2 text-slate-700 dark:text-slate-200 font-bold text-xs uppercase tracking-wider">
                            <GlobeAltIcon class="w-4 h-4 text-indigo-500 shrink-0" />
                            <span>GPS Coordinates & Map Pin</span>
                        </div>
                        <button
                            type="button"
                            @click="getCurrentLocation"
                            :disabled="isGettingLocation || readonly"
                            class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors"
                        >
                            <span v-if="isGettingLocation" class="w-3 h-3 rounded-full border-2 border-indigo-600 border-t-transparent animate-spin"></span>
                            <MapPinIcon v-else class="w-3.5 h-3.5" />
                            <span>{{ isGettingLocation ? 'Detecting...' : 'Detect Device Coordinates' }}</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <BaseInput 
                                v-model="form.latitude" 
                                label="GPS Latitude (DD)"
                                placeholder="e.g. 12.971598" 
                                :error="errors?.latitude"
                                :disabled="readonly"
                            />
                        </div>

                        <div>
                            <BaseInput 
                                v-model="form.longitude" 
                                label="GPS Longitude (DD)"
                                placeholder="e.g. 77.594566" 
                                :error="errors?.longitude"
                                :disabled="readonly"
                            />
                        </div>
                    </div>

                    <!-- Navigation Map Preview & External Quick-Link -->
                    <div class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                <MapIcon class="w-5 h-5" />
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                    {{ form.latitude && form.longitude ? `${form.latitude}, ${form.longitude}` : 'Coordinates Unset' }}
                                </div>
                                <p class="text-[10px] text-slate-400">
                                    {{ form.latitude && form.longitude ? 'Geofence centroid & navigation waypoints mapped' : 'Add GPS coordinates for driver route navigation and geofencing' }}
                                </p>
                            </div>
                        </div>

                        <a 
                            v-if="googleMapsUrl"
                            :href="googleMapsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/70 rounded-lg text-xs font-bold transition-colors shrink-0"
                        >
                            <span>Open in Google Maps</span>
                            <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
:deep(.p-select) {
    @apply !bg-white dark:!bg-slate-800 !rounded-lg !border-slate-200 dark:!border-slate-700;
}
:deep(.p-inputtext) {
    @apply !bg-white dark:!bg-slate-800;
}
</style>
