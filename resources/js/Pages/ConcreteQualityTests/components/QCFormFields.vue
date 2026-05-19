<script setup lang="ts">
import { computed, watch } from 'vue';
import Message from 'primevue/message';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Textarea from 'primevue/textarea';

const props = defineProps<{
    form: any;
    plants: any[];
    batches: any[];
}>();

// Map batches options
const batchOptions = computed(() => {
    return props.batches.map(b => ({
        id: b.id,
        label: `Batch No: ${b.batch_no}`
    }));
});

const statusOptions = [
    { label: 'Pending Review', value: 'pending' },
    { label: 'Passed Control', value: 'passed' },
    { label: 'Failed/Warning', value: 'failed' }
];

// Live Validation / QC recommendation alert
const qaSlumpAssessment = computed(() => {
    const val = Number(props.form.slump_value);
    if (!val) return { text: 'Enter slump value', color: 'info' };
    if (val < 100) return { text: 'Low Workability (Warning: difficult placing)', color: 'warn' };
    if (val > 150) return { text: 'High Workability (Warning: segregation risk)', color: 'error' };
    return { text: 'Optimal pumpable concrete range', color: 'success' };
});

const qaStrengthAssessment = computed(() => {
    const val28 = Number(props.form.cube_strength_28_days);
    if (!val28) return { text: 'Enter strength values', color: 'info' };
    if (val28 < 25) return { text: 'Does not meet standard M25 structural limit', color: 'error' };
    return { text: 'Meets targeted structural strength specifications', color: 'success' };
});

// Auto status helper
watch(() => [props.form.slump_value, props.form.cube_strength_28_days], () => {
    const slump = Number(props.form.slump_value);
    const strength = Number(props.form.cube_strength_28_days);
    if (slump >= 100 && slump <= 150 && strength >= 25) {
        props.form.status = 'passed';
    } else if (slump < 80 || strength < 22) {
        props.form.status = 'failed';
    } else {
        props.form.status = 'pending';
    }
});

const existingPhotos = computed(() => {
    return props.form.existing_photos || [];
});

const newPhotosPreviews = computed(() => {
    if (!props.form.photos || !Array.isArray(props.form.photos)) return [];
    return props.form.photos.map((file: File) => {
        try {
            return {
                name: file.name,
                url: URL.createObjectURL(file),
                file
            };
        } catch (e) {
            return null;
        }
    }).filter(Boolean);
});

const handleFileChange = (e: any) => {
    const files = Array.from(e.target.files) as File[];
    if (!props.form.photos) {
        props.form.photos = [];
    }
    props.form.photos = [...props.form.photos, ...files];
};

const removeNewPhoto = (index: number) => {
    if (props.form.photos) {
        props.form.photos.splice(index, 1);
    }
};

const markPhotoForDeletion = (photoId: number) => {
    if (!props.form.deleted_photo_ids) {
        props.form.deleted_photo_ids = [];
    }
    if (!props.form.deleted_photo_ids.includes(photoId)) {
        props.form.deleted_photo_ids.push(photoId);
    }
};

const isPhotoMarkedForDeletion = (photoId: number) => {
    return props.form.deleted_photo_ids?.includes(photoId);
};

const undoPhotoDeletion = (photoId: number) => {
    if (props.form.deleted_photo_ids) {
        const index = props.form.deleted_photo_ids.indexOf(photoId);
        if (index > -1) {
            props.form.deleted_photo_ids.splice(index, 1);
        }
    }
};
</script>

