<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { TrashIcon } from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = withDefaults(defineProps<{
    url?: string;
    title?: string;
    text?: string;
    confirmButtonText?: string;
    successMessage?: string;
    iconOnly?: boolean;
    buttonText?: string;
    disabled?: boolean;
}>(), {
    title: 'Confirm Deletion',
    text: 'This action cannot be undone.',
    confirmButtonText: 'Yes, Delete',
    successMessage: 'Removed successfully',
    iconOnly: true,
    buttonText: 'Delete',
    disabled: false
});

const emit = defineEmits<{
    (e: 'success'): void;
    (e: 'error'): void;
}>();

const handleClick = (e: MouseEvent) => {
    if (props.disabled) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return;
    }

    if (props.url) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        Swal.fire({
            title: props.title,
            text: props.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: props.confirmButtonText
        }).then((result) => {
            if (result.isConfirmed && props.url) {
                router.delete(props.url, {
                    onSuccess: () => {
                        Swal.fire({ 
                            toast: true, 
                            position: 'top-end', 
                            icon: 'success', 
                            title: props.successMessage, 
                            timer: 2000, 
                            showConfirmButton: false 
                        });
                        emit('success');
                    },
                    onError: () => {
                        emit('error');
                    }
                });
            }
        });
    }
};
</script>

<template>
    <button 
        type="button" 
        @click="handleClick" 
        :disabled="disabled"
        :class="[
            iconOnly ? 'action-btn delete' : 'action-btn-full delete',
            { 'opacity-40 cursor-not-allowed': disabled }
        ]"
    >
        <TrashIcon :class="iconOnly ? 'w-4 h-4' : 'w-4 h-4 mr-1.5'" />
        <span v-if="!iconOnly">{{ buttonText }}</span>
    </button>
</template>

<style scoped>
.action-btn { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    width: 32px; 
    height: 32px; 
    border-radius: 8px; 
    border: none; 
    background: transparent; 
    cursor: pointer; 
    transition: all 0.15s; 
    color: #ff3333;
}
.action-btn.delete:hover { 
    background: #fee2e2; 
    color: #ef4444; 
}

.action-btn-full {
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    padding: 6px 12px;
    border-radius: 8px; 
    border: 1px solid #e2e8f0; 
    background: white; 
    cursor: pointer; 
    transition: all 0.2s; 
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #ef4444;
}

.action-btn-full.delete:hover { 
    background: #fee2e2; 
    border-color: #fca5a5;
    color: #dc2626; 
}
</style>
