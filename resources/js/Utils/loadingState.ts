import { ref } from 'vue';

export const isGlobalLoading = ref(false);

export function showLoader() {
    isGlobalLoading.value = true;
}

export function hideLoader() {
    isGlobalLoading.value = false;
}
