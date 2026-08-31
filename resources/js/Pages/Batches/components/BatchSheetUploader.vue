<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue';
import BatchSheetProgress from './BatchSheetProgress.vue';
import DuplicateWarning from './DuplicateWarning.vue';
import Button from 'primevue/button';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    batchId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['close', 'completed', 'openReview']);

const dragActive = ref(false);
const uploading = ref(false);
const processing = ref(false);
const progressVal = ref(0);
const statusMsg = ref('');
const errorMsg = ref<string | null>(null);
const logs = ref<Array<{ time: string; level: string; message: string }>>([]);

// Duplicate popup states
const showDuplicateDialog = ref(false);
const duplicateData = ref<any>(null);

const activeUploadId = ref<number | null>(null);

const onDragOver = (e: DragEvent) => {
    e.preventDefault();
    dragActive.value = true;
};

const onDragLeave = () => {
    dragActive.value = false;
};

const onDrop = (e: DragEvent) => {
    e.preventDefault();
    dragActive.value = false;
    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        handleFile(e.dataTransfer.files[0]);
    }
};

const onFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        handleFile(target.files[0]);
    }
};

const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'csv'];
const maxSizeBytes = 20 * 1024 * 1024; // 20MB

const handleFile = async (file: File) => {
    // 1. Client-Side Pre-Validation
    const fileName = file.name || '';
    const fileExt = fileName.split('.').pop()?.toLowerCase() || '';

    if (!allowedExtensions.includes(fileExt)) {
        Swal.fire({
            title: 'Unsupported File Format',
            text: `File ".${fileExt}" is not supported. Please upload a Batch Sheet in PDF, JPEG, PNG, WEBP, or Excel format.`,
            icon: 'error',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    if (file.size > maxSizeBytes) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
        Swal.fire({
            title: 'File Too Large',
            text: `The selected file is ${sizeMb} MB. Maximum allowed upload size is 20 MB.`,
            icon: 'error',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    uploading.value = true;
    errorMsg.value = null;
    logs.value = [];
    progressVal.value = 0;

    const formData = new FormData();
    formData.append('file', file);
    if (props.batchId) {
        formData.append('batch_id', props.batchId.toString());
    }

    try {
        statusMsg.value = 'Uploading & Identifying Plant Driver...';
        processing.value = true;
        uploading.value = false;
        
        const response = await axios.post(route('batch-sheets.upload'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        processing.value = false;

        if (response.data.status === 'duplicate') {
            duplicateData.value = response.data.duplicate;
            showDuplicateDialog.value = true;
            return;
        }

        if (response.data.upload_id) {
            activeUploadId.value = response.data.upload_id;
            // Emit openReview so parent opens the split-screen verification window
            emit('openReview', response.data.upload_id);
            emit('completed', response.data);
        }
    } catch (e: any) {
        processing.value = false;
        uploading.value = false;
        const msg = e.response?.data?.message || e.response?.data?.error || 'Failed to upload batch sheet. Please verify document format.';
        errorMsg.value = msg;
        
        Swal.fire({
            title: 'Upload Validation Failed',
            text: msg,
            icon: 'warning',
            confirmButtonText: 'Try Another Document',
            confirmButtonColor: '#4f46e5'
        });
    }
};

const closeDuplicate = () => {
    showDuplicateDialog.value = false;
    duplicateData.value = null;
};

const reprocessDuplicate = async () => {
    if (!duplicateData.value) return;
    const dupId = duplicateData.value.id;
    closeDuplicate();
    
    // Not supported in synchronous mode unless we pass existing ID
};

const viewDuplicateBatch = () => {
    if (!duplicateData.value) return;
    const batchId = duplicateData.value.id;
    closeDuplicate();
    emit('close');
    window.location.href = route('batches.show', batchId);
};
</script>

<template>
    <div class="max-w-xl mx-auto py-8">
        <!-- Progress panel -->
        <div v-if="processing || uploading" class="space-y-6">
            <BatchSheetProgress 
                :status="statusMsg || 'uploading'"
                :progress="progressVal"
                :logs="logs"
                :errorMessage="errorMsg"
            />
            <div class="text-center" v-if="statusMsg !== 'failed'">
                <p class="text-xs text-gray-400 italic">Please do not close this window during extraction.</p>
            </div>
            <div class="text-center mt-4" v-else>
                <Button label="Try Another File" class="p-button-text p-button-sm" @click="processing = false; uploading = false; errorMsg = null;" />
            </div>
        </div>

        <!-- Dropzone -->
        <div 
            v-else
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            class="border-2 border-dashed rounded-2xl p-10 text-center transition-all duration-300 cursor-pointer"
            :class="[
                dragActive ? 'border-indigo-600 bg-indigo-50/50' : 'border-gray-300 bg-white hover:border-indigo-500/80 hover:bg-gray-50/30'
            ]"
            @click="$refs.fileSelectInput.click()"
        >
            <input 
                ref="fileSelectInput" 
                type="file" 
                class="hidden" 
                accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif,.bmp,.webp,.xlsx,.xls,.csv"
                @change="onFileSelect" 
            />
            
            <div class="mx-auto w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4 text-indigo-600">
                <i class="pi pi-upload text-2xl"></i>
            </div>
            
            <h3 class="text-md font-bold text-gray-800 mb-1">Upload Batch Sheet Document</h3>
            <p class="text-xs text-gray-500 mb-4 max-w-xs mx-auto leading-relaxed">
                Drag and drop your batch sheet file here, or click to browse.
            </p>
            
            <div class="inline-flex flex-wrap justify-center gap-2 max-w-sm mx-auto">
                <span class="px-2 py-1 rounded bg-gray-100 text-gray-500 font-bold uppercase tracking-wider text-[9px]">PDF</span>
                <span class="px-2 py-1 rounded bg-gray-100 text-gray-500 font-bold uppercase tracking-wider text-[9px]">JPG/PNG</span>
                <span class="px-2 py-1 rounded bg-gray-100 text-gray-500 font-bold uppercase tracking-wider text-[9px]">Excel</span>
                <span class="px-2 py-1 rounded bg-gray-100 text-gray-500 font-bold uppercase tracking-wider text-[9px]">CSV</span>
            </div>
            
            <div v-if="errorMsg" class="mt-4 p-3 bg-red-50 text-red-700 text-xs font-semibold rounded-lg">
                {{ errorMsg }}
            </div>
        </div>

        <!-- Duplicate detection warning modal -->
        <DuplicateWarning 
            :visible="showDuplicateDialog"
            :duplicateInfo="duplicateData"
            @close="closeDuplicate"
            @reprocess="reprocessDuplicate"
            @viewExisting="viewDuplicateBatch"
        />
    </div>
</template>