<template>
    <div class="space-y-3">
        <!-- 1. Metadata Block -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
            <!-- <BaseSelect
                v-model="form.plant_id"
                label="Facility / Plant"
                :options="plants"
                optionLabel="name"
                optionValue="id"
                :required="true"
                placeholder="Select Facility"
            /> -->

            <BaseSelect
                v-model="form.batch_id"
                label="Associate RMC Batch"
                :options="batchOptions"
                optionLabel="label"
                optionValue="id"
                placeholder="Generic/No Batch"
                :showClear="true"
            />

            <BaseDatePicker
                v-model="form.test_date"
                label="Testing Date"
                :required="true"
            />
        <!-- </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5"> -->
            <BaseInput
                v-model="form.tested_by"
                label="QC Tested By"
                placeholder="e.g. S. Karthik (QC Eng)"
            />

            <BaseSelect
                v-model="form.status"
                label="Quality Status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                :required="true"
            />
        </div>

        <!-- 2. Fresh Concrete Tests Card -->
        <div class="px-5 py-2 bg-gradient-to-br from-indigo-50/50 to-blue-50/50 dark:from-indigo-950/20 dark:to-blue-950/20 border border-indigo-100/30 dark:border-indigo-800/10 rounded-2xl space-y-2">
            <h3 class="text-sm font-bold text-indigo-700 dark:text-indigo-400 tracking-wider uppercase flex items-center gap-2">
                <i class="pi pi-filter"></i>
                Fresh Concrete Parameters
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <BaseInput
                    v-model="form.slump_value"
                    type="number"
                    label="Slump Value (mm)"
                    :required="true"
                />
                <BaseInput
                    v-model="form.fresh_temperature"
                    type="number"
                    label="Temperature (°C)"
                    :required="true"
                />
                <BaseInput
                    v-model="form.air_content"
                    type="number"
                    label="Air Content (%)"
                    :required="true"
                />
                <BaseInput
                    v-model="form.fresh_density"
                    type="number"
                    label="Density (kg/m³)"
                    :required="true"
                />
            </div>
            
            <!-- Real-time Slump Warning Alert -->
            <!-- <Message :severity="qaSlumpAssessment.color" :closable="false" class="text-xs mt-2 border-none">
                {{ qaSlumpAssessment.text }}
            </Message> -->
        </div>

        <!-- 3. Cured/Hardened Concrete Tests Card -->
        <div class="p-5 bg-gradient-to-br from-purple-50/50 to-pink-50/50 dark:from-purple-950/20 dark:to-pink-950/20 border border-purple-100/30 dark:border-purple-800/10 rounded-2xl space-y-4">
            <h3 class="text-sm font-bold text-purple-700 dark:text-purple-400 tracking-wider uppercase flex items-center gap-2">
                <i class="pi pi-box"></i>
                Hardened / Cured parameters
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <BaseInput
                    v-model="form.cube_strength_7_days"
                    type="number"
                    label="7 Days Strength (MPa)"
                    :required="true"
                />
                <BaseInput
                    v-model="form.cube_strength_28_days"
                    type="number"
                    label="28 Days Strength (MPa)"
                    :required="true"
                />
                <BaseInput
                    v-model="form.core_test_strength"
                    type="number"
                    label="Core strength (MPa)"
                    placeholder="Optional"
                />
            </div>

            <!-- Real-time Strength assessment -->
            <!-- <Message :severity="qaStrengthAssessment.color" :closable="false" class="text-xs mt-2 border-none">
                {{ qaStrengthAssessment.text }}
            </Message> -->
        </div>

        <!-- 4. Durability Optional checks -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <BaseInput
                v-model="form.water_permeability"
                type="number"
                label="Water Permeability (mm)"
                placeholder="Optional check"
            />
            <BaseInput
                v-model="form.rapid_chloride_permeability"
                type="number"
                label="Chloride Permeability (Coulombs)"
                placeholder="Optional check"
            />
             <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-semibold text-gray-700 dark:text-gray-400 uppercase tracking-wider">Quality Control Remarks</label>
                <Textarea 
                    v-model="form.remarks" 
                    rows="2" 
                    fluid
                    placeholder=""
                />
            </div>
        </div>

        <!-- 5. Modern Multiple Reference Photos Block -->
        <div class="p-6 bg-gradient-to-br from-indigo-50/20 to-purple-50/20 dark:from-indigo-950/5 dark:to-purple-950/5 border border-indigo-100/30 dark:border-indigo-800/10 rounded-2xl space-y-4">
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <h3 class="text-xs font-bold text-indigo-700 dark:text-indigo-400 tracking-wider uppercase flex items-center gap-2">
                        <i class="pi pi-images"></i>
                        Laboratory Reference Photos
                    </h3>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 font-medium">Attach high-resolution logs, slump tests, or cube identification marks.</p>
                </div>
                <div v-if="existingPhotos.length || newPhotosPreviews.length" class="flex gap-2 text-[9px] font-black uppercase">
                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">
                        Total: {{ existingPhotos.length + newPhotosPreviews.length }}
                    </span>
                    <span v-if="newPhotosPreviews.length" class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                        To Add: {{ newPhotosPreviews.length }}
                    </span>
                    <span v-if="form.deleted_photo_ids?.length" class="px-2 py-0.5 rounded bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400">
                        To Delete: {{ form.deleted_photo_ids.length }}
                    </span>
                </div>
            </div>

            <!-- Upload Dropzone Card -->
            <div 
                @click="$refs.fileInput.click()"
                class="group relative cursor-pointer border-2 border-dashed border-gray-200 dark:border-gray-800 hover:border-indigo-500 dark:hover:border-indigo-500 rounded-2xl p-6 bg-gray-50/50 dark:bg-gray-900/10 hover:bg-indigo-50/5 dark:hover:bg-indigo-950/5 transition-all duration-300 shadow-sm flex flex-col items-center justify-center text-center gap-2"
            >
                <input 
                    ref="fileInput"
                    type="file" 
                    accept="image/*" 
                    multiple
                    @change="handleFileChange"
                    class="hidden"
                />
                
                <!-- Cloud Upload Glowing Icon -->
                <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center group-hover:scale-110 group-hover:shadow-md transition-all duration-300">
                    <i class="pi pi-cloud-upload text-indigo-600 dark:text-indigo-400 text-xl animate-bounce"></i>
                </div>
                
                <div class="space-y-1">
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-200">
                        Drag & Drop or <span class="text-indigo-600 dark:text-indigo-400 underline decoration-2 decoration-indigo-200 dark:decoration-indigo-800 hover:text-indigo-700">browse files</span>
                    </p>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 font-medium">
                        Supports JPEG, PNG or WEBP (Max 10MB per file)
                    </p>
                </div>
            </div>

            <!-- Enhanced Premium Image Gallery Grid -->
            <div v-if="existingPhotos.length || newPhotosPreviews.length" class="space-y-2">
                <label class="text-[9px] font-extrabold text-gray-400 dark:text-gray-500 uppercase tracking-widest block">Selected Reference Logs</label>
                
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3 p-2 bg-gray-50/50 dark:bg-gray-900/5 rounded-xl border border-gray-100 dark:border-gray-800 max-h-[220px] overflow-y-auto">
                    
                    <!-- Existing Photos -->
                    <template v-for="photo in existingPhotos" :key="photo.id">
                        <div class="relative group aspect-square rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 flex items-center justify-center shadow-sm transition-all duration-300 hover:shadow-md">
                            <img 
                                :src="photo.url" 
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                :class="{'opacity-20 blur-[2px]': isPhotoMarkedForDeletion(photo.id)}"
                            />
                            
                            <!-- Premium Delete/Restore Overlay -->
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 gap-1.5">
                                <button 
                                    v-if="!isPhotoMarkedForDeletion(photo.id)" 
                                    type="button" 
                                    @click="markPhotoForDeletion(photo.id)" 
                                    class="w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 active:scale-95"
                                    title="Delete Photo"
                                >
                                    <i class="pi pi-trash text-xs"></i>
                                </button>
                                <button 
                                    v-else 
                                    type="button" 
                                    @click="undoPhotoDeletion(photo.id)" 
                                    class="w-8 h-8 bg-emerald-500 hover:bg-emerald-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 active:scale-95"
                                    title="Undo Delete"
                                >
                                    <i class="pi pi-refresh text-xs"></i>
                                </button>
                            </div>
                            
                            <!-- Premium Trashed Badge -->
                            <div v-if="isPhotoMarkedForDeletion(photo.id)" class="absolute top-1.5 right-1.5 bg-red-600 text-white text-[7px] font-black uppercase px-1.5 py-0.5 rounded shadow">
                                Trashed
                            </div>
                        </div>
                    </template>

                    <!-- Newly Selected Photos (Drafts) -->
                    <template v-for="(preview, idx) in newPhotosPreviews" :key="idx">
                        <div class="relative group aspect-square rounded-xl overflow-hidden border-2 border-indigo-400/50 dark:border-indigo-500/50 bg-white dark:bg-gray-950 flex items-center justify-center shadow-sm transition-all duration-300 hover:shadow-md">
                            <img 
                                :src="preview.url" 
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                            
                            <!-- Premium Draft Remove Button -->
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <button 
                                    type="button" 
                                    @click="removeNewPhoto(idx)" 
                                    class="w-8 h-8 bg-gray-900/90 hover:bg-black text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 active:scale-95"
                                    title="Remove Draft"
                                >
                                    <i class="pi pi-times text-xs"></i>
                                </button>
                            </div>
                            
                            <!-- Vibrant Draft Badge -->
                            <div class="absolute top-1.5 right-1.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-[7px] font-black uppercase px-1.5 py-0.5 rounded shadow">
                                Draft
                            </div>
                        </div>
                    </template>

                </div>
            </div>
        </div>
    </div>
</template>
