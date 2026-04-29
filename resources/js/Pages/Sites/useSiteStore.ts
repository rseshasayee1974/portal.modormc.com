import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Site {
    id: number;
    plant_id: number;
    name: string;
    code: string | null;
    type: 'loading' | 'unloading';
    is_restricted: boolean | number;
    latitude: string | number | null;
    longitude: string | number | null;
    plant?: { id: number, name: string };
}

export const useSiteStore = defineStore('site', () => {
    const sites = ref<Site[]>([]);

    function setSites(data: Site[]) {
        sites.value = data;
    }

    function addSite(item: Site) {
        sites.value.push(item);
    }

    function updateSite(updatedItem: Site) {
        const index = sites.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            sites.value[index] = updatedItem;
        }
    }

    function removeSite(id: number) {
        sites.value = sites.value.filter(a => a.id !== id);
    }

    return {
        sites,
        setSites,
        addSite,
        updateSite,
        removeSite
    };
});
