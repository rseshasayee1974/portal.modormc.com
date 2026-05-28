<script setup>
import { ref } from 'vue';

defineProps({
    label: {
        type: String,
        default: 'Upload Image',
    },
    accept: {
        type: String,
        default: 'image/*',
    },
    shortcut: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['file-selected']);
const fileInput = ref(null);

const handleClick = () => {
    fileInput.value?.click();
};

const handleFileChange = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        emit('file-selected', file);
    }
};
</script>

<template>
    <div>
        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            class="hidden"
            @change="handleFileChange"
        />
        <button
            type="button"
            class="upload-button"
            :disabled="disabled"
            @click="handleClick"
        >
            <span>{{ label }}</span>
            <span v-if="shortcut" class="shortcut">{{ shortcut }}</span>
        </button>
    </div>
</template>

<style scoped>
.upload-button {
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

.upload-button:hover:not(:disabled) {
    background-color: #fff5f5;
    border-color: #ff5252;
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);
}

.upload-button:active:not(:disabled) {
    background-color: #ffe0e0;
    transform: scale(0.98);
}

.upload-button:focus {
    outline: none;
    ring: 2px solid #ff6b6b;
    ring-offset: 2px;
}

.upload-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.shortcut {
    font-size: 0.875rem;
    color: #9ca3af;
    font-weight: 400;
}
</style>
