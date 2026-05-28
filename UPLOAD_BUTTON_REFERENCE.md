<!-- UPLOAD BUTTON REFERENCE GUIDE -->

<!-- Example 1: Basic Upload Button with Vue Component -->
<UploadButton
    label="Upload Image"
    accept="image/*"
    shortcut="Ctrl + ⏎"
    @file-selected="handleFileSelected"
/>

<!-- Example 2: Form Actions with Upload Style -->
<StyledFormActions
    label="Enroll Asset"
    cancel-label="Reset Registry"
    variant="upload"
    :loading="form.processing"
    @submit="submitForm"
    @reset="resetForm"
/>

<!-- Example 3: Using CSS Classes (Plain HTML) -->
<button class="btn-upload">
    <span>Upload Image</span>
    <span class="btn-shortcut">Ctrl + ⏎</span>
</button>

<!-- Example 4: Upload Button with Icon -->
<button class="btn-upload">
    <i class="pi pi-cloud-upload"></i>
    <span>Upload File</span>
</button>

<!-- Example 5: Multiple Form Actions -->
<div class="flex items-center justify-end gap-3">
    <button 
        class="px-6 py-2 bg-white border border-gray-300 rounded-md 
                text-gray-600 hover:bg-gray-50 transition"
        @click="cancel"
    >
        Cancel
    </button>
    <button class="btn-upload">
        <i class="pi pi-check"></i>
        <span>Submit</span>
    </button>
</div>

<!-- Styling Options -->
<style>
/* Variant 1: Default Upload Button */
.btn-upload {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.5rem;
    background-color: white;
    border: 2px solid #ff6b6b;
    border-radius: 9999px;
    font-size: 1rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease-in-out;
}

.btn-upload:hover:not(:disabled) {
    background-color: #fff5f5;
    border-color: #ff5252;
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);
    transform: translateY(-2px);
}

.btn-upload:active:not(:disabled) {
    background-color: #ffe0e0;
    transform: translateY(0);
}

/* Variant 2: Dark Mode Upload Button */
.btn-upload.dark {
    background-color: #1f2937;
    border-color: #ff6b6b;
    color: #e5e7eb;
}

.btn-upload.dark:hover:not(:disabled) {
    background-color: #374151;
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.15);
}

/* Variant 3: Large Upload Button */
.btn-upload.lg {
    padding: 0.875rem 2rem;
    font-size: 1.125rem;
}

/* Variant 4: Small Upload Button */
.btn-upload.sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

/* Shortcut Label */
.btn-shortcut {
    font-size: 0.875rem;
    color: #9ca3af;
    font-weight: 400;
}

/* Disabled State */
.btn-upload:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Focus State */
.btn-upload:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
}

/* Loading State */
.btn-upload.loading {
    position: relative;
    color: transparent;
}

.btn-upload.loading::after {
    content: '';
    position: absolute;
    width: 1rem;
    height: 1rem;
    top: 50%;
    left: 50%;
    margin-top: -0.5rem;
    margin-left: -0.5rem;
    border: 2px solid rgba(255, 107, 107, 0.3);
    border-top-color: #ff6b6b;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>

<!-- Usage Examples in Vue Components -->

<!-- Form with Upload-style Submit -->
<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Asset Name</label>
            <input 
                v-model="form.name" 
                type="text" 
                class="mt-1 block w-full rounded-md border-gray-300"
            />
        </div>

        <!-- Form Actions with Upload Style -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <button 
                type="button"
                class="px-6 py-2 bg-white border border-gray-300 rounded-md 
                        text-gray-600 hover:bg-gray-50 transition"
                @click="resetForm"
            >
                Reset Registry
            </button>
            <button 
                type="submit"
                class="btn-upload"
                :disabled="form.processing"
            >
                <span v-if="!form.processing">Enroll Asset</span>
                <span v-else class="spinner"></span>
            </button>
        </div>
    </form>
</template>

<script setup>
import { ref } from 'vue';

const form = ref({
    name: '',
    processing: false
});

const submitForm = async () => {
    form.value.processing = true;
    try {
        // Submit logic here
        await new Promise(resolve => setTimeout(resolve, 1000));
    } finally {
        form.value.processing = false;
    }
};

const resetForm = () => {
    form.value.name = '';
};
</script>

<!-- Color Variants -->

<!-- Teal Upload Button -->
<button class="btn-upload" style="border-color: #14b8a6; --border-color: #14b8a6;">
    Upload
</button>

<!-- Purple Upload Button -->
<button class="btn-upload" style="border-color: #8b5cf6;">
    Upload
</button>

<!-- Blue Upload Button -->
<button class="btn-upload" style="border-color: #3b82f6;">
    Upload
</button>
