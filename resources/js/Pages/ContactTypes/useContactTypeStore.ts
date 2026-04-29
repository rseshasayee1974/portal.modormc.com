import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface ContactType {
    id: number;
    type: string;
}

export const useContactTypeStore = defineStore('contactType', () => {
    const contactTypes = ref<ContactType[]>([]);

    function setContactTypes(data: ContactType[]) {
        contactTypes.value = data;
    }

    function addContactType(item: ContactType) {
        contactTypes.value.push(item);
    }

    function updateContactType(updatedItem: ContactType) {
        const index = contactTypes.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            contactTypes.value[index] = updatedItem;
        }
    }

    function removeContactType(id: number) {
        contactTypes.value = contactTypes.value.filter(a => a.id !== id);
    }

    return {
        contactTypes,
        setContactTypes,
        addContactType,
        updateContactType,
        removeContactType
    };
});
