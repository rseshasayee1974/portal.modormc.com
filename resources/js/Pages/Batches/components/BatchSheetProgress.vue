<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    progress: number;
    logs: Array<{ time: string; level: string; message: string }>;
    errorMessage: string | null;
}>();

const statusTitle = computed(() => {
    switch (props.status) {
        case 'uploaded': return 'File Uploaded';
        case 'validating': return 'Validating file signature...';
        case 'processing': return 'Processing document...';
        case 'ocr_running': return 'Running AI OCR (Scanned PDF)...';
        case 'extracting': return 'Extracting batch fields...';
        case 'review': return 'Ready for Review';
        case 'completed': return 'Batch Sheet Imported';
        case 'failed': return 'Processing Failed';
        default: return 'Processing...';
    }
});

const statusColorClass = computed(() => {
    if (props.status === 'failed') return 'bg-red-500 text-white';
    if (props.status === 'completed') return 'bg-emerald-500 text-white';
    if (props.status === 'review') return 'bg-indigo-500 text-white';
    return 'bg-blue-600 text-white';
});

const formatTime = (timeStr: string) => {
    try {
        return new Date(timeStr).toLocaleTimeString('en-IN', { hour12: false });
    } catch (e) {
        return '';
    }
};
</script>

<template>
    <div class="glassmorphism-card p-6 rounded-xl border border-gray-200/50 shadow-xl max-w-lg mx-auto bg-white/80 backdrop-blur-md">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i v-if="status !== 'failed' && status !== 'completed' && status !== 'review'" class="pi pi-spin pi-spinner text-blue-600"></i>
                <i v-else-if="status === 'completed'" class="pi pi-check-circle text-emerald-600"></i>
                <i v-else-if="status === 'review'" class="pi pi-info-circle text-indigo-600"></i>
                <i v-else class="pi pi-exclamation-triangle text-red-600"></i>
                {{ statusTitle }}
            </h3>
            <span class="text-xs px-2.5 py-1 rounded-full font-semibold uppercase tracking-wider" :class="statusColorClass">
                {{ status }}
            </span>
        </div>

        <!-- Progress Bar -->
        <div class="relative w-full h-3 bg-gray-100 rounded-full overflow-hidden mb-6">
            <div 
                class="absolute left-0 top-0 h-full transition-all duration-500 rounded-full bg-gradient-to-r"
                :class="[
                    status === 'failed' ? 'from-red-500 to-rose-600' : 
                    status === 'completed' ? 'from-emerald-500 to-teal-600' :
                    'from-blue-500 to-indigo-600'
                ]"
                :style="{ width: `${progress}%` }"
            ></div>
        </div>

        <!-- Error Panel -->
        <div v-if="errorMessage" class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r text-red-700 text-sm font-medium">
            <div class="flex gap-2">
                <i class="pi pi-times-circle mt-0.5"></i>
                <div>
                    <span class="font-bold">Extraction Error:</span> {{ errorMessage }}
                </div>
            </div>
        </div>

        <!-- Real-time Processing Logs -->
        <div class="bg-gray-900 rounded-lg p-4 font-mono text-[11px] text-gray-300 max-h-48 overflow-y-auto shadow-inner border border-gray-800">
            <div class="text-gray-500 border-b border-gray-800 pb-1 mb-2 uppercase text-[9px] tracking-wider font-bold">
                Processing pipeline logs
            </div>
            <div v-if="logs.length === 0" class="text-gray-500 italic">
                Initializing pipeline...
            </div>
            <div v-for="(log, idx) in logs" :key="idx" class="mb-1 flex items-start gap-2 leading-relaxed">
                <span class="text-gray-600 select-none">[{{ formatTime(log.time) }}]</span>
                <span :class="{
                    'text-amber-400': log.level === 'warning',
                    'text-red-400': log.level === 'error',
                    'text-blue-400': log.level === 'info',
                    'text-emerald-400': log.level === 'success'
                }">
                    {{ log.message }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glassmorphism-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
</style>
