<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Components
import Dialog from 'primevue/dialog';

import { CheckCircleIcon, CameraIcon, EyeIcon, ScaleIcon, ShoppingCartIcon, PrinterIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const props = defineProps<{ inward: any }>();
const data = ref({ ...props.inward });
watch(() => props.inward, (inward) => { data.value = { ...inward }; });
const page = usePage();
const isManualWeightDisabled = computed(() => page.props.custom_settings?.batching?.manual_weight == 1);
const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();
const imageModalVisible = ref(false);
const imageModalSrc = ref('');
const imageModalTitle = ref('');

const openImageModal = (src: string, title: string = 'Weight Snapshot') => {
    imageModalSrc.value = src;
    imageModalTitle.value = title;
    imageModalVisible.value = true;
};

const formatImageUrl = (img: any) => {
    if (!img) return null;
    if (typeof img === 'string') {
        if (img.startsWith('data:image') || img.startsWith('http://') || img.startsWith('https://')) return img;
        if (img.startsWith('/storage/')) return img;
        return '/storage/' + img.replace(/^\/+/, '');
    }
    if (img.url) {
        if (img.url.startsWith('data:image') || img.url.startsWith('http://') || img.url.startsWith('https://')) return img.url;
        if (img.url.startsWith('/storage/')) return img.url;
        return '/storage/' + String(img.url).replace(/^\/+/, '');
    }
    if (img.image_path) {
        const path = String(img.image_path).replace(/^\/+/, '');
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:image')) return path;
        return '/storage/' + path;
    }
    return null;
};

const getGrossPhotoUrl = (inward: any) => {
    if (!inward) return null;
    if (inward._loaded_snap) return inward._loaded_snap;
    if (inward.loaded_weight_photo) return formatImageUrl(inward.loaded_weight_photo);
    const img = inward.loaded_weight_image || inward.loadedWeightImage;
    return formatImageUrl(img);
};

const getTarePhotoUrl = (inward: any) => {
    if (!inward) return null;
    if (inward._empty_snap) return inward._empty_snap;
    if (inward.empty_weight_photo) return formatImageUrl(inward.empty_weight_photo);
    const img = inward.empty_weight_image || inward.emptyWeightImage;
    return formatImageUrl(img);
};

const saveGrossWeight = (inward: any, newWeight?: any, photo?: string | null) => {
    const payload: any = {};
    if (newWeight !== undefined && newWeight !== null && newWeight !== '') {
        payload.truck_loaded = Number(newWeight);
    }
    const snapPhoto = photo || inward._loaded_snap || null;
    if (snapPhoto) {
        payload.loaded_weight_photo = snapPhoto;
    }
    if (Object.keys(payload).length === 0) return;

    router.post(route('inwards.update-weight', inward.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Gross weight & snapshot saved', showConfirmButton: false, timer: 1500 });
        },
        onError: (errs: any) => {
            console.error('Update gross weight error:', errs);
            const msg = typeof errs === 'object' ? Object.values(errs).flat().join(', ') : 'Failed to save gross weight.';
            Swal.fire('Error', msg, 'error');
        }
    });
};

const captureGrossWeight = async (inward: any) => {
    await captureWeight(async (w: number) => {
        inward.truck_loaded = w;

        let snap: string | null = null;
        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_2 || customSettings.batching.camera_url || customSettings.batching.camera_url_1;
            try {
                snap = await captureCameraSnap(cameraUrl);
                inward._loaded_snap = snap;
            } catch (err) {
                console.error('Gross camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Gross weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        saveGrossWeight(inward, w, snap);
    });
};

const takeGrossSnapOnly = async (inward: any) => {
    const customSettings: any = page.props.custom_settings || {};
    const cameraUrl = customSettings?.batching?.camera_url_2 || customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1;
    if (!cameraUrl) {
        Swal.fire('Warning', 'No camera URL configured in settings', 'warning');
        return;
    }
    try {
        const snap = await captureCameraSnap(cameraUrl);
        inward._loaded_snap = snap;
        saveGrossWeight(inward, inward.truck_loaded, snap);
    } catch (err) {
        console.error('Camera snapshot failed:', err);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Gross camera snapshot failed',
            showConfirmButton: false,
            timer: 1500
        });
    }
};

