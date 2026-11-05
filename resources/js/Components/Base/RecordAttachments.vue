<!--
Author: ragul-onemodo
Created: 2026-11-05 12:36:13 Asia/Calcutta (UTC+05:30)
-->

<script setup lang="ts">
import { computed, ref } from 'vue';
import Popover from 'primevue/popover';

const props = withDefaults(defineProps<{
    modelValue?: File[];
    files?: { id: string; name: string }[] | null;
    errors?: Record<string, string | undefined>;
    downloadRoute?: string;
    recordId?: number;
    downloadOnly?: boolean;
    disabled?: boolean;
}>(), {
    modelValue: () => [],
    errors: () => ({}),
});

const emit = defineEmits<{ (e: 'update:modelValue', files: File[]): void }>();
const fileInput = ref<HTMLInputElement | null>(null);
const savedPopover = ref<InstanceType<typeof Popover> | null>(null);
const isPopoverOpen = ref(false);
const isDragging = ref(false);
const selectionError = ref('');
const acceptedTypes = '.xls,.xlsx,.pdf,.jpg,.jpeg,.png,.webp,.gif,.bmp,.tif,.tiff';
const allowedExtensions = new Set(acceptedTypes.split(',').map(type => type.slice(1)));
const savedFiles = computed(() => props.downloadRoute && props.recordId ? props.files ?? [] : []);
const canSelect = computed(() => !props.disabled && props.modelValue.length < 5);
const error = computed(() => selectionError.value || Object.entries(props.errors)
    .find(([key]) => key === 'attachments' || key.startsWith('attachments.'))?.[1]);

