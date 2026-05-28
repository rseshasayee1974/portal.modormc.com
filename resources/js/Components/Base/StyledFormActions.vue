<script setup>
import BaseButton from './BaseButton.vue';

interface Props {
    label?: string;
    cancelLabel?: string;
    submitIcon?: string;
    cancelIcon?: string;
    loading?: boolean;
    showCancel?: boolean;
    disabled?: boolean;
    variant?: 'default' | 'upload' | 'outline';
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Submit',
    cancelLabel: 'Cancel',
    submitIcon: 'pi pi-check',
    cancelIcon: 'pi pi-times',
    loading: false,
    showCancel: true,
    disabled: false,
    variant: 'upload',
});

const emit = defineEmits<{
    submit: [];
    cancel: [];
    reset: [];
}>();

const handleSubmit = () => {
    emit('submit');
};

const handleCancel = () => {
    emit('cancel');
    emit('reset');
};
</script>

<template>
    <div class="form-actions" :class="`form-actions--${variant}`">
        <!-- Cancel Button -->
        <button
            v-if="showCancel"
            type="button"
            class="form-actions__btn form-actions__btn--cancel"
            :disabled="loading"
            @click="handleCancel"
        >
            <i v-if="cancelIcon" :class="cancelIcon"></i>
            <span>{{ cancelLabel }}</span>
        </button>

        <!-- Primary Action Button -->
        <button
            type="submit"
            class="form-actions__btn form-actions__btn--primary"
            :disabled="loading || disabled"
            :class="{ 'is-loading': loading }"
            @click="handleSubmit"
        >
            <i v-if="submitIcon && !loading" :class="submitIcon"></i>
            <span v-if="!loading">{{ label }}</span>
            <span v-else class="spinner"></span>
        </button>
    </div>
</template>

<style scoped>
.form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 1rem;
}

.form-actions__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease-in-out;
    border-radius: 6px;
}

/* Upload Variant (Default) */
.form-actions--upload .form-actions__btn--primary {
    padding: 0.625rem 1.5rem;
    background-color: white;
    border: 2px solid #ff6b6b;
    color: #6b7280;
    border-radius: 9999px;
}

.form-actions--upload .form-actions__btn--primary:hover:not(:disabled) {
    background-color: #fff5f5;
    border-color: #ff5252;
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);
    transform: translateY(-2px);
}

.form-actions--upload .form-actions__btn--primary:active:not(:disabled) {
    background-color: #ffe0e0;
    transform: translateY(0);
}

.form-actions--upload .form-actions__btn--cancel {
    padding: 0.625rem 1rem;
    background-color: transparent;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.form-actions--upload .form-actions__btn--cancel:hover:not(:disabled) {
    background-color: #f9fafb;
    border-color: #d1d5db;
    color: #6b7280;
}

/* Default Variant */
.form-actions--default .form-actions__btn--primary {
    padding: 0.625rem 2rem;
    background-color: #4f46e5;
    color: white;
    border: 2px solid #4f46e5;
}

.form-actions--default .form-actions__btn--primary:hover:not(:disabled) {
    background-color: #4338ca;
    border-color: #4338ca;
    box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
}

.form-actions--default .form-actions__btn--cancel {
    padding: 0.625rem 2rem;
    background-color: white;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.form-actions--default .form-actions__btn--cancel:hover:not(:disabled) {
    background-color: #f3f4f6;
    border-color: #d1d5db;
}

/* Outline Variant */
.form-actions--outline .form-actions__btn {
    padding: 0.625rem 1.5rem;
    border: 2px solid #d1d5db;
    background-color: white;
    color: #374151;
}

.form-actions--outline .form-actions__btn--primary {
    border-color: #4f46e5;
    color: #4f46e5;
    background-color: white;
}

.form-actions--outline .form-actions__btn--primary:hover:not(:disabled) {
    background-color: #4f46e5;
    color: white;
}

/* Disabled State */
.form-actions__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Loading Spinner */
.spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.form-actions--upload .spinner {
    border-color: rgba(255, 107, 107, 0.3);
    border-top-color: #ff6b6b;
}
</style>
