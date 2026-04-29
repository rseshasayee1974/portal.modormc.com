import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Plant {
    id: number;
    entity_id: number;
    code: string;
    name: string;
    gstin: string | null;
    latitude: string | null;
    longitude: string | null;
    is_main: boolean | number;
    is_active: boolean | number;
    entity?: { id: number, legal_name: string };
    addresses?: any[];
    contacts?: any[];
}

export const usePlantStore = defineStore('plant', () => {
    const plants = ref<Plant[]>([]);

    function setPlants(data: Plant[]) {
        plants.value = data;
    }

    function addPlant(item: Plant) {
        plants.value.push(item);
    }

    function updatePlant(updatedItem: Plant) {
        const index = plants.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            plants.value[index] = updatedItem;
        }
    }

    function removePlant(id: number) {
        plants.value = plants.value.filter(a => a.id !== id);
    }

    return {
        plants,
        setPlants,
        addPlant,
        updatePlant,
        removePlant
    };
});
