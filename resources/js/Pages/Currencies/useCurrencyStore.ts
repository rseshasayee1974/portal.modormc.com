import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Currency {
    id: number;
    currency_name: string;
    currency_code: string;
}

export const useCurrencyStore = defineStore('currency', () => {
    const currencies = ref<Currency[]>([]);

    function setCurrencies(data: Currency[]) {
        currencies.value = data;
    }

    function addCurrency(item: Currency) {
        currencies.value.push(item);
    }

    function updateCurrency(updatedItem: Currency) {
        const index = currencies.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            currencies.value[index] = updatedItem;
        }
    }

    function removeCurrency(id: number) {
        currencies.value = currencies.value.filter(a => a.id !== id);
    }

    return {
        currencies,
        setCurrencies,
        addCurrency,
        updateCurrency,
        removeCurrency
    };
});