const extension = (name: string) => name.split('.').pop()?.toLowerCase() ?? '';
const fileType = (name: string) => {
    const ext = extension(name);
    if (ext === 'pdf') return { icon: 'pi-file-pdf', tone: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' };
    if (ext === 'xls' || ext === 'xlsx') return { icon: 'pi-file-excel', tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' };
    if (allowedExtensions.has(ext)) return { icon: 'pi-image', tone: 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300' };
    return { icon: 'pi-paperclip', tone: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200' };
};

const fileSize = (bytes: number) => bytes < 1024 * 1024
    ? `${Math.max(1, Math.round(bytes / 1024))} KB`
    : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;

const addFiles = (selected: File[]) => {
    if (props.disabled || !selected.length) return;
    selectionError.value = '';
    if (props.modelValue.length + selected.length > 5) {
        selectionError.value = 'Choose up to 5 files per save.';
        return;
    }
    if (selected.some(file => file.size > 2 * 1024 * 1024)) {
        selectionError.value = 'Each file must be 2 MB or smaller.';
        return;
    }
    if (selected.some(file => !allowedExtensions.has(extension(file.name)))) {
        selectionError.value = 'Choose Excel, PDF or image files.';
        return;
    }
    emit('update:modelValue', [...props.modelValue, ...selected]);
};

const selectFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    addFiles(Array.from(input.files ?? []));
    input.value = '';
};

const dragOver = (event: DragEvent) => {
    if (event.dataTransfer?.types.includes('Files')) {
        event.dataTransfer.dropEffect = canSelect.value ? 'copy' : 'none';
        isDragging.value = canSelect.value;
    }
};

const dropFiles = (event: DragEvent) => {
    isDragging.value = false;
    addFiles(Array.from(event.dataTransfer?.files ?? []));
};

const removeFile = (index: number) => {
    selectionError.value = '';
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
};
</script>

<template>
    <div :class="downloadOnly ? 'text-xs' : 'space-y-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900'">
        <template v-if="downloadOnly">
            <button v-if="savedFiles.length" type="button"
                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 font-semibold text-indigo-700 hover:bg-indigo-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200"
                aria-haspopup="dialog" :aria-expanded="isPopoverOpen" @click.stop="savedPopover?.toggle($event)">
                <i class="pi pi-paperclip" aria-hidden="true"></i>
                {{ savedFiles.length }} {{ savedFiles.length === 1 ? 'file' : 'files' }}
                <i class="pi pi-chevron-down text-[9px]" aria-hidden="true"></i>
            </button>
            <span v-else class="text-slate-400">—</span>
        </template>

        <template v-else>
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <i class="pi pi-paperclip text-indigo-600 dark:text-indigo-300" aria-hidden="true"></i>
                    Attachments
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400">Optional</span>
            </div>
            <input ref="fileInput" type="file" multiple hidden :disabled="!canSelect"
                :accept="acceptedTypes" @change="selectFiles" />
            <div @dragover.prevent="dragOver" @dragleave="isDragging = false" @drop.prevent="dropFiles">
                <button type="button" :disabled="!canSelect" @click="fileInput?.click()"
                    class="flex w-full items-center gap-3 rounded-lg border border-dashed px-3 py-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :class="isDragging ? 'border-indigo-500 bg-indigo-100 dark:bg-indigo-950' : 'border-slate-300 bg-slate-50 enabled:hover:border-indigo-400 enabled:hover:bg-indigo-50 dark:border-slate-600 dark:bg-slate-800/60 dark:enabled:hover:bg-indigo-950/50'">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-200">
                        <i class="pi pi-cloud-upload" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-indigo-700 dark:text-indigo-200">
                            {{ disabled ? 'Attachments unavailable' : canSelect ? 'Choose files' : 'File limit reached' }}
                            <span v-if="canSelect" class="font-normal text-slate-600 dark:text-slate-300"> or drag them here</span>
                        </span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Excel, PDF or images. Up to 5 files per save, 2 MB each.</span>
                    </span>
                </button>
            </div>
            <p v-if="error" role="alert" class="flex items-center gap-2 rounded-md bg-rose-50 px-2.5 py-2 text-xs text-rose-700 dark:bg-rose-950/50 dark:text-rose-200">
                <i class="pi pi-exclamation-circle" aria-hidden="true"></i> {{ error }}
            </p>
            <div v-if="modelValue.length" class="space-y-1.5">
                <p class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ modelValue.length }} ready to upload on save</p>
                <ul class="space-y-1.5">
                    <li v-for="(file, index) in modelValue" :key="index"
                        class="flex min-w-0 items-center gap-2 rounded-lg border border-slate-200 px-2 py-1.5 dark:border-slate-700">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-md" :class="fileType(file.name).tone">
                            <i class="pi" :class="fileType(file.name).icon" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-slate-800 dark:text-slate-100" :title="file.name">{{ file.name }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ fileSize(file.size) }}</p>
                        </div>
                        <button type="button" class="flex size-8 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-50 dark:hover:bg-rose-950/50"
                            :disabled="disabled" :aria-label="`Remove ${file.name}`" title="Remove file" @click="removeFile(index)">
                            <i class="pi pi-times text-xs" aria-hidden="true"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </template>

        <component :is="downloadOnly ? Popover : 'div'" v-if="savedFiles.length" ref="savedPopover"
            :pt="downloadOnly ? { root: { 'aria-label': 'Saved attachments' } } : undefined"
            @show="isPopoverOpen = true" @hide="isPopoverOpen = false">
            <div :class="downloadOnly ? 'w-64 max-w-[75vw]' : 'mt-2 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700'">
                <p class="mb-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">Saved attachments ({{ savedFiles.length }})</p>
                <ul class="max-h-56 space-y-1 overflow-y-auto">
                    <li v-for="file in savedFiles" :key="file.id">
                        <a :href="route(downloadRoute!, [recordId, file.id])" :aria-label="`Download ${file.name}`" :title="file.name"
                            class="flex min-w-0 items-center gap-2 rounded-md p-1.5 text-xs font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-slate-200 dark:hover:bg-indigo-950/60">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-md" :class="fileType(file.name).tone">
                                <i class="pi" :class="fileType(file.name).icon" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                            <i class="pi pi-download shrink-0 text-indigo-600 dark:text-indigo-300" aria-hidden="true"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </component>
    </div>
</template>
