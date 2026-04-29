import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface EntityType {
    id: number;
    type: string;
}

export const useEntityTypeStore = defineStore('entityType', () => {
    const entityTypes = ref<EntityType[]>([]);

    function setEntityTypes(data: EntityType[]) {
        entityTypes.value = data;
    }

    function addEntityType(item: EntityType) {
        entityTypes.value.push(item);
    }

    function updateEntityType(updatedItem: EntityType) {
        const index = entityTypes.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            entityTypes.value[index] = updatedItem;
        }
    }

    function removeEntityType(id: number) {
        entityTypes.value = entityTypes.value.filter(a => a.id !== id);
    }

    return {
        entityTypes,
        setEntityTypes,
        addEntityType,
        updateEntityType,
        removeEntityType
    };
});