const saveEmptyWeight = (inward: any, newWeight?: any, photo?: string | null) => {
    const payload: any = {};
    if (newWeight !== undefined && newWeight !== null && newWeight !== '') {
        payload.truck_empty = Number(newWeight);
    }
    const snapPhoto = photo || inward._empty_snap || null;
    if (snapPhoto) {
        payload.empty_weight_photo = snapPhoto;
    }
    if (Object.keys(payload).length === 0) return;

    router.post(route('inwards.update-weight', inward.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tare weight & snapshot saved', showConfirmButton: false, timer: 1500 });
        },
        onError: (errs: any) => {
            console.error('Update tare weight error:', errs);
            const msg = typeof errs === 'object' ? Object.values(errs).flat().join(', ') : 'Failed to save tare weight.';
            Swal.fire('Error', msg, 'error');
        }
    });
};

const captureTareWeight = async (inward: any) => {
    await captureWeight(async (w: number) => {
        inward.truck_empty = w;

        let snap: string | null = null;
        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_1 || customSettings.batching.camera_url || customSettings.batching.camera_url_2;
            try {
                snap = await captureCameraSnap(cameraUrl);
                inward._empty_snap = snap;
            } catch (err) {
                console.error('Tare camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Tare weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        saveEmptyWeight(inward, w, snap);
    });
};

const takeTareSnapOnly = async (inward: any) => {
    const customSettings: any = page.props.custom_settings || {};
    const cameraUrl = customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_2;
    if (!cameraUrl) {
        Swal.fire('Warning', 'No camera URL configured in settings', 'warning');
        return;
    }
    try {
        const snap = await captureCameraSnap(cameraUrl);
        inward._empty_snap = snap;
        saveEmptyWeight(inward, inward.truck_empty, snap);
    } catch (err) {
        console.error('Camera snapshot failed:', err);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Tare camera snapshot failed',
            showConfirmButton: false,
            timer: 1500
        });
    }
};

const saveInwardWeights = (inward: any) => {
    const payload: any = {
        truck_loaded: Number(inward.truck_loaded || 0),
        truck_empty: Number(inward.truck_empty || 0),
    };
    if (inward.conversion_quantity !== undefined) {
        payload.conversion_quantity = Number(inward.conversion_quantity);
    }
    if (inward.conversion_uom_id !== undefined) {
        payload.conversion_uom_id = inward.conversion_uom_id;
    }
    if (inward._loaded_snap) payload.loaded_weight_photo = inward._loaded_snap;
    if (inward._empty_snap) payload.empty_weight_photo = inward._empty_snap;

    router.post(route('inwards.update-weight', inward.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Inward details saved successfully', showConfirmButton: false, timer: 1500 });
        },
        onError: (errs: any) => {
            console.error('Update inward weights error:', errs);
            const msg = typeof errs === 'object' ? Object.values(errs).flat().join(', ') : 'Failed to save inward weights.';
            Swal.fire('Error', msg, 'error');
        }
    });
};

</script>

