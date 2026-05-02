<template>
    <AppLayout title="Image to GIF Converter">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Image to GIF Converter
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Left Column: Upload and Controls -->
                        <div class="space-y-6">
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-10 text-center hover:border-indigo-400 transition-colors cursor-pointer" 
                                 @click="$refs.fileInput.click()">
                                <input type="file" ref="fileInput" class="hidden" accept="image/*" @change="handleFileUpload">
                                <div v-if="!selectedImage" class="space-y-4">
                                    <i class="pi pi-cloud-upload text-5xl text-gray-400"></i>
                                    <p class="text-gray-600 font-medium">Click to upload or drag and drop</p>
                                    <p class="text-xs text-gray-400 uppercase tracking-widest">Supports PNG, JPG, WEBP</p>
                                </div>
                                <div v-else class="space-y-4">
                                    <img :src="selectedImage" class="max-h-48 mx-auto rounded-lg shadow-md" alt="Preview">
                                    <p class="text-indigo-600 font-bold uppercase text-xs tracking-widest">Change Image</p>
                                </div>
                            </div>

                            <div v-if="selectedImage" class="space-y-4 bg-slate-50 p-6 rounded-xl border border-slate-200">
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-4">Animation Settings</h3>
                                
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold uppercase text-slate-400">Effect</label>
                                    <select v-model="settings.effect" class="w-full rounded-lg border-slate-200 text-sm">
                                        <option value="spin">360° Rotation</option>
                                        <option value="pulse">Pulse / Zoom</option>
                                        <option value="bounce">Bounce</option>
                                        <option value="slide">Slide In</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold uppercase text-slate-400">Frames</label>
                                        <input type="number" v-model="settings.frames" min="5" max="30" class="w-full rounded-lg border-slate-200 text-sm">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold uppercase text-slate-400">Duration (ms)</label>
                                        <input type="number" v-model="settings.interval" step="0.1" class="w-full rounded-lg border-slate-200 text-sm">
                                    </div>
                                </div>

                                <button @click="generateGif" 
                                        :disabled="isGenerating"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-indigo-200 disabled:opacity-50">
                                    <span v-if="isGenerating"><i class="pi pi-spin pi-spinner mr-2"></i> Generating...</span>
                                    <span v-else>Convert to Animated GIF</span>
                                </button>
                            </div>
                        </div>

                        <!-- Right Column: Result -->
                        <div class="flex flex-col items-center justify-center border border-slate-100 rounded-2xl bg-slate-50/50 p-8 min-h-[400px]">
                            <div v-if="generatedGif" class="text-center space-y-6">
                                <h3 class="text-xs font-bold uppercase tracking-widest text-emerald-500">Generated GIF</h3>
                                <div class="bg-white p-4 rounded-2xl shadow-xl inline-block">
                                    <img :src="generatedGif" class="max-w-full rounded-lg" alt="Generated GIF">
                                </div>
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <a :href="generatedGif" download="animation.gif" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg font-bold text-xs shadow-md transition-all">
                                        Download GIF
                                    </a>
                                    <button @click="setAsLoader" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold text-xs shadow-md transition-all">
                                        Set as Global Loader
                                    </button>
                                    <button @click="reset" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg font-bold text-xs transition-all">
                                        Start Over
                                    </button>
                                </div>
                            </div>
                            <div v-else-if="isGenerating" class="text-center space-y-4">
                                <div class="w-16 h-16 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
                                <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">Processing Frames...</p>
                            </div>
                            <div v-else class="text-center space-y-4 opacity-30">
                                <i class="pi pi-images text-7xl text-slate-300"></i>
                                <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">Your GIF will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, reactive, onMounted } from 'vue';

const selectedImage = ref(null);
const generatedGif = ref(null);
const isGenerating = ref(false);
const settings = reactive({
    effect: 'spin',
    frames: 15,
    interval: 0.1,
    width: 400,
    height: 400
});

import { showLoader, hideLoader } from '@/Utils/loadingState';

