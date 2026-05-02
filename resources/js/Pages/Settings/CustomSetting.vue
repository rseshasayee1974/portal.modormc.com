<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputSwitch from 'primevue/inputswitch';
import Card from 'primevue/card';
import Swal from 'sweetalert2';
import { 
    Cog6ToothIcon, 
    VideoCameraIcon, 
    ScaleIcon, 
    ArrowPathIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    batchingSettings: any;
    plantId: number;
    plantName: string;
}>();

const form = useForm({
    module: 'batching',
    settings: {
        newweight: props.batchingSettings?.newweight == 1,
        manual_weight: props.batchingSettings?.manual_weight == 1,
        InvoiceInMetricTon: props.batchingSettings?.InvoiceInMetricTon == 1,
        camera: props.batchingSettings?.camera == 1,
        camera_url: props.batchingSettings?.camera_url || '',
        camera_url_1: props.batchingSettings?.camera_url_1 || '',
        camera_url_2: props.batchingSettings?.camera_url_2 || '',
        loader_gif: props.batchingSettings?.loader_gif || '',
    }
});

const submit = () => {
    const payload = {
        ...form.settings,
        newweight: form.settings.newweight ? 1 : 0,
        manual_weight: form.settings.manual_weight ? 1 : 0,
        InvoiceInMetricTon: form.settings.InvoiceInMetricTon ? 1 : 0,
        camera: form.settings.camera ? 1 : 0,
    };

    form.transform((data) => ({
        ...data,
        settings: payload
    })).post(route('settings.customsetting.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Settings updated',
                showConfirmButton: false,
                timer: 2000
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Custom Settings">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Custom Settings
                </h2>
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50 border border-indigo-100 rounded-full">
                    <span class="text-[10px] font-bold uppercase text-indigo-400">Active Plant:</span>
                    <span class="text-xs font-bold text-indigo-700">{{ plantName }}</span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Weighbridge Settings -->
                    <Card class="shadow-lg border-t-4 border-emerald-500">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <ScaleIcon class="w-6 h-6 text-emerald-600" />
                                <span>Weighbridge Configuration</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-6">
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                                    <div>
                                        <h4 class="font-bold text-slate-700">Local API Proxy (V2) <span class="text-[10px] text-slate-400 font-normal"> [newweight]</span></h4>
                                        <p class="text-xs text-slate-500">Use localhost:8089 instead of direct Serial Port</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.newweight" />
                                </div>

                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                                    <div>
                                        <h4 class="font-bold text-slate-700">Manual Weight Entry <span class="text-[10px] text-slate-400 font-normal"> [manual_weight]</span></h4>
                                        <p class="text-xs text-slate-500">Allow users to type weight if scale is disconnected</p>
                                        <div v-if="form.settings.manual_weight" class="mt-1">
                                            <span class="text-[9px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">Manual Entry Enabled</span>
                                        </div>
                                    </div>
                                    <InputSwitch v-model="form.settings.manual_weight" />
                                </div>

                                <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg border border-indigo-100">
                                    <div>
                                        <h4 class="font-bold text-indigo-700">Invoice In Metric Ton <span class="text-[10px] text-indigo-400 font-normal"> [InvoiceInMetricTon]</span></h4>
                                        <p class="text-xs text-indigo-500">Calculate invoice amount based on Batch Size (m³) instead of Net Weight</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.InvoiceInMetricTon" />
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Camera Settings -->
                    <Card class="shadow-lg border-t-4 border-cyan-500">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <VideoCameraIcon class="w-6 h-6 text-cyan-600" />
                                <span>Camera Integration</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100 mb-4">
                                    <div>
                                        <h4 class="font-bold text-slate-700">Enable Snapshots <span class="text-[10px] text-slate-400 font-normal"> [camera]</span></h4>
                                        <p class="text-xs text-slate-500">Capture truck images during weight capture</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.camera" />
                                </div>

                                <div v-if="form.settings.camera" class="space-y-4 animate-fade-in">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-500 uppercase">Default Camera URL <span class="text-[9px] text-slate-300 normal-case font-normal">[camera_url]</span></label>
                                        <InputText v-model="form.settings.camera_url" placeholder="http://192.168.1.10/snap.jpg?usr=admin&pwd=admin" class="w-full text-sm" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-500 uppercase">Camera 1 (Entry/Empty) <span class="text-[9px] text-slate-300 normal-case font-normal">[camera_url_1]</span></label>
                                        <InputText v-model="form.settings.camera_url_1" placeholder="Optional specific URL" class="w-full text-sm" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-500 uppercase">Camera 2 (Exit/Loaded) <span class="text-[9px] text-slate-300 normal-case font-normal">[camera_url_2]</span></label>
                                        <InputText v-model="form.settings.camera_url_2" placeholder="Optional specific URL" class="w-full text-sm" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- UI/UX Settings -->
                    <Card class="shadow-lg border-t-4 border-indigo-500 md:col-span-2">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <Cog6ToothIcon class="w-6 h-6 text-indigo-600" />
                                <span>System Appearance</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Custom Global Loader (GIF URL) <span class="text-[9px] text-slate-300 normal-case font-normal">[loader_gif]</span></label>
                                    <InputText v-model="form.settings.loader_gif" placeholder="/storage/loaders/truck.gif" class="w-full text-sm" />
                                    <p class="text-[10px] text-slate-400 mt-1 italic">Note: Use the "Image to GIF" tool to generate your custom loader.</p>
                                </div>
                                <div class="flex items-center justify-end">
                                    <Button 
                                        @click="submit" 
                                        icon="pi pi-save" 
                                        label="Save All Settings" 
                                        class="p-button-lg p-button-raised p-button-indigo" 
                                        :loading="form.processing"
                                    />
                                </div>
                            </div>
                        </template>
                    </Card>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