<template>
    <div class="p-4 bg-slate-50/80 border-y border-slate-200 space-y-4">

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 ring-2 ring-indigo-500/10 transition-all">
            <div
                class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center shadow-sm shadow-indigo-200 shrink-0">
                        <ScaleIcon class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-xs font-black text-slate-800 uppercase tracking-tight">Weighbridge Weighment
                                Station</h3>
                            <span v-if="Number(data.truck_loaded || 0) > 0"
                                class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider bg-indigo-100 text-indigo-700">
                                Gross: {{ Number(data.truck_loaded).toLocaleString() }} {{ data.uom?.unit_code }}
                            </span>
                            <span v-if="Number(data.truck_empty || 0) > 0"
                                class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700">
                                Tare: {{ Number(data.truck_empty).toLocaleString() }} {{ data.uom?.unit_code }}
                            </span>
                            <span v-else
                                class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider bg-amber-100 text-amber-700 animate-pulse">
                                Pending Weigh-out
                            </span>
                        </div>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">
                            GRN Ref: {{ data.inward_no }} • Truck: {{ data.truck?.registration || 'External Vehicle' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <a :href="route('inwards.receipt', data.id)" target="_blank"
                        class="px-3 py-1.5 rounded font-black text-[9px] uppercase tracking-wider flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs border border-indigo-600 transition-all cursor-pointer text-decoration-none"
                        title="Open PDF Style Receipt with Print Button">
                        <PrinterIcon class="w-3.5 h-3.5 text-white" />
                        <span>Print Receipt</span>
                    </a>

                    <a :href="route('inwards.download-receipt', data.id)"
                        class="px-2.5 py-1.5 rounded font-black text-[9px] uppercase tracking-wider flex items-center gap-1  hover:bg-slate-200 text-green-500 shadow-xs border border-slate-300 transition-all cursor-pointer text-decoration-none"
                        title="Download GRN as PDF File">
                        <ArrowDownTrayIcon class="w-3.5 h-3.5 text-green-500" />
                        <span>Download PDF</span>
                    </a>

                    <button @click.stop="saveInwardWeights(data)" type="button"
                        class="px-3 py-1.5 rounded font-black text-[9px] uppercase tracking-wider flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs border border-emerald-600 transition-all cursor-pointer"
                        title="Save Inward Details">
                        <CheckCircleIcon class="w-3.5 h-3.5 text-white" />
                        <span>Save</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 pt-4">

                <div class="bg-slate-50 p-3 rounded-md border border-slate-200/80 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">1. Gross
                            (Loaded)</span>
                    </div>
                    <div class="mt-2 flex items-center gap-1.5">
                        <input type="number" v-model="data.truck_loaded"
                            :disabled="page.props.custom_settings?.batching?.manual_weight == 0"
                            class="w-full bg-white border border-slate-200 rounded px-2 py-1 text-base font-black text-slate-800 font-mono focus:ring-1 focus:ring-indigo-500"
                            placeholder="0.00" @keyup.enter="saveGrossWeight(data, data.truck_loaded)" />
                        <span class="text-[9px] font-bold text-slate-400 uppercase">{{ data.uom?.unit_code }}</span>
                    </div>
                    <div v-if="getGrossPhotoUrl(data)" class="mt-2">
                        <img :src="getGrossPhotoUrl(data)"
                            @click="openImageModal(getGrossPhotoUrl(data), 'Gross Weight Snap — ' + data.inward_no)"
                            class="w-full h-16 object-cover rounded border border-slate-200 cursor-pointer hover:opacity-90 transition-opacity"
                            alt="Gross Snap" />
                    </div>
                    <div v-else
                        class="mt-2 h-16 bg-slate-100 rounded border border-dashed border-slate-200 flex items-center justify-center text-[8px] text-slate-400 font-bold uppercase">
                        No Gross Snap
                    </div>
                </div>

                <div class="bg-amber-50/50 p-3 rounded-md border border-amber-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[8px] font-black uppercase tracking-widest text-amber-700">2. Tare
                            (Empty)</span>
                        <button @click.stop="captureTareWeight(data)" type="button"
                            :class="['px-2 py-1 rounded font-black text-[9px] uppercase tracking-wider flex items-center gap-1 transition-all shadow-xs border cursor-pointer', isScaleConnected ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600' : 'bg-amber-500 hover:bg-amber-600 text-white border-amber-500']"
                            :title="isScaleConnected ? 'Capture Tare Weight from Scale & Take Snap' : 'Connect Weighbridge & Capture Tare'">
                            <ScaleIcon class="w-3.5 h-3.5 text-white" />
                            <span>Get Tare</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-center gap-1.5">
                        <input type="number" v-model="data.truck_empty"
                            :disabled="page.props.custom_settings?.batching?.manual_weight == 0"
                            class="w-full bg-white border border-amber-200 rounded px-2 py-1 text-base font-black text-amber-900 font-mono focus:ring-1 focus:ring-amber-500"
                            placeholder="0.00" @keyup.enter="saveEmptyWeight(data, data.truck_empty)" />
                        <span class="text-[9px] font-bold text-amber-700 uppercase">{{ data.uom?.unit_code }}</span>
                    </div>
                    <div v-if="getTarePhotoUrl(data)" class="mt-2">
                        <img :src="getTarePhotoUrl(data)"
                            @click="openImageModal(getTarePhotoUrl(data), 'Tare Weight Snap — ' + data.inward_no)"
                            class="w-full h-16 object-cover rounded border border-amber-200 cursor-pointer hover:opacity-90 transition-opacity"
                            alt="Tare Snap" />
                    </div>
                    <div v-else
                        class="mt-2 h-16 bg-amber-100/50 rounded border border-dashed border-amber-200 flex items-center justify-center text-[8px] text-amber-600 font-bold uppercase">
                        No Tare Snap
                    </div>
                </div>

                <div class="bg-emerald-50/50 p-3 rounded-md border border-emerald-200 flex flex-col justify-between">
                    <span class="text-[8px] font-black uppercase tracking-widest text-emerald-700">3. Net Calculated
                        Qty</span>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-xl font-black text-emerald-700 font-mono">
                            {{ Math.max(0, Number(data.truck_loaded || 0) - Number(data.truck_empty ||
                                0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </span>
                        <span class="text-[9px] font-bold text-emerald-600 uppercase">{{ data.uom?.unit_code }}</span>
                    </div>
                    <p class="text-[8px] text-slate-400 font-bold uppercase mt-1">
                        Gross ({{ Number(data.truck_loaded || 0) }}) - Tare ({{ Number(data.truck_empty || 0) }})
                    </p>
                </div>

                <div class="bg-slate-50 p-3 rounded-md border border-slate-200 flex flex-col justify-between">
                    <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">Accepted Inward
                        Qty</span>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-xl font-black text-slate-800 font-mono">
                            {{ Number(data.received_qty || 0).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }) }}
                        </span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase">{{ data.uom?.unit_code }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <CheckCircleIcon class="w-3.5 h-3.5 text-emerald-600" />
                        <span class="text-[8px] font-black text-slate-600 uppercase">Received to Stock</span>
                    </div>
                </div>

                <div class="bg-indigo-50/50 p-3 rounded-md border border-indigo-200 flex flex-col justify-between">
                    <span class="text-[8px] font-black uppercase tracking-widest text-indigo-700">Conversion Qty &
                        UOM</span>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-xl font-black text-indigo-800 font-mono">
                            {{ Number(data.conversion_quantity || 0).toLocaleString(undefined, {
                                minimumFractionDigits:
                                    2, maximumFractionDigits: 4
                            }) }}
                        </span>
                        <span class="text-[9px] font-bold text-indigo-600 uppercase">{{ data.conversion_uom?.unit_code
                            || data.conversionUom?.unit_code || data.uom?.unit_code }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <span class="text-[8px] font-bold text-indigo-500 uppercase">Converted Stock Qty</span>
                    </div>
                </div>
            </div>
        </div>


    </div>

    <Dialog v-model:visible="imageModalVisible" modal :header="imageModalTitle"
        :style="{ width: '750px', maxWidth: '95vw' }" class="p-fluid rounded-2xl overflow-hidden shadow-2xl border-0">
        <div class="p-4 flex flex-col items-center justify-center bg-slate-950 rounded-xl">
            <img :src="imageModalSrc" class="w-full max-h-[75vh] object-contain rounded shadow-lg"
                alt="Weight Snapshot" />
        </div>
    </Dialog>
</template>
