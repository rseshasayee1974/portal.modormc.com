<template>
    <Transition name="fade">
        <div v-if="isVisible" class="global-loader">
            <div class="loader-content">
                <template v-if="loaderImage">
                    <img :src="loaderImage" class="custom-loader-img" alt="Loading..." />
                </template>
                <template v-else>
                    <div class="spinner">
                        <div class="inner-circle"></div>
                    </div>
                </template>
                <div class="loader-text">Loading...</div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { isGlobalLoading } from '@/Utils/loadingState';

const isInertiaLoading = ref(false);
const isVisible = computed(() => isInertiaLoading.value || isGlobalLoading.value);
let timeout = null;

router.on('start', () => {
    // Show loader only if it takes more than 150ms to prevent flickering on fast loads
    timeout = setTimeout(() => {
        isInertiaLoading.value = true;
    }, 150);
});

router.on('finish', (event) => {
    clearTimeout(timeout);
    isInertiaLoading.value = false;
});
</script>

<style scoped>
.global-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

.loader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 3px solid #ebf0fc;
    border-top: 3px solid #7e63da;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.inner-circle {
    width: 20px;
    height: 20px;
    border: 2px solid #7e63da;
    border-bottom: 2px solid transparent;
    border-radius: 50%;
    animation: spin-reverse 0.6s linear infinite;
}

.loader-text {
    font-family: 'Outfit', sans-serif;
    font-size: 0.875rem;
    font-weight: 700;
    color: #7e63da;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes spin-reverse {
    0% { transform: rotate(360deg); }
    100% { transform: rotate(0deg); }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
