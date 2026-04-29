import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface TermsCondition {
    id: number;
    entity_id: number;
    order_type: string;
    terms_condition: string;
    status: string;
    entity?: { id: number, legal_name: string };
}

export const useTermsConditionStore = defineStore('termsCondition', () => {
    const termsConditions = ref<TermsCondition[]>([]);

    function setTermsConditions(data: TermsCondition[]) {
        termsConditions.value = data;
    }

    function addTermsCondition(item: TermsCondition) {
        termsConditions.value.push(item);
    }

    function updateTermsCondition(updatedItem: TermsCondition) {
        const index = termsConditions.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            termsConditions.value[index] = updatedItem;
        }
    }

    function removeTermsCondition(id: number) {
        termsConditions.value = termsConditions.value.filter(a => a.id !== id);
    }

    return {
        termsConditions,
        setTermsConditions,
        addTermsCondition,
        updateTermsCondition,
        removeTermsCondition
    };
});
