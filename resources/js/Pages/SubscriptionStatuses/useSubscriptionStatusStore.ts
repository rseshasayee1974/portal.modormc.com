import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface SubscriptionStatus {
    id: number;
    status_name: string;
}

export const useSubscriptionStatusStore = defineStore('subscriptionStatus', () => {
    const subscriptionStatuses = ref<SubscriptionStatus[]>([]);

    function setSubscriptionStatuses(data: SubscriptionStatus[]) {
        subscriptionStatuses.value = data;
    }

    function addSubscriptionStatus(item: SubscriptionStatus) {
        subscriptionStatuses.value.push(item);
    }

    function updateSubscriptionStatus(updatedItem: SubscriptionStatus) {
        const index = subscriptionStatuses.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            subscriptionStatuses.value[index] = updatedItem;
        }
    }

    function removeSubscriptionStatus(id: number) {
        subscriptionStatuses.value = subscriptionStatuses.value.filter(a => a.id !== id);
    }

    return {
        subscriptionStatuses,
        setSubscriptionStatuses,
        addSubscriptionStatus,
        updateSubscriptionStatus,
        removeSubscriptionStatus
    };
});
