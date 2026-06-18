<template>
  <div class="space-y-6">
    <div
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      :class="[
        'border-2 border-dashed rounded-2xl p-8 text-center transition-all flex flex-col items-center justify-center gap-4 cursor-pointer',
        isDragging
          ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 scale-[1.01]'
          : 'border-slate-200 dark:border-slate-700 hover:border-indigo-400 hover:bg-slate-50/50 dark:hover:bg-slate-800/20'
      ]"
      @click="triggerFileInput"
    >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        accept=".pdf,.docx,.txt,.doc"
        @change="handleFileSelect"
      />

      <div class="w-14 h-14 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center border border-indigo-100 dark:border-indigo-900 shadow-sm">
        <CloudArrowUpIcon v-if="!uploading" class="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
        <ArrowPathIcon v-else class="w-7 h-7 text-indigo-600 dark:text-indigo-400 animate-spin" />
      </div>

      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
          {{ uploading ? 'Uploading and processing...' : 'Drag & Drop your document here' }}
        </p>
        <p class="text-xs text-slate-400">
          Supports PDF, DOCX, TXT up to 20MB
        </p>
      </div>

      <div v-if="selectedFile && !uploading" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 rounded-xl flex items-center gap-3 max-w-md">
        <DocumentIcon class="w-5 h-5 text-slate-500 flex-shrink-0" />
        <span class="text-xs text-slate-700 dark:text-slate-300 truncate font-medium max-w-[200px]">
          {{ selectedFile.name }}
        </span>
        <span class="text-[10px] text-slate-400 font-mono">
          ({{ formatBytes(selectedFile.size) }})
        </span>
        <button @click.stop="clearFile" class="text-slate-400 hover:text-red-500 p-0.5 rounded">
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Optional Title Override -->
    <div v-if="selectedFile && !uploading" class="flex flex-col gap-2 p-4 bg-slate-50 dark:bg-slate-900/40 rounded-2xl border border-slate-100 dark:border-slate-800">
      <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
        Document Title (Defaults to filename)
      </label>
      <input
        v-model="title"
        type="text"
        placeholder="Enter custom title"
        class="w-full text-xs font-sans rounded-lg border border-slate-200 dark:border-gray-700 p-2.5 bg-white dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-300"
      />
    </div>

    <!-- Upload Button -->
    <div v-if="selectedFile" class="flex justify-end gap-2">
      <button
        @click="clearFile"
        :disabled="uploading"
        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
      >
        Cancel
      </button>
      <button
        @click="uploadDocument"
        :disabled="uploading"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl text-xs font-semibold flex items-center gap-2 transition-all shadow-md shadow-indigo-200/50"
      >
        <ArrowPathIcon v-if="uploading" class="w-4 h-4 animate-spin" />
        <span>{{ uploading ? 'Parsing & Chunking...' : 'Index & Vectorize Document' }}</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import {
  CloudArrowUpIcon,
  ArrowPathIcon,
  DocumentIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';

const emit = defineEmits(['uploaded', 'error']);

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const title = ref<string>('');
const uploading = ref<boolean>(false);
const isDragging = ref<boolean>(false);

const triggerFileInput = () => {
  if (uploading.value) return;
  fileInput.value?.click();
};

const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    setFile(target.files[0]);
  }
};

const handleDrop = (e: DragEvent) => {
  isDragging.value = false;
  if (uploading.value) return;
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    setFile(e.dataTransfer.files[0]);
  }
};

const setFile = (file: File) => {
  selectedFile.value = file;
  title.value = file.name.replace(/\.[^/.]+$/, ""); // strip extension
};

const clearFile = () => {
  selectedFile.value = null;
  title.value = '';
  if (fileInput.value) fileInput.value.value = '';
};

const uploadDocument = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('title', title.value);

  try {
    const response = await axios.post('/api/ai/knowledge/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    if (response.data.success) {
      emit('uploaded', response.data);
      clearFile();
    } else {
      throw new Error(response.data.error || 'Upload failed');
    }
  } catch (err: any) {
    console.error('File upload error:', err);
    const errorMsg = err.response?.data?.error || err.message || 'Failed to upload and parse file.';
    emit('error', errorMsg);
  } finally {
    uploading.value = false;
  }
};

const formatBytes = (bytes: number, decimals = 2) => {
  if (!bytes) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};
</script>
