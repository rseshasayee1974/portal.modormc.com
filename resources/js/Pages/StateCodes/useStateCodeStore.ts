import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface StateCode {
    id: number;
    country_id: number;
    state_code: string;
    state_name: string;
}

export interface CountryOption {
    id: number;
    country_name: string;
}

export const useStateCodeStore = defineStore('stateCode', () => {
    const stateCodes = ref<StateCode[]>([]);
    const countries = ref<CountryOption[]>([]);

    function setStateCodes(data: StateCode[]) {
        stateCodes.value = data;
    }

    function setCountries(data: CountryOption[]) {
        countries.value = data;
    }

    function addStateCode(item: StateCode) {
        stateCodes.value.push(item);
    }

    function updateStateCode(updatedItem: StateCode) {
        const index = stateCodes.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            stateCodes.value[index] = updatedItem;
        }
    }

    function removeStateCode(id: number) {
        stateCodes.value = stateCodes.value.filter(a => a.id !== id);
    }

    return {
        stateCodes,
        countries,
        setStateCodes,
        setCountries,
        addStateCode,
        updateStateCode,
        removeStateCode
    };
});
