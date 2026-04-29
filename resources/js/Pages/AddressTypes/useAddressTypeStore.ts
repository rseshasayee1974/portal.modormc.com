import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface AddressType {
    id: number;
    type: string;
}

export const useAddressTypeStore = defineStore('addressType', () => {
    const addressTypes = ref<AddressType[]>([]);

    function setAddressTypes(data: AddressType[]) {
        addressTypes.value = data;
    }

    function addAddressType(item: AddressType) {
        addressTypes.value.push(item);
    }

    function updateAddressType(updatedItem: AddressType) {
        const index = addressTypes.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            addressTypes.value[index] = updatedItem;
        }
    }

    function removeAddressType(id: number) {
        addressTypes.value = addressTypes.value.filter(a => a.id !== id);
    }

    return {
        addressTypes,
        setAddressTypes,
        addAddressType,
        updateAddressType,
        removeAddressType
    };
});
