import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Country {
    id: number;
    country_name: string;
    country_code: string;
    is_active: number | boolean;
}

export const useCountryStore = defineStore('country', () => {
    const countries = ref<Country[]>([]);

    function setCountries(data: Country[]) {
        countries.value = data;
    }

    function addCountry(item: Country) {
        countries.value.push(item);
    }

    function updateCountry(updatedItem: Country) {
        const index = countries.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            countries.value[index] = updatedItem;
        }
    }

    function removeCountry(id: number) {
        countries.value = countries.value.filter(a => a.id !== id);
    }

    return {
        countries,
        setCountries,
        addCountry,
        updateCountry,
        removeCountry
    };
});