// Load gifshot from CDN
onMounted(() => {
    if (!window.gifshot) {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/gifshot/0.4.5/gifshot.min.js';
        script.onload = () => console.log('gifshot library loaded successfully');
        script.onerror = () => {
            console.error('Failed to load gifshot from primary CDN, trying secondary...');
            const fallbackScript = document.createElement('script');
            fallbackScript.src = 'https://cdn.jsdelivr.net/npm/gifshot@0.4.5/dist/gifshot.min.js';
            document.head.appendChild(fallbackScript);
        };
        document.head.appendChild(script);
    }
});

const handleFileUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
            selectedImage.value = event.target.result;
            generatedGif.value = null;
        };
        reader.readAsDataURL(file);
    }
};

const generateGif = () => {
    if (!window.gifshot) {
        console.error('gifshot library not loaded');
        import('sweetalert2').then(S => S.default.fire('Error', 'GIF library not loaded. Please check your internet connection.', 'error'));
        return;
    }
    if (!selectedImage.value) {
        import('sweetalert2').then(S => S.default.fire('Error', 'Please upload an image first.', 'warning'));
        return;
    }

    isGenerating.value = true;
    showLoader();
    console.log('Starting GIF generation with settings:', settings);

    const images = [];
    const { effect, frames, interval } = settings;

    const img = new Image();
    img.onload = () => {
        if (img.width === 0 || img.height === 0) {
            console.error('Invalid image dimensions');
            isGenerating.value = false;
            hideLoader();
            return;
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = settings.width;
        canvas.height = settings.height;

        console.log('Generating frames...');
        for (let i = 0; i < frames; i++) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.translate(canvas.width / 2, canvas.height / 2);

            if (effect === 'spin') {
                ctx.rotate((Math.PI * 2 * i) / frames);
            } else if (effect === 'pulse') {
                const scale = 1 + Math.sin((Math.PI * 2 * i) / frames) * 0.2;
                ctx.scale(scale, scale);
            } else if (effect === 'bounce') {
                const y = Math.abs(Math.sin((Math.PI * i) / frames)) * -50;
                ctx.translate(0, y);
            } else if (effect === 'slide') {
                const x = (i / frames) * canvas.width - canvas.width / 2;
                ctx.translate(x, 0);
            }

            // Draw image maintaining aspect ratio within the square canvas
            const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
            const w = img.width * scale;
            const h = img.height * scale;
            ctx.drawImage(img, -w / 2, -h / 2, w, h);
            ctx.restore();
            images.push(canvas.toDataURL('image/png'));
        }

        console.log('Frames ready, calling gifshot.createGIF...');
        window.gifshot.createGIF({
            images: images,
            interval: interval,
            gifWidth: settings.width,
            gifHeight: settings.height,
            numWorkers: 2,
        }, (obj) => {
            console.log('gifshot callback received:', obj);
            if (!obj.error) {
                generatedGif.value = obj.image;
                console.log('GIF generated successfully');
            } else {
                console.error('gifshot error:', obj.error, obj.errorCode, obj.errorMsg);
                import('sweetalert2').then(S => S.default.fire('Error', 'GIF generation failed: ' + obj.errorMsg, 'error'));
            }
            isGenerating.value = false;
            hideLoader();
        });
    };
    img.onerror = () => {
        console.error('Failed to load preview image for processing');
        isGenerating.value = false;
        hideLoader();
    };
    img.src = selectedImage.value;
};

const setAsLoader = () => {
    if (generatedGif.value) {
        localStorage.setItem('custom_loader_gif', generatedGif.value);
        import('sweetalert2').then((Swal) => {
            Swal.default.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Global Loader Updated!',
                showConfirmButton: false,
                timer: 2000
            });
        });
    }
};

const reset = () => {
    selectedImage.value = null;
    generatedGif.value = null;
    isGenerating.value = false;
};
</script>

<style>
@import 'primeicons/primeicons.css';
</style>
